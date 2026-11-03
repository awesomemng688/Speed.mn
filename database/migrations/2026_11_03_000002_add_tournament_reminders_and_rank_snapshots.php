<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('tournament_reminder_sent_at')->nullable();
        });

        Schema::create('rank_progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40);
            $table->unsignedInteger('level');
            $table->timestamps();
            $table->unique(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_progress_snapshots');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('tournament_reminder_sent_at');
        });
    }
};
