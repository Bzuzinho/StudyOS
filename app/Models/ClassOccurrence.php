<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClassOccurrence extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'source_payload' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function eventType(): string
    {
        $type = $this->source_payload['event_type'] ?? null;

        if ($type !== null) {
            return $type === 'assessment_deadline' ? 'assessment' : $type;
        }

        // Older rows already identify their purpose through their source.
        return match ($this->source) {
            'inforestudante_ical' => 'class',
            'estg_assessment_calendar_2026_27', 'moodle_audit' => 'assessment',
            'study_plan' => 'study',
            default => 'other',
        };
    }

    public function eventTypeLabel(): string
    {
        return match ($this->eventType()) {
            'class' => 'Aula',
            'assessment' => 'Avaliação',
            'study' => 'Estudo',
            'assignment' => 'Trabalho',
            default => 'Outro evento',
        };
    }

    public function scopeScheduledClasses(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where(function (Builder $query) {
                $query->where('source_payload->event_type', 'class')
                    ->orWhere(function (Builder $legacy) {
                        $legacy->whereNull('source_payload->event_type')
                            ->where('source', 'inforestudante_ical');
                    });
            });
    }

    public function scopeElapsedClasses(Builder $query): Builder
    {
        return $query->scheduledClasses()->where(function (Builder $query) {
            $query->where('ends_at', '<=', now()->utc())
                ->orWhere(function (Builder $query) {
                    $query->whereNull('ends_at')->where('starts_at', '<', now()->utc());
                });
        });
    }

    public function localStartsAt(): Carbon
    {
        return $this->starts_at->copy()->timezone(config('app.timezone', 'Europe/Lisbon'));
    }

    public function localEndsAt(): ?Carbon
    {
        return $this->ends_at?->copy()->timezone(config('app.timezone', 'Europe/Lisbon'));
    }
}
