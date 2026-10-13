<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estudo · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=practice-run-1">
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
        <div><strong>{{ $completedMinutes }}</strong><span>minutos de estudo confirmados</span></div>
    </section>

    <main class="grid">
        <section class="card wide" id="recommendations">
            <div class="card-head"><h3>Plano de estudo e treino por UC</h3><span>Prioridades com base em evidência</span></div>
            <p class="muted-text">Recomendações baseadas apenas em tópicos confirmados como lecionados e em resultados corrigidos. A duração é uma sugestão, não uma sessão automaticamente realizada.</p>
            @foreach($studyRecommendations as $plan)
                <div class="topic-course-block">
                    <div class="topic-course-head">
                        <a href="{{ route('courses.show', $plan['course']) }}"><strong>{{ $plan['course']->name }}</strong></a>
                        <span>{{ $plan['taught_count'] }}/{{ $plan['topic_count'] }} tópicos lecionados</span>
                    </div>
                    @if($plan['assessment'])
                        <p class="muted-text">Próxima avaliação: {{ $plan['assessment']->title }} · {{ $plan['assessment']->due_at->timezone(config('app.timezone'))->format('d/m/Y') }} ({{ max(0, $plan['days_to_assessment']) }} dias)</p>
                    @endif
                    @forelse($plan['recommendations'] as $recommendation)
                        <article class="study-session">
                            <div>
                                <strong>{{ $recommendation['topic']->title }}</strong>
                                <p>{{ $recommendation['reason'] }}</p>
                                <small>Sessão sugerida: {{ $recommendation['minutes'] }} minutos</small>
                                <div class="study-session-actions">
                                    <a href="{{ route('study.create', ['course_id' => $plan['course']->id]) }}">Planear estudo →</a>
                                    @if($recommendation['action'] === 'practice')
                                        <a href="{{ route('practice.runs.create', ['course_id' => $plan['course']->id, 'topic_id' => $recommendation['topic']->id]) }}">Treinar tópico →</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="muted-text">{{ $plan['topic_count'] === 0 ? 'Ainda não existem tópicos estruturados para esta UC.' : 'Ainda não há tópicos confirmados como lecionados. Assinala a matéria já dada para obteres recomendações.' }}</p>
                    @endforelse
                </div>
            @endforeach
        </section>

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
                            @if($session->status === 'completed')
                                Estudo realizado · {{ $session->metadata['actual_minutes'] ?? $session->planned_minutes }} minutos confirmados.
                            @elseif(($session->metadata['execution_evidence'] ?? false))
                                Atividade observada no StudyOS · sessão iniciada, não assumida como concluída.
                            @elseif($elapsed)
                                Janela de estudo decorrida — execução ainda não confirmada.
                            @else
                                Planeada — não conta como estudo realizado.
                            @endif
                        </small>
                        <div class="study-session-actions">
                            <a href="{{ route('practice.runs.create', ['course_id' => $session->course_id, 'study_session_id' => $session->id]) }}">Praticar nesta sessão →</a>
                            @if($session->status !== 'completed' && $session->source === 'manual')
                                <form method="POST" action="{{ route('study.complete', $session) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label>Minutos realizados <input type="number" name="actual_minutes" min="1" max="480" required value="{{ $session->planned_minutes }}"></label>
                                    <button class="button" type="submit">Confirmar estudo realizado</button>
                                </form>
                            @endif
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
                <div><strong>Estudo</strong><p>O tempo realizado só é contabilizado quando confirmas a sessão e a sua duração efetiva.</p></div>
                <div><strong>Domínio</strong><p>É calculado apenas com tentativas corrigidas. Menos de 3 exercícios distintos continua a ser evidência insuficiente.</p></div>
            </div>
        </section>

        <section class="card wide">
            <p class="muted-text"><strong>Próximo passo sugerido:</strong> começa pelos tópicos já lecionados que ainda não têm domínio demonstrado ou cujo resultado seja frágil. Planeia uma sessão de revisão, responde a exercícios da fonte e confirma a sessão quando a realizares. Tópicos sem fontes ou sem tentativas não são classificados como dominados.</p>
            <div class="card-head"><h3>Estudar e praticar por UC</h3></div>
            <p class="muted-text">Os materiais de estudo ficam organizados na árvore curricular de cada UC. Para praticar, escolhe os temas e inicia uma sequência de perguntas do banco de exercícios.</p>
            <div class="topic-grid">
            @foreach($courses as $course)
              <article class="topic-card">
                <h3>{{ $course->name }}</h3>
                <div class="topic-card-actions">
                  <a href="{{ route('courses.show',$course) }}">Ler os materiais</a>
                  <a href="{{ route('practice.runs.create',['course_id'=>$course->id]) }}">Iniciar treino</a>
                  <a href="{{ route('study.create',['course_id'=>$course->id]) }}">Planear sessão</a>
                </div>
              </article>
            @endforeach
            </div>
        </section>
    </main>
</div>
</body>
</html>
