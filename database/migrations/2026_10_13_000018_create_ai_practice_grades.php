<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('ai_practice_grades', function(Blueprint $table) {
   $table->id(); $table->unsignedInteger('input_tokens')->default(0);
   $table->unsignedInteger('output_tokens')->default(0);
   $table->decimal('estimated_usd',10,6)->default(0); $table->timestamps();
  });
 }
 public function down():void { Schema::dropIfExists('ai_practice_grades'); }
};