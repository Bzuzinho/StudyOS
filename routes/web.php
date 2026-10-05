<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/calendar', CalendarController::class)->name('calendar');
Route::get('/api/system/status', SystemStatusController::class)->name('system.status');
