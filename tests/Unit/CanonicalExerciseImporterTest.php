<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\Academic\ConfirmedCurriculum;
use App\Services\Learning\CanonicalExerciseImporter;
use App\Services\Learning\Curriculum;
use ZipArchive;

class CanonicalExerciseImporterTest extends FucCurriculumImporterTest
{
    public function test_exercises_import_into_existing_topics_without_changing_programme(): void
    {
        $course=Course::create(['name'=>'Métodos Quantitativos','academic_code'=>'15436','status'=>'active']);
        app(ConfirmedCurriculum::class)->apply($course);
        $before=Curriculum::topics($course->fresh('topics'))->pluck('id')->all();
        $file=tempnam(sys_get_temp_dir(),'mq-ex-');
        $zip=new ZipArchive();
        $this->assertTrue($zip->open($file,ZipArchive::OVERWRITE) === true);
        $zip->addFromString('manifest.json',json_encode([
            'schema'=>'studyos.canonical-exercises.v1','course'=>['academic_code'=>'15436'],
        ]));
        $zip->addFromString('exercicios.json',json_encode(['items'=>[
            ['id'=>'MQ-1-1-001','curricular_number'=>'1.1','type'=>'numeric',
             'title'=>'Calcular derivada','prompt'=>"Calcule f'(2) para f(x)=x².",
             'reference_answer'=>'4','explanation'=>"f'(x)=2x, logo f'(2)=4.",
             'answer_config'=>['tolerance'=>0.01],'difficulty'=>'basic'],
        ]]));
        $zip->close();
        try {
            $result=app(CanonicalExerciseImporter::class)->import($course,$file);
            $this->assertSame(1,$result['exercises']);
            $this->assertSame($before,Curriculum::topics($course->fresh('topics'))->pluck('id')->all());
            $this->assertDatabaseHas('exercises',['course_id'=>$course->id,'external_id'=>'15436:MQ-1-1-001']);
            $result=app(CanonicalExerciseImporter::class)->import($course,$file);
            $this->assertSame(1,$result['exercises']);
            $this->assertSame(1,$course->exercises()->where('external_id','15436:MQ-1-1-001')->count());
        } finally {
            @unlink($file);
        }
    }
}
