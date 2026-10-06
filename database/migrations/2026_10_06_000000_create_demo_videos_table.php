<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('original_filename', 255);
            $table->string('file_path');
            $table->string('map', 64)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['recorded_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_videos');
    }
};