<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Resultados do treino · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=practice-run-1"></head><body><div class="shell narrow-shell">
<nav class="nav"><a class="brand" href="/"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a><div class="nav-links"><a href="/">Dashboard</a><a href="/courses">UCs</a><a href="/study">Estudo</a><a href="/practice" class="active">Prática</a></div></nav>
<header class="form-header"><p class="eyebrow">Sessão concluída</p><h1>Resultados e correção</h1><p>{{ $questions->count() }} perguntas respondidas · {{ $graded->count() }} corrigidas · {{ $pending }} a aguardar revisão.</p></header>
<section class="card">
@if($maximum>0)<h2>{{ number_format(($points/$maximum)*20,1,',','.') }} / 20 <small>nas perguntas já corrigidas</small></h2><p>{{ number_format($points/$maximum*100,1,',','.') }}% de aproveitamento nas perguntas com correção automática.</p>@endif
@if($pending>0)<p class="extraction-warning"><strong>Classificação global ainda não disponível.</strong> Há {{ $pending }} resposta(s) aberta(s) pendente(s) de correção. Não seriam fiáveis uma nota final ou um nível de domínio baseados nestas respostas sem avaliação.</p>@else<p>Todas as perguntas foram corrigidas. Revê abaixo os erros e as soluções.</p>@endif
</section>
@foreach($questions as $question)
@php $ex=$question['exercise'];$response=$question['response']??[];$isGraded=($response['grading_status']??'')==='graded';$correct=$isGraded && (float)($response['score']??0)>=(float)$ex->max_points; @endphp
<section class="card" style="margin-top:14px">
<h3>{{ $loop->iteration }}. {{ $ex->prompt }}</h3>
<p><strong>A tua resposta:</strong> {{ $response['answer']??'—' }}</p>
<p><strong>Estado:</strong> {{ $isGraded ? ($correct?'Correta':'Incorreta') : 'Aguarda revisão' }}</p>
@if(!$correct)<p><strong>Resposta de referência:</strong> {{ $ex->expected_answer ?: 'Não disponível' }}</p>@endif
@if($ex->explanation)<p><strong>Explicação:</strong> {{ $ex->explanation }}</p>@endif
@if(!empty($response['feedback']))<p>{{ $response['feedback'] }}</p>@endif
</section>
@endforeach
<div class="form-actions"><a class="button primary" href="{{ route('practice.runs.create',['course_id'=>$row->course_id]) }}">Novo treino</a><a class="button ghost" href="{{ route('study.index') }}">Estudo</a></div>
</div></body></html>