<?php

namespace App\Services\Learning;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

class AcademicFileExtractor
{
    private const MAX_OFFICE_XML_BYTES = 20_000_000;
    /**
     * @return array{
     *   status:string,
     *   text:?string,
     *   sections:list<array{locator:string,start:int,length:int}>,
     *   engine:string,
     *   error:?string
     * }
     */
    public function extract(string $path, string $extension): array
    {
        $extension = mb_strtolower(trim($extension));

        try {
            $sections = match ($extension) {
                'pdf' => $this->pdfSections($path),
                'pptx' => $this->pptxSections($path),
                'docx' => $this->docxSections($path),
                'txt', 'md' => $this->textSections($path),
                default => throw new RuntimeException("Unsupported academic file extension: {$extension}"),
            };

            $assembled = $this->assemble($sections);

            if ($assembled['text'] === '') {
                return [
                    'status' => $extension === 'pdf' ? 'empty_or_scanned' : 'empty',
                    'text' => null,
                    'sections' => [],
                    'engine' => $this->engine($extension),
                    'error' => $extension === 'pdf'
                        ? 'Não foi encontrado texto pesquisável. O PDF pode ser digitalizado como imagem.'
                        : 'O ficheiro não contém texto extraível.',
                ];
            }

            return [
                'status' => 'extracted',
                'text' => $assembled['text'],
                'sections' => $assembled['sections'],
                'engine' => $this->engine($extension),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'text' => null,
                'sections' => [],
                'engine' => $this->engine($extension),
                'error' => mb_substr($exception->getMessage(), 0, 1000),
            ];
        }
    }

    /**
     * @return list<array{locator:string,content:string}>
     */
    private function pdfSections(string $path): array
    {
        $document = (new PdfParser())->parseFile($path);
        $sections = [];

        foreach ($document->getPages() as $index => $page) {
            $text = $this->normalizeText($page->getText());

            if ($text !== '') {
                $sections[] = [
                    'locator' => 'Página '.($index + 1),
                    'content' => $text,
                ];
            }
        }

        return $sections;
    }

    /**
     * @return list<array{locator:string,content:string}>
     */
    private function pptxSections(string $path): array
    {
        $zip = $this->openZip($path);
        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name && preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $matches) === 1) {
                $slides[(int) $matches[1]] = $name;
            }
        }

        ksort($slides, SORT_NUMERIC);
        $sections = [];
        $uncompressedBytes = 0;

        foreach ($slides as $number => $name) {
            $stat = $zip->statName($name);
            $uncompressedBytes += (int) ($stat['size'] ?? 0);

            if ($uncompressedBytes > self::MAX_OFFICE_XML_BYTES) {
                $zip->close();
                throw new RuntimeException('A apresentação excede o limite seguro de texto descomprimido.');
            }

            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            $text = $this->xmlText($xml, ['</a:p>' => "\n", '</a:t>' => ' ']);

            if ($text !== '') {
                $sections[] = [
                    'locator' => 'Slide '.$number,
                    'content' => $text,
                ];
            }
        }

        $zip->close();

        return $sections;
    }

    /**
     * @return list<array{locator:string,content:string}>
     */
    private function docxSections(string $path): array
    {
        $zip = $this->openZip($path);
        $stat = $zip->statName('word/document.xml');

        if ((int) ($stat['size'] ?? 0) > self::MAX_OFFICE_XML_BYTES) {
            $zip->close();
            throw new RuntimeException('O documento excede o limite seguro de texto descomprimido.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('O DOCX não contém word/document.xml.');
        }

        $text = $this->xmlText($xml, [
            '</w:p>' => "\n\n",
            '</w:tr>' => "\n",
            '</w:t>' => ' ',
            '<w:tab/>' => "\t",
        ]);

        return $text === '' ? [] : [[
            'locator' => 'Documento',
            'content' => $text,
        ]];
    }

    /**
     * @return list<array{locator:string,content:string}>
     */
    private function textSections(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler o ficheiro de texto.');
        }

        $text = $this->normalizeText($contents);

        return $text === '' ? [] : [[
            'locator' => 'Documento',
            'content' => $text,
        ]];
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        $result = $zip->open($path);

        if ($result !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo Office.');
        }

        return $zip;
    }

    private function xmlText(string $xml, array $breaks): string
    {
        $xml = strtr($xml, $breaks);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalizeText($text);
    }

    private function normalizeText(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

            if ($encoding) {
                $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            }
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * @param list<array{locator:string,content:string}> $sections
     * @return array{text:string,sections:list<array{locator:string,start:int,length:int}>}
     */
    private function assemble(array $sections): array
    {
        $text = '';
        $map = [];

        foreach ($sections as $section) {
            $content = $this->normalizeText($section['content']);

            if ($content === '') {
                continue;
            }

            if ($text !== '') {
                $text .= "\n\n";
            }

            $start = mb_strlen($text);
            $text .= $content;

            $map[] = [
                'locator' => $section['locator'],
                'start' => $start,
                'length' => mb_strlen($content),
            ];
        }

        return ['text' => $text, 'sections' => $map];
    }

    private function engine(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'smalot/pdfparser',
            'pptx' => 'zip-office-pptx',
            'docx' => 'zip-office-docx',
            'txt', 'md' => 'plain-text',
            default => 'unknown',
        };
    }
}
