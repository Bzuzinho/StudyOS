<?php
namespace Tests\Unit;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\Material;
use App\Models\Topic;
use App\Services\Learning\CurriculumPackageImporter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class CurriculumPackageImporterTest extends TestCase
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
                'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true,
            ],
        ]);
        $this->artisan('migrate', ['--force'=>true])->assertExitCode(0);
    }

    public function test_a_package_imports_all_components_and_reimport_is_idempotent(): void
    {
        $course = Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        $path = tempnam(sys_get_temp_dir(), 'studyos-curriculum');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode([
            'schema'=>'studyos.curriculum-package.proposal.v1',
            'course'=>['academic_code'=>'14020'],
            'themes'=>[['id'=>'IG-01','title'=>'Fundamentos da Gestão','official_fuc'=>true,
                'children'=>[['id'=>'IG-01.01','title'=>'Organizações e gestão',
                    'file'=>'temas/tema-01/subtema-01-01.md']]]],
        ]));
        $zip->addFromString('exercicios.json', json_encode(['items'=>[
            ['id'=>'IG-01.01-Q01','topic_id'=>'IG-01.01','type'=>'true_false','prompt'=>'Uma organização tem objetivos?',
                'reference_answer'=>'verdadeiro'],
        ]]));
        $zip->addFromString('temas/tema-01/subtema-01-01.md',
            str_repeat('A gestão coordena recursos humanos e materiais para alcançar objetivos. ', 12));
        $zip->close();

        try {
            $result=app(CurriculumPackageImporter::class)->import($course,$path);
            $this->assertSame(['themes'=>1,'subtopics'=>1,'exercises'=>1],$result);
            $this->assertSame(2,Topic::where('course_id',$course->id)->count());
            $this->assertSame(1,Exercise::where('course_id',$course->id)->count());
            $this->assertSame('true_false',Exercise::where('course_id',$course->id)->first()->type);
            $this->assertSame(1,Material::where('course_id',$course->id)->count());
            $topic=Topic::where('external_id','14020:IG-01.01')->firstOrFail();
            $this->assertNotNull($topic->parent_id);
            $this->assertNull($topic->taught_at);
            $this->assertGreaterThan(0,$topic->sourceChunks()->count());
            $exercise = Exercise::where('course_id',$course->id)->firstOrFail();
            $exercise->update(['prompt'=>'Versão usada no treino antigo']);
            $runId = \Illuminate\Support\Facades\DB::table('practice_runs')->insertGetId([
                'course_id'=>$course->id, 'exercise_ids'=>json_encode([$exercise->id]), 'responses'=>json_encode([]),
                'current_index'=>0, 'status'=>'active', 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            app(CurriculumPackageImporter::class)->import($course,$path);
            $snapshots = json_decode(\Illuminate\Support\Facades\DB::table('practice_runs')->find($runId)->question_snapshots,true);
            $this->assertSame('Versão usada no treino antigo',$snapshots[$exercise->id]['prompt']);
            $this->assertSame('Uma organização tem objetivos?',$exercise->fresh()->prompt);
            $this->assertSame(2,Topic::where('course_id',$course->id)->count());
            $this->assertSame(1,Exercise::where('course_id',$course->id)->count());
            $this->assertSame(1,Material::where('course_id',$course->id)->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_append_preserves_omitted_records_and_replace_deactivates_only_package_records(): void
    {
        $course = Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        $other = Topic::create(['course_id'=>$course->id,'source'=>'manual','external_id'=>'manual-keep','title'=>'Notas pessoais','position'=>9,'status'=>'active']);
        $extra = Topic::create(['course_id'=>$course->id,'source'=>'chatgpt_package','external_id'=>'14020:IG-01.02',
            'title'=>'Subtema anterior','position'=>2,'status'=>'active']);
        $exercise = Exercise::create(['course_id'=>$course->id,'source'=>'chatgpt_package','external_id'=>'14020:OLD-Q01',
            'title'=>'Pergunta antiga','prompt'=>'Questão antiga?','expected_answer'=>'Resposta','type'=>'open_text','status'=>'active']);
        $material = Material::create(['course_id'=>$course->id,'source'=>'chatgpt_package','external_id'=>'14020:IG-01.02',
            'title'=>'Manual antigo','type'=>'document','status'=>'active']);
        $moodle = Material::create(['course_id'=>$course->id,'source'=>'moodle','external_id'=>'moodle-keep',
            'title'=>'Diapositivos','type'=>'document','status'=>'active']);
        $path = tempnam(sys_get_temp_dir(), 'studyos-mode');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['schema'=>'studyos.curriculum-package.proposal.v1',
            'course'=>['academic_code'=>'14020'], 'themes'=>[['id'=>'IG-01','title'=>'Fundamentos',
            'children'=>[['id'=>'IG-01.01','title'=>'Gestão','file'=>'temas/tema-01/subtema-01-01.md']]]]]));
        $zip->addFromString('exercicios.json', json_encode(['items'=>[]]));
        $zip->addFromString('temas/tema-01/subtema-01-01.md', str_repeat('A gestão articula recursos e objetivos. ', 15));
        $zip->close();
        try {
            app(CurriculumPackageImporter::class)->import($course,$path,'append');
            $this->assertSame('active',$extra->fresh()->status);
            $this->assertSame('active',$exercise->fresh()->status);
            $this->assertSame('active',$material->fresh()->status);
            app(CurriculumPackageImporter::class)->import($course,$path,'replace');
            $this->assertSame('inactive',$extra->fresh()->status);
            $this->assertSame('inactive',$exercise->fresh()->status);
            $this->assertSame('inactive',$material->fresh()->status);
            $this->assertSame('active',$other->fresh()->status);
            $this->assertSame('active',$moodle->fresh()->status);
            $this->assertSame('active', Topic::where('external_id','14020:IG-01.01')->firstOrFail()->status);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_a_package_for_the_wrong_course_before_writing(): void
    {
        $course=Course::create(['academic_code'=>'99999','name'=>'Outra UC','status'=>'active']);
        $path=tempnam(sys_get_temp_dir(),'studyos-invalid');
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json',json_encode(['schema'=>'studyos.curriculum-package.proposal.v1',
            'course'=>['academic_code'=>'14020'],'themes'=>[]]));
        $zip->addFromString('exercicios.json',json_encode(['items'=>[]]));
        $zip->close();
        try {
            $this->expectException(\RuntimeException::class);
            app(CurriculumPackageImporter::class)->import($course,$path);
        } finally {
            @unlink($path);
        }
    }
}
