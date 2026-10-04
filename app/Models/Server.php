<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'name', 'game', 'category', 'ip', 'port', 'region', 'country', 'max_players', 'query_type', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_polled_at' => 'datetime',
        'last_successful_poll_at' => 'datetime',
    ];

    public function statuses(): HasMany
    {
        return $this->hasMany(ServerStatus::class);
    }

    public function latestStatus(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ServerStatus::class)->latestOfMany();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'server_favorites')->withTimestamps();
    }

    public function getAddressAttribute(): string
    {
        return "{$this->ip}:{$this->port}";
    }
}
