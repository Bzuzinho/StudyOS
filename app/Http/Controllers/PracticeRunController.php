<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\StudySession;
use App\Models\Topic;
use App\Services\Practice\ExerciseGrader;
use App\Services\Practice\OpenAnswerAiGrader;
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
        $courses = Course::query()->where('status', 'active')->with(['topics' => fn ($q) => $q
            ->where('status', 'active')->orderBy('position')])->orderBy('name')->get();
        $session = $request->integer('study_session_id') ? StudySession::with('topics')->findOrFail($request->integer('study_session_id')) : null;
        $selected = collect($request->input('topic_ids', array_filter([$request->integer('topic_id')])))->map(fn ($id) => (int) $id);
        if ($session) $selected = $session->topics->pluck('id');
        $repeat = $request->integer('repeat_run_id') ? DB::table('practice_runs')->find($request->integer('repeat_run_id')) : null;
        if ($repeat && $selected->isEmpty()) $selected = collect(data_get(json_decode($repeat->settings, true), 'topic_ids', []));
        return view('practice.start', [
            'courses' => $courses, 'selectedCourseId' => $session?->course_id ?: ($repeat?->course_id ?: $request->integer('course_id')),
            'selectedTopics' => collect(old('topic_ids', $selected->all()))->map(fn ($id) => (int) $id),
            'studySessionId' => $session?->id, 'repeatRunId' => $repeat?->id,
        ]);
    }

    private function filters(Request $request, bool $starting): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'topic_ids' => [$starting ? 'required' : 'sometimes', 'array', 'min:1', 'max:500'],
            'topic_ids.*' => ['required', 'integer', 'distinct'],
            'count' => [$starting ? 'required' : 'sometimes', 'integer', 'min:1', 'max:30'],
            'difficulty' => ['nullable', Rule::in(['basic', 'intermediate', 'advanced'])],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::in(['true_false', 'single_answer', 'numeric', 'open_text'])],
            'mode' => ['nullable', Rule::in(['quick', 'normal', 'assessment'])],
            'errors_only' => ['nullable', 'boolean'],
            'repeat_run_id' => ['nullable', 'integer'],
            'study_session_id' => ['nullable', 'integer', Rule::exists('study_sessions', 'id')],
            'accept_available' => ['nullable', 'boolean'],
        ]);
    }

    public function eligible(Request $request, \App\Services\Practice\PracticeQuestionPool $pool): \Illuminate\Http\JsonResponse
    {
        $data = $this->filters($request, false);
        if (empty($data['topic_ids'])) return response()->json(['count' => 0]);
        $course = Course::with('topics')->findOrFail($data['course_id']);
        return response()->json(['count' => $pool->query($course, $data['topic_ids'], $data)->count()]);
    }

    public function store(Request $request, \App\Services\Practice\PracticeQuestionPool $pool): RedirectResponse
    {
        $data = $this->filters($request, true);
        $course = Course::with('topics')->findOrFail($data['course_id']);
        $topicIds = \App\Services\Learning\Curriculum::expand($course, $data['topic_ids']);
        $session = !empty($data['study_session_id']) ? StudySession::where('course_id', $course->id)->findOrFail($data['study_session_id']) : null;
        $query = $pool->query($course, $topicIds, $data);
        $available = (clone $query)->count();
        if (!$available) throw ValidationException::withMessages(['topic_ids' => 'Não existem perguntas elegíveis para esta seleção.']);
        if ($available < $data['count'] && !$request->boolean('accept_available')) {
            throw ValidationException::withMessages(['count' => "Existem apenas {$available} perguntas elegíveis. Confirma a utilização das disponíveis."]);
        }
        // Prefer unused/least recently answered questions, randomizing ties.
        $exercises = $query->with('topics')->withMax('attempts', 'attempted_at')
            ->orderByRaw('case when (select max(attempted_at) from exercise_attempts where exercise_attempts.exercise_id = exercises.id) is null then 0 else 1 end')
            ->orderBy('attempts_max_attempted_at')->inRandomOrder()->limit((int) $data['count'])->get();
        $snapshots = $exercises->mapWithKeys(fn ($ex) => [$ex->id => [
            'id' => $ex->id, 'title' => $ex->title, 'prompt' => $ex->prompt, 'type' => $ex->type,
            'expected_answer' => $ex->expected_answer, 'answer_config' => $ex->answer_config,
            'explanation' => $ex->explanation, 'max_points' => $ex->max_points, 'metadata' => $ex->metadata,
            'topic_ids' => $ex->topics->pluck('id')->all(),
            'topic_titles' => $ex->topics->pluck('title', 'id')->all(),
        ]])->all();
        $id = DB::table('practice_runs')->insertGetId([
            'course_id' => $course->id, 'study_session_id' => $session?->id,
            'exercise_ids' => json_encode($exercises->pluck('id')->all()), 'responses' => json_encode([]),
            'settings' => json_encode([...$data, 'topic_ids' => $topicIds]),
            'question_snapshots' => json_encode($snapshots, JSON_UNESCAPED_UNICODE),
            'current_index' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('practice.runs.show', $id);
    }

    private function questions(object $row): \Illuminate\Support\Collection
    {
        $ids = json_decode($row->exercise_ids, true) ?: [];
        $snapshots = json_decode($row->question_snapshots ?? 'null', true) ?: [];
        $current = Exercise::with('topics')->whereIn('id', $ids)->get()->keyBy('id');
        return collect($ids)->mapWithKeys(fn ($id) => [$id => isset($snapshots[$id])
            ? new Exercise($snapshots[$id]) : $current->get($id)]);
    }

    public function show(int $run): View
    {
        $row=DB::table('practice_runs')->find($run);
        abort_unless($row,404);
        $ids=json_decode($row->exercise_ids,true) ?: [];
        $responses=json_decode($row->responses,true) ?: [];
        $attempts = ExerciseAttempt::where('metadata->practice_run_id', $row->id)->get()->keyBy('exercise_id');
        foreach ($responses as $exerciseId => &$response) {
            if ($attempt = $attempts->get($exerciseId)) {
                $response = [...$response, 'score' => $attempt->score, 'max_points' => $attempt->max_points, 'grading_status' => $attempt->grading_status, 'grading_method' => $attempt->grading_method, 'feedback' => $attempt->feedback, 'attempt_id' => $attempt->id];
            }
        }
        unset($response);
        $exercises=$this->questions($row);
        if ($row->status==='completed') {
            $questions=collect($ids)->map(fn($id)=>[
                'exercise'=>$exercises->get($id),
                'response'=>$responses[$id] ?? null,
            ])->filter(fn($x)=>$x['exercise']!==null);
            $graded=$questions->filter(fn($q)=>($q['response']['grading_status']??'')==='graded');
            $pending=$questions->count()-$graded->count();
            $points=$graded->sum(fn($q)=>(float)($q['response']['score']??0));
            $maximum=$graded->sum(fn($q)=>(float)$q['exercise']->max_points);
            $provisional = $graded->contains(fn ($q) => ($q['response']['grading_method'] ?? '') === 'ai_rubric_provisional');
            $correct = $graded->filter(fn ($q) => (float) $q['response']['score'] >= (float) $q['exercise']->max_points)->count();
            $byTopic = [];
            foreach ($questions as $question) {
                $ex = $question['exercise'];
                $titles = $ex->topic_titles ?? $ex->topics->pluck('title', 'id')->all();
                foreach ($titles as $topicId => $title) {
                    $byTopic[$topicId] ??= ['title' => $title, 'points' => 0, 'maximum' => 0, 'pending' => 0];
                    if (($question['response']['grading_status'] ?? '') === 'graded') {
                        $byTopic[$topicId]['points'] += (float) $question['response']['score'];
                        $byTopic[$topicId]['maximum'] += (float) $ex->max_points;
                    } else $byTopic[$topicId]['pending']++;
                }
            }
            $settings = json_decode($row->settings ?? 'null', true) ?: [];
            $retryTopics = $settings['topic_ids'] ?? collect($questions)->flatMap(fn ($q) => $q['exercise']->topic_ids ?? $q['exercise']->topics->pluck('id')->all())->unique()->values()->all();
            return view('practice.result', compact('row','questions','graded','pending','points','maximum','provisional','correct','byTopic','settings','retryTopics'));
        }
        $id=$ids[$row->current_index]??null;
        if (!$id) abort(404);
        $exercise=$exercises->get($id);
        abort_unless($exercise,404);
        return view('practice.run',[
            'run'=>$row,'exercise'=>$exercise,'position'=>$row->current_index+1,'total'=>count($ids),
        ]);
    }

    public function answer(Request $request,int $run,ExerciseGrader $grader,TopicMasteryService $mastery,OpenAnswerAiGrader $aiGrader): RedirectResponse
    {
        $data=$request->validate(['answer'=>['required','string','max:10000'], 'question_id'=>['nullable','integer']]);
        DB::transaction(function() use($run,$data,$grader,$mastery,$aiGrader) {
            $row=DB::table('practice_runs')->where('id',$run)->lockForUpdate()->first();
            abort_unless($row && $row->status==='active',409);
            $ids=json_decode($row->exercise_ids,true)?:[];
            $id=$ids[$row->current_index]??null;
            abort_unless($id,409);
$exercise=$this->questions($row)->get($id);
            abort_unless($exercise, 404);
            if (isset($data['question_id'])) abort_unless((int) $data['question_id'] === (int) $id, 409);
            $result=$grader->grade($exercise->type,$exercise->expected_answer,
                $exercise->answer_config??[],$data['answer'],(float)$exercise->max_points);
            if ($exercise->type==='open_text' && trim((string)$exercise->expected_answer)!=='') {
                try {
                    $result=$aiGrader->grade($exercise->prompt,(string)$exercise->expected_answer,
                        $data['answer'],(array)($exercise->metadata['rubric']??[]),
                        (float)$exercise->max_points);
                } catch (\Throwable $exception) {
                    report($exception);
                    // Remains pending; a failed API request must not fabricate a grade.
                }
            }
            $responses=json_decode($row->responses,true)?:[];
            $responses[$id]=['answer'=>$data['answer'],'score'=>$result['score'], 'max_points'=>$exercise->max_points, 'grading_method'=>$result['grading_method'],
                'grading_status'=>$result['grading_status'],'feedback'=>$result['feedback']];
            $attempt=ExerciseAttempt::create([
                'exercise_id'=>$id,'study_session_id'=>$row->study_session_id,
                'submitted_answer'=>$data['answer'],'score'=>$result['score'],
                'max_points'=>$exercise->max_points,'percentage'=>$result['percentage'],
                'grading_status'=>$result['grading_status'],'grading_method'=>$result['grading_method'],
                'feedback'=>$result['feedback'],'attempted_at'=>now(),
                'metadata'=>['question_snapshot'=>$exercise->getAttributes(), 'practice_run_id'=>$row->id,'provisional_ai'=>($result['grading_method']??'')==='ai_rubric_provisional'],
            ]);
            if ($attempt->grading_status==='graded' && $attempt->grading_method !== 'ai_rubric_provisional') $mastery->recalculateForAttempt($attempt);
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
