<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'topics_source_external_unique');
            $table->index(['course_id', 'status', 'position']);
        });

        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');
            $table->uuid('external_id')->unique();
            $table->string('type')->default('study');
            $table->string('title')->nullable();
            $table->timestampTz('starts_at');
            $table->unsignedSmallInteger('planned_minutes');
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'starts_at', 'status']);
        });

        Schema::create('study_session_topic', function (Blueprint $table) {
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['study_session_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_session_topic');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('topics');
    }
};
