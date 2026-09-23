<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Schedule::call(function (): void {
    if (config('session.driver') !== 'database') {
        return;
    }

    if (! Schema::hasTable('users') || ! Schema::hasTable('sessions')) {
        return;
    }

    $activeAfter = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

    User::query()
        ->where('created_at', '<', now()->subDay())
        ->whereNotIn('id', function ($query) use ($activeAfter) {
            $query->select('user_id')
                ->from('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', $activeAfter);
        })
        ->delete();

    DB::table('sessions')
        ->where('last_activity', '<', $activeAfter)
        ->delete();
})->hourly()->name('cleanup-stale-chat-guests')->withoutOverlapping();
