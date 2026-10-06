<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a class="active" href="/">Dashboard</a>
            <a href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
            <a href="/study">Estudo</a>
            <a href="/practice">Prática</a>
        </div>
    </nav>

    <header class="topbar">
        <div><p class="eyebrow">StudyOS · Alpha 0.15</p><h1>Painel académico</h1></div>
        <div class="sync {{ $lastSync?->status === 'success' ? 'ok' : '' }}"><span></span>{{ $lastSync ? 'Última sincronização: '.$lastSync->started_at?->copy()->timezone(config('app.timezone'))->format('d/m H:i') : 'Sincronização ainda não configurada' }}</div>
    </header>

    <section class="hero">
        <div>
            <p class="eyebrow">Semestre atual</p>
            <h2>{{ $courseCount }} UCs ativas</h2>
            <p>{{ $plannedCourseCount }} UCs já registadas para o 2.º semestre. Agenda, avaliações e progresso passam a ficar associados à UC correta.</p>
        </div>
        <div class="metric"><strong>{{ $todayClasses->count() }}</strong><span>aulas hoje</span></div>
    </section>

    <main class="grid">
        <section class="card">
            <div class="card-head"><h3>Hoje</h3><a href="/calendar">Ver calendário</a></div>
            @forelse ($todayClasses as $class)
                <article class="item">
                    <div class="time">{{ $class->localStartsAt()->format('H:i') }}</div>
                    <div>
                        @if($class->course)
                            <a href="{{ route('courses.show', $class->course) }}"><strong>{{ $class->course->name }}</strong></a>
                        @else
                            <strong>{{ $class->title }}</strong>
                        @endif
                        <p>{{ $class->location ?: 'Sala por confirmar' }}</p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não há ocorrências importadas para hoje.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Próximas avaliações</h3><span>{{ $upcomingAssessments->count() }}</span></div>
            @forelse ($upcomingAssessments as $assessment)
                <article class="item"><div class="date">{{ $assessment->due_at->copy()->timezone(config('app.timezone'))->format('d/m') }}</div><div><strong>{{ $assessment->title }}</strong><p>{{ $assessment->course?->name }} · {{ $assessment->confirmed ? 'Confirmada' : 'Por confirmar' }}</p></div></article>
            @empty
                <p class="empty">Nenhuma avaliação importada.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Tarefas pendentes</h3><a href="/activities">Ver atividades</a></div>
            @forelse ($pendingTasks as $task)
                <article class="item">
                    <div class="date">{{ $task->due_at ? $task->due_at->copy()->timezone(config('app.timezone'))->format('d/m') : '—' }}</div>
                    <div>
                        <strong>{{ $task->title }}</strong>
                        <p>{{ $task->course?->name }} · {{ $task->due_at ? 'Com prazo' : 'Prazo não publicado' }}</p>
                    </div>
                </article>
            @empty
                <p class="empty">Sem tarefas pendentes registadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Próximo estudo</h3><a href="/study">Plano de estudo</a></div>
            <div class="learning-metrics">
                <div><strong>{{ $topicCount }}</strong><span>tópicos</span></div>
                <div><strong>{{ $plannedStudyMinutes }}</strong><span>min planeados</span></div>
            </div>
            @forelse($upcomingStudySessions as $session)
                <article class="item">
                    <div class="date">{{ $session->localStartsAt()->format('d/m') }}</div>
                    <div>
                        <strong>{{ $session->title ?: $session->course->name }}</strong>
                        <p>{{ $session->localStartsAt()->format('H:i') }} · {{ $session->planned_minutes }} min · planeado</p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não existem sessões futuras planeadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Prática e domínio</h3><a href="/practice">Abrir prática</a></div>
            <div class="learning-metrics">
                <div><strong>{{ $exerciseCount }}</strong><span>exercícios</span></div>
                <div><strong>{{ $gradedAttemptCount }}</strong><span>tentativas corrigidas</span></div>
                <div><strong>{{ $masteryEvidenceCount }}</strong><span>tópicos com evidência</span></div>
            </div>
            <div class="corpus-summary">
                <span>{{ $sourceChunkCount }} fragmento(s) indexado(s)</span>
                <span>{{ $richSourceChunkCount }} apto(s) para geração</span>
                <span>{{ $groundedExerciseCount }} exercício(s) gerado(s) da fonte</span>
            </div>
            @forelse($recentAttempts as $attempt)
                <article class="item">
                    <div class="date">{{ $attempt->percentage !== null ? number_format((float) $attempt->percentage, 0).'%' : '…' }}</div>
                    <div>
                        <a href="{{ route('practice.show', $attempt->exercise) }}"><strong>{{ $attempt->exercise->title ?: 'Exercício' }}</strong></a>
                        <p>{{ $attempt->exercise->course->name }} · {{ $attempt->grading_status === 'graded' ? 'corrigida' : 'aguarda revisão' }}</p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não existem tentativas de prática.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Contexto de estudo</h3><a href="/materials">Ver materiais</a></div>
            <div class="learning-metrics">
                <div><strong>{{ $materialCount }}</strong><span>materiais</span></div>
                <div><strong>{{ $uploadedFileCount }}</strong><span>ficheiros cloud</span></div>
                <div><strong>{{ $extractedFileCount }}</strong><span>extraídos</span></div>
                @if($queuedFileCount > 0)
                    <div><strong>{{ $queuedFileCount }}</strong><span>em processamento</span></div>
                @endif
            </div>
            @forelse($recentMaterials as $material)
                <article class="item">
                    <div class="material-icon">{{ strtoupper(substr($material->type, 0, 2)) }}</div>
                    <div>
                        <strong>{{ $material->title }}</strong>
                        @php $materialVersion = $material->versions->first(); @endphp
                        <p>
                            {{ $material->course?->name }} · {{ $material->source === 'manual' ? 'Manual' : ($material->source === 'moodle' ? 'Moodle automático' : 'Fonte auditada') }}
                            @if($materialVersion?->storage_path)
                                · ficheiro guardado
                            @endif
                        </p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não existem materiais registados.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>UCs do 1.º semestre</h3><a href="/courses">Ver todas</a></div>
            <div class="compact-course-grid">
                @forelse ($activeCourses as $course)
                    <a class="compact-course" href="{{ route('courses.show', $course) }}">
                        <span class="course-code">{{ $course->academic_code }}</span>
                        <strong>{{ $course->name }}</strong>
                        <small>{{ $course->class_occurrences_count }} ocorrências ligadas</small>
                    </a>
                @empty
                    <p class="empty">O catálogo académico ainda não foi inicializado.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Roadmap Alpha</h3><span>0.15</span></div>
            <div class="roadmap">
                <div class="done"><b>01</b><span>Fundação de dados</span></div>
                <div class="done"><b>02</b><span>iCalendar InforEstudante</span></div>
                <div class="done"><b>03</b><span>Calendário visual</span></div>
                <div class="done"><b>04</b><span>UCs e associação automática</span></div>
                <div class="done"><b>05</b><span>Avaliações oficiais</span></div>
                <div class="done"><b>06</b><span>Moodle e atividades</span></div>
                <div class="done"><b>07</b><span>Fiabilidade da sincronização</span></div>
                <div class="done"><b>08</b><span>Materiais e contexto de aprendizagem</span></div>
                <div class="done"><b>09</b><span>Tópicos e planeamento de estudo</span></div>
                <div class="done"><b>10</b><span>Exercícios, tentativas e domínio</span></div>
                <div class="done"><b>11</b><span>Corpus com proveniência e prática fundamentada</span></div>
                <div class="done"><b>12</b><span>Upload cloud e extração de documentos</span></div>
                <div class="done"><b>13</b><span>Extração assíncrona e worker cloud</span></div>
                <div class="done"><b>14</b><span>Sincronização automática do Moodle</span></div>
                <div class="active"><b>15</b><span>Autenticação Moodle via Microsoft/ULO SSO</span></div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
