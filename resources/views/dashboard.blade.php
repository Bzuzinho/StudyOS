<!doctype html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Painel académico · StudyOS</title>
@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=knowledge-20261020"></head>
<body class="learning-page"><div class="shell">
@include('partials.learning-nav',['active'=>'/'])
<header class="learning-header"><div><p class="eyebrow">Acompanhamento académico</p><h1>Painel académico</h1><p class="muted-text">Calendário, matéria lecionada e conhecimento efetivamente demonstrado. Sem confundir estudo com avaliação.</p></div><a class="button primary" href="/practice">Avaliar conhecimento no ChatGPT</a></header>

<section class="learning-stats" aria-label="Resumo global de aprendizagem">
<div class="learning-stat"><span>UCs ativas</span><strong>{{ $courseCount }}</strong><small>semestre em acompanhamento</small></div>
<div class="learning-stat"><span>Matéria lecionada</span><strong>{{ $knowledge['taught'] }} / {{ $knowledge['total'] }}</strong><small>{{ $knowledge['coverage_percent'] }}% dos subtemas</small></div>
<div class="learning-stat"><span>Conhecimento avaliado</span><strong>{{ $knowledge['assessed'] }} / {{ $knowledge['total'] }}</strong><small>{{ $knowledge['evidence_percent'] }}% dos subtemas com nível sustentado</small></div>
<div class="learning-stat"><span>Domínio demonstrado</span><strong>{{ $knowledge['mastered'] }} / {{ $knowledge['total'] }}</strong><small>{{ $knowledge['mastery_percent'] }}% competentes ou fortes</small></div>
</section>
<main class="learning-columns">
<div class="learning-stack">
<section class="card"><div class="card-head"><h3>Estado de conhecimento por UC</h3><a href="/courses">Abrir UCs →</a></div>
<p class="muted-text">Indicadores relativos aos subtemas curriculares. «Sem evidência» não significa 0% de conhecimento. As percentagens representam cobertura, não uma nota universitária.</p>
@forelse($knowledge['courses'] as $summary)
<article class="learning-line">
<div class="learning-actions" style="justify-content:space-between;align-items:flex-start">
<div><strong><a href="{{ route('courses.show',$summary['course'],false) }}">{{ $summary['course']->name }}</a></strong><p>{{ $summary['taught'] }}/{{ $summary['total'] }} lecionados · {{ $summary['assessed'] }} avaliados · {{ $summary['mastered'] }} com domínio</p></div>
<a href="{{ route('practice.external.index',['course'=>$summary['course'],'mode'=>'status'],false) }}">Diagnosticar →</a>
</div>
<div class="learning-actions" style="gap:10px;flex-wrap:wrap">
<span class="muted-text">Sem evidência: {{ $summary['status_counts']['no_evidence'] }}</span>
<span class="muted-text">Insuficiente: {{ $summary['status_counts']['insufficient_evidence'] }}</span>
<span class="muted-text">Frágil: {{ $summary['status_counts']['fragile'] }}</span>
<span class="muted-text">Em desenvolvimento: {{ $summary['status_counts']['developing'] }}</span>
<span class="muted-text">Competente: {{ $summary['status_counts']['competent'] }}</span>
<span class="muted-text">Forte: {{ $summary['status_counts']['strong'] }}</span>
</div>
@if($summary['last_assessed_on'])<p class="muted-text">Último relatório: {{ $summary['last_assessed_on'] }} · {{ $summary['last_score_20'] === null ? 'sem nota global' : number_format((float)$summary['last_score_20'],1,',',' ').'/20' }}</p>@endif
</article>
@empty<p class="empty">Ainda não existem UCs ativas.</p>@endforelse
</section>
<section class="card"><div class="card-head"><h3>Hoje</h3><a href="/calendar">Ver calendário →</a></div>
@forelse($todayRows as $row)
@php($event=$row['event'])
<article class="item daily-event {{ $event->eventType() === 'assessment' ? 'assessment-event' : '' }}">
<div class="time">{{ $event->localStartsAt()->format('H:i') }}</div>
<div class="daily-event-content"><div class="daily-event-details">
@if($event->course)<a href="{{ route('courses.show',$event->course,false) }}"><strong>{{ $event->course->name }}</strong></a>@else<strong>{{ $event->title }}</strong>@endif
<p>{{ $event->eventTypeLabel() }} · {{ $event->location ?: 'Sala por confirmar' }}</p>
</div>@include('partials.inline-assessments',['owner'=>$event,'assessments'=>$row['assessments']])</div>
</article>
@empty<p class="empty">Sem ocorrências registadas para hoje.</p>@endforelse
</section>
<section class="card"><div class="card-head"><h3>Próximas avaliações</h3><a href="/activities">Atividades →</a></div>
@forelse($upcomingAssessments as $assessment)
<article class="item"><div class="date">{{ $assessment->due_at->copy()->timezone(config('app.timezone'))->format('d/m') }}</div><div><strong>{{ $assessment->title }}</strong><p>{{ $assessment->course?->name }} · {{ $assessment->confirmed ? 'Confirmada' : 'Por confirmar' }}</p></div></article>
@empty<p class="empty">Sem avaliações futuras registadas.</p>@endforelse
</section>
</div>
<aside class="learning-stack">
<section class="card"><div class="card-head"><h3>Prioridades de estudo</h3><a href="/courses">Ver UCs →</a></div>
<p class="muted-text">Prioridades orientativas: matéria lecionada com domínio frágil ou insuficientemente avaliado vem primeiro.</p>
@forelse($knowledge['priorities'] as $item)
<div class="learning-line"><strong>{{ $item['number'] }} · {{ $item['title'] }}</strong>
<p>{{ $knowledge['courses']->first(fn($summary) => $summary['course']->id === $item['course_id'])['course']->name ?? 'UC' }}</p>
<p class="muted-text">{{ $item['reason'] }} · {{ $item['evidence_units'] }} evidência(s)</p>
<a href="{{ route('practice.external.index',['course'=>$item['course_id'],'topic_id'=>$item['topic_id']],false) }}">Trabalhar no ChatGPT →</a></div>
@empty<p class="empty">Sem subtemas para priorizar.</p>@endforelse
</section>
<section class="card"><div class="card-head"><h3>Tarefas pendentes</h3><a href="/activities">Ver atividades →</a></div>
@forelse($pendingTasks as $task)
<article class="item"><div class="date">{{ $task->due_at ? $task->due_at->copy()->timezone(config('app.timezone'))->format('d/m') : '—' }}</div><div><strong>{{ $task->title }}</strong><p>{{ $task->course?->name }}</p></div></article>
@empty<p class="empty">Sem tarefas pendentes.</p>@endforelse
</section>

</aside>
</main></div></body></html>