<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrder extends Model
{
    public const STATUSES = ['pending' => 'Хүлээгдэж байна', 'paid' => 'Баталгаажсан', 'cancelled' => 'Цуцлагдсан'];

    protected $fillable = ['user_id', 'shop_product_id', 'code', 'amount', 'status', 'note', 'details', 'receipt_path', 'paid_at'];

    protected $casts = ['paid_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }
}
