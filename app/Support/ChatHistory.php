<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class ChatHistory
{
    private const CACHE_KEY = 'chat:lobby:history:v1';

    public function recent(?int $limit = null): array
    {
        $limit ??= (int) config('chat.history_limit', 50);
        $messages = Cache::get(self::CACHE_KEY, []);

        if (! is_array($messages)) {
            return [];
        }

        return array_values(array_slice($messages, -max(1, $limit)));
    }

    public function push(array $payload): void
    {
        $limit = max(1, (int) config('chat.history_limit', 50));
        $ttl = now()->addMinutes(max(1, (int) config('chat.history_ttl_minutes', 30)));

        try {
            Cache::lock(self::CACHE_KEY.':lock', 3)->block(2, function () use ($payload, $limit, $ttl): void {
                $messages = Cache::get(self::CACHE_KEY, []);
                $messages = is_array($messages) ? $messages : [];
                $messages[] = $payload;
                $messages = array_values(array_slice($messages, -$limit));
                Cache::put(self::CACHE_KEY, $messages, $ttl);
            });
        } catch (Throwable) {
            // Fall back to a non-atomic update; history must not block message delivery.
            $messages = Cache::get(self::CACHE_KEY, []);
            $messages = is_array($messages) ? $messages : [];
            $messages[] = $payload;
            Cache::put(self::CACHE_KEY, array_values(array_slice($messages, -$limit)), $ttl);
        }
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
