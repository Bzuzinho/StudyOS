<?php

namespace App\Services\Calendar;

use App\Models\ClassOccurrence;
use App\Models\Evidence;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ICalendarSyncService
{
    public function __construct(
        private readonly CalendarFeedClient $client,
        private readonly ICalendarParser $parser,
        private readonly CourseMatcher $courseMatcher,
    ) {}

    public function sync(SyncConnection $connection): SyncRun
    {
        $run = $connection->runs()->create([
            'status' => 'running',
            'started_at' => now(),
            'stats' => [],
        ]);

        try {
            $feed = $this->client->fetch($connection);

            if ($feed['not_modified'] ?? false) {
                return $this->finishSuccess($connection, $run, [
                    'not_modified' => true,
                    'not_modified_reason' => 'http_304',
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'cancelled' => 0,
                ]);
            }

            $previousHash = $connection->config['content_hash'] ?? null;
            $currentHash = $feed['content_hash'] ?? null;

            if ($previousHash && $currentHash && hash_equals($previousHash, $currentHash)) {
                $this->storeFeedMetadata($connection, $feed);

                return $this->finishSuccess($connection, $run, [
                    'not_modified' => true,
                    'not_modified_reason' => 'content_hash',
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'cancelled' => 0,
                ]);
            }

            $from = now()->subDays(config('studyos.calendar.past_days', 120));
            $to = now()->addDays(config('studyos.calendar.future_days', 420));
            $events = $this->parser->parse($feed['content'], $from, $to);

            $stats = DB::transaction(function () use ($connection, $events, $from, $to) {
                $stats = [
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'cancelled' => 0,
                    'legacy_rekeyed' => 0,
                ];
                $seen = [];

                foreach ($events as $event) {
                    $seen[] = $event['external_uid'];

                    [$occurrence, $legacyRekeyed] = $this->findOccurrence($connection, $event);
                    $wasExisting = $occurrence->exists;
                    $course = $this->courseMatcher->match($event['title']);

                    $canonicalAttributes = [
                        'course_id' => $course?->id,
                        'title' => $event['title'],
                        'location' => $event['location'] ?: null,
                        'starts_at' => $event['starts_at'],
                        'ends_at' => $event['ends_at'],
                        'status' => $event['status'],
                    ];

                    $occurrence->fill($canonicalAttributes);
                    $canonicalChanged = $wasExisting
                        && $occurrence->isDirty(array_keys($canonicalAttributes));

                    $occurrence->fill([
                        'last_seen_at' => now(),
                        'cancelled_at' => $event['status'] === 'cancelled'
                            ? ($occurrence->cancelled_at ?? now())
                            : null,
                        'source_payload' => [
                            'source_uid' => $event['source_uid'],
                            'source_recurrence_id' => $event['source_recurrence_id'],
                            'identity_kind' => $event['identity_kind'],
                            'identity_version' => 2,
                            'description' => $event['description'],
                            'sequence' => $event['sequence'],
                            'last_modified' => $event['last_modified'],
                        ],
                    ]);

                    $occurrence->save();

                    if (! $wasExisting) {
                        $stats['created']++;
                        $this->recordEvidence($occurrence);
                    } elseif ($canonicalChanged) {
                        $stats['updated']++;
                        $this->recordEvidence($occurrence);
                    } else {
                        $stats['unchanged']++;
                    }

                    if ($legacyRekeyed) {
                        $stats['legacy_rekeyed']++;
                    }
                }

                if (config('studyos.calendar.mark_missing_as_cancelled', false)) {
                    $missing = ClassOccurrence::query()
                        ->where('source', $connection->source)
                        ->where('status', 'scheduled')
                        ->whereBetween('starts_at', [$from, $to])
                        ->when($seen !== [], fn ($query) => $query->whereNotIn('external_uid', $seen))
                        ->get();

                    foreach ($missing as $occurrence) {
                        $occurrence->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                        ]);
                        $stats['cancelled']++;
                        $this->recordEvidence($occurrence, 'calendar_occurrence_missing');
                    }
                }

                return $stats;
            });

            $this->storeFeedMetadata($connection, $feed);

            return $this->finishSuccess($connection, $run, [
                ...$stats,
                'not_modified' => false,
                'events_seen' => count($events),
                'window_from' => $from->toIso8601String(),
                'window_to' => $to->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);

            $connection->update(['status' => 'error']);

            report($exception);

            return $run->fresh();
        }
    }

    /**
     * @return array{0: ClassOccurrence, 1: bool}
     */
    private function findOccurrence(SyncConnection $connection, array $event): array
    {
        $occurrence = ClassOccurrence::query()
            ->where('source', $connection->source)
            ->where('external_uid', $event['external_uid'])
            ->first();

        if ($occurrence) {
            return [$occurrence, false];
        }

        // Alpha 0.2 used UID + DTSTART for every event. For standalone
        // VEVENTs, adopt the existing row by source UID once and re-key it
        // instead of creating a duplicate when the stable identity is enabled.
        if (($event['identity_kind'] ?? null) === 'single') {
            $legacyCandidates = ClassOccurrence::query()
                ->where('source', $connection->source)
                ->where('source_payload->source_uid', $event['source_uid'])
                ->get();

            if ($legacyCandidates->count() === 1) {
                $legacy = $legacyCandidates->first();
                $legacy->external_uid = $event['external_uid'];

                return [$legacy, true];
            }
        }

        return [
            new ClassOccurrence([
                'source' => $connection->source,
                'external_uid' => $event['external_uid'],
            ]),
            false,
        ];
    }

    private function storeFeedMetadata(SyncConnection $connection, array $feed): void
    {
        $config = $connection->config ?? [];
        $config['etag'] = $feed['etag'] ?? ($config['etag'] ?? null);
        $config['last_modified'] = $feed['last_modified'] ?? ($config['last_modified'] ?? null);
        $config['content_hash'] = $feed['content_hash'] ?? ($config['content_hash'] ?? null);

        $connection->config = array_filter($config, fn ($value) => $value !== null);
        $connection->save();
    }

    private function finishSuccess(SyncConnection $connection, SyncRun $run, array $stats): SyncRun
    {
        $run->update([
            'status' => 'success',
            'finished_at' => now(),
            'stats' => $stats,
        ]);

        $connection->update([
            'status' => 'healthy',
            'last_synced_at' => now(),
        ]);

        return $run->fresh();
    }

    private function recordEvidence(ClassOccurrence $occurrence, string $factType = 'calendar_occurrence_observed'): void
    {
        Evidence::create([
            'entity_type' => ClassOccurrence::class,
            'entity_id' => $occurrence->id,
            'fact_type' => $factType,
            'source' => $occurrence->source,
            'confidence' => 1,
            'observed_at' => now(),
            'payload' => [
                'external_uid' => $occurrence->external_uid,
                'title' => $occurrence->title,
                'location' => $occurrence->location,
                'starts_at' => Carbon::parse($occurrence->starts_at)->toIso8601String(),
                'ends_at' => $occurrence->ends_at ? Carbon::parse($occurrence->ends_at)->toIso8601String() : null,
                'status' => $occurrence->status,
            ],
        ]);
    }
}
