<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->boolean('is_vip')->default(false);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 16)->default('pending')->index();
            $table->string('note', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_products');
    }
};
