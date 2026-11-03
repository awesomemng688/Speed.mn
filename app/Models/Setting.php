<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'site_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'discord_url' => 'https://discord.gg/93XEkJDD6',
        'hero_title' => 'Хурдан тогло.',
        'hero_accent' => 'Хамтдаа тогло.',
        'hero_lead' => 'CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус, чансаа, skin хэрэгслүүд нэг дор.',
        'banner_enabled' => '0',
        'banner_text' => '',
        'banner_url' => '',
    ];

    public static function value(string $key, ?string $default = null): ?string
    {
        $value = self::cached()[$key] ?? null;

        return ($value !== null && $value !== '') ? $value : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('site.settings');
    }

    private static function cached(): array
    {
        try {
            return Cache::remember('site.settings', 300, fn () => self::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return [];
        }
    }
}
