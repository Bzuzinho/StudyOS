<?php

use App\Models\SyncConnection;
use App\Services\Academic\AcademicCatalogBootstrapper;
use App\Services\Calendar\ICalendarSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('studyos:status', function () {
    $this->info('StudyOS operational.');
})->purpose('Show StudyOS application status');

Artisan::command('studyos:bootstrap-academic-year', function () {
    $stats = app(AcademicCatalogBootstrapper::class)->run();
    $this->info('Academic catalogue ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap the audited 2026/2027 InforEstudante course catalogue');

Artisan::command('studyos:sync-ical {connection?}', function () {
    $connectionId = $this->argument('connection');

    if (! $connectionId && config('studyos.calendar.inforestudante_url')) {
        SyncConnection::firstOrCreate(
            ['source' => 'inforestudante_ical', 'name' => 'InforEstudante iCalendar'],
            [
                'secret' => config('studyos.calendar.inforestudante_url'),
                'status' => 'pending',
                'enabled' => true,
            ],
        );
    }

    $query = SyncConnection::query()
        ->where('enabled', true)
        ->whereIn('source', ['inforestudante_ical', 'moodle_ical']);

    if ($connectionId) {
        $query->whereKey($connectionId);
    }

    $connections = $query->get();

    if ($connections->isEmpty()) {
        $this->warn('No enabled iCalendar connections are configured.');

        return 0;
    }

    $service = app(ICalendarSyncService::class);
    $failed = false;

    foreach ($connections as $connection) {
        $this->line("Syncing {$connection->name}...");
        $run = $service->sync($connection);

        if ($run->status === 'success') {
            $this->info('OK '.json_encode($run->stats, JSON_UNESCAPED_UNICODE));
        } else {
            $failed = true;
            $this->error($run->error ?: 'Synchronization failed.');
        }
    }

    return $failed ? 1 : 0;
})->purpose('Synchronize configured StudyOS iCalendar sources');

Schedule::command('studyos:sync-ical')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20);
