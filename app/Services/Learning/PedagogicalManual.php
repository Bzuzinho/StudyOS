<?php

namespace App\Services\Learning;

class PedagogicalManual
{
    /**
     * Keep assessment questions out of the reading experience. Imported
     * exercises remain available in the practice repository.
     */
    public function sections(string $markdown): array
    {
        $parts = preg_split('/(?=^#{1,3}\s+[^\r\n]+)/mu', trim($markdown)) ?: [];
        $sections = [];
        foreach ($parts as $part) {
            if (trim($part) === '') {
                continue;
            }
            if (preg_match('/^#{1,3}\s+(.+)\R?/u', $part, $match)) {
                $title = trim($match[1], " \t\r\n*");
                if ($this->isExercise($title)) {
                    continue;
                }
                $content = trim(substr($part, strlen($match[0])));
            } else {
                $title = '';
                $content = trim($part);
            }
            if ($content === '' && $title === '') {
                continue;
            }
            $sections[] = [
                'title' => $title,
                'body' => $content,
                'kind' => $this->kind($title),
            ];
        }

        return $sections;
    }

    public function review(array $sections): array
    {
        $review = array_values(array_filter($sections, fn ($section) => $section['kind'] === 'summary'));
        if ($review !== []) {
            return $review;
        }

        // Legacy packages may lack a synthesis; avoid claiming that the
        // full theory is a ready-made summary.
        return [];
    }

    private function isExercise(string $title): bool
    {
        return (bool) preg_match('/\b(exerc[ií]cios?|quest[oõ]es?\s+de\s+(revis[aã]o|aplica[cç][aã]o)|teste\s+de\s+conhecimentos|autoe?avalia[cç][aã]o|crit[eé]rios\s+de\s+(corre[cç][aã]o|classifica[cç][aã]o))\b/iu', $title);
    }

    private function kind(string $title): string
    {
        return match (true) {
            (bool) preg_match('/s[ií]ntese|resumo|pontos.chave|essencial|mapa\s+mental/iu', $title) => 'summary',
            (bool) preg_match('/objetivos/iu', $title) => 'objectives',
            (bool) preg_match('/exemplos?|casos?\s+pr[aá]ticos?/iu', $title) => 'example',
            (bool) preg_match('/aten[cç][aã]o|n[aã]o\s+confundir|erros?\s+frequentes?/iu', $title) => 'warning',
            (bool) preg_match('/compara[cç][aã]o|diferen[cç]as?|rela[cç][aã]o/iu', $title) => 'comparison',
            default => 'theory',
        };
    }
}
