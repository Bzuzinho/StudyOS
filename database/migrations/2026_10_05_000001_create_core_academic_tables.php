<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        Schema::create('courses',function(Blueprint $t){$t->id();$t->string('name');$t->string('academic_code')->nullable()->index();$t->unsignedTinyInteger('semester')->nullable();$t->unsignedTinyInteger('ects')->nullable();$t->string('status')->default('active');$t->timestamps();});
        Schema::create('source_courses',function(Blueprint $t){$t->id();$t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();$t->string('source');$t->string('external_id');$t->string('external_name');$t->json('metadata')->nullable();$t->timestamps();$t->unique(['source','external_id']);});
        Schema::create('class_occurrences',function(Blueprint $t){$t->id();$t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();$t->string('source');$t->string('external_uid')->nullable();$t->string('title');$t->string('location')->nullable();$t->dateTimeTz('starts_at');$t->dateTimeTz('ends_at')->nullable();$t->json('source_payload')->nullable();$t->timestamps();$t->unique(['source','external_uid']);});
        Schema::create('assessments',function(Blueprint $t){$t->id();$t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();$t->string('source');$t->string('external_id')->nullable();$t->string('type')->default('assessment');$t->string('title');$t->dateTimeTz('opens_at')->nullable();$t->dateTimeTz('due_at')->nullable();$t->decimal('weight',5,2)->nullable();$t->boolean('confirmed')->default(false);$t->json('metadata')->nullable();$t->timestamps();$t->index(['course_id','due_at']);});
    }
    public function down():void{Schema::dropIfExists('assessments');Schema::dropIfExists('class_occurrences');Schema::dropIfExists('source_courses');Schema::dropIfExists('courses');}
};
