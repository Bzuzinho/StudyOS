<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_versions', function (Blueprint $table) {
            $table->longText('content_text')->nullable()->after('version_label');
        });

        Schema::create('source_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_summary_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->string('title')->nullable();
            $table->string('locator')->nullable();
            $table->text('content');
            $table->string('content_hash', 64);
            $table->string('quality')->default('outline_only');
            $table->string('status')->default('active');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['material_version_id', 'ordinal', 'content_hash'],
                'source_chunks_material_ordinal_hash_unique',
            );
            $table->unique(
                ['lesson_summary_id', 'ordinal', 'content_hash'],
                'source_chunks_summary_ordinal_hash_unique',
            );
            $table->index(['course_id', 'status', 'quality']);
        });

        Schema::create('source_chunk_topic', function (Blueprint $table) {
            $table->foreignId('source_chunk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('match_method')->default('exact_title');
            $table->decimal('confidence', 4, 3)->default(1);
            $table->primary(['source_chunk_id', 'topic_id']);
        });

        Schema::create('exercise_source_chunk', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_chunk_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('reference');
            $table->primary(['exercise_id', 'source_chunk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_source_chunk');
        Schema::dropIfExists('source_chunk_topic');
        Schema::dropIfExists('source_chunks');

        Schema::table('material_versions', function (Blueprint $table) {
            $table->dropColumn('content_text');
        });
    }
};
