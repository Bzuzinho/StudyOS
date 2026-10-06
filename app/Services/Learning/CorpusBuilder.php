<?php

namespace App\Services\Learning;

use App\Models\LessonSummary;
use App\Models\MaterialVersion;
use App\Models\SourceChunk;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CorpusBuilder
{
    public function __construct(
        private readonly SourceTextChunker $chunker,
        private readonly CorpusQualityClassifier $classifier,
    ) {}

    public function rebuildAll(): array
    {
        $stats = [
            'sources_seen' => 0,
            'chunks_active' => 0,
            'chunks_content' => 0,
            'chunks_outline_only' => 0,
            'topic_links' => 0,
        ];

        LessonSummary::query()
            ->with('course.topics')
            ->orderBy('id')
            ->each(function (LessonSummary $summary) use (&$stats) {
                $result = $this->rebuildLessonSummary($summary);
                $this->mergeStats($stats, $result);
            });

        MaterialVersion::query()
            ->with('material.course.topics')
            ->orderBy('id')
            ->each(function (MaterialVersion $version) use (&$stats) {
                $result = $this->rebuildMaterialVersion($version);
                $this->mergeStats($stats, $result);
            });

        return $stats;
    }

    public function rebuildLessonSummary(LessonSummary $summary): array
    {
        $summary->loadMissing('course.topics');

        return $this->rebuildSource(
            sourceType: 'lesson_summary',
            sourceId: $summary->id,
            courseId: $summary->course_id,
            title: $summary->title,
            text: $summary->content,
            sourceMetadata: [
                'source' => $summary->source,
                'external_id' => $summary->external_id,
                'observed_at' => $summary->occurred_at?->toIso8601String(),
            ],
            topics: $summary->course?->topics ?? collect(),
        );
    }

    public function rebuildMaterialVersion(MaterialVersion $version): array
    {
        $version->loadMissing('material.course.topics');

        if ($version->material_id) {
            SourceChunk::query()
                ->whereHas('materialVersion', fn ($query) => $query
                    ->where('material_id', $version->material_id)
                    ->whereKeyNot($version->id))
                ->where('status', 'active')
                ->update(['status' => 'superseded']);
        }

        return $this->rebuildSource(
            sourceType: 'material_version',
            sourceId: $version->id,
            courseId: $version->material?->course_id,
            title: $version->material?->title ?? $version->version_label,
            text: $version->content_text ?? '',
            sourceMetadata: [
                'source' => $version->material?->source,
                'external_id' => $version->material?->external_id,
                'version_label' => $version->version_label,
                'observed_at' => $version->observed_at?->toIso8601String(),
            ],
            topics: $version->material?->course?->topics ?? collect(),
        );
    }

    /**
     * @param Collection<int, Topic> $topics
     */
    private function rebuildSource(
        string $sourceType,
        int $sourceId,
        ?int $courseId,
        ?string $title,
        string $text,
        array $sourceMetadata,
        Collection $topics,
    ): array {
        $chunks = $this->chunker->chunk($text);

        $baseQuery = SourceChunk::query();

        if ($sourceType === 'material_version') {
            $baseQuery->where('material_version_id', $sourceId);
        } else {
            $baseQuery->where('lesson_summary_id', $sourceId);
        }

        $baseQuery->where('status', 'active')->update(['status' => 'superseded']);

        $stats = [
            'sources_seen' => 1,
            'chunks_active' => 0,
            'chunks_content' => 0,
            'chunks_outline_only' => 0,
            'topic_links' => 0,
        ];

        foreach ($chunks as $index => $content) {
            $ordinal = $index + 1;
            $hash = hash('sha256', $content);
            $quality = $this->classifier->classify($content);

            $attributes = [
                'course_id' => $courseId,
                'material_version_id' => $sourceType === 'material_version' ? $sourceId : null,
                'lesson_summary_id' => $sourceType === 'lesson_summary' ? $sourceId : null,
                'ordinal' => $ordinal,
                'title' => $title,
                'locator' => 'Fragmento '.$ordinal,
                'content' => $content,
                'content_hash' => $hash,
                'quality' => $quality,
                'status' => 'active',
                'confidence' => 1,
                'metadata' => [
                    'source_type' => $sourceType,
                    ...$sourceMetadata,
                ],
            ];

            $chunk = SourceChunk::query()
                ->where($sourceType === 'material_version' ? 'material_version_id' : 'lesson_summary_id', $sourceId)
                ->where('ordinal', $ordinal)
                ->where('content_hash', $hash)
                ->first();

            if ($chunk) {
                $chunk->update($attributes);
            } else {
                $chunk = SourceChunk::query()->create($attributes);
            }

            $topicLinks = $this->topicLinks($content, $topics);
            $chunk->topics()->sync($topicLinks);

            $stats['chunks_active']++;
            $stats[$quality === 'content' ? 'chunks_content' : 'chunks_outline_only']++;
            $stats['topic_links'] += count($topicLinks);
        }

        return $stats;
    }

    /**
     * @param Collection<int, Topic> $topics
     * @return array<int, array{match_method: string, confidence: float}>
     */
    private function topicLinks(string $content, Collection $topics): array
    {
        $normalizedContent = $this->normalize($content);
        $links = [];

        foreach ($topics as $topic) {
            $normalizedTitle = $this->normalize($topic->title);

            if ($normalizedTitle !== '' && str_contains($normalizedContent, $normalizedTitle)) {
                $links[$topic->id] = [
                    'match_method' => 'exact_title',
                    'confidence' => 1,
                ];

                continue;
            }

            if (preg_match('/\(([^)]+)\)/u', $topic->title, $matches) === 1) {
                $acronym = $this->normalize($matches[1]);

                if ($acronym !== '' && preg_match('/(?:^|\s)'.preg_quote($acronym, '/').'(?:$|\s)/u', $normalizedContent) === 1) {
                    $links[$topic->id] = [
                        'match_method' => 'acronym',
                        'confidence' => 0.95,
                    ];
                }
            }
        }

        return $links;
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = (string) preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function mergeStats(array &$total, array $addition): void
    {
        foreach ($total as $key => $value) {
            $total[$key] = $value + ($addition[$key] ?? 0);
        }
    }
}
