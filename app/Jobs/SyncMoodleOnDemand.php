<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Services\Moodle\MoodleWebServiceClient;
use App\Services\Moodle\MoodleWebServiceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SyncMoodleOnDemand implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $runId, private readonly string $token)
    {
        $this->onConnection('moodle')->onQueue('moodle');
    }

    public function handle(): void
    {
        $run = SyncRun::find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }

        // The token is used for this job only, never saved as a connection secret.
        $previousToken = config('studyos.moodle.token');
        config(['studyos.moodle.token' => $this->token]);
        try {
            $client = new MoodleWebServiceClient();
            $site = $client->siteInfo();
            $username = strtolower((string) ($site['username'] ?? ''));
            $expected = strtolower((string) config('studyos.moodle.expected_username'));
            if ($expected === '' || ! in_array($username, [$expected, $expected.'@ulo.pt'], true)) {
                throw new RuntimeException('A conta autenticada não corresponde à conta deste StudyOS.');
            }

            // Resolve fresh clients after the temporary configuration is set.
            app(MoodleWebServiceSyncService::class)->sync($run->connection, $run);
        } catch (Throwable) {
            $this->markFailed();
        } finally {
            config(['studyos.moodle.token' => $previousToken]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed();
    }

    private function markFailed(): void
    {
        SyncRun::whereKey($this->runId)->update([
            'status' => 'failed', 'finished_at' => now(),
            'error' => 'A recolha falhou. Confirma a conta ULO e volta a autenticar.',
        ]);
    }
}
