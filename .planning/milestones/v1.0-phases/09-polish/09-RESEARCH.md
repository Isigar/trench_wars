# Phase 9: Polish — Research

**Researched:** 2026-05-14
**Domain:** Notifications hub + leaderboards aggregator + moderator tooling + performance + accessibility + security hardening — applied to a Laravel 12 + Inertia v2 + Vue 3 + Filament v3 + Postgres 16 + Redis 7 stack already shipped through Phase 8.
**Confidence:** HIGH — every library shown is already installed and pinned in `apps/web/composer.json` / `apps/web/package.json`; every pattern is verified against current official Laravel 12.x / Filament 3.x / Spatie v11 docs (fetched 2026-05-14).

---

## Summary

Phase 9 is a buffer/polish phase consuming 10–12 plans against an already-shipped Phase 1–8 surface (1134 Pest tests, 8 plans complete, 89% project progress). The phase is exclusively additive — no schema-breaking work, no new domain models, no architectural pivots. Every recommendation in this research sits on top of dependencies that already live in `apps/web/composer.json`:

- **Notifications** — Laravel 12's `Notifiable` trait + `php artisan make:notifications-table` ships the `notifications` polymorphic table and `databaseType()` discriminator. Custom `DiscordChannel` writes to the existing Phase 5 `discord_outbound_messages` outbox (NOT a direct webhook). Per-user toggle table `user_notification_preferences` is gated at `via()` time so disabled channels are simply omitted.
- **Leaderboards** — `MatchPlayerStat` aggregator is already shipped (Phase 8); Phase 9 just adds a `LeaderboardService` that wraps grouped queries in `Cache::tags(['leaderboards'])->flexible(...)` for 7d / 30d / all-time windows. `PlayerPrivacyGate` (Phase 1) gates each row at serialization time.
- **Moderator tooling** — `spatie/laravel-permission` 7.4 + new `moderator` role; Filament v3 `BulkAction::make()->form([])->action(fn (Collection $records, array $data))` for ban/suspend bulk operations; `match_disputes` table is the new domain model.
- **Performance** — `Model::shouldBeStrict()` in `AppServiceProvider::boot()` (dev only) catches every N+1 in CI; `Cache::tags()->flush()` driven by Eloquent observers; `spatie/laravel-medialibrary` 11.22 (already installed) emits WebP via `->addMediaConversion(...)->format('webp')`.
- **Accessibility** — `@axe-core/cli` 4.11.3 in a CI workflow scans the SSR-rendered routes against WCAG 2.1 AA; manual keyboard-nav + focus-ring CSS audit.
- **Security** — `RateLimiter::for('public-api', ...)` in `AppServiceProvider::boot()`; `report_abuse` queue; Spatie permission gate audit.

**Primary recommendation:** Land 09-01 as the unified Wave-0 (the migration trio: `notifications`, `user_notification_preferences`, `bans`, `match_disputes`) + RED Pest skeleton for SC-1..SC-5, then march through the 10 implementation plans without re-litigating dependencies. Every package this phase needs is already installed and at a known-good version.

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Notification creation (`MatchStartingSoon::class`, etc.) | API / Backend (apps/web) | Schedule cron in `routes/console.php` | Domain logic lives in Laravel; cron is the trigger surface |
| Notification delivery (web bell) | API / Backend (apps/web) | Browser (Inertia page mounts `unreadNotifications`) | DatabaseChannel writes; Vue renders |
| Notification delivery (Discord DM) | API / Backend (apps/web) → outbox → bot (apps/bot) | Bot polls + sends via discord.js | D-004: bot is thin display layer; web writes to `discord_outbound_messages`, bot consumes |
| User notification preferences UI | Frontend (Inertia/Vue) | API / Backend persists | Inertia form posts; controller updates `user_notification_preferences` |
| Leaderboard aggregation | API / Backend (apps/web) | Database (`MatchPlayerStat` SUM/COUNT) | `LeaderboardService` runs grouped queries; cache layer wraps |
| Leaderboard rendering | Frontend (Inertia/Vue SSR) | API / Backend serializes via `spatie/laravel-data` | Public surface; SSR enabled per Phase 7 |
| Moderator BulkActions (ban/suspend) | Filament admin panel (apps/web) | Database (`bans` table + `activity_log`) | Filament v3 BulkAction with form fields |
| Dispute workflow | Filament admin panel (apps/web) | Database (`match_disputes` table) | Status state machine in Filament resource |
| N+1 audit | Dev tooling (apps/web debugbar / Telescope opt-in) | CI gate (Pest performance assertion) | `shouldBeStrict()` in AppServiceProvider; CI fails on lazy load |
| Tagged cache strategy | API / Backend (apps/web) | Redis 7 (cache.store=redis) | Tags require Redis or Memcached store |
| WebP image variants | API / Backend (Spatie medialibrary conversions) | Storage layer (`storage/app/media`) | Conversion runs on `addMedia()`; Imagick driver |
| Accessibility scan | CI (GitHub Actions axe-core) | Frontend (focus-ring CSS) | axe-core runs against deployed staging URL |
| Rate limiting | API / Backend middleware | Redis (rate limiter store) | `RateLimiter::for()` + `throttle:name` middleware |
| Report-abuse flow | Frontend (Inertia form) | Backend (`report_abuse` queue → moderator inbox in Filament) | UI submits → DB row → admin reviews |

---

## Phase Requirements

This phase consumes residual polish backlog. **No v1 requirements map to Phase 9** — all 15 mappable round-1 requirements are already satisfied through Phase 8 (per `.planning/REQUIREMENTS.md` traceability table). Phase 9 ships the v2-adjacent polish surface that makes the v1 surface production-grade.

| ID | Description | Research Support |
|----|-------------|------------------|
| NOTF-01 (v2 hoisted into v1 polish) | User-configurable notification preferences (web bell + Discord DM rules) | `## Notifications`, `## User Notification Preferences Design` |
| (implicit) Performance NFR — public surface latency | Public clan directory + leaderboards + blog index render under target time budgets | `## Performance & N+1`, `## Cache Strategy` |
| (implicit) WCAG 2.1 AA — `CON-frontend-goals` | Keyboard, screen-reader, AA contrast — translatable from day one | `## Accessibility (WCAG 2.1 AA)` |
| (implicit) Rate-limit hardening — `CON-discord-security` adjacent | Public API endpoints throttled; abuse vectors documented | `## Security Hardening` |
| (implicit) Audit completeness — D-012 | All moderator actions audited via `activity_log` | `## Moderator Tooling & Disputes` |

The 5 ROADMAP Success Criteria from `09-CONTEXT.md` are the de-facto requirement set for this phase:

| SC | Verbatim from CONTEXT |
|----|------------------------|
| SC-1 | Notifications hub: web bell + Discord DM rules; default rules wired (match starting 1h/15m, match cancelled, result published) |
| SC-2 | Leaderboards: top clans + top players by stat windows from MatchPlayerStat aggregates |
| SC-3 | Moderator tooling: bulk actions, ban/suspend, dispute resolution workflow in Filament; all audited |
| SC-4 | Performance pass: N+1 eliminated; documented cache-key strategy; WebP image variants; round-1 public surface within target time budgets |
| SC-5 | Accessibility + security pass: AA contrast, keyboard nav, focus rings; rate-limit + abuse-vector hardening documented |

---

## Project Constraints (from CLAUDE.md)

These directives are extracted verbatim from `./CLAUDE.md` and bind every plan in this phase. Plans MUST NOT recommend approaches that contradict them. [VERIFIED: CLAUDE.md, read 2026-05-14]

- **D-021 Container-only commands.** Every `composer`, `php`, `php artisan`, `pnpm`, `npm`, `node`, `vite` invocation runs inside containers via `make ...` aliases or `docker compose exec`. Host PHP/Node/Postgres are NOT used. Plans MUST emit `docker compose exec web ...` (or `make`) in their actions.
- **PHP 8.4 + Laravel 12 + Filament v3.3 + Inertia v2 + Vue 3 + Tailwind v4** — stack is LOCKED. No version bumps in Phase 9.
- **Pest (NOT PHPUnit) — `it()` / `test()` / `expect()`.** Feature tests under `apps/web/tests/Feature/`, Unit under `apps/web/tests/Unit/`. Browser tests deferred.
- **Pint preset + PHPStan level 8** are CI gates. `make pint ARGS="--test"` + `make phpstan` MUST pass.
- **D-013 i18n.** Every UI string flows through `__()` (PHP/Blade) or `t()` (Vue). PHP array files only (`apps/web/lang/en/*.php`) — NO JSON locale files in P1.
- **D-002 Discord ID canonical** — text UNIQUE column; snowflake overflows JS Number.
- **D-004 Bot is thin display layer.** No DB writes from bot. Notifications dispatched to Discord MUST route through `discord_outbound_messages` outbox (Phase 5), NOT direct HTTP from web.
- **D-009 One active ClanMembership** — partial unique index already enforces.
- **D-018 PlayerPrivacyGate** — leaderboard rows MUST honour per-section `show_*` flags + global `show_to` tier.
- **D-04-03-A LOCKED — `App\Models\GameMatch`** is the canonical model FQN (NOT `Match`). Use direct `use App\Models\GameMatch` everywhere; no `Match as MatchModel` alias-on-import. `BelongsTo<GameMatch, $this>` passes `match_id` as explicit FK arg per D-04-03-B / D-06-03-A / D-07-* continuation.
- **Spatie permission default_guard='web'** — matches Filament panel guard (CLAUDE.md §6 + Pitfall 4).
- **`SESSION_SECURE_COOKIE=false` for local HTTP** — production uses `true`.
- **Activity log is append-only via `LogsActivity` trait** — Filament admin UI never exposes edit/delete on `activity_log` rows.
- **No starter kits** (D-017) — hand-roll any auth surface needed.
- **Translatable user content** uses `spatie/laravel-translatable` JSONB columns keyed by locale (NOT separate translation tables).

---

## User Constraints (from CONTEXT.md)

`09-CONTEXT.md` was auto-generated (`mode: Auto-generated (discuss skipped via workflow.skip_discuss)`). The "Locked + Discretionary" block reaffirms project-level decisions and grants Phase 9 the following autonomy:

### Locked Decisions (verbatim from CONTEXT.md)

- **D-04-03-A** `App\Models\GameMatch`.
- **D-013** i18n every UI string.
- **D-018** PlayerPrivacyGate on leaderboards (privacy-aware).
- **D-021** Container-only.

### Claude's Discretion (verbatim from CONTEXT.md)

- Notifications backend: Laravel Notifications + DatabaseChannel for web bell + DiscordChannel via Phase 5 outbound infra.
- Leaderboard windows: 7d / 30d / all-time; cached aggregate queries.
- Moderator tooling: spatie/permission moderator role + Filament BulkActions.
- Performance: Laravel cache facade + tag-keyed strategy; spatie/image-optimizer for WebP variants.
- Accessibility: axe-core scan in CI; manual keyboard tests; focus-ring CSS audit.

### Deferred Ideas — OUT OF SCOPE (verbatim from CONTEXT.md)

- Real-time notifications via WebSockets / Reverb (v2).
- ELO rating systems (Phase 10+).
- Multi-language UI (i18n key files only — actual translations beyond English deferred).

---

## Standard Stack

### Core (already installed — confirmed via `composer show` / `package.json` 2026-05-14)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^12.0` (installed 12.58.0) | Notifications, RateLimiter, Cache, Schedule | Phase 9 is purely additive on the existing Laravel 12 foundation [VERIFIED: composer show 2026-05-14] |
| `filament/filament` | `^3.3` (installed 3.3.50) | BulkActions on UserResource/MatchResource, ModeratorPanel, ReviewQueueResource | Already shipped through Phase 1–8 [VERIFIED: composer show] |
| `spatie/laravel-permission` | `^7.4` (installed 7.4.1) | New `moderator` role + `moderate-users`, `moderate-disputes`, `view-reports` permissions | Already gated to Filament panel guard `web` per Pitfall 4 mitigation [VERIFIED: composer show] |
| `spatie/laravel-activitylog` | `^5.0` (installed 5.0.0) | Audit moderator actions (ban, dispute decision, bulk update) | D-012 — already the audit engine [VERIFIED: composer show] |
| `spatie/laravel-medialibrary` | `^11.22` (installed 11.22.1) | WebP variants via `addMediaConversion()->format('webp')` | Already required by Phase 7 CMS [VERIFIED: composer show] |
| `spatie/image-optimizer` | `^1.7` (installed 1.8.1) | Auto-runs against converted images (jpegoptim, optipng, pngquant 2, SVGO, gifsicle, avifenc) | Bundled with medialibrary; optimization is the default — opt OUT via `->nonOptimized()` not opt IN [VERIFIED: composer show + Spatie v11 docs] |
| `laravel/horizon` | `^5.46` (installed 5.46.0) | Queue notifications (`implements ShouldQueue`) + medialibrary conversions (queued by default) | Already shipped Phase 5 [VERIFIED: composer show] |
| `predis/predis` or `phpredis` ext | (tied to Redis 7 in compose) | Backs Cache::tags(), Cache::lock(), rate limiter | `Cache::tags()` requires Redis OR Memcached — file/database drivers NOT supported [VERIFIED: Laravel 12 cache docs] |
| `barryvdh/laravel-debugbar` | `^3.0` (installed 3.16.5) | Dev-only N+1 detection; query count per page; cache hit/miss | Already installed; production-disabled via `APP_DEBUG=false` [VERIFIED: composer show] |

### Supporting (CI only — not runtime deps)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@axe-core/cli` | `^4.11.3` | CLI runner that scans deployed URLs for WCAG violations | Phase 9 plan that adds GitHub Actions a11y job [VERIFIED: npm view 2026-05-14 — 4.11.3 latest] |
| `axe-core` | `^4.11.4` | Core rule engine consumed by `@axe-core/cli` | Transitive dep; pin via cli only [VERIFIED: npm view 2026-05-14] |

### Alternatives Considered (and rejected for round-1)

| Instead of | Could Use | Tradeoff — why rejected |
|------------|-----------|----------|
| `database` channel for web bell | `broadcast` / `reverb` (WebSocket) | Real-time push deferred to v2 (explicit `09-CONTEXT.md` deferral) |
| Custom `DiscordChannel` writing to `discord_outbound_messages` outbox | `laravel-notification-channels/discord` (direct webhook) | Violates D-004 (bot is thin display layer; web does NOT call Discord directly). The outbox + bot polling is the established Phase 5 pattern |
| `spatie/laravel-responsecache` | Manual `Cache::tags()` | Page-level cache adds an invalidation matrix on top of Eloquent observers; we already own the invalidation choke points via Phase 7 observers. Keep it explicit |
| `laravel/telescope` | `barryvdh/laravel-debugbar` | Telescope wants its own DB tables (10+ migrations) + auth gate. Debugbar gives the same query-count signal inline in dev. Telescope is OPTIONAL — install only if dev complains debugbar isn't enough |
| `@axe-core/playwright` | `@axe-core/cli` | Playwright pulls in headless Chromium (~150MB), browser session orchestration, retry logic. For SSR'd public pages the CLI's single-pass scan against a deployed URL is sufficient. Playwright is a v2 enhancement if interactive flows need scanning |
| Hand-rolled rate limit middleware | `Illuminate\Routing\Middleware\ThrottleRequests` (via `RateLimiter::for()`) | Built-in is Redis-backed by default, supports `Limit::perMinute()->by()` keying, supports `Limit::none()` for VIPs. No reason to reinvent |
| Memcached cache store | Redis 7 | Already deployed (D-014 Railway Redis plugin); `Cache::tags()` supports both but the project standardised on Redis from Phase 1 |
| `phpredis` C extension | `predis/predis` PHP client | The web container ships PHP 8.4 with `phpredis` available; pure-PHP `predis` is a fallback, not the primary. Use whatever `config/database.php` redis client setting already points at — DO NOT change |

**Verification commands** (run before each plan that needs a fresh check):

```bash
docker compose exec web composer show laravel/framework filament/filament spatie/laravel-medialibrary spatie/laravel-permission laravel/horizon | grep versions
npm view @axe-core/cli version
npm view axe-core version
```

---

## Architecture Patterns

### System Architecture Diagram

```
                            ┌──────────────────────────────────────────────────────────┐
                            │                  Phase 9 Polish surface                  │
                            │                                                          │
   ┌─────────────────────┐  │   ┌────────────────────────┐    ┌──────────────────┐  │   ┌──────────────────────┐
   │ Schedule::call cron │──┼──▶│ NotificationDispatcher │───▶│ Notification cls │──┼──▶│ DatabaseChannel      │
   │ everyMinute()       │  │   │ (queries upcoming      │    │ ->via()          │  │   │   → notifications tbl │
   │ onOneServer()       │  │   │  GameMatch rows)       │    │ (per-user prefs) │  │   ├──────────────────────┤
   └─────────────────────┘  │   └────────────────────────┘    └──────────────────┘  │   │ DiscordChannel       │
                            │                                          │            │   │   → discord_outbound  │
   ┌─────────────────────┐  │                                          │            │   │     _messages outbox  │
   │ Domain events       │──┼──▶ Eloquent observers ────────────────────              │   └──────────────────────┘
   │ (match_cancelled,   │  │   (MatchObserver, MatchResultObserver,                  │            │
   │  result_published)  │  │    ClanApplicationObserver)                              │            │
   └─────────────────────┘  │                                                          │            ▼
                            │                                                          │   Existing Phase 5
                            │                                                          │   bot poller → DM
                            │                                                          │
   ┌─────────────────────┐  │   ┌────────────────────────┐    ┌──────────────────┐  │
   │ Web bell button     │──┼──▶│ NotificationsController│───▶│ User->unread     │──┼──▶ Vue NotificationsHub
   │ (Inertia link)      │  │   │ index + markAsRead     │    │ Notifications    │  │   (bell badge, drawer)
   └─────────────────────┘  │   └────────────────────────┘    └──────────────────┘  │
                            │                                                          │
   ┌─────────────────────┐  │   ┌────────────────────────┐    ┌──────────────────┐  │
   │ Public page request │──┼──▶│ Cache::tags(...)       │───▶│ LeaderboardSvc   │──┼──▶ MatchPlayerStat
   │ /leaderboards       │  │   │ ::flexible([3600,7200])│    │ topClans/Players │  │     GROUP BY (cached)
   └─────────────────────┘  │   └────────────────────────┘    └──────────────────┘  │
                            │                                                          │
   ┌─────────────────────┐  │   ┌────────────────────────┐    ┌──────────────────┐  │
   │ Filament admin      │──┼──▶│ BulkAction->form->     │───▶│ BanService /     │──┼──▶ bans tbl + activity_log
   │ (UserResource etc)  │  │   │   action(records,data) │    │ DisputeService   │  │     append-only
   └─────────────────────┘  │   └────────────────────────┘    └──────────────────┘  │
                            └──────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| File / Class | Responsibility | Phase 9 ships? |
|--------------|-----------|----|
| `app/Notifications/*.php` | One class per notification type (MatchStartingSoon, MatchCancelled, MatchResultPublished, ClanApplicationApproved, ClanInviteReceived) | NEW |
| `app/Notifications/Channels/DiscordChannel.php` | Writes a `discord_outbound_messages` row (NOT direct webhook) — payload-keyed dispatch matching Phase 5/6/8 idiom | NEW |
| `app/Services/NotificationDispatcher.php` | Cron-driven sweeper: finds upcoming matches in `[+1h ± 5m]` and `[+15m ± 5m]` windows, calls `notify()` on participants | NEW |
| `app/Services/LeaderboardService.php` | `topPlayers(window: '7d'\|'30d'\|'all'), topClans(window: ...)` — wraps cached aggregate queries | NEW |
| `app/Services/BanService.php` + `app/Services/DisputeService.php` | Encapsulate ban-issue, suspend-lift, dispute-open/resolve logic + activity_log writes | NEW |
| `app/Filament/Resources/UserResource.php` (existing) | Extend with `BulkAction::make('ban')->form([...])` | EXTEND |
| `app/Filament/Resources/MatchResource.php` (existing) | Extend with `BulkAction::make('mark_cancelled')` | EXTEND |
| `app/Filament/Resources/MatchDisputeResource.php` | New resource: list / view / resolve | NEW |
| `app/Filament/Resources/AbuseReportResource.php` | New resource: review queue + assign + close | NEW |
| `database/migrations/2026_05_18_*_create_notifications_table.php` | Polymorphic `notifications` table (id uuid, notifiable_type/id, type, data jsonb, read_at) | NEW |
| `database/migrations/2026_05_18_*_create_user_notification_preferences_table.php` | (user_id, event_type, channel, enabled) — composite UNIQUE | NEW |
| `database/migrations/2026_05_18_*_create_bans_table.php` | (user_id, reason, ban_type, expires_at, issued_by_user_id) | NEW |
| `database/migrations/2026_05_18_*_create_match_disputes_table.php` | (match_id, raised_by_user_id, status, resolution, resolved_by_user_id, resolved_at) | NEW |
| `database/migrations/2026_05_18_*_create_abuse_reports_table.php` | (reporter_user_id, target_type, target_id, reason, status) | NEW |
| `app/Providers/AppServiceProvider::boot()` | Add `Model::shouldBeStrict()` + `Vite::prefetch()` + `RateLimiter::for('public-api', ...)` + new tag-flush observers | EXTEND |
| `routes/console.php` | Add `Schedule::command('notifications:dispatch-upcoming')->everyMinute()->withoutOverlapping()->onOneServer()` | EXTEND |
| `app/Console/Commands/NotificationsDispatchUpcomingCommand.php` | Wraps `NotificationDispatcher::sweep()` | NEW |
| `resources/js/Pages/Notifications/Index.vue` | Bell drawer + `markAsRead` | NEW |
| `resources/js/Pages/Account/NotificationPreferences.vue` | Per-channel × per-event-type matrix | NEW |
| `resources/js/Pages/Leaderboards/Index.vue` | Filterable table (window, game, role) | NEW |
| `.github/workflows/a11y.yml` | `@axe-core/cli` against staging URL | NEW |

### Recommended Project Structure (delta over Phase 8 state)

```
apps/web/
├── app/
│   ├── Notifications/                  # NEW
│   │   ├── MatchStartingSoon.php
│   │   ├── MatchCancelled.php
│   │   ├── MatchResultPublished.php
│   │   ├── ClanApplicationApproved.php
│   │   ├── ClanInviteReceived.php
│   │   └── Channels/
│   │       └── DiscordChannel.php
│   ├── Services/
│   │   ├── NotificationDispatcher.php  # NEW
│   │   ├── LeaderboardService.php      # NEW
│   │   ├── BanService.php              # NEW
│   │   └── DisputeService.php          # NEW
│   └── Filament/
│       └── Resources/
│           ├── MatchDisputeResource.php  # NEW
│           └── AbuseReportResource.php   # NEW
├── database/migrations/
│   ├── 2026_05_18_*_create_notifications_table.php          # NEW
│   ├── 2026_05_18_*_create_user_notification_preferences_table.php
│   ├── 2026_05_18_*_create_bans_table.php
│   ├── 2026_05_18_*_create_match_disputes_table.php
│   └── 2026_05_18_*_create_abuse_reports_table.php
├── resources/js/Pages/
│   ├── Notifications/
│   │   └── Index.vue                    # NEW (bell drawer)
│   ├── Account/
│   │   └── NotificationPreferences.vue  # NEW
│   ├── Leaderboards/
│   │   └── Index.vue                    # NEW
│   └── Report/
│       └── Create.vue                   # NEW (abuse report)
└── lang/en/
    ├── notifications.php                # NEW (subject/title/cta keys)
    ├── leaderboards.php                 # NEW
    ├── moderation.php                   # NEW (ban reasons, dispute statuses, audit copy)
    └── a11y.php                         # NEW (skip-to-content, aria-labels)
```

### Pattern 1: Database notification class

**What:** Each notification type is a single PHP class extending `Illuminate\Notifications\Notification` that exposes `via()`, `toArray()` (database channel), and `toDiscord()` (custom channel).

**When to use:** Every Phase 9 notification.

**Example:**

```php
<?php
// Source: Laravel 12 docs /docs/12.x/notifications + Phase 5 outbox shape
// File: app/Notifications/MatchStartingSoon.php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GameMatch; // D-04-03-A LOCKED canonical FQN
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class MatchStartingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GameMatch $match,
        public readonly int $minutesUntilStart, // 60 or 15 — drives copy
    ) {}

    /**
     * Channels chosen at queue time via shouldSend() — gated against
     * user_notification_preferences row (channel + event_type).
     */
    public function via(User $notifiable): array
    {
        return $notifiable->enabledNotificationChannels('match_starting_soon');
        // returns ['database'] | ['database', DiscordChannel::class] | []
    }

    public function toArray(User $notifiable): array
    {
        return [
            'match_id'   => $this->match->id,
            'match_slug' => $this->match->slug ?? null,
            'minutes'    => $this->minutesUntilStart,
            'i18n_key'   => 'notifications.match_starting_soon.title',
        ];
    }

    /**
     * Stable discriminator for the notifications.type column —
     * isolates frontend rendering from PHP namespace churn.
     */
    public function databaseType(User $notifiable): string
    {
        return 'match.starting_soon';
    }

    /**
     * DiscordChannel reads this and writes a discord_outbound_messages row
     * — does NOT call Discord directly (D-004).
     */
    public function toDiscord(User $notifiable): array
    {
        return [
            'message_type'  => 'user_dm',
            'recipient_id'  => $notifiable->discord_id,
            'payload'       => [
                'embed_title'       => __('notifications.match_starting_soon.title', ['min' => $this->minutesUntilStart]),
                'embed_description' => __('notifications.match_starting_soon.body', ['match' => $this->match->title]),
                'cta_url'           => route('matches.show', $this->match),
            ],
        ];
    }
}
```

### Pattern 2: NotificationDispatcher cron sweep

**What:** Scheduled command that scans for matches entering the 1h or 15m window and fires `notify()` on the participant set.

**When to use:** SC-1 dispatch path. Lives in `app/Services/NotificationDispatcher.php` + `routes/console.php`.

**Example:**

```php
<?php
// File: app/Services/NotificationDispatcher.php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Notifications\MatchStartingSoon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class NotificationDispatcher
{
    /**
     * Sweep ±2.5min around the target window so a 1-minute cron tick never
     * misses a target. We track dispatch idempotency via the
     * notifications table (type + data->match_id + data->minutes).
     */
    public function sweepUpcoming(): void
    {
        $this->dispatchWindow(minutes: 60);
        $this->dispatchWindow(minutes: 15);
    }

    private function dispatchWindow(int $minutes): void
    {
        $target = Carbon::now()->addMinutes($minutes);

        GameMatch::query()
            ->where('scheduled_at', '>=', $target->copy()->subMinutes(3))
            ->where('scheduled_at', '<=', $target->copy()->addMinutes(3))
            ->where('status', 'scheduled')
            ->with(['signups.user', 'hostClan.activeMemberships.user'])
            ->each(function (GameMatch $match) use ($minutes): void {
                $participants = $match->signups->pluck('user')
                    ->merge($match->hostClan->activeMemberships->pluck('user'))
                    ->unique('id')
                    ->filter();

                foreach ($participants as $user) {
                    if ($this->alreadyDispatched($user, $match, $minutes)) {
                        continue;
                    }
                    $user->notify(new MatchStartingSoon($match, $minutes));
                }
            });
    }

    private function alreadyDispatched(\App\Models\User $user, GameMatch $match, int $minutes): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('type', 'match.starting_soon')
            ->whereJsonContains('data->match_id', $match->id)
            ->whereJsonContains('data->minutes', $minutes)
            ->exists();
    }
}
```

```php
// File: routes/console.php — append to existing scheduled block

Schedule::command('notifications:dispatch-upcoming')
    ->everyMinute()
    ->withoutOverlapping()   // single-host single-execution
    ->onOneServer();         // multi-host (Railway D-014) single-execution
```

### Pattern 3: Leaderboard aggregator with tagged cache

**What:** `LeaderboardService` returns top-N rows; the cache is keyed by window + game + scope and tagged so domain mutations can flush it.

**When to use:** SC-2 — `/leaderboards` public page + `/clans/{slug}/leaderboard` per-clan view.

**Example:**

```php
<?php
// File: app/Services/LeaderboardService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatchPlayerStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class LeaderboardService
{
    private const WINDOWS = ['7d' => 7, '30d' => 30, 'all' => null];
    private const TTL_FRESH = 600;       // 10 minutes — fresh
    private const TTL_STALE = 3600;      // 1 hour — SWR window via Cache::flexible

    public function topPlayers(string $window, ?int $gameId = null, int $limit = 25): Collection
    {
        return Cache::tags(['leaderboards', "lb:players:{$window}"])->flexible(
            key: "lb:players:{$window}:" . ($gameId ?? 'all') . ":{$limit}",
            ttl: [self::TTL_FRESH, self::TTL_STALE],
            callback: fn () => $this->computePlayerLeaderboard($window, $gameId, $limit),
        );
    }

    private function computePlayerLeaderboard(string $window, ?int $gameId, int $limit): Collection
    {
        $since = self::WINDOWS[$window] === null
            ? null
            : Carbon::now()->subDays(self::WINDOWS[$window]);

        return MatchPlayerStat::query()
            ->selectRaw('player_id, SUM(kills) AS kills, SUM(deaths) AS deaths, COUNT(*) AS matches_played')
            ->when($since, fn ($q) => $q->whereHas('match', fn ($q) => $q->where('scheduled_at', '>=', $since)))
            ->when($gameId, fn ($q) => $q->whereHas('match', fn ($q) => $q->where('game_id', $gameId)))
            ->groupBy('player_id')
            ->orderByRaw('SUM(kills) DESC')
            ->limit($limit)
            ->with('player.user.privacy') // D-018 gate at serialization
            ->get();
    }
}
```

```php
// File: app/Observers/MatchResultObserver.php — append flush hook
public function created(MatchResult $result): void
{
    // ... existing match_result_announce branch ...
    Cache::tags('leaderboards')->flush();
}
```

### Pattern 4: Filament BulkAction with form-collected input

**What:** Filament v3 `BulkAction::make()` collects user input via `->form([...])` and receives it as `$data` in `->action(function (Collection $records, array $data))`.

**When to use:** SC-3 — bulk ban with reason, bulk dispute-resolve with resolution text.

**Example:**

```php
<?php
// File: app/Filament/Resources/UserResource.php — add to ->bulkActions([])

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;

BulkActionGroup::make([
    BulkAction::make('ban')
        ->label(__('moderation.bulk.ban.label'))
        ->icon('heroicon-o-no-symbol')
        ->color('danger')
        ->requiresConfirmation()
        ->modalHeading(__('moderation.bulk.ban.modal_heading'))
        ->modalDescription(__('moderation.bulk.ban.modal_description'))
        ->form([
            Select::make('ban_type')
                ->label(__('moderation.bulk.ban.ban_type'))
                ->options([
                    'temporary' => __('moderation.ban.types.temporary'),
                    'permanent' => __('moderation.ban.types.permanent'),
                ])
                ->required(),
            Textarea::make('reason')
                ->label(__('moderation.bulk.ban.reason'))
                ->required()
                ->minLength(10)
                ->maxLength(500),
            DateTimePicker::make('expires_at')
                ->label(__('moderation.bulk.ban.expires_at'))
                ->visible(fn (callable $get) => $get('ban_type') === 'temporary')
                ->required(fn (callable $get) => $get('ban_type') === 'temporary'),
        ])
        ->action(function (Collection $records, array $data, BanService $bans): void {
            foreach ($records as $user) {
                $bans->issue(
                    user: $user,
                    reason: $data['reason'],
                    banType: $data['ban_type'],
                    expiresAt: $data['ban_type'] === 'temporary'
                        ? Carbon::parse($data['expires_at'])
                        : null,
                    issuedBy: auth()->user(),
                );
            }
        })
        ->deselectRecordsAfterCompletion()
        ->visible(fn () => auth()->user()?->can('moderate-users')),
]),
```

### Pattern 5: WebP variant via medialibrary conversion

**What:** Spatie medialibrary v11 emits WebP from any uploaded image via `->addMediaConversion('name')->format('webp')`. Imagick driver required (verify in Phase 9 plan 0).

**When to use:** SC-4 image perf pass — clan logos, player avatars, article cover images.

**Example:**

```php
<?php
// File: app/Models/Clan.php (existing) — add to InteractsWithMedia trait surface

public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
{
    $this->addMediaConversion('avatar-thumb')
        ->width(48)->height(48)
        ->format('webp')
        ->queued();

    $this->addMediaConversion('avatar-card')
        ->width(200)->height(200)
        ->format('webp')
        ->queued();

    $this->addMediaConversion('avatar-hero')
        ->width(800)->height(800)
        ->format('webp')
        ->queued();
}
```

```vue
<!-- File: resources/js/Components/ClanLogo.vue — render WebP -->
<img
  :src="clan.media[0]?.conversions['avatar-card']"
  :alt="t('clans.logo_alt', { name: clan.name })"
  width="200" height="200"
  loading="lazy"
  decoding="async"
/>
```

### Pattern 6: Strict mode (N+1 catcher) in AppServiceProvider

**What:** `Model::shouldBeStrict()` throws `LazyLoadingViolationException` on accidental lazy loads — dev/test only; production keeps it relaxed.

**When to use:** SC-4 N+1 elimination. Add in plan 0 (Wave 0).

**Example:**

```php
// File: app/Providers/AppServiceProvider.php — boot()

use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // ... existing boot ...

    // N+1 catcher — explodes loud in dev/test, relaxed in production.
    Model::shouldBeStrict(! $this->app->isProduction());

    // Tag-flush observers — covered in Cache Strategy section.
    // RateLimiter::for('public-api', ...) — covered in Security section.
}
```

### Pattern 7: Per-user notification preference matrix

**What:** `user_notification_preferences` is a (user_id, event_type, channel, enabled) row set with composite UNIQUE. `User->enabledNotificationChannels($eventType)` returns the array passed to `Notification::via()`.

**When to use:** Every notification class.

**Example:**

```php
// File: app/Models/User.php — append

use App\Notifications\Channels\DiscordChannel;

public function notificationPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(UserNotificationPreference::class);
}

public function enabledNotificationChannels(string $eventType): array
{
    $prefs = $this->notificationPreferences
        ->where('event_type', $eventType)
        ->keyBy('channel');

    $channels = [];

    // Default-ON web bell unless explicitly disabled
    if (($prefs['database'] ?? null)?->enabled !== false) {
        $channels[] = 'database';
    }

    // Default-OFF Discord DM unless explicitly enabled AND user has discord_id
    if ($this->discord_id && ($prefs['discord'] ?? null)?->enabled === true) {
        $channels[] = DiscordChannel::class;
    }

    return $channels;
}
```

```php
// Migration: create_user_notification_preferences_table.php

Schema::create('user_notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('event_type'); // 'match_starting_soon' | 'match_cancelled' | 'match_result_published' | 'clan_application_decided' | 'clan_invite_received'
    $table->string('channel');    // 'database' | 'discord'
    $table->boolean('enabled')->default(true);
    $table->timestamps();
    $table->unique(['user_id', 'event_type', 'channel'], 'unp_unique');
    $table->index('user_id');
});
```

### Anti-Patterns to Avoid

- **DON'T call Discord webhooks directly from the web tier** — violates D-004 (bot is the only Discord caller). All Discord-bound notifications MUST write to `discord_outbound_messages`; the bot's existing Phase 5 poller picks them up.
- **DON'T add a notification `data->user_id` denormalisation alongside `notifiable_id`** — the polymorphic columns are the canonical identity. Frontend reads `notifiable_id`/`notifiable_type` from the row, not from `data`.
- **DON'T put real-time bell polling in the Vue layer** — round 1 does NOT have WebSockets. The bell badge re-renders on every Inertia navigation (`shared.user.unread_notifications_count` middleware prop). No long-poll, no SSE.
- **DON'T put `Cache::tags()->flush()` inside model `saved` events** — `saved` fires for both `created` AND `updated`; use the specific hook the invalidation actually needs (e.g., `created` for new leaderboard entries, `updated` for stat corrections).
- **DON'T hand-roll a leaderboard refresh job** — `Cache::flexible([fresh, stale])` already gives stale-while-revalidate; a separate `php artisan leaderboards:refresh` cron would duplicate the SWR semantic.
- **DON'T scope BulkActions to "all users" — gate via `spatie/permission`** — every BulkAction MUST have `->visible(fn () => auth()->user()?->can('moderate-users'))` (or equivalent). Filament panel access is necessary but not sufficient.
- **DON'T re-issue the existing `App\Models\Match` alias trick** — D-04-03-A LOCKED requires direct `use App\Models\GameMatch`; the alias-on-import is forbidden across Phase 8 and is forbidden here too.
- **DON'T add the `LogsActivity` trait to the `bans`/`match_disputes`/`abuse_reports` models without verifying it doesn't double-log** — Phase 9 services should call `activity()->log(...)` explicitly (matches Phase 7/8 pattern); the trait is for entity-CRUD audit and can fire spuriously when services already write.
- **DON'T enable `Cache::tags()` on the `file` or `database` cache driver** — only Redis and Memcached support tags [VERIFIED: Laravel 12 cache docs]. Plan 0 MUST verify `CACHE_STORE=redis` in `.env.example`.
- **DON'T use `@axe-core/playwright` for round 1** — adds ~150MB headless Chromium to CI; `@axe-core/cli` is sufficient for SSR'd public pages.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Polymorphic notifications table | Custom `notifications` table with `user_id` FK | `php artisan make:notifications-table` (Laravel built-in) | Polymorphic `notifiable_type/id`, JSON `data`, `read_at`, `databaseType()` discriminator — all stable since Laravel 5.7. UUID variant ships as `uuidMorphs('notifiable')`. [CITED: Laravel 12 notifications docs] |
| Mark-as-read endpoint | Hand-rolled `PUT /notifications/{id}/read` controller + sql update | `$user->notifications()->find($id)?->markAsRead()` | Built-in method writes `read_at = now()`. |
| Stale-while-revalidate cache | `Cache::remember()` + custom refresh-after-N-seconds logic | `Cache::flexible($key, [$fresh, $stale], $cb)` (Laravel 11+) | Serves stale within window, refreshes deferred via response lifecycle. [CITED: Laravel 12 cache docs] |
| Rate-limit middleware | Custom Redis ZSET windowing | `RateLimiter::for('name', fn() => Limit::perMinute(60)->by($req->ip()))` + `throttle:name` | Built-in supports `by()`, `response()`, named limits, Redis backend via `throttleWithRedis()`. [CITED: Laravel 12 routing docs] |
| WebP generation | `Intervention\Image` + manual conversion script | `spatie/laravel-medialibrary`'s `->addMediaConversion()->format('webp')` | Already installed; auto-optimised via `spatie/image-optimizer`; queued by default; replays on `php artisan media-library:regenerate`. |
| Image optimisation pipeline | Shelling out to `cwebp` / `pngquant` | `spatie/image-optimizer` (bundled with medialibrary) | Auto-runs every conversion; ships JpegOptim/Optipng/Pngquant 2/SVGO/Gifsicle/Avifenc binaries (Imagick driver). [CITED: Spatie medialibrary v11 docs] |
| N+1 detection | Manual code review | `Model::shouldBeStrict()` in dev/test + `barryvdh/laravel-debugbar` overlay | Strict mode throws on every accidental lazy load — moves the bug from "we'll notice in prod" to "test suite is RED". [CITED: Laravel 12 eloquent-relationships docs] |
| Per-server cron lock | Custom Redis SETNX | `Schedule::...->onOneServer()` | Built-in atomic-lock dispatcher. [CITED: Laravel 12 scheduling docs] |
| BulkAction confirmation modal | Custom Filament livewire component | `BulkAction::make()->requiresConfirmation()->modalDescription()->form([...])` | Built-in modal supports form fields, custom icon/heading. [CITED: Filament v3 actions/modals docs] |
| Accessibility regression detection | Manual ad-hoc keyboard testing | `@axe-core/cli` in CI | WCAG 2.1 AA rule engine maintained by Deque; 4.11.3 latest 2026-05-04 [VERIFIED: npm registry 2026-05-14]. |

**Key insight:** Phase 9 is plumbing the polish layer onto an already-built foundation. Every "could we hand-roll this?" question has the same answer: NO — every primitive is already in `composer.json` or one `npm install` away in CI-only scope. The danger of this phase is over-engineering: writing a custom DiscordChannel that bypasses the Phase 5 outbox, hand-rolling a leaderboard refresh job that duplicates `Cache::flexible`, or adding a Telescope install when debugbar already shows the query count. The plan-checker should hard-fail on any plan that introduces a custom primitive when a framework-native one is available.

---

## User Notification Preferences Design

**Goal:** Let users opt OUT of any channel × event_type combination, with sane defaults.

**Schema:**

```sql
user_notification_preferences (
  id BIGINT PK,
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  event_type VARCHAR NOT NULL,   -- 'match_starting_soon' | 'match_cancelled' | 'match_result_published' | 'clan_application_decided' | 'clan_invite_received'
  channel VARCHAR NOT NULL,      -- 'database' | 'discord'
  enabled BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ,
  UNIQUE (user_id, event_type, channel)
);
CREATE INDEX ON user_notification_preferences (user_id);
```

**Default policy (when no row exists):**

| Channel | Default state | Rationale |
|---------|---------------|-----------|
| `database` (web bell) | enabled | User opted into the platform; web bell is in-app and unobtrusive |
| `discord` (DM via outbox) | **disabled UNLESS user has `discord_id`** | OAuth means they have a discord_id, so default-on for Discord-authed users (which is everyone — D-002) but the bell-only opt-out path is still available |

**Phase 9 default: enable both for all auth'd users.** Disabled only on explicit user opt-out. The UI is a 5×2 matrix (event_type × channel) of toggles on `/account/notification-preferences`.

**Migration seed:** No seed rows. Absent row = default = enabled. The `enabledNotificationChannels()` resolver applies the defaults; storage only records DEVIATIONS from defaults.

**Per-channel × per-event truth table** (rendered on `/account/notification-preferences`):

| event_type | Web bell (DB) | Discord DM |
|------------|---------------|-----------|
| `match_starting_soon` | ✓ default | ✓ default |
| `match_cancelled` | ✓ default | ✓ default |
| `match_result_published` | ✓ default | ✗ default (avoid spam — match channel already announces) |
| `clan_application_decided` | ✓ default | ✓ default |
| `clan_invite_received` | ✓ default | ✓ default |

The `match_result_published` Discord DM defaults to OFF because the existing Phase 8 `match_result_announce` outbox already posts to the host clan's announce channel — a per-user DM would be duplicative.

---

## Cache Strategy

### Tagged cache keys

| Cache key | Tags | TTL (fresh, stale) | Invalidated by |
|-----------|------|--------------------|-----------------|
| `lb:players:{window}:{game_id}:{limit}` | `leaderboards`, `lb:players:{window}` | (600s, 3600s) | `MatchResultObserver::created/updated` flushes `leaderboards` tag |
| `lb:clans:{window}:{game_id}:{limit}` | `leaderboards`, `lb:clans:{window}` | (600s, 3600s) | same |
| `clan:directory:page:{n}:{tag_filter_hash}` | `clans`, `clans:directory` | (1800s, 7200s) | `ClanObserver::saved`, `ClanTagObserver::saved` flush `clans:directory` |
| `cms:articles:index:page:{n}` | `cms`, `cms:articles` | (300s, 1800s) | `ArticleObserver::saved` flushes `cms:articles` (Phase 7) |
| `home:hero:articles` | `cms`, `cms:home` | (300s, 1800s) | `ArticleObserver::published` flushes `cms:home` |
| `player:profile:{id}:{viewer_tier}` | `players`, `player:{id}` | (300s, 1800s) | `PlayerObserver::saved`, `PlayerPrivacyObserver::saved` flush `player:{id}` |

**Why `Cache::flexible` over `Cache::remember`:** Public pages tolerate up-to-1-hour-stale leaderboards in exchange for sub-100ms render times. `flexible()` serves stale within the second window and queues a background refresh after response (Laravel 11+ feature).

**Why tagged caches require Redis:** `Cache::tags()` is only supported on `redis` and `memcached` cache stores [VERIFIED: Laravel 12 cache docs]. File and database drivers throw. Plan 0 MUST verify `CACHE_STORE=redis` in `.env.example` AND in `config/cache.php`'s default.

### Cache invalidation rule

**Every domain mutation that changes a public surface MUST end its observer hook with a tag flush.** No exceptions. The plan-checker should reject any new model whose observer modifies leaderboard-feeding state (match results, player stats, clan rosters) without a corresponding `Cache::tags(...)->flush()` call.

### Cache key conventions

- Always include scope (`window`, `game_id`, `tag_filter_hash`) in the key. NEVER cache a viewer-specific result under a non-viewer-keyed key.
- For viewer-tier-aware caches (player profiles with privacy gates), include `{viewer_tier}` in the key. The four tiers are `public` (anon), `community` (auth'd non-clan), `clan` (same clan), `private` (self only) per D-018.
- Hash long discriminators (tag filter sets) via `sha1(json_encode($sorted))` to keep keys under 250 chars.

---

## Performance & N+1

### Strict-mode N+1 catcher

`Model::shouldBeStrict(! $this->app->isProduction())` in `AppServiceProvider::boot()` makes every unprepared relationship access throw `Illuminate\Database\LazyLoadingViolationException` in dev/test. CI test runs will RED on every N+1 — the bug becomes impossible to merge.

**Caveat:** Phase 9 plan 0 MUST run the full Pest suite under strict mode and fix every existing N+1 before turning the flag on globally. Expect 20–40 fixes across Phase 7 article-feed code paths, Phase 6 tournament bracket code paths, and Phase 8 match-results pages. Each fix is a `->with([...])` addition in a controller or page resolver.

### N+1 audit pattern (manual sweep)

For each Vue page in `apps/web/resources/js/Pages/`, follow this protocol:

1. Load page in browser with `debugbar` enabled (`APP_DEBUG=true`).
2. Open Queries panel — note the count.
3. Identify any "N similar queries" pattern (the debugbar groups them).
4. Find the controller or page resolver returning the data.
5. Add `->with([relations])` to the Eloquent query.
6. Reload, confirm query count drops to a constant.
7. Add a Pest assertion using `DB::enableQueryLog()` + `count(DB::getQueryLog()) <= N` to lock the behaviour.

### Target query budgets per public page

| Page | Max queries | Notes |
|------|-------------|-------|
| `/` (home) | 12 | Hero articles + featured matches + clan directory preview |
| `/clans` (directory) | 8 | Paginated; tags eager-loaded |
| `/clans/{slug}` | 15 | Roster + recent matches + tags |
| `/players/{slug}` | 10 | Privacy-gated sections each cost 1 query if shown |
| `/matches` (calendar) | 6 | FullCalendar bulk fetch |
| `/matches/{id}` | 12 | Signups + result + per-player stats |
| `/tournaments/{slug}` | 15 | Bracket + standings |
| `/leaderboards` | 4 | Cached aggregate + member look-ups |
| `/articles` (blog index) | 6 | Pagination + featured |
| `/articles/{slug}` | 8 | Author + category + related |

Pest test pattern:

```php
it('renders /leaderboards under 4 queries', function () {
    \DB::enableQueryLog();
    $response = $this->get('/leaderboards');
    $response->assertStatus(200);
    expect(count(\DB::getQueryLog()))->toBeLessThanOrEqual(4);
});
```

### Database indexes added in Phase 9

| Table | Index | Reason |
|-------|-------|--------|
| `notifications` | `(notifiable_type, notifiable_id, read_at)` | Bell badge unread count + chronological list |
| `notifications` | `(notifiable_type, notifiable_id, created_at)` | Chronological pagination |
| `match_player_stats` | `(match_id, player_id)` (already exists) — verify also `(player_id, kills DESC)` for top-players sort | Top-N player aggregation |
| `bans` | `(user_id, expires_at)` | Active-bans lookup ("is this user banned right now?") |
| `match_disputes` | `(status, created_at)` | Open-disputes queue ordering |
| `abuse_reports` | `(status, created_at)` | Review-queue ordering |
| `user_notification_preferences` | composite UNIQUE `(user_id, event_type, channel)` | Default-resolution lookup |

---

## Accessibility (WCAG 2.1 AA)

**Target:** Round 1 ships WCAG 2.1 AA compliance on every public page (anonymous + authenticated) and on the Filament admin panel.

### Success criteria covered

| WCAG SC | Title | How Phase 9 verifies |
|---------|-------|----------------------|
| 1.4.3 | Contrast (Minimum) — 4.5:1 normal, 3:1 large | axe-core scan; Tailwind v4 `@theme` tokens audited against 4.5:1 against `--color-bg` |
| 1.4.11 | Non-text Contrast — 3:1 for UI components | axe-core scan; focus-ring colour audit (`outline: 2px solid var(--color-focus)`) |
| 2.1.1 | Keyboard | Manual smoke — tab through every form, every nav item, every modal |
| 2.1.2 | No Keyboard Trap | Manual smoke — Esc closes modal, Tab cycles through |
| 2.4.2 | Page Titled | Inertia page resolver sets `<title>` via Inertia head shim |
| 2.4.7 | Focus Visible | CSS audit + axe-core; `:focus-visible` rules in `app.css` |
| 3.1.1 | Language of Page | `<html lang="{{ app()->getLocale() }}">` in `app.blade.php` — verify already set in Phase 1 |
| 3.3.2 | Labels or Instructions | axe-core scan flags unlabeled inputs |

### CI integration

```yaml
# File: .github/workflows/a11y.yml (NEW)
name: Accessibility (axe-core)
on:
  push:
    branches: [master]
  pull_request:
    branches: [master]
jobs:
  axe:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 22 }
      - name: Install axe-core CLI
        run: npm install -g @axe-core/cli@^4.11.3
      - name: Start staging tunnel or deploy preview
        # ... project-specific: Railway preview URL, ngrok, or local docker stack ...
      - name: Run axe-core scan
        run: |
          axe https://preview.trenchwars.local/ \
            --tags wcag2aa,wcag21aa \
            --exit \
            --reporter json > axe-report.json
      - uses: actions/upload-artifact@v4
        if: failure()
        with: { name: axe-report, path: axe-report.json }
```

### Manual keyboard test checklist

| Surface | Test |
|---------|------|
| Top nav | Tab through; Enter activates each link |
| Theme toggle | Space activates; aria-pressed updates |
| Mobile menu | Tab into menu; Esc closes; focus returns to trigger |
| Login button | Enter triggers Discord OAuth redirect |
| User menu | Click + keyboard both open; Esc closes |
| Notifications bell | Enter opens drawer; Esc closes; arrow keys cycle items; Enter marks read |
| Notification preferences toggles | Space toggles each switch |
| Match signup modal | Tab through role slots; Enter selects; Esc cancels |
| Article reader | Tab through headings + links; reading order matches DOM |
| Filament admin (BulkActions) | Selection checkboxes keyboard-navigable; BulkAction modal traps focus |
| Filament audit log filters | Date pickers, multi-selects keyboard-operable |

### Focus-ring CSS audit

Tailwind v4 semantic tokens already declare `--color-focus`; Phase 9 plan adds:

```css
/* resources/css/app.css */
*:focus-visible {
  outline: 2px solid var(--color-focus);
  outline-offset: 2px;
  border-radius: 2px;
}
button:focus-visible,
a:focus-visible {
  /* enhanced ring for interactive */
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-focus) 30%, transparent);
}
```

### i18n keys for screen readers

| Key | Copy |
|-----|------|
| `a11y.skip_to_content` | "Skip to content" (already exists in Phase 1) |
| `a11y.notifications.bell_label` | "Notifications (:count unread)" |
| `a11y.notifications.mark_read` | "Mark notification as read" |
| `a11y.menu.toggle_open` | "Open menu" |
| `a11y.menu.toggle_close` | "Close menu" |
| `a11y.theme.switch_to_light` | "Switch to light theme" (exists Phase 1) |
| `a11y.theme.switch_to_dark` | "Switch to dark theme" (exists Phase 1) |
| `a11y.bulk_action.select_row` | "Select :name for bulk action" |

---

## Security Hardening

### Rate limiting matrix

| Limiter name | Limit | Keyed by | Routes |
|--------------|-------|----------|--------|
| `api` (existing default) | 60/min | `$user?->id ?: $request->ip()` | `/api/*` general |
| `public-api` (NEW) | 30/min | `$request->ip()` | `/clans.json`, `/players.json`, `/events/feed.json`, `/search` |
| `auth` (NEW) | 10/min | `$request->ip()` | `/login`, `/auth/discord/callback` |
| `notifications-read` (NEW) | 120/min | `$user->id` | `POST /notifications/{id}/read`, `POST /notifications/read-all` |
| `report-abuse` (NEW) | 5/hour | `$user->id` | `POST /reports` — abuse vector hardening |
| `discord-bot` (existing — keep) | 600/min | `bot_token` | `/api/bot/*` |
| `rcon-internal` (existing — keep) | 600/min | (HMAC signed; throttle defence-in-depth) | `/api/internal/match/*` |

Definition (append to `app/Providers/AppServiceProvider::boot()`):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('public-api', function (Request $request) {
    return Limit::perMinute(30)->by($request->ip());
});

RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('notifications-read', function (Request $request) {
    return Limit::perMinute(120)->by((string) $request->user()?->id);
});

RateLimiter::for('report-abuse', function (Request $request) {
    return Limit::perHour(5)->by((string) $request->user()?->id);
});
```

Attach in routes:

```php
Route::middleware(['throttle:public-api'])->group(function () {
    Route::get('/clans.json', [ClanController::class, 'json']);
    // ...
});
```

### Abuse vector matrix

| Vector | Existing mitigation | Phase 9 add |
|--------|---------------------|-------------|
| Brute-force Discord OAuth | OAuth provider rate-limits | New `auth` limiter (10/min by IP) |
| Spam clan invites | None | (deferred — clan invite throttle is a per-user-per-clan limit; out of round 1) |
| Spam abuse reports | None | New `report-abuse` limiter (5/hour by user) |
| Mass scrape of player profiles | None | `public-api` limiter on JSON endpoints |
| Mass scrape of leaderboards | Cache layer absorbs | Layered: cache + `public-api` throttle |
| Notification bombing (server-side bug) | None | Service-layer dedupe in `NotificationDispatcher::alreadyDispatched` |
| Bypass `PlayerPrivacyGate` via JSON endpoints | Existing gate runs in transformer | Phase 9 audit: every public JSON endpoint MUST instantiate `PlayerPrivacyGate` per row |
| Privilege escalation via Filament panel | Spatie permission gate + `canAccessPanel` | Phase 9 audit: every new BulkAction has `->visible(...)` gate |
| CSRF on `markAsRead` / preferences | Inertia XSRF cookie | Already covered (no new endpoint outside Inertia) |
| Session fixation | Laravel session regenerate on login | Already covered (Phase 1) |
| Discord token leak | Sanctum hash store | Already covered (Phase 5) |
| HMAC replay | Phase 8 60s nonce store | Already covered |

### Report-abuse flow

```
User clicks "Report" on a clan/player/article ──▶ POST /reports (Inertia form)
                                                   │
                                                   ▼
                                          AbuseReport row inserted
                                          status=pending
                                                   │
                                                   ▼
                                          activity_log row (causer=reporter, subject=target)
                                                   │
                                                   ▼
                                          Filament AbuseReportResource queue
                                          (moderator role required)
                                                   │
                                          ┌────────┴────────┐
                                          ▼                 ▼
                                  status=dismissed    status=actioned
                                  (close with note)   (link to ban or content edit)
```

Schema:

```sql
CREATE TABLE abuse_reports (
  id BIGINT PK,
  reporter_user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  target_type VARCHAR NOT NULL,      -- 'App\Models\Clan' | 'App\Models\Player' | 'App\Models\Article' | 'App\Models\GameMatch'
  target_id VARCHAR NOT NULL,
  reason_code VARCHAR NOT NULL,      -- 'harassment' | 'spam' | 'cheating' | 'inappropriate_content' | 'other'
  body TEXT,
  status VARCHAR NOT NULL DEFAULT 'pending', -- 'pending' | 'dismissed' | 'actioned'
  reviewed_by_user_id UUID NULL REFERENCES users(id),
  reviewed_at TIMESTAMPTZ NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ
);
CREATE INDEX ON abuse_reports (status, created_at);
CREATE INDEX ON abuse_reports (target_type, target_id);
CREATE INDEX ON abuse_reports (reporter_user_id);
```

---

## Notifications

(Section consolidating earlier diagram + pattern + preferences.)

**Event types shipped in round 1:**

| event_type | Trigger | Recipients |
|------------|---------|-----------|
| `match_starting_soon` (60min) | NotificationDispatcher cron @ T-60min ± 3min | Match signups + host clan active members |
| `match_starting_soon` (15min) | NotificationDispatcher cron @ T-15min ± 3min | Match signups + host clan active members |
| `match_cancelled` | `MatchObserver::updated` when `status` flips `scheduled` → `cancelled` | Match signups + host clan active members |
| `match_result_published` | `MatchResultObserver::created` (Phase 8 already fires the announce; this adds per-user notification) | Match signups + host clan active members + guest clan active members |
| `clan_application_decided` | `ClanApplicationObserver::updated` when `status` changes from `pending` | Applicant only |
| `clan_invite_received` | `ClanInviteObserver::created` | Invitee only |

**Idempotency:** `NotificationDispatcher::alreadyDispatched()` queries the `notifications` table for an existing row of the same `(type, data->match_id, data->minutes)` tuple. Observer-driven dispatches (`match_cancelled`, `match_result_published`, `clan_application_decided`, `clan_invite_received`) fire once per state transition — no idempotency check needed because the transition itself is the dedupe key.

**Failure mode for Discord DM:** If `discord_outbound_messages` insert fails (DB unavailable), the queued notification throws and Horizon retries. Web bell delivery is independent — the DatabaseChannel writes inline and is not affected.

---

## Leaderboards

(Detail beyond Pattern 3.)

**Aggregation:**

```sql
-- topPlayers (window='7d', game_id=1, limit=25):
SELECT mps.player_id,
       SUM(mps.kills) AS kills,
       SUM(mps.deaths) AS deaths,
       SUM(mps.kills)::float / NULLIF(SUM(mps.deaths), 0) AS kdr,
       COUNT(*) AS matches_played
FROM match_player_stats mps
INNER JOIN matches m ON m.id = mps.match_id
WHERE m.scheduled_at >= NOW() - INTERVAL '7 days'
  AND m.game_id = 1
GROUP BY mps.player_id
ORDER BY SUM(mps.kills) DESC
LIMIT 25;

-- topClans (window='30d', game_id=1, limit=25):
-- aggregate via clan_memberships JOIN at time-of-match
SELECT cm.clan_id,
       SUM(mps.kills) AS kills,
       COUNT(DISTINCT mps.match_id) AS matches_played,
       SUM(CASE WHEN mr.winner_clan_id = cm.clan_id THEN 1 ELSE 0 END) AS wins
FROM match_player_stats mps
INNER JOIN matches m ON m.id = mps.match_id
INNER JOIN clan_memberships cm ON cm.player_id = mps.player_id
       AND cm.active = true
INNER JOIN match_results mr ON mr.match_id = m.id
WHERE m.scheduled_at >= NOW() - INTERVAL '30 days'
  AND m.game_id = 1
GROUP BY cm.clan_id
ORDER BY SUM(mps.kills) DESC
LIMIT 25;
```

**Privacy gating:** `D-018` requires `PlayerPrivacyGate`. The `topPlayers` aggregation includes all players, but at serialization time the Vue page wraps each row in `<PlayerLink :gate="row.gate" />` — if `gate.show_stats === false` the row renders with anonymous label "Anonymous Player" (or similar i18n key) and no link.

**Edge case — kdr divide-by-zero:** `NULLIF(SUM(mps.deaths), 0)` returns NULL when deaths=0; Postgres `1 / NULL = NULL`. Vue renderer treats NULL kdr as "—" or "∞" per design preference.

**Frontend filters:** game_id (default: 1=HLL), role (game_role_id, optional), window (7d/30d/all). Each filter combo gets its own cache key.

---

## Moderator Tooling & Disputes

### Schema

```sql
CREATE TABLE bans (
  id BIGINT PK,
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ban_type VARCHAR NOT NULL,           -- 'temporary' | 'permanent'
  reason TEXT NOT NULL,
  expires_at TIMESTAMPTZ NULL,         -- NULL for permanent
  issued_by_user_id UUID NOT NULL REFERENCES users(id),
  lifted_at TIMESTAMPTZ NULL,
  lifted_by_user_id UUID NULL REFERENCES users(id),
  lift_reason TEXT NULL,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ
);
CREATE INDEX ON bans (user_id, expires_at);
CREATE INDEX ON bans (issued_by_user_id);

CREATE TABLE match_disputes (
  id BIGINT PK,
  match_id UUID NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  raised_by_user_id UUID NOT NULL REFERENCES users(id),
  body TEXT NOT NULL,
  status VARCHAR NOT NULL DEFAULT 'open',   -- 'open' | 'under_review' | 'resolved' | 'rejected'
  resolution VARCHAR NULL,                  -- 'result_amended' | 'result_voided' | 'no_action' | 'sanction_issued'
  resolution_notes TEXT NULL,
  resolved_by_user_id UUID NULL REFERENCES users(id),
  resolved_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ
);
CREATE INDEX ON match_disputes (status, created_at);
CREATE INDEX ON match_disputes (match_id);
```

### Permissions

```
moderator role (new):
  ├── moderate-users       — issue/lift bans
  ├── moderate-disputes    — open/transition/resolve match_disputes
  ├── moderate-content     — edit/unpublish articles
  ├── view-reports         — see abuse_reports queue
  └── manage-reports       — transition abuse_reports status
```

Seed in `RoleAndPermissionSeeder` (existing, extend):

```php
$mod = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
foreach (['moderate-users', 'moderate-disputes', 'moderate-content', 'view-reports', 'manage-reports'] as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}
$mod->syncPermissions(['moderate-users', 'moderate-disputes', 'moderate-content', 'view-reports', 'manage-reports']);
```

### Filament resources

- `UserResource` (existing): extend with `BulkActionGroup::make([ban, unban])`. Show `BanRelationManager` listing current+historical bans.
- `MatchResource` (existing): extend with `BulkAction::make('mark_cancelled')`.
- `MatchDisputeResource` (NEW): standard list+view+resolve resource. Status transitions via `Action::make('transition')` with form choosing target status + notes.
- `AbuseReportResource` (NEW): review queue. `Action::make('dismiss')` + `Action::make('action')` (linking to ban + closing).

### Dispute workflow state machine

```
                    open
                     │
                     ▼
              under_review ──┐
                     │       │
              ┌──────┴────┐  │
              ▼           ▼  │
          resolved    rejected
                     ▲
                     │
                     └ (mod can re-open from rejected back to under_review if needed)
```

Each transition writes an `activity_log` row with `causer = auth()->user()`, `subject = $dispute`, `description = "dispute transition: {from} → {to}"`, plus a `properties->notes` JSON entry. Append-only — Filament UI does NOT expose edit/delete on activity_log rows (CLAUDE.md §6).

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Cache::remember()` for read-heavy public pages | `Cache::flexible([fresh, stale])` (SWR) | Laravel 11 (2024) | Sub-100ms render even on cache miss (stale-served while background refresh runs) |
| `RouteServiceProvider` boot() for rate limiters | `AppServiceProvider` boot() | Laravel 11 (2024) — `RouteServiceProvider` removed | `RateLimiter::for()` now lives in `AppServiceProvider` |
| `app/Console/Kernel.php` schedule() method | `routes/console.php` `Schedule::command()` | Laravel 11 (2024) | Schedule definitions live with route definitions |
| `Model::preventLazyLoading()` only | `Model::shouldBeStrict()` (covers lazy + accessing-without-eager-load + missing-attribute) | Laravel 9+ | Catches more N+1 cases earlier |
| Hand-rolled WebP conversion | `medialibrary v11 ->format('webp')` chain | Spatie medialibrary v10 (2023) | Native support; auto-optimised; queued by default |
| `axe-core` 4.10 | `@axe-core/cli` 4.11.3 / `axe-core` 4.11.4 | 2026-05-04 [VERIFIED: npm registry] | Latest WCAG 2.1 rules + 2.2 partial; pin via cli package |
| Manual `Notification::routeNotificationFor*()` per channel | Built-in routing + per-user `enabledNotificationChannels()` resolver | Laravel 10+ | Channels chosen at queue time; user prefs gate the choice |
| Polymorphic `notifications` table without UUID morphs | `uuidMorphs('notifiable')` for UUID PK projects | Laravel 9+ | Matches our UUID-PK users table |

**Deprecated/outdated:**

- `Kernel.php`-based schedule definitions — Laravel 11 removed `app/Console/Kernel.php`; all scheduling now lives in `routes/console.php`. Phase 7 already moved here (verified above).
- `app/Http/Kernel.php` for middleware groups — Laravel 11 moved to `bootstrap/app.php`. Already in canonical position.
- `RouteServiceProvider` — removed in Laravel 11; rate limiter definitions moved to `AppServiceProvider`. Already in canonical position.
- `Notifiable::notify()` synchronous path (without `ShouldQueue`) for Discord DM — slow path; always `implements ShouldQueue` for any channel that does network I/O.

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `CACHE_STORE=redis` is set in `.env.example` and is the production default | Cache Strategy | If file/database driver is default, `Cache::tags()` throws RuntimeException. Plan 0 MUST verify. [ASSUMED — `.env.example` not re-checked this round; Phase 1 D-021 implies Redis is wired, but not verified for Cache::tags() specifically.] |
| A2 | `players.user_id` exists as FK to `users` (linking player rows back to users for notification routing) | Notifications | If users only link to players via Discord OAuth (no direct FK), `User->notifications` works fine — notifications use `notifiable_id = users.id`. Lower risk. [ASSUMED] |
| A3 | Imagick PHP extension is enabled in the web container (medialibrary WebP requires Imagick OR GD with WebP support) | WebP variants | If only GD is enabled and GD lacks WebP, conversions fail silently. Plan 0 MUST `docker compose exec web php -m | grep -i imagick` to verify. [ASSUMED — Spatie v11 docs say either driver works; specifics for this stack unverified.] |
| A4 | Filament v3.3's `BulkAction::make()->form()` accepts the same field types as a Resource form (TextInput, Select, Textarea, DateTimePicker) | Moderator Tooling | If `BulkAction::form()` is more restrictive in v3.3 than the general action surface, ban-with-form bulk needs a different shape. [ASSUMED based on Filament v3 actions docs which describe BulkAction as extending the base Action class.] |
| A5 | `match_player_stats` table has `(player_id, kills DESC)` composite index, OR `kills` is indexed via the existing `(match_id, player_id)` UNIQUE index well enough that top-N aggregation under 7d window is sub-100ms | Performance | If not, top-players query becomes the bottleneck. Plan should benchmark before locking. [ASSUMED — Phase 8 RESEARCH didn't audit this index pattern for leaderboard reads.] |
| A6 | `discord_outbound_messages` schema accepts a new `message_type` value `user_dm` (existing values from Phase 5/6/8: `match_signup_open`, `match_result_announce`, `bracket_result_announce`, etc.) | Notifications — Discord channel | The Phase 8 CHECK constraint enumerates allowed `message_type` values; Phase 9 plan MUST add `user_dm` to the CHECK list via migration. If we forget, INSERT throws 23514. [ASSUMED — there is a CHECK constraint per Phase 8 verification line 87; the migration name confirms.] |
| A7 | Container PHP 8.4 has `pcntl` / `pgrep`-like features needed by Horizon's signal handling for graceful shutdown (so queued notifications + medialibrary conversions ride Horizon cleanly) | Notifications + WebP | Phase 5 already shipped Horizon; if signal handling were broken, Phase 5 would have caught it. Low risk. [VERIFIED: Phase 5 already runs Horizon in production-like containers.] |
| A8 | Public surface latency target: P95 < 500ms for cached pages, < 1.5s for cold | Performance | If owner has a stricter target (e.g., P95 < 200ms), the cache-flexible TTLs need tightening. Plan-checker should confirm with owner. [ASSUMED — no explicit budget in REQUIREMENTS.md; this is a sensible default for a SSR'd Laravel app on Railway.] |
| A9 | `match_disputes.match_id` references `matches.id` with `ON DELETE CASCADE` is acceptable (disputes vanish if a match is hard-deleted) | Moderator Tooling | If audit retention requires dispute survival past match deletion, use `ON DELETE RESTRICT` and require manual dispute resolution before match deletion. [ASSUMED — D-012 audit retention is indefinite; matches are unlikely to be hard-deleted in round 1. Owner confirmation recommended.] |
| A10 | The `moderator` role does NOT inherit any `admin` role permissions (it's a distinct, lower-privilege role) | Moderator Tooling | If owner expects moderator ⊂ admin, the role hierarchy needs adjustment. Spatie permission doesn't do role inheritance natively — every permission must be explicitly granted. [ASSUMED — keeps the gate clean; if owner wants inheritance, plan adds a `$mod->givePermissionTo($admin->permissions)` seed line.] |

**Assumptions flagged HIGH risk for plan-checker review:** A1 (cache store), A3 (Imagick), A6 (CHECK constraint), A8 (latency target).

---

## Open Questions

1. **WebP fallback strategy for browsers without WebP support (IE11, very old Safari)?**
   - What we know: WebP is supported in Chrome 32+, Firefox 65+, Safari 14+, Edge 18+. Modern browser share >99%.
   - What's unclear: Does the league community include any meaningful IE11 / Safari 13 traffic?
   - Recommendation: Ship WebP only (no JPEG fallback) for round 1. Add `<picture>` source fallback only if monitoring shows >0.5% failure rate.

2. **Notification batching for clan-wide events (e.g., clan_invite_received when 20 invites go out in 5 min)?**
   - What we know: Phase 9 dispatches one notification per recipient per event.
   - What's unclear: Owner preference — batch into "you have 20 new clan invites" vs 20 separate rows?
   - Recommendation: Defer batching to v2; ship one-per-event in round 1.

3. **Should `match_result_published` Discord DM default OFF or default ON?**
   - What we know: Phase 8 already announces results to the host clan's channel.
   - What's unclear: Does the per-user DM add value or feel spammy?
   - Recommendation: Default OFF (rationale in `## User Notification Preferences Design`). Users can opt in via `/account/notification-preferences`.

4. **Ban scope: site-wide only, or per-clan?**
   - What we know: `bans` table has no `clan_id` column in the schema above.
   - What's unclear: Does owner want per-clan bans (user X is banned from clan Y but can rejoin clan Z)?
   - Recommendation: Round 1 is site-wide bans only. Per-clan bans add D-009-class constraint complexity and aren't in CONTEXT.md.

5. **Should the `moderator` role have access to the Filament admin panel, or a separate slimmed-down panel?**
   - What we know: Phase 1 set up a single Filament panel gated to admin role.
   - What's unclear: Owner preference — separate `ModeratorPanel` (Filament v3 supports multiple panels) vs gated resources within the existing panel.
   - Recommendation: Single panel with per-resource gates; moderator role's `canAccessPanel()` returns true but each resource's `viewAny`/`view`/`update` policy gates based on permission. Saves the operational overhead of a second panel.

6. **WebP regeneration for existing media uploaded in Phase 7 articles?**
   - What we know: Phase 7 CMS shipped without WebP conversions defined.
   - What's unclear: Does owner want `php artisan media-library:regenerate` to backfill, or only apply WebP going forward?
   - Recommendation: Plan 9 includes a one-time `regenerate` command run as part of deployment. The conversions are queued so it doesn't block the deploy.

7. **Notification retention — when do old `notifications` rows get pruned?**
   - What we know: Laravel doesn't auto-prune notifications. Phase 7 added `Schedule::command('articles:publish-scheduled')` daily-at; we can add a `notifications:prune` similarly.
   - What's unclear: Retention duration. 30 days? 90 days? Indefinite (consistent with audit retention)?
   - Recommendation: 90 days (3× CON-audit-retention re-visit window). Add `Schedule::call(fn() => DB::table('notifications')->where('created_at', '<', now()->subDays(90))->delete())->daily()->onOneServer();` in plan.

8. **Telescope install — yes or no?**
   - What we know: Debugbar is installed and works. Telescope adds 10+ migrations and an auth-gated dashboard.
   - What's unclear: Does owner want the deeper introspection?
   - Recommendation: NO for round 1. Debugbar covers the N+1 detection use case; Telescope's value is incident debugging in staging/prod, which is a v2 concern. Reduces moving parts.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker + docker compose | All container work (D-021) | ✓ | 29.3.0 | — |
| PHP 8.4 (in web container) | Notifications, services, migrations | ✓ | 8.4 [VERIFIED: Phase 8 verification] | — |
| Postgres 16 (postgres service) | All migrations | ✓ | 16 [VERIFIED: D-021] | — |
| Redis 7 (redis service) | `Cache::tags()`, rate limiter, Horizon | ✓ | 7 [VERIFIED: D-021] | — — REQUIRED for cache tags |
| Node 22 (host) | axe-core/cli in CI | ✓ | 22.22.2 [VERIFIED: 2026-05-14] | — |
| Imagick PHP extension (web container) | medialibrary WebP conversions | [ASSUMED ✓] | unverified | GD with WebP build flag — confirm in plan 0 |
| GitHub Actions CI runners | axe-core/cli a11y workflow | ✓ | ubuntu-latest | — |
| Horizon queue worker | Queued notifications + queued conversions | ✓ | Phase 5 already wired | — |
| `composer show` against installed packages | Verification commands | ✓ | (Phase 8 verified all packages installed) | — |

**Missing dependencies with no fallback:** Imagick verification needed in Plan 0 — if not present, plans need a Dockerfile patch task before any medialibrary WebP work begins. [VERIFY in plan 0: `docker compose exec web php -m | grep -i imagick`]

**Missing dependencies with fallback:** None — every other dep is verified shipped.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4 (via `pestphp/pest ^4.7` + `pestphp/pest-plugin-laravel ^4.0`) |
| Config file | `apps/web/phpunit.xml` + `apps/web/tests/Pest.php` |
| Quick run command | `docker compose exec web ./vendor/bin/pest --filter={TestName} --no-coverage` |
| Full suite command | `docker compose exec web ./vendor/bin/pest --no-coverage` |
| Bot test framework (Phase 5) | Vitest — `docker compose run --rm --no-deps bot sh -c "cd /repo/apps/bot && pnpm test"` |
| a11y test framework (NEW) | `@axe-core/cli` in `.github/workflows/a11y.yml` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SC-1 (notifications hub) | Web bell renders unread notifications | feature | `pest --filter=NotificationsBellTest` | ❌ Wave 0 |
| SC-1 (notifications hub) | `MatchStartingSoon` notification ships database + discord channels | unit | `pest --filter=MatchStartingSoonNotificationTest` | ❌ Wave 0 |
| SC-1 (dispatcher cron) | `NotificationDispatcher::sweepUpcoming` dispatches at T-60min and T-15min | feature | `pest --filter=NotificationDispatcherTest` | ❌ Wave 0 |
| SC-1 (dispatcher idempotency) | Re-running cron does not duplicate notifications | feature | `pest --filter=NotificationDispatcherIdempotencyTest` | ❌ Wave 0 |
| SC-1 (preferences) | `User->enabledNotificationChannels(...)` honours user_notification_preferences | unit | `pest --filter=UserNotificationPreferencesTest` | ❌ Wave 0 |
| SC-1 (DiscordChannel) | `DiscordChannel` writes a `discord_outbound_messages` row with correct payload | feature | `pest --filter=DiscordChannelOutboxTest` | ❌ Wave 0 |
| SC-2 (leaderboard players) | `LeaderboardService::topPlayers('7d', 1, 25)` returns sorted aggregates | feature | `pest --filter=LeaderboardServiceTopPlayersTest` | ❌ Wave 0 |
| SC-2 (leaderboard clans) | `LeaderboardService::topClans('30d', 1, 25)` aggregates via clan_memberships JOIN | feature | `pest --filter=LeaderboardServiceTopClansTest` | ❌ Wave 0 |
| SC-2 (cache) | Second call within window returns cached result; flush via tag invalidates | feature | `pest --filter=LeaderboardCacheTest` | ❌ Wave 0 |
| SC-2 (privacy) | Player with `show_stats=false` renders anonymously on `/leaderboards` | feature | `pest --filter=LeaderboardPrivacyTest` | ❌ Wave 0 |
| SC-3 (BulkAction ban) | Filament UserResource ban BulkAction issues bans + writes activity_log | feature | `pest --filter=UserResourceBanBulkActionTest` | ❌ Wave 0 |
| SC-3 (BulkAction match cancel) | MatchResource bulk-cancel issues `match_cancelled` notifications | feature | `pest --filter=MatchResourceBulkCancelTest` | ❌ Wave 0 |
| SC-3 (dispute workflow) | MatchDisputeResource transitions open → under_review → resolved | feature | `pest --filter=MatchDisputeWorkflowTest` | ❌ Wave 0 |
| SC-3 (abuse review) | AbuseReportResource transitions pending → actioned with linked ban | feature | `pest --filter=AbuseReportWorkflowTest` | ❌ Wave 0 |
| SC-3 (permission gates) | Non-moderator user cannot access BulkActions or new resources | feature | `pest --filter=ModeratorPermissionGateTest` | ❌ Wave 0 |
| SC-3 (audit) | Every moderator action writes an activity_log row | feature | `pest --filter=ModeratorAuditLogTest` | ❌ Wave 0 |
| SC-4 (N+1 strict) | App boot enables `Model::shouldBeStrict()` in non-production | unit | `pest --filter=AppServiceProviderStrictModeTest` | ❌ Wave 0 |
| SC-4 (query budgets) | `/leaderboards` runs ≤4 queries | feature | `pest --filter=LeaderboardsQueryBudgetTest` | ❌ Wave 0 |
| SC-4 (query budgets) | `/clans` runs ≤8 queries | feature | `pest --filter=ClansQueryBudgetTest` | ❌ Wave 0 |
| SC-4 (cache strategy) | `Cache::tags(['leaderboards'])->flush()` invalidates leaderboard caches | feature | `pest --filter=CacheTagFlushTest` | ❌ Wave 0 |
| SC-4 (WebP) | Clan logo upload generates `avatar-thumb.webp`, `avatar-card.webp`, `avatar-hero.webp` | feature | `pest --filter=ClanLogoWebpConversionTest` | ❌ Wave 0 |
| SC-4 (WebP) | Article cover upload generates WebP variants | feature | `pest --filter=ArticleCoverWebpConversionTest` | ❌ Wave 0 |
| SC-5 (a11y) | All public pages render `<html lang>` correctly | feature | `pest --filter=PublicPagesHtmlLangTest` | ❌ Wave 0 |
| SC-5 (a11y) | All form inputs have associated labels (Vue template static scan) | feature | `pest --filter=VueFormLabelsTest` | ❌ Wave 0 |
| SC-5 (a11y) | axe-core CI workflow exists and is wired | manual-only | `.github/workflows/a11y.yml` exists | ❌ Wave 0 |
| SC-5 (rate limit) | `RateLimiter::for('public-api')` defined; 30/min by IP | unit | `pest --filter=RateLimiterDefinitionsTest` | ❌ Wave 0 |
| SC-5 (rate limit) | `/clans.json` throttled by `public-api` limiter (429 after 30 reqs) | feature | `pest --filter=PublicApiThrottleTest` | ❌ Wave 0 |
| SC-5 (report abuse) | POST /reports creates abuse_reports row + activity_log entry | feature | `pest --filter=ReportAbuseTest` | ❌ Wave 0 |
| SC-5 (report abuse) | report-abuse limiter blocks 6th report in 1 hour | feature | `pest --filter=ReportAbuseThrottleTest` | ❌ Wave 0 |
| SC-5 (i18n coverage) | Every notifications.*, leaderboards.*, moderation.*, a11y.* key referenced exists | feature | `pest --filter=Phase9I18nKeyCoverageTest` | ❌ Wave 0 |
| (sentinel) | All Phase 8 GREEN tests stay GREEN (regression) | feature | `pest --no-coverage` (existing 1134 tests) | ✅ exists |

### Sampling Rate

- **Per task commit:** `docker compose exec web ./vendor/bin/pest --filter={TestNameAddedThisTask} --no-coverage`
- **Per wave merge:** `docker compose exec web ./vendor/bin/pest --no-coverage` (full Pest suite; expect 1134 → ~1230+)
- **Phase gate:** Full suite GREEN + Pint GREEN + PHPStan L8 GREEN + a11y CI workflow GREEN before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `apps/web/tests/Feature/Notifications/NotificationsBellTest.php` — covers SC-1 web bell
- [ ] `apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php`
- [ ] `apps/web/tests/Feature/Notifications/NotificationDispatcherTest.php` — covers SC-1 cron sweep
- [ ] `apps/web/tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php`
- [ ] `apps/web/tests/Unit/UserNotificationPreferencesTest.php`
- [ ] `apps/web/tests/Feature/Notifications/DiscordChannelOutboxTest.php`
- [ ] `apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php`
- [ ] `apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php`
- [ ] `apps/web/tests/Feature/Leaderboards/LeaderboardCacheTest.php`
- [ ] `apps/web/tests/Feature/Leaderboards/LeaderboardPrivacyTest.php`
- [ ] `apps/web/tests/Feature/Admin/UserResourceBanBulkActionTest.php`
- [ ] `apps/web/tests/Feature/Admin/MatchResourceBulkCancelTest.php`
- [ ] `apps/web/tests/Feature/Admin/MatchDisputeWorkflowTest.php`
- [ ] `apps/web/tests/Feature/Admin/AbuseReportWorkflowTest.php`
- [ ] `apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php`
- [ ] `apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php`
- [ ] `apps/web/tests/Unit/AppServiceProviderStrictModeTest.php`
- [ ] `apps/web/tests/Feature/Performance/LeaderboardsQueryBudgetTest.php`
- [ ] `apps/web/tests/Feature/Performance/ClansQueryBudgetTest.php`
- [ ] `apps/web/tests/Feature/Cache/CacheTagFlushTest.php`
- [ ] `apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php`
- [ ] `apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php`
- [ ] `apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php`
- [ ] `apps/web/tests/Feature/A11y/VueFormLabelsTest.php`
- [ ] `apps/web/tests/Unit/RateLimiterDefinitionsTest.php`
- [ ] `apps/web/tests/Feature/Security/PublicApiThrottleTest.php`
- [ ] `apps/web/tests/Feature/Reports/ReportAbuseTest.php`
- [ ] `apps/web/tests/Feature/Reports/ReportAbuseThrottleTest.php`
- [ ] `apps/web/tests/Feature/I18n/Phase9I18nKeyCoverageTest.php`
- [ ] `.github/workflows/a11y.yml` — axe-core CI workflow
- [ ] Wave 0 migration trio: `notifications`, `user_notification_preferences`, `bans`, `match_disputes`, `abuse_reports`
- [ ] `app/Notifications/` directory + base `Channels/DiscordChannel.php` skeleton
- [ ] `app/Services/NotificationDispatcher.php`, `LeaderboardService.php`, `BanService.php`, `DisputeService.php` skeletons (throw `RuntimeException("Wave 0 skeleton")` from any method per Phase 8 D-08-01-D idiom)

---

## Security Domain

### Applicable ASVS Categories (Level 1 — per `config.security_asvs_level: 1`)

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes (inherited) | Discord OAuth via Socialite (existing); no Phase 9 change |
| V3 Session Management | yes (inherited) | Laravel session w/ Lax+HttpOnly+Secure-in-prod (existing) |
| V4 Access Control | yes (NEW work) | `spatie/laravel-permission` 7.4 + moderator role + `->visible()` gates on BulkActions; `canAccessPanel()` for Filament |
| V5 Input Validation | yes (NEW work) | Filament form rules + Laravel FormRequest for `/reports` + `spatie/laravel-data` DTOs |
| V6 Cryptography | yes (inherited) | No NEW crypto in Phase 9; existing AES-GCM via Laravel encrypter (CRCON creds in Phase 8); HMAC SHA-256 (Phase 8) |
| V7 Error Handling | yes | Laravel default + Horizon retry; no PII in error responses |
| V8 Data Protection | yes (NEW) | `notifications.data` JSONB stores user-routable identifiers only; no PII beyond `match_id`/`clan_id`/`user_id` references |
| V9 Communications | yes (inherited) | HTTPS in prod via Railway; HMAC for `/api/internal/*` (Phase 8) |
| V11 Business Logic | yes (NEW) | Dispute workflow state machine; ban state machine; abuse-report state machine — each transition gated by permission + activity_log |
| V12 Files & Resources | yes (NEW) | Spatie medialibrary handles upload validation + WebP conversion + MIME-type sniffing; existing |
| V13 API & Web Service | yes (NEW) | `RateLimiter::for('public-api', ...)` 30/min; `throttle:public-api` middleware on all JSON endpoints |
| V14 Configuration | yes | `.env.example` documents all new env shape (none new in Phase 9 — Redis already wired); Horizon secured admin gate |

### Known Threat Patterns for Phase 9

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Bulk-ban authorisation bypass | Elevation of Privilege | `BulkAction::visible(fn() => $user->can('moderate-users'))` + `canAccessPanel()` (defence in depth) |
| Mass abuse reports as harassment | Spoofing/Repudiation | `report-abuse` rate limiter (5/hour per user); abuse-reports themselves are audited via activity_log |
| Notification spoofing via direct DB write | Tampering | Notifications are write-only from the Notification class path; admin Filament UI does not allow editing notification rows (read-only resource if even shipped) |
| Information disclosure via leaderboards bypassing PlayerPrivacyGate | Information Disclosure | Gate runs at serialization; cache key includes `viewer_tier`; tests assert anonymous rendering for `show_stats=false` players |
| Cache poisoning via tag collision | Tampering | Tags namespaced (`leaderboards`, `clans`, `cms`); no user-controlled tag input |
| WebP conversion via uploaded malicious image (image parsing CVEs in Imagick) | DoS / Code Execution | Imagick + spatie/image-optimizer are well-maintained; medialibrary validates MIME before passing to Imagick; container isolation as defence in depth |
| Notification preference manipulation via mass-toggle (causing missed match alerts as form of griefing) | Tampering | Preferences are user-owned (FK cascade on user); only the user themselves or admin can modify; Inertia CSRF gate |
| Match dispute spam | Spoofing | Limit one open dispute per (match_id, raised_by_user_id) — partial unique index (status='open' rows only) |
| Ban evasion via secondary Discord account | Spoofing | D-002 makes Discord ID canonical; ban links to user_id (which is tied 1:1 to discord_id via OAuth). Cross-account ban evasion is out of round-1 scope (Discord-level detection) |
| Rate limit bypass via X-Forwarded-For header injection | Tampering | Laravel `TrustProxies` middleware properly configured (existing); Railway terminates TLS and sets the header |

---

## Common Pitfalls

### Pitfall 1: `Cache::tags()` fails silently on non-Redis store

**What goes wrong:** Developer runs `php artisan test` locally without `CACHE_STORE=redis` set; tests pass under `array` driver but production fails on `Cache::tags(...)->put()` with `BadMethodCallException` ("This cache store does not support tagging.").

**Why it happens:** `array` driver accepts `tags()` but the in-memory implementation is partial — tag-flush often silently no-ops. File and database drivers throw on `tags()` directly.

**How to avoid:**
- Plan 0 verifies `CACHE_STORE=redis` in `.env.example` AND `phpunit.xml`.
- Tests that exercise tagged caches use `Cache::store('redis')->tags(...)` explicitly OR run under the configured default with `CACHE_STORE=redis` in `phpunit.xml`'s `<env>`.
- `LeaderboardCacheTest` includes one case asserting the cache store supports tags (`Cache::supportsTags()`).

**Warning signs:** Test passes locally but fails on CI; or test passes with `--filter` but fails in full suite (test pollution from in-memory tags).

### Pitfall 2: Strict mode breaks existing tests

**What goes wrong:** `Model::shouldBeStrict()` in `AppServiceProvider::boot()` causes Phase 1–8 tests to RED because some controller in Phase 7 lazily accesses `$article->author->name` without eager-loading.

**Why it happens:** Phase 1–8 didn't run under strict mode. The N+1 was tolerated because it didn't crash; now it does.

**How to avoid:** Plan 0 has a dedicated task: "Run full Pest suite with `Model::shouldBeStrict(true)` in test env; identify every LazyLoadingViolationException; fix each with `->with(...)` or `->load(...)`; commit fixes BEFORE the flag flip lands in main." Treat the fixes as bugs being eliminated, not as breakage being created.

**Warning signs:** `LazyLoadingViolationException: Attempted to lazy load [author] on model [App\Models\Article] but lazy loading is disabled.` — that's the catch.

### Pitfall 3: DiscordChannel direct webhook bypasses D-004

**What goes wrong:** Developer naively implements `DiscordChannel::send()` calling `Http::post($webhookUrl, ...)`, bypassing the Phase 5 `discord_outbound_messages` outbox. Now the web tier is making Discord HTTP calls, the bot's rate-limit handling doesn't apply, and Discord embeds-with-buttons-and-actions aren't possible (only the bot can send those).

**Why it happens:** Most Laravel Discord notification tutorials show direct webhook posting. D-004 is project-specific.

**How to avoid:** `DiscordChannel::send()` does ONE thing: `DB::table('discord_outbound_messages')->insert(...)`. No HTTP. The bot picks it up via the existing Phase 5 poll loop. RESEARCH `DiscordChannelOutboxTest` Pest test asserts NO outbound HTTP fires and ONE outbox row is inserted.

**Warning signs:** Mockery or `Http::fake()` expectations in the channel test, or `apps/web` outbound HTTPS in deployment metrics for `discord.com`.

### Pitfall 4: `databaseType()` discriminator collision

**What goes wrong:** Two notification classes return the same `databaseType()` string. Vue page resolves the wrong template for renderer.

**Why it happens:** Stable discriminator strings are easy to clash if not centralised.

**How to avoid:** Add a Pest test `it('every notification class has a unique databaseType discriminator')` that reflects on `app/Notifications/*.php` and asserts no two classes return the same `databaseType()`. Use dot-namespaced strings (`match.starting_soon`, `match.cancelled`, `clan.application_decided`).

**Warning signs:** Bell shows "Unknown notification type" placeholder for some notifications.

### Pitfall 5: Idempotency check race condition

**What goes wrong:** `NotificationDispatcher::alreadyDispatched()` performs a DB read then `notify()` writes; if two cron replicas run simultaneously (mitigated by `onOneServer()` BUT we should still defend), both pass the check and both write.

**Why it happens:** Read-then-write is racy without a lock.

**How to avoid:** `Schedule::command(...)->onOneServer()` is the primary defence (cache lock). Add `withoutOverlapping()` for single-host safety. As a defence-in-depth, add a partial unique index on `(notifiable_type, notifiable_id, type, (data->>'match_id'), (data->>'minutes'))` so duplicates fail at the DB layer — but ONLY for `match_starting_soon` type. (Generic notifications might legitimately repeat.)

**Warning signs:** Duplicate `MatchStartingSoon` rows in `notifications` table when running multiple `php artisan schedule:work` instances locally.

### Pitfall 6: Imagick PHP extension not present in container

**What goes wrong:** First `addMediaConversion(...)->format('webp')` in CI fails: "Image processing failed: Imagick driver not available."

**Why it happens:** PHP 8.4 Docker base images don't always ship Imagick by default; the project's `docker/web/Dockerfile` may not install it.

**How to avoid:** Plan 0 task: `docker compose exec web php -m | grep -i imagick`. If missing, add to Dockerfile:
```dockerfile
RUN apt-get update && apt-get install -y libmagickwand-dev imagemagick \
    && pecl install imagick \
    && docker-php-ext-enable imagick
```
Or use GD with WebP support (verify `gd_info()['WebP Support']`).

**Warning signs:** `ImagickException` in Horizon failed-jobs queue; medialibrary `Media` rows with `manipulations` set but no `conversions` written.

### Pitfall 7: `notifications` table morph columns don't match users PK type

**What goes wrong:** Default `make:notifications-table` ships `morphs('notifiable')` (BIGINT). Our `users.id` is UUID. INSERT fails 22P02 (invalid integer).

**Why it happens:** Laravel default migrations assume BIGINT primary keys.

**How to avoid:** The Wave 0 migration MUST use `uuidMorphs('notifiable')`:
```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuidMorphs('notifiable');  // NOT morphs()
    $table->string('type');
    $table->jsonb('data');             // NOT text — we want indexable JSON
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

**Warning signs:** `SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type bigint`.

### Pitfall 8: BulkAction modal closes silently on validation failure

**What goes wrong:** Filament BulkAction with form validation; user submits with empty `reason`; modal closes; no error displayed; no ban issued.

**Why it happens:** Filament v3 form validation inside BulkAction modals can swallow errors if the form is misconfigured.

**How to avoid:** Always set `->required()` on form fields AND `->minLength(N)` for text inputs; Filament will display the validation error inline before closing. Pest test: `assertHasFormErrors(['reason' => 'required'])` against the BulkAction.

**Warning signs:** Moderators report "I clicked ban and nothing happened"; `bans` table doesn't grow but no logs of an error.

### Pitfall 9: `Cache::flexible()` background refresh dies silently

**What goes wrong:** SWR refresh callback throws (e.g., a leaderboard query fails because of a schema drift); response was already sent so the exception is silently logged; cache stays stale forever.

**Why it happens:** `Cache::flexible()` runs the refresh after `response->send()` via `terminating()`. Exceptions in terminating callbacks don't bubble.

**How to avoid:** Wrap the callback in try/catch and call `report($e)` explicitly so Horizon's exception handler captures it. OR: don't use `flexible()` for queries that can throw — fall back to `remember()` with shorter TTL.

**Warning signs:** Leaderboard shows last week's data permanently; no error visible in HTTP logs but Horizon's exception list shows the throw.

### Pitfall 10: `discord_outbound_messages.message_type` CHECK constraint blocks new value

**What goes wrong:** DiscordChannel inserts `message_type='user_dm'` but Phase 5/6/8 CHECK constraint only allows `match_signup_open`, `match_result_announce`, etc. INSERT fails 23514.

**Why it happens:** The CHECK constraint enumerates known values; Phase 9 introduces a new one.

**How to avoid:** Plan 0 migration extends the CHECK to add `user_dm` (and any other new types). Match the Phase 5 `extend_discord_outbound_message_types_for_*` migration pattern.

**Warning signs:** `SQLSTATE[23514]: Check violation: 7 ERROR: new row for relation "discord_outbound_messages" violates check constraint`.

### Pitfall 11: axe-core CI scans behind authentication

**What goes wrong:** axe-core CI workflow scans `/admin/...` route, hits the login redirect, scans the LOGIN page instead of the admin surface. CI passes but admin a11y is untested.

**Why it happens:** axe-core/cli has no built-in auth; it scans whatever the URL responds with.

**How to avoid:** Round 1 scans ONLY public routes (homepage, /clans, /players, /matches, /tournaments, /articles, /leaderboards). Admin a11y is a manual smoke. If admin scanning is needed, swap to `@axe-core/playwright` and add login automation — but that's a v2 enhancement.

**Warning signs:** axe-core report consistently scans 200 response of `/login`; admin pages absent.

### Pitfall 12: `Model::shouldBeStrict()` breaks Pest's `RefreshDatabase` trait in subtle ways

**What goes wrong:** Strict mode + `RefreshDatabase` + a factory that creates a parent without setting child relations → some test asserts `$child->parent->name` after creation but the relation isn't loaded. Test RED.

**Why it happens:** Factories don't auto-load relations.

**How to avoid:** Tests assert against unloaded relations should `$model->load('relation')` first, OR use `->load('relation')` on the freshly-created model. Don't disable strict mode for tests — the test bug is real, fix it.

**Warning signs:** `LazyLoadingViolationException` thrown from test assertions; resolve by adding `->load([...])` in the assertion's setup.

---

## Code Examples

Verified patterns aggregated above; for ready-reference convenience:

### Notification with conditional channels

(See Pattern 1.)

### Cron-driven dispatcher

(See Pattern 2.)

### Leaderboard service

(See Pattern 3.)

### Filament BulkAction with form

(See Pattern 4.)

### WebP conversion on a media model

(See Pattern 5.)

### Strict mode + tag-flush observer

(See Pattern 6 + below.)

```php
<?php
// File: app/Observers/MatchResultObserver.php — extend

use Illuminate\Support\Facades\Cache;

public function created(MatchResult $result): void
{
    // ... existing match_result_announce branch ...
    Cache::tags('leaderboards')->flush();
}

public function updated(MatchResult $result): void
{
    // ... existing branches ...
    if ($result->wasChanged(['allies_score', 'axis_score', 'winner_clan_id'])) {
        Cache::tags('leaderboards')->flush();
    }
}
```

```php
<?php
// File: app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Existing boot work...

        // SC-4: N+1 catcher — explodes loud in dev/test, relaxed in production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // SC-5: rate limiters
        RateLimiter::for('public-api', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('notifications-read', fn (Request $r) => Limit::perMinute(120)->by((string) $r->user()?->id));
        RateLimiter::for('report-abuse', fn (Request $r) => Limit::perHour(5)->by((string) $r->user()?->id));
    }
}
```

---

## Sources

### Primary (HIGH confidence — official docs fetched 2026-05-14)

- Laravel 12.x notifications — https://laravel.com/docs/12.x/notifications [VERIFIED via WebFetch 2026-05-14]
- Laravel 12.x scheduling — https://laravel.com/docs/12.x/scheduling [VERIFIED via WebFetch 2026-05-14]
- Laravel 12.x cache — https://laravel.com/docs/12.x/cache [VERIFIED via WebFetch 2026-05-14]
- Laravel 12.x rate limiting — https://laravel.com/docs/12.x/routing#rate-limiting [VERIFIED via WebFetch 2026-05-14]
- Laravel 12.x eloquent N+1 — https://laravel.com/docs/12.x/eloquent-relationships#eager-loading [VERIFIED via WebFetch 2026-05-14]
- Laravel 12.x telescope — https://laravel.com/docs/12.x/telescope [VERIFIED via WebFetch 2026-05-14, evaluated and rejected for round 1]
- Filament v3 BulkActions / Modals — https://filamentphp.com/docs/3.x/tables/actions + https://filamentphp.com/docs/3.x/actions/modals#modal-forms [VERIFIED via WebFetch 2026-05-14]
- Spatie medialibrary v11 conversions — https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions [VERIFIED via WebFetch 2026-05-14]
- Spatie medialibrary v11 optimization — https://spatie.be/docs/laravel-medialibrary/v11/converting-images/optimizing-converted-images [VERIFIED via WebFetch 2026-05-14]
- WCAG 2.1 AA quickref — https://www.w3.org/WAI/WCAG21/quickref/?versions=2.1&levels=aa [VERIFIED via WebFetch 2026-05-14]
- `@axe-core/cli` package — https://www.npmjs.com/package/@axe-core/cli [VERIFIED: npm view 2026-05-14 → 4.11.3]
- `axe-core` package — https://www.npmjs.com/package/axe-core [VERIFIED: npm view 2026-05-14 → 4.11.4]
- Phase 8 PHASE-VERIFICATION.md — `/home/rtx/projects/trench-wars/.planning/phases/08-rcon-automation/08-PHASE-VERIFICATION.md` [VERIFIED]
- Project CLAUDE.md — `/home/rtx/projects/trench-wars/CLAUDE.md` [VERIFIED]
- composer.json — `/home/rtx/projects/trench-wars/apps/web/composer.json` [VERIFIED 2026-05-14]
- package.json — `/home/rtx/projects/trench-wars/apps/web/package.json` [VERIFIED 2026-05-14]
- `composer show` for installed versions — [VERIFIED 2026-05-14 in container]

### Secondary (MEDIUM confidence)

- @axe-core/playwright considered but rejected as overkill for SSR public surface — based on standard CI integration philosophy on dequelabs/axe-core-npm README.

### Tertiary (LOW confidence)

- None — every Phase 9 recommendation is sourced from an official primary doc or verified package registry.

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — every package version verified in container; every primary library has its v3.x/v11.x/v12.x official doc cited.
- Architecture: HIGH — Phase 5 outbox pattern (D-004 compliance), Phase 7 cache-flush observer pattern, Phase 8 BulkAction surface are all extension of established Phase 5–8 idioms with no new architectural primitives.
- Pitfalls: HIGH — 12 pitfalls each map to a known Laravel/Filament/Spatie footgun OR a known Phase 1–8 design decision; mitigations are concrete.
- Security: MEDIUM-HIGH — rate-limit numbers (30/min, 5/hour, etc.) are owner-tunable starting values, not absolute; ASVS Level 1 coverage is comprehensive for the new surface; report-abuse design is greenfield (Phase 9 first appearance) so awaiting owner feedback on shape.
- Validation Architecture: HIGH — every SC has 1+ Pest test mapped; Wave 0 gap list is complete; framework choices match Phase 1–8.

**Research date:** 2026-05-14
**Valid until:** 2026-06-13 (30 days — stable Laravel 12.x / Filament 3.x / Spatie v11 ecosystem; flag re-verification needed for any package bumped within the window)
