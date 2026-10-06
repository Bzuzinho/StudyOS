<?php

namespace App\Jobs;

use App\Models\MaterialVersion;
use App\Services\Learning\MaterialExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractMaterialVersion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $materialVersionId,
    ) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(MaterialExtractionService $service): void
    {
        $version = MaterialVersion::query()->find($this->materialVersionId);

        if (! $version || ! $version->storage_path) {
            return;
        }

        $service->process($version);
    }

    public function failed(Throwable $exception): void
    {
        $version = MaterialVersion::query()->find($this->materialVersionId);

        if ($version) {
            app(MaterialExtractionService::class)->markPermanentlyFailed($version, $exception);
        }
    }
}
