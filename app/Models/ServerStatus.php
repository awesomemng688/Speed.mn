<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id', 'online', 'players', 'max_players', 'bots', 'vac', 'map', 'response_time', 'player_list', 'version', 'created_at',
    ];

    protected $casts = [
        'online' => 'boolean',
        'vac' => 'boolean',
        'player_list' => 'array',
        'created_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
