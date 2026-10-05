<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atividades · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
            <a class="active" href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
            <a href="/study">Estudo</a>
        </div>
    </nav>

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Trabalhos, quizzes e avaliações</p>
            <h1>Atividades</h1>
        </div>
        <div class="course-stats"><div><strong>{{ $openCount }}</strong><span>em aberto</span></div></div>
    </header>

    <main class="grid">
        <section class="card">
            <div class="card-head"><h3>Com prazo / avaliação</h3><span>{{ $assessments->count() }}</span></div>
            @forelse($assessments as $assessment)
                <article class="activity-row">
                    <div class="activity-date">
                        @if($assessment->due_at)
                            <strong>{{ $assessment->due_at->copy()->timezone(config('app.timezone'))->format('d/m') }}</strong>
                            <span>{{ $assessment->due_at->copy()->timezone(config('app.timezone'))->format('H:i') }}</span>
                        @else
                            <strong>—</strong><span>sem prazo</span>
                        @endif
                    </div>
                    <div>
                        <span class="activity-type">{{ strtoupper($assessment->type) }}</span>
                        <h3>{{ $assessment->title }}</h3>
                        <p>{{ $assessment->course?->name }}</p>
                        @php
                            $durationMinutes = $assessment->metadata['duration_minutes'] ?? null;
                            $attemptsAllowed = $assessment->metadata['attempts_allowed'] ?? null;
                            $passwordRequired = (bool) ($assessment->metadata['password_required'] ?? false);
                        @endphp
                        @if($durationMinutes)
                            <small>
                                {{ $durationMinutes }} min · {{ $attemptsAllowed ?? '?' }} tentativa(s)
                                @if($passwordRequired)
                                    · palavra-passe necessária
                                @endif
                            </small>
                        @endif
                    </div>
                </article>
            @empty
                <p class="empty">Sem avaliações futuras registadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Tarefas</h3><span>{{ $tasks->count() }}</span></div>
            @forelse($tasks as $task)
                <article class="activity-row">
                    <div class="activity-date">
                        @if($task->due_at)
                            <strong>{{ $task->due_at->copy()->timezone(config('app.timezone'))->format('d/m') }}</strong>
                            <span>{{ $task->due_at->copy()->timezone(config('app.timezone'))->format('H:i') }}</span>
                        @else
                            <strong>—</strong><span>prazo não publicado</span>
                        @endif
                    </div>
                    <div>
                        <span class="activity-type">{{ strtoupper($task->type) }}</span>
                        <h3>{{ $task->title }}</h3>
                        <p>{{ $task->course?->name }}</p>
                        @if($task->description)<small>{{ $task->description }}</small>@endif
                    </div>
                </article>
            @empty
                <p class="empty">Sem tarefas pendentes registadas.</p>
            @endforelse
        </section>
    </main>
</div>
</body>
</html>
