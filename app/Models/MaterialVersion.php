<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
