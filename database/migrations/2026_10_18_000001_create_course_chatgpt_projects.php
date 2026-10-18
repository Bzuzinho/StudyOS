<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
   Schema::create('course_chatgpt_projects', function(Blueprint $t){
     $t->id(); $t->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
     $t->string('project_url', 500); $t->timestamps();
   });
 }
 public function down(): void { Schema::dropIfExists('course_chatgpt_projects'); }
};