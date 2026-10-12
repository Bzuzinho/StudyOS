<?php
namespace Tests\Unit;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\SourceChunk;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

class AiTopicMatcherTest extends TestCase
{
    public function createApplication()
    {
        $app=require dirname(__DIR__,2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
    protected function setUp():void
    {
        parent::setUp();
        config([
            'app.key'=>'base64:'.base64_encode(str_repeat('a',32)),
            'session.driver'=>'array',
            'database.default'=>'sqlite',
            'database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
            'studyos_ai.enabled'=>true,'studyos_ai.api_key'=>'dummy',
            'studyos_ai.access_code'=>'authorized','studyos_ai.monthly_budget_usd'=>10,
        ]);
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
    }
    public function test_one_document_can_support_multiple_topics():void
    {
        $course=Course::create(['name'=>'Gestão','status'=>'active']);
        $a=Topic::create(['course_id'=>$course->id,'title'=>'Planeamento','source'=>'manual','status'=>'active']);
        $b=Topic::create(['course_id'=>$course->id,'title'=>'Controlo','source'=>'manual','status'=>'active']);
        $material=Material::create(['course_id'=>$course->id,'source'=>'manual','title'=>'Apresentação conjunta','status'=>'active']);
        $version=MaterialVersion::create(['material_id'=>$material->id,'source_hash'=>'testhash','observed_at'=>now()]);
        $chunk=SourceChunk::create(['course_id'=>$course->id,'material_version_id'=>$version->id,'ordinal'=>1,
            'title'=>'Aula','content'=>'O processo de gestão abrange decisões sobre objetivos e acompanhamento de resultados.',
            'content_hash'=>hash('sha256','unique'),'status'=>'active','quality'=>'content']);
        Http::fake(['api.openai.com/*'=>Http::response([
            'choices'=>[['message'=>['content'=>json_encode(['links'=>[
                ['chunk_id'=>$chunk->id,'topic_ids'=>[$a->id,$b->id,99999]]
            ]])]]],'usage'=>['prompt_tokens'=>200,'completion_tokens'=>50],
        ],200)]);
        $url='/ai/courses/'.$course->id.'/match-materials';
        $this->post($url,['access_code'=>'wrong'])->assertForbidden();
        Http::assertNothingSent();
        $this->post($url,['access_code'=>'authorized'])->assertRedirect();
        $this->assertSame(2,$chunk->topics()->count());
        $this->assertNull($a->fresh()->taught_at);
        $this->assertDatabaseCount('ai_topic_match_runs',1);
        $this->post($url,['access_code'=>'authorized'])->assertRedirect();
        Http::assertSentCount(1);
    }
}
