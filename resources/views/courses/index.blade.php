<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UCs · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=course-progress-1">
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
            <a href="/study">Estudo</a>
            <a href="/practice">Prática</a>
        </div>
    </nav>

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Licenciatura em Gestão · 2026/2027</p>
            <h1>Unidades curriculares</h1>
        </div>
        <div class="course-stats">
            <div><strong>{{ $activeCount }}</strong><span>ativas</span></div>
            <div><strong>{{ $plannedCount }}</strong><span>2.º semestre</span></div>
        </div>
    </header>

    @foreach ([1 => '1.º semestre', 2 => '2.º semestre'] as $semester => $label)
        <section class="course-section">
            <div class="section-heading">
                <h2>{{ $label }}</h2>
                <span>{{ ($coursesBySemester[$semester] ?? collect())->count() }} UCs</span>
            </div>

            <div class="course-grid">
                @forelse ($coursesBySemester[$semester] ?? collect() as $course)
                    @php $nextClass = $course->classOccurrences->first(); @endphp
                    <a class="course-card" href="{{ route('courses.show', $course, false) }}">
                        <div class="course-card-head">
                            <span class="course-code">{{ $course->academic_code }}</span>
                            <span class="status-pill {{ $course->status }}">{{ $course->status === 'active' ? 'Ativa' : 'Planeada' }}</span>
                        </div>
                        <h3>{{ $course->name }}</h3>
                        @include('partials.course-class-progress')
                        <div class="course-meta">
                            <span>{{ $course->assessments_count }} avaliações</span>
                            <span>{{ $course->tasks_count }} tarefas</span>
                            <span>{{ $course->materials_count }} materiais</span>
                            <span>{{ $course->taught_topics_count }}/{{ $course->topics_count }} tópicos lecionados</span>
                            <span>{{ $course->exercises_count }} exercícios</span>
                            @if($course->source_chunks_count > 0)
                                <span>{{ $course->source_chunks_count }} fragmento(s) de fonte</span>
                            @endif
                            @if($course->study_sessions_count > 0)
                                <span>{{ $course->study_sessions_count }} sessão(ões) de estudo</span>
                            @endif
                            @if($course->lesson_summaries_count > 0)
                                <span>{{ $course->lesson_summaries_count }} sumário(s)</span>
                            @endif
                            @if($course->ects)<span>{{ $course->ects }} ECTS</span>@endif
                        </div>
                        @if($nextClass)
                            <div class="next-class">
                                <small>Próxima aula</small>
                                <strong>{{ $nextClass->localStartsAt()->translatedFormat('D, d/m · H:i') }}</strong>
                                <span>{{ $nextClass->location ?: 'Sala por confirmar' }}</span>
                            </div>
                        @elseif($course->status === 'active')
                            <div class="next-class muted-text">Sem próxima ocorrência ligada.</div>
                        @endif
                    </a>
                @empty
                    <p class="empty">Ainda não existem UCs neste semestre.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
</body>
</html>
