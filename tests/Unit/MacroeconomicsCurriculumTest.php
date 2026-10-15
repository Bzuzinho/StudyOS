<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\Academic\MacroeconomicsCurriculum;
use App\Services\Learning\Curriculum;

class MacroeconomicsCurriculumTest extends FucCurriculumImporterTest
{
    public function test_confirmed_programme_has_exact_hierarchy_and_survives_reimported_topics(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'academic_code' => '10149', 'status' => 'active']);
        $old = $course->topics()->create(['source' => 'chatgpt_package', 'title' => 'Programa errado', 'status' => 'active', 'taught_at' => now()]);
        $service = app(MacroeconomicsCurriculum::class);
        $service->apply($course);
        $topics = Curriculum::topics($course->fresh('topics'));
        $this->assertCount(27, $topics);
        $this->assertCount(6, $topics->whereNull('parent_id'));
        $this->assertCount(22, Curriculum::leaves($course->fresh('topics')));
        $this->assertSame('archived', $old->fresh()->status);
        $this->assertNotNull($old->fresh()->taught_at);
        $this->assertCount(0, $topics->whereNotNull('taught_at'));
        $byNumber = $topics->keyBy(fn ($topic) => $topic->metadata['curricular_number']);
        foreach ($topics as $topic) {
            $number = $topic->metadata['curricular_number'];
            if (str_contains($number, '.')) {
                $this->assertSame($byNumber[explode('.', $number)[0]]->id, $topic->parent_id);
            }
        }
        $this->assertSame('O modelo keynesiano em economia aberta', $byNumber['6']->title);
        $this->assertFalse($topics->contains(fn ($topic) => str_starts_with($topic->metadata['curricular_number'], '6.')));
        $byNumber['1.1']->update(['taught_at' => now()]);
        $service->apply($course);
        $this->assertNotNull($byNumber['1.1']->fresh()->taught_at);
        $this->assertCount(27, Curriculum::topics($course->fresh('topics')));
        $old->update(['status' => 'active']);
        $this->assertCount(27, Curriculum::topics($course->fresh('topics')));
        $this->get('/courses/'.$course->id)->assertOk()
            ->assertSee('Ciência Económica')->assertSee('crowding-out')->assertDontSee('Programa errado');
    }
}
