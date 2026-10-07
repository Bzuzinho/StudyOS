<?php

namespace App\Services\Calendar;

use App\Models\ClassOccurrence;
use Illuminate\Support\Collection;

class CalendarEventPresenter
{
    /** @return Collection<int, array{event: ClassOccurrence, assessments: Collection}> */
    public function rows(Collection $events): Collection
    {
        $classes = $events->filter(fn (ClassOccurrence $event) =>
            $event->eventType() === 'class' && $event->status === 'scheduled');
        $attached = [];
        $hidden = [];

        foreach ($events as $event) {
            if ($event->eventType() !== 'assessment' || $event->status !== 'scheduled' || ! $event->course_id) {
                continue;
            }

            $candidates = $classes->filter(fn (ClassOccurrence $class) =>
                $class->course_id === $event->course_id
                && $class->localStartsAt()->toDateString() === $event->localStartsAt()->toDateString());
            $owner = $this->matchingClass($event, $candidates);

            if ($owner) {
                $attached[$owner->id][] = $event;
                $hidden[$event->id] = true;
            }
        }

        // This only groups the presentation; both source records and counters remain intact.
        return $events->reject(fn (ClassOccurrence $event) => isset($hidden[$event->id]))
            ->map(fn (ClassOccurrence $event) => [
                'event' => $event,
                'assessments' => collect($attached[$event->id] ?? []),
            ])->values();
    }

    private function matchingClass(ClassOccurrence $assessment, Collection $classes): ?ClassOccurrence
    {
        $sameTime = $classes->filter(fn (ClassOccurrence $class) =>
            $class->starts_at->equalTo($assessment->starts_at));
        if ($sameTime->count() === 1) {
            return $sameTime->first();
        }

        $duringClass = $classes->filter(fn (ClassOccurrence $class) =>
            $class->ends_at && $assessment->starts_at->gte($class->starts_at)
            && $assessment->starts_at->lt($class->ends_at));
        if ($duringClass->count() === 1) {
            return $duringClass->first();
        }

        // A single session of this UC on the day is unambiguous. If its time differs,
        // the compact assessment shows its own time instead of repeating the class time.
        return $classes->count() === 1 ? $classes->first() : null;
    }
}
