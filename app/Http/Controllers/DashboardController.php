<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SyncRun;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(): View
    {
        $now = now()->timezone(config('app.timezone', 'Europe/Lisbon'));
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();

        return view('dashboard', [
            'courseCount' => Course::count(),
            'todayClasses' => ClassOccurrence::query()
                ->with('course')
                ->whereBetween('starts_at', [$dayStart, $dayEnd])
                ->orderBy('starts_at')
                ->get(),
            'upcomingAssessments' => Assessment::query()
                ->with('course')
                ->whereNotNull('due_at')
                ->where('due_at', '>=', $now)
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'lastSync' => SyncRun::query()->latest('started_at')->first(),
        ]);
    }
}
