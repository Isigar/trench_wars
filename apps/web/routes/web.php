<?php

declare(strict_types=1);

use App\Http\Controllers\Account\NotificationPreferencesController;
use App\Http\Controllers\Account\PrivacyController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BlogIndexController;
use App\Http\Controllers\BlogShowController;
use App\Http\Controllers\ClanDirectoryController;
use App\Http\Controllers\Clans\ClanApplyController;
use App\Http\Controllers\Clans\ClanCreateController;
use App\Http\Controllers\ClanShowController;
use App\Http\Controllers\ClansJsonController;
use App\Http\Controllers\EventsCalendarController;
use App\Http\Controllers\EventsFeedJsonController;
use App\Http\Controllers\LeaderboardsController;
use App\Http\Controllers\MatchCalendarController;
use App\Http\Controllers\Matches\MatchDisputeController;
use App\Http\Controllers\Matches\MatchSignupController;
use App\Http\Controllers\MatchShowController;
use App\Http\Controllers\MyClan\ClanApplicationController;
use App\Http\Controllers\MyClan\ClanInviteController;
use App\Http\Controllers\MyClan\MyClanController;
use App\Http\Controllers\MyClan\MyClanMemberController;
use App\Http\Controllers\MyClan\MyClanProfileController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\PlayersIndexController;
use App\Http\Controllers\PlayersJsonController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TournamentIndexController;
use App\Http\Controllers\TournamentPublicJsonController;
use App\Http\Controllers\TournamentShowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

// Public routes — no auth required (REQ-tenancy-multi-clan, REQ-goal-public-profiles)
Route::get('/clans', ClanDirectoryController::class)->name('clans.index');

// Plan 09-11 — public JSON list endpoints under SC-5 public-api throttle (30/min by IP,
// T-09-11-01 mitigation). MUST be declared BEFORE /clans/{clan:slug} so Laravel's
// first-match-wins router does NOT bind `clans.json` to a slug. Same precedent as
// Phase 6 /tournaments/{slug}.json (D-06-12-C) and Phase 7 /events/feed.json.
Route::get('/clans.json', ClansJsonController::class)
    ->middleware('throttle:public-api')
    ->name('clans.json');
Route::get('/players.json', PlayersJsonController::class)
    ->middleware('throttle:public-api')
    ->name('players.json');

// Public player directory index. MUST be declared BEFORE /players/{player:slug}
// so the literal /players path is not bound as a slug. Linked from the header
// nav + emitted into sitemap.xml (previously 404 — no route existed).
Route::get('/players', PlayersIndexController::class)->name('players.index');

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

// Phase 7 — Public CMS + Events + Search (SC-2 + SC-3 + SC-4).
// Routes ordered per Phase 6 D-06-12-C precedent: /events/feed.json BEFORE /events
// so Laravel's first-match-wins router does not capture `.json` as part of a slug.
// Plan 09-11 — replaces the prior throttle:60,1 with the named SC-5 throttle:public-api
// (30/min by IP, T-09-11-01 mitigation; harmonises the public-JSON throttle matrix
// across Phase 7 + Phase 9 endpoints).
Route::middleware(['throttle:public-api'])->group(function (): void {
    Route::get('/events/feed.json', EventsFeedJsonController::class)->name('events.feed');
    Route::get('/search', SearchController::class)->name('search.index');
});

Route::get('/blog', BlogIndexController::class)->name('blog.index');
Route::get('/blog/{slug}', BlogShowController::class)->name('blog.show');
Route::get('/events', EventsCalendarController::class)->name('events.index');

// Phase 9 plan 09-06 — public leaderboards (SC-2). throttle:public-api caps
// anonymous scraping at 30/min/IP (T-09-06-05 mitigation; named limiter
// registered by AppServiceProvider until plan 09-11 refines it).
Route::get('/leaderboards', [LeaderboardsController::class, 'index'])
    ->middleware('throttle:public-api')
    ->name('leaderboards.index');

// Source: 01-RESEARCH.md Pattern 1 + 01-09-PLAN.md Task 1.
// Discord OAuth flow — guests only (an authenticated visitor revisiting /redirect
// would otherwise loop through OAuth needlessly). Logout requires an active session.
// Plan 09-11 — throttle:auth (10/min by IP, T-09-11-07 mitigation) layered onto both
// /redirect and /callback so OAuth-state-replay storms are bounded at the network edge.
Route::middleware(['guest', 'throttle:auth'])->group(function (): void {
    Route::get('/auth/discord/redirect', [DiscordController::class, 'redirect'])
        ->name('auth.discord.redirect');

    Route::get('/auth/discord/callback', [DiscordController::class, 'callback'])
        ->name('auth.discord.callback');
});

Route::middleware(['auth', 'banned'])->group(function (): void {
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

    // Application submit — authenticated user applies to join a clan.
    // NOT under /my-clan prefix: applicant may not yet have any clan membership.
    // WR-03: throttle:clan-apply (5/min per user) guards against submission storms.
    Route::post('/clans/{clan:slug}/apply', [ClanApplyController::class, 'store'])
        ->middleware('throttle:clan-apply')
        ->name('clans.apply');

    // Match signups (SC-2 + SC-5 — REQ-goal-match-workflows). MatchSignupService
    // is the SOLE production write path to match_slots.occupant_user_id.
    Route::post('/matches/{match}/signups', [MatchSignupController::class, 'store'])->name('matches.signups.store');
    Route::delete('/matches/{match}/signups/{slot}', [MatchSignupController::class, 'destroy'])->name('matches.signups.destroy');

    // Raise a match dispute — the reachable entry point into the moderator
    // dispute queue (DisputeService::open). Eligibility (played match +
    // organiser/participant) enforced in StoreMatchDisputeRequest; light
    // throttle bounds spam (the one-open-per-match unique index is the real cap).
    Route::post('/matches/{match}/disputes', [MatchDisputeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('matches.disputes.store');

    // Phase 9 plan 09-06 — notifications hub + per-user preference matrix (SC-1).
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])
        ->middleware('throttle:notifications-read')
        ->name('notifications.markRead');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllAsRead'])
        ->middleware('throttle:notifications-read')
        ->name('notifications.markAllRead');

    Route::get('/account/notification-preferences', [NotificationPreferencesController::class, 'edit'])
        ->name('account.notification-preferences.edit');
    Route::post('/account/notification-preferences', [NotificationPreferencesController::class, 'update'])
        ->name('account.notification-preferences.update');

    // Self-service profile privacy (D-018 — user-controllable per-section + global
    // tier). Writes the auth user's own PlayerPrivacy row; mirrors notification-prefs.
    Route::get('/account/privacy', [PrivacyController::class, 'edit'])
        ->name('account.privacy.edit');
    Route::post('/account/privacy', [PrivacyController::class, 'update'])
        ->name('account.privacy.update');
});

// Plan 09-11 — Report Abuse flow (SC-5). Auth-required + per-user throttle
// (5/hour, T-09-11-03 mitigation). FormRequest validation lives in
// StoreAbuseReportRequest; activity_log write + abuse_reports row insert
// inside ReportsController::store under a DB transaction.
Route::middleware(['auth', 'banned', 'throttle:report-abuse'])->group(function (): void {
    Route::get('/reports/create', [ReportsController::class, 'create'])->name('reports.create');
    Route::post('/reports', [ReportsController::class, 'store'])->name('reports.store');
});
