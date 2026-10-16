<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\Academic\ConfirmedCurriculum;
use App\Services\Learning\Curriculum;

class ConfirmedCurriculumTest extends FucCurriculumImporterTest
{
    public function test_confirmed_programmes_match_the_submitted_outlines_and_preserve_history(): void
    {
        foreach ([
            ['6709', 2, 11, 13, 'Reconciliações bancárias'],
            ['11909', 1, 5, 6, 'tecnologias de informação'],
            ['15436', 5, 20, 25, 'Sistemas de equações lineares'],
        ] as [$code, $roots, $leaves, $total, $lastTerm]) {
            $course = Course::create(['name' => 'UC '.$code, 'academic_code' => $code, 'status' => 'active']);
            $legacy = $course->topics()->create(['source' => 'official_fuc', 'external_id' => 'legacy-'.$code,
                'title' => 'Programa desatualizado', 'status' => 'active', 'taught_at' => now()]);
            app(ConfirmedCurriculum::class)->apply($course);
            $topics = Curriculum::topics($course->fresh('topics'));
            $this->assertCount($total, $topics);
            $this->assertCount($roots, $topics->whereNull('parent_id'));
            $this->assertCount($leaves, Curriculum::leaves($course->fresh('topics')));
            $this->assertTrue($topics->contains(fn ($topic) => str_contains(mb_strtolower($topic->title), mb_strtolower($lastTerm))));
            $this->assertSame('archived', $legacy->fresh()->status);
            $this->assertNotNull($legacy->fresh()->taught_at);
            app(ConfirmedCurriculum::class)->apply($course);
            $this->assertCount($total, Curriculum::topics($course->fresh('topics')));
        }
    }
}
