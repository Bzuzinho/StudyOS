<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\StudySession;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class StudySessionDeletionTest extends TestCase
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
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_manual_sessions_of_all_statuses_can_be_deleted(): void
    {
        $course = Course::create(['name'=>'Métodos Quantitativos','status'=>'active']);
        foreach (['planned','started','completed'] as $status) {
            $session = StudySession::create([
                'course_id'=>$course->id, 'source'=>'manual', 'external_id'=>'session-'.$status,
                'type'=>'study', 'starts_at'=>now(), 'planned_minutes'=>30, 'status'=>$status,
            ]);
            $this->delete('/study/'.$session->id)->assertRedirect('/study');
            $this->assertDatabaseMissing('study_sessions',['id'=>$session->id]);
        }
    }

    public function test_external_sessions_cannot_be_deleted(): void
    {
        $course = Course::create(['name'=>'Gestão','status'=>'active']);
        $session = StudySession::create([
            'course_id'=>$course->id, 'source'=>'moodle', 'external_id'=>'external-test',
            'type'=>'study', 'starts_at'=>now(), 'planned_minutes'=>30, 'status'=>'planned',
        ]);
        $this->delete('/study/'.$session->id)->assertStatus(409);
        $this->assertDatabaseHas('study_sessions',['id'=>$session->id]);
    }
}
