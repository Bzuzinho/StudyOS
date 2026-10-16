<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Treino em curso · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"><link rel="stylesheet" href="/css/ink-scratchpad.css?v=1"></head><body class="learning-page"><div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/practice'])
<header class="form-header"><p class="eyebrow">Treino em curso · Pergunta {{ $position }} de {{ $total }}</p><h1>Uma pergunta de cada vez</h1><p>As respostas submetidas ficam guardadas. Podes sair e retomar este treino na área Prática.</p></header>
<div class="progress-track" role="progressbar" aria-label="Perguntas respondidas" aria-valuenow="{{ $position-1 }}" aria-valuemin="0" aria-valuemax="{{ $total }}"><div class="progress-fill" style="width:{{ round(($position-1)/$total*100) }}%"></div></div>
@if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="card"><p class="question-prompt">{{ $exercise->prompt }}</p>
<form method="POST" action="{{ route('practice.runs.answer',$run->id,false) }}" class="event-form">@csrf<input type="hidden" name="question_id" value="{{ $exercise->id }}">
@if($exercise->type==='true_false')<label class="field"><span>Resposta</span><select name="answer" aria-label="Resposta" required><option value="">Selecionar…</option><option value="verdadeiro" @selected(old('answer')==='verdadeiro')>Verdadeiro</option><option value="falso" @selected(old('answer')==='falso')>Falso</option></select></label>
@elseif($exercise->type==='open_text')<label class="field"><span>A tua resposta</span><textarea name="answer" rows="6" maxlength="10000" required>{{ old('answer') }}</textarea></label>
@else<label class="field"><span>A tua resposta</span><input name="answer" type="text" @if($exercise->type==='numeric') inputmode="decimal" @endif maxlength="10000" required autocomplete="off" value="{{ old('answer') }}"></label>@endif
<section class="ink-pad" data-ink-pad data-review-url="{{ route('practice.runs.review-ink', $run->id) }}" data-recognize-url="{{ route('practice.runs.transcribe-ink', $run->id) }}" data-question-id="{{ $exercise->id }}" data-ink-answer-type="{{ $exercise->type }}" data-storage-key="studyos-ink-run-{{ $run->id }}-{{ $exercise->id }}" aria-label="Caderno de resolução manuscrita">
  <div class="ink-toolbar">
    <strong>Rascunho com caneta</strong>
    <div class="ink-tools"><button type="button" class="button primary" data-ink-recognize>Reconhecer escrita</button><button type="button" class="button ghost" data-ink-undo>Desfazer</button><button type="button" class="button ghost" data-ink-clear>Apagar</button><button type="button" class="button" data-ink-expand aria-expanded="false">Ampliar folha</button><button type="button" class="button primary" data-ink-close aria-expanded="true" hidden>Voltar ao exercício</button></div>
  </div>
  <canvas class="ink-canvas" aria-label="Área branca para escrever cálculos com a caneta" role="img"></canvas>
  <p class="muted-text" data-ink-status>Rascunho guardado neste dispositivo; o reconhecimento requer ligação à IA.</p>
  <div class="ink-recognition" data-ink-review hidden>
    <label class="field"><span>Resolução reconhecida — confirma e corrige eventuais símbolos trocados</span><textarea rows="5" maxlength="9000" data-ink-transcription aria-label="Transcrição da escrita manuscrita"></textarea></label>
    <p class="muted-text" data-ink-notes></p>
    <button type="button" class="button primary" data-ink-evaluate>Analisar a resolução</button> <button type="button" class="button ghost" data-ink-use>Usar esta transcrição na resposta</button><p class="muted-text" data-ink-feedback role="status"></p>
    <p class="muted-text">O reconhecimento não é uma correção. Questões numéricas são avaliadas pelo valor final; passos manuscritos necessitam de avaliação própria, ainda provisória.</p>
  </div>
</section>
<div class="form-actions"><a class="button ghost" href="/practice">Sair e retomar depois</a><button class="button primary" type="submit">{{ $position===$total ? 'Terminar e ver resultados' : 'Responder e avançar' }}</button></div></form></section></div>
<script>(()=>{const form=document.querySelector('form');const input=form.querySelector('[name="answer"]');const key='studyos-draft-{{ $run->id }}-{{ $exercise->id }}';try{if(!input.value)input.value=sessionStorage.getItem(key)||'';input.addEventListener('input',()=>sessionStorage.setItem(key,input.value));form.addEventListener('submit',()=>{form.querySelector('button[type="submit"]').disabled=true;});}catch(e){}})();</script><script src="/js/ink-scratchpad.js?v=1" defer></script></body></html>
