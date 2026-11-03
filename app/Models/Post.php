<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Post extends Model
{
    protected $fillable = ['type', 'title', 'slug', 'excerpt', 'body', 'image_url', 'event_at', 'is_published', 'published_at'];

    protected $casts = ['event_at' => 'datetime', 'published_at' => 'datetime', 'is_published' => 'boolean'];

    public const TYPES = ['news' => 'Мэдээ', 'tournament' => 'Тэмцээн'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
