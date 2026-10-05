<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController
{
    public function __invoke(Request $request): View
    {
        $timezone = config('app.timezone', 'Europe/Lisbon');
        $mode = $request->string('mode')->value() === 'month' ? 'month' : 'week';
        $anchor = ($request->date('date') ?? now())->timezone($timezone);

        if ($mode === 'month') {
            $start = $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $end = $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        } else {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY);
            $end = $anchor->copy()->endOfWeek(Carbon::SUNDAY);
        }

        $events = ClassOccurrence::query()
            ->with('course')
            ->whereBetween('starts_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (ClassOccurrence $event) => $event->localStartsAt()->toDateString());

        $days = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        return view('calendar', [
            'mode' => $mode,
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'events' => $events,
        ]);
    }
}
