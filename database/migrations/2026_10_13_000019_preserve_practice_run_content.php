<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('practice_runs', function (Blueprint $table) {
            $table->json('settings')->nullable();
            $table->json('question_snapshots')->nullable();
        });
        // Rebuild only derived mastery caches previously influenced by provisional AI grades.
        \App\Models\Topic::whereHas('exercises.attempts', fn ($q) => $q->where('grading_method', 'ai_rubric_provisional'))
            ->each(fn ($topic) => app(\App\Services\Practice\TopicMasteryService::class)->recalculate($topic));
    }
    public function down(): void {
        Schema::table('practice_runs', fn (Blueprint $table) => $table->dropColumn(['settings', 'question_snapshots']));
    }
};
