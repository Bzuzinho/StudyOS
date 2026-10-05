<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planear estudo · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head>
<body>
<div class="shell narrow-shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a href="/calendar">Calendário</a>
            <a href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
            <a class="active" href="/study">Estudo</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="{{ route('study.index') }}">← Estudo</a></div>

    <header class="form-header">
        <p class="eyebrow">Planeamento</p>
        <h1>Planear sessão de estudo</h1>
        <p>A sessão aparece no calendário, mas só fica registada como planeamento. O StudyOS não assume que foi realizada apenas porque a hora passou.</p>
    </header>

    @if($errors->any())
        <div class="alert error">
            <strong>Há campos a corrigir.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="event-form card" method="POST" action="{{ route('study.store') }}">
        @csrf

        <div class="form-grid">
            <label class="field span-2">
                <span>Unidade curricular</span>
                <select name="course_id" id="study-course" required>
                    <option value="">Selecionar UC…</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('course_id', $selectedCourseId) === (string) $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Tipo</span>
                <select name="type" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', 'study') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Título <small>opcional</small></span>
                <input name="title" type="text" maxlength="255" value="{{ old('title') }}" placeholder="Ex.: Revisão para P.E. 1">
            </label>

            <label class="field">
                <span>Data</span>
                <input name="date" type="date" required value="{{ old('date', $selectedDate) }}">
            </label>

            <label class="field">
                <span>Início</span>
                <input name="start_time" type="time" required value="{{ old('start_time') }}">
            </label>

            <label class="field span-2">
                <span>Duração planeada</span>
                <select name="planned_minutes" required>
                    @foreach([30,45,60,90,120,150,180] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('planned_minutes', 60) === $minutes)>{{ $minutes }} minutos</option>
                    @endforeach
                </select>
            </label>

            <fieldset class="field span-2 topic-selector">
                <legend>Tópicos <small>opcional</small></legend>
                @foreach($courses as $course)
                    @if($course->topics->isNotEmpty())
                        <div class="topic-options" data-course="{{ $course->id }}">
                            <strong>{{ $course->name }}</strong>
                            @foreach($course->topics as $topic)
                                <label>
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked(in_array($topic->id, old('topic_ids', [])))>
                                    <span>{{ $topic->title }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                <p class="field-hint">Só são apresentados tópicos já suportados pelas fontes académicas recolhidas.</p>
            </fieldset>

            <label class="field span-2">
                <span>Notas <small>opcional</small></span>
                <textarea name="notes" rows="4" maxlength="4000" placeholder="Objetivo da sessão, exercícios a resolver, material a rever…">{{ old('notes') }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <a class="button ghost" href="{{ route('study.index') }}">Cancelar</a>
            <button class="button primary" type="submit">Planear sessão</button>
        </div>
    </form>
</div>

<script>
(() => {
    const course = document.getElementById('study-course');
    const groups = [...document.querySelectorAll('.topic-options')];

    const refresh = () => {
        groups.forEach(group => {
            const visible = group.dataset.course === course.value;
            group.hidden = !visible;
            if (!visible) {
                group.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = false);
            }
        });
    };

    course.addEventListener('change', refresh);
    refresh();
})();
</script>
</body>
</html>
