<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSubmission extends Model
{
    protected $fillable = ['user_id', 'name', 'game', 'category', 'ip', 'port', 'region', 'max_players', 'note', 'status', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
