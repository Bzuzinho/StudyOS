<!doctype html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Importar pacote · StudyOS</title>@include('partials.app-identity')<link rel="stylesheet" href="/css/app.css"></head>
<body><div class="shell narrow-shell">
<nav class="nav"><a class="brand" href="/"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a><div class="nav-links"><a href="/">Dashboard</a><a href="/calendar">Calendário</a><a class="active" href="/courses">UCs</a><a href="/materials">Materiais</a><a href="/study">Estudo</a><a href="/practice">Prática</a></div></nav>
<div class="breadcrumb"><a href="{{ route('courses.show', $course, false) }}">← {{ $course->name }}</a></div>
<header class="form-header"><p class="eyebrow">Material pedagógico estruturado</p><h1>Importar pacote da UC</h1><p>Importa de uma só vez a árvore curricular, os manuais de estudo de cada subtema e os exercícios com respostas. Os ficheiros ficam associados automaticamente pelos códigos do manifesto.</p></header>
@if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="event-form card" method="POST" enctype="multipart/form-data" action="{{ route('courses.import-package.store', $course, false) }}">
@csrf
<label class="field"><span>Pacote ZIP gerado no ChatGPT</span><input type="file" name="package" accept=".zip,application/zip" required><small>Máximo 10 MB · pacote com manifest.json, exercicios.json e os manuais em temas/.</small></label>
<p class="muted-text">A UC de destino é {{ $course->name }} ({{ $course->academic_code }}). A importação valida a correspondência antes de alterar dados e permite atualizar o mesmo pacote sem duplicar temas ou exercícios. Não marca automaticamente nenhum tema como lecionado nem apaga avaliações anteriores.</p>
<div class="card" style="padding:18px;margin:16px 0">
<strong>Escolhe o tipo de importação</strong>
<p><strong>Substituir tudo:</strong> atualiza os elementos incluídos no ZIP e desativa os tópicos, manuais e exercícios anteriores deste pacote que já não estejam presentes. Mantém o histórico de estudo, tentativas e materiais de outras origens (incluindo Moodle).</p>
<p><strong>Acrescentar:</strong> cria novos conteúdos e atualiza os que tenham o mesmo identificador, sem desativar elementos não incluídos no ZIP.</p>
</div>
<div class="form-actions">
<a class="button ghost" href="{{ route('courses.show', $course, false) }}">Cancelar</a>
<button class="button ghost" name="mode" value="append" type="submit">Acrescentar ao existente</button>
<button class="button primary" name="mode" value="replace" type="submit" onclick="return confirm('Substituir os conteúdos pedagógicos desta UC? Os registos do pacote que não constem do ZIP serão desativados. O histórico e os materiais do Moodle serão preservados.')">Substituir pacote existente</button>
</div>
</form></div></body></html>