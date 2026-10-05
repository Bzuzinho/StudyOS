<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SyncRun;
use Illuminate\View\View;
class DashboardController
{
    public function __invoke():View
    {
        $now=now();
        return view('dashboard',[
            'courseCount'=>Course::count(),
            'todayClasses'=>ClassOccurrence::with('course')->whereDate('starts_at',$now->toDateString())->orderBy('starts_at')->get(),
            'upcomingAssessments'=>Assessment::with('course')->whereNotNull('due_at')->where('due_at','>=',$now)->orderBy('due_at')->limit(6)->get(),
            'lastSync'=>SyncRun::latest('started_at')->first(),
        ]);
    }
}
