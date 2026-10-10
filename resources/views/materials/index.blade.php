<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Materiais · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=material-access-1">
</head>
<body>
<div class="shell">
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

    @if(session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    <header class="calendar-header">
        <div>
            <p class="eyebrow">Conteúdo académico</p>
            <h1>Materiais</h1>
        </div>
        <div><a class="button" href="{{ route('moodle.sync', [], false) }}">Sincronizar Moodle</a>
        <a class="button primary" href="{{ route('materials.create', [], false) }}">+ Adicionar material</a></div>
    </header>

    <form class="material-filters" method="GET" action="{{ route('materials.index', [], false) }}">
        <label class="field"><span>UC</span><select name="course_id">
            <option value="">Todas as UCs</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" @selected(($filters['course_id'] ?? '') == $course->id)>{{ $course->name }}</option>
            @endforeach
        </select></label>
        <label class="field"><span>Origem</span><select name="source">
            <option value="">Todas as origens</option>
            <option value="moodle" @selected(($filters['source'] ?? '') === 'moodle')>Moodle</option>
            <option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option>
            <option value="moodle_audit" @selected(($filters['source'] ?? '') === 'moodle_audit')>Fonte auditada</option>
        </select></label>
        <label class="field"><span>Pesquisar</span><input type="search" name="q" maxlength="200" value="{{ $filters['q'] ?? '' }}" placeholder="Título ou nome do ficheiro"></label>
        <button class="button primary" type="submit">Filtrar</button>
        <a class="button" href="{{ route('materials.index', [], false) }}">Limpar</a>
    </form>
    <p class="muted-text">{{ $materials->total() }} material(is) · Os ficheiros importados podem ser descarregados mesmo quando a extração de texto falha.</p>

    <section class="material-grid">
        @forelse($materials as $material)
            @php
                $notes = $material->metadata['notes'] ?? null;
                $latestVersion = $material->versions->first();
                $activeChunks = $latestVersion?->active_chunks_count ?? 0;
                $richChunks = $latestVersion?->rich_chunks_count ?? 0;
                $extractionLabels = [
                    'queued' => 'Na fila de extração',
                    'processing' => 'A extrair no servidor',
                    'extracted' => 'Texto extraído',
                    'empty_or_scanned' => 'PDF sem texto pesquisável',
                    'empty' => 'Sem texto extraível',
                    'failed' => 'Extração falhou',
                    'manual_text' => 'Texto colado',
                    'not_applicable' => 'Sem extração',
                ];
                $extractionLabel = $latestVersion ? ($extractionLabels[$latestVersion->extraction_status] ?? $latestVersion->extraction_status) : null;
            @endphp
            <article class="material-card">
                <div class="material-card-head">
                    <span class="material-type">{{ strtoupper($material->type) }}</span>
                    <span class="source-pill {{ $material->source === 'manual' ? 'manual' : '' }}">
                        {{ $material->source === 'manual' ? 'Manual' : ($material->source === 'moodle' ? 'Moodle' : 'Fonte auditada') }}
                    </span>
                </div>
                <h2><a href="{{ $latestVersion?->storage_path ? route('materials.download', ['material' => $material, 'version' => $latestVersion], false) : ($material->url ?: '#') }}">{{ $material->title }}</a></h2>
                <a class="material-course" href="{{ route('courses.show', $material->course, false) }}">{{ $material->course?->name }}</a>

                @if($notes)
                    <p>{{ $notes }}</p>
                @elseif(($material->metadata['change_observed'] ?? null))
                    <p>{{ $material->metadata['change_observed'] }}</p>
                @elseif(($material->metadata['observed_items'] ?? null))
                    <p>{{ $material->metadata['observed_items'] }} ficheiros {{ $material->metadata['observed_format'] ?? '' }} observados.</p>
                @endif

                @if($latestVersion?->extraction_status === 'queued')
                    <p class="extraction-pending">Ficheiro guardado. A extração está na fila e continua mesmo que feches esta página.</p>
                @elseif($latestVersion?->extraction_status === 'processing')
                    <p class="extraction-pending">O servidor está a extrair e indexar este documento.</p>
                @elseif($latestVersion?->extraction_status === 'empty_or_scanned')
                    <p class="extraction-warning">Este PDF parece não ter texto pesquisável. O ficheiro foi guardado, mas não entra na geração de exercícios.</p>
                @elseif($latestVersion?->extraction_status === 'failed')
                    <p class="extraction-warning">O ficheiro foi guardado, mas a extração automática falhou. O StudyOS não usará conteúdo não extraído como fonte.</p>
                @endif

                <div class="material-meta">
                    @if($latestVersion)
                        <span>Versão: {{ $latestVersion->version_label ?: 'observada' }}</span>
                    @endif
                    @if($latestVersion?->storage_path)
                        <a href="{{ route('materials.download', ['material' => $material, 'version' => $latestVersion], false) }}">Descarregar ficheiro · {{ $latestVersion->original_filename }}</a>
                        <span>{{ $extractionLabel }}</span>
                    @elseif($extractionLabel)
                        <span>{{ $extractionLabel }}</span>
                    @endif
                    @if($activeChunks > 0)
                        <span>{{ $activeChunks }} fragmento(s) · {{ $richChunks }} apto(s) para prática</span>
                    @elseif($latestVersion?->content_text)
                        <span>Conteúdo por indexar</span>
                    @endif
                    @if($material->url)
                        <a href="{{ $material->url }}" target="_blank" rel="noopener noreferrer">{{ $material->source === 'moodle' ? 'Ver no Moodle' : 'Abrir origem' }} ↗</a>
                    @endif
                    @if($latestVersion?->storage_path && in_array($latestVersion->extraction_status, ['failed', 'empty', 'empty_or_scanned'], true))
                        <form method="POST" action="{{ route('materials.reprocess', ['material' => $material, 'version' => $latestVersion], false) }}">
                            @csrf
                            <button class="text-link" type="submit">Tentar extrair novamente</button>
                        </form>
                    @endif
                    @if($material->source === 'manual')
                        <a href="{{ route('materials.edit', $material, false) }}">Editar</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="card wide"><p class="empty">Não foram encontrados materiais com estes filtros.</p></div>
        @endforelse
    </section>
    @if($materials->hasPages())
        <nav class="material-pagination" aria-label="Páginas de materiais">
            @if($materials->onFirstPage())<span>Anterior</span>@else<a class="button" href="{{ $materials->previousPageUrl() }}">Anterior</a>@endif
            <span>Página {{ $materials->currentPage() }} de {{ $materials->lastPage() }}</span>
            @if($materials->hasMorePages())<a class="button" href="{{ $materials->nextPageUrl() }}">Seguinte</a>@else<span>Seguinte</span>@endif
        </nav>
    @endif
</div>
</body>
</html>
