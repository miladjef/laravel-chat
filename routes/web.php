<?php

use App\Livewire\LobbyPage;
use App\Livewire\RegisterPage;
use Illuminate\Support\Facades\Route;

Route::get('/', LobbyPage::class)
    ->name('lobby')
    ->middleware('auth:web');

Route::get('auth', RegisterPage::class)
    ->name('login')
    ->middleware('guest');
