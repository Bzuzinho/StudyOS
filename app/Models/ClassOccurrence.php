<?php

namespace App\Models;

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

    public function localStartsAt(): Carbon
    {
        return $this->starts_at->copy()->timezone(config('app.timezone', 'Europe/Lisbon'));
    }

    public function localEndsAt(): ?Carbon
    {
        return $this->ends_at?->copy()->timezone(config('app.timezone', 'Europe/Lisbon'));
    }
}
