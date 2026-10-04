<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ServerStatus extends Model
{
    public const FRESHNESS_MINUTES = 3;

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

    public static function freshSince(?Carbon $referenceTime = null): Carbon
    {
        return ($referenceTime ?? now())->copy()->subMinutes(self::FRESHNESS_MINUTES);
    }

    public function isFresh(?Carbon $referenceTime = null): bool
    {
        return $this->created_at?->gte(self::freshSince($referenceTime)) ?? false;
    }

    public static function stateOf(?self $status, ?Carbon $referenceTime = null): string
    {
        if ($status === null) {
            return 'unknown';
        }

        if (! $status->isFresh($referenceTime)) {
            return 'stale';
        }

        return $status->online ? 'online' : 'offline';
    }
}
