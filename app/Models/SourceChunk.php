<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SourceChunk extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function materialVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class);
    }

    public function lessonSummary(): BelongsTo
    {
        return $this->belongsTo(LessonSummary::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)
            ->withPivot(['match_method', 'confidence']);
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class)
            ->withPivot('role');
    }

    public function scopeEligibleForPractice(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('quality', 'content')
            ->whereHas('topics');
    }

    public function sourceLabel(): string
    {
        if ($this->materialVersion) {
            return $this->materialVersion->material?->title
                ?? $this->title
                ?? 'Material';
        }

        return $this->lessonSummary?->title
            ?? $this->title
            ?? 'Sumário';
    }
}
