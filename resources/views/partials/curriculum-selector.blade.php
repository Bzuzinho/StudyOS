<div class="curriculum-picker" data-curriculum-picker data-select="{{ $selectId }}">
<div class="learning-actions"><button class="button ghost" type="button" data-pick="taught">Toda a matéria lecionada</button><button class="button ghost" type="button" data-pick="all">Selecionar tudo</button><button class="button ghost" type="button" data-pick="clear">Limpar seleção</button></div>
@foreach($courses as $pickerCourse)
@php $pickerTopics = \App\Services\Learning\Curriculum::topics($pickerCourse); @endphp
<div data-topic-course="{{ $pickerCourse->id }}" hidden>
@forelse($pickerTopics->whereNull('parent_id') as $theme)
@php $children = $pickerTopics->where('parent_id', $theme->id); @endphp
<details class="curriculum-theme" @if($selectedTopics->intersect($children->pluck('id')->push($theme->id))->isNotEmpty()) open @endif>
<summary><span>{{ $theme->title }}</span><small>{{ $children->count() ?: 1 }} {{ $children->isEmpty() ? 'tópico' : 'subtemas' }}</small></summary>
<label class="curriculum-check"><input type="checkbox" @if($children->isEmpty()) name="topic_ids[]" data-leaf data-taught="{{ $theme->taught_at ? 1 : 0 }}" @endif value="{{ $theme->id }}" data-theme-check @checked($selectedTopics->contains($theme->id))><span>{{ $children->isEmpty() ? 'Selecionar tópico' : 'Selecionar tema completo' }}</span></label>
@foreach($children as $child)<label class="curriculum-check"><input type="checkbox" name="topic_ids[]" data-leaf data-taught="{{ $child->taught_at ? 1 : 0 }}" value="{{ $child->id }}" @checked($selectedTopics->contains($child->id) || $selectedTopics->contains($theme->id))><span>{{ $child->title }}<small>{{ $child->taught_at ? 'Lecionado' : 'Não lecionado' }}</small></span></label>@endforeach
</details>
@empty<p class="empty">Esta UC ainda não tem programa curricular. <a href="{{ route('courses.show', $pickerCourse, false) }}">Abrir UC e importar pacote →</a></p>@endforelse
</div>@endforeach
<p class="field-hint" data-selection-label role="status">Seleciona a UC e a matéria.</p>
</div>
