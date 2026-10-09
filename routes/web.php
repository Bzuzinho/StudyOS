<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseTopicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualCalendarEventController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/calendar', CalendarController::class)->name('calendar');
Route::get('/activities', ActivityController::class)->name('activities.index');

Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
Route::get('/moodle/sync', [\App\Http\Controllers\MoodleSyncController::class, 'index'])->name('moodle.sync');
Route::post('/moodle/browser/start', [\App\Http\Controllers\MoodleBrowserController::class, 'start'])->middleware('throttle:6,1')->block(60, 60)->name('moodle.browser.start');
Route::get('/moodle/browser/status', [\App\Http\Controllers\MoodleBrowserController::class, 'status'])->block(40, 40)->name('moodle.browser.status');
Route::get('/moodle/browser/frame', [\App\Http\Controllers\MoodleBrowserController::class, 'frame'])->block(40, 40)->name('moodle.browser.frame');
Route::post('/moodle/browser/input', [\App\Http\Controllers\MoodleBrowserController::class, 'input'])->middleware('throttle:240,1')->block(40, 40)->name('moodle.browser.input');
Route::post('/moodle/browser/cancel', [\App\Http\Controllers\MoodleBrowserController::class, 'cancel'])->block(40, 40)->name('moodle.browser.cancel');
Route::post('/moodle/start', [\App\Http\Controllers\MoodleSyncController::class, 'start'])->middleware('throttle:6,1')->name('moodle.start');
Route::get('/moodle/callback', [\App\Http\Controllers\MoodleSyncController::class, 'callback'])->name('moodle.callback');
Route::post('/moodle/complete', [\App\Http\Controllers\MoodleSyncController::class, 'complete'])->middleware('throttle:6,1')->block(10, 10)->name('moodle.complete');
Route::get('/moodle/status', [\App\Http\Controllers\MoodleSyncController::class, 'status'])->name('moodle.status');
Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
Route::get('/materials/{material}/versions/{version}/download', [MaterialController::class, 'download'])->name('materials.download');
Route::post('/materials/{material}/versions/{version}/reprocess', [MaterialController::class, 'reprocess'])->name('materials.reprocess');
Route::get('/materials/{material}/edit', [MaterialController::class, 'edit'])->name('materials.edit');
Route::put('/materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

Route::get('/study', [StudyController::class, 'index'])->name('study.index');
Route::get('/study/create', [StudyController::class, 'create'])->name('study.create');
Route::post('/study', [StudyController::class, 'store'])->name('study.store');
Route::patch('/study/{studySession}/complete', [StudyController::class, 'complete'])->name('study.complete');
Route::delete('/study/{studySession}', [StudyController::class, 'destroy'])->name('study.destroy');

Route::get('/practice', [PracticeController::class, 'index'])->name('practice.index');
Route::get('/practice/create', [PracticeController::class, 'create'])->name('practice.create');
Route::post('/practice', [PracticeController::class, 'store'])->name('practice.store');
Route::post('/practice/generate/topic/{topic}', [PracticeController::class, 'generateForTopic'])->name('practice.generate-topic');
Route::get('/practice/{exercise}', [PracticeController::class, 'show'])->name('practice.show');
Route::get('/practice/{exercise}/edit', [PracticeController::class, 'edit'])->name('practice.edit');
Route::put('/practice/{exercise}', [PracticeController::class, 'update'])->name('practice.update');
Route::delete('/practice/{exercise}', [PracticeController::class, 'destroy'])->name('practice.destroy');
Route::post('/practice/{exercise}/attempts', [PracticeController::class, 'attempt'])->name('practice.attempt');
Route::put('/practice/attempts/{attempt}/review', [PracticeController::class, 'reviewAttempt'])->name('practice.review-attempt');

Route::get('/calendar/events/create', [ManualCalendarEventController::class, 'create'])->name('calendar-events.create');
Route::post('/calendar/events', [ManualCalendarEventController::class, 'store'])->name('calendar-events.store');
Route::get('/calendar/events/{event}/edit', [ManualCalendarEventController::class, 'edit'])->name('calendar-events.edit');
Route::put('/calendar/events/{event}', [ManualCalendarEventController::class, 'update'])->name('calendar-events.update');
Route::delete('/calendar/events/{event}', [ManualCalendarEventController::class, 'destroy'])->name('calendar-events.destroy');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses/{course}/topics/create', [CourseTopicController::class, 'create'])->name('course-topics.create');
Route::post('/courses/{course}/topics', [CourseTopicController::class, 'store'])->name('course-topics.store');
Route::patch('/courses/{course}/topics/coverage', [CourseTopicController::class, 'coverage'])->name('course-topics.coverage');
Route::get('/courses/{course}/topics/{topic}/edit', [CourseTopicController::class, 'edit'])->name('course-topics.edit');
Route::put('/courses/{course}/topics/{topic}', [CourseTopicController::class, 'update'])->name('course-topics.update');
Route::delete('/courses/{course}/topics/{topic}', [CourseTopicController::class, 'destroy'])->name('course-topics.destroy');
Route::get('/api/system/status', SystemStatusController::class)->name('system.status');
