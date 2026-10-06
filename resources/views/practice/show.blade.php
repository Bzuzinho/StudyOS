<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $exercise->title ?: 'Exercício' }} · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell narrow-shell">
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

    <div class="breadcrumb"><a href="{{ route('practice.index') }}">← Prática</a></div>

    <header class="practice-hero">
        <div>
            <span class="activity-type">{{ strtoupper(str_replace('_', ' ', $exercise->type)) }} · {{ $exercise->source === 'manual' ? 'MANUAL' : strtoupper($exercise->source) }}@if(($exercise->metadata['source_grounded'] ?? false)) · FUNDAMENTADO @endif</span>
            <h1>{{ $exercise->title ?: 'Exercício' }}</h1>
            <p>{{ $exercise->course->name }} · {{ number_format((float) $exercise->max_points, 1) }} ponto(s)</p>
        </div>
        @if($exercise->source === 'manual')
            <a class="button ghost" href="{{ route('practice.edit', $exercise) }}">Editar</a>
        @endif
    </header>

    <section class="card exercise-prompt">
        <div class="topic-chips">
            @foreach($exercise->topics as $topic)
                <span>{{ $topic->title }}</span>
            @endforeach
        </div>
        <p>{!! nl2br(e($exercise->prompt)) !!}</p>
    </section>

    @if($exercise->sourceChunks->isNotEmpty())
        <section class="card source-evidence-card">
            <div class="card-head"><h3>Fontes do exercício</h3><span>{{ $exercise->sourceChunks->count() }} fragmento(s)</span></div>
            @foreach($exercise->sourceChunks as $chunk)
                <article class="source-evidence">
                    <div>
                        <strong>{{ $chunk->sourceLabel() }}</strong>
                        <span>{{ $chunk->locator }} · {{ $chunk->quality === 'content' ? 'conteúdo detalhado' : 'esquema' }}</span>
                    </div>
                    @if($exercise->attempts->isNotEmpty())
                        <p>{!! nl2br(e($chunk->content)) !!}</p>
                    @else
                        <p class="source-hidden">O excerto é mostrado depois da primeira tentativa para não antecipar a resposta.</p>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    @if($studySession)
        <div class="study-context-banner">Esta tentativa ficará associada à sessão de estudo de {{ $studySession->localStartsAt()->format('d/m H:i') }}.</div>
    @endif

    <form class="card attempt-form" method="POST" action="{{ route('practice.attempt', $exercise) }}">
        @csrf
        @if($studySession)
            <input type="hidden" name="study_session_id" value="{{ $studySession->id }}">
        @endif

        <label class="field">
            <span>A tua resposta</span>
            @if($exercise->type === 'true_false')
                <select name="submitted_answer" required>
                    <option value="">Escolher…</option>
                    <option value="verdadeiro">Verdadeiro</option>
                    <option value="falso">Falso</option>
                </select>
            @else
                <textarea name="submitted_answer" rows="{{ $exercise->type === 'open_text' ? 8 : 3 }}" maxlength="10000" required>{{ old('submitted_answer') }}</textarea>
            @endif
        </label>

        <div class="form-actions">
            <button class="button primary" type="submit">Submeter tentativa</button>
        </div>
    </form>

    @if($exercise->attempts->isNotEmpty())
        <section class="card attempt-history">
            <div class="card-head"><h3>Tentativas</h3><span>{{ $exercise->attempts->count() }}</span></div>
            @foreach($exercise->attempts as $attempt)
                <article class="attempt-detail">
                    <div class="attempt-detail-head">
                        <div class="attempt-score {{ $attempt->grading_status }}">
                            {{ $attempt->percentage !== null ? number_format((float) $attempt->percentage, 0).'%' : '…' }}
                        </div>
                        <div>
                            <strong>{{ $attempt->attempted_at->copy()->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</strong>
                            <span>{{ $attempt->grading_status === 'graded' ? 'Corrigida · '.$attempt->grading_method : 'Aguarda revisão' }}</span>
                        </div>
                    </div>

                    <div class="answer-box">
                        <small>Resposta submetida</small>
                        <p>{!! nl2br(e($attempt->submitted_answer)) !!}</p>
                    </div>

                    @if($attempt->feedback)
                        <p class="attempt-feedback">{{ $attempt->feedback }}</p>
                    @endif

                    @if($attempt->grading_status === 'pending_review')
                        <form method="POST" action="{{ route('practice.review-attempt', $attempt) }}" class="manual-review-form">
                            @csrf
                            @method('PUT')
                            <label class="field">
                                <span>Cotação atribuída (0–{{ number_format((float) $attempt->max_points, 2) }})</span>
                                <input name="score" type="number" min="0" max="{{ $attempt->max_points }}" step="0.01" required>
                            </label>
                            <label class="field">
                                <span>Feedback <small>opcional</small></span>
                                <textarea name="feedback" rows="3" maxlength="4000"></textarea>
                            </label>
                            <button class="button ghost" type="submit">Concluir revisão</button>
                        </form>
                    @endif
                </article>
            @endforeach

            <div class="reference-answer">
                <small>Resposta de referência</small>
                <p>{{ $exercise->expected_answer ?: 'Não definida.' }}</p>
                @if($exercise->explanation)
                    <small>Explicação / resolução</small>
                    <p>{!! nl2br(e($exercise->explanation)) !!}</p>
                @endif
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-head"><h3>Evidência nos tópicos</h3><span>apenas tentativas corrigidas</span></div>
        @foreach($exercise->topics as $topic)
            @php
                $mastery = $topic->mastery;
            @endphp
            <div class="mastery-inline">
                <strong>{{ $topic->title }}</strong>
                <span>{{ $mastery?->score_percent !== null ? number_format((float) $mastery->score_percent, 0).'%' : 'sem evidência' }}</span>
                <small>{{ $mastery?->evidence_exercises ?? 0 }} exercício(s) distinto(s) · {{ $mastery?->evidence_attempts ?? 0 }} tentativa(s)</small>
            </div>
        @endforeach
    </section>
</div>
</body>
</html>
