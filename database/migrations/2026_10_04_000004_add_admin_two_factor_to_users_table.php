<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('admin_totp_secret')->nullable();
            $table->timestamp('admin_totp_confirmed_at')->nullable();
            $table->text('admin_totp_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_totp_secret', 'admin_totp_confirmed_at', 'admin_totp_recovery_codes']);
        });
    }
};