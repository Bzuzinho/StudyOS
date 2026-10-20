<!doctype html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Avaliação no ChatGPT · {{ $course->name }} · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head>
<body class="learning-page"><div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/courses'])
<div class="breadcrumb"><a href="{{ route('courses.show',$course,false) }}">← {{ $course->name }}</a></div>
<header class="form-header"><p class="eyebrow">Estudo, prática e conhecimento</p><h1>{{ $course->name }} no ChatGPT</h1><p>O ChatGPT trata do conteúdo pedagógico; o StudyOS valida os resultados, mantém o histórico e atualiza o estado de conhecimento.</p></header>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

<section class="card" id="external-prompt-builder" data-code="{{ $course->academic_code }}" data-course="{{ $course->name }}">
<h2>1. Preparar atividade no ChatGPT</h2>
<form method="POST" action="{{ route('practice.external.project',$course,false) }}" class="learning-actions">
@csrf
<label class="field" style="flex:1"><span>Endereço do projeto ChatGPT desta UC</span><input type="url" name="project_url" value="{{ $projectUrl }}" required placeholder="https://chatgpt.com/g/g-p-.../project"></label>
<button class="button ghost" type="submit">Guardar ligação</button>
</form>
<p class="muted-text">A prompt é copiada e o projeto abre noutra janela. Não é utilizada automação de browser nem uma API paga para ler o projeto ChatGPT.</p>
<div class="field"><span>Objetivo</span><select id="external-mode">
<option value="practice" @selected(request('mode') !== 'study' && request('mode') !== 'status')>Praticar e avaliar</option>
<option value="study" @selected(request('mode') === 'study')>Estudar ou rever matéria</option>
<option value="status" @selected(request('mode') === 'status')>Diagnosticar estado de conhecimento</option>
</select></div>
<p>Seleciona os subtemas que pretendes trabalhar.</p>
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
<label class="field"><span>Prompt para o projeto da UC no ChatGPT</span><textarea id="external-prompt" rows="16" readonly placeholder="Seleciona os subtemas e clica em Gerar prompt"></textarea></label>
<div class="learning-actions"><button class="button ghost" type="button" id="copy-external-prompt">Copiar prompt</button>
<button class="button primary" type="button" id="open-chatgpt-project" data-project-url="{{ $projectUrl ?: 'https://chatgpt.com/' }}">Copiar e abrir projeto ChatGPT ↗</button></div>
<p class="muted-text" id="external-prompt-status" role="status"></p>
</section>

<section class="card"><h2>2. Registar o estado de conhecimento</h2><p class="muted-text">Primeiro analisamos o relatório; nada é gravado antes da confirmação.</p>
<p>Cola o relatório completo devolvido pelo ChatGPT. Pode ser o JSON estruturado ou texto normal. O StudyOS tenta normalizar localmente e não grava nada antes da tua confirmação.</p>
<form method="POST" action="{{ route('practice.external.preview',$course,false) }}">@csrf
<label class="field"><span>Relatório do ChatGPT</span><textarea name="report" rows="12" maxlength="100000" required placeholder="Cola aqui o relatório completo ou o bloco JSON">{{ $rawReport ?? old('report') }}</textarea></label>
<div class="form-actions"><button class="button primary" type="submit">Analisar relatório</button></div></form>
</section>

@if(isset($preview))
<section class="card" id="import-preview"><h2>3. Pré-visualização da importação</h2>
@if(!$preview['ok'])
<div class="alert error"><p>O relatório ainda não pode ser importado sem inventar dados.</p><p>Campos em falta ou ambíguos: {{ implode(', ', $preview['missing_fields']) }}.</p></div>
@foreach($preview['warnings'] ?? [] as $warning)<p class="muted-text">{{ $warning }}</p>@endforeach
@if(!empty($preview['normalization_prompt']))
<p>Usa esta prompt no ChatGPT para converter o relatório para o formato reconhecido pelo StudyOS:</p>
<label class="field"><span>Prompt de normalização</span><textarea id="normalization-prompt" rows="14" readonly>{{ $preview['normalization_prompt'] }}</textarea></label>
<button class="button ghost" type="button" id="copy-normalization-prompt">Copiar prompt de normalização</button>
@endif
@else
@php($rep=$preview['report'])
@php($statusLabels=['no_evidence'=>'Sem evidência','insufficient_evidence'=>'Evidência insuficiente','fragile'=>'Frágil','developing'=>'Em desenvolvimento','competent'=>'Competente','strong'=>'Forte'])
<div class="learning-stats">
<div class="learning-stat"><span>UC</span><strong>{{ $rep['academic_code'] }}</strong><small>{{ $course->name }}</small></div>
<div class="learning-stat"><span>Data</span><strong>{{ $rep['assessed_on'] }}</strong><small>{{ $rep['activity_type'] }}</small></div>
<div class="learning-stat"><span>Classificação global</span><strong>{{ $rep['score_20'] === null ? '—' : number_format((float)$rep['score_20'],2,',',' ').'/20' }}</strong><small>{{ $rep['score_20'] === null ? 'sem classificação global observada' : 'resultado observado' }}</small></div>
<div class="learning-stat"><span>Evidência</span><strong>{{ $rep['evidence_count'] }}</strong><small>unidade(s) declaradas</small></div>
</div>
@foreach($preview['warnings'] ?? [] as $warning)<p class="muted-text">{{ $warning }}</p>@endforeach
<div style="margin-top:16px">
@foreach($rep['topics'] as $topic)
<article style="border-bottom:1px solid #33424a;padding:12px 0">
<strong>{{ $topic['number'] }} · {{ $topic['title'] }}</strong>
<p>{{ $topic['score_percent'] === null ? 'Sem percentagem observada' : number_format((float)$topic['score_percent'],0).'% observado' }} · {{ $topic['evidence_count'] }} evidência(s)</p>
<p class="muted-text">{{ $topic['feedback'] }}</p>
<p>Estado proposto após integrar o histórico: <strong>{{ $statusLabels[$topic['proposed_status']] ?? $topic['proposed_status'] }}</strong>@if($topic['proposed_score_percent'] !== null) · estimativa agregada {{ number_format((float)$topic['proposed_score_percent'],1,',',' ') }}%@endif · {{ $topic['proposed_evidence_units'] }} unidade(s) de evidência.</p>
</article>
@endforeach
</div>
@if(!empty($rep['strengths']))<p><strong>Pontos fortes:</strong> {{ implode(' · ', $rep['strengths']) }}</p>@endif
@if(!empty($rep['difficulties']))<p><strong>Dificuldades:</strong> {{ implode(' · ', $rep['difficulties']) }}</p>@endif
@if(!empty($rep['conceptual_errors']))<p><strong>Erros conceptuais:</strong> {{ implode(' · ', $rep['conceptual_errors']) }}</p>@endif
@if(!empty($rep['recommendations']))<p><strong>Recomendações:</strong> {{ implode(' · ', $rep['recommendations']) }}</p>@endif
<form method="POST" action="{{ route('practice.external.confirm',$course,false) }}">@csrf
<textarea name="normalized_report" style="display:none">{{ json_encode($rep, JSON_UNESCAPED_UNICODE) }}</textarea>
<div class="form-actions"><button class="button primary" type="submit">Confirmar e importar</button></div>
</form>
<p class="muted-text">A importação guarda o histórico e recalcula o domínio. A matéria lecionada, a matéria estudada e as notas oficiais permanecem dimensões independentes.</p>
@endif
</section>
@endif

<section class="card"><h2>Histórico de avaliações externas</h2>
@if($history->isEmpty())<p>Ainda não existem avaliações importadas.</p>@else
@foreach($history as $item)
@php($rep=json_decode($item->report,true))
<article style="border-bottom:1px solid #33424a;padding:12px 0"><strong>{{ $item->assessed_on }} · {{ is_null($item->score_20) ? 'sem nota global' : number_format((float)$item->score_20,2,',',' ').'/20' }}</strong>
<p class="muted-text">{{ count($rep['topics'] ?? []) }} subtema(s) · {{ $item->external_id }} · {{ $rep['activity_type'] ?? 'assessment' }}</p>
@foreach(($rep['topics'] ?? []) as $topic)
<div>{{ $topic['number'] ?? '?' }} — {{ array_key_exists('score_percent',$topic) && $topic['score_percent'] !== null ? number_format((float)$topic['score_percent'],0).'%' : 'sem evidência quantificada' }} · {{ $topic['feedback'] ?? '' }}</div>
@endforeach
</article>
@endforeach
@endif
</section>
</div><script src="/js/external-assessment-prompt.js?v=3" defer></script></body></html>
