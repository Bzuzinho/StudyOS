<?php

namespace Tests\Unit;

use App\Services\Moodle\MoodleSsoPayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoodleSsoPayloadTest extends TestCase
{
    private string $baseUrl = 'https://ead.ulo.pt/2026-27';

    public function test_it_accepts_only_the_current_site_and_passport_and_discards_the_private_token(): void
    {
        $token = str_repeat('a', 32);
        $uri = 'web+studyos://token='.base64_encode(md5($this->baseUrl.'passport').':::'.$token.':::private-secret');
        $this->assertSame($token, (new MoodleSsoPayload())->token($uri, $this->baseUrl, 'passport'));
    }

    public function test_a_payload_from_another_site_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $uri = 'web+studyos://token='.base64_encode(md5('https://other.examplepassport').':::'.str_repeat('a', 32));
        (new MoodleSsoPayload())->token($uri, $this->baseUrl, 'passport');
    }

    public function test_an_old_passport_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $uri = 'web+studyos://token='.base64_encode(md5($this->baseUrl.'old').':::'.str_repeat('a', 32));
        (new MoodleSsoPayload())->token($uri, $this->baseUrl, 'new');
    }

    public function test_invalid_payloads_are_rejected_without_echoing_secrets(): void
    {
        $parser = new MoodleSsoPayload();
        foreach (['', 'https://evil.example', 'moodlemobile://token=abc', 'web+studyos://token=!!!',
            'web+studyos://token='.base64_encode(md5($this->baseUrl.'passport').':::secret'),
            'web+studyos://token='.str_repeat('A', 2048)] as $uri) {
            try {
                $parser->token($uri, $this->baseUrl, 'passport');
                $this->fail('Invalid payload accepted');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Resposta de autenticação inválida.', $error->getMessage());
            }
        }
    }
}
