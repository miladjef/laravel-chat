<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('lobby', static function (User $user): array {
    return [
        'uuid' => $user->uuid,
        'display_name' => $user->display_name,
        'avatar' => $user->avatar,
    ];
});
