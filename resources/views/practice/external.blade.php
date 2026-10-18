<!doctype html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Avaliação no ChatGPT · {{ $course->name }} · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head>
<body class="learning-page"><div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/practice'])
<div class="breadcrumb"><a href="/practice">← Prática</a></div>
<header class="form-header"><p class="eyebrow">Treino e validação externa</p><h1>Praticar {{ $course->name }} no ChatGPT</h1><p>O StudyOS gera a instrução e recebe o relatório final. A avaliação é pedagógica, não uma nota oficial.</p></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="card" id="external-prompt-builder" data-code="{{ $course->academic_code }}" data-course="{{ $course->name }}">
<h2>1. Preparar atividade no ChatGPT</h2>
<form method="POST" action="{{ route('practice.external.project',$course,false) }}" class="learning-actions">
@csrf
<label class="field" style="flex:1"><span>Endereço do projeto ChatGPT desta UC</span><input type="url" name="project_url" value="{{ $projectUrl }}" required placeholder="https://chatgpt.com/g/g-p-.../project"></label>
<button class="button ghost" type="submit">Guardar ligação</button>
</form>
<p class="muted-text">O projeto abre noutra janela. Por segurança, o ChatGPT não aceita que o StudyOS introduza mensagens diretamente no seu campo; a prompt é copiada para colares.</p>
<div class="field"><span>Objetivo</span><select id="external-mode">
<option value="practice" @selected(request('mode') !== 'study' && request('mode') !== 'status')>Praticar e avaliar</option>
<option value="study" @selected(request('mode') === 'study')>Estudar ou rever matéria</option>
<option value="status" @selected(request('mode') === 'status')>Pedir relatório de conhecimento</option>
</select></div>
<p>Seleciona os subtemas que pretendes praticar. Podes escolher vários.</p>
<div style="max-height:350px;overflow:auto;margin:12px 0">
@foreach($topics as $topic)
<label class="learning-actions" style="display:block;padding:8px">
<input type="checkbox" data-assessment-topic value="{{ $topic->metadata['curricular_number'] ?? '' }}" data-topic-title="{{ $topic->title }}" @checked(in_array((string)($topic->metadata['curricular_number'] ?? ''), $selectedTopicNumbers, true))>
{{ $topic->metadata['curricular_number'] ?? '' }} · {{ $topic->title }}
</label>
@endforeach
</div>
<div class="form-grid">
<label class="field"><span>Número de perguntas</span><select id="external-question-count"><option value="5">5</option><option value="10" selected>10</option><option value="15">15</option><option value="20">20</option></select></label>
<label class="field"><span>Grau de dificuldade</span><select id="external-difficulty"><option value="progressiva">Progressiva</option><option value="basica">Básica</option><option value="intermedia">Intermédia</option><option value="avancada">Avançada</option></select></label>
</div>
<button class="button primary" type="button" id="generate-external-prompt">Gerar prompt</button>
<label class="field"><span>Prompt para copiar para o projeto da UC no ChatGPT</span><textarea id="external-prompt" rows="16" readonly placeholder="Seleciona os subtemas e clica em Gerar prompt"></textarea></label>
<div class="learning-actions"><button class="button ghost" type="button" id="copy-external-prompt">Copiar prompt</button>
<button class="button primary" type="button" id="open-chatgpt-project" data-project-url="{{ $projectUrl ?: 'https://chatgpt.com/' }}">Copiar e abrir projeto ChatGPT ↗</button></div>
<p class="muted-text" id="external-prompt-status" role="status"></p>
</section>
<section class="card"><h2>2. Registar o estado de conhecimento</h2>
<p>Depois de terminares o treino no ChatGPT, copia exclusivamente o bloco JSON final e cola-o aqui. O StudyOS valida os códigos dos subtemas e regista o histórico sem alterar notas oficiais.</p>
<form method="POST" action="{{ route('practice.external.store',$course,false) }}">@csrf
<label class="field"><span>Relatório JSON devolvido pelo ChatGPT</span><textarea name="report" rows="12" maxlength="100000" required placeholder='{"schema":"studyos.external-assessment.v1", ...}'>{{ old('report') }}</textarea></label>
<div class="form-actions"><button class="button primary" type="submit">Importar avaliação</button></div></form>
</section>
<section class="card"><h2>Histórico de avaliações externas</h2>
@if($history->isEmpty())<p>Ainda não existem avaliações importadas.</p>@else
@foreach($history as $item)
<article style="border-bottom:1px solid #33424a;padding:12px 0"><strong>{{ $item->assessed_on }} · {{ number_format((float)$item->score_20,2,',',' ') }}/20</strong>
@php($rep=json_decode($item->report,true))
<p class="muted-text">{{ count($rep['topics'] ?? []) }} subtema(s) · {{ $item->external_id }}</p>
@foreach(($rep['topics'] ?? []) as $topic)
<div>{{ $topic['number'] ?? '?' }} — {{ number_format((float)($topic['score_percent']??0),0) }}% · {{ $topic['feedback'] ?? '' }}</div>
@endforeach
</article>
@endforeach
@endif
</section>
</div><script src="/js/external-assessment-prompt.js?v=2" defer></script></body></html>