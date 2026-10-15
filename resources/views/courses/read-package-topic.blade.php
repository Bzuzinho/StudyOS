<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $topic->title }} · StudyOS</title>
@include('partials.app-identity')
<link rel="stylesheet" href="/css/app.css?v=learning-20261013">
<link rel="stylesheet" href="/css/pedagogical-reader.css?v=20261015">
</head>
<body class="learning-page">
<div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/study'])
<div class="breadcrumb"><a href="{{ route('courses.show',$course,false) }}#package-curriculum">← {{ $course->name }}</a></div>
<header class="form-header"><p class="eyebrow">Biblioteca pedagógica · {{ $topic->metadata['curricular_number'] ?? 'Subtema' }}</p><h1>{{ $topic->title }}</h1><p>Aprende os conceitos, consulta a síntese ou pratica com exercícios autónomos.</p></header>
@php($isReview = request()->query('mode') === 'review')
<nav class="reader-modes" aria-label="Modo de aprendizagem">
<a class="{{ !$isReview ? 'selected' : '' }}" @if(!$isReview) aria-current="page" @endif href="{{ route('courses.package-topic.read',['course'=>$course,'topic'=>$topic],false) }}">Estudar <small>Explicação completa</small></a>
<a class="{{ $isReview ? 'selected' : '' }}" @if($isReview) aria-current="page" @endif href="{{ route('courses.package-topic.read',['course'=>$course,'topic'=>$topic,'mode'=>'review'],false) }}">Rever <small>Síntese essencial</small></a>
<a href="{{ route('practice.runs.create',['course_id'=>$course->id,'topic_id'=>$topic->id],false) }}">Treinar <small>Questões e correção</small></a>
</nav>
@if($isReview)
    @if(count($reviewSections))
    <div class="reader-stack">
    @foreach($reviewSections as $section)
    <section class="reader-section type-{{ $section['kind'] }}">
    <div class="reader-section-heading"><span class="reader-kicker">Revisão rápida</span><h2>{{ $section['title'] ?: 'Conceitos essenciais' }}</h2></div>
    <div class="reader-prose">{!! \Illuminate\Support\Str::markdown($section['body'],['html_input'=>'strip','allow_unsafe_links'=>false]) !!}</div>
@if(!empty($section['rows']))<div class="reader-table-wrap"><table><tbody>@foreach($section['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
@if(!empty($section['steps']))<ol class="reader-flow">@foreach($section['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>@endif
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
@if(!empty($section['steps']))<ol class="reader-flow">@foreach($section['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>@endif
    </section>
    @endforeach
    </div>
@endif
<div class="form-actions"><a class="button ghost" href="{{ route('courses.package-topic.read',['course'=>$course,'topic'=>$topic,'mode'=>$isReview?'study':'review'],false) }}">{{ $isReview ? 'Estudar matéria completa' : 'Consultar revisão' }}</a><a class="button primary" href="{{ route('practice.runs.create',['course_id'=>$course->id,'topic_id'=>$topic->id],false) }}">Treinar este subtema</a></div>
</div></body></html>
