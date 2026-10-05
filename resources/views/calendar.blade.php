<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendário · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a class="active" href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
        </div>
    </nav>

    @if(session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

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
            <a class="button primary" href="{{ route('calendar-events.create', ['date' => $anchor->toDateString()]) }}">+ Novo evento</a>
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
                    <div class="day-head-actions">
                        <a class="day-add" href="{{ route('calendar-events.create', ['date' => $day->toDateString()]) }}" title="Criar evento neste dia">+</a>
                        <strong>{{ $day->format('d') }}</strong>
                    </div>
                </header>

                <div class="calendar-events">
                    @forelse ($dayEvents as $event)
                        @php
                            $localStart = $event->localStartsAt();
                            $localEnd = $event->localEndsAt();
                            $isManual = $event->source === 'manual';
                            $isAssessment = $event->source === 'estg_assessment_calendar_2026_27';
                            $isMoodle = $event->source === 'moodle_audit';
                        @endphp
                        <div class="calendar-event {{ $event->status === 'cancelled' ? 'cancelled' : '' }} {{ $isManual ? 'manual' : '' }} {{ $isAssessment ? 'assessment-event' : '' }} {{ $isMoodle ? 'moodle-event' : '' }}">
                            <div class="event-topline">
                                <div class="event-time">{{ $localStart->format('H:i') }}@if($localEnd)–{{ $localEnd->format('H:i') }}@endif</div>
                                @if($isManual)
                                    <a class="event-edit" href="{{ route('calendar-events.edit', $event) }}">Editar</a>
                                @endif
                            </div>
                            @if($event->course)
                                <a href="{{ route('courses.show', $event->course) }}"><strong>{{ $event->course->name }}</strong></a>
                            @else
                                <strong>{{ $event->title }}</strong>
                            @endif
                            <span>{{ $event->location ?: 'Sala por confirmar' }}</span>
                            @if($event->course && $event->title !== $event->course->name)
                                <small>{{ $event->title }}</small>
                            @endif
                            @if($isMoodle)
                                <small class="moodle-label">Moodle · prazo confirmado</small>
                            @elseif($isAssessment)
                                <small class="assessment-label">Avaliação periódica · calendário oficial</small>
                            @elseif($isManual)
                                <small class="manual-label">Manual · {{ ucfirst($event->source_payload['event_type'] ?? 'evento') }}</small>
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
