<?php

use App\Models\Course;
use App\Services\Academic\StatisticsCurriculum;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Course::query()->where('academic_code', '23456')
            ->orWhereRaw('LOWER(name) = ?', ['estatística aplicada à gestão i'])
            ->each(fn (Course $course) => app(StatisticsCurriculum::class)->apply($course));
    }

    public function down(): void
    {
        // A confirmed programme correction must not delete subsequent learning records.
    }
};
