<?php

use App\Livewire\LobbyPage;
use App\Livewire\RegisterPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', LobbyPage::class)
    ->name('lobby')
    ->middleware('auth:web');

Route::get('/auth', RegisterPage::class)
    ->name('login')
    ->middleware('guest');

Route::post('/logout', function (Request $request) {
    $user = $request->user();

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    $user?->delete();

    return redirect()->route('login');
})->name('logout')->middleware('auth:web');
