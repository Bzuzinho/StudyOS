<?php

namespace App\Http\Controllers;

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
    public function index(): View
    {
        return view('materials.index', [
            'materials' => Material::query()
                ->with(['course', 'versions.sourceChunks'])
                ->where('status', 'active')
                ->orderByDesc('updated_at')
                ->get(),
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

            $content = $this->composeContent(
                $ingestion['extraction'] ?? null,
                $data['content_text'] ?? null,
            );

            [$material, $version] = DB::transaction(function () use ($data, $externalId, $ingestion, $content) {
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
                    $this->versionAttributes(
                        materialId: $material->id,
                        label: $ingestion
                            ? 'Upload · '.$ingestion['original_filename']
                            : 'Criado manualmente',
                        content: $content,
                        ingestion: $ingestion,
                    ),
                );

                return [$material, $version];
            });
        } catch (Throwable $exception) {
            $ingestor->delete($ingestion);
            throw $exception;
        }

        $corpusBuilder->rebuildMaterialVersion($version);
        $generator->generateForCourse($material->course_id);

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
            'contentText' => $latestVersion?->storage_path ? '' : ($latestVersion?->content_text ?? ''),
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
        $ingestion = null;

        try {
            if ($request->hasFile('file')) {
                $ingestion = $ingestor->ingest($request->file('file'), (int) $data['course_id']);
            }

            $versionPlan = $this->updatedVersionPlan(
                latestVersion: $latestVersion,
                ingestion: $ingestion,
                manualText: $data['content_text'] ?? null,
            );

            [$version, $courseId, $versionCreated] = DB::transaction(function () use ($material, $data, $versionPlan) {
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

                $attributes = $this->versionAttributes(
                    materialId: $material->id,
                    label: $versionPlan['label'],
                    content: $versionPlan['content'],
                    ingestion: $versionPlan['ingestion'],
                    inheritedFile: $versionPlan['inherited_file'],
                );

                $version = MaterialVersion::query()
                    ->where('material_id', $material->id)
                    ->where('source_hash', $attributes['source_hash'])
                    ->first();

                $versionCreated = false;

                if (! $version) {
                    $version = MaterialVersion::query()->create($attributes);
                    $versionCreated = true;
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

        $corpusBuilder->rebuildMaterialVersion($version);
        $generator->generateForCourse($courseId);

        return redirect()->route('materials.index')->with(
            'status',
            $this->successMessage($version, created: false),
        );
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
                // The database record is already gone. A stale object is safer than
                // failing the user-facing delete after the academic data was removed.
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

    /**
     * @return array{text:?string,sections:array,manual_text_supplied:bool}
     */
    private function composeContent(?array $extraction, ?string $manualText): array
    {
        $text = trim((string) ($extraction['text'] ?? ''));
        $sections = $extraction['sections'] ?? [];
        $manualText = trim((string) $manualText);

        if ($manualText !== '') {
            if ($text !== '') {
                $text .= "\n\n";
            }

            $start = mb_strlen($text);
            $text .= $manualText;
            $sections[] = [
                'locator' => 'Texto adicionado no StudyOS',
                'start' => $start,
                'length' => mb_strlen($manualText),
            ];
        }

        return [
            'text' => $text !== '' ? $text : null,
            'sections' => $sections,
            'manual_text_supplied' => $manualText !== '',
        ];
    }

    private function updatedVersionPlan(
        ?MaterialVersion $latestVersion,
        ?array $ingestion,
        ?string $manualText,
    ): array {
        if ($ingestion) {
            return [
                'label' => 'Upload · '.$ingestion['original_filename'],
                'content' => $this->composeContent($ingestion['extraction'], $manualText),
                'ingestion' => $ingestion,
                'inherited_file' => null,
            ];
        }

        if ($latestVersion?->storage_path) {
            $manualText = trim((string) $manualText);

            if ($manualText === '') {
                return [
                    'label' => $latestVersion->version_label ?: 'Versão atual',
                    'content' => [
                        'text' => $latestVersion->content_text,
                        'sections' => $latestVersion->metadata['sections'] ?? [],
                        'manual_text_supplied' => (bool) ($latestVersion->metadata['manual_text_supplied'] ?? false),
                    ],
                    'ingestion' => null,
                    'inherited_file' => $this->fileAttributesFromVersion($latestVersion),
                ];
            }

            $base = trim((string) $latestVersion->content_text);
            $sections = $latestVersion->metadata['sections'] ?? [];

            if ($base !== '') {
                $base .= "\n\n";
            }

            $start = mb_strlen($base);
            $base .= $manualText;
            $sections[] = [
                'locator' => 'Texto adicional no StudyOS',
                'start' => $start,
                'length' => mb_strlen($manualText),
            ];

            return [
                'label' => 'Conteúdo adicional',
                'content' => [
                    'text' => $base,
                    'sections' => $sections,
                    'manual_text_supplied' => true,
                ],
                'ingestion' => null,
                'inherited_file' => $this->fileAttributesFromVersion($latestVersion),
            ];
        }

        return [
            'label' => 'Alteração manual',
            'content' => $this->composeContent(null, $manualText),
            'ingestion' => null,
            'inherited_file' => null,
        ];
    }

    private function versionAttributes(
        int $materialId,
        string $label,
        array $content,
        ?array $ingestion,
        ?array $inheritedFile = null,
    ): array {
        $file = $ingestion
            ? [
                'storage_disk' => $ingestion['disk'],
                'storage_path' => $ingestion['path'],
                'original_filename' => $ingestion['original_filename'],
                'mime_type' => $ingestion['mime_type'],
                'size_bytes' => $ingestion['size_bytes'],
                'file_sha256' => $ingestion['sha256'],
                'extraction_status' => $ingestion['extraction']['status'],
                'extraction_error' => $ingestion['extraction']['error'],
                'engine' => $ingestion['extraction']['engine'],
                'extension' => $ingestion['extension'],
            ]
            : ($inheritedFile ?? [
                'storage_disk' => null,
                'storage_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'size_bytes' => null,
                'file_sha256' => null,
                'extraction_status' => $content['text'] ? 'manual_text' : 'not_applicable',
                'extraction_error' => null,
                'engine' => null,
                'extension' => null,
            ]);

        $contentHash = hash('sha256', (string) ($content['text'] ?? ''));
        $sourceHash = hash(
            'sha256',
            ($file['file_sha256'] ?? 'no-file').'|'.$contentHash.'|'.json_encode($content['sections']),
        );

        return [
            'material_id' => $materialId,
            'source_hash' => $sourceHash,
            'version_label' => $label,
            'content_text' => $content['text'],
            'storage_disk' => $file['storage_disk'],
            'storage_path' => $file['storage_path'],
            'original_filename' => $file['original_filename'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
            'file_sha256' => $file['file_sha256'],
            'extraction_status' => $file['extraction_status'],
            'extraction_error' => $file['extraction_error'],
            'observed_at' => now(),
            'metadata' => [
                'manual' => true,
                'sections' => $content['sections'],
                'manual_text_supplied' => $content['manual_text_supplied'],
                'extraction_engine' => $file['engine'],
                'file_extension' => $file['extension'],
            ],
        ];
    }

    private function fileAttributesFromVersion(MaterialVersion $version): array
    {
        return [
            'storage_disk' => $version->storage_disk,
            'storage_path' => $version->storage_path,
            'original_filename' => $version->original_filename,
            'mime_type' => $version->mime_type,
            'size_bytes' => $version->size_bytes,
            'file_sha256' => $version->file_sha256,
            'extraction_status' => $version->extraction_status,
            'extraction_error' => $version->extraction_error,
            'engine' => $version->metadata['extraction_engine'] ?? null,
            'extension' => $version->metadata['file_extension'] ?? null,
        ];
    }

    private function successMessage(MaterialVersion $version, bool $created): string
    {
        $verb = $created ? 'adicionado' : 'atualizado';

        return match ($version->extraction_status) {
            'extracted' => "Material {$verb}; ficheiro guardado online e texto extraído para o corpus.",
            'empty_or_scanned' => "Material {$verb}; ficheiro guardado, mas o PDF não tem texto pesquisável.",
            'empty' => "Material {$verb}; ficheiro guardado, mas sem texto extraível.",
            'failed' => "Material {$verb}; ficheiro guardado, mas a extração de texto falhou.",
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
