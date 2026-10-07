@if($assessments->isNotEmpty())
    <div class="inline-assessments">
        @foreach($assessments as $assessmentEvent)
            <div class="inline-assessment-event" data-assessment-id="{{ $assessmentEvent->id }}">
                <span class="assessment-kind">Avaliação</span>
                <strong>{{ $assessmentEvent->title }}</strong>
                @if(! $assessmentEvent->starts_at->equalTo($owner->starts_at))
                    <small>{{ $assessmentEvent->localStartsAt()->format('H:i') }}</small>
                @endif
                @if($assessmentEvent->location && $assessmentEvent->location !== $owner->location)
                    <small>{{ $assessmentEvent->location }}</small>
                @endif
                @if($assessmentEvent->source === 'manual')
                    <a class="event-edit" href="{{ route('calendar-events.edit', $assessmentEvent) }}" aria-label="Editar avaliação {{ $assessmentEvent->title }}">Editar</a>
                @endif
            </div>
        @endforeach
    </div>
@endif
