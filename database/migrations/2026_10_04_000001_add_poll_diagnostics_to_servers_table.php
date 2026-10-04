<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('last_polled_at')->nullable()->index();
            $table->timestamp('last_successful_poll_at')->nullable();
            $table->text('last_query_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['last_polled_at']);
            $table->dropColumn(['last_polled_at', 'last_successful_poll_at', 'last_query_error']);
        });
    }
};