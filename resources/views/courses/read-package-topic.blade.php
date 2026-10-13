<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $topic->title }} · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=learning-20261013"></head><body class="learning-page"><div class="shell narrow-shell">
@include('partials.learning-nav',['active'=>'/study'])
<div class="breadcrumb"><a href="{{ route('courses.show',$course,false) }}#package-curriculum">← {{ $course->name }}</a></div>
<header class="form-header"><p class="eyebrow">Manual de estudo</p><h1>{{ $topic->title }}</h1><p>Material pedagógico complementar preparado no ChatGPT.</p></header>
<article class="card study-manual">{!! \Illuminate\Support\Str::markdown($content,['html_input'=>'strip','allow_unsafe_links'=>false]) !!}</article>
<div class="form-actions"><a class="button" href="{{ route('study.create',['course_id'=>$course->id,'topic_id'=>$topic->id,'now'=>1],false) }}">Estudar este subtema</a><a class="button primary" href="{{ route('practice.runs.create',['course_id'=>$course->id,'topic_id'=>$topic->id],false) }}">Treinar este subtema</a></div>
</div></body></html>