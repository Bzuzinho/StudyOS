<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $topic->title }} · StudyOS</title>
@include('partials.app-identity')
<link rel="stylesheet" href="/css/app.css?v=learning-20261013">
<link rel="stylesheet" href="/css/pedagogical-reader.css?v=20261015">
<link rel="stylesheet" href="/css/study-mindmaps.css?v=1">
</head>
<body class="learning-page">
<div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/study'])
<div class="breadcrumb"><a href="{{ $backUrl }}">← {{ $backLabel }}</a></div>
@php($isReview = request()->query('mode') === 'review')
<header class="form-header"><p class="eyebrow">Biblioteca pedagógica · {{ $topic->metadata['curricular_number'] ?? 'Subtema' }}</p><h1>{{ $topic->title }}</h1><p>{{ $isReview ? "Síntese autónoma dos conceitos essenciais." : "Desenvolvimento teórico e exemplos para aprender este subtema." }}</p></header>
@php($isReview = request()->query('mode') === 'review')

@if($isReview)
    @if(count($reviewSections))
    <div class="reader-stack reader-focused">
    @foreach($reviewSections as $section)
    <section class="reader-section type-{{ $section['kind'] }}">
    <div class="reader-section-heading"><span class="reader-kicker">Revisão rápida</span><h2>{{ $section['title'] ?: 'Conceitos essenciais' }}</h2></div>
    <div class="reader-prose">{!! \Illuminate\Support\Str::markdown($section['body'],['html_input'=>'strip','allow_unsafe_links'=>false]) !!}</div>
@if(!empty($section['rows']))<div class="reader-table-wrap"><table><tbody>@foreach($section['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
@if(!empty($section['steps']))@if($section['kind'] === 'diagram')<div class="study-mindmap" role="group" aria-label="{{ $section['title'] }}"><div class="study-mindmap-core">{{ $section['title'] }}</div><ul class="study-mindmap-branches">@foreach($section['steps'] as $step)<li class="study-mindmap-branch">{{ $step }}</li>@endforeach</ul></div>@else<ol class="reader-flow">@foreach($section['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>@endif@endif
    </section>
    @endforeach
    </div>
    @else
    <section class="reader-section type-warning"><h2>Revisão ainda não disponível</h2><p>Este pacote não contém uma síntese autónoma. Podes consultar a matéria integral ou treinar os conhecimentos; não apresentamos a teoria completa como se fosse uma revisão rápida.</p></section>
    @endif
@else
    <div class="reader-stack">
    @foreach($sections as $section)
    <section class="reader-section type-{{ $section['kind'] }}">
    @if($section['title'] !== '')
    <div class="reader-section-heading">
    <span class="reader-kicker">{{ ['objectives'=>'O que vais aprender','example'=>'Na prática','summary'=>'Em poucas palavras','warning'=>'Atenção','comparison'=>'Relacionar conceitos','concept'=>'Conceito','definition'=>'Definição','key_idea'=>'Ideia fundamental','explanation'=>'Compreender','table'=>'Quadro','diagram'=>'Diagrama','process'=>'Processo','case_study'=>'Caso aplicado','common_mistake'=>'Não confundir','formula'=>'Fórmula','timeline'=>'Cronologia','relationship'=>'Relações','memory_tip'=>'Memorizar','theory'=>'Compreender'][$section['kind']] }}</span>
    <h2>{{ $section['title'] }}</h2>
    </div>
    @endif
    <div class="reader-prose">{!! \Illuminate\Support\Str::markdown($section['body'],['html_input'=>'strip','allow_unsafe_links'=>false]) !!}</div>
@if(!empty($section['rows']))<div class="reader-table-wrap"><table><tbody>@foreach($section['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
@if(!empty($section['steps']))@if($section['kind'] === 'diagram')<div class="study-mindmap" role="group" aria-label="{{ $section['title'] }}"><div class="study-mindmap-core">{{ $section['title'] }}</div><ul class="study-mindmap-branches">@foreach($section['steps'] as $step)<li class="study-mindmap-branch">{{ $step }}</li>@endforeach</ul></div>@else<ol class="reader-flow">@foreach($section['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>@endif@endif
    </section>
    @endforeach
    </div>
@endif

<nav aria-label="Regressar à sessão anterior" class="reader-return">
<a class="button ghost" href="{{ $backUrl }}">← {{ $backLabel }}</a>
</nav>
</div></body></html>
