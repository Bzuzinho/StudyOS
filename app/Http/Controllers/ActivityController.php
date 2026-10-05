<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Task;
use Illuminate\View\View;

class ActivityController
{
    public function __invoke(): View
    {
        $now = now();

        $assessments = Assessment::query()
            ->with('course')
            ->where(function ($query) {
                $query->whereNull('metadata->conditional')
                    ->orWhere('metadata->conditional', false);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('due_at')->orWhere('due_at', '>=', $now);
            })
            ->orderByRaw('due_at is null, due_at asc')
            ->get();

        $tasks = Task::query()
            ->with('course')
            ->where('status', '!=', 'completed')
            ->orderByRaw('due_at is null, due_at asc')
            ->get();

        return view('activities.index', [
            'assessments' => $assessments,
            'tasks' => $tasks,
            'openCount' => $assessments->count() + $tasks->count(),
        ]);
    }
}
