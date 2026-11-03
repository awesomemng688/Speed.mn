<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->nullable()->after('price');
            $table->json('gallery')->nullable()->after('image_path');
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->string('receipt_path')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn('receipt_path');
        });
        Schema::table('shop_products', function (Blueprint $table): void {
            $table->dropColumn(['stock', 'gallery']);
        });
    }
};
