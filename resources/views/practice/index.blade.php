<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prática · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css?v=practice-run-1"></head><body><div class="shell">
<nav class="nav"><a class="brand" href="/"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a><div class="nav-links"><a href="/">Dashboard</a><a href="/calendar">Calendário</a><a href="/courses">UCs</a><a href="/activities">Atividades</a><a href="/materials">Materiais</a><a href="/study">Estudo</a><a class="active" href="/practice">Prática</a></div></nav>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
<header class="calendar-header"><div><p class="eyebrow">Banco de questões · treino guiado</p><h1>Prática</h1><p>Escolhe a matéria a treinar, responde a uma sequência de perguntas e consulta os resultados no final. O repositório de questões não é apresentado como uma lista.</p></div><a class="button primary" href="{{ route('practice.runs.create',array_filter(['course_id'=>$selectedCourseId,'topic_id'=>$selectedTopicId,'study_session_id'=>$studySession?->id])) }}">Iniciar treino</a></header>
@if($studySession)<section class="card wide"><strong>Sessão de estudo: {{ $studySession->course->name }}</strong><p>{{ $studySession->localStartsAt()->format('d/m H:i') }} · As respostas ficarão associadas à sessão planeada.</p><a class="button primary" href="{{ route('practice.runs.create',['course_id'=>$studySession->course_id,'study_session_id'=>$studySession->id]) }}">Treinar nesta sessão</a></section>@endif
<main class="grid">
<section class="card wide"><div class="card-head"><h3>Começar por unidade curricular</h3></div><div class="topic-grid">
@foreach($courses as $course)
<article class="topic-card"><h3>{{ $course->name }}</h3><p class="muted-text">Seleciona os temas e subtemas a praticar. O StudyOS escolhe as perguntas disponíveis.</p><a class="button primary" href="{{ route('practice.runs.create',['course_id'=>$course->id]) }}">Escolher matéria</a></article>
@endforeach
</div></section>
<section class="card wide"><div class="card-head"><h3>Histórico de treino</h3></div>
@php $runs=\Illuminate\Support\Facades\DB::table('practice_runs')->orderByDesc('id')->limit(15)->get(); @endphp
@forelse($runs as $run)
<article class="study-session"><div><strong>{{ $run->created_at }}</strong><p>{{ $courses->firstWhere('id',$run->course_id)?->name??'UC' }} · {{ count(json_decode($run->exercise_ids,true)?:[]) }} perguntas · {{ $run->status==='completed'?'Concluído':'Em curso' }}</p><a href="{{ route('practice.runs.show',$run->id) }}">{{ $run->status==='completed'?'Ver correção':'Retomar treino' }} →</a></div></article>
@empty <p class="muted-text">Ainda não realizaste sessões de treino.</p>
@endforelse
</section>
</main></div></body></html>