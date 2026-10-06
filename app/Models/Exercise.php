<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answer_config' => 'array',
            'metadata' => 'array',
            'max_points' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    public function sourceChunks(): BelongsToMany
    {
        return $this->belongsToMany(SourceChunk::class)
            ->withPivot('role');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class)->orderByDesc('attempted_at');
    }
}
