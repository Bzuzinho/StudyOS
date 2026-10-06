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
            ->assertViewHas('todayRows', fn ($rows) => $rows->count() === 3
                && $rows->last()['event']->title === 'Aula 18:00'
                && $rows->last()['assessments']->pluck('title')->all() === ['Relatório'])
            ->assertSee('Relatório')
            ->assertSee('Avaliação');

        foreach (['week', 'month'] as $mode) {
            $this->get('/calendar?date=2026-10-06&mode='.$mode)->assertOk()
                ->assertViewHas('events', fn ($days) => $days->get('2026-10-06')->count() === 4)
                ->assertViewHas('eventRows', fn ($days) => $days->get('2026-10-06')->count() === 3
                    && $days->get('2026-10-06')->last()['assessments']->count() === 1)
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
            ->assertSee('Editar avaliação Teste manual')->assertSee('Estudo')->assertSee('Cancelada');
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

    public function test_an_assessment_without_a_class_of_its_uc_keeps_a_full_amber_card(): void
    {
        $statistics = Course::create(['name' => 'Estatística']);
        $english = Course::create(['name' => 'Inglês']);
        $this->event($english, 'inforestudante_ical', 'Aula de Inglês', '18:00');
        $report = $this->event($statistics, 'manual', 'Relatório autónomo', '18:00', 'assessment');
        $this->event($statistics, 'inforestudante_ical', 'Aula cancelada', '18:00', null, 'cancelled');

        $response = $this->get('/')->assertOk()->assertViewHas('todayRows', fn ($rows) =>
            $rows->count() === 3 && $rows->every(fn ($row) => $row['assessments']->isEmpty()));
        self::assertStringContainsString('daily-event assessment-event', $response->getContent());
        self::assertStringNotContainsString('data-assessment-id="'.$report->id.'"', $response->getContent());
        $this->get('/calendar?date=2026-10-06')->assertOk()
            ->assertViewHas('eventRows', fn ($days) => $days->get('2026-10-06')->count() === 3)
            ->assertSee('Relatório autónomo')->assertSee('assessment-event');
    }

    public function test_multiple_assessments_share_the_class_and_only_different_details_are_repeated(): void
    {
        $course = Course::create(['name' => 'Estatística']);
        $class = $this->event($course, 'inforestudante_ical', 'Aula de Estatística', '18:00');
        $class->update(['location' => 'Sala 1']);
        $report = $this->event($course, 'manual', 'Relatório', '18:00', 'assessment');
        $report->update(['location' => 'Sala 1']);
        $quiz = $this->event($course, 'manual', 'Quiz', '19:00', 'assessment');
        $quiz->update(['location' => 'Sala 2']);

        $response = $this->get('/')->assertOk()->assertViewHas('todayRows', fn ($rows) =>
            $rows->count() === 1 && $rows->first()['event']->id === $class->id
            && $rows->first()['assessments']->count() === 2);
        $today = explode('</section>', explode('<h3>Hoje</h3>', $response->getContent())[1])[0];
        self::assertSame(1, substr_count($today, 'class="item daily-event'));
        self::assertSame(2, substr_count($today, 'data-assessment-id='));
        self::assertSame(1, substr_count($today, '18:00'));
        self::assertSame(1, substr_count($today, 'Sala 1'));
        self::assertStringContainsString('19:00', $today);
        self::assertStringContainsString('Sala 2', $today);
        self::assertStringContainsString(route('calendar-events.edit', $report), $today);
    }

    public function test_ambiguous_sessions_and_other_days_do_not_hide_assessments(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        $this->event($course, 'inforestudante_ical', 'Aula manhã', '10:00');
        $this->event($course, 'inforestudante_ical', 'Aula tarde', '18:00');
        $this->event($course, 'manual', 'Teste com sessão por confirmar', '14:00', 'assessment');
        $nextDay = $this->event($course, 'manual', 'Prova amanhã', '18:00', 'assessment');
        $nextDay->update(['starts_at' => Carbon::parse('2026-10-07 18:00', 'Europe/Lisbon')->utc()]);

        $this->get('/calendar?date=2026-10-06')->assertOk()
            ->assertViewHas('eventRows', fn ($days) =>
                $days->get('2026-10-06')->count() === 3
                && $days->get('2026-10-06')->every(fn ($row) => $row['assessments']->isEmpty())
                && $days->get('2026-10-07')->count() === 1
                && $days->get('2026-10-07')->first()['event']->id === $nextDay->id);
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
