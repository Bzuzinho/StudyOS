<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $guarded = [];

    public function sourceCourses(): HasMany
    {
        return $this->hasMany(SourceCourse::class);
    }

    public function classOccurrences(): HasMany
    {
        return $this->hasMany(ClassOccurrence::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
