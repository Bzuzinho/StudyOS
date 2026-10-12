<?php

namespace App\Http\Controllers;

use App\Jobs\AutoMatchCourseMaterials;
use App\Jobs\ExtractMaterialVersion;
use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Services\Learning\AcademicFileIngestor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseMaterialImportController
{
    public function create(Course $course): View
    {
        return view('materials.bulk', ['course' => $course]);
    }

    public function store(Request $request, Course $course, AcademicFileIngestor $ingestor): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:25'],
            'files.*' => ['required', 'file', 'max:25600', 'mimes:pdf,pptx,docx,txt,md'],
        ]);

        $jobs = [];
        $imported = 0;
        foreach ($data['files'] as $file) {
            $ingestion = $ingestor->ingest($file, $course->id);
            try {
                $version = DB::transaction(function () use ($course, $ingestion): MaterialVersion {
                    $material = Material::create([
                        'course_id' => $course->id,
                        'source' => 'manual',
                        'external_id' => (string) Str::uuid(),
                        'type' => $ingestion['extension'] === 'pptx' ? 'presentation' : 'document',
                        'title' => pathinfo($ingestion['original_filename'], PATHINFO_FILENAME),
                        'status' => 'active',
                        'metadata' => ['manual' => true, 'bulk_import' => true],
                    ]);

                    return MaterialVersion::create([
                        'material_id' => $material->id,
                        'source_hash' => $ingestion['sha256'],
                        'version_label' => 'Importação em lote',
                        'storage_disk' => $ingestion['disk'],
                        'storage_path' => $ingestion['path'],
                        'original_filename' => $ingestion['original_filename'],
                        'mime_type' => $ingestion['mime_type'],
                        'size_bytes' => $ingestion['size_bytes'],
                        'file_sha256' => $ingestion['sha256'],
                        'extraction_status' => 'queued',
                        'extraction_queued_at' => now(),
                        'extraction_attempts' => 0,
                        'observed_at' => now(),
                        'metadata' => ['file_extension' => $ingestion['extension'], 'sections' => []],
                    ]);
                });
            } catch (\Throwable $error) {
                $ingestor->delete($ingestion);
                throw $error;
            }
            $jobs[] = new ExtractMaterialVersion($version->id);
            $imported++;
        }

        // Extraction must finish before semantic matching begins. A single
        // background chain replaces repeated per-topic uploads and UI actions.
        if (config('studyos_ai.enabled') && config('studyos_ai.api_key')) {
            $jobs[] = new AutoMatchCourseMaterials($course->id);
        }
        Bus::chain($jobs)->dispatch();

        return redirect()->route('courses.show', $course, false)
            ->with('status', $imported.' ficheiros recebidos. Extração e organização por tópico iniciadas em segundo plano. Confirma o resultado na UC.');
    }
}
