<?php

namespace Tests\Unit;

use App\Services\Academic\AcademicCatalogBootstrapper;
use App\Services\Academic\AssessmentCalendarBootstrapper;
use App\Services\Academic\LearningContextBootstrapper;
use App\Services\Academic\MoodleAuditBootstrapper;
use App\Services\Academic\TopicBootstrapper;
use App\Services\Learning\CorpusBuilder;
use App\Services\Practice\GroundedPracticeGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Mockery;

class DeploymentPreparationTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'filesystems.default' => 'local',
            'studyos.calendar.inforestudante_url' => null,
        ]);

        foreach ([AcademicCatalogBootstrapper::class, AssessmentCalendarBootstrapper::class,
            MoodleAuditBootstrapper::class, LearningContextBootstrapper::class, TopicBootstrapper::class] as $service) {
            $mock = Mockery::mock($service);
            $mock->shouldReceive('run')->once()->andReturn([]);
            $this->app->instance($service, $mock);
        }
    }

    public function test_a_normal_deployment_finishes_without_reprocessing_imported_documents(): void
    {
        $corpus = Mockery::mock(CorpusBuilder::class);
        $corpus->shouldNotReceive('rebuildAll');
        $generator = Mockery::mock(GroundedPracticeGenerator::class);
        $generator->shouldNotReceive('generateAll');
        $this->app->instance(CorpusBuilder::class, $corpus);
        $this->app->instance(GroundedPracticeGenerator::class, $generator);

        $this->artisan('studyos:deploy-prepare')->assertExitCode(0);
    }

    public function test_a_full_learning_rebuild_remains_available_when_requested(): void
    {
        $corpus = Mockery::mock(CorpusBuilder::class);
        $corpus->shouldReceive('rebuildAll')->once()->andReturn([]);
        $generator = Mockery::mock(GroundedPracticeGenerator::class);
        $generator->shouldReceive('generateAll')->once()->andReturn([]);
        $this->app->instance(CorpusBuilder::class, $corpus);
        $this->app->instance(GroundedPracticeGenerator::class, $generator);

        $this->artisan('studyos:deploy-prepare', ['--rebuild-learning' => true])->assertExitCode(0);
    }
}
