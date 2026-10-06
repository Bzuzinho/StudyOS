<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Services\Learning\CorpusBuilder;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
        ]);
    }

    public function store(
        Request $request,
        CorpusBuilder $corpusBuilder,
        GroundedPracticeGenerator $generator,
    ): RedirectResponse {
        $data = $this->validated($request);
        $externalId = (string) Str::uuid();

        [$material, $version] = DB::transaction(function () use ($data, $externalId) {
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

            $version = MaterialVersion::query()->create([
                'material_id' => $material->id,
                'source_hash' => hash(
                    'sha256',
                    $externalId.'|'.$material->title.'|'.$material->url.'|'.($data['content_text'] ?? ''),
                ),
                'version_label' => 'Criado manualmente',
                'content_text' => $data['content_text'] ?: null,
                'observed_at' => now(),
                'metadata' => ['manual' => true],
            ]);

            return [$material, $version];
        });

        if ($version->content_text) {
            $corpusBuilder->rebuildMaterialVersion($version);
            $generator->generateForCourse($material->course_id);
        }

        return redirect()->route('materials.index')->with(
            'status',
            $version->content_text
                ? 'Material adicionado e conteúdo indexado para prática fundamentada.'
                : 'Material adicionado.',
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
            'contentText' => $latestVersion?->content_text ?? '',
        ]);
    }

    public function update(
        Request $request,
        Material $material,
        CorpusBuilder $corpusBuilder,
        GroundedPracticeGenerator $generator,
    ): RedirectResponse {
        $this->ensureManual($material);
        $data = $this->validated($request);

        [$version, $courseId] = DB::transaction(function () use ($material, $data) {
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

            $sourceHash = hash(
                'sha256',
                $material->title.'|'.$material->url.'|'.($material->metadata['notes'] ?? '').'|'.($data['content_text'] ?? ''),
            );

            $version = MaterialVersion::query()->firstOrCreate(
                [
                    'material_id' => $material->id,
                    'source_hash' => $sourceHash,
                ],
                [
                    'version_label' => 'Alteração manual',
                    'content_text' => $data['content_text'] ?: null,
                    'observed_at' => now(),
                    'metadata' => ['manual' => true],
                ],
            );

            if ($version->wasRecentlyCreated === false && $version->content_text !== ($data['content_text'] ?: null)) {
                $version->update(['content_text' => $data['content_text'] ?: null]);
            }

            return [$version, $material->course_id];
        });

        if ($version->content_text) {
            $corpusBuilder->rebuildMaterialVersion($version);
        }

        $generator->generateForCourse($courseId);

        return redirect()->route('materials.index')->with(
            'status',
            $version->content_text
                ? 'Material atualizado e corpus revisto.'
                : 'Material atualizado.',
        );
    }

    public function destroy(Material $material, GroundedPracticeGenerator $generator): RedirectResponse
    {
        $this->ensureManual($material);
        $courseId = $material->course_id;
        $material->delete();
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
        ]);
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
