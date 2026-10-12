<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_topic_match_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_chunk_id');
            $table->unsignedInteger('fragments_processed');
            $table->unsignedInteger('matches_created');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_usd', 10, 6)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ai_topic_match_runs'); }
};