<?php

declare(strict_types=1);

use App\Http\Controllers\BotApi\BotApiClanController;
use App\Http\Controllers\BotApi\BotApiDiscordEventController;
use App\Http\Controllers\BotApi\BotApiMatchController;
use App\Http\Controllers\BotApi\BotApiMatchSignupController;
use App\Http\Controllers\BotApi\BotApiOutboundController;
use App\Http\Controllers\BotApi\BotApiUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Source: .planning/phases/05-discord-bot-v1/05-04-PLAN.md <interfaces> + 05-RESEARCH.md
| Pattern 1 (layered Sanctum + abilities + bot.acts-as middleware stack).
|
| Three sub-groups under the /api/bot prefix:
|   (a) Read-only / outer group — auth:sanctum + abilities:bot:read (every bot route
|       requires bot:read; acts-as routes layer additional gates).
|   (b) Acts-as-user — adds abilities:bot:act-as-user + bot.acts-as middleware so
|       LogsActivity attributes activity_log rows to the human behind each Discord
|       interaction (SC-5).
|   (c) Outbound delivery — abilities:bot:write-outbound; NO bot.acts-as because the
|       bot acts as itself when claiming and ack-ing outbound messages.
|   (d) Discord-events reconciler — abilities:bot:reconcile; bot reports observed
|       guildMemberUpdate events (Pitfall 10 echo suppression in controller).
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('bot')->middleware(['auth:sanctum', 'abilities:bot:read'])->group(function (): void {
    // Read-only — bot:read ability only.
    Route::get('/clans', [BotApiClanController::class, 'index'])->name('bot.clans.index');
    Route::get('/clans/by-discord-role/{discordRoleId}', [BotApiClanController::class, 'showByDiscordRole'])
        ->whereNumber('discordRoleId')->name('bot.clans.byDiscordRole');
    Route::get('/matches', [BotApiMatchController::class, 'index'])->name('bot.matches.index');
    Route::get('/matches/{match}', [BotApiMatchController::class, 'show'])->name('bot.matches.show');

    // Acts-as-user — additional abilities:bot:act-as-user + bot.acts-as middleware.
    Route::middleware(['abilities:bot:act-as-user', 'bot.acts-as'])->group(function (): void {
        Route::get('/users/me', [BotApiUserController::class, 'me'])->name('bot.users.me');
        Route::post('/matches/{match}/signups', [BotApiMatchSignupController::class, 'store'])
            ->name('bot.matches.signups.store');
        Route::delete('/matches/{match}/signups/{gameRole}', [BotApiMatchSignupController::class, 'destroy'])
            ->name('bot.matches.signups.destroy');
    });

    // Outbound delivery — abilities:bot:write-outbound (NO bot.acts-as; bot acts as itself).
    Route::middleware('abilities:bot:write-outbound')->group(function (): void {
        Route::get('/outbound-messages', [BotApiOutboundController::class, 'pending'])
            ->name('bot.outbound.pending');
        Route::post('/outbound-messages/{id}/sent', [BotApiOutboundController::class, 'markSent'])
            ->whereUuid('id')->name('bot.outbound.sent');
        Route::post('/outbound-messages/{id}/failed', [BotApiOutboundController::class, 'markFailed'])
            ->whereUuid('id')->name('bot.outbound.failed');
    });

    // Discord-side drift reconciliation — abilities:bot:reconcile (no acts-as; bot reports observed events).
    Route::middleware('abilities:bot:reconcile')->group(function (): void {
        Route::post('/discord-events/role-change', [BotApiDiscordEventController::class, 'roleChange'])
            ->name('bot.discordEvents.roleChange');
    });
});
