<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    protected $fillable = ['name', 'description', 'price', 'is_vip', 'duration_days', 'active'];

    protected $casts = ['is_vip' => 'boolean', 'active' => 'boolean', 'price' => 'integer'];
}
