<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Services\Learning\AiStudyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiStudyController
{
    public function generate(Request $request, Topic $topic, AiStudyService $service): RedirectResponse
    {
        $request->validate(['access_code' => ['required', 'string', 'max:200']]);
        $configured = config('studyos_ai.access_code');
        if (! is_string($configured) || $configured === ''
            || ! hash_equals($configured, (string) $request->input('access_code'))) {
            abort(403, 'Código de autorização inválido.');
        }

        try {
            $result = $service->prepare($topic);
        } catch (RuntimeException $error) {
            return redirect()->to(route('courses.show', $topic->course_id, false))
                ->withErrors(['ai' => $error->getMessage()]);
        }

        return redirect()->to(route('courses.show', $topic->course_id, false))
            ->with('ai_study_sheet', ['topic' => $topic->title, 'result' => $result]);
    }
}
