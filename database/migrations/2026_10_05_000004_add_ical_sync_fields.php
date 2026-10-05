<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_occurrences', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->after('ends_at')->index();
            $table->timestampTz('last_seen_at')->nullable()->after('status');
            $table->timestampTz('cancelled_at')->nullable()->after('last_seen_at');
        });

        Schema::table('sync_connections', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('class_occurrences', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_seen_at', 'cancelled_at']);
        });

        Schema::table('sync_connections', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
