<!doctype html>
<html lang="pt">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Iniciar treino · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=practice-run-1"></head>
<body><div class="shell narrow-shell">
<nav class="nav"><a class="brand" href="/"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a><div class="nav-links"><a href="/">Dashboard</a><a href="/calendar">Calendário</a><a href="/courses">UCs</a><a href="/materials">Materiais</a><a href="/study">Estudo</a><a class="active" href="/practice">Prática</a></div></nav>
<div class="breadcrumb"><a href="{{ route('practice.index') }}">← Prática</a></div>
<header class="form-header"><p class="eyebrow">Treino guiado</p><h1>Preparar sequência de perguntas</h1><p>Escolhe a UC, os temas ou subtemas e o número de perguntas. Os exercícios são retirados do banco de questões, sem expor a lista completa.</p></header>
@if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('practice.runs.store') }}" class="event-form card">@csrf
<label class="field"><span>Unidade curricular</span><select name="course_id" id="training-course" required><option value="">Selecionar UC...</option>
@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string)$selectedCourseId===(string)$course->id)>{{ $course->name }}</option>@endforeach</select></label>
<div class="field"><span>O que pretendes treinar?</span><div class="topic-grid">
@foreach($courses as $course)
<div class="training-course-topics" data-course="{{ $course->id }}" hidden>
@php $chosenTopics = $course->topics->where('source','chatgpt_package')->isNotEmpty() ? $course->topics->where('source','chatgpt_package') : $course->topics; @endphp
@foreach($chosenTopics->whereNull('parent_id') as $theme)
<fieldset style="border:1px solid #2b4650;border-radius:12px;padding:12px;margin:8px 0">
<legend><label><input type="checkbox" name="topic_ids[]" value="{{ $theme->id }}" @checked($selectedTopicId===$theme->id)> {{ $theme->title }}</label></legend>
<div style="display:flex;flex-direction:column;gap:8px">
@foreach($chosenTopics->where('parent_id',$theme->id) as $topic)<label><input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked($selectedTopicId===$topic->id)> {{ $topic->title }}</label>@endforeach
</div></fieldset>
@endforeach
</div>
@endforeach
</div><small>Podes selecionar um tema completo ou vários subtemas. As perguntas são escolhidas automaticamente entre os existentes.</small></div>
<label class="field"><span>Número de perguntas</span><select name="count">@foreach([5,10,15,20,30] as $n)<option value="{{ $n }}" @selected($n===10)>{{ $n }} perguntas</option>@endforeach</select></label>
@if($studySessionId)<input type="hidden" name="study_session_id" value="{{ $studySessionId }}">@endif
<p class="muted-text">Questões de resposta aberta ficam pendentes de correção por critérios. O resultado distingue perguntas corrigidas e respostas ainda não avaliadas.</p>
<div class="form-actions"><a class="button ghost" href="{{ route('practice.index') }}">Cancelar</a><button class="button primary" type="submit">Iniciar treino</button></div>
</form>
<script>(()=>{const s=document.getElementById('training-course');const groups=[...document.querySelectorAll('.training-course-topics')];const change=()=>groups.forEach(group=>{const visible=group.dataset.course===s.value;group.hidden=!visible;group.querySelectorAll('input').forEach(input=>{input.disabled=!visible})});s.addEventListener('change',change);change();})();</script>
</div></body></html>