<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'config' => 'array',
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
