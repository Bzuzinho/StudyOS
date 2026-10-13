<nav class="nav" aria-label="Navegação principal">
<a class="brand" href="/" aria-label="StudyOS — Painel académico"><img src="/brand/studyos-mark.svg" width="36" height="36" alt=""><span>StudyOS</span></a>
<div class="nav-links">@foreach(['/' => 'Dashboard', '/calendar' => 'Calendário', '/courses' => 'UCs', '/activities' => 'Atividades', '/materials' => 'Materiais', '/study' => 'Estudo', '/practice' => 'Prática'] as $url => $label)<a href="{{ $url }}" @class(['active' => $active === $url]) @if($active === $url) aria-current="page" @endif>{{ $label }}</a>@endforeach</div>
</nav>
