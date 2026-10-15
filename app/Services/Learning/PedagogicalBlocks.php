<?php

namespace App\Services\Learning;

use RuntimeException;

class PedagogicalBlocks
{
    private const TYPES = ['concept','definition','key_idea','explanation','comparison','table','diagram','process','example','case_study','warning','common_mistake','formula','timeline','relationship','summary','memory_tip','objectives'];

    public function validate(mixed $data): array
    {
        if (!is_array($data) || ($data['schema'] ?? null) !== 'studyos.pedagogical-blocks.v1'
            || !isset($data['blocks']) || !is_array($data['blocks']) || count($data['blocks']) > 80) {
            throw new RuntimeException('Estrutura de blocos pedagógicos inválida.');
        }
        $validated = [];
        foreach ($data['blocks'] as $block) {
            if (!is_array($block) || !in_array($block['type'] ?? null, self::TYPES, true)
                || !in_array($block['mode'] ?? null, ['study','review','both'], true)
                || !is_string($block['title'] ?? null) || mb_strlen($block['title']) > 180
                || !is_string($block['body'] ?? null) || mb_strlen($block['body']) > 14000) {
                throw new RuntimeException('Bloco pedagógico inválido.');
            }
            $rows = $block['rows'] ?? [];
            if (!is_array($rows) || count($rows) > 30) {
                throw new RuntimeException('Tabela pedagógica inválida.');
            }
            foreach ($rows as $row) {
                if (!is_array($row) || count($row) > 8) {
                    throw new RuntimeException('Linha de tabela inválida.');
                }
                foreach ($row as $cell) {
                    if (!is_string($cell) || mb_strlen($cell) > 700) {
                        throw new RuntimeException('Célula de tabela inválida.');
                    }
                }
            }
            $steps = $block['steps'] ?? [];
            if (!is_array($steps) || count($steps) > 16) {
                throw new RuntimeException('Processo pedagógico inválido.');
            }
            foreach ($steps as $step) {
                if (!is_string($step) || mb_strlen($step) > 500) {
                    throw new RuntimeException('Passo de processo inválido.');
                }
            }
            $type = $block['type'];
            if (in_array($type, ['table','comparison'], true) && $rows === []) {
                throw new RuntimeException('Comparação ou tabela sem linhas.');
            }
            if (in_array($type, ['process','diagram','timeline'], true) && $steps === []) {
                throw new RuntimeException('Diagrama ou processo sem passos.');
            }
            $validated[] = [
                'kind' => $type, 'title' => $block['title'], 'body' => $block['body'],
                'mode' => $block['mode'], 'rows' => $rows, 'steps' => $steps,
            ];
        }
        return $validated;
    }

    public function forMode(array $blocks, string $mode): array
    {
        return array_values(array_filter($blocks, fn ($block) => in_array($block['mode'], [$mode,'both'], true)));
    }
}
