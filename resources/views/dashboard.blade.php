<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyOS</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div><p class="eyebrow">StudyOS · Alpha 0.1</p><h1>Painel académico</h1></div>
        <div class="sync {{ $lastSync?->status === 'success' ? 'ok' : '' }}"><span></span>{{ $lastSync ? 'Última sincronização: '.$lastSync->started_at?->format('d/m H:i') : 'Sincronização ainda não configurada' }}</div>
    </header>
    <section class="hero">
        <div><p class="eyebrow">Visão geral</p><h2>{{ $courseCount }} UCs configuradas</h2><p>Fundação do StudyOS: modelo académico, agenda, avaliações e rastreio de sincronizações.</p></div>
        <div class="metric"><strong>{{ $todayClasses->count() }}</strong><span>aulas hoje</span></div>
    </section>
    <main class="grid">
        <section class="card">
            <div class="card-head"><h3>Hoje</h3><span>{{ now()->format('d/m') }}</span></div>
            @forelse ($todayClasses as $class)
                <article class="item"><div class="time">{{ $class->starts_at->format('H:i') }}</div><div><strong>{{ $class->course?->name ?? $class->title }}</strong><p>{{ $class->location ?: 'Sala por confirmar' }}</p></div></article>
            @empty
                <p class="empty">Ainda não há ocorrências importadas. O primeiro conector será o iCalendar do InforEstudante.</p>
            @endforelse
        </section>
        <section class="card">
            <div class="card-head"><h3>Próximas avaliações</h3><span>{{ $upcomingAssessments->count() }}</span></div>
            @forelse ($upcomingAssessments as $assessment)
                <article class="item"><div class="date">{{ $assessment->due_at->format('d/m') }}</div><div><strong>{{ $assessment->title }}</strong><p>{{ $assessment->course?->name }} · {{ $assessment->confirmed ? 'Confirmada' : 'Por confirmar' }}</p></div></article>
            @empty
                <p class="empty">Nenhuma avaliação importada. O StudyOS não inventará datas sem evidência da fonte.</p>
            @endforelse
        </section>
        <section class="card wide">
            <div class="card-head"><h3>Roadmap Alpha</h3><span>0.1</span></div>
            <div class="roadmap">
                <div class="done"><b>01</b><span>Fundação de dados</span></div>
                <div class="active"><b>02</b><span>iCalendar InforEstudante</span></div>
                <div><b>03</b><span>Calendário Moodle</span></div>
                <div><b>04</b><span>Dashboard automático</span></div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
