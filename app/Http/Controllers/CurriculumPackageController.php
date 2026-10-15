<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Material;
use App\Models\Topic;
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
        return view('courses.read-package-topic', [
            'course' => $course, 'topic' => $topic,
            'sections' => $sections,
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
