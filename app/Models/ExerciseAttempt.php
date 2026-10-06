<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_points' => 'decimal:2',
            'percentage' => 'decimal:2',
            'attempted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }
}
