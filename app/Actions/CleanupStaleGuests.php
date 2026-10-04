<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class CleanupStaleGuests
{
    public function __invoke(): int
    {
        $cutoff = now()->subHours(max(1, (int) config('chat.guest_stale_hours', 24)));
        $deleted = 0;

        User::query()
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_seen_at')
                    ->where('created_at', '<', $cutoff);
            })
            ->orWhere('last_seen_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$deleted): void {
                foreach ($users as $user) {
                    if (Cache::has('chat:presence:'.$user->uuid)) {
                        continue;
                    }

                    $deleted += $user->delete() ? 1 : 0;
                }
            });

        return $deleted;
    }
}
