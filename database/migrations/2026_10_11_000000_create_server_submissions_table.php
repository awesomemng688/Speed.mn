<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('game', 10);
            $table->string('category', 30)->nullable();
            $table->string('ip', 45);
            $table->unsignedSmallInteger('port');
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('max_players')->default(32);
            $table->string('note', 500)->nullable();
            $table->string('status', 10)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_submissions');
    }
};
