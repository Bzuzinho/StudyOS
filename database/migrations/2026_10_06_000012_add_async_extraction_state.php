<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_versions', function (Blueprint $table) {
            $table->longText('manual_text')->nullable()->after('content_text');
            $table->timestampTz('extraction_queued_at')->nullable()->after('extraction_error');
            $table->timestampTz('extraction_started_at')->nullable()->after('extraction_queued_at');
            $table->timestampTz('extraction_finished_at')->nullable()->after('extraction_started_at');
            $table->unsignedSmallInteger('extraction_attempts')->default(0)->after('extraction_finished_at');

            $table->index(['extraction_status', 'extraction_queued_at']);
        });
    }

    public function down(): void
    {
        Schema::table('material_versions', function (Blueprint $table) {
            $table->dropIndex(['extraction_status', 'extraction_queued_at']);
            $table->dropColumn([
                'manual_text',
                'extraction_queued_at',
                'extraction_started_at',
                'extraction_finished_at',
                'extraction_attempts',
            ]);
        });
    }
};
