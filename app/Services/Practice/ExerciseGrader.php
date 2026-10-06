<?php

namespace App\Services\Practice;

class ExerciseGrader
{
    public function grade(
        string $type,
        ?string $expectedAnswer,
        array $answerConfig,
        string $submittedAnswer,
        float $maxPoints,
    ): array {
        return match ($type) {
            'single_answer' => $this->gradeSingleAnswer($expectedAnswer, $answerConfig, $submittedAnswer, $maxPoints),
            'true_false' => $this->gradeTrueFalse($expectedAnswer, $submittedAnswer, $maxPoints),
            'numeric' => $this->gradeNumeric($expectedAnswer, $answerConfig, $submittedAnswer, $maxPoints),
            default => [
                'grading_status' => 'pending_review',
                'grading_method' => 'manual_review',
                'score' => null,
                'percentage' => null,
                'feedback' => 'Resposta aberta: necessita de revisão antes de contar para o domínio.',
            ],
        };
    }

    private function gradeSingleAnswer(
        ?string $expectedAnswer,
        array $answerConfig,
        string $submittedAnswer,
        float $maxPoints,
    ): array {
        $accepted = array_filter([
            $expectedAnswer,
            ...($answerConfig['accepted_answers'] ?? []),
        ], fn ($answer) => is_string($answer) && trim($answer) !== '');

        $submitted = $this->normalizeText($submittedAnswer);
        $correct = collect($accepted)->contains(
            fn (string $answer) => $this->normalizeText($answer) === $submitted,
        );

        return $this->gradedResult($correct ? $maxPoints : 0.0, $maxPoints, 'deterministic_text');
    }

    private function gradeTrueFalse(?string $expectedAnswer, string $submittedAnswer, float $maxPoints): array
    {
        $expected = $this->normalizeBoolean($expectedAnswer ?? '');
        $submitted = $this->normalizeBoolean($submittedAnswer);
        $correct = $expected !== null && $submitted !== null && $expected === $submitted;

        return $this->gradedResult($correct ? $maxPoints : 0.0, $maxPoints, 'deterministic_boolean');
    }

    private function gradeNumeric(
        ?string $expectedAnswer,
        array $answerConfig,
        string $submittedAnswer,
        float $maxPoints,
    ): array {
        $expected = $this->parseNumber($expectedAnswer);
        $submitted = $this->parseNumber($submittedAnswer);

        if ($expected === null || $submitted === null) {
            return $this->gradedResult(0.0, $maxPoints, 'deterministic_numeric', 'Resposta numérica inválida.');
        }

        $tolerance = max(0.0, (float) ($answerConfig['tolerance'] ?? 0));
        $correct = abs($submitted - $expected) <= $tolerance;

        return $this->gradedResult(
            $correct ? $maxPoints : 0.0,
            $maxPoints,
            'deterministic_numeric',
            $correct ? null : 'O valor não está dentro da tolerância definida.',
        );
    }

    private function gradedResult(
        float $score,
        float $maxPoints,
        string $method,
        ?string $feedback = null,
    ): array {
        $percentage = $maxPoints > 0 ? round(($score / $maxPoints) * 100, 2) : 0.0;

        return [
            'grading_status' => 'graded',
            'grading_method' => $method,
            'score' => round($score, 2),
            'percentage' => $percentage,
            'feedback' => $feedback,
        ];
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function normalizeBoolean(string $value): ?bool
    {
        return match ($this->normalizeText($value)) {
            'true', '1', 'v', 'verdadeiro', 'sim' => true,
            'false', '0', 'f', 'falso', 'não', 'nao' => false,
            default => null,
        };
    }

    private function parseNumber(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
