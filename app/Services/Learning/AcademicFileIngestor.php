<?php

namespace App\Services\Learning;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AcademicFileIngestor
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'pptx', 'docx', 'txt', 'md'];

    /**
     * @return array{
     *   disk:string,
     *   path:string,
     *   original_filename:string,
     *   mime_type:string,
     *   size_bytes:int,
     *   sha256:string,
     *   extension:string
     * }
     */
    public function ingest(UploadedFile $file, int $courseId): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'Formato não suportado. Usa PDF, PPTX, DOCX, TXT ou MD.',
            ]);
        }

        $realPath = $file->getRealPath();

        if (! $realPath) {
            throw new RuntimeException('O ficheiro temporário do upload não está disponível.');
        }

        $disk = config('filesystems.default', 'local');
        $uuid = (string) Str::uuid();
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'material';
        }

        $filename = $base.'.'.$extension;
        $directory = 'materials/'.$courseId.'/'.$uuid;
        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'private'],
        );

        if (! $path) {
            throw new RuntimeException('Não foi possível guardar o ficheiro no armazenamento académico.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $realPath) ?: hash('sha256', $uuid),
            'extension' => $extension,
        ];
    }

    public function delete(?array $ingestion): void
    {
        if (! $ingestion || empty($ingestion['disk']) || empty($ingestion['path'])) {
            return;
        }

        Storage::disk($ingestion['disk'])->delete($ingestion['path']);
    }
}
