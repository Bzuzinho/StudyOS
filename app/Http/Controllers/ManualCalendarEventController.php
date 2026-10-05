<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManualCalendarEventController
{
    public function create(Request $request): View
    {
        return view('calendar-events.form', [
            'event' => new ClassOccurrence(),
            'courses' => Course::query()->orderBy('semester')->orderBy('name')->get(),
            'selectedDate' => $request->date('date')?->toDateString() ?? now()->timezone(config('app.timezone'))->toDateString(),
            'types' => $this->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $course = Course::query()->findOrFail($data['course_id']);
        [$startsAt, $endsAt] = $this->parseTimes($data);

        ClassOccurrence::query()->create([
            'course_id' => $course->id,
            'source' => 'manual',
            'external_uid' => (string) Str::uuid(),
            'title' => $data['title'] ?: $course->name,
            'location' => $data['location'] ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
            'last_seen_at' => now(),
            'source_payload' => [
                'manual' => true,
                'event_type' => $data['event_type'],
                'notes' => $data['notes'] ?: null,
            ],
        ]);

        return redirect()
            ->route('calendar', ['date' => $startsAt->timezone(config('app.timezone'))->toDateString()])
            ->with('status', 'Evento criado e associado à UC.');
    }

    public function edit(ClassOccurrence $event): View
    {
        $this->ensureManual($event);

        return view('calendar-events.form', [
            'event' => $event,
            'courses' => Course::query()->orderBy('semester')->orderBy('name')->get(),
            'selectedDate' => $event->localStartsAt()->toDateString(),
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, ClassOccurrence $event): RedirectResponse
    {
        $this->ensureManual($event);

        $data = $this->validated($request);
        $course = Course::query()->findOrFail($data['course_id']);
        [$startsAt, $endsAt] = $this->parseTimes($data);

        $payload = $event->source_payload ?? [];
        $payload['manual'] = true;
        $payload['event_type'] = $data['event_type'];
        $payload['notes'] = $data['notes'] ?: null;

        $event->update([
            'course_id' => $course->id,
            'title' => $data['title'] ?: $course->name,
            'location' => $data['location'] ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source_payload' => $payload,
        ]);

        return redirect()
            ->route('calendar', ['date' => $startsAt->timezone(config('app.timezone'))->toDateString()])
            ->with('status', 'Evento atualizado.');
    }

    public function destroy(ClassOccurrence $event): RedirectResponse
    {
        $this->ensureManual($event);
        $date = $event->localStartsAt()->toDateString();
        $event->delete();

        return redirect()
            ->route('calendar', ['date' => $date])
            ->with('status', 'Evento manual eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'event_type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'end_time.after' => 'A hora de fim tem de ser posterior à hora de início.',
        ]);
    }

    private function parseTimes(array $data): array
    {
        $timezone = config('app.timezone', 'Europe/Lisbon');
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['start_time'], $timezone);
        $endsAt = ! empty($data['end_time'])
            ? Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['end_time'], $timezone)
            : null;

        return [$startsAt->utc(), $endsAt?->utc()];
    }

    private function ensureManual(ClassOccurrence $event): void
    {
        abort_unless($event->source === 'manual', 404);
    }

    private function types(): array
    {
        return [
            'class' => 'Aula / sessão',
            'study' => 'Estudo',
            'assessment' => 'Avaliação',
            'assignment' => 'Trabalho',
            'other' => 'Outro',
        ];
    }
}
