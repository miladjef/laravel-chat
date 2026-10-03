<?php

namespace App\Actions;

use App\Models\User;

final class CleanupStaleGuests
{
    public function __invoke(): int
    {
        return User::query()
            ->where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->where('created_at', '<', now()->subDay());
            })
            ->orWhere('last_seen_at', '<', now()->subDay())
            ->delete();
    }
}
