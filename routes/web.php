<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourseController;
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
Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
Route::get('/materials/{material}/edit', [MaterialController::class, 'edit'])->name('materials.edit');
Route::put('/materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

Route::get('/study', [StudyController::class, 'index'])->name('study.index');
Route::get('/study/create', [StudyController::class, 'create'])->name('study.create');
Route::post('/study', [StudyController::class, 'store'])->name('study.store');
Route::delete('/study/{studySession}', [StudyController::class, 'destroy'])->name('study.destroy');

Route::get('/practice', [PracticeController::class, 'index'])->name('practice.index');
Route::get('/practice/create', [PracticeController::class, 'create'])->name('practice.create');
Route::post('/practice', [PracticeController::class, 'store'])->name('practice.store');
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
Route::get('/api/system/status', SystemStatusController::class)->name('system.status');
