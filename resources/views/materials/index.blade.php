<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Materiais · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
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
        <a class="button primary" href="{{ route('materials.create') }}">+ Adicionar material</a>
    </header>

    <section class="material-grid">
        @forelse($materials as $material)
            @php
                $notes = $material->metadata['notes'] ?? null;
                $latestVersion = $material->versions->first();
                $activeChunks = $latestVersion?->sourceChunks?->where('status', 'active') ?? collect();
                $richChunks = $activeChunks->where('quality', 'content')->count();
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
                    <span class="source-pill {{ $material->source === 'manual' ? 'manual' : '' }}">{{ $material->source === 'manual' ? 'Manual' : 'Fonte auditada' }}</span>
                </div>
                <h2>{{ $material->title }}</h2>
                <a class="material-course" href="{{ route('courses.show', $material->course) }}">{{ $material->course?->name }}</a>

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
                        <a href="{{ route('materials.download', ['material' => $material, 'version' => $latestVersion]) }}">⬇ {{ $latestVersion->original_filename }}</a>
                        <span>{{ $extractionLabel }}</span>
                    @elseif($extractionLabel)
                        <span>{{ $extractionLabel }}</span>
                    @endif
                    @if($activeChunks->isNotEmpty())
                        <span>{{ $activeChunks->count() }} fragmento(s) · {{ $richChunks }} apto(s) para prática</span>
                    @elseif($latestVersion?->content_text)
                        <span>Conteúdo por indexar</span>
                    @endif
                    @if($material->url)
                        <a href="{{ $material->url }}" target="_blank" rel="noopener noreferrer">Abrir ↗</a>
                    @endif
                    @if($latestVersion?->storage_path && in_array($latestVersion->extraction_status, ['failed', 'empty', 'empty_or_scanned'], true))
                        <form method="POST" action="{{ route('materials.reprocess', ['material' => $material, 'version' => $latestVersion]) }}">
                            @csrf
                            <button class="text-link" type="submit">Tentar extrair novamente</button>
                        </form>
                    @endif
                    @if($material->source === 'manual')
                        <a href="{{ route('materials.edit', $material) }}">Editar</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="card wide"><p class="empty">Ainda não existem materiais registados.</p></div>
        @endforelse
    </section>
</div>
</body>
</html>
