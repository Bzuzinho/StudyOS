<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractMaterialVersion;
use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Services\Learning\AcademicFileIngestor;
use App\Services\Learning\CorpusBuilder;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MaterialController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'source' => ['nullable', Rule::in(['moodle', 'manual', 'moodle_audit'])],
            'q' => ['nullable', 'string', 'max:200'],
        ]);
        $search = trim($filters['q'] ?? '');

        return view('materials.index', [
            'materials' => Material::query()
                ->with(['course', 'versions' => fn ($query) => $query->withCount([
                    'sourceChunks as active_chunks_count' => fn ($chunks) => $chunks->where('status', 'active'),
                    'sourceChunks as rich_chunks_count' => fn ($chunks) => $chunks->where('status', 'active')->where('quality', 'content'),
                ])])
                ->where('status', 'active')
                ->when($filters['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
                ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->whereLike('title', '%'.$search.'%')
                        ->orWhereHas('versions', fn ($versions) => $versions->whereLike('original_filename', '%'.$search.'%'));
                }))
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(24)
                ->withPath(route('materials.index', [], false))
                ->withQueryString(),
            'courses' => Course::query()->whereHas('materials', fn ($query) => $query->where('status', 'active'))->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('materials.form', [
            'material' => new Material(),
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
            'types' => $this->types(),
            'contentText' => '',
            'latestVersion' => null,
        ]);
    }

    public function store(
        Request $request,
        AcademicFileIngestor $ingestor,
        CorpusBuilder $corpusBuilder,
        GroundedPracticeGenerator $generator,
    ): RedirectResponse {
        $data = $this->validated($request);
        $externalId = (string) Str::uuid();
        $ingestion = null;

        try {
            if ($request->hasFile('file')) {
                $ingestion = $ingestor->ingest($request->file('file'), (int) $data['course_id']);
            }

            [$material, $version] = DB::transaction(function () use ($data, $externalId, $ingestion) {
                $material = Material::query()->create([
                    'course_id' => $data['course_id'],
                    'source' => 'manual',
                    'external_id' => $externalId,
                    'type' => $data['type'],
                    'title' => $data['title'],
                    'url' => $data['url'] ?: null,
                    'status' => 'active',
                    'metadata' => [
                        'notes' => $data['notes'] ?: null,
                        'manual' => true,
                    ],
                ]);

                $version = MaterialVersion::query()->create(
                    $ingestion
                        ? $this->queuedFileVersionAttributes(
                            $material->id,
                            $ingestion,
                            $data['content_text'] ?? null,
                            'Upload · '.$ingestion['original_filename'],
                        )
                        : $this->manualVersionAttributes(
                            $material->id,
                            $data['content_text'] ?? null,
                            'Criado manualmente',
                        ),
                );

                return [$material, $version];
            });
        } catch (Throwable $exception) {
            $ingestor->delete($ingestion);
            throw $exception;
        }

        if ($version->storage_path) {
            ExtractMaterialVersion::dispatch($version->id)->afterCommit();
        } else {
            $corpusBuilder->rebuildMaterialVersion($version);
            $generator->generateForCourse($material->course_id);
        }

        return redirect()->route('materials.index')->with(
            'status',
            $this->successMessage($version, created: true),
        );
    }

    public function edit(Material $material): View
    {
        $this->ensureManual($material);
        $latestVersion = $material->versions()->first();

        return view('materials.form', [
            'material' => $material,
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
            'types' => $this->types(),
            'contentText' => $latestVersion?->storage_path ? '' : ($latestVersion?->manual_text ?? $latestVersion?->content_text ?? ''),
            'latestVersion' => $latestVersion,
        ]);
    }

    public function update(
        Request $request,
        Material $material,
        AcademicFileIngestor $ingestor,
        CorpusBuilder $corpusBuilder,
        GroundedPracticeGenerator $generator,
    ): RedirectResponse {
        $this->ensureManual($material);
        $data = $this->validated($request);
        $latestVersion = $material->versions()->first();
        $previousCourseId = $material->course_id;
        $ingestion = null;
        $shouldQueue = false;

        try {
            if ($request->hasFile('file')) {
                $ingestion = $ingestor->ingest($request->file('file'), (int) $data['course_id']);
            }

            [$version, $courseId, $versionCreated] = DB::transaction(function () use (
                $material,
                $data,
                $latestVersion,
                $ingestion,
                &$shouldQueue,
            ) {
                $material->update([
                    'course_id' => $data['course_id'],
                    'type' => $data['type'],
                    'title' => $data['title'],
                    'url' => $data['url'] ?: null,
                    'metadata' => [
                        ...($material->metadata ?? []),
                        'notes' => $data['notes'] ?: null,
                        'manual' => true,
                    ],
                ]);

                if ($ingestion) {
                    $attributes = $this->queuedFileVersionAttributes(
                        $material->id,
                        $ingestion,
                        $data['content_text'] ?? null,
                        'Upload · '.$ingestion['original_filename'],
                    );
                    $shouldQueue = true;
                } elseif ($latestVersion?->storage_path && trim((string) ($data['content_text'] ?? '')) !== '') {
                    $manualText = $this->appendManualText(
                        $latestVersion->manual_text,
                        $data['content_text'],
                    );
                    $attributes = $this->queuedInheritedFileVersionAttributes(
                        $material->id,
                        $latestVersion,
                        $manualText,
                    );
                    $shouldQueue = true;
                } elseif ($latestVersion?->storage_path) {
                    $shouldQueue = false;

                    return [$latestVersion, $material->course_id, false];
                } else {
                    $attributes = $this->manualVersionAttributes(
                        $material->id,
                        $data['content_text'] ?? null,
                        'Alteração manual',
                    );
                }

                $version = MaterialVersion::query()
                    ->where('material_id', $material->id)
                    ->where('source_hash', $attributes['source_hash'])
                    ->first();

                $versionCreated = false;

                if (! $version) {
                    $version = MaterialVersion::query()->create($attributes);
                    $versionCreated = true;
                } elseif ($version->storage_path) {
                    if (in_array($version->extraction_status, ['extracted', 'processing'], true)) {
                        $shouldQueue = false;
                    } else {
                        $version->update([
                            'extraction_status' => 'queued',
                            'extraction_error' => null,
                            'extraction_queued_at' => now(),
                            'extraction_started_at' => null,
                            'extraction_finished_at' => null,
                        ]);
                        $shouldQueue = true;
                    }
                }

                return [$version, $material->course_id, $versionCreated];
            });

            if ($ingestion && ! $versionCreated) {
                $ingestor->delete($ingestion);
                $ingestion = null;
            }
        } catch (Throwable $exception) {
            $ingestor->delete($ingestion);
            throw $exception;
        }

        if ($version->storage_path && $shouldQueue) {
            ExtractMaterialVersion::dispatch($version->id)->afterCommit();
        } else {
            $corpusBuilder->rebuildMaterialVersion($version);
            $generator->generateForCourse($courseId);

            if ($previousCourseId !== $courseId) {
                $generator->generateForCourse($previousCourseId);
            }
        }

        return redirect()->route('materials.index')->with(
            'status',
            $this->successMessage($version, created: false),
        );
    }

    public function reprocess(Material $material, MaterialVersion $version): RedirectResponse
    {
        abort_unless($version->material_id === $material->id, 404);
        abort_unless($version->storage_disk && $version->storage_path, 404);

        $version->update([
            'extraction_status' => 'queued',
            'extraction_error' => null,
            'extraction_queued_at' => now(),
            'extraction_started_at' => null,
            'extraction_finished_at' => null,
        ]);

        ExtractMaterialVersion::dispatch($version->id)->afterCommit();

        return redirect()->route('materials.index')
            ->with('status', 'O ficheiro voltou à fila de extração.');
    }

    public function download(Material $material, MaterialVersion $version)
    {
        abort_unless($version->material_id === $material->id, 404);
        abort_unless($version->storage_disk && $version->storage_path, 404);

        return Storage::disk($version->storage_disk)->download(
            $version->storage_path,
            $version->original_filename ?: basename($version->storage_path),
            ['Content-Type' => $version->mime_type ?: 'application/octet-stream'],
        );
    }

    public function destroy(Material $material, GroundedPracticeGenerator $generator): RedirectResponse
    {
        $this->ensureManual($material);
        $courseId = $material->course_id;

        $storedFiles = $material->versions()
            ->whereNotNull('storage_path')
            ->get(['storage_disk', 'storage_path'])
            ->map(fn (MaterialVersion $version) => [
                'disk' => $version->storage_disk,
                'path' => $version->storage_path,
            ])
            ->unique(fn (array $file) => ($file['disk'] ?? '').'|'.($file['path'] ?? ''))
            ->values();

        $material->delete();

        foreach ($storedFiles as $file) {
            try {
                if ($file['disk'] && $file['path']) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            } catch (Throwable) {
                // The academic record is already gone. A stale private object is safer
                // than turning an otherwise successful delete into a user-facing error.
            }
        }

        $generator->generateForCourse($courseId);

        return redirect()->route('materials.index')->with('status', 'Material eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'content_text' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'max:25600'],
        ]);
    }

    private function manualVersionAttributes(int $materialId, ?string $manualText, string $label): array
    {
        $manualText = trim((string) $manualText);
        $sections = $manualText === '' ? [] : [[
            'locator' => 'Texto adicionado no StudyOS',
            'start' => 0,
            'length' => mb_strlen($manualText),
        ]];

        return [
            'material_id' => $materialId,
            'source_hash' => hash('sha256', 'no-file|'.hash('sha256', $manualText)),
            'version_label' => $label,
            'content_text' => $manualText !== '' ? $manualText : null,
            'manual_text' => $manualText !== '' ? $manualText : null,
            'storage_disk' => null,
            'storage_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'file_sha256' => null,
            'extraction_status' => $manualText !== '' ? 'manual_text' : 'not_applicable',
            'extraction_error' => null,
            'extraction_queued_at' => null,
            'extraction_started_at' => null,
            'extraction_finished_at' => $manualText !== '' ? now() : null,
            'extraction_attempts' => 0,
            'observed_at' => now(),
            'metadata' => [
                'manual' => true,
                'sections' => $sections,
                'manual_text_supplied' => $manualText !== '',
                'extraction_engine' => null,
                'file_extension' => null,
            ],
        ];
    }

    private function queuedFileVersionAttributes(
        int $materialId,
        array $ingestion,
        ?string $manualText,
        string $label,
    ): array {
        $manualText = trim((string) $manualText);

        return [
            'material_id' => $materialId,
            'source_hash' => hash(
                'sha256',
                $ingestion['sha256'].'|manual:'.hash('sha256', $manualText),
            ),
            'version_label' => $label,
            'content_text' => null,
            'manual_text' => $manualText !== '' ? $manualText : null,
            'storage_disk' => $ingestion['disk'],
            'storage_path' => $ingestion['path'],
            'original_filename' => $ingestion['original_filename'],
            'mime_type' => $ingestion['mime_type'],
            'size_bytes' => $ingestion['size_bytes'],
            'file_sha256' => $ingestion['sha256'],
            'extraction_status' => 'queued',
            'extraction_error' => null,
            'extraction_queued_at' => now(),
            'extraction_started_at' => null,
            'extraction_finished_at' => null,
            'extraction_attempts' => 0,
            'observed_at' => now(),
            'metadata' => [
                'manual' => true,
                'sections' => [],
                'manual_text_supplied' => $manualText !== '',
                'extraction_engine' => null,
                'file_extension' => $ingestion['extension'],
            ],
        ];
    }

    private function queuedInheritedFileVersionAttributes(
        int $materialId,
        MaterialVersion $previous,
        string $manualText,
    ): array {
        return [
            'material_id' => $materialId,
            'source_hash' => hash(
                'sha256',
                $previous->file_sha256.'|manual:'.hash('sha256', $manualText),
            ),
            'version_label' => 'Conteúdo adicional',
            'content_text' => null,
            'manual_text' => $manualText !== '' ? $manualText : null,
            'storage_disk' => $previous->storage_disk,
            'storage_path' => $previous->storage_path,
            'original_filename' => $previous->original_filename,
            'mime_type' => $previous->mime_type,
            'size_bytes' => $previous->size_bytes,
            'file_sha256' => $previous->file_sha256,
            'extraction_status' => 'queued',
            'extraction_error' => null,
            'extraction_queued_at' => now(),
            'extraction_started_at' => null,
            'extraction_finished_at' => null,
            'extraction_attempts' => 0,
            'observed_at' => now(),
            'metadata' => [
                'manual' => true,
                'sections' => [],
                'manual_text_supplied' => $manualText !== '',
                'extraction_engine' => null,
                'file_extension' => $previous->metadata['file_extension']
                    ?? pathinfo((string) $previous->original_filename, PATHINFO_EXTENSION),
                'inherited_storage_from_version' => $previous->id,
            ],
        ];
    }

    private function appendManualText(?string $existing, ?string $additional): string
    {
        $existing = trim((string) $existing);
        $additional = trim((string) $additional);

        return trim($existing.($existing !== '' && $additional !== '' ? "\n\n" : '').$additional);
    }

    private function successMessage(MaterialVersion $version, bool $created): string
    {
        $verb = $created ? 'adicionado' : 'atualizado';

        return match ($version->extraction_status) {
            'queued' => "Material {$verb}; ficheiro guardado online e colocado na fila de extração.",
            'processing' => "Material {$verb}; extração em curso no servidor.",
            'extracted' => "Material {$verb}; ficheiro e corpus prontos.",
            'empty_or_scanned' => "Material {$verb}; ficheiro guardado, mas o PDF não tem texto pesquisável.",
            'empty' => "Material {$verb}; ficheiro guardado, mas sem texto extraível.",
            'failed' => "Material {$verb}; ficheiro guardado, mas a extração falhou.",
            'manual_text' => "Material {$verb} e texto indexado no corpus.",
            default => "Material {$verb}.",
        };
    }

    private function ensureManual(Material $material): void
    {
        abort_unless($material->source === 'manual', 404);
    }

    private function types(): array
    {
        return [
            'document' => 'Documento',
            'presentation' => 'Apresentação',
            'folder' => 'Pasta',
            'link' => 'Link',
            'note' => 'Nota',
            'other' => 'Outro',
        ];
    }
}
