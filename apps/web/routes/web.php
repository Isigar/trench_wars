<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClanDirectoryController;
use App\Http\Controllers\Clans\ClanCreateController;
use App\Http\Controllers\ClanShowController;
use App\Http\Controllers\MatchCalendarController;
use App\Http\Controllers\Matches\MatchSignupController;
use App\Http\Controllers\MatchShowController;
use App\Http\Controllers\MyClan\ClanApplicationController;
use App\Http\Controllers\MyClan\ClanInviteController;
use App\Http\Controllers\MyClan\MyClanController;
use App\Http\Controllers\MyClan\MyClanMemberController;
use App\Http\Controllers\MyClan\MyClanProfileController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\TournamentIndexController;
use App\Http\Controllers\TournamentPublicJsonController;
use App\Http\Controllers\TournamentShowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

// Public routes — no auth required (REQ-tenancy-multi-clan, REQ-goal-public-profiles)
Route::get('/clans', ClanDirectoryController::class)->name('clans.index');
Route::get('/clans/{clan:slug}', ClanShowController::class)->name('clans.show');
Route::get('/players/{player:slug}', PlayerProfileController::class)->name('players.show');

// Public match calendar + detail (SC-3 — REQ-goal-match-workflows)
// is_public + status NOT IN (draft, cancelled) enforced inside the controllers.
Route::get('/matches', MatchCalendarController::class)->name('matches.index');
Route::get('/matches/{match}', MatchShowController::class)->name('matches.show');

// Public tournament directory + detail (Phase 6 SC-3 — REQ-success-tournament-end-to-end)
// is_public + status filter enforced inside the controllers. {tournament:slug} binding.
Route::get('/tournaments', TournamentIndexController::class)->name('tournaments.index');

// Public tournament JSON polling endpoint (T-06-12-01 mitigation: throttle:60,1).
// MUST be declared BEFORE the {slug} route so Laravel's first-match-wins dispatcher
// captures `.json` requests here instead of binding `{tournament:slug}` to a slug
// that contains a trailing `.json`. Powers the 30s polling loop in
// Tournaments/Show.vue (Pattern 9 If-None-Match).
Route::middleware(['throttle:60,1'])->group(function (): void {
    Route::get('/tournaments/{tournament:slug}.json', TournamentPublicJsonController::class)
        ->name('tournaments.show.json');
});

// Tournament detail — slug constrained to not contain a leading dot so the .json
// endpoint above wins on order. Mirrors Phase 4 idiom for routing precedence.
Route::get('/tournaments/{tournament:slug}', TournamentShowController::class)
    ->name('tournaments.show')
    ->where('tournament', '[A-Za-z0-9_-]+');

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

    // Clan create — any authenticated user may create one clan (D-009 one-active enforced in controller).
    Route::post('/clans', ClanCreateController::class)->name('clans.store');

    // My Clan management — auth + active Leader/Officer check inside MyClanController.
    Route::prefix('my-clan')->name('my-clan.')->group(function (): void {
        Route::get('/', MyClanController::class)->name('index');
        Route::patch('/profile/{clan:slug}', [MyClanProfileController::class, 'update'])->name('profile.update');
        Route::patch('/members/{membership}/role', [MyClanMemberController::class, 'updateRole'])->name('members.role');
        Route::delete('/members/{membership}', [MyClanMemberController::class, 'remove'])->name('members.remove');
        // Invite management — Leader/Officer sends and revokes invites.
        Route::post('/invites', [ClanInviteController::class, 'store'])->name('invites.store');
        Route::delete('/invites/{invite}', [ClanInviteController::class, 'destroy'])->name('invites.destroy');

        // Application management — Leader/Officer accepts or declines pending applications.
        Route::post('/applications/{application}/accept', [ClanApplicationController::class, 'accept'])->name('applications.accept');
        Route::post('/applications/{application}/decline', [ClanApplicationController::class, 'decline'])->name('applications.decline');
    });

    // Invite accept/decline — NOT under /my-clan prefix because the invitee may not
    // yet have any clan membership.
    Route::post('/invites/{invite}/accept', [ClanInviteController::class, 'accept'])->name('invites.accept');
    Route::post('/invites/{invite}/decline', [ClanInviteController::class, 'decline'])->name('invites.decline');

    // Application cancel — NOT under /my-clan prefix because the applicant may not
    // yet have any clan membership.
    Route::post('/applications/{application}/cancel', [ClanApplicationController::class, 'cancel'])->name('applications.cancel');

    // Match signups (SC-2 + SC-5 — REQ-goal-match-workflows). MatchSignupService
    // is the SOLE production write path to match_slots.occupant_user_id.
    Route::post('/matches/{match}/signups', [MatchSignupController::class, 'store'])->name('matches.signups.store');
    Route::delete('/matches/{match}/signups/{slot}', [MatchSignupController::class, 'destroy'])->name('matches.signups.destroy');
});
