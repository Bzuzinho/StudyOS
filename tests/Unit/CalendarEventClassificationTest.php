<?php

namespace Tests\Unit;

use App\Models\ClassOccurrence;
use App\Models\Course;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

class CalendarEventClassificationTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.timezone' => 'Europe/Lisbon',
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00', 'Europe/Lisbon'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_three_classes_and_a_report_remain_four_events_but_only_three_classes(): void
    {
        $course = Course::create(['name' => 'Estatística Aplicada à Gestão I', 'semester' => 1]);
        foreach (['10:00', '12:00', '18:00'] as $time) {
            $this->event($course, 'inforestudante_ical', 'Aula '.$time, $time);
        }
        $this->event($course, 'estg_assessment_calendar_2026_27', 'Relatório', '18:00', 'assessment');

        $this->get('/')->assertOk()
            ->assertViewHas('todayClasses', fn ($events) => $events->count() === 3)
            ->assertViewHas('todayEvents', fn ($events) => $events->count() === 4)
            ->assertViewHas('todayAssessmentCount', 1)
            ->assertSee('Relatório')
            ->assertSee('Avaliação');

        foreach (['week', 'month'] as $mode) {
            $this->get('/calendar?date=2026-10-06&mode='.$mode)->assertOk()
                ->assertViewHas('events', fn ($days) => $days->get('2026-10-06')->count() === 4)
                ->assertSee('Relatório')->assertSee('assessment-event')->assertSee('Avaliação');
        }
    }

    public function test_non_class_events_and_cancelled_classes_do_not_become_the_next_class(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'semester' => 1]);
        $this->event($course, 'manual', 'Teste manual', '10:00', 'assessment');
        $this->event($course, 'moodle_audit', 'Prazo Moodle', '11:00', 'assessment_deadline');
        $this->event($course, 'study_plan', 'Estudo planeado', '12:00', 'study');
        $this->event($course, 'manual', 'Entrega', '13:00', 'assignment');
        $this->event($course, 'manual', 'Outro evento', '14:00', 'other');
        $this->event($course, 'inforestudante_ical', 'Aula cancelada', '15:00', null, 'cancelled');
        $class = $this->event($course, 'manual', 'Aula válida', '18:00', 'class');

        self::assertSame([$class->id], ClassOccurrence::scheduledClasses()->pluck('id')->all());
        $this->get('/')->assertOk()
            ->assertViewHas('todayClasses', fn ($events) => $events->pluck('id')->all() === [$class->id])
            ->assertViewHas('todayAssessmentCount', 2);
        $this->get('/courses')->assertOk()
            ->assertViewHas('coursesBySemester', fn ($semesters) =>
                $semesters->get(1)->first()->classOccurrences->first()->id === $class->id);
        $this->get('/courses/'.$course->id)->assertOk()
            ->assertViewHas('upcomingClasses', fn ($events) => $events->pluck('id')->all() === [$class->id]);
        $this->get('/calendar?date=2026-10-06')->assertOk()
            ->assertSee('Manual · Avaliação')->assertSee('Estudo')->assertSee('Cancelada');
    }

    public function test_existing_source_only_rows_are_classified_without_resynchronising(): void
    {
        $course = Course::create(['name' => 'Inglês']);
        $this->event($course, 'estg_assessment_calendar_2026_27', 'Prova', '10:00');
        $this->event($course, 'moodle_audit', 'Prazo', '11:00');
        $this->event($course, 'study_plan', 'Estudo', '12:00');
        $class = $this->event($course, 'inforestudante_ical', 'ING (TP1)', '18:00');

        self::assertSame([$class->id], ClassOccurrence::scheduledClasses()->pluck('id')->all());
        $this->get('/')->assertOk()
            ->assertViewHas('todayClasses', fn ($events) => $events->count() === 1)
            ->assertViewHas('todayAssessmentCount', 2);
    }

    public function test_today_uses_lisbon_day_boundaries_for_utc_records(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão']);
        $early = $this->event($course, 'inforestudante_ical', 'Aula cedo', '00:30');
        $this->event($course, 'inforestudante_ical', 'Aula de amanhã', '00:30')
            ->update(['starts_at' => Carbon::parse('2026-10-07 00:30', 'Europe/Lisbon')->utc()]);

        $this->get('/')->assertOk()
            ->assertViewHas('todayClasses', fn ($events) => $events->pluck('id')->all() === [$early->id]);
    }

    private function event(Course $course, string $source, string $title, string $time,
        ?string $type = null, string $status = 'scheduled'): ClassOccurrence
    {
        return ClassOccurrence::create([
            'course_id' => $course->id,
            'source' => $source,
            'title' => $title,
            'starts_at' => Carbon::parse('2026-10-06 '.$time, 'Europe/Lisbon')->utc(),
            'status' => $status,
            'source_payload' => $type === null ? null : ['event_type' => $type],
        ]);
    }
}
