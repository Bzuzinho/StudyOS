<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_versions', function (Blueprint $table) {
            $table->string('storage_disk')->nullable()->after('content_text');
            $table->text('storage_path')->nullable()->after('storage_disk');
            $table->string('original_filename')->nullable()->after('storage_path');
            $table->string('mime_type')->nullable()->after('original_filename');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->string('file_sha256', 64)->nullable()->after('size_bytes');
            $table->string('extraction_status')->default('not_applicable')->after('file_sha256');
            $table->text('extraction_error')->nullable()->after('extraction_status');

            $table->index(['extraction_status', 'observed_at']);
            $table->index('file_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('material_versions', function (Blueprint $table) {
            $table->dropIndex(['extraction_status', 'observed_at']);
            $table->dropIndex(['file_sha256']);
            $table->dropColumn([
                'storage_disk',
                'storage_path',
                'original_filename',
                'mime_type',
                'size_bytes',
                'file_sha256',
                'extraction_status',
                'extraction_error',
            ]);
        });
    }
};
