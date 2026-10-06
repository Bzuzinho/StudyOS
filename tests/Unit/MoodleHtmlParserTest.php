<?php

namespace Tests\Unit;

use App\Services\Moodle\MoodleHtmlParser;
use PHPUnit\Framework\TestCase;

class MoodleHtmlParserTest extends TestCase
{
    public function test_course_page_discovers_resources_folders_and_direct_files(): void
    {
        $html = <<<'HTML'
        <html><body>
            <a href="/2026-27/mod/resource/view.php?id=101">Slides Introdução File</a>
            <a href="/2026-27/mod/folder/view.php?id=202">Materiais Semana 2 Folder</a>
            <a href="https://ead.ulo.pt/2026-27/pluginfile.php/44/mod_resource/content/1/ficha.pdf">Ficha PDF</a>
        </body></html>
        HTML;

        $items = (new MoodleHtmlParser())->courseItems(
            $html,
            'https://ead.ulo.pt/2026-27/course/view.php?id=1829',
            '1829',
        );

        self::assertCount(3, $items);
        self::assertSame('resource', $items[0]['kind']);
        self::assertSame('Slides Introdução', $items[0]['title']);
        self::assertSame('course:1829:resource:101', $items[0]['external_id']);

        self::assertSame('folder', $items[1]['kind']);
        self::assertSame('course:1829:folder:202', $items[1]['external_id']);

        self::assertSame('file', $items[2]['kind']);
        self::assertSame('Ficha PDF', $items[2]['title']);
    }

    public function test_folder_page_discovers_multiple_pluginfiles_with_stable_ids(): void
    {
        $html = <<<'HTML'
        <html><body>
            <a href="/2026-27/pluginfile.php/77/mod_folder/content/0/Capitulo%201.pdf">Capítulo 1</a>
            <a href="/2026-27/pluginfile.php/77/mod_folder/content/0/Capitulo%202.pdf?forcedownload=1">Capítulo 2</a>
        </body></html>
        HTML;

        $parser = new MoodleHtmlParser();
        $items = $parser->folderFiles(
            $html,
            'https://ead.ulo.pt/2026-27/mod/folder/view.php?id=99',
            '2276',
            '99',
        );

        self::assertCount(2, $items);
        self::assertSame('Capitulo 1.pdf', $items[0]['title']);
        self::assertStringStartsWith('course:2276:folder:99:file:', $items[0]['external_id']);
        self::assertNotSame($items[0]['external_id'], $items[1]['external_id']);
    }

    public function test_embedded_files_are_deduplicated_by_pluginfile_path(): void
    {
        $html = <<<'HTML'
        <html><body>
            <a href="/2026-27/pluginfile.php/55/mod_resource/content/1/slides.pptx">Abrir</a>
            <object data="/2026-27/pluginfile.php/55/mod_resource/content/1/slides.pptx"></object>
        </body></html>
        HTML;

        $files = (new MoodleHtmlParser())->embeddedFiles(
            $html,
            'https://ead.ulo.pt/2026-27/mod/resource/view.php?id=123',
        );

        self::assertCount(1, $files);
        self::assertStringEndsWith('/pluginfile.php/55/mod_resource/content/1/slides.pptx', $files[0]);
    }
}
