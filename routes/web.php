<?php

use App\Livewire\LobbyPage;
use App\Models\User;
use App\Livewire\RegisterPage;
use App\Support\ReadinessCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/ready', function (ReadinessCheck $readiness) {
    $result = $readiness->run();

    return response()->json($result, $result['ok'] ? 200 : 503);
})->name('ready')->middleware('throttle:30,1');

Route::get('/', LobbyPage::class)
    ->name('lobby')
    ->middleware('auth:web');

Route::get('/auth', RegisterPage::class)
    ->name('login')
    ->middleware('guest');

Route::post('/logout', function (Request $request) {
    /** @var User|null $user */
    $user = $request->user();

    if ($user) {
        Cache::forget('chat:presence:'.$user->uuid);
    }

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    $user?->delete();

    return redirect()->route('login');
})->name('logout')->middleware('auth:web');
