<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicMastery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score_percent' => 'decimal:2',
            'last_practiced_at' => 'datetime',
            'calculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
