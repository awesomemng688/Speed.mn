<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('participant_name', 80);
            $table->string('team_name', 80)->nullable();
            $table->string('contact', 100)->nullable();
            $table->string('status', 20)->default('registered');
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('server_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 300)->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'user_id']);
            $table->index(['server_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_ratings');
        Schema::dropIfExists('tournament_registrations');
    }
};
