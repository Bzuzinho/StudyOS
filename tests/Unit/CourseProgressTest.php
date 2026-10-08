<?php

namespace Tests\Unit;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SourceChunk;
use App\Models\Topic;
use App\Services\Academic\TopicBootstrapper;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

class CourseProgressTest extends TestCase
{
    private string $originalTimezone;

    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        date_default_timezone_set('UTC');
        Carbon::setTestNow(Carbon::parse('2026-10-08 12:00', 'UTC'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->originalTimezone);
        parent::tearDown();
    }

    public function test_application_runs_in_testing_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));
    }

    public function test_progress_counts_only_classes_and_waits_for_the_end_of_an_ongoing_class(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'semester' => 1, 'status' => 'active']);
        $this->event($course, '2026-10-07 10:00', '2026-10-07 12:00');
        $this->event($course, '2026-10-08 09:00', '2026-10-08 10:00', source: 'manual', type: 'class');
        $this->event($course, '2026-10-08 10:00', '2026-10-08 12:00');
        $this->event($course, '2026-10-08 11:00', null);
        $this->event($course, '2026-10-08 11:00', '2026-10-08 13:00');
        $this->event($course, '2026-10-09 11:00', '2026-10-09 12:00');
        $this->event($course, '2026-10-07 10:00', '2026-10-07 12:00', type: 'assessment');
        $this->event($course, '2026-10-07 10:00', '2026-10-07 12:00', source: 'moodle_audit');
        $this->event($course, '2026-10-07 10:00', '2026-10-07 12:00', source: 'study_plan');
        $this->event($course, '2026-10-07 10:00', '2026-10-07 12:00', status: 'cancelled');

        $this->get('/courses')->assertOk()->assertSee('66,7%')->assertSee('4 de 6 aulas programadas')
            ->assertViewHas('coursesBySemester', fn ($groups) => $groups[1]->first()->scheduled_classes_count === 6);
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('66,7%')
            ->assertViewHas('course', fn ($course) => $course->elapsed_classes_count === 4 && $course->classProgressPercent() === 66.7);
    }

    public function test_empty_calendars_have_no_percentage_and_fully_elapsed_calendars_reach_one_hundred(): void
    {
        $course = Course::create(['name' => 'Sem calendário', 'semester' => 2]);
        $this->get('/courses/'.$course->id)->assertOk()
            ->assertViewHas('course', fn ($course) => $course->classProgressPercent() === null);
        $this->event($course, '2026-10-07 10:00', '2026-10-07 11:00');
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('100,0%');
    }

    public function test_topics_can_be_selected_cleared_and_resaved_without_changing_the_original_confirmation(): void
    {
        $course = Course::create(['name' => 'Contabilidade Financeira I', 'academic_code' => '6709', 'semester' => 1]);
        app(TopicBootstrapper::class)->run();
        $topics = $course->topics()->get();
        $ids = $topics->modelKeys();
        $first = $topics->first();
        $this->patch('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => $ids, 'taught_topic_ids' => [$first->id]])
            ->assertRedirect('/courses/'.$course->id.'#course-topics');
        $confirmedAt = $first->fresh()->taught_at->toIso8601String();
        $this->assertDatabaseCount('topic_masteries', 0);
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('1 de 7 tópicos registados')
            ->assertSee('form="taught-topics"', false)->assertSee('name="taught_topic_ids[]"', false);
        $this->get('/courses')->assertOk()->assertSee('1/7 tópicos lecionados');

        Carbon::setTestNow(now()->addHour());
        $this->patch('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => $ids, 'taught_topic_ids' => [$first->id]])->assertRedirect();
        app(TopicBootstrapper::class)->run();
        $this->assertSame($confirmedAt, $first->fresh()->taught_at->toIso8601String());
        $this->patch('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => $ids])->assertRedirect();
        $this->assertNull($first->fresh()->taught_at);
        $this->assertDatabaseCount('topic_masteries', 0);
    }

    public function test_foreign_or_unlisted_topics_are_rejected_without_clearing_existing_marks(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        $own = $course->topics()->create(['source' => 'manual', 'title' => 'PIB', 'taught_at' => now()]);
        $unlisted = $course->topics()->create(['source' => 'manual', 'title' => 'Inflação', 'taught_at' => now()]);
        $other = Course::create(['name' => 'Inglês']);
        $foreign = $other->topics()->create(['source' => 'manual', 'title' => 'Vocabulary']);
        $this->withExceptionHandling();
        $this->patchJson('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => [$own->id, $foreign->id]])->assertUnprocessable();
        $this->patchJson('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => [$own->id], 'taught_topic_ids' => [$foreign->id]])->assertUnprocessable();
        $this->patchJson('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => [$own->id], 'taught_topic_ids' => [$unlisted->id]])->assertUnprocessable();
        $this->assertNotNull($own->fresh()->taught_at);
        $this->patch('/courses/'.$course->id.'/topics/coverage', ['topic_ids' => [$own->id]])->assertRedirect();
        $this->assertNotNull($unlisted->fresh()->taught_at);
    }

    public function test_manual_topics_can_be_added_edited_and_archived_with_their_history_preserved(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão']);
        $this->get('/courses/'.$course->id.'/topics/create')->assertOk()->assertSee('Novo tópico');
        $this->post('/courses/'.$course->id.'/topics', ['title' => '  Organização  ', 'description' => 'Objetivos', 'is_taught' => 1])->assertRedirect();
        $topic = $course->topics()->firstOrFail();
        $this->assertSame('Organização', $topic->title);
        $this->assertNotNull($topic->taught_at);
        $this->get('/courses/'.$course->id.'/topics/'.$topic->id.'/edit')->assertOk()->assertSee('Objetivos');
        $this->put('/courses/'.$course->id.'/topics/'.$topic->id, ['title' => 'Gestão', 'is_taught' => 0])->assertRedirect();
        $this->assertNull($topic->fresh()->taught_at);
        $chunk = SourceChunk::create(['course_id' => $course->id, 'ordinal' => 1, 'content' => 'Fonte', 'content_hash' => hash('sha256', 'Fonte')]);
        $topic->sourceChunks()->attach($chunk);
        $this->delete('/courses/'.$course->id.'/topics/'.$topic->id)->assertRedirect();
        $this->assertSame('archived', $topic->fresh()->status);
        $this->assertSame(1, $topic->sourceChunks()->count());
        $this->get('/courses/'.$course->id)->assertOk()->assertViewHas('course', fn ($course) => $course->topics->isEmpty());
    }

    public function test_topic_editing_cannot_cross_courses_or_change_source_imported_titles(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        $other = Course::create(['name' => 'Inglês']);
        $foreign = $other->topics()->create(['source' => 'manual', 'title' => 'English']);
        $imported = $course->topics()->create(['source' => 'inforestudante_audit', 'title' => 'Importado']);
        $this->withExceptionHandling()->get('/courses/'.$course->id.'/topics/'.$foreign->id.'/edit')->assertNotFound();
        $this->put('/courses/'.$course->id.'/topics/'.$imported->id, ['title' => 'Alterado'])->assertForbidden();
        $this->assertSame('Importado', $imported->fresh()->title);
        $this->postJson('/courses/'.$course->id.'/topics', ['title' => '   '])->assertUnprocessable();
        $this->postJson('/courses/'.$course->id.'/topics', ['title' => ['Invalid']])->assertUnprocessable();
    }

    public function test_course_page_keeps_the_full_source_count_but_only_loads_a_small_preview(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        for ($ordinal = 1; $ordinal <= 25; $ordinal++) {
            SourceChunk::create(['course_id' => $course->id, 'ordinal' => $ordinal, 'title' => 'Fonte', 'content' => 'Texto '.$ordinal, 'content_hash' => hash('sha256', (string) $ordinal)]);
        }
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('24 de 25 excerto(s)')
            ->assertViewHas('course', fn ($course) => $course->active_source_chunks_count === 25 && $course->sourceChunks->count() === 24);
    }

    private function event(Course $course, string $start, ?string $end, string $source = 'inforestudante_ical', ?string $type = null, string $status = 'scheduled'): void
    {
        ClassOccurrence::create([
            'course_id' => $course->id, 'source' => $source, 'external_uid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Evento', 'starts_at' => $start, 'ends_at' => $end, 'status' => $status,
            'source_payload' => $type ? ['event_type' => $type] : [],
        ]);
    }
}
