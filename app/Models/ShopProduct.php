<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShopProduct extends Model
{
    public const CATEGORIES = [
        'vip' => 'VIP',
        'cs2_model' => 'CS2 model',
        'cs16_model' => 'CS 1.6 model',
        'knife' => 'Хутга',
        'other' => 'Бусад',
    ];

    protected $fillable = ['name', 'description', 'category', 'image_path', 'gallery', 'price', 'stock', 'is_vip', 'duration_days', 'active'];

    protected $casts = ['is_vip' => 'boolean', 'active' => 'boolean', 'price' => 'integer', 'stock' => 'integer', 'gallery' => 'array'];

    public function inStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function galleryUrls(): array
    {
        return collect($this->gallery ?? [])->map(fn (string $path) => Storage::disk('public')->url($path))->all();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
