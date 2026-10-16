<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Topic;
use App\Services\Learning\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalAssessmentController
{
    public function index(Course $course)
    {
        $topics = Curriculum::leaves($course->load('topics'));
        $history = DB::table('external_assessments')->where('course_id',$course->id)->latest('assessed_on')->limit(20)->get();
        return view('practice.external', compact('course','topics','history'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate(['report' => ['required','string','max:100000']]);
        $report = json_decode(trim($data['report']),true);
        if (!is_array($report) || ($report['schema'] ?? '') !== 'studyos.external-assessment.v1'
            || (string)($report['academic_code'] ?? '') !== (string)$course->academic_code
            || !is_string($report['assessment_id'] ?? null)
            || !preg_match('/^[a-zA-Z0-9_-]{8,120}$/D',$report['assessment_id'])
            || !is_string($report['assessed_on'] ?? null)
            || !is_numeric($report['score_20'] ?? null)
            || (float)$report['score_20']<0 || (float)$report['score_20']>20
            || !is_array($report['topics'] ?? null) || !count($report['topics'])
            || count($report['topics'])>100) {
            throw ValidationException::withMessages(['report'=>'Relatório incompatível com esta UC ou formato inválido.']);
        }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$report['assessed_on']);
        if (!$date || $date->format('Y-m-d')!==$report['assessed_on'])
            throw ValidationException::withMessages(['report'=>'Data da avaliação inválida.']);
        $known=Curriculum::leaves($course->load('topics'))->keyBy(fn($t)=>(string)($t->metadata['curricular_number']??''));
        $seen=[];
        foreach ($report['topics'] as $topic) {
            $number=(string)($topic['number']??'');
            if (isset($seen[$number]) || !$known->has($number)
                || !is_numeric($topic['score_percent']??null)
                || (float)$topic['score_percent']<0 || (float)$topic['score_percent']>100
                || !is_string($topic['feedback']??null) || mb_strlen($topic['feedback'])>2000)
                throw ValidationException::withMessages(['report'=>'Resultado por subtema inválido: '.$number]);
            $seen[$number]=true;
        }
        foreach (['strengths','difficulties','next_steps'] as $field) {
            if (!is_array($report[$field]??null) || count($report[$field])>30
                || collect($report[$field])->contains(fn($v)=>!is_string($v)||mb_strlen($v)>500))
                throw ValidationException::withMessages(['report'=>'Campo de diagnóstico inválido: '.$field]);
        }
        DB::table('external_assessments')->updateOrInsert(
            ['course_id'=>$course->id,'external_id'=>$report['assessment_id']],
            ['assessed_on'=>$report['assessed_on'],'score_20'=>$report['score_20'],
             'report'=>json_encode($report,JSON_UNESCAPED_UNICODE),
             'updated_at'=>now(),'created_at'=>now()]
        );
        return redirect()->route('practice.external.index',$course)->with('status','Avaliação externa importada. O histórico oficial e as notas universitárias não foram alterados.');
    }
}
