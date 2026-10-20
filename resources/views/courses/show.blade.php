<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $course->name }} · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head>
<body class="learning-page"><div class="shell">
@include('partials.learning-nav', ['active' => '/courses'])
<div class="breadcrumb"><a href="/courses">← Unidades curriculares</a></div>
@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<header class="learning-header">
<div><p class="eyebrow">{{ $course->academic_code }} · {{ $course->semester }}.º semestre · {{ $course->status === 'active' ? 'Ativa' : 'Planeada' }}</p><h1>{{ $course->name }}</h1></div>
<div><div class="learning-actions"><a class="button primary" href="{{ route('practice.external.index', ['course'=>$course, 'mode'=>'study'], false) }}">Preparar estudo no ChatGPT</a><a class="button" href="{{ route('practice.external.index', ['course' => $course->id], false) }}">Treinar no ChatGPT</a></div></div>
</header>
<section class="learning-stats" aria-label="Resumo da unidade curricular">
<div class="learning-stat"><span>Aulas</span><strong>{{ $course->elapsed_classes_count }} / {{ $course->scheduled_classes_count }}</strong><small>decorridas / programadas</small></div>
<div class="learning-stat"><span>Matéria</span><strong>{{ $leaves->whereNotNull('taught_at')->count() }} / {{ $leaves->count() }}</strong><small>subtemas lecionados</small></div>
<div class="learning-stat"><span>Aprendizagem</span><strong>{{ $leaves->filter(fn ($t) => in_array($t->mastery?->status, ['competent', 'strong']))->count() }} / {{ $leaves->count() }}</strong><small>subtemas com domínio demonstrado</small></div>
<div class="learning-stat"><span>Próxima avaliação</span><strong class="stat-detail">{{ $nextAssessment?->title ?? 'Sem avaliação agendada' }}</strong><small>{{ $nextAssessment?->due_at?->copy()->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</small></div>
</section>
<section class="card" aria-label="Diagnóstico de conhecimento">
<div class="card-head"><h3>Conhecimento demonstrado</h3><a href="{{ route('practice.external.index',['course'=>$course,'mode'=>'status'],false) }}">Atualizar diagnóstico no ChatGPT →</a></div>
<p class="muted-text">O estado é calculado a partir de evidências de exercícios e avaliações importadas. Não representa uma classificação oficial. Estudar um tema não equivale a dominá-lo.</p>
<div class="learning-metrics">
<div><strong>{{ $knowledge['with_evidence'] }}/{{ $knowledge['total'] }}</strong><span>com alguma evidência</span></div>
<div><strong>{{ $knowledge['assessed'] }}/{{ $knowledge['total'] }}</strong><span>com nível sustentado</span></div>
<div><strong>{{ $knowledge['mastered'] }}/{{ $knowledge['total'] }}</strong><span>competentes ou fortes</span></div>
<div><strong>{{ $knowledge['status_counts']['fragile'] + $knowledge['status_counts']['developing'] }}</strong><span>por consolidar</span></div>
</div>
<div class="learning-actions" style="flex-wrap:wrap">
<span class="muted-text">Sem evidência: {{ $knowledge['status_counts']['no_evidence'] }}</span>
<span class="muted-text">Insuficiente: {{ $knowledge['status_counts']['insufficient_evidence'] }}</span>
<span class="muted-text">Frágil: {{ $knowledge['status_counts']['fragile'] }}</span>
<span class="muted-text">Em desenvolvimento: {{ $knowledge['status_counts']['developing'] }}</span>
<span class="muted-text">Competente: {{ $knowledge['status_counts']['competent'] }}</span>
<span class="muted-text">Forte: {{ $knowledge['status_counts']['strong'] }}</span>
</div>
@if($knowledge['last_assessed_on'])<p class="muted-text">Último relatório importado: {{ $knowledge['last_assessed_on'] }} · {{ $knowledge['last_score_20'] === null ? 'sem nota global' : number_format((float)$knowledge['last_score_20'],1,',',' ').'/20' }}</p>@endif
@if(count($knowledge['difficulties']))<p><strong>Dificuldades recentes:</strong> {{ implode(' · ', $knowledge['difficulties']) }}</p>@endif
@if(count($knowledge['conceptual_errors']))<p><strong>Erros conceptuais:</strong> {{ implode(' · ', $knowledge['conceptual_errors']) }}</p>@endif
@if(count($knowledge['recommendations']))<p><strong>Recomendações do último relatório:</strong> {{ implode(' · ', $knowledge['recommendations']) }}</p>@endif
</section>
@if($knowledge['report_count'] > 0)
<section class="card" aria-label="Relatórios de conhecimento importados">
<div class="card-head"><h3>Estados de conhecimento importados</h3><a href="{{ route('practice.external.index',$course,false) }}#import-history">Consultar histórico completo →</a></div>
<p><strong>{{ $knowledge['report_count'] }}</strong> relatório(s) guardado(s) nesta UC · <strong>{{ $knowledge['imported_topics_count'] }}</strong> referência(s) a subtemas nos últimos relatórios.</p>
@if($knowledge['assessed'] === 0)
<p class="muted-text">Existe histórico importado, mas nenhum subtema tem ainda um nível de conhecimento sustentado nos indicadores atuais. Os relatórios anteriores estão preservados para consulta; não são convertidos automaticamente em domínio demonstrado sem evidência compatível.</p>
@endif
@foreach(array_slice($knowledge['imported_topics'],0,8) as $item)
@if(is_array($item))
<div class="learning-line"><strong>{{ $item['number'] ?? 'Subtema' }}</strong>
<p>{{ $item['feedback'] ?? ($item['notes'] ?? 'Avaliação registada no histórico') }}</p>
@if(isset($item['score_percent']) && is_numeric($item['score_percent']))<p class="muted-text">{{ number_format((float)$item['score_percent'],0) }}% indicado no relatório original</p>@endif
</div>
@endif
@endforeach
</section>
@endif
<main class="learning-columns">
<section class="card" id="package-curriculum"><div id="course-topics"></div><div class="card-head"><h3>Programa curricular</h3><span>{{ $curriculum->whereNull('parent_id')->count() }} temas · {{ $leaves->count() }} subtemas</span></div>
<p class="muted-text">Abre um tema para consultar os subtemas, estudar ou treinar. Assinala a matéria dada nas aulas e guarda as alterações.</p>
@if($curriculum->isNotEmpty())
<form id="package-taught-form" method="POST" action="{{ route('course-topics.coverage', $course, false) }}">@csrf @method('PATCH')</form>
@foreach($curriculum->whereNull('parent_id') as $theme)
@php $children = $curriculum->where('parent_id', $theme->id); $themeLeaves = $children->isNotEmpty() ? $children : collect([$theme]); $taught = $themeLeaves->whereNotNull('taught_at')->count(); $mastered = $themeLeaves->filter(fn ($t) => in_array($t->mastery?->status, ['competent','strong']))->count(); @endphp
<details class="curriculum-theme"><summary>{{ isset($theme->metadata['curricular_number']) ? $theme->metadata['curricular_number'].'. ' : '' }}{{ $theme->title }}<small>{{ $taught }} de {{ $themeLeaves->count() }} lecionados · {{ round($taught / max(1,$themeLeaves->count()) * 100) }}% de cobertura @if($mastered) · {{ $mastered }} com domínio demonstrado @endif</small></summary>
@if($children->isNotEmpty())<label class="curriculum-check"><input type="hidden" form="package-taught-form" name="topic_ids[]" value="{{ $theme->id }}"><input form="package-taught-form" class="package-theme-check" type="checkbox" name="taught_topic_ids[]" value="{{ $theme->id }}" data-theme="{{ $theme->id }}" @checked($taught === $children->count())><span>Todo o tema lecionado</span></label>@endif
@foreach($themeLeaves as $topic)
<div class="curriculum-row"><div class="curriculum-row-content"><strong>{{ isset($topic->metadata['curricular_number']) ? $topic->metadata['curricular_number'].' ' : '' }}{{ $topic->title }}</strong><div class="curriculum-row-meta"><label class="learning-actions"><input type="hidden" form="package-taught-form" name="topic_ids[]" value="{{ $topic->id }}"><input form="package-taught-form" class="package-child-check" data-parent="{{ $theme->id }}" type="checkbox" name="taught_topic_ids[]" value="{{ $topic->id }}" @checked($topic->taught_at !== null)> Lecionado</label><span>{{ $topic->completed_study_sessions_count > 0 ? 'Estudado' : 'Estudo por realizar' }}</span>@php($number=(string)($topic->metadata['curricular_number'] ?? ''))
@php($imported=$knowledge['latest_by_number'][$number] ?? null)
@php($level=$topic->mastery?->status ?? 'no_evidence')
<span><strong>Conhecimento:</strong> {{ ['no_evidence'=>'Por avaliar','insufficient_evidence'=>'Evidência insuficiente','fragile'=>'Frágil','developing'=>'Em desenvolvimento','competent'=>'Competente','strong'=>'Forte'][$level] ?? 'Por avaliar' }}</span>
@if($topic->mastery?->score_percent !== null)<span>{{ number_format((float)$topic->mastery->score_percent,0) }}% estimados</span>@endif
@if($imported)<span>Última importação: {{ $imported['assessed_on'] }}@if($imported['score_percent'] !== null) · {{ number_format((float)$imported['score_percent'],0) }}% no relatório @endif</span>@endif
@if($imported && $level === 'no_evidence')<span>Importação guardada, ainda sem domínio consolidado</span>@endif</div></div>
<div class="learning-actions"><a class="button ghost" href="{{ route('practice.external.index', ['course'=>$course->id,'topic_id'=>$topic->id,'mode'=>'study'], false) }}">Estudar</a><a class="button" href="{{ route('practice.external.index', ['course'=>$course->id,'topic_id'=>$topic->id], false) }}">Treinar</a></div></div>
@endforeach</details>@endforeach
<div class="learning-actions"><button class="button primary" type="submit" form="package-taught-form">Guardar matéria lecionada</button><span class="muted-text" id="coverage-feedback" role="status">A marcação não altera o domínio.</span></div>
@else<p class="empty">Importa um pacote para organizar o programa e os materiais desta UC.</p><a class="button" href="{{ route('course-topics.create', $course, false) }}">Acrescentar tópico manualmente</a>@endif
</section>
<aside class="learning-stack">
<section class="card"><div class="card-head"><h3>Próximas ações</h3></div>
<div class="learning-line"><strong>Próxima aula</strong><p>{{ $nextClass ? $nextClass->localStartsAt()->format('d/m · H:i').' · '.($nextClass->location ?: 'Sala por confirmar') : 'Sem próxima aula agendada' }}</p><a href="/calendar">Abrir calendário →</a></div>
<div class="learning-line"><strong>Próxima avaliação</strong><p>{{ $nextAssessment?->title ?? 'Sem avaliação agendada' }}</p><a href="{{ route('activities.index', ['course_id'=>$course->id], false) }}">Ver avaliações →</a></div>

@if($priority)<div class="learning-line"><strong>Prioridade de revisão</strong><p>{{ $priority->title }}</p><a href="{{ route('practice.external.index', ['course'=>$course->id,'topic_id'=>$priority->id], false) }}">Rever este subtema no ChatGPT →</a></div>@endif
</section>

<section class="card"><div class="card-head"><h3>Treino e prática</h3></div><p class="muted-text">Escolhe os subtemas no StudyOS, realiza o treino no projeto ChatGPT desta UC e importa no final a avaliação estruturada.</p><a class="button primary" href="{{ route('practice.external.index', ['course'=>$course->id], false) }}">Preparar treino no ChatGPT</a></section>
</aside></main></div>
<script>(()=>{document.querySelectorAll('.package-theme-check').forEach(parent=>{const children=[...document.querySelectorAll('.package-child-check')].filter(c=>c.dataset.parent===parent.dataset.theme);const refresh=()=>{parent.checked=children.every(c=>c.checked);parent.indeterminate=children.some(c=>c.checked)&&!parent.checked;};refresh();parent.addEventListener('change',()=>{children.forEach(c=>c.checked=parent.checked);refresh();});children.forEach(c=>c.addEventListener('change',refresh));});document.querySelectorAll('[form="package-taught-form"][type="checkbox"]').forEach(c=>c.addEventListener('change',()=>{document.getElementById('coverage-feedback').textContent='Alterações por guardar';}));})();</script>
</body></html>
