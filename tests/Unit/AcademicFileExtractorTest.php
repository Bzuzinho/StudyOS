<?php

namespace Tests\Unit;

use App\Services\Learning\AcademicFileExtractor;
use PHPUnit\Framework\TestCase;

class AcademicFileExtractorTest extends TestCase
{
    public function test_plain_text_is_extracted_with_a_document_locator(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studyos-text-');
        file_put_contents($path, "Tema principal\n\nExplicação com detalhe suficiente.");

        try {
            $result = (new AcademicFileExtractor())->extract($path, 'txt');

            self::assertSame('extracted', $result['status']);
            self::assertSame('Documento', $result['sections'][0]['locator']);
            self::assertStringContainsString('Explicação', $result['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_text_file_is_not_marked_as_extracted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studyos-empty-');
        file_put_contents($path, " \n ");

        try {
            $result = (new AcademicFileExtractor())->extract($path, 'txt');

            self::assertSame('empty', $result['status']);
            self::assertNull($result['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_unsupported_extension_fails_without_inventing_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studyos-unsupported-');
        file_put_contents($path, 'conteúdo');

        try {
            $result = (new AcademicFileExtractor())->extract($path, 'exe');

            self::assertSame('failed', $result['status']);
            self::assertNull($result['text']);
        } finally {
            @unlink($path);
        }
    }
}
