<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseTopicController
{
    public function create(Course $course): View
    {
        return view('courses.topic-form', ['course' => $course, 'topic' => new Topic()]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($course, $data) {
            Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $course->topics()->create([
                'source' => 'manual',
                'external_id' => (string) Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'position' => ((int) $course->topics()->max('position')) + 1,
                'status' => 'active',
                'taught_at' => ($data['is_taught'] ?? false) ? now() : null,
            ]);
        });

        return $this->backToCourse($course, 'Tópico acrescentado à UC.');
    }

    public function edit(Course $course, Topic $topic): View
    {
        $this->ensureManualTopic($course, $topic);

        return view('courses.topic-form', compact('course', 'topic'));
    }

    public function update(Request $request, Course $course, Topic $topic): RedirectResponse
    {
        $this->ensureManualTopic($course, $topic);
        $data = $this->validated($request);
        $topic->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'taught_at' => ($data['is_taught'] ?? false) ? ($topic->taught_at ?? now()) : null,
        ]);

        return $this->backToCourse($course, 'Tópico atualizado.');
    }

    public function destroy(Course $course, Topic $topic): RedirectResponse
    {
        $this->ensureManualTopic($course, $topic);
        // Preserve the topic and any linked exercises or study history.
        $topic->update(['status' => 'archived']);

        return $this->backToCourse($course, 'Tópico retirado da lista.');
    }

    public function coverage(Request $request, Course $course): RedirectResponse
    {
        $topicRule = fn () => Rule::exists('topics', 'id')->where('course_id', $course->id)->where('status', 'active');
        $data = $request->validate([
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['required', 'integer', 'distinct', $topicRule()],
            'taught_topic_ids' => ['sometimes', 'array'],
            'taught_topic_ids.*' => ['required', 'integer', 'distinct', $topicRule()],
        ]);
        $selected = $data['taught_topic_ids'] ?? [];
        if (array_diff($selected, $data['topic_ids'])) {
            throw ValidationException::withMessages(['taught_topic_ids' => 'Seleciona apenas tópicos apresentados nesta UC.']);
        }

        DB::transaction(function () use ($course, $data, $selected) {
            Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $topics = $course->topics()->where('status', 'active')->whereIn('id', $data['topic_ids']);
            (clone $topics)->whereIn('id', $selected)->whereNull('taught_at')->update(['taught_at' => now()]);
            (clone $topics)->whereNotIn('id', $selected)->whereNotNull('taught_at')->update(['taught_at' => null]);
        });

        return $this->backToCourse($course, 'Matéria lecionada atualizada.');
    }

    private function validated(Request $request): array
    {
        if (is_string($request->input('title'))) {
            $request->merge(['title' => trim($request->input('title'))]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_taught' => ['sometimes', 'boolean'],
        ]);
    }

    private function ensureManualTopic(Course $course, Topic $topic): void
    {
        abort_unless($topic->course_id === $course->id && $topic->status === 'active', 404);
        abort_unless($topic->source === 'manual', 403);
    }

    private function backToCourse(Course $course, string $message): RedirectResponse
    {
        return redirect(route('courses.show', $course, false).'#course-topics')->with('status', $message);
    }
}
