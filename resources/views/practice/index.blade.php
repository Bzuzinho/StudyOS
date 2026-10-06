<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prática · StudyOS</title>
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
            <a href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
            <a href="/study">Estudo</a>
            <a class="active" href="/practice">Prática</a>
        </div>
    </nav>

    @if(session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Exercícios, tentativas e evidência</p>
            <h1>Prática</h1>
        </div>
        <a class="button primary" href="{{ route('practice.create') }}">+ Criar exercício</a>
    </header>

    <form method="GET" class="practice-filters card">
        <label class="field">
            <span>UC</span>
            <select name="course_id">
                <option value="">Todas</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) $selectedCourseId === (string) $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="button ghost" type="submit">Filtrar</button>
        @if($selectedCourseId || $selectedTopicId)
            <a class="button ghost" href="{{ route('practice.index') }}">Limpar</a>
        @endif
    </form>

    <main class="grid">
        <section class="card wide">
            <div class="card-head"><h3>Domínio por tópico</h3><span>baseado em tentativas corrigidas</span></div>
            <div class="mastery-grid">
                @forelse($topics as $topic)
                    @php
                        $mastery = $topic->mastery;
                        $status = $mastery?->status ?? 'no_evidence';
                        $labels = [
                            'no_evidence' => 'Sem evidência',
                            'insufficient_evidence' => 'Evidência insuficiente',
                            'fragile' => 'Frágil',
                            'developing' => 'Em desenvolvimento',
                            'competent' => 'Competente',
                            'strong' => 'Forte',
                        ];
                    @endphp
                    <article class="mastery-card">
                        <div class="mastery-card-head">
                            <span>{{ $topic->course->name }}</span>
                            <strong>{{ $mastery?->score_percent !== null ? number_format((float) $mastery->score_percent, 0).'%' : '—' }}</strong>
                        </div>
                        <h3>{{ $topic->title }}</h3>
                        <div class="mastery-status {{ $status }}">{{ $labels[$status] ?? $status }}</div>
                        <p>
                            {{ $mastery?->evidence_exercises ?? 0 }} exercício(s) distinto(s) ·
                            {{ $mastery?->evidence_attempts ?? 0 }} tentativa(s) corrigida(s)
                        </p>
                        <div class="mastery-actions">
                            <a href="{{ route('practice.index', ['topic_id' => $topic->id]) }}">{{ $topic->exercises_count }} exercício(s)</a>
                            <a href="{{ route('practice.create', ['course_id' => $topic->course_id, 'topic_id' => $topic->id]) }}">Criar exercício</a>
                        </div>
                    </article>
                @empty
                    <p class="empty">Ainda não existem tópicos disponíveis para prática.</p>
                @endforelse
            </div>
            <p class="policy-note">Política atual: o valor é a média da tentativa corrigida mais recente de cada exercício distinto (até 8). Com menos de 3 exercícios distintos, o StudyOS mostra “evidência insuficiente”, mesmo que a média seja elevada.</p>
        </section>

        <section class="card">
            <div class="card-head"><h3>Exercícios</h3><span>{{ $exercises->count() }}</span></div>
            @forelse($exercises as $exercise)
                <a class="practice-item" href="{{ route('practice.show', $exercise) }}">
                    <div>
                        <span class="activity-type">{{ strtoupper(str_replace('_', ' ', $exercise->type)) }}</span>
                        <strong>{{ $exercise->title ?: Str::limit($exercise->prompt, 80) }}</strong>
                        <p>{{ $exercise->course->name }} · {{ $exercise->attempts->count() }} tentativa(s)</p>
                    </div>
                    <span>→</span>
                </a>
            @empty
                <p class="empty">Ainda não existem exercícios neste filtro. O StudyOS não cria perguntas sem uma fonte de conteúdo validada.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Tentativas recentes</h3><span>{{ $recentAttempts->count() }}</span></div>
            @forelse($recentAttempts as $attempt)
                <article class="attempt-row">
                    <div class="attempt-score {{ $attempt->grading_status }}">
                        {{ $attempt->percentage !== null ? number_format((float) $attempt->percentage, 0).'%' : '…' }}
                    </div>
                    <div>
                        <a href="{{ route('practice.show', $attempt->exercise) }}"><strong>{{ $attempt->exercise->title ?: Str::limit($attempt->exercise->prompt, 70) }}</strong></a>
                        <p>{{ $attempt->exercise->course->name }} · {{ $attempt->attempted_at->copy()->timezone(config('app.timezone'))->format('d/m H:i') }}</p>
                        <small>{{ $attempt->grading_status === 'graded' ? 'Corrigida · '.$attempt->grading_method : 'Aguarda revisão' }}</small>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não existem tentativas.</p>
            @endforelse
        </section>
    </main>
</div>
</body>
</html>
