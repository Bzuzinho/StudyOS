<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Material;
use App\Models\Topic;
use App\Models\StudySession;
use App\Services\Learning\CurriculumPackageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CurriculumPackageController
{
    public function create(Course $course): View
    {
        return view('courses.import-package', compact('course'));
    }

    public function read(Course $course, Topic $topic): View
    {
        abort_unless($topic->course_id === $course->id && $topic->source === 'chatgpt_package', 404);
        $material = Material::query()->where('course_id', $course->id)
            ->where('source', 'chatgpt_package')
            ->where('external_id', $topic->external_id)
            ->firstOrFail();
        $version = $material->versions()->orderByDesc('id')->firstOrFail();
        $blocks = $version->metadata['pedagogical_blocks'] ?? null;
        $isStructured = is_array($blocks);
        $sections = $isStructured ? app(\App\Services\Learning\PedagogicalBlocks::class)->forMode($blocks, 'study')
            : app(\App\Services\Learning\PedagogicalManual::class)->sections((string) $version->content_text);
        $sessionId = request()->integer('study_session_id');
        $studySession = $sessionId ? StudySession::query()
            ->whereKey($sessionId)
            ->where('course_id', $course->id)
            ->whereHas('topics', fn ($query) => $query->whereKey($topic->id))
            ->first() : null;
        $backUrl = $studySession
            ? route('study.show', $studySession, false)
            : route('courses.show', $course, false).'#package-curriculum';
        $backLabel = $studySession ? 'Voltar à sessão de estudo' : 'Voltar à unidade curricular';
        return view('courses.read-package-topic', [
            'course' => $course, 'topic' => $topic,
            'backUrl' => $backUrl, 'backLabel' => $backLabel,
            'sections' => $isStructured ? $sections : array_values(array_filter($sections, fn ($section) => $section['kind'] !== 'summary')),
            'reviewSections' => $isStructured
                ? app(\App\Services\Learning\PedagogicalBlocks::class)->forMode($blocks, 'review')
                : app(\App\Services\Learning\PedagogicalManual::class)->review($sections),
        ]);
    }

    public function store(Request $request, Course $course, CurriculumPackageImporter $importer): RedirectResponse
    {
        $data = $request->validate(['package' => ['required', 'file', 'max:10240'], 'mode' => ['required', 'in:replace,append']]);
        try {
            $result = $importer->import($course, $data['package']->getRealPath(), $data['mode']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }
        return redirect()->to(route('courses.show', $course, false).'#package-curriculum')
            ->with('status', ($data['mode'] === 'replace' ? 'Pacote substituído: ' : 'Conteúdos acrescentados: ').$result['themes'].' temas, '.
                $result['subtopics'].' subtemas e '.$result['exercises'].' exercícios.');
    }
}
