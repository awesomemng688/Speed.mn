<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['type', 'title', 'slug', 'excerpt', 'body', 'image_url', 'event_at', 'is_published', 'published_at', 'tournament_reminder_sent_at'];

    protected $casts = ['event_at' => 'datetime', 'published_at' => 'datetime', 'tournament_reminder_sent_at' => 'datetime', 'is_published' => 'boolean'];

    public const TYPES = ['news' => 'Мэдээ', 'tournament' => 'Тэмцээн'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
