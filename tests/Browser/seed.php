<?php
// Isolated SQLite fixture used only by the browser CI job.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$course = \App\Models\Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','semester'=>1,'status'=>'active']);
for ($i=1;$i<=181;$i++) $course->topics()->create(['source'=>'manual','title'=>'Histórico antigo '.$i,'status'=>'active','taught_at'=>now()]);
for ($theme=1;$theme<=9;$theme++) {
    $parent = $course->topics()->create(['source'=>'chatgpt_package','external_id'=>'14020:T'.$theme,'title'=>'Tema '.$theme.' — Fundamentos da Gestão','position'=>$theme,'status'=>'active']);
    for ($n=1;$n<=($theme<=6?4:3);$n++) {
        $topic=$course->topics()->create(['source'=>'chatgpt_package','external_id'=>'14020:T'.$theme.'.'.$n,'parent_id'=>$parent->id,'title'=>$theme.'.'.$n.' — Organizações, competências e funções da gestão','position'=>$n,'status'=>'active','taught_at'=>$theme===1?now():null]);
        $material=$course->materials()->create(['source'=>'chatgpt_package','external_id'=>$topic->external_id,'title'=>'Manual do subtema','type'=>'document','status'=>'active']);
        $material->versions()->create(['source_hash'=>hash('sha256',$topic->external_id),'content_text'=>"# Organizações\n\nEste é o manual do subtema.",'extraction_status'=>'manual_text','observed_at'=>now()]);
        for($q=1;$q<=8;$q++) {
            $ex=$course->exercises()->create(['source'=>'chatgpt_package','external_id'=>$topic->external_id.':Q'.$q,'type'=>'true_false','prompt'=>'Uma organização pode ter vários objetivos?','expected_answer'=>'verdadeiro','explanation'=>'As organizações coordenam recursos e pessoas.','max_points'=>1,'status'=>'active','metadata'=>['difficulty'=>'medio']]);
            $ex->topics()->attach($topic);
        }
    }
}
