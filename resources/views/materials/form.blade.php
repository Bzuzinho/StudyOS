<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $material->exists ? 'Editar material' : 'Novo material' }} · StudyOS</title>
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
            <a class="active" href="/materials">Materiais</a>
            <a href="/study">Estudo</a>
            <a href="/practice">Prática</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="{{ route('materials.index') }}">← Materiais</a></div>

    <header class="form-header">
        <p class="eyebrow">Conteúdo académico</p>
        <h1>{{ $material->exists ? 'Editar material' : 'Adicionar material' }}</h1>
        <p>Carrega o documento académico diretamente. O StudyOS guarda-o em armazenamento privado, extrai o texto e preserva página/slide sempre que o formato o permite.</p>
    </header>

    @if($errors->any())
        <div class="alert error">
            <strong>Há campos a corrigir.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="event-form card" method="POST" enctype="multipart/form-data" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}">
        @csrf
        @if($material->exists) @method('PUT') @endif

        <div class="form-grid">
            <label class="field span-2">
                <span>Unidade curricular</span>
                <select name="course_id" required>
                    <option value="">Selecionar UC…</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('course_id', $material->course_id) === (string) $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Tipo</span>
                <select name="type" required>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $material->type ?: 'document') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Título</span>
                <input type="text" name="title" maxlength="255" required value="{{ old('title', $material->title) }}">
            </label>

            <label class="field span-2 upload-field">
                <span>Ficheiro académico <small>PDF, PPTX, DOCX, TXT ou MD · máximo 25 MB</small></span>
                <input type="file" name="file" accept=".pdf,.pptx,.docx,.txt,.md">
                <small class="field-hint">O ficheiro fica privado no armazenamento cloud do StudyOS. PDFs mantêm referência de página e apresentações PPTX mantêm referência de slide.</small>
                @if($latestVersion?->storage_path)
                    <div class="current-file">
                        <div>
                            <strong>{{ $latestVersion->original_filename }}</strong>
                            <span>
                                {{ $latestVersion->size_bytes ? number_format($latestVersion->size_bytes / 1024 / 1024, 2).' MB' : 'tamanho não disponível' }}
                                · {{ $latestVersion->extraction_status }}
                            </span>
                        </div>
                        <a href="{{ route('materials.download', ['material' => $material, 'version' => $latestVersion]) }}">Descarregar atual</a>
                    </div>
                @endif
            </label>

            <label class="field span-2">
                <span>Ligação <small>opcional</small></span>
                <input type="url" name="url" maxlength="2000" value="{{ old('url', $material->url) }}" placeholder="https://…">
            </label>

            <label class="field span-2">
                <span>Notas <small>opcional</small></span>
                <textarea name="notes" rows="5" maxlength="4000">{{ old('notes', $material->metadata['notes'] ?? '') }}</textarea>
            </label>

            <label class="field span-2">
                <span>Texto adicional / conteúdo colado <small>opcional · até 200 000 caracteres</small></span>
                <textarea name="content_text" rows="10" maxlength="200000" placeholder="Usa este campo para apontamentos ou quando não tens um ficheiro para carregar…">{{ old('content_text', $contentText) }}</textarea>
                <small class="field-hint">
                    @if($latestVersion?->storage_path)
                        O texto extraído do ficheiro atual não é repetido aqui. Se escreveres neste campo, será acrescentado como uma nova secção com proveniência própria.
                    @else
                        Este texto é versionado. Meros títulos ou esquemas continuam excluídos da geração automática até existir detalhe suficiente.
                    @endif
                </small>
            </label>
        </div>

        <div class="form-actions">
            <a class="button ghost" href="{{ route('materials.index') }}">Cancelar</a>
            <button class="button primary" type="submit">{{ $material->exists ? 'Guardar alterações' : 'Adicionar material' }}</button>
        </div>
    </form>

    @if($material->exists)
        <form method="POST" action="{{ route('materials.destroy', $material) }}" class="danger-zone" onsubmit="return confirm('Eliminar este material?')">
            @csrf
            @method('DELETE')
            <button class="button danger" type="submit">Eliminar material</button>
        </form>
    @endif
</div>
</body>
</html>
