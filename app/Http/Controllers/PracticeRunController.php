<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\StudySession;
use App\Models\Topic;
use App\Services\Practice\ExerciseGrader;
use App\Services\Practice\TopicMasteryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PracticeRunController
{
    public function create(Request $request): View
    {
        $courses = Course::query()->where('status', 'active')->with(['topics' => fn($q) => $q
            ->where('status','active')->orderBy('position')])->orderBy('name')->get();
        return view('practice.start', ['courses'=>$courses,
            'selectedCourseId'=>$request->integer('course_id'),
            'selectedTopicId'=>$request->integer('topic_id'),
            'studySessionId'=>$request->integer('study_session_id')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id'=>['required','integer',Rule::exists('courses','id')],
            'topic_ids'=>['required','array','min:1','max:45'],
            'topic_ids.*'=>['required','integer','distinct'],
            'count'=>['required','integer','min:1','max:30'],
            'study_session_id'=>['nullable','integer',Rule::exists('study_sessions','id')],
        ]);
        $course = Course::findOrFail($data['course_id']);
        $topicIds = array_map('intval',$data['topic_ids']);
        $topics=Topic::where('course_id',$course->id)->where('status','active')->whereIn('id',$topicIds)->get();
        if($topics->count() !== count($topicIds)) {
            throw ValidationException::withMessages(['topic_ids'=>'Há tópicos que não pertencem à UC.']);
        }
        $expanded=$topics->pluck('id')->all();
        $parents=$topics->filter(fn($t)=>$t->parent_id===null)->pluck('id')->all();
        if ($parents !== []) {
            $expanded=array_unique([...$expanded,...Topic::where('course_id',$course->id)->where('status','active')
                ->whereIn('parent_id',$parents)->pluck('id')->all()]);
        }
        $session=null;
        if (!empty($data['study_session_id'])) {
            $session=StudySession::where('course_id',$course->id)->findOrFail($data['study_session_id']);
        }
        $ids=Exercise::query()->where('course_id',$course->id)->where('status','active')
            ->whereHas('topics',fn($q)=>$q->whereIn('topics.id',$expanded))
            ->inRandomOrder()->limit((int)$data['count'])->pluck('id')->all();
        if (!$ids) {
            return back()->withErrors(['topic_ids'=>'Não existem perguntas no banco para estes subtemas.']);
        }
        $id=DB::table('practice_runs')->insertGetId([
            'course_id'=>$course->id,'study_session_id'=>$session?->id,
            'exercise_ids'=>json_encode($ids),'responses'=>json_encode([]),
            'current_index'=>0,'status'=>'active','created_at'=>now(),'updated_at'=>now(),
        ]);
        return redirect()->route('practice.runs.show',$id);
    }

    public function show(int $run): View
    {
        $row=DB::table('practice_runs')->find($run);
        abort_unless($row,404);
        $ids=json_decode($row->exercise_ids,true) ?: [];
        $responses=json_decode($row->responses,true) ?: [];
        $exercises=Exercise::whereIn('id',$ids)->get()->keyBy('id');
        if ($row->status==='completed') {
            $questions=collect($ids)->map(fn($id)=>[
                'exercise'=>$exercises->get($id),
                'response'=>$responses[$id] ?? null,
            ])->filter(fn($x)=>$x['exercise']!==null);
            $graded=$questions->filter(fn($q)=>($q['response']['grading_status']??'')==='graded');
            $pending=$questions->count()-$graded->count();
            $points=$graded->sum(fn($q)=>(float)($q['response']['score']??0));
            $maximum=$graded->sum(fn($q)=>(float)$q['exercise']->max_points);
            return view('practice.result',compact('row','questions','graded','pending','points','maximum'));
        }
        $id=$ids[$row->current_index]??null;
        if (!$id) abort(404);
        $exercise=$exercises->get($id);
        abort_unless($exercise,404);
        return view('practice.run',[
            'run'=>$row,'exercise'=>$exercise,'position'=>$row->current_index+1,'total'=>count($ids),
        ]);
    }

    public function answer(Request $request,int $run,ExerciseGrader $grader,TopicMasteryService $mastery): RedirectResponse
    {
        $data=$request->validate(['answer'=>['required','string','max:10000']]);
        DB::transaction(function() use($run,$data,$grader,$mastery) {
            $row=DB::table('practice_runs')->where('id',$run)->lockForUpdate()->first();
            abort_unless($row && $row->status==='active',409);
            $ids=json_decode($row->exercise_ids,true)?:[];
            $id=$ids[$row->current_index]??null;
            abort_unless($id,409);
            $exercise=Exercise::findOrFail($id);
            $result=$grader->grade($exercise->type,$exercise->expected_answer,
                $exercise->answer_config??[],$data['answer'],(float)$exercise->max_points);
            $responses=json_decode($row->responses,true)?:[];
            $responses[$id]=['answer'=>$data['answer'],'score'=>$result['score'],
                'grading_status'=>$result['grading_status'],'feedback'=>$result['feedback']];
            $attempt=ExerciseAttempt::create([
                'exercise_id'=>$id,'study_session_id'=>$row->study_session_id,
                'submitted_answer'=>$data['answer'],'score'=>$result['score'],
                'max_points'=>$exercise->max_points,'percentage'=>$result['percentage'],
                'grading_status'=>$result['grading_status'],'grading_method'=>$result['grading_method'],
                'feedback'=>$result['feedback'],'attempted_at'=>now(),
                'metadata'=>['practice_run_id'=>$row->id],
            ]);
            if ($attempt->grading_status==='graded') $mastery->recalculateForAttempt($attempt);
            if ($row->study_session_id) {
                $session=StudySession::find($row->study_session_id);
                if ($session) {
                    $meta=$session->metadata??[];
                    $meta['execution_evidence']=true;
                    $session->update(['status'=>$session->status==='planned'?'started':$session->status,
                        'metadata'=>$meta]);
                }
            }
            $index=$row->current_index+1;
            DB::table('practice_runs')->where('id',$run)->update([
                'responses'=>json_encode($responses,JSON_UNESCAPED_UNICODE),
                'current_index'=>$index,
                'status'=>$index>=count($ids)?'completed':'active',
                'completed_at'=>$index>=count($ids)?now():null,
                'updated_at'=>now(),
            ]);
        });
        return redirect()->route('practice.runs.show',$run);
    }
}
