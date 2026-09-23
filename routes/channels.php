<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id)
{
    return (int)$user->id === (int)$id;
});

Broadcast::channel('lobby', static function (User $user)
{
    return [
        'display_name' => $user->display_name,
        'email'        => $user->email,
        'uuid'         => str($user->email)->replace('@okkio.chat', ''),
        'avatar'       => $user->avatar,
    ];
});
