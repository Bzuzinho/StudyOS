<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estudo · StudyOS</title>
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
            <a class="active" href="/study">Estudo</a>
            <a href="/practice">Prática</a>
        </div>
    </nav>

    @if(session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Preparação académica</p>
            <h1>Estudo</h1>
        </div>
        <div class="calendar-actions">
            <a class="button primary" href="{{ route('study.create') }}">+ Planear sessão</a>
        </div>
    </header>

    <section class="study-overview">
        <div><strong>{{ $topicCount }}</strong><span>tópicos identificados</span></div>
        <div><strong>{{ $sessions->count() }}</strong><span>sessões planeadas</span></div>
        <div><strong>{{ $plannedMinutes }}</strong><span>minutos planeados</span></div>
    </section>

    <main class="grid">
        <section class="card">
            <div class="card-head"><h3>Sessões planeadas</h3><span>{{ $sessions->count() }}</span></div>
            @forelse($sessions as $session)
                @php
                    $elapsed = $session->localEndsAt()->isPast();
                @endphp
                <article class="study-session">
                    <div class="study-session-time">
                        <strong>{{ $session->localStartsAt()->format('d/m') }}</strong>
                        <span>{{ $session->localStartsAt()->format('H:i') }}–{{ $session->localEndsAt()->format('H:i') }}</span>
                    </div>
                    <div>
                        <span class="activity-type">{{ strtoupper($session->type) }}</span>
                        <h3>{{ $session->title ?: $session->course->name }}</h3>
                        <p>{{ $session->course->name }} · {{ $session->planned_minutes }} min</p>
                        @if($session->topics->isNotEmpty())
                            <div class="topic-chips">
                                @foreach($session->topics as $topic)
                                    <span>{{ $topic->title }}</span>
                                @endforeach
                            </div>
                        @endif
                        <small>
                            @if(($session->metadata['execution_evidence'] ?? false))
                                Atividade observada no StudyOS · sessão iniciada, não assumida como concluída.
                            @elseif($elapsed)
                                Janela de estudo decorrida — execução ainda não confirmada.
                            @else
                                Planeada — não conta como estudo realizado.
                            @endif
                        </small>
                        <div class="study-session-actions">
                            <a href="{{ route('practice.index', ['study_session_id' => $session->id]) }}">Praticar nesta sessão →</a>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('study.destroy', $session) }}" onsubmit="return confirm('Eliminar esta sessão planeada?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-danger" type="submit">Eliminar</button>
                    </form>
                </article>
            @empty
                <p class="empty">Ainda não existem sessões de estudo planeadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Estados de progresso</h3><span>Separados por evidência</span></div>
            <div class="progress-explainer">
                <div><strong>Curricular</strong><p>Indica apenas se o tópico foi observado em fontes académicas.</p></div>
                <div><strong>Estudo</strong><p>Por agora mostra planeamento. Não assume que uma sessão decorrida foi realizada.</p></div>
                <div><strong>Domínio</strong><p>É calculado apenas com tentativas corrigidas. Menos de 3 exercícios distintos continua a ser evidência insuficiente.</p></div>
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Matéria identificada</h3><span>{{ $topicCount }} tópicos</span></div>
            @foreach($courses as $course)
                @if($course->topics->isNotEmpty())
                    <div class="topic-course-block">
                        <div class="topic-course-head">
                            <a href="{{ route('courses.show', $course) }}"><strong>{{ $course->name }}</strong></a>
                            <span>{{ $course->topics->count() }} tópico(s)</span>
                        </div>
                        <div class="topic-grid">
                            @foreach($course->topics as $topic)
                                <article class="topic-card">
                                    <span class="topic-position">{{ str_pad((string) $topic->position, 2, '0', STR_PAD_LEFT) }}</span>
                                    <h3>{{ $topic->title }}</h3>
                                    @php
                                        $mastery = $topic->mastery;
                                        $masteryStatus = $mastery?->status ?? 'no_evidence';
                                        $masteryLabels = [
                                            'no_evidence' => 'sem evidência',
                                            'insufficient_evidence' => 'evidência insuficiente',
                                            'fragile' => 'frágil',
                                            'developing' => 'em desenvolvimento',
                                            'competent' => 'competente',
                                            'strong' => 'forte',
                                        ];
                                    @endphp
                                    <div class="topic-states">
                                        <span class="observed">Curricular · observado</span>
                                        <span>{{ $topic->study_sessions_count > 0 ? 'Estudo · planeado' : 'Estudo · por planear' }}</span>
                                        <span class="{{ $masteryStatus === 'no_evidence' ? 'unknown' : 'mastery-evidence' }}">
                                            Domínio · {{ $masteryLabels[$masteryStatus] ?? $masteryStatus }}
                                            @if($mastery?->score_percent !== null)
                                                · {{ number_format((float) $mastery->score_percent, 0) }}%
                                            @endif
                                        </span>
                                        <span class="{{ $topic->rich_source_chunks_count > 0 ? 'source-ready' : 'unknown' }}">
                                            Fonte · {{ $topic->source_chunks_count }} fragmento(s)
                                            @if($topic->rich_source_chunks_count > 0)
                                                · {{ $topic->rich_source_chunks_count }} detalhado(s)
                                            @endif
                                        </span>
                                    </div>
                                    <div class="topic-card-actions">
                                        <a href="{{ route('practice.index', ['topic_id' => $topic->id]) }}">Praticar ({{ $topic->exercises_count }})</a>
                                        <a href="{{ route('study.create', ['course_id' => $course->id]) }}">Planear estudo</a>
                                        @if($topic->rich_source_chunks_count > 0)
                                            <form method="POST" action="{{ route('practice.generate-topic', $topic) }}">
                                                @csrf
                                                <button class="text-link" type="submit">Gerar da fonte</button>
                                            </form>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            @if($topicCount === 0)
                <p class="empty">Ainda não existem tópicos suportados pelas fontes recolhidas.</p>
            @endif
        </section>
    </main>
</div>
</body>
</html>
