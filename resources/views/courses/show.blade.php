<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $course->name }} · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=course-progress-1">
</head>
<body>
<div class="shell">
    <nav class="nav">
        <a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
        <div class="nav-links">
            <a href="/">Dashboard</a>
            <a href="/calendar">Calendário</a>
            <a class="active" href="/courses">UCs</a>
            <a href="/activities">Atividades</a>
            <a href="/materials">Materiais</a>
            <a href="/study">Estudo</a>
            <a href="/practice">Prática</a>
        </div>
    </nav>

    <div class="breadcrumb"><a href="/courses">← UCs</a></div>
    @if(session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <header class="course-hero">
        <div>
            <div class="course-card-head">
                <span class="course-code">{{ $course->academic_code }}</span>
                <span class="status-pill {{ $course->status }}">{{ $course->status === 'active' ? 'Ativa' : 'Planeada' }}</span>
            </div>
            <h1>{{ $course->name }}</h1>
            <p>{{ $course->semester }}.º semestre @if($course->ects) · {{ $course->ects }} ECTS @endif</p>
        </div>
        <div class="course-hero-metrics">
            <div><strong>{{ $course->classOccurrences->count() }}</strong><span>aulas</span></div>
            <div><strong>{{ $course->assessments->count() }}</strong><span>avaliações</span></div>
            <div><strong>{{ $course->tasks->count() }}</strong><span>tarefas</span></div>
            <div><strong>{{ $course->materials->count() }}</strong><span>materiais</span></div>
            <div><strong>{{ $course->topics->count() }}</strong><span>tópicos</span></div>
            <div><strong>{{ $course->exercises->count() }}</strong><span>exercícios</span></div>
        </div>
    </header>

    <main class="grid">
        <section class="card wide">
            <div class="card-head"><h3>Progresso das aulas</h3><a href="/calendar">Calendário</a></div>
            @include('partials.course-class-progress')
            <p class="muted-text">Calculado pelas aulas do calendário disponível. Avaliações e aulas canceladas ficam excluídas. Uma aula com hora de fim só conta depois de terminar; sem hora de fim, é usada a hora de início.</p>
        </section>

        <section class="card wide" aria-label="Diagnóstico de conteúdos">
            <div class="card-head"><h3>Disponibilidade de conteúdos para estudar</h3><a href="{{ route('materials.index', ['course_id' => $course->id], false) }}">Ver materiais desta UC</a></div>
            <div class="course-meta">
                <span><strong>{{ $course->materials->count() }}</strong> materiais catalogados</span>
                <span><strong>{{ $course->downloaded_materials_count }}</strong> com ficheiro</span>
                <span><strong>{{ $course->extracted_materials_count }}</strong> com texto extraído</span>
                <span><strong>{{ $course->active_source_chunks_count }}</strong> excertos indexados</span>
                <span><strong>{{ $course->topics->count() }}</strong> tópicos definidos</span>
                @if($course->failed_materials_count > 0)<span><strong>{{ $course->failed_materials_count }}</strong> extrações falhadas</span>@endif
            </div>
            @if($course->topics->isEmpty())
                <p class="muted-text"><strong>Programa ainda não estruturado:</strong> esta UC não tem tópicos registados. Os dados sincronizados não foram convertidos automaticamente em matéria curricular. Podes acrescentar tópicos a partir do programa oficial, sem inventar conteúdos.</p>
                <a class="button primary" href="{{ route('course-topics.create', $course, false) }}">+ Estruturar matéria da UC</a>
            @elseif($course->active_source_chunks_count === 0)
                <p class="muted-text">Existem tópicos, mas não há excertos de fonte indexados. O StudyOS não deve gerar perguntas sobre conteúdo que ainda não conseguiu ler. Confirma os ficheiros no separador Materiais.</p>
            @endif
            <p class="muted-text">Um material catalogado não é necessariamente um ficheiro descarregado ou um texto utilizável. Estes valores são calculados a partir do estado persistido na aplicação.</p>
        </section>

        @if($missingStudySources->isNotEmpty())
            <section class="card wide" id="missing-study-content">
                <div class="card-head">
                    <h3>Precisamos de mais matéria para estudar</h3>
                    <a href="{{ route('materials.create', ['course_id' => $course->id], false) }}">+ Adicionar apontamentos ou documentos</a>
                </div>
                <p class="muted-text">Identifiquei {{ $missingStudySources->count() }} tópico(s) já lecionado(s) sem conteúdo explicativo detalhado associado. Os slides podem resumir os conceitos sem os desenvolver. Tens apontamentos, páginas de um manual, fichas de exercícios ou outros documentos destas matérias? Podes carregar um ficheiro ou colar texto, sem sair da UC.</p>
                <div class="topic-chips">
                    @foreach($missingStudySources->take(8) as $missingTopic)
                        <span>{{ $missingTopic->title }}</span>
                    @endforeach
                </div>
                @if($missingStudySources->count() > 8)
                    <p class="muted-text">E mais {{ $missingStudySources->count() - 8 }} tópico(s).</p>
                @endif
                <p class="muted-text">Este aviso significa que ainda não há texto detalhado ligado aos tópicos, não que os materiais importados estejam necessariamente vazios. Quando adicionares conteúdo, o StudyOS terá de o extrair e associar à matéria correta antes de poder considerá-lo apto para treino.</p>
                <a class="button primary" href="{{ route('materials.create', ['course_id' => $course->id], false) }}">Adicionar conteúdo desta UC</a>
            </section>
        @endif

        <section class="card wide" id="course-topics">
            <div class="card-head"><h3>Matéria lecionada e preparação</h3><a class="button" href="{{ route('course-topics.create', $course, false) }}">+ Acrescentar tópico</a></div>
            <p class="muted-text">{{ $course->topics->whereNotNull('taught_at')->count() }} de {{ $course->topics->count() }} tópicos registados marcados como lecionados. Seleciona a matéria já dada nas aulas e guarda a seleção.</p>
            @if($course->topics->isNotEmpty())
                <form id="taught-topics" method="POST" action="{{ route('course-topics.coverage', $course, false) }}">
                    @csrf
                    @method('PATCH')
                </form>
            @endif
            <div class="topic-grid">
                @forelse($course->topics as $topic)
                    <article class="topic-card">
                        <span class="topic-position">{{ str_pad((string) $topic->position, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>@if(($topic->metadata['curricular_number'] ?? null)){{ $topic->metadata['curricular_number'] }} · @endif{{ $topic->title }}</h3>
                        @if($topic->source === 'official_fuc')
                            <small>Programa oficial da UC · não implica matéria lecionada</small>
                        @elseif($topic->source === 'source_backed')
                            <small>Sumário académico · tópico provisório</small>
                        @endif
                        <input type="hidden" form="taught-topics" name="topic_ids[]" value="{{ $topic->id }}">
                        <label class="taught-topic-option">
                            <input type="checkbox" form="taught-topics" name="taught_topic_ids[]" value="{{ $topic->id }}" @checked(old('topic_ids') !== null ? in_array($topic->id, (array) old('taught_topic_ids', [])) : $topic->taught_at !== null)>
                            <span>Lecionado</span>
                        </label>
                        @if($topic->description)<p class="muted-text">{{ $topic->description }}</p>@endif
                        @php
                            $mastery = $topic->mastery;
                            $masteryStatus = $mastery?->status ?? 'no_evidence';
                            $masteryLabels = [
                                'no_evidence' => 'sem evidência',
                                'insufficient_evidence' => 'evidência insuficiente',
                                'fragile' => 'frágil',
                                'developing' => 'em desenvolvimento',
                                'competent' => 'competente',
                                'strong' => 'forte',
                            ];
                        @endphp
                        <div class="topic-states">
                            <span class="{{ $topic->taught_at ? 'source-ready' : 'unknown' }}">{{ $topic->taught_at ? 'Lecionado · assinalado' : 'Matéria · por assinalar' }}</span>
                            <span>{{ $topic->study_sessions_count > 0 ? 'Estudo · planeado' : 'Estudo · por planear' }}</span>
                            <span class="{{ $masteryStatus === 'no_evidence' ? 'unknown' : 'mastery-evidence' }}">
                                Domínio · {{ $masteryLabels[$masteryStatus] ?? $masteryStatus }}
                                @if($mastery?->score_percent !== null)
                                    · {{ number_format((float) $mastery->score_percent, 0) }}%
                                @endif
                            </span>
                            <span class="{{ $topic->rich_source_chunks_count > 0 ? 'source-ready' : 'unknown' }}">
                                Fonte · {{ $topic->source_chunks_count }} fragmento(s)
                                @if($topic->rich_source_chunks_count > 0)
                                    · {{ $topic->rich_source_chunks_count }} detalhado(s)
                                @endif
                            </span>
                        </div>
                        <div class="topic-card-actions">
                            <a href="{{ route('practice.index', ['topic_id' => $topic->id]) }}">Praticar ({{ $topic->exercises_count }})</a>
                            <a href="{{ route('practice.create', ['course_id' => $course->id, 'topic_id' => $topic->id]) }}">Criar exercício</a>
                            @if($topic->source === 'manual')<a href="{{ route('course-topics.edit', ['course' => $course, 'topic' => $topic], false) }}">Editar tópico</a>@endif
                            @if($topic->rich_source_chunks_count > 0)
                                <form method="POST" action="{{ route('practice.generate-topic', $topic) }}">
                                    @csrf
                                    <button class="text-link" type="submit">Gerar da fonte</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty">Acrescenta os tópicos do programa desta UC para acompanhares a matéria lecionada.</p>
                @endforelse
            </div>
            <div class="topic-coverage-actions">
                @if($course->topics->isNotEmpty())<button class="button primary" form="taught-topics" type="submit">Guardar matéria lecionada</button>@endif
                <a href="{{ route('study.create', ['course_id' => $course->id], false) }}">Planear estudo</a>
            </div>

            @if($course->studySessions->isNotEmpty())
                <div class="course-study-sessions">
                    @foreach($course->studySessions as $session)
                        <article>
                            <strong>{{ $session->localStartsAt()->format('d/m · H:i') }}</strong>
                            <span>{{ $session->title ?: ucfirst($session->type) }} · {{ $session->planned_minutes }} min</span>
                            <small>{{ $session->localEndsAt()->isPast() ? 'Janela decorrida — execução não confirmada' : 'Planeado' }}</small>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
        <section class="card">
            <div class="card-head"><h3>Próximas aulas</h3><a href="/calendar">Calendário</a></div>
            @forelse ($upcomingClasses as $class)
                <article class="item">
                    <div class="date">{{ $class->localStartsAt()->format('d/m') }}</div>
                    <div>
                        <strong>{{ $class->localStartsAt()->format('H:i') }}@if($class->localEndsAt())–{{ $class->localEndsAt()->format('H:i') }}@endif</strong>
                        <p>{{ $class->location ?: 'Sala por confirmar' }} · {{ $class->title }}</p>
                    </div>
                </article>
            @empty
                <p class="empty">Sem próximas aulas importadas.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h3>Avaliações</h3><span>{{ $course->assessments->count() }}</span></div>
            @forelse ($course->assessments as $assessment)
                @php
                    $conditional = (bool) ($assessment->metadata['conditional'] ?? false);
                    $regime = $assessment->metadata['regime'] ?? null;
                @endphp
                <article class="item {{ $conditional ? 'conditional-item' : '' }}">
                    <div class="date">{{ $assessment->due_at?->copy()->timezone(config('app.timezone'))->format('d/m') ?? '—' }}</div>
                    <div>
                        <strong>{{ $assessment->title }}</strong>
                        <p>
                            {{ ucfirst(str_replace('_', ' ', $assessment->type)) }}
                            @if($regime) · {{ ucfirst($regime) }} @endif
                            · {{ $conditional ? 'Condicional' : 'Ação necessária' }}
                        </p>
                    </div>
                </article>
            @empty
                <p class="empty">Ainda não foram recolhidas avaliações para esta UC.</p>
            @endforelse
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Tarefas</h3><span>{{ $course->tasks->count() }}</span></div>
            @forelse($course->tasks as $task)
                <article class="item">
                    <div class="date">{{ $task->due_at ? $task->due_at->copy()->timezone(config('app.timezone'))->format('d/m') : '—' }}</div>
                    <div>
                        <strong>{{ $task->title }}</strong>
                        <p>
                            {{ ucfirst($task->type) }} · {{ $task->status === 'completed' ? 'Concluída' : 'Pendente' }}
                            @if(! $task->due_at)
                                · prazo não publicado
                            @endif
                        </p>
                        @if($task->description)
                            <small class="task-description">{{ $task->description }}</small>
                        @endif
                    </div>
                </article>
            @empty
                <p class="empty">Sem tarefas registadas para esta UC.</p>
            @endforelse
        </section>



        <section class="card wide">
            <div class="card-head"><h3>Exercícios</h3><a href="{{ route('practice.create', ['course_id' => $course->id]) }}">+ Criar exercício</a></div>
            <div class="practice-course-grid">
                @forelse($course->exercises as $exercise)
                    <a class="practice-course-card" href="{{ route('practice.show', $exercise) }}">
                        <span class="activity-type">{{ strtoupper(str_replace('_', ' ', $exercise->type)) }}@if(($exercise->metadata['source_grounded'] ?? false)) · FONTE @endif</span>
                        <strong>{{ $exercise->title ?: 'Exercício' }}</strong>
                        <p>{{ $exercise->attempts_count }} tentativa(s)</p>
                    </a>
                @empty
                    <p class="empty">Ainda não existem exercícios para esta UC.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Fontes de estudo</h3><span>{{ $course->sourceChunks->count() }} de {{ $course->active_source_chunks_count }} excerto(s)</span></div>
            @if($course->active_source_chunks_count > $course->sourceChunks->count())<p class="muted-text">Amostra de fontes. Os ficheiros completos estão disponíveis nos materiais desta UC.</p>@endif
            <div class="source-chunk-grid">
                @forelse($course->sourceChunks as $chunk)
                    <article class="source-chunk-card {{ $chunk->quality }}">
                        <div class="source-chunk-head">
                            <strong>{{ $chunk->sourceLabel() }}</strong>
                            <span>{{ $chunk->quality === 'content' ? 'Detalhado' : 'Esquema' }}</span>
                        </div>
                        <small>{{ $chunk->locator }}</small>
                        <p>{{ \Illuminate\Support\Str::limit($chunk->content, 240) }}</p>
                        <div class="topic-chips">
                            @foreach($chunk->topics as $linkedTopic)
                                <span>{{ $linkedTopic->title }}</span>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="empty">Ainda não existem fragmentos de conteúdo indexados para esta UC.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Materiais</h3><a href="{{ route('materials.index', ['course_id' => $course->id], false) }}">Ver {{ $course->materials->count() }} material(is) desta UC</a></div>
            <div class="course-material-grid">
                @forelse($course->materials as $material)
                    @php
                        $latestVersion = $material->versions->first();
                    @endphp
                    <article class="course-material">
                        <div class="material-card-head">
                            <span class="material-type">{{ strtoupper($material->type) }}</span>
                            <span class="source-pill {{ $material->source === 'manual' ? 'manual' : '' }}">{{ $material->source === 'manual' ? 'Manual' : ($material->source === 'moodle' ? 'Moodle' : 'Fonte auditada') }}</span>
                        </div>
                        <strong><a href="{{ $latestVersion?->storage_path ? route('materials.download', ['material' => $material, 'version' => $latestVersion], false) : ($material->url ?: '#') }}">{{ $material->title }}</a></strong>
                        @if($latestVersion)
                            <small>{{ $latestVersion->version_label ?: 'Versão observada' }}</small>
                            @if($latestVersion->storage_path)
                                @php
                                    $courseExtractionLabels = [
                                        'queued' => 'na fila',
                                        'processing' => 'a extrair',
                                        'extracted' => 'texto extraído',
                                        'empty_or_scanned' => 'sem texto pesquisável',
                                        'empty' => 'sem texto extraível',
                                        'failed' => 'extração falhou',
                                        'manual_text' => 'texto manual',
                                    ];
                                @endphp
                                <small>{{ $latestVersion->original_filename }} · {{ $courseExtractionLabels[$latestVersion->extraction_status] ?? $latestVersion->extraction_status }}</small>
                            @endif
                        @endif
                        <div class="material-meta">
                            @if($latestVersion?->storage_path)
                                <a href="{{ route('materials.download', ['material' => $material, 'version' => $latestVersion], false) }}">Descarregar ficheiro</a>
                            @endif
                            @if($material->url)
                                <a href="{{ $material->url }}" target="_blank" rel="noopener noreferrer">{{ $material->source === 'moodle' ? 'Ver no Moodle' : 'Abrir origem' }} ↗</a>
                            @endif
                            @if($material->source === 'manual')
                                <a href="{{ route('materials.edit', $material) }}">Editar</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty">Ainda não existem materiais registados para esta UC.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Sumários e matéria lecionada</h3><span>{{ $course->lessonSummaries->count() }}</span></div>
            @forelse($course->lessonSummaries as $summary)
                <article class="lesson-summary">
                    <div class="lesson-summary-head">
                        <strong>{{ $summary->title }}</strong>
                        @if($summary->occurred_at)
                            <span>até {{ $summary->occurred_at->copy()->timezone(config('app.timezone'))->format('d/m/Y') }}</span>
                        @endif
                    </div>
                    <p>{!! nl2br(e($summary->content)) !!}</p>
                    <small>{{ $summary->source === 'inforestudante_audit' ? 'Fonte: InforEstudante auditado' : $summary->source }}</small>
                </article>
            @empty
                <p class="empty">Ainda não existem sumários recolhidos para esta UC.</p>
            @endforelse
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Histórico recente</h3><span>{{ $recentClasses->count() }}</span></div>
            <div class="history-grid">
                @forelse ($recentClasses as $class)
                    <div class="history-item">
                        <strong>{{ $class->localStartsAt()->format('d/m · H:i') }}</strong>
                        <span>{{ $class->location ?: 'Sala por confirmar' }}</span>
                        <small>{{ $class->title }}</small>
                    </div>
                @empty
                    <p class="empty">Sem histórico importado.</p>
                @endforelse
            </div>
        </section>

        <section class="card wide">
            <div class="card-head"><h3>Fontes</h3><span>{{ $course->sourceCourses->count() }}</span></div>
            @forelse ($course->sourceCourses as $sourceCourse)
                <div class="source-row">
                    <div>
                        <strong>{{ $sourceCourse->source }}</strong>
                        <span>{{ $sourceCourse->external_name }}</span>
                    </div>
                    <code>{{ $sourceCourse->external_id }}</code>
                </div>
            @empty
                <p class="empty">Sem fonte académica associada.</p>
            @endforelse
        </section>
    </main>
</div>
</body>
</html>
