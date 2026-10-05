<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendário · StudyOS</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div class="shell">
    <nav class="nav">
        <a class="brand" href="/">StudyOS</a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a class="active" href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
        </div>
    </nav>

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Agenda académica</p>
            <h1>{{ $mode === 'month' ? $anchor->translatedFormat('F Y') : $start->format('d/m').' – '.$end->format('d/m/Y') }}</h1>
        </div>
        <div class="calendar-actions">
            @php
                $previous = $mode === 'month' ? $anchor->copy()->subMonth() : $anchor->copy()->subWeek();
                $next = $mode === 'month' ? $anchor->copy()->addMonth() : $anchor->copy()->addWeek();
            @endphp
            <a class="button ghost" href="{{ route('calendar', ['mode' => $mode, 'date' => $previous->toDateString()]) }}">←</a>
            <a class="button ghost" href="{{ route('calendar', ['mode' => $mode, 'date' => now()->timezone(config('app.timezone'))->toDateString()]) }}">Hoje</a>
            <a class="button ghost" href="{{ route('calendar', ['mode' => $mode, 'date' => $next->toDateString()]) }}">→</a>
            <a class="button {{ $mode === 'week' ? 'primary' : 'ghost' }}" href="{{ route('calendar', ['mode' => 'week', 'date' => $anchor->toDateString()]) }}">Semana</a>
            <a class="button {{ $mode === 'month' ? 'primary' : 'ghost' }}" href="{{ route('calendar', ['mode' => 'month', 'date' => $anchor->toDateString()]) }}">Mês</a>
        </div>
    </header>

    <section class="calendar-grid {{ $mode }}">
        @foreach ($days as $day)
            @php $dayEvents = $events->get($day->toDateString(), collect()); @endphp
            <article class="calendar-day {{ $day->isToday() ? 'today' : '' }} {{ $mode === 'month' && $day->month !== $anchor->month ? 'muted' : '' }}">
                <header>
                    <span>{{ $day->translatedFormat('D') }}</span>
                    <strong>{{ $day->format('d') }}</strong>
                </header>

                <div class="calendar-events">
                    @forelse ($dayEvents as $event)
                        @php
                            $localStart = $event->localStartsAt();
                            $localEnd = $event->localEndsAt();
                        @endphp
                        <div class="calendar-event {{ $event->status === 'cancelled' ? 'cancelled' : '' }}">
                            <div class="event-time">{{ $localStart->format('H:i') }}@if($localEnd)–{{ $localEnd->format('H:i') }}@endif</div>
                            @if($event->course)
                                <a href="{{ route('courses.show', $event->course) }}"><strong>{{ $event->course->name }}</strong></a>
                            @else
                                <strong>{{ $event->title }}</strong>
                            @endif
                            <span>{{ $event->location ?: 'Sala por confirmar' }}</span>
                            @if($event->course && $event->title !== $event->course->name)
                                <small>{{ $event->title }}</small>
                            @endif
                        </div>
                    @empty
                        @if($mode === 'week')
                            <p class="empty compact">Sem eventos.</p>
                        @endif
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>
</div>
</body>
</html>
