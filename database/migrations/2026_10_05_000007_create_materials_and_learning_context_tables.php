<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('type')->default('document');
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('status')->default('active');
            $table->timestampTz('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'materials_source_external_unique');
            $table->index(['course_id', 'status']);
        });

        Schema::create('material_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('source_hash');
            $table->string('version_label')->nullable();
            $table->timestampTz('observed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'source_hash']);
        });

        Schema::create('lesson_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('content');
            $table->timestampTz('occurred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'lesson_summaries_source_external_unique');
            $table->index(['course_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_summaries');
        Schema::dropIfExists('material_versions');
        Schema::dropIfExists('materials');
    }
};
