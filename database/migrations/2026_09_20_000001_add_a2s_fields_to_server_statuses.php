<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_statuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('bots')->nullable()->after('max_players');
            $table->boolean('vac')->nullable()->after('bots');
        });
    }

    public function down(): void
    {
        Schema::table('server_statuses', function (Blueprint $table) {
            $table->dropColumn(['bots', 'vac']);
        });
    }
};
