<?php

use App\Models\MaterialVersion;
use App\Models\SyncConnection;
use App\Services\Academic\AcademicCatalogBootstrapper;
use App\Services\Academic\AssessmentCalendarBootstrapper;
use App\Services\Academic\LearningContextBootstrapper;
use App\Services\Academic\MoodleAuditBootstrapper;
use App\Services\Academic\TopicBootstrapper;
use App\Services\Academic\SourceBackedTopicBuilder;
use App\Services\Academic\FucCurriculumImporter;
use App\Services\Academic\SlideCurriculumImporter;
use App\Models\Course;
use App\Services\Calendar\ICalendarSyncService;
use App\Services\Learning\CorpusBuilder;
use App\Services\Moodle\MoodleAuthenticatedClient;
use App\Services\Moodle\MoodleSyncService;
use App\Services\Moodle\MoodleWebServiceClient;
use App\Services\Moodle\MoodleWebServiceSyncService;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('studyos:status', function () {
    $this->info('StudyOS operational.');
})->purpose('Show StudyOS application status');

Artisan::command('studyos:queue-status', function () {
    $this->info('Queue: '.json_encode([
        'pending_jobs' => DB::table('jobs')->count(),
        'failed_jobs' => DB::table('failed_jobs')->count(),
        'files_queued' => MaterialVersion::query()->where('extraction_status', 'queued')->count(),
        'files_processing' => MaterialVersion::query()->where('extraction_status', 'processing')->count(),
        'files_failed' => MaterialVersion::query()->where('extraction_status', 'failed')->count(),
    ], JSON_UNESCAPED_UNICODE));

    return 0;
})->purpose('Show background academic document extraction queue status');

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

Artisan::command('studyos:build-source-topics', function () {
    $stats = ['courses' => 0, 'created' => 0, 'skipped' => 0];
    $builder = app(SourceBackedTopicBuilder::class);
    Course::query()->where('status', 'active')->each(function (Course $course) use (&$stats, $builder) {
        $result = $builder->build($course);
        $stats['courses']++;
        $stats['created'] += $result['created'];
        $stats['skipped'] += $result['skipped'];
    });
    $this->info('Source-backed provisional topics: '.json_encode($stats, JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Derive provisional topics only from observed academic materials and summaries');

Artisan::command('studyos:import-fuc', function () {
    $stats = ['scanned' => 0, 'recognized' => 0, 'created' => 0, 'updated' => 0];
    MaterialVersion::query()
        ->whereNotNull('content_text')
        ->whereHas('material', fn ($query) => $query
            ->where('title', 'like', '%FUC%')
            ->orWhere('title', 'like', '%Programa%'))
        ->with('material')
        ->chunkById(100, function ($versions) use (&$stats) {
            foreach ($versions as $version) {
                $result = app(FucCurriculumImporter::class)->import($version);
                $stats['scanned']++;
                $stats['recognized'] += (int) $result['recognized'];
                $stats['created'] += $result['created'];
                $stats['updated'] += $result['updated'];
            }
        });
    $this->info('Official FUC curricula: '.json_encode($stats, JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Import hierarchical UC programme from already extracted official FUC materials');

Artisan::command('studyos:import-slides', function () {
    $stats = ['scanned' => 0, 'created' => 0, 'updated' => 0];
    MaterialVersion::query()->whereNotNull('content_text')->whereNotNull('original_filename')
        ->where(function ($q) { $q->where('original_filename', 'like', '%.pptx')->orWhere('original_filename', 'like', '%.pdf'); })->with('material')
        ->chunkById(100, function ($versions) use (&$stats) {
            foreach ($versions as $version) {
                $result = app(SlideCurriculumImporter::class)->import($version);
                $stats['scanned']++;
                $stats['created'] += $result['created'];
                $stats['updated'] += $result['updated'];
            }
        });
    $this->info('Slide topics: '.json_encode($stats, JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Index slides from already-extracted Moodle PowerPoint files');

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

Artisan::command('studyos:sync-moodle', function () {
    if (! config('studyos.moodle.enabled', true)) {
        $this->warn('Moodle synchronization is disabled.');

        return 0;
    }

    $webServiceClient = app(MoodleWebServiceClient::class);

    if ($webServiceClient->isConfigured()) {
        $connection = SyncConnection::query()->firstOrCreate(
            ['source' => 'moodle_webservice', 'name' => 'Moodle EAD 2026/27 · Microsoft SSO'],
            [
                'secret' => null,
                'config' => [
                    'base_url' => config('studyos.moodle.base_url'),
                    'read_only' => true,
                    'auth_mode' => 'microsoft_sso_moodle_token',
                    'credentials_source' => 'environment_secret',
                ],
                'status' => 'pending',
                'enabled' => true,
            ],
        );

        $run = app(MoodleWebServiceSyncService::class)->sync($connection);

        if (in_array($run->status, ['success', 'success_with_warnings'], true)) {
            $this->info('Moodle SSO sync '.strtoupper($run->status).': '.json_encode($run->stats, JSON_UNESCAPED_UNICODE));

            return 0;
        }

        $this->error($run->error ?: 'Moodle SSO synchronization failed.');

        return 1;
    }

    // Fallback retained only for Moodle accounts that genuinely use a local password.
    $legacyClient = app(MoodleAuthenticatedClient::class);

    if ($legacyClient->isConfigured()) {
        $connection = SyncConnection::query()->firstOrCreate(
            ['source' => 'moodle_authenticated', 'name' => 'Moodle EAD 2026/27 · local login'],
            [
                'secret' => null,
                'config' => [
                    'base_url' => config('studyos.moodle.base_url'),
                    'read_only' => true,
                    'credentials_source' => 'environment_secret',
                ],
                'status' => 'pending',
                'enabled' => true,
            ],
        );

        $run = app(MoodleSyncService::class)->sync($connection);

        if (in_array($run->status, ['success', 'success_with_warnings'], true)) {
            $this->info('Moodle legacy sync '.strtoupper($run->status).': '.json_encode($run->stats, JSON_UNESCAPED_UNICODE));

            return 0;
        }

        $this->error($run->error ?: 'Moodle legacy synchronization failed.');

        return 1;
    }

    $connection = SyncConnection::query()->firstOrCreate(
        ['source' => 'moodle_webservice', 'name' => 'Moodle EAD 2026/27 · Microsoft SSO'],
        [
            'secret' => null,
            'config' => [
                'base_url' => config('studyos.moodle.base_url'),
                'read_only' => true,
                'auth_mode' => 'microsoft_sso_moodle_token',
                'credentials_source' => 'environment_secret',
            ],
            'status' => 'pending_microsoft_sso',
            'enabled' => true,
        ],
    );

    $connection->update(['status' => 'pending_microsoft_sso']);
    $this->warn('Moodle synchronization is waiting for a one-time Microsoft/ULO SSO token.');

    return 0;
})->purpose('Synchronize Moodle documents through read-only Microsoft/ULO SSO');

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

Artisan::command('studyos:deploy-prepare {--rebuild-learning : Rebuild all source chunks and grounded practice}', function () {
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

        $this->call('studyos:build-source-topics');
        $this->call('studyos:import-fuc');
        $this->call('studyos:import-slides');

        // Imported documents already rebuild their own corpus in the worker.
        // Reprocessing every document here can exhaust Railway's pre-deploy timeout.
        if ($this->option('rebuild-learning')) {
            $corpus = app(CorpusBuilder::class)->rebuildAll();
            $this->info('Grounded corpus ready: '.json_encode($corpus, JSON_UNESCAPED_UNICODE));

            $groundedPractice = app(GroundedPracticeGenerator::class)->generateAll();
            $this->info('Grounded practice ready: '.json_encode($groundedPractice, JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Existing learning corpus preserved; document updates are processed by the worker.');
        }

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
