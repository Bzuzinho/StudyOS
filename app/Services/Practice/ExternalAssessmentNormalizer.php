<?php

namespace App\Services\Practice;

use App\Models\Course;
use App\Services\Learning\Curriculum;
use DateTimeImmutable;

class ExternalAssessmentNormalizer
{
    public const SCHEMA_V1 = 'studyos.external-assessment.v1';
    public const SCHEMA_V2 = 'studyos.external-assessment.v2';

    public function normalize(Course $course, string $input): array
    {
        $input = trim($input);
        $decoded = $this->decodeJson($input);

        if (is_array($decoded)) {
            $result = $this->normalizeStructured($course, $decoded);
            $result['input_format'] = 'json';

            return $result;
        }

        $result = $this->normalizePlainText($course, $input);
        $result['input_format'] = 'text';

        return $result;
    }

    private function normalizeStructured(Course $course, array $source): array
    {
        $schema = (string) ($source['schema'] ?? '');
        if (! in_array($schema, [self::SCHEMA_V1, self::SCHEMA_V2], true)) {
            return $this->invalid($course, $source, ['schema'], 'Esquema de relatório não suportado.');
        }

        $topics = [];
        foreach (($source['topics'] ?? []) as $topic) {
            if (! is_array($topic)) {
                continue;
            }

            $feedback = trim((string) ($topic['feedback'] ?? ''));
            $evidenceCount = $schema === self::SCHEMA_V1
                ? ($this->feedbackMeansNoEvidence($feedback) ? 0 : 1)
                : $this->integerOrNull($topic['evidence_count'] ?? null);
            $score = $this->numericOrNull($topic['score_percent'] ?? null);

            if ($evidenceCount === 0) {
                $score = null;
            }

            $topics[] = [
                'number' => trim((string) ($topic['number'] ?? '')),
                'score_percent' => $score,
                'evidence_count' => $evidenceCount,
                'feedback' => $feedback,
            ];
        }

        $report = [
            'schema' => self::SCHEMA_V2,
            'source_schema' => $schema,
            'assessment_id' => trim((string) ($source['assessment_id'] ?? '')),
            'academic_code' => trim((string) ($source['academic_code'] ?? '')),
            'assessed_on' => trim((string) ($source['assessed_on'] ?? '')),
            'activity_type' => $schema === self::SCHEMA_V1
                ? 'assessment'
                : trim((string) ($source['activity_type'] ?? '')),
            'score_20' => $this->numericOrNull($source['score_20'] ?? null),
            'topics' => $topics,
            'strengths' => $this->stringList($source['strengths'] ?? []),
            'difficulties' => $this->stringList($source['difficulties'] ?? []),
            'conceptual_errors' => $this->stringList($source['conceptual_errors'] ?? []),
            'recommendations' => $this->stringList($source['recommendations'] ?? ($source['next_steps'] ?? [])),
            'review_topics' => $this->stringList($source['review_topics'] ?? []),
            'mastered_topics' => $this->stringList($source['mastered_topics'] ?? []),
        ];

        return $this->validateCanonical($course, $report);
    }

    private function normalizePlainText(Course $course, string $input): array
    {
        $report = [
            'schema' => self::SCHEMA_V2,
            'source_schema' => 'plain-text',
            'assessment_id' => $this->match('/\b(STUDYOS-[A-Za-z0-9_-]{4,110})\b/u', $input),
            'academic_code' => (string) $course->academic_code,
            'assessed_on' => $this->extractDate($input),
            'activity_type' => $this->extractActivityType($input),
            'score_20' => $this->extractScore20($input),
            'topics' => $this->extractTopicScores($course, $input),
            'strengths' => $this->extractSectionList($input, ['pontos fortes', 'forças', 'strengths']),
            'difficulties' => $this->extractSectionList($input, ['dificuldades', 'difficulties']),
            'conceptual_errors' => $this->extractSectionList($input, ['erros conceptuais', 'conceptual errors']),
            'recommendations' => $this->extractSectionList($input, ['recomendações', 'recomendacoes', 'próximos passos', 'proximos passos', 'recommendations']),
            'review_topics' => [],
            'mastered_topics' => [],
        ];

        $result = $this->validateCanonical($course, $report);
        $result['warnings'][] = 'Relatório em texto normalizado localmente; confirma cuidadosamente a pré-visualização antes de importar.';

        if (! $result['ok']) {
            $result['normalization_prompt'] = $this->normalizationPrompt($course, $input, $result['missing_fields']);
        }

        return $result;
    }

    private function validateCanonical(Course $course, array $report): array
    {
        $missing = [];
        $warnings = [];

        if (! preg_match('/^[A-Za-z0-9_-]{8,120}$/D', (string) $report['assessment_id'])) {
            $missing[] = 'assessment_id';
        }
        if ((string) $report['academic_code'] !== (string) $course->academic_code) {
            $missing[] = 'academic_code';
        }
        if (! $this->validDate((string) $report['assessed_on'])) {
            $missing[] = 'assessed_on';
        }
        if (! in_array((string) $report['activity_type'], ['study', 'review', 'practice', 'assessment', 'diagnostic'], true)) {
            $missing[] = 'activity_type';
        }
        if ($report['score_20'] !== null && ($report['score_20'] < 0 || $report['score_20'] > 20)) {
            $missing[] = 'score_20';
        }
        if (! is_array($report['topics']) || count($report['topics']) === 0 || count($report['topics']) > 100) {
            $missing[] = 'topics';
        }

        $known = Curriculum::leaves($course->loadMissing('topics'))
            ->keyBy(fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? ''));
        $seen = [];
        $validTopics = [];

        foreach ($report['topics'] as $topic) {
            $number = (string) ($topic['number'] ?? '');
            $score = $topic['score_percent'] ?? null;
            $evidenceCount = $topic['evidence_count'] ?? null;
            $feedback = (string) ($topic['feedback'] ?? '');

            $valid = $number !== ''
                && ! isset($seen[$number])
                && $known->has($number)
                && is_int($evidenceCount)
                && $evidenceCount >= 0
                && $evidenceCount <= 100
                && (($evidenceCount === 0 && $score === null)
                    || ($evidenceCount > 0 && is_numeric($score) && $score >= 0 && $score <= 100))
                && mb_strlen($feedback) <= 2000;

            if (! $valid) {
                $missing[] = 'topic:'.$number;
                continue;
            }

            $seen[$number] = true;
            $validTopics[] = $topic;
        }

        foreach (['strengths', 'difficulties', 'conceptual_errors', 'recommendations', 'review_topics', 'mastered_topics'] as $field) {
            if (! is_array($report[$field]) || count($report[$field]) > 30
                || collect($report[$field])->contains(fn ($value) => ! is_string($value) || mb_strlen($value) > 500)) {
                $missing[] = $field;
            }
        }

        if ($report['score_20'] === null && collect($validTopics)->contains(fn ($topic) => ($topic['evidence_count'] ?? 0) > 0)) {
            $warnings[] = 'Sem classificação global: serão importadas apenas as evidências por subtema.';
        }

        $report['topics'] = $validTopics;
        $report['evidence_count'] = collect($validTopics)->sum('evidence_count');
        $missing = array_values(array_unique($missing));

        return [
            'ok' => $missing === [],
            'report' => $report,
            'missing_fields' => $missing,
            'warnings' => $warnings,
            'normalization_prompt' => null,
        ];
    }

    private function invalid(Course $course, array $source, array $missing, string $warning): array
    {
        return [
            'ok' => false,
            'report' => null,
            'missing_fields' => $missing,
            'warnings' => [$warning],
            'normalization_prompt' => $this->normalizationPrompt($course, json_encode($source, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $missing),
        ];
    }

    private function decodeJson(string $input): ?array
    {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/isu', $input, $match)) {
            $decoded = json_decode($match[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($input, '{');
        $end = strrpos($input, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($input, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractTopicScores(Course $course, string $input): array
    {
        $topics = Curriculum::leaves($course->loadMissing('topics'));
        $lines = preg_split('/\R/u', $input) ?: [];
        $result = [];

        foreach ($topics as $topic) {
            $number = (string) ($topic->metadata['curricular_number'] ?? '');
            if ($number === '') {
                continue;
            }

            foreach ($lines as $line) {
                if (! preg_match('/(?<![\d.])'.preg_quote($number, '/').'(?![\d.])/u', $line)) {
                    continue;
                }
                if (! preg_match('/(\d{1,3}(?:[\.,]\d+)?)\s*%/u', $line, $scoreMatch)) {
                    continue;
                }

                $score = (float) str_replace(',', '.', $scoreMatch[1]);
                if ($score < 0 || $score > 100) {
                    continue;
                }

                $evidenceCount = 1;
                if (preg_match('/\b(\d{1,2})\s*(?:quest(?:ão|ões)|itens?|evid[eê]ncias?|respostas?)\b/iu', $line, $evidenceMatch)) {
                    $evidenceCount = max(1, min(100, (int) $evidenceMatch[1]));
                }

                $result[] = [
                    'number' => $number,
                    'score_percent' => $score,
                    'evidence_count' => $evidenceCount,
                    'feedback' => trim($line),
                ];
                break;
            }
        }

        return $result;
    }

    private function extractDate(string $input): string
    {
        if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/u', $input, $match) && $this->validDate($match[1])) {
            return $match[1];
        }
        if (preg_match('/\b(\d{2})\/(\d{2})\/(20\d{2})\b/u', $input, $match)) {
            $date = $match[3].'-'.$match[2].'-'.$match[1];
            if ($this->validDate($date)) {
                return $date;
            }
        }

        return '';
    }

    private function extractActivityType(string $input): string
    {
        $value = mb_strtolower($input);
        return match (true) {
            str_contains($value, 'diagnóst') || str_contains($value, 'diagnost') => 'diagnostic',
            str_contains($value, 'avalia') || str_contains($value, 'teste') || str_contains($value, 'exame') => 'assessment',
            str_contains($value, 'prática') || str_contains($value, 'pratica') || str_contains($value, 'treino') => 'practice',
            str_contains($value, 'revis') => 'review',
            str_contains($value, 'estudo') => 'study',
            default => '',
        };
    }

    private function extractScore20(string $input): ?float
    {
        if (preg_match('/(?:classifica(?:ç|c)[aã]o|nota|score)?[^\d]{0,20}(\d{1,2}(?:[\.,]\d+)?)\s*\/\s*20\b/iu', $input, $match)) {
            $score = (float) str_replace(',', '.', $match[1]);
            return $score >= 0 && $score <= 20 ? $score : null;
        }

        return null;
    }

    private function extractSectionList(string $input, array $headings): array
    {
        $lines = preg_split('/\R/u', $input) ?: [];
        $collecting = false;
        $items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $normalized = mb_strtolower(rtrim($trimmed, ':'));

            if (collect($headings)->contains(fn ($heading) => $normalized === $heading)) {
                $collecting = true;
                continue;
            }

            if ($collecting && preg_match('/^[\pL][\pL\s]{2,40}:$/u', $trimmed)) {
                break;
            }

            if ($collecting) {
                $item = trim((string) preg_replace('/^[\-*•\d.)\s]+/u', '', $trimmed));
                if ($item !== '') {
                    $items[] = mb_substr($item, 0, 500);
                }
            }
        }

        return array_slice($items, 0, 30);
    }

    private function normalizationPrompt(Course $course, string $input, array $missing): string
    {
        $topics = Curriculum::leaves($course->loadMissing('topics'))
            ->map(fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? '').' — '.$topic->title)
            ->filter(fn ($line) => ! str_starts_with($line, ' — '))
            ->implode("\n");

        return "Normaliza o relatório abaixo para importação no StudyOS. Não inventes dados nem classificações. Se não houver evidência num subtema, usa score_percent=null e evidence_count=0. Usa apenas estes subtemas canónicos da UC {$course->academic_code} — {$course->name}:\n{$topics}\n\nCampos em falta/ambíguos detetados: ".implode(', ', $missing).".\n\nDevolve APENAS um bloco JSON válido com schema studyos.external-assessment.v2 e os campos: assessment_id, academic_code, assessed_on, activity_type, score_20, topics [{number, score_percent, evidence_count, feedback}], strengths, difficulties, conceptual_errors, recommendations, review_topics, mastered_topics.\n\nRelatório original:\n".mb_substr($input, 0, 30000);
    }

    private function match(string $pattern, string $input): string
    {
        return preg_match($pattern, $input, $match) ? trim((string) $match[1]) : '';
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/D', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn ($item) => trim((string) $item), $value));
    }

    private function feedbackMeansNoEvidence(string $feedback): bool
    {
        return preg_match('/\b(?:sem|aus[eê]ncia de|no)\s+evid[eê]ncia\b/iu', $feedback) === 1;
    }
}
