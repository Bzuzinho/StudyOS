<?php

namespace Tests\Unit;

use App\Services\Learning\AcademicFileExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

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

    public function test_docx_text_is_extracted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studyos-docx-');
        @unlink($path);
        $path .= '.docx';

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE) === true);
        $zip->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="urn:test"><w:body><w:p><w:r><w:t>Primeiro conceito.</w:t></w:r></w:p><w:p><w:r><w:t>Segundo conceito explicado.</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        try {
            $result = (new AcademicFileExtractor())->extract($path, 'docx');

            self::assertSame('extracted', $result['status']);
            self::assertSame('Documento', $result['sections'][0]['locator']);
            self::assertStringContainsString('Segundo conceito', $result['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_pptx_preserves_slide_locators(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studyos-pptx-');
        @unlink($path);
        $path .= '.pptx';

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE) === true);
        $zip->addFromString(
            'ppt/slides/slide1.xml',
            '<p:sld xmlns:p="urn:p" xmlns:a="urn:a"><a:p><a:r><a:t>Introdução ao tema</a:t></a:r></a:p></p:sld>',
        );
        $zip->addFromString(
            'ppt/slides/slide2.xml',
            '<p:sld xmlns:p="urn:p" xmlns:a="urn:a"><a:p><a:r><a:t>Desenvolvimento detalhado</a:t></a:r></a:p></p:sld>',
        );
        $zip->close();

        try {
            $result = (new AcademicFileExtractor())->extract($path, 'pptx');

            self::assertSame('extracted', $result['status']);
            self::assertCount(2, $result['sections']);
            self::assertSame('Slide 1', $result['sections'][0]['locator']);
            self::assertSame('Slide 2', $result['sections'][1]['locator']);
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
