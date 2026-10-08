<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $topic->exists ? 'Editar tópico' : 'Novo tópico' }} · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=course-progress-1">
</head>
<body>
<div class="shell narrow-shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links"><a href="/">Dashboard</a><a href="/calendar">Calendário</a><a class="active" href="/courses">UCs</a><a href="/activities">Atividades</a><a href="/materials">Materiais</a><a href="/study">Estudo</a><a href="/practice">Prática</a></div>
    </nav>
    <div class="breadcrumb"><a href="{{ route('courses.show', $course, false) }}#course-topics">← {{ $course->name }}</a></div>
    <header class="form-header"><p class="eyebrow">Matéria da UC</p><h1>{{ $topic->exists ? 'Editar tópico' : 'Novo tópico' }}</h1><p>{{ $course->name }}</p></header>
    @if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="card event-form" method="POST" action="{{ $topic->exists ? route('course-topics.update', ['course' => $course, 'topic' => $topic], false) : route('course-topics.store', $course, false) }}">
        @csrf
        @if($topic->exists)@method('PUT')@endif
        <div class="form-grid">
            <label class="field span-2"><span>Título</span><input name="title" required maxlength="255" value="{{ old('title', $topic->title) }}" placeholder="Ex.: Estrutura e funções da gestão"></label>
            <label class="field span-2"><span>Descrição <small>opcional</small></span><textarea name="description" rows="4" maxlength="4000">{{ old('description', $topic->description) }}</textarea></label>
            <input type="hidden" name="is_taught" value="0">
            <label class="taught-topic-option"><input type="checkbox" name="is_taught" value="1" @checked(old('is_taught', $topic->taught_at !== null))><span>Este tópico já foi lecionado</span></label>
        </div>
        <div class="form-actions"><a class="button" href="{{ route('courses.show', $course, false) }}#course-topics">Cancelar</a><button class="button primary" type="submit">Guardar tópico</button></div>
    </form>
    @if($topic->exists)
        <form class="danger-zone" method="POST" action="{{ route('course-topics.destroy', ['course' => $course, 'topic' => $topic], false) }}">
            @csrf
            @method('DELETE')
            <button class="button danger" type="submit">Retirar tópico da lista</button>
        </form>
    @endif
</div>
</body>
</html>
