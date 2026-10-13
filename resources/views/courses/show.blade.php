<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $course->name }} · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head>
<body class="learning-page"><div class="shell">
@include('partials.learning-nav', ['active' => '/courses'])
<div class="breadcrumb"><a href="/courses">← Unidades curriculares</a></div>
@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<header class="learning-header">
<div><p class="eyebrow">{{ $course->academic_code }} · {{ $course->semester }}.º semestre · {{ $course->status === 'active' ? 'Ativa' : 'Planeada' }}</p><h1>{{ $course->name }}</h1></div>
<div><div class="learning-actions"><a class="button primary" href="{{ route('study.create', ['course_id' => $course->id, 'now' => 1], false) }}">Estudar</a><a class="button" href="{{ route('practice.runs.create', ['course_id' => $course->id], false) }}">Iniciar treino</a><a class="button ghost" href="{{ route('materials.index', ['course_id' => $course->id], false) }}">Materiais</a></div><a class="learning-import" href="{{ route('courses.import-package', $course, false) }}">Importar / atualizar pacote da UC →</a></div>
</header>
<section class="learning-stats" aria-label="Resumo da unidade curricular">
<div class="learning-stat"><span>Aulas</span><strong>{{ $course->elapsed_classes_count }} / {{ $course->scheduled_classes_count }}</strong><small>decorridas / programadas</small></div>
<div class="learning-stat"><span>Matéria</span><strong>{{ $leaves->whereNotNull('taught_at')->count() }} / {{ $leaves->count() }}</strong><small>subtemas lecionados</small></div>
<div class="learning-stat"><span>Aprendizagem</span><strong>{{ $leaves->filter(fn ($t) => in_array($t->mastery?->status, ['competent', 'strong']))->count() }} / {{ $leaves->count() }}</strong><small>subtemas com domínio demonstrado</small></div>
<div class="learning-stat"><span>Próxima avaliação</span><strong class="stat-detail">{{ $nextAssessment?->title ?? 'Sem avaliação agendada' }}</strong><small>{{ $nextAssessment?->due_at?->copy()->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</small></div>
</section>
<main class="learning-columns">
<section class="card" id="package-curriculum"><div id="course-topics"></div><div class="card-head"><h3>Programa curricular</h3><span>{{ $curriculum->whereNull('parent_id')->count() }} temas · {{ $leaves->count() }} subtemas</span></div>
<p class="muted-text">Abre um tema para consultar os subtemas, estudar ou treinar. Assinala a matéria dada nas aulas e guarda as alterações.</p>
@if($curriculum->isNotEmpty())
<form id="package-taught-form" method="POST" action="{{ route('course-topics.coverage', $course, false) }}">@csrf @method('PATCH')</form>
@foreach($curriculum->whereNull('parent_id') as $theme)
@php $children = $curriculum->where('parent_id', $theme->id); $themeLeaves = $children->isNotEmpty() ? $children : collect([$theme]); $taught = $themeLeaves->whereNotNull('taught_at')->count(); $mastered = $themeLeaves->filter(fn ($t) => in_array($t->mastery?->status, ['competent','strong']))->count(); @endphp
<details class="curriculum-theme"><summary>{{ $theme->title }}<small>{{ $taught }} de {{ $themeLeaves->count() }} lecionados · {{ round($taught / max(1,$themeLeaves->count()) * 100) }}% de cobertura @if($mastered) · {{ $mastered }} com domínio demonstrado @endif</small></summary>
@if($children->isNotEmpty())<label class="curriculum-check"><input type="hidden" form="package-taught-form" name="topic_ids[]" value="{{ $theme->id }}"><input form="package-taught-form" class="package-theme-check" type="checkbox" name="taught_topic_ids[]" value="{{ $theme->id }}" data-theme="{{ $theme->id }}" @checked($taught === $children->count())><span>Todo o tema lecionado</span></label>@endif
@foreach($themeLeaves as $topic)
<div class="curriculum-row"><div class="curriculum-row-content"><strong>{{ $topic->title }}</strong><div class="curriculum-row-meta"><label class="learning-actions"><input type="hidden" form="package-taught-form" name="topic_ids[]" value="{{ $topic->id }}"><input form="package-taught-form" class="package-child-check" data-parent="{{ $theme->id }}" type="checkbox" name="taught_topic_ids[]" value="{{ $topic->id }}" @checked($topic->taught_at !== null)> Lecionado</label><span>{{ $manualTopicIds->contains($topic->id) ? 'Manual disponível' : 'Consultar materiais da UC' }}</span><span>{{ $topic->completed_study_sessions_count > 0 ? 'Estudado' : 'Estudo por realizar' }}</span><span>Domínio: {{ ['no_evidence'=>'sem evidência','insufficient_evidence'=>'evidência insuficiente','fragile'=>'frágil','developing'=>'em desenvolvimento','competent'=>'competente','strong'=>'forte'][$topic->mastery?->status ?? 'no_evidence'] ?? 'sem evidência' }}</span></div></div>
<div class="learning-actions"><a class="button ghost" href="{{ route('study.create', ['course_id'=>$course->id,'topic_id'=>$topic->id,'now'=>1], false) }}">Estudar</a><a class="button" href="{{ route('practice.runs.create', ['course_id'=>$course->id,'topic_id'=>$topic->id], false) }}">Treinar</a>@if($manualTopicIds->contains($topic->id))<a class="learning-import" href="{{ route('courses.package-topic.read', ['course'=>$course,'topic'=>$topic], false) }}">Manual →</a>@endif</div></div>
@endforeach</details>@endforeach
<div class="learning-actions"><button class="button primary" type="submit" form="package-taught-form">Guardar matéria lecionada</button><span class="muted-text" id="coverage-feedback" role="status">A marcação não altera o domínio.</span></div>
@else<p class="empty">Importa um pacote para organizar o programa e os materiais desta UC.</p><a class="button" href="{{ route('course-topics.create', $course, false) }}">Acrescentar tópico manualmente</a>@endif
</section>
<aside class="learning-stack">
<section class="card"><div class="card-head"><h3>Próximas ações</h3></div>
<div class="learning-line"><strong>Próxima aula</strong><p>{{ $nextClass ? $nextClass->localStartsAt()->format('d/m · H:i').' · '.($nextClass->location ?: 'Sala por confirmar') : 'Sem próxima aula agendada' }}</p><a href="/calendar">Abrir calendário →</a></div>
<div class="learning-line"><strong>Próxima avaliação</strong><p>{{ $nextAssessment?->title ?? 'Sem avaliação agendada' }}</p><a href="{{ route('activities.index', ['course_id'=>$course->id], false) }}">Ver avaliações →</a></div>
<div class="learning-line"><strong>Próxima sessão de estudo</strong><p>{{ $nextSession ? $nextSession->localStartsAt()->format('d/m · H:i').' · '.($nextSession->title ?: 'Estudo') : 'Sem sessão planeada' }}</p><a href="/study">Abrir plano de estudo →</a></div>
@if($priority)<div class="learning-line"><strong>Prioridade de revisão</strong><p>{{ $priority->title }}</p><a href="{{ route('practice.runs.create', ['course_id'=>$course->id,'topic_id'=>$priority->id], false) }}">Rever este subtema →</a></div>@endif
</section>
<section class="card"><div class="card-head"><h3>Materiais</h3></div><p class="muted-text">{{ $availableMaterials->count() }} materiais pedagógicos disponíveis · {{ $manualTopicIds->count() }} subtemas com manual associado.</p><p class="muted-text">{{ $availableMaterials->where('source', '!=', 'chatgpt_package')->count() }} materiais complementares da UC.</p><a class="button ghost" href="{{ route('materials.index', ['course_id'=>$course->id], false) }}">Abrir biblioteca da UC</a></section>
<section class="card"><div class="card-head"><h3>Treino e prática</h3></div><p class="muted-text">Escolhe a matéria, responde às perguntas e consulta a classificação e a correção no final.</p><a class="button primary" href="{{ route('practice.runs.create', ['course_id'=>$course->id], false) }}">Treinar esta unidade curricular</a></section>
</aside></main></div>
<script>(()=>{document.querySelectorAll('.package-theme-check').forEach(parent=>{const children=[...document.querySelectorAll('.package-child-check')].filter(c=>c.dataset.parent===parent.dataset.theme);const refresh=()=>{parent.checked=children.every(c=>c.checked);parent.indeterminate=children.some(c=>c.checked)&&!parent.checked;};refresh();parent.addEventListener('change',()=>{children.forEach(c=>c.checked=parent.checked);refresh();});children.forEach(c=>c.addEventListener('change',refresh));});document.querySelectorAll('[form="package-taught-form"][type="checkbox"]').forEach(c=>c.addEventListener('change',()=>{document.getElementById('coverage-feedback').textContent='Alterações por guardar';}));})();</script>
</body></html>
