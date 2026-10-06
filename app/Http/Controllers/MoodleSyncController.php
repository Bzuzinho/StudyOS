<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMoodleOnDemand;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use App\Services\Moodle\MoodleSsoPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class MoodleSyncController
{
    public function index(Request $request)
    {
        return response()->view('moodle.sync', [
            'run' => $this->sessionRun($request),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function start(Request $request)
    {
        if ($this->sessionRun($request)?->status === 'queued'
            || $this->sessionRun($request)?->status === 'running') {
            return response()->json(['message' => 'Já existe uma sincronização em curso.'], 409);
        }

        $passport = Str::random(64);
        $request->session()->regenerate();
        $request->session()->put('moodle_sso', ['passport' => $passport, 'expires' => now()->addMinutes(15)->timestamp]);

        return response()->json(['url' => config('studyos.moodle.base_url').'/admin/tool/mobile/launch.php?'.http_build_query([
            'service' => 'moodle_mobile_app',
            'passport' => $passport,
            'urlscheme' => 'web+studyos',
            'oauthsso' => 3,
        ])])->header('Cache-Control', 'no-store');
    }

    public function callback()
    {
        return response()->view('moodle.callback')
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function complete(Request $request, MoodleSsoPayload $payload)
    {
        $challenge = $request->session()->get('moodle_sso');
        if (! is_array($challenge) || ($challenge['expires'] ?? 0) < now()->timestamp) {
            return response()->json(['message' => 'A ligação expirou. Volta a iniciar a sincronização.'], 422);
        }

        try {
            $uri = $request->input('payload');
            if (! is_string($uri)) {
                throw new InvalidArgumentException();
            }
            $token = $payload->token($uri, config('studyos.moodle.base_url'), $challenge['passport']);
        } catch (InvalidArgumentException) {
            // Never flash authentication payloads into the session or error pages.
            return response()->json(['message' => 'Não foi possível validar a ligação. Tenta novamente.'], 422);
        }

        $request->session()->forget('moodle_sso');
        $connection = SyncConnection::firstOrCreate(
            ['source' => 'moodle_manual', 'name' => 'Moodle · sincronização manual'],
            ['status' => 'pending', 'config' => ['read_only' => true, 'trigger' => 'manual']],
        );
        $run = $connection->runs()->create(['status' => 'queued', 'started_at' => now(), 'stats' => []]);
        $request->session()->put('moodle_run_id', $run->id);

        try {
            SyncMoodleOnDemand::dispatch($run->id, $token);
        } catch (Throwable) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'Não foi possível iniciar a recolha.']);

            return response()->json(['message' => 'Não foi possível iniciar a recolha. Tenta novamente.'], 503);
        }

        return response()->json(['url' => route('moodle.sync')])->header('Cache-Control', 'no-store');
    }

    public function status(Request $request)
    {
        $run = $this->sessionRun($request);

        return response()->json([
            'status' => $run?->status ?? 'idle',
            'stats' => $run?->stats ?? [],
            'finished_at' => $run?->finished_at?->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    private function sessionRun(Request $request): ?SyncRun
    {
        return SyncRun::where('id', $request->session()->get('moodle_run_id'))
            ->whereHas('connection', fn ($query) => $query->where('source', 'moodle_manual'))->first();
    }
}
