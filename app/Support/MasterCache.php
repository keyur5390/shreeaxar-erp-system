<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MasterCache
{
    public const TTL = 300;

    public static function remember(string $key, callable $callback): mixed
    {
        if (self::supportsTags()) {
            return Cache::tags(['masters'])->remember($key, self::TTL, $callback);
        }

        return Cache::remember($key, self::TTL, $callback);
    }

    public static function forget(string $key): void
    {
        if (self::supportsTags()) {
            Cache::tags(['masters'])->forget($key);

            return;
        }

        Cache::forget($key);
    }

    private static function supportsTags(): bool
    {
        return config('cache.default') === 'redis'
            && method_exists(Cache::getStore(), 'tags');
    }
}
