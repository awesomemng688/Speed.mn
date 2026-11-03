<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipMember extends Model
{
    protected $fillable = ['steam_id', 'name', 'plan', 'amount', 'starts_at', 'expires_at', 'note'];

    protected $casts = ['starts_at' => 'date', 'expires_at' => 'date'];

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->endOfDay()->isFuture();
    }
}
