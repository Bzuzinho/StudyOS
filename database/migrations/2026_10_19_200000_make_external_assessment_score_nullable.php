<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_assessments', function (Blueprint $table) {
            $table->decimal('score_20', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('external_assessments', function (Blueprint $table) {
            $table->decimal('score_20', 5, 2)->nullable(false)->change();
        });
    }
};
