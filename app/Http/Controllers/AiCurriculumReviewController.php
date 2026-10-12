<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Learning\AiCurriculumReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiCurriculumReviewController
{
    public function review(Request $request, Course $course, AiCurriculumReviewService $service): RedirectResponse
    {
        $data = $request->validate(['access_code' => ['required', 'string', 'max:200']]);
        $code = config('studyos_ai.access_code');
        if (! is_string($code) || $code === '' || ! hash_equals($code, $data['access_code'])) {
            abort(403);
        }
        try {
            $review = $service->review($course);
        } catch (RuntimeException $exception) {
            return redirect()->to(route('courses.show', $course, false).'#ai-curriculum')
                ->withErrors(['ai' => $exception->getMessage()]);
        }

        return redirect()->to(route('courses.show', $course, false).'#ai-curriculum-results')
            ->with('status', 'Análise curricular concluída. Consulta abaixo as propostas e as fontes antes de alterar matéria lecionada.');
    }
}
