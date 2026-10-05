<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualCalendarEventController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/calendar', CalendarController::class)->name('calendar');

Route::get('/calendar/events/create', [ManualCalendarEventController::class, 'create'])->name('calendar-events.create');
Route::post('/calendar/events', [ManualCalendarEventController::class, 'store'])->name('calendar-events.store');
Route::get('/calendar/events/{event}/edit', [ManualCalendarEventController::class, 'edit'])->name('calendar-events.edit');
Route::put('/calendar/events/{event}', [ManualCalendarEventController::class, 'update'])->name('calendar-events.update');
Route::delete('/calendar/events/{event}', [ManualCalendarEventController::class, 'destroy'])->name('calendar-events.destroy');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/api/system/status', SystemStatusController::class)->name('system.status');
