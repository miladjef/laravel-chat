<?php

use App\Actions\CleanupStaleGuests;
use Illuminate\Support\Facades\Schedule;

Schedule::call(static fn (): int => app(CleanupStaleGuests::class)())
    ->hourly()
    ->name('cleanup-stale-chat-guests')
    ->withoutOverlapping();
