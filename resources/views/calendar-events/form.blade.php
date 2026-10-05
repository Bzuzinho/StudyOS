<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->exists ? 'Editar evento' : 'Novo evento' }} · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell narrow-shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a class="active" href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="{{ route('calendar', ['date' => $selectedDate]) }}">← Calendário</a></div>

    <header class="form-header">
        <p class="eyebrow">Agenda académica</p>
        <h1>{{ $event->exists ? 'Editar evento' : 'Criar novo evento' }}</h1>
        <p>O evento fica associado diretamente à unidade curricular escolhida.</p>
    </header>

    @if ($errors->any())
        <div class="alert error">
            <strong>Há campos a corrigir.</strong>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form class="event-form card" method="POST" action="{{ $event->exists ? route('calendar-events.update', $event) : route('calendar-events.store') }}">
        @csrf
        @if($event->exists) @method('PUT') @endif

        @php
            $payload = $event->source_payload ?? [];
            $localStart = $event->exists ? $event->localStartsAt() : null;
            $localEnd = $event->exists ? $event->localEndsAt() : null;
        @endphp

        <div class="form-grid">
            <label class="field span-2">
                <span>Unidade curricular</span>
                <select name="course_id" required>
                    <option value="">Selecionar UC…</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('course_id', $event->course_id) === (string) $course->id)>
                            {{ $course->semester }}.º sem. · {{ $course->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Tipo</span>
                <select name="event_type" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('event_type', $payload['event_type'] ?? 'class') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Título <small>opcional</small></span>
                <input name="title" type="text" maxlength="255" value="{{ old('title', $event->exists ? $event->title : '') }}" placeholder="Se vazio, usa o nome da UC">
            </label>

            <label class="field">
                <span>Data</span>
                <input name="date" type="date" required value="{{ old('date', $localStart?->toDateString() ?? $selectedDate) }}">
            </label>

            <label class="field">
                <span>Início</span>
                <input name="start_time" type="time" required value="{{ old('start_time', $localStart?->format('H:i') ?? '') }}">
            </label>

            <label class="field">
                <span>Fim <small>opcional</small></span>
                <input name="end_time" type="time" value="{{ old('end_time', $localEnd?->format('H:i') ?? '') }}">
            </label>

            <label class="field">
                <span>Sala / local <small>opcional</small></span>
                <input name="location" type="text" maxlength="255" value="{{ old('location', $event->location) }}" placeholder="Ex.: A.S2.5">
            </label>

            <label class="field span-2">
                <span>Notas <small>opcional</small></span>
                <textarea name="notes" rows="4" maxlength="3000" placeholder="Objetivo, matéria, lembrete…">{{ old('notes', $payload['notes'] ?? '') }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <a class="button ghost" href="{{ route('calendar', ['date' => old('date', $localStart?->toDateString() ?? $selectedDate)]) }}">Cancelar</a>
            <button class="button primary" type="submit">{{ $event->exists ? 'Guardar alterações' : 'Criar evento' }}</button>
        </div>
    </form>

    @if($event->exists)
        <form method="POST" action="{{ route('calendar-events.destroy', $event) }}" class="danger-zone" onsubmit="return confirm('Eliminar este evento manual?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="button danger">Eliminar evento</button>
        </form>
    @endif
</div>
</body>
</html>
