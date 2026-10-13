<?php
namespace App\Http\Controllers;

use App\Models\Course;
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

    public function store(Request $request, Course $course, CurriculumPackageImporter $importer): RedirectResponse
    {
        $data = $request->validate(['package' => ['required', 'file', 'max:10240']]);
        try {
            $result = $importer->import($course, $data['package']->getRealPath());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }
        return redirect()->to(route('courses.show', $course, false).'#course-topics')
            ->with('status', 'Pacote importado: '.$result['themes'].' temas, '.
                $result['subtopics'].' subtemas e '.$result['exercises'].' exercícios.');
    }
}
