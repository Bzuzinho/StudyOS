<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prática · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head><body class="learning-page"><div class="shell">
@include('partials.learning-nav',['active'=>'/practice'])
@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
<header class="learning-header"><div><p class="eyebrow">Avaliar e consolidar</p><h1>Prática</h1><p class="muted-text">Prepara avaliações no ChatGPT por UC, importa os resultados e acompanha a evolução da aprendizagem.</p></div><a class="button primary" href="#treinar-chatgpt">Treinar no ChatGPT</a></header>
<main class="learning-stack">
<section class="card" id="treinar-chatgpt"><div class="card-head"><h3>Treinar nos projetos ChatGPT</h3></div>
<p>Seleciona uma UC, gera uma prompt orientada para o seu programa e importa o relatório final de conhecimentos no StudyOS.</p>
<div class="learning-actions">
@foreach($courses as $course)
<a class="button ghost" href="{{ route('practice.external.index',$course,false) }}">{{ $course->name }}</a>
@endforeach
</div></section>
<section class="card"><div class="card-head"><h3>Avaliações importadas do ChatGPT</h3><span>Últimas avaliações pedagógicas</span></div>
@forelse($externalAssessments as $assessment)
<div class="learning-line">
<strong>{{ $courses->firstWhere('id',$assessment->course_id)?->name ?? 'UC' }}</strong>
<p>{{ $assessment->assessed_on }} · {{ number_format((float)$assessment->score_20,1,',','.') }}/20 · avaliação externa (ChatGPT)</p>
<a href="{{ route('practice.external.index',$assessment->course_id,false) }}">Ver evolução e diagnóstico →</a>
</div>
@empty<p class="empty">Ainda não importaste avaliações do ChatGPT.</p>@endforelse
</section>
@if($runs->where('status','completed')->isNotEmpty())
<details class="card review-details"><summary>Histórico de treinos antigos · {{ $runs->where('status','completed')->count() }}</summary>
<p class="muted-text">Estes resultados pertencem ao fluxo anterior do StudyOS. Mantêm-se apenas para consulta histórica; os novos treinos são realizados no projeto ChatGPT da respetiva UC.</p>
@foreach($runs->where('status','completed')->take(20) as $run)<div class="learning-line"><strong>{{ $courses->firstWhere('id',$run->course_id)?->name ?? 'UC' }}</strong><p>{{ \Illuminate\Support\Carbon::parse($run->created_at)->timezone(config('app.timezone'))->format('d/m/Y · H:i') }} · {{ $summaries[$run->id]['score'] !== null ? number_format($summaries[$run->id]['score'],1,',','.').' / 20' : 'Classificação pendente' }}</p><a href="{{ route('practice.runs.show',$run->id,false) }}">Consultar resultado antigo →</a></div>@endforeach
</details>
@endif
</main></div></body></html>
