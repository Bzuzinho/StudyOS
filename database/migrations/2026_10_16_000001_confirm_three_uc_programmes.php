<?php

use App\Models\Course;
use App\Services\Academic\ConfirmedCurriculum;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Course::query()->whereIn('academic_code', ['6709', '11909', '15436'])
            ->each(fn (Course $course) => app(ConfirmedCurriculum::class)->apply($course));
    }

    public function down(): void
    {
        // Preserve academic history and the user-confirmed curricular identity.
    }
};
