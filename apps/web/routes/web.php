<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClanDirectoryController;
use App\Http\Controllers\ClanShowController;
use App\Http\Controllers\PlayerProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

// Public routes — no auth required (REQ-tenancy-multi-clan, REQ-goal-public-profiles)
Route::get('/clans', ClanDirectoryController::class)->name('clans.index');
Route::get('/clans/{clan:slug}', ClanShowController::class)->name('clans.show');
Route::get('/players/{player:slug}', PlayerProfileController::class)->name('players.show');

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
