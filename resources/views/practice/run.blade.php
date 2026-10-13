<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Treino em curso · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=practice-run-1"></head><body><div class="shell narrow-shell">
<nav class="nav"><a class="brand" href="/"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a><div class="nav-links"><a href="/">Dashboard</a><a href="/courses">UCs</a><a href="/study">Estudo</a><a href="/practice" class="active">Prática</a></div></nav>
<header class="form-header"><p class="eyebrow">Treino em curso · {{ $position }} de {{ $total }}</p><h1>{{ $exercise->title ?: 'Pergunta '.$position }}</h1><p>Responde antes de continuar. A correção e a resposta de referência serão reveladas apenas no final.</p></header>
<div style="height:8px;border-radius:12px;background:#23404b;margin:18px 0"><div style="height:100%;width:{{ round(($position-1)/$total*100) }}%;border-radius:12px;background:#b8a7ff"></div></div>
@if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="card"><p style="font-size:1.1rem;line-height:1.7">{{ $exercise->prompt }}</p>
<form method="POST" action="{{ route('practice.runs.answer',$run->id) }}" class="event-form">@csrf
@if($exercise->type==='true_false')
<label class="field"><span>Resposta</span><select name="answer" required><option value="">Selecionar...</option><option value="verdadeiro">Verdadeiro</option><option value="falso">Falso</option></select></label>
@elseif($exercise->type==='open_text')
<label class="field"><span>A tua resposta</span><textarea name="answer" rows="6" maxlength="10000" required></textarea></label>
@else
<label class="field"><span>A tua resposta</span><input name="answer" type="text" maxlength="10000" required autocomplete="off"></label>
@endif
<div class="form-actions"><button class="button primary" type="submit">{{ $position===$total ? 'Terminar e ver resultados' : 'Guardar e avançar' }}</button></div>
</form></section></div></body></html>