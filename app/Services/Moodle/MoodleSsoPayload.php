<?php

namespace App\Services\Moodle;

use InvalidArgumentException;

class MoodleSsoPayload
{
    public function token(string $uri, string $baseUrl, string $passport): string
    {
        if (strlen($uri) > 2048 || ! preg_match('/\A(?:web\+studyos|moodlemobile):\/\/token=([A-Za-z0-9+\/=]+)\z/', $uri, $matches)) {
            throw new InvalidArgumentException('Resposta de autenticação inválida.');
        }

        $decoded = base64_decode($matches[1], true);
        $parts = $decoded === false ? [] : explode(':::', $decoded);

        if (count($parts) < 2 || count($parts) > 3
            || ! hash_equals(md5(rtrim($baseUrl, '/').$passport), $parts[0])
            || ! preg_match('/\A[a-f0-9]{32}\z/i', $parts[1])) {
            throw new InvalidArgumentException('Resposta de autenticação inválida.');
        }

        // The private browser-login token is deliberately discarded.
        return $parts[1];
    }
}
