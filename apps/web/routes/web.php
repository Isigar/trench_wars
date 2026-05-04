<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

// Source: 01-RESEARCH.md Pattern 1 + 01-09-PLAN.md Task 1.
// Discord OAuth flow — guests only (an authenticated visitor revisiting /redirect
// would otherwise loop through OAuth needlessly). Logout requires an active session.
Route::middleware('guest')->group(function (): void {
    Route::get('/auth/discord/redirect', [DiscordController::class, 'redirect'])
        ->name('auth.discord.redirect');

    Route::get('/auth/discord/callback', [DiscordController::class, 'callback'])
        ->name('auth.discord.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
});
