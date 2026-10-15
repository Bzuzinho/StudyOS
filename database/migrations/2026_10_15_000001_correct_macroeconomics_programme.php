<?php

use App\Models\Course;
use App\Services\Academic\MacroeconomicsCurriculum;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Course::query()->where('academic_code', '10149')
            ->orWhereRaw('LOWER(name) = ?', ['macroeconomia'])
            ->each(fn (Course $course) => app(MacroeconomicsCurriculum::class)->apply($course));
    }

    public function down(): void
    {
        // A confirmed programme correction must not delete subsequent learning records.
    }
};
