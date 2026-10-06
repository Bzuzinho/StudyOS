<?php

namespace App\Services\Moodle;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MoodleBrowserClient
{
    public function configured(): bool
    {
        return trim((string) config('studyos.moodle.browser_url')) !== ''
            && strlen((string) config('studyos.moodle.browser_secret')) >= 32;
    }

    public function create(string $launch): string
    {
        $id = $this->call('POST', '/sessions', ['launch' => $launch])->json('id');
        if (! is_string($id) || ! preg_match('/\A[a-f0-9]{64}\z/', $id)) {
            throw new RuntimeException('O navegador de autenticação não está disponível.');
        }
        return $id;
    }

    public function status(string $id): array
    {
        $data = $this->call('GET', $this->path($id).'/status')->json();
        if (! is_array($data)) throw new RuntimeException('Resposta inválida do navegador.');
        return $data;
    }

    public function frame(string $id): string
    {
        $response = $this->call('GET', $this->path($id).'/frame');
        if (! str_starts_with((string) $response->header('Content-Type'), 'image/jpeg')) {
            throw new RuntimeException('Imagem de autenticação indisponível.');
        }
        return $response->body();
    }

    public function input(string $id, array $input): void
    {
        $this->call('POST', $this->path($id).'/input', $input);
    }

    public function close(string $id): void
    {
        try { $this->call('DELETE', $this->path($id)); } catch (Throwable) {}
    }

    private function path(string $id): string
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $id)) throw new RuntimeException('Sessão inválida.');
        return '/sessions/'.$id;
    }

    private function call(string $method, string $path, array $data = []): Response
    {
        if (! $this->configured()) throw new RuntimeException('Navegador não configurado.');
        try {
            $response = Http::withToken((string) config('studyos.moodle.browser_secret'))
                ->timeout($method === 'POST' && $path === '/sessions' ? 50 : 35)
                ->connectTimeout(5)->withOptions(['allow_redirects' => false])
                ->send($method, rtrim((string) config('studyos.moodle.browser_url'), '/').$path,
                    $method === 'POST' ? ['json' => $data] : []);
            if (! $response->successful()) throw new RuntimeException();
            return $response;
        } catch (Throwable) {
            // Do not propagate upstream exceptions containing input or payload data.
            throw new RuntimeException('O navegador de autenticação não está disponível. Volta a iniciar a ligação.');
        }
    }
}
