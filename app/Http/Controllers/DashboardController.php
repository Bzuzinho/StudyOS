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
            'courseCount' => Course::query()->where('status', 'active')->count(),
            'plannedCourseCount' => Course::query()->where('status', 'planned')->count(),
            'activeCourses' => Course::query()
                ->where('status', 'active')
                ->withCount('classOccurrences')
                ->orderBy('name')
                ->get(),
            'todayClasses' => ClassOccurrence::query()
                ->with('course')
                ->whereBetween('starts_at', [$dayStart, $dayEnd])
                ->orderBy('starts_at')
                ->get(),
            'upcomingAssessments' => Assessment::query()
                ->with('course')
                ->whereNotNull('due_at')
                ->where('due_at', '>=', $now)
                ->where(function ($query) {
                    $query->whereNull('metadata->conditional')
                        ->orWhere('metadata->conditional', false);
                })
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'lastSync' => SyncRun::query()->latest('started_at')->first(),
        ]);
    }
}
