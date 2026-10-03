<?php

namespace App\Support;

use Illuminate\Http\Request;

final class AnonymousClient
{
    public static function fingerprint(?Request $request = null): string
    {
        $request ??= request();

        $key = (string) config('app.key', '');
        $ip = (string) ($request->ip() ?: 'unknown');

        return hash_hmac('sha256', $ip, $key !== '' ? $key : 'okkio-chat');
    }
}
