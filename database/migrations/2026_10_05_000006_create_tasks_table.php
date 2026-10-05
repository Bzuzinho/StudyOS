<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('type')->default('task');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTimeTz('opens_at')->nullable();
            $table->dateTimeTz('due_at')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('confirmed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'tasks_source_external_unique');
            $table->index(['course_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
