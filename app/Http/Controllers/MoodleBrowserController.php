<?php

namespace App\Http\Controllers;

use App\Services\Moodle\MoodleBrowserClient;
use App\Services\Moodle\MoodleSsoPayload;
use Illuminate\Http\Request;
use Throwable;

class MoodleBrowserController
{
    public function start(Request $request, MoodleBrowserClient $browser, MoodleSyncController $sync)
    {
        if (! $browser->configured()) {
            return response()->json(['message' => 'O navegador de autenticação está a ser preparado. Tenta novamente dentro de instantes.'], 503);
        }
        if ($id = $request->session()->get('moodle_browser_id')) $browser->close($id);
        $request->session()->forget(['moodle_browser_id', 'moodle_sso']);
        // Reuse the existing session challenge and concurrent-run protection.
        $request->headers->set('Accept', 'application/json');
        $launch = $sync->start($request);
        if ($launch->getStatusCode() !== 200) return $launch;
        try {
            $id = $browser->create($launch->getData(true)['url']);
            $request->session()->put('moodle_browser_id', $id);
            return response()->json(['status' => 'authenticating', 'csrf_token' => csrf_token()])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível abrir o navegador de autenticação. Tenta novamente.'], 503);
        }
    }

    public function status(Request $request, MoodleBrowserClient $browser, MoodleSyncController $sync, MoodleSsoPayload $payload)
    {
        $id = $request->session()->get('moodle_browser_id');
        if (! is_string($id)) return response()->json(['status' => 'idle'])->header('Cache-Control', 'no-store');
        try {
            $state = $browser->status($id);
            if (($state['phase'] ?? '') === 'ready') {
                // The return never passes through the user's browser, query string or logs.
                $request->merge(['payload' => $state['payload'] ?? null]);
                $response = $sync->complete($request, $payload);
                $browser->close($id);
                $request->session()->forget('moodle_browser_id');
                return $response;
            }
            if (($state['phase'] ?? '') === 'error') throw new \RuntimeException();
            $origin = $state['origin'] ?? '';
            return response()->json(['status' => 'authenticating', 'origin' => is_string($origin) ? $origin : ''])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            $browser->close($id);
            $request->session()->forget('moodle_browser_id');
            return response()->json(['message' => 'A janela de autenticação terminou ou não está disponível. Volta a iniciar a ligação.'], 503);
        }
    }

    public function frame(Request $request, MoodleBrowserClient $browser)
    {
        $id = $request->session()->get('moodle_browser_id');
        if (! is_string($id)) return response('', 404);
        try {
            return response($browser->frame($id))->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'no-store')->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable) { return response('', 503)->header('Cache-Control', 'no-store'); }
    }

    public function input(Request $request, MoodleBrowserClient $browser)
    {
        $id = $request->session()->get('moodle_browser_id');
        if (! is_string($id)) return response()->json(['message' => 'Sessão de autenticação indisponível.'], 404);
        // Do not use validation exceptions that may flash input into the session.
        $input = $request->only(['type', 'x', 'y', 'deltaY', 'key', 'text']);
        if (strlen(json_encode($input) ?: '') > 16384) return response()->json(['message' => 'Entrada inválida.'], 422);
        try {
            $browser->input($id, $input);
            return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível enviar a ação à janela.'], 503);
        }
    }

    public function cancel(Request $request, MoodleBrowserClient $browser)
    {
        if ($id = $request->session()->get('moodle_browser_id')) $browser->close($id);
        $request->session()->forget(['moodle_browser_id', 'moodle_sso']);
        return response()->json(['status' => 'idle'])->header('Cache-Control', 'no-store');
    }
}
