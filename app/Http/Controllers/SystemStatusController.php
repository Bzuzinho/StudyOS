<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;

class SystemStatusController
{
    public function __invoke(): JsonResponse
    {
        $lastRun = SyncRun::query()->latest('started_at')->first();

        return response()->json([
            'courses' => Course::count(),
            'calendar_events' => ClassOccurrence::count(),
            'sync_connections' => SyncConnection::count(),
            'last_sync' => $lastRun ? [
                'status' => $lastRun->status,
                'started_at' => $lastRun->started_at?->toIso8601String(),
                'finished_at' => $lastRun->finished_at?->toIso8601String(),
                'stats' => $lastRun->stats,
                'error' => $lastRun->status === 'failed' ? $lastRun->error : null,
            ] : null,
        ]);
    }
}
