<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Learning\AiTopicMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiTopicMatchController
{
    public function run(Request $request, Course $course, AiTopicMatcher $matcher): RedirectResponse
    {
        $request->validate(['access_code'=>['required','string','max:200']]);
        $code=config('studyos_ai.access_code');
        if (!is_string($code) || $code==='' ||
            !hash_equals($code,(string)$request->input('access_code'))) abort(403);
        try {
            $result=$matcher->run($course);
            $message=$result['processed'].' excertos analisados; '.$result['linked'].
                ' novas associações. '.($result['remaining']>0
                    ? 'Restam '.$result['remaining'].' excertos por analisar. Podes continuar.'
                    : 'Análise dos excertos disponíveis concluída.');
            return redirect()->to(route('courses.show',$course,false).'#coverage')
                ->with('status',$message);
        } catch(RuntimeException $e) {
            return redirect()->to(route('courses.show',$course,false).'#coverage')
                ->withErrors(['ai'=>$e->getMessage()]);
        }
    }
}
