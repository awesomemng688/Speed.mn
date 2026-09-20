<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('game', ['cs2', 'cs16']);
            $table->string('ip', 45);
            $table->unsignedSmallInteger('port');
            $table->string('region')->nullable();
            $table->string('country', 2)->nullable();
            $table->unsignedSmallInteger('max_players')->default(0);
            $table->string('query_type')->default('a2s');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['game', 'enabled']);
        });

        Schema::create('server_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('online')->default(false);
            $table->unsignedSmallInteger('players')->default(0);
            $table->unsignedSmallInteger('max_players')->default(0);
            $table->string('map')->nullable();
            $table->unsignedInteger('response_time')->nullable();
            $table->json('player_list')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['server_id', 'created_at']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('steam_id', 32)->unique();
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->string('profile_url')->nullable();
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('steam_id', 32)->nullable()->index();
            $table->string('player_name')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['server_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bans');
        Schema::dropIfExists('players');
        Schema::dropIfExists('server_statuses');
        Schema::dropIfExists('servers');
    }
};
