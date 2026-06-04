<?php

declare(strict_types=1);

use App\Http\Controllers\BotApi\BotApiClanApplicationController;
use App\Http\Controllers\BotApi\BotApiClanController;
use App\Http\Controllers\BotApi\BotApiDiscordEventController;
use App\Http\Controllers\BotApi\BotApiMatchController;
use App\Http\Controllers\BotApi\BotApiMatchSignupController;
use App\Http\Controllers\BotApi\BotApiOutboundController;
use App\Http\Controllers\BotApi\BotApiUserController;
use App\Http\Controllers\Internal\BookingScheduleController;
use App\Http\Controllers\Internal\MatchEventsController;
use App\Http\Controllers\Internal\MatchServerCredentialsController;
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
        Route::post('/clans/{clan:slug}/applications', [BotApiClanApplicationController::class, 'store'])
            ->name('bot.clans.applications.store');
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

// ----- RCON Worker → Web (Phase 8) -----
//
// Source: .planning/phases/08-rcon-automation/08-06-PLAN.md <interfaces> Route block.
//
// Three HMAC-protected endpoints consumed by apps/rcon-worker (plans 08-10..08-11):
//   POST match/{match}/events          — MatchEventsController::store (Wave 4 shim;
//                                        plan 08-07 swaps the body for the real
//                                        MatchEventIngestService).
//   GET  bookings/due                  — BookingScheduleController::dueNow → fed to
//                                        plan 08-11 BookingScheduler.
//   GET  match-servers/{server}/...    — MatchServerCredentialsController::show →
//                                        called by plan 08-10 CrconClient on session open.
//
// Middleware stack:
//   - `rcon.signature` (alias registered in bootstrap/app.php by plan 08-05) — HMAC
//     gate over (timestamp + raw body); rejects missing/stale/bad-sig/replayed requests.
//   - `throttle:600,1` — DoS guard (T-08-06-01). 600 req/min is well above the worker's
//     single-replica steady-state rate (≤ 1 batch/s on /events; one /bookings/due poll
//     every 30s; one /credentials per session open).
//
// Namespace segregation: `/api/internal/*` is SEPARATE from `/api/*` (bot — Sanctum
// abilities) and `/api/v1/*` (public). The rcon.signature middleware MUST NOT bleed
// onto unrelated traffic — the route group prefix is the boundary.
Route::middleware(['rcon.signature', 'throttle:600,1'])
    ->prefix('internal')
    ->name('internal.')
    ->group(function (): void {
        Route::post('match/{match}/events', [MatchEventsController::class, 'store'])
            ->whereUuid('match')->name('match.events.store');
        Route::get('bookings/due', [BookingScheduleController::class, 'dueNow'])
            ->name('bookings.due');
        Route::get('match-servers/{server}/credentials', [MatchServerCredentialsController::class, 'show'])
            ->whereUuid('server')->name('match-servers.credentials');
    });
