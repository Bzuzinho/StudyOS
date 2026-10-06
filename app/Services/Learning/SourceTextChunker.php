<?php

namespace App\Services\Learning;

class SourceTextChunker
{
    /**
     * @return list<string>
     */
    public function chunk(string $text, int $targetCharacters = 1200): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        if ($text === '') {
            return [];
        }

        $lines = array_values(array_filter(array_map(
            fn (string $line) => $this->normalizeWhitespace($line),
            explode("\n", $text),
        ), fn (string $line) => $line !== ''));

        if (
            count($lines) >= 3
            && max(array_map(fn (string $line) => mb_strlen($line), $lines)) <= 220
        ) {
            return $lines;
        }

        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [$text];
        $paragraphs = array_values(array_filter(array_map(
            fn (string $paragraph) => $this->normalizeWhitespace($paragraph),
            $paragraphs,
        ), fn (string $paragraph) => $paragraph !== ''));

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            foreach ($this->splitOversized($paragraph, $targetCharacters) as $piece) {
                $candidate = trim($buffer === '' ? $piece : $buffer.' '.$piece);

                if ($buffer !== '' && mb_strlen($candidate) > $targetCharacters) {
                    $chunks[] = $buffer;
                    $buffer = $piece;
                } else {
                    $buffer = $candidate;
                }
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_values(array_filter($chunks));
    }

    /**
     * @return list<string>
     */
    private function splitOversized(string $text, int $targetCharacters): array
    {
        if (mb_strlen($text) <= $targetCharacters) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];

        if (count($sentences) > 1) {
            $pieces = [];
            $buffer = '';

            foreach ($sentences as $sentence) {
                $candidate = trim($buffer === '' ? $sentence : $buffer.' '.$sentence);

                if ($buffer !== '' && mb_strlen($candidate) > $targetCharacters) {
                    $pieces[] = $buffer;
                    $buffer = $sentence;
                } else {
                    $buffer = $candidate;
                }
            }

            if ($buffer !== '') {
                $pieces[] = $buffer;
            }

            return $pieces;
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $pieces = [];
        $buffer = '';

        foreach ($words as $word) {
            $candidate = trim($buffer === '' ? $word : $buffer.' '.$word);

            if ($buffer !== '' && mb_strlen($candidate) > $targetCharacters) {
                $pieces[] = $buffer;
                $buffer = $word;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $pieces[] = $buffer;
        }

        return $pieces;
    }

    private function normalizeWhitespace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
