<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Cache key prefix.
     */
    protected const CACHE_PREFIX = 'system_setting_';

    /**
     * Get a setting value by key with caching.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX . $key, function () use ($key, $default) {
            try {
                $setting = static::where('key', $key)->first();
                return $setting ? $setting->value : $default;
            } catch (\Throwable $e) {
                return $default;
            }
        });
    }

    /**
     * Set/update a setting value and invalidate cache.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Helper to get logo URL with fallback to default theme assets.
     */
    public static function getLogo(string $type = 'normal'): string
    {
        $settingKey = match ($type) {
            'small' => 'site_logo_small',
            'dark' => 'site_logo_dark',
            default => 'site_logo',
        };

        $fallbackAsset = match ($type) {
            'small' => 'assets/img/logo-small.svg',
            'dark' => 'assets/img/logo-white.svg',
            default => 'assets/img/logo.svg',
        };

        $path = static::get($settingKey);

        if (! empty($path)) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            $cleanPath = ltrim($path, '/');
            if (app()->runningInConsole()) {
                return asset('storage/' . $cleanPath);
            }

            return request()->getBaseUrl() . '/storage/' . $cleanPath;
        }

        return asset($fallbackAsset);
    }

    /**
     * Helper to get favicon URL with fallback.
     */
    public static function getFavicon(): string
    {
        $path = static::get('site_favicon');

        if (! empty($path)) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            $cleanPath = ltrim($path, '/');
            if (app()->runningInConsole()) {
                return asset('storage/' . $cleanPath);
            }

            return request()->getBaseUrl() . '/storage/' . $cleanPath;
        }

        return asset('assets/img/favicon.png');
    }

    /**
     * Helper to get site name with fallback.
     */
    public static function getSiteName(): string
    {
        return static::get('site_name', 'PDMS');
    }
}
