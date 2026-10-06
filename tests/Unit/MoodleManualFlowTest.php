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
        $response->assertSessionHas('_token', $response->json('csrf_token'));
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $parameters);
        $this->assertSame('moodlemobile', $parameters['urlscheme']);
        $this->assertSame('moodle_mobile_app', $parameters['service']);
        $this->assertSame('1', $parameters['confirmed']);
        $this->assertArrayNotHasKey('oauthsso', $parameters);
        $response->assertSessionHas('moodle_sso.passport', $parameters['passport']);
        $this->assertStringNotContainsString('token=', $response->json('url'));
        Queue::assertNothingPushed();
    }

    public function test_browser_form_redirects_to_moodle_with_a_session_bound_challenge(): void
    {
        $this->withSession(['_token' => 'original-csrf']);
        $response = $this->post('/moodle/start')->assertStatus(302);
        $response->assertSessionHas('_token', 'original-csrf');
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://ead.ulo.pt/2026-27/admin/tool/mobile/launch.php?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        $response->assertSessionHas('moodle_sso.passport', $parameters['passport']);
        $this->assertSame('moodlemobile', $parameters['urlscheme']);
        Queue::assertNothingPushed();
    }

    public function test_sync_and_callback_pages_render_successfully(): void
    {
        $sync = $this->get('/moodle/sync')->assertOk()->assertSee('Autenticar e sincronizar');
        $callback = $this->get('/moodle/callback')->assertOk()->assertSee('Ligação ao Moodle');
        // JSON may escape slashes; inspect the actual URL after unescaping them.
        $this->assertStringNotContainsString('http://localhost/moodle/', str_replace('\\/', '/', $sync->getContent()));
        $this->assertStringNotContainsString('http://localhost/moodle/', str_replace('\\/', '/', $callback->getContent()));
    }

    public function test_callback_consumes_challenge_and_queues_one_encrypted_job(): void
    {
        $challenge = ['passport' => 'passport', 'expires' => now()->addMinutes(15)->timestamp];
        $uri = 'moodlemobile://token='.base64_encode(md5('https://ead.ulo.pt/2026-27passport').':::'.str_repeat('a', 32));
        $this->withSession(['moodle_sso' => $challenge])->postJson('/moodle/complete', ['payload' => $uri])
            ->assertOk()->assertJsonPath('url', '/moodle/sync')->assertSessionMissing('moodle_sso')->assertSessionHas('moodle_run_id');
        Queue::assertPushed(SyncMoodleOnDemand::class, function ($job) {
            return $job instanceof ShouldBeEncrypted && $job->connection === 'moodle' && $job->queue === 'moodle';
        });
        $this->assertSame('queued', SyncRun::first()->status);
        $this->assertNull(SyncConnection::first()->secret);
        $this->postJson('/moodle/complete', ['payload' => $uri])->assertStatus(422);
        Queue::assertPushed(SyncMoodleOnDemand::class, 1);
    }

    public function test_web_flow_keeps_studyos_open_and_does_not_require_protocol_registration(): void
    {
        $page = $this->get('/moodle/sync')->assertOk();
        $page->assertSee('Janela de autenticação ULO')->assertSee('auth-screen', false);
        $this->assertStringNotContainsString('registerProtocolHandler', $page->getContent());
        $this->assertStringNotContainsString('return-link', $page->getContent());
        Queue::assertNothingPushed();
    }

    public function test_managed_browser_is_bound_to_the_session_and_automatically_starts_one_import(): void
    {
        config(['studyos.moodle.browser_url' => 'http://browser.internal:3000', 'studyos.moodle.browser_secret' => str_repeat('s', 64)]);
        $id = str_repeat('c', 64);
        Http::fake(['http://browser.internal:3000/sessions' => Http::response(['id' => $id], 201)]);
        $this->postJson('/moodle/browser/start')->assertOk()->assertSessionHas('moodle_browser_id', $id);
        $challenge = session('moodle_sso');
        $token = str_repeat('a', 32);
        $return = 'moodlemobile://token='.base64_encode(md5('https://ead.ulo.pt/2026-27'.$challenge['passport']).':::'.$token);
        Http::fake([
            'http://browser.internal:3000/sessions/'.$id.'/status' => Http::response(['phase' => 'ready', 'payload' => $return]),
            'http://browser.internal:3000/sessions/'.$id => Http::response(['status' => 'closed']),
        ]);
        $response = $this->getJson('/moodle/browser/status')->assertOk()->assertJsonPath('url', '/moodle/sync');
        $response->assertSessionMissing('moodle_browser_id')->assertSessionMissing('moodle_sso');
        $this->assertStringNotContainsString($return, $response->getContent());
        $this->assertStringNotContainsString($token, $response->getContent());
        Queue::assertPushed(SyncMoodleOnDemand::class, 1);
        $this->getJson('/moodle/browser/status')->assertOk()->assertJsonPath('status', 'idle');
        Queue::assertPushed(SyncMoodleOnDemand::class, 1);
    }

    public function test_managed_browser_frames_and_input_require_this_sessions_browser(): void
    {
        $this->get('/moodle/browser/frame')->assertNotFound();
        $this->postJson('/moodle/browser/input', ['type' => 'text', 'text' => 'fixture'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_managed_browser_can_be_cancelled_without_importing(): void
    {
        config(['studyos.moodle.browser_url' => 'http://browser.internal:3000', 'studyos.moodle.browser_secret' => str_repeat('s', 64)]);
        $id = str_repeat('c', 64);
        Http::fake(['*' => Http::response(['status' => 'closed'])]);
        $this->withSession(['moodle_browser_id' => $id, 'moodle_sso' => ['passport' => 'fixture']])
            ->postJson('/moodle/browser/cancel')->assertOk()->assertSessionMissing('moodle_browser_id')->assertSessionMissing('moodle_sso');
        Queue::assertNothingPushed();
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->hasHeader('Authorization', 'Bearer '.str_repeat('s', 64)));
    }

    public function test_managed_browser_rejects_a_return_for_another_passport_and_closes_the_context(): void
    {
        config(['studyos.moodle.browser_url' => 'http://browser.internal:3000', 'studyos.moodle.browser_secret' => str_repeat('s', 64)]);
        $id = str_repeat('d', 64);
        $return = 'moodlemobile://token='.base64_encode(md5('https://ead.ulo.pt/2026-27another').':::'.str_repeat('b', 32));
        Http::fake([
            'http://browser.internal:3000/sessions/'.$id.'/status' => Http::response(['phase' => 'ready', 'payload' => $return]),
            'http://browser.internal:3000/sessions/'.$id => Http::response(['status' => 'closed']),
        ]);
        $this->withSession(['moodle_browser_id' => $id, 'moodle_sso' => ['passport' => 'current', 'expires' => now()->addMinute()->timestamp]])
            ->getJson('/moodle/browser/status')->assertStatus(422)->assertSessionMissing('moodle_browser_id');
        Queue::assertNothingPushed();
    }

    public function test_resuming_login_preserves_the_passport_and_original_expiry(): void
    {
        $first = $this->postJson('/moodle/start')->assertOk();
        $challenge = session('moodle_sso');
        $this->travel(2)->minutes();
        $second = $this->postJson('/moodle/start')->assertOk();
        $this->assertSame($first->json('url'), $second->json('url'));
        $this->assertSame($challenge, session('moodle_sso'));
        $this->get('/moodle/sync')->assertOk()->assertSee('Autenticar e sincronizar');
        $this->travel(14)->minutes();
        $third = $this->postJson('/moodle/start')->assertOk();
        $this->assertNotSame($second->json('url'), $third->json('url'));
        Queue::assertNothingPushed();
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
