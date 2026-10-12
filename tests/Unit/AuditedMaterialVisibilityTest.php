<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Services\Academic\LearningContextBootstrapper;
use Illuminate\Foundation\Testing\TestCase;

class AuditedMaterialVisibilityTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        \App\Models\Course::create(['academic_code' => '14020', 'name' => 'Introdução à Gestão', 'status' => 'active']);
        \App\Models\Course::create(['academic_code' => '10149', 'name' => 'Macroeconomia', 'status' => 'active']);
        \App\Models\Course::create(['academic_code' => '6709', 'name' => 'Contabilidade Financeira I', 'status' => 'active']);
    }

    public function test_reference_without_file_is_not_an_imported_study_material(): void
    {
        app(LearningContextBootstrapper::class)->run();
        $item = Material::where('external_id', 'ig:integrated-material:20261005')->firstOrFail();
        $this->assertSame('reference_only', $item->status);
        $this->assertNull($item->versions()->first()->storage_path);
        $this->assertNull($item->versions()->first()->content_text);
        $this->get('/materials?course_id='.$item->course_id)->assertOk()->assertDontSee('Material integrado atualizado');
        app(LearningContextBootstrapper::class)->run();
        $this->assertSame('reference_only', $item->fresh()->status);
    }
}
