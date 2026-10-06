<?php

namespace App\Services\Learning;

class CorpusQualityClassifier
{
    public function classify(string $content): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $content));
        $wordCount = count(array_filter(preg_split('/\s+/u', $normalized) ?: []));

        if (mb_strlen($normalized) < 180 || $wordCount < 25) {
            return 'outline_only';
        }

        return 'content';
    }
}
