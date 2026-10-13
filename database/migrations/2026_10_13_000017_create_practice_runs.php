<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('practice_runs', function(Blueprint $t) {
   $t->id();
   $t->foreignId('course_id')->constrained()->cascadeOnDelete();
   $t->foreignId('study_session_id')->nullable()->constrained()->nullOnDelete();
   $t->json('exercise_ids');
   $t->json('responses')->nullable();
   $t->unsignedSmallInteger('current_index')->default(0);
   $t->string('status')->default('active');
   $t->timestamp('completed_at')->nullable();
   $t->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('practice_runs'); }
};