<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $course->name }} · StudyOS</title>
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
            <a class="active" href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="/courses">← UCs</a></div>

    <header class="course-hero">
        <div>
            <div class="course-card-head">
                <span class="course-code">{{ $course->academic_code }}</span>
                <span class="status-pill {{ $course->status }}">{{ $course->status === 'active' ? 'Ativa' : 'Planeada' }}</span>
            </div>
            <h1>{{ $course->name }}</h1>
            <p>{{ $course->semester }}.º semestre@if($course->ects) · {{ $course->ects }} ECTS@endif</p>
        </div>
        <div class="course-hero-metrics">
            <div><strong>{{ $course->classOccurrences->count() }}</strong><span>ocorrências</span></div>
            <div><strong>{{ $course->assessments->count() }}</strong><span>avaliações</span></div>
            <div><strong>{{ $course->tasks->count() }}</strong><span>tarefas</span></div>
            <div><strong>{{ $course->materials->count() }}</strong><span>materiais</span></div>
        </div>
    </header>

    <main class="grid">
        <section class="card">
            <div class="card-head"><h3>Próximas aulas</h3><a href="/calendar">Calendário</a></div>
            @forelse ($upcomingClasses as $class)
                <article class="item">
                    <div class="date">{{ $class->localStartsAt()->format('d/m') }}</div>
                    <div>
                        <strong>{{ $class->localStartsAt()->format('H:i') }}@if($class->localEndsAt())–{{ $class->localEndsAt()->format('H:i') }}@endif</strong>
                        <p>{{ $class->location ?: 'Sala por confirmar' }} · {{ $class->title }}</p>
                    </div>
                </article>
            @empty
                <p class="empty">Sem próximas aulas importadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Avaliações</h3><span>{{ $course->assessments->count() }}</span></div>
            @forelse ($course->assessments as $assessment)
                @php
                    $conditional = (bool) ($assessment->metadata['conditional'] ?? false);
                    $regime = $assessment->metadata['regime'] ?? null;
                @endphp
                <article class="item {{ $conditional ? 'conditional-item' : '' }}">
                    <div class="date">{{ $assessment->due_at?->copy()->timezone(config('app.timezone'))->format('d/m') ?? '—' }}</div>
                    <div>
                        <strong>{{ $assessment->title }}</strong>
                        <p>
                            {{ ucfirst(str_replace('_', ' ', $assessment->type)) }}
                            @if($regime) · {{ ucfirst($regime) }} @endif
                            · {{ $conditional ? 'Condicional' : 'Ação necessária' }}
                        </p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não foram recolhidas avaliações para esta UC.</p>
            @endforelse
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Tarefas</h3><span>{{ $course->tasks->count() }}</span></div>
            @forelse($course->tasks as $task)
                <article class="item">
                    <div class="date">{{ $task->due_at ? $task->due_at->copy()->timezone(config('app.timezone'))->format('d/m') : '—' }}</div>
                    <div>
                        <strong>{{ $task->title }}</strong>
                        <p>
                            {{ ucfirst($task->type) }} · {{ $task->status === 'completed' ? 'Concluída' : 'Pendente' }}
                            @if(! $task->due_at)
                                · prazo não publicado
                            @endif
                        </p>
                        @if($task->description)
                            <small class="task-description">{{ $task->description }}</small>
                        @endif
                    </div>
                </article>
            @empty
                <p class="empty">Sem tarefas registadas para esta UC.</p>
            @endforelse
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Materiais</h3><a href="{{ route('materials.index') }}">{{ $course->materials->count() }} registado(s)</a></div>
            <div class="course-material-grid">
                @forelse($course->materials as $material)
                    @php
                        $latestVersion = $material->versions->first();
                    @endphp
                    <article class="course-material">
                        <div class="material-card-head">
                            <span class="material-type">{{ strtoupper($material->type) }}</span>
                            <span class="source-pill {{ $material->source === 'manual' ? 'manual' : '' }}">{{ $material->source === 'manual' ? 'Manual' : 'Auditado' }}</span>
                        </div>
                        <strong>{{ $material->title }}</strong>
                        @if($latestVersion)
                            <small>{{ $latestVersion->version_label ?: 'Versão observada' }}</small>
                        @endif
                        <div class="material-meta">
                            @if($material->url)
                                <a href="{{ $material->url }}" target="_blank" rel="noopener noreferrer">Abrir ↗</a>
                            @endif
                            @if($material->source === 'manual')
                                <a href="{{ route('materials.edit', $material) }}">Editar</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty">Ainda não existem materiais registados para esta UC.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Sumários e matéria lecionada</h3><span>{{ $course->lessonSummaries->count() }}</span></div>
            @forelse($course->lessonSummaries as $summary)
                <article class="lesson-summary">
                    <div class="lesson-summary-head">
                        <strong>{{ $summary->title }}</strong>
                        @if($summary->occurred_at)
                            <span>até {{ $summary->occurred_at->copy()->timezone(config('app.timezone'))->format('d/m/Y') }}</span>
                        @endif
                    </div>
                    <p>{!! nl2br(e($summary->content)) !!}</p>
                    <small>{{ $summary->source === 'inforestudante_audit' ? 'Fonte: InforEstudante auditado' : $summary->source }}</small>
                </article>
            @empty
                <p class="empty">Ainda não existem sumários recolhidos para esta UC.</p>
            @endforelse
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Histórico recente</h3><span>{{ $recentClasses->count() }}</span></div>
            <div class="history-grid">
                @forelse ($recentClasses as $class)
                    <div class="history-item">
                        <strong>{{ $class->localStartsAt()->format('d/m · H:i') }}</strong>
                        <span>{{ $class->location ?: 'Sala por confirmar' }}</span>
                        <small>{{ $class->title }}</small>
                    </div>
                @empty
                    <p class="empty">Sem histórico importado.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Fontes</h3><span>{{ $course->sourceCourses->count() }}</span></div>
            @forelse ($course->sourceCourses as $sourceCourse)
                <div class="source-row">
                    <div>
                        <strong>{{ $sourceCourse->source }}</strong>
                        <span>{{ $sourceCourse->external_name }}</span>
                    </div>
                    <code>{{ $sourceCourse->external_id }}</code>
                </div>
            @empty
                <p class="empty">Sem fonte académica associada.</p>
            @endforelse
        </section>
    </main>
</div>
</body>
</html>
