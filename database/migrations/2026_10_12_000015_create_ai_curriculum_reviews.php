<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_curriculum_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('evidence_hash', 64);
            $table->string('model');
            $table->json('output');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_usd', 10, 6)->default(0);
            $table->timestamps();
            $table->index(['course_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ai_curriculum_reviews');
    }
};