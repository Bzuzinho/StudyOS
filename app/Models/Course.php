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

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function lessonSummaries(): HasMany
    {
        return $this->hasMany(LessonSummary::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('position');
    }

    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class)->orderBy('starts_at');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
