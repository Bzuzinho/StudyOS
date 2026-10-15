<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\Academic\StatisticsCurriculum;
use App\Services\Learning\Curriculum;

class StatisticsCurriculumTest extends FucCurriculumImporterTest
{
    public function test_correction_migration_targets_only_statistics_i(): void
    {
        $first = Course::create(['name' => 'Estatística Aplicada à Gestão I', 'academic_code' => '23456']);
        $second = Course::create(['name' => 'Estatística Aplicada à Gestão II', 'academic_code' => '17497']);
        $migration = require database_path('migrations/2026_10_15_000002_correct_statistics_programme.php');
        $migration->up();
        $migration->up();
        $this->assertCount(20, Curriculum::topics($first->fresh('topics')));
        $this->assertCount(0, $second->fresh('topics')->topics);
    }

    public function test_confirmed_programme_has_exact_hierarchy_and_survives_reimported_topics(): void
    {
        $course = Course::create(['name' => 'Estatística Aplicada à Gestão I', 'academic_code' => '23456', 'status' => 'active']);
        $old = $course->topics()->create(['source' => 'chatgpt_package', 'title' => 'Programa errado', 'status' => 'active', 'taught_at' => now()]);
        $service = app(StatisticsCurriculum::class);
        $service->apply($course);
        $topics = Curriculum::topics($course->fresh('topics'));
        $this->assertCount(20, $topics);
        $this->assertCount(4, $topics->whereNull('parent_id'));
        $this->assertCount(16, Curriculum::leaves($course->fresh('topics')));
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
        $this->assertSame('Distribuições de probabilidade', $byNumber['4']->title);
        $this->assertSame('Índices simples', $byNumber['1.1']->title);
        $this->assertSame('Quadros de frequências', $byNumber['3.3']->title);
        $byNumber['1.1']->update(['taught_at' => now()]);
        $service->apply($course);
        $this->assertNotNull($byNumber['1.1']->fresh()->taught_at);
        $this->assertCount(20, Curriculum::topics($course->fresh('topics')));
        $old->update(['status' => 'active']);
        $this->assertCount(20, Curriculum::topics($course->fresh('topics')));
        $this->get('/courses/'.$course->id)->assertOk()
            ->assertSee('Números índices')->assertSee('t-Student')->assertDontSee('Programa errado');
    }
}
