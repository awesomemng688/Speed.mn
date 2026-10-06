<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoVideo extends Model
{
    protected $fillable = [
        'title', 'original_filename', 'file_path', 'map', 'recorded_at', 'uploaded_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}