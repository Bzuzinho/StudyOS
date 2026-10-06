<?php

namespace App\Services\Learning;

use App\Models\MaterialVersion;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MaterialExtractionService
{
    public function __construct(
        private readonly AcademicFileExtractor $extractor,
        private readonly CorpusBuilder $corpusBuilder,
        private readonly GroundedPracticeGenerator $generator,
    ) {}

    public function process(MaterialVersion $version): MaterialVersion
    {
        $version->loadMissing('material');

        if (! $version->storage_disk || ! $version->storage_path) {
            throw new RuntimeException('A versão não tem ficheiro armazenado para extrair.');
        }

        $metadata = $version->metadata ?? [];
        $metadata['extraction_worker'] = 'database_queue';

        $version->update([
            'extraction_status' => 'processing',
            'extraction_error' => null,
            'extraction_started_at' => now(),
            'extraction_finished_at' => null,
            'extraction_attempts' => ((int) $version->extraction_attempts) + 1,
            'metadata' => $metadata,
        ]);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'studyos-extract-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Não foi possível criar um ficheiro temporário para extração.');
        }

        try {
            $input = Storage::disk($version->storage_disk)->readStream($version->storage_path);

            if (! is_resource($input)) {
                throw new RuntimeException('Não foi possível abrir o ficheiro académico no armazenamento cloud.');
            }

            $output = fopen($temporaryPath, 'wb');

            if (! is_resource($output)) {
                fclose($input);
                throw new RuntimeException('Não foi possível preparar o ficheiro temporário.');
            }

            try {
                stream_copy_to_stream($input, $output);
            } finally {
                fclose($input);
                fclose($output);
            }

            $extension = mb_strtolower((string) ($metadata['file_extension']
                ?? pathinfo((string) $version->original_filename, PATHINFO_EXTENSION)));

            $extraction = $this->extractor->extract($temporaryPath, $extension);
            $content = $this->composeContent($extraction, $version->manual_text);

            $metadata = [
                ...($version->metadata ?? []),
                'sections' => $content['sections'],
                'manual_text_supplied' => $content['manual_text_supplied'],
                'extraction_engine' => $extraction['engine'],
                'extracted_at' => now()->toIso8601String(),
            ];

            $version->update([
                'content_text' => $content['text'],
                'extraction_status' => $extraction['status'],
                'extraction_error' => $extraction['error'],
                'extraction_finished_at' => now(),
                'metadata' => $metadata,
            ]);

            $version = $version->fresh(['material']);

            $this->corpusBuilder->rebuildMaterialVersion($version);

            if ($version->material?->course_id) {
                $this->generator->generateForCourse($version->material->course_id);
            }

            return $version;
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function markPermanentlyFailed(MaterialVersion $version, Throwable $exception): void
    {
        $version->update([
            'extraction_status' => 'failed',
            'extraction_error' => mb_substr($exception->getMessage(), 0, 1000),
            'extraction_finished_at' => now(),
        ]);
    }

    /**
     * @return array{text:?string,sections:array,manual_text_supplied:bool}
     */
    private function composeContent(array $extraction, ?string $manualText): array
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
}
