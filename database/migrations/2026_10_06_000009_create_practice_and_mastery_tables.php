<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');
            $table->string('external_id');
            $table->string('type')->default('single_answer');
            $table->string('title')->nullable();
            $table->text('prompt');
            $table->text('expected_answer')->nullable();
            $table->json('answer_config')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('max_points', 8, 2)->default(1);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'exercises_source_external_unique');
            $table->index(['course_id', 'status', 'type']);
        });

        Schema::create('exercise_topic', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['exercise_id', 'topic_id']);
        });

        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_session_id')->nullable()->constrained()->nullOnDelete();
            $table->text('submitted_answer');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_points', 8, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grading_status')->default('pending_review');
            $table->string('grading_method')->default('pending');
            $table->text('feedback')->nullable();
            $table->timestampTz('attempted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['exercise_id', 'attempted_at']);
            $table->index(['grading_status', 'attempted_at']);
        });

        Schema::create('topic_masteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('evidence_attempts')->default(0);
            $table->unsignedInteger('evidence_exercises')->default(0);
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->string('status')->default('no_evidence');
            $table->timestampTz('last_practiced_at')->nullable();
            $table->timestampTz('calculated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_masteries');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('exercise_topic');
        Schema::dropIfExists('exercises');
    }
};
