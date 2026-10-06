<?php

use App\Models\SyncConnection;
use App\Services\Academic\AcademicCatalogBootstrapper;
use App\Services\Academic\AssessmentCalendarBootstrapper;
use App\Services\Academic\LearningContextBootstrapper;
use App\Services\Academic\MoodleAuditBootstrapper;
use App\Services\Academic\TopicBootstrapper;
use App\Services\Calendar\ICalendarSyncService;
use App\Services\Learning\CorpusBuilder;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('studyos:status', function () {
    $this->info('StudyOS operational.');
})->purpose('Show StudyOS application status');

Artisan::command('studyos:bootstrap-academic-year', function () {
    $stats = app(AcademicCatalogBootstrapper::class)->run();
    $this->info('Academic catalogue ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap the audited 2026/2027 InforEstudante course catalogue');

Artisan::command('studyos:bootstrap-assessments', function () {
    $stats = app(AssessmentCalendarBootstrapper::class)->run();
    $this->info('Assessment calendar ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap the official 2026/2027 first-semester assessment calendar');

Artisan::command('studyos:bootstrap-moodle-audit', function () {
    $stats = app(MoodleAuditBootstrapper::class)->run();
    $this->info('Moodle audit data ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap audited Moodle course mappings and confirmed activities');

Artisan::command('studyos:bootstrap-learning-context', function () {
    $stats = app(LearningContextBootstrapper::class)->run();
    $this->info('Learning context ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap audited materials, versions and lesson summaries');

Artisan::command('studyos:bootstrap-topics', function () {
    $stats = app(TopicBootstrapper::class)->run();
    $this->info('Topics ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Bootstrap topics explicitly supported by audited academic sources');

Artisan::command('studyos:rebuild-corpus', function () {
    $stats = app(CorpusBuilder::class)->rebuildAll();
    $this->info('Grounded corpus ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Build versioned source chunks from academic content');

Artisan::command('studyos:generate-grounded-practice', function () {
    $stats = app(GroundedPracticeGenerator::class)->generateAll();
    $this->info('Grounded practice ready: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Generate only practice supported by sufficiently rich source chunks');

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

Artisan::command('studyos:deploy-prepare', function () {
    $advisoryLockKey = 2026100601;
    $advisoryLockHeld = false;

    try {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_lock(?)', [$advisoryLockKey]);
            $advisoryLockHeld = true;
        }

        $migrationExit = $this->call('migrate', ['--force' => true]);

        if ($migrationExit !== 0) {
            return $migrationExit;
        }

        if (config('filesystems.default') === 's3' && env('AWS_BUCKET')) {
            $storageCheckPath = 'healthchecks/'.Str::uuid().'.txt';
            Storage::disk('s3')->put($storageCheckPath, 'StudyOS storage check');
            Storage::disk('s3')->delete($storageCheckPath);
            $this->info('Academic cloud storage ready.');
        }

        $catalogue = app(AcademicCatalogBootstrapper::class)->run();
        $this->info('Academic catalogue ready: '.json_encode($catalogue, JSON_UNESCAPED_UNICODE));

        $syncExit = $this->call('studyos:sync-ical');

        if ($syncExit !== 0) {
            return $syncExit;
        }

        $assessments = app(AssessmentCalendarBootstrapper::class)->run();
        $this->info('Assessment calendar ready: '.json_encode($assessments, JSON_UNESCAPED_UNICODE));

        $moodle = app(MoodleAuditBootstrapper::class)->run();
        $this->info('Moodle audit data ready: '.json_encode($moodle, JSON_UNESCAPED_UNICODE));

        $learning = app(LearningContextBootstrapper::class)->run();
        $this->info('Learning context ready: '.json_encode($learning, JSON_UNESCAPED_UNICODE));

        $topics = app(TopicBootstrapper::class)->run();
        $this->info('Topics ready: '.json_encode($topics, JSON_UNESCAPED_UNICODE));

        $corpus = app(CorpusBuilder::class)->rebuildAll();
        $this->info('Grounded corpus ready: '.json_encode($corpus, JSON_UNESCAPED_UNICODE));

        $groundedPractice = app(GroundedPracticeGenerator::class)->generateAll();
        $this->info('Grounded practice ready: '.json_encode($groundedPractice, JSON_UNESCAPED_UNICODE));

        return 0;
    } finally {
        if ($advisoryLockHeld) {
            DB::select('select pg_advisory_unlock(?)', [$advisoryLockKey]);
        }
    }
})->purpose('Prepare StudyOS database and audited academic data before deployment');

Schedule::command('studyos:sync-ical')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20);
