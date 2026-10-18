<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('external_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 120);
            $table->date('assessed_on');
            $table->decimal('score_20', 5, 2);
            $table->json('report');
            $table->timestamps();
            $table->unique(['course_id', 'external_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('external_assessments'); }
};
