<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_products', function (Blueprint $table): void {
            $table->string('category', 20)->default('other')->index()->after('description');
            $table->string('image_path')->nullable()->after('category');
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->string('details', 255)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
        Schema::table('shop_products', function (Blueprint $table): void {
            $table->dropColumn(['category', 'image_path']);
        });
    }
};
