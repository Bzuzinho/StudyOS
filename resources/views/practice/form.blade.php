<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $exercise->exists ? 'Editar exercício' : 'Novo exercício' }} · StudyOS</title>
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
            <a href="/study">Estudo</a>
            <a class="active" href="/practice">Prática</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="{{ route('practice.index') }}">← Prática</a></div>

    <header class="form-header">
        <p class="eyebrow">Banco de exercícios</p>
        <h1>{{ $exercise->exists ? 'Editar exercício' : 'Criar exercício' }}</h1>
        <p>Exercícios manuais ficam identificados como tal. A geração automática a partir de materiais validados será adicionada separadamente.</p>
    </header>

    @if($errors->any())
        <div class="alert error">
            <strong>Há campos a corrigir.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $config = $exercise->answer_config ?? [];
        $acceptedAnswers = implode("\n", $config['accepted_answers'] ?? []);
        $oldTopics = old('topic_ids', $exercise->exists ? $exercise->topics->pluck('id')->all() : ($selectedTopicId ? [$selectedTopicId] : []));
    @endphp

    <form class="event-form card" method="POST" action="{{ $exercise->exists ? route('practice.update', $exercise) : route('practice.store') }}">
        @csrf
        @if($exercise->exists) @method('PUT') @endif

        <div class="form-grid">
            <label class="field span-2">
                <span>Unidade curricular</span>
                <select name="course_id" id="practice-course" required>
                    <option value="">Selecionar UC…</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('course_id', $exercise->course_id ?: $selectedCourseId) === (string) $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </label>

            <fieldset class="field span-2 topic-selector">
                <legend>Tópicos <small>pelo menos um</small></legend>
                @foreach($courses as $course)
                    @if($course->topics->isNotEmpty())
                        <div class="topic-options" data-course="{{ $course->id }}">
                            @foreach($course->topics as $topic)
                                <label>
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked(in_array($topic->id, $oldTopics))>
                                    <span>{{ $topic->title }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </fieldset>

            <label class="field">
                <span>Tipo</span>
                <select name="type" id="exercise-type" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $exercise->type ?: 'single_answer') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Cotação</span>
                <input type="number" name="max_points" min="0.1" max="100" step="0.1" required value="{{ old('max_points', $exercise->max_points ?: 1) }}">
            </label>

            <label class="field span-2">
                <span>Título <small>opcional</small></span>
                <input type="text" name="title" maxlength="255" value="{{ old('title', $exercise->title) }}">
            </label>

            <label class="field span-2">
                <span>Enunciado</span>
                <textarea name="prompt" rows="6" maxlength="10000" required>{{ old('prompt', $exercise->prompt) }}</textarea>
            </label>

            <label class="field span-2">
                <span>Resposta correta / referência <small>obrigatória para correção automática</small></span>
                <textarea name="expected_answer" rows="3" maxlength="10000">{{ old('expected_answer', $exercise->expected_answer) }}</textarea>
            </label>

            <label class="field span-2" data-answer-field="accepted">
                <span>Outras respostas aceites <small>uma por linha</small></span>
                <textarea name="accepted_answers" rows="3" maxlength="10000">{{ old('accepted_answers', $acceptedAnswers) }}</textarea>
            </label>

            <label class="field span-2" data-answer-field="tolerance">
                <span>Tolerância numérica</span>
                <input type="number" name="numeric_tolerance" min="0" max="1000000" step="0.0001" value="{{ old('numeric_tolerance', $config['tolerance'] ?? 0) }}">
            </label>

            <label class="field span-2">
                <span>Explicação / resolução <small>opcional, mostrada depois da tentativa</small></span>
                <textarea name="explanation" rows="5" maxlength="10000">{{ old('explanation', $exercise->explanation) }}</textarea>
            </label>
        </div>

        <div class="form-actions">
            <a class="button ghost" href="{{ $exercise->exists ? route('practice.show', $exercise) : route('practice.index') }}">Cancelar</a>
            <button class="button primary" type="submit">{{ $exercise->exists ? 'Guardar alterações' : 'Criar exercício' }}</button>
        </div>
    </form>

    @if($exercise->exists)
        <form method="POST" action="{{ route('practice.destroy', $exercise) }}" class="danger-zone" onsubmit="return confirm('Eliminar este exercício e as suas tentativas?')">
            @csrf
            @method('DELETE')
            <button class="button danger" type="submit">Eliminar exercício</button>
        </form>
    @endif
</div>

<script>
(() => {
    const course = document.getElementById('practice-course');
    const type = document.getElementById('exercise-type');
    const topicGroups = [...document.querySelectorAll('.topic-options')];
    const accepted = document.querySelector('[data-answer-field="accepted"]');
    const tolerance = document.querySelector('[data-answer-field="tolerance"]');

    const refreshTopics = () => {
        topicGroups.forEach(group => {
            const visible = group.dataset.course === course.value;
            group.hidden = !visible;
            if (!visible) {
                group.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = false);
            }
        });
    };

    const refreshAnswerFields = () => {
        accepted.hidden = !['single_answer'].includes(type.value);
        tolerance.hidden = type.value !== 'numeric';
    };

    course.addEventListener('change', refreshTopics);
    type.addEventListener('change', refreshAnswerFields);
    refreshTopics();
    refreshAnswerFields();
})();
</script>
</body>
</html>
