<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TouchGuestActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            Cache::put(
                'chat:presence:'.$user->uuid,
                now()->getTimestamp(),
                now()->addSeconds(max(60, (int) config('chat.heartbeat_seconds', 180) * 3)),
            );

            if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5))) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
