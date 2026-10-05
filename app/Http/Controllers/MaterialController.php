<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialController
{
    public function index(): View
    {
        return view('materials.index', [
            'materials' => Material::query()
                ->with(['course', 'versions'])
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $externalId = (string) Str::uuid();

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

        MaterialVersion::query()->create([
            'material_id' => $material->id,
            'source_hash' => hash('sha256', $externalId.'|'.$material->title.'|'.$material->url),
            'version_label' => 'Criado manualmente',
            'observed_at' => now(),
            'metadata' => ['manual' => true],
        ]);

        return redirect()->route('materials.index')->with('status', 'Material adicionado.');
    }

    public function edit(Material $material): View
    {
        $this->ensureManual($material);

        return view('materials.form', [
            'material' => $material,
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $this->ensureManual($material);
        $data = $this->validated($request);

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

        MaterialVersion::query()->firstOrCreate(
            [
                'material_id' => $material->id,
                'source_hash' => hash('sha256', $material->title.'|'.$material->url.'|'.($material->metadata['notes'] ?? '')),
            ],
            [
                'version_label' => 'Alteração manual',
                'observed_at' => now(),
                'metadata' => ['manual' => true],
            ],
        );

        return redirect()->route('materials.index')->with('status', 'Material atualizado.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $this->ensureManual($material);
        $material->delete();

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
