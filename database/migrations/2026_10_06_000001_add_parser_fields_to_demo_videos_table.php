<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_videos', function (Blueprint $table) {
            $table->string('media_type', 16)->default('mp4');
            $table->string('processing_status', 16)->default('ready')->index();
            $table->string('analysis_path')->nullable();
            $table->text('processing_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demo_videos', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['media_type', 'processing_status', 'analysis_path', 'processing_error']);
        });
    }
};