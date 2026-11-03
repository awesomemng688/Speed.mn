<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_products');

        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->where('key', 'shop_bank_info')->delete();
            Cache::forget('site.settings');
        }

        Storage::disk('public')->deleteDirectory('shop');
        Storage::disk('local')->deleteDirectory('shop-receipts');
    }

    public function down(): void
    {
        // The shop was removed permanently; its data cannot be restored.
    }
};
