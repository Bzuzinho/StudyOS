@php $classPercent = $course->classProgressPercent(); @endphp
<div class="course-progress">
    <div class="course-progress-heading">
        <strong>{{ $classPercent !== null ? number_format($classPercent, 1, ',', '.').'%' : 'Sem calendário' }}</strong>
        <span>Aulas decorridas</span>
    </div>
    @if($classPercent !== null)
        <progress value="{{ $course->elapsed_classes_count }}" max="{{ $course->scheduled_classes_count }}" aria-label="Percentagem de aulas decorridas"></progress>
        <span>{{ $course->elapsed_classes_count }} de {{ $course->scheduled_classes_count }} aulas programadas</span>
    @else
        <span>Ainda não existem aulas programadas para calcular o progresso.</span>
    @endif
</div>
