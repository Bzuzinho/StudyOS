<?php

namespace Tests\Unit;

use App\Services\Moodle\MoodleCourseContentParser;
use PHPUnit\Framework\TestCase;

class MoodleCourseContentParserTest extends TestCase
{
    public function test_it_discovers_files_from_resources_folders_and_other_modules(): void
    {
        $sections = [
            [
                'id' => 10,
                'name' => 'Semana 1',
                'modules' => [
                    [
                        'id' => 101,
                        'name' => 'Introdução à Gestão',
                        'modname' => 'resource',
                        'contents' => [
                            [
                                'type' => 'file',
                                'filename' => 'introducao.pdf',
                                'fileurl' => 'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/10/mod_resource/content/1/introducao.pdf?forcedownload=1',
                                'mimetype' => 'application/pdf',
                                'filesize' => 1200,
                                'timemodified' => 1700000000,
                            ],
                        ],
                    ],
                    [
                        'id' => 102,
                        'name' => 'Fichas',
                        'modname' => 'folder',
                        'contents' => [
                            [
                                'type' => 'file',
                                'filename' => 'ficha-1.pdf',
                                'fileurl' => 'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/11/mod_folder/content/0/ficha-1.pdf',
                            ],
                            [
                                'type' => 'file',
                                'filename' => 'ficha-2.docx',
                                'fileurl' => 'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/11/mod_folder/content/0/ficha-2.docx',
                            ],
                        ],
                    ],
                    [
                        'id' => 103,
                        'name' => 'Conteúdo incorporado',
                        'modname' => 'page',
                        'contents' => [
                            [
                                'type' => 'file',
                                'filename' => 'quadro.png',
                                'fileurl' => 'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/12/mod_page/content/0/quadro.png',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $files = (new MoodleCourseContentParser())->files($sections, '1829');

        self::assertCount(4, $files);
        self::assertSame('Introdução à Gestão', $files[0]['title']);
        self::assertSame('Fichas · ficha-1.pdf', $files[1]['title']);
        self::assertSame('Fichas · ficha-2.docx', $files[2]['title']);
        self::assertSame('page', $files[3]['modname']);
        self::assertSame('Semana 1', $files[0]['section_name']);
        self::assertStringStartsWith('course:1829:module:101:file:', $files[0]['external_id']);
    }

    public function test_query_string_changes_do_not_change_external_identity(): void
    {
        $parser = new MoodleCourseContentParser();

        $base = [
            'id' => 1,
            'name' => 'Tema',
            'modules' => [[
                'id' => 2,
                'name' => 'Slides',
                'contents' => [[
                    'type' => 'file',
                    'filename' => 'slides.pdf',
                    'fileurl' => 'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/3/mod_resource/content/1/slides.pdf?rev=1',
                ]],
            ]],
        ];

        $first = $parser->files([$base], '99');

        $base['modules'][0]['contents'][0]['fileurl'] =
            'https://ead.ulo.pt/2026-27/webservice/pluginfile.php/3/mod_resource/content/1/slides.pdf?rev=2';

        $second = $parser->files([$base], '99');

        self::assertSame($first[0]['external_id'], $second[0]['external_id']);
    }
}
