<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'extraction_queued_at' => 'datetime',
            'extraction_started_at' => 'datetime',
            'extraction_finished_at' => 'datetime',
            'extraction_attempts' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function sourceChunks(): HasMany
    {
        return $this->hasMany(SourceChunk::class);
    }
}
