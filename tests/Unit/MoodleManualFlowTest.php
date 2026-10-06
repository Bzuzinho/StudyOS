<?php

namespace Tests\Unit;

use App\Jobs\SyncMoodleOnDemand;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class MoodleManualFlowTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->instance('env', 'testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
            'cache.stores.array' => ['driver' => 'array'],
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'studyos.moodle.base_url' => 'https://ead.ulo.pt/2026-27',
            'studyos.moodle.expected_username' => '1234567890',
        ]);
        Schema::create('sync_connections', function (Blueprint $table) {
            $table->id(); $table->string('source'); $table->string('name');
            $table->text('secret')->nullable(); $table->json('config')->nullable();
            $table->string('status'); $table->timestamp('last_synced_at')->nullable(); $table->timestamps();
        });
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id(); $table->foreignId('sync_connection_id'); $table->string('status');
            $table->timestamp('started_at'); $table->timestamp('finished_at')->nullable();
            $table->json('stats')->nullable(); $table->text('error')->nullable(); $table->timestamps();
        });
        Queue::fake();
    }

    public function test_start_creates_a_session_bound_launch_without_credentials(): void
    {
        $response = $this->postJson('/moodle/start')->assertOk();
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $parameters);
        $this->assertSame('web+studyos', $parameters['urlscheme']);
        $this->assertSame('moodle_mobile_app', $parameters['service']);
        $response->assertSessionHas('moodle_sso.passport', $parameters['passport']);
        $this->assertStringNotContainsString('token=', $response->json('url'));
        Queue::assertNothingPushed();
    }

    public function test_sync_and_callback_pages_render_successfully(): void
    {
        $this->get('/moodle/sync')->assertOk()->assertSee('Autenticar e sincronizar')
            ->assertDontSee('http://localhost/moodle/status', false)->assertDontSee('http://localhost/moodle/start', false);
        $this->get('/moodle/callback')->assertOk()->assertSee('Ligação ao Moodle')
            ->assertDontSee('http://localhost/moodle/complete', false);
    }

    public function test_callback_consumes_challenge_and_queues_one_encrypted_job(): void
    {
        $challenge = ['passport' => 'passport', 'expires' => now()->addMinutes(15)->timestamp];
        $uri = 'web+studyos://token='.base64_encode(md5('https://ead.ulo.pt/2026-27passport').':::'.str_repeat('a', 32));
        $this->withSession(['moodle_sso' => $challenge])->postJson('/moodle/complete', ['payload' => $uri])
            ->assertOk()->assertSessionMissing('moodle_sso')->assertSessionHas('moodle_run_id');
        Queue::assertPushed(SyncMoodleOnDemand::class, function ($job) {
            return $job instanceof ShouldBeEncrypted && $job->connection === 'moodle' && $job->queue === 'moodle';
        });
        $this->assertSame('queued', SyncRun::first()->status);
        $this->assertNull(SyncConnection::first()->secret);
        $this->postJson('/moodle/complete', ['payload' => $uri])->assertStatus(422);
        Queue::assertPushed(SyncMoodleOnDemand::class, 1);
    }

    public function test_expired_or_missing_challenges_do_not_create_jobs(): void
    {
        $this->postJson('/moodle/complete', ['payload' => 'secret'])->assertStatus(422);
        $this->withSession(['moodle_sso' => ['passport' => 'passport', 'expires' => now()->subMinute()->timestamp]])
            ->postJson('/moodle/complete', ['payload' => 'secret'])->assertStatus(422);
        Queue::assertNothingPushed();
        $this->assertSame(0, SyncRun::count());
    }

    public function test_status_does_not_expose_another_sessions_run(): void
    {
        $connection = SyncConnection::create(['source' => 'moodle_manual', 'name' => 'Manual', 'status' => 'pending']);
        $connection->runs()->create(['status' => 'queued', 'started_at' => now()]);
        $this->getJson('/moodle/status')->assertOk()->assertJsonPath('status', 'idle');
    }

    public function test_wrong_account_is_rejected_and_temporary_token_is_restored(): void
    {
        $connection = SyncConnection::create(['source' => 'moodle_manual', 'name' => 'Manual', 'status' => 'pending']);
        $run = $connection->runs()->create(['status' => 'queued', 'started_at' => now()]);
        config(['studyos.moodle.token' => 'previous']);
        Http::fake(['*' => Http::response(['username' => 'another-user'], 200)]);
        (new SyncMoodleOnDemand($run->id, str_repeat('a', 32)))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('previous', config('studyos.moodle.token'));
        Http::assertSentCount(1);
    }
}
