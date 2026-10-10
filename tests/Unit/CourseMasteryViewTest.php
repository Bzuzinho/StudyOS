<?php
namespace Tests\Unit;
use App\Models\Course;
use Illuminate\Foundation\Testing\TestCase;
class CourseMasteryViewTest extends TestCase {
public function createApplication() {
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
return $app;
}
protected function setUp(): void {
parent::setUp();
config(['app.key'=>'base64:'.base64_encode(str_repeat('a',32)), 'session.driver'=>'array', 'database.default'=>'sqlite', 'database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
$this->artisan('migrate',['--force'=>true])->assertExitCode(0);
}
public function test_course_level_is_unknown_without_exercise_evidence(): void {
$c=Course::create(['name'=>'Gestão','status'=>'active']);
$this->get('/courses/'.$c->id)->assertOk()->assertSee('Por avaliar')->assertViewHas('meanMastery',null);
}
}