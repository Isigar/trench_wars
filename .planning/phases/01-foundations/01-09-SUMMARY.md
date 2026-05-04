---
phase: 01-foundations
plan: 09
subsystem: discord-oauth-and-first-login-provisioning
tags:
  - laravel-socialite-5-27
  - socialiteproviders-discord-4-2
  - oauth-state-csrf-default
  - oauth-scopes-identify-email
  - login-event-listener
  - db-transaction-first-login
  - idempotent-relogin
  - inertia-shared-auth-prop
  - native-anchor-not-inertia-link
  - inline-svg-discord-brand
  - phpstan-level-8-clean
  - pint-laravel-preset
  - pest-mocked-socialite
dependency_graph:
  requires:
    - pest-4-test-framework                # plan 01-05 — RefreshDatabase + Pest helpers
    - phpstan-level-8-gate                 # plan 01-05 — extended over new controllers + listener
    - pint-laravel-preset                  # plan 01-05 — auto-fixed 3 style issues across 7 files
    - inertia-shared-auth-prop             # plan 01-06 — HandleInertiaRequests::share() returns id/discord_id/username/avatar_url
    - i18n-shared-translations-prop        # plan 01-08 — t('auth.discord.button_label'), t('home.welcome_back', {name}), t('home.next_steps')
    - users-table                          # plan 01-10 — discord_id text UNIQUE NOT NULL; updateOrCreate keys off it (D-002, T-1-03)
    - user-eloquent-model                  # plan 01-10 — fillable shape consumed by DiscordController upsert
    - players-table                        # plan 01-10 — slug UNIQUE; user_id UNIQUE FK enforces 1:1
    - player-privacy-table                 # plan 01-10 — D-018 default columns + CHECK in (public,community,clan,private)
    - discord-services-config              # plan 01-04 — services.discord.{client_id,client_secret,redirect}
    - .env-discord-oauth-block             # plan 01-04 — DISCORD_CLIENT_ID/SECRET/REDIRECT_URI placeholders
  provides:
    - laravel-socialite-installed          # composer require laravel/socialite ^5.27 + socialiteproviders/discord ^4.2 (v5.27.0 + v4.2.0 resolved)
    - discord-socialite-provider-binding   # AppServiceProvider::boot() registers Discord via Event::listen(SocialiteWasCalled) — Laravel 11+ pattern, no EventServiceProvider
    - login-event-listener-binding         # AppServiceProvider::boot() binds Login event → ProvisionFirstLogin listener
    - discord-oauth-redirect-route         # GET /auth/discord/redirect → DiscordController@redirect (guest middleware) named auth.discord.redirect
    - discord-oauth-callback-route         # GET /auth/discord/callback → DiscordController@callback (guest middleware) named auth.discord.callback
    - logout-route                         # POST /auth/logout → LogoutController invocable (auth middleware) named auth.logout
    - discord-controller                   # apps/web/app/Http/Controllers/Auth/DiscordController.php — redirect() + callback(); upserts user by discord_id; calls Auth::login + session regenerate; surfaces InvalidStateException as cancelled flash
    - logout-controller                    # apps/web/app/Http/Controllers/Auth/LogoutController.php — Auth::logout + session invalidate + token regenerate
    - provision-first-login-listener       # apps/web/app/Listeners/ProvisionFirstLogin.php — DB::transaction wraps last_login_at touch + Player creation (slug = Str::slug(username) + '-' + 4 random lowercase chars) + PlayerPrivacy creation with D-018 defaults; idempotent on re-login
    - login-button-component               # apps/web/resources/js/components/LoginButton.vue — native <a> (not Inertia <Link>) styled as primary CTA; w-full mobile / md:w-auto; icon at gap-2
    - discord-icon-component               # apps/web/resources/js/components/icons/DiscordIcon.vue — inline brand SVG, currentColor fill
    - home-vue-auth-aware                  # apps/web/resources/js/pages/Home.vue — logged-out: tagline + subcopy + LoginButton; logged-in: welcome_back + next_steps
    - discord-oauth-feature-tests          # apps/web/tests/Feature/Auth/DiscordOAuthTest.php — redirect URL contains discord.com + client_id + scope=identify+email; happy callback logs user in; InvalidStateException → cancelled flash
    - first-login-provisioning-tests       # apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php — first login = 1 of each row; D-018 defaults; re-login idempotent (0 new rows); last_login_at updates on every login
  affects:
    - "01-12 (Filament v3 panel) — admin route /admin requires authenticated user; the Discord OAuth flow is the only entry path. FilamentUser::canAccessPanel + 'admin-access' permission gate enforces RBAC on top."
    - "01-13 (activity log) — Login + Logout events are NOT subscribed to spatie/activitylog by default; auditing first-login provisioning lands in plan 13."
    - "Phase 2 clans — Player creation here is the precondition for ClanMembership; slug uniqueness collisions become possible at scale and may need a retry loop later."
    - "Phase 5 Discord bot — bot identifies league members by Discord ID, which is canonical here (D-002); the bot never writes to users/players/player_privacy."
tech_stack:
  added:
    - "laravel/socialite ^5.27 (resolved 5.27.0)"
    - "socialiteproviders/discord ^4.2 (resolved 4.2.0)"
    - "transitive: socialiteproviders/manager 4.9.2, league/oauth1-client 1.11.0, firebase/php-jwt 7.0.5, paragonie/random_compat 9.99.100, paragonie/constant_time_encoding 3.1.3, phpseclib 3.0.52"
  patterns:
    - "Event::listen(SocialiteWasCalled) provider registration — Laravel 11+ pattern; no EventServiceProvider. Lives in AppServiceProvider::boot()."
    - "updateOrCreate by canonical OAuth ID (discord_id) — single insert/update path per login; DB-level UNIQUE absorbs concurrent first-login races (T-1-03)."
    - "Login event listener → DB::transaction — first-login provisioning is atomic; failure rolls back user/player/player_privacy together (no partial state)."
    - "Idempotent listener — re-login no-op via `if ($user->player !== null) return`; row counts stay flat across re-logins."
    - "Native <a> for OAuth redirects — Inertia <Link> XHRs the URL and breaks the cross-origin redirect; full document navigation is required."
    - "Inline SVG brand icon (DiscordIcon.vue) — lucide-vue-next has no Discord mark; brand SVG with `fill='currentColor'` aligns with theme tokens (--color-accent-fg)."
    - "Mockery-backed Socialite facade for callback tests — Socialite::shouldReceive('driver')->with('discord')->andReturnSelf(); Socialite::shouldReceive('user')->andReturn($fakeUser). Real driver runs for the redirect-URL test (config()->set inline)."
key_files:
  created:
    - apps/web/app/Http/Controllers/Auth/DiscordController.php
    - apps/web/app/Http/Controllers/Auth/LogoutController.php
    - apps/web/app/Listeners/ProvisionFirstLogin.php
    - apps/web/resources/js/components/LoginButton.vue
    - apps/web/resources/js/components/icons/DiscordIcon.vue
    - apps/web/tests/Feature/Auth/DiscordOAuthTest.php
    - apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php
  modified:
    - apps/web/composer.json
    - apps/web/composer.lock
    - apps/web/app/Providers/AppServiceProvider.php
    - apps/web/routes/web.php
    - apps/web/resources/js/pages/Home.vue
decisions:
  - "Use Native <a href='/auth/discord/redirect'> instead of Inertia <Link> in LoginButton — Inertia <Link> issues an XHR with the X-Inertia header which would never complete the cross-origin Discord redirect. Documented inline in LoginButton.vue."
  - "Cast Socialite::driver('discord') return type to AbstractProvider in DiscordController::redirect() so PHPStan L8 can resolve the scopes() method (Contracts\\Provider has no scopes()). One PHPStan error fixed inline (Rule 3, blocking)."
  - "Configure services.discord.{client_id,client_secret,redirect} via config()->set in tests rather than adding to phpunit.xml — keeps OAuth-related env out of the committed test config and matches the 'tests don't need real secrets' contract."
  - "Slug derivation = Str::slug(username) + '-' + 4 random lowercase chars — collision-tolerant for the small volume expected in P1; a future plan can add a retry loop if the league grows past ~10K players (the birthday-bound on a 4-char [a-z0-9] suffix is ~1300)."
  - "Listener narrows on `if (! $user instanceof User) return` — defends against future event-source changes (admin impersonation, queue worker re-dispatch). Keeps PHPStan happy and the listener single-responsibility."
  - "POST /auth/logout under auth middleware (not Sanctum) — P1 ships session-cookie auth only; Sanctum lands in Phase 5 when the bot needs API tokens."
metrics:
  duration: "5 min"
  completed: "2026-05-04T17:59:34Z"
  tasks_completed: 3
  files_created: 7
  files_modified: 5
  tests_added: 7
  total_tests_passing: 35
---

# Phase 1 Plan 9: Discord OAuth + First-Login Provisioning Summary

End-to-end Discord OAuth wired with a `ProvisionFirstLogin` listener that creates `users` + `players` + `player_privacy` rows inside a single `DB::transaction` with D-018 privacy defaults; idempotent on re-login; full Pest coverage with mocked Socialite.

## Objective Achieved

The biggest user-visible deliverable in P1 (SC-1 from ROADMAP): a logged-out visitor lands on `/`, clicks "Log in with Discord", completes OAuth, and lands logged in with `users` + `players` + `player_privacy` rows automatically created. The flow is enforced atomically via `DB::transaction`, defended against fixation via `$request->session()->regenerate()`, and tested end-to-end with `Socialite::shouldReceive` mocking — the only step that can't be automated is the live round-trip to discord.com, which is the manual smoke gate per `01-VALIDATION.md`.

## What Was Built

**OAuth provider registration (`AppServiceProvider::boot()`)** — `Event::listen(SocialiteWasCalled, …)` extends Socialite with the `SocialiteProviders\Discord\Provider` (Laravel 11+ pattern; no `EventServiceProvider` in the skeleton). Same boot binds `Login` events to `ProvisionFirstLogin`.

**`DiscordController`** — `redirect()` returns `Socialite::driver('discord')->scopes(['identify','email'])->redirect()` (we deliberately do NOT call `->stateless()` so the OAuth state CSRF check stays on, mitigating T-1-01). `callback()` upserts the `User` by `discord_id` (D-002 canonical identity), calls `Auth::login(remember: true)`, regenerates the session (T-1-06 anti-fixation), and redirects to `/` with a localised success flash. `InvalidStateException` is caught and surfaces as `auth.discord.error.cancelled`; any other `Throwable` falls back to `auth.discord.error.provider`.

**`ProvisionFirstLogin` listener** — Wraps the entire write path in `DB::transaction`. Touches `last_login_at` on every login. On first login (`$user->player === null`) creates the `Player` (slug = `Str::slug(username) + '-' + 4 random lowercase chars`) and the `PlayerPrivacy` row with D-018 defaults: `show_to='community'`, `show_real_name=false`, all four other section booleans `true`. Re-login is a no-op for the player/privacy creation block.

**`LogoutController`** — Single-action invocable controller; `Auth::logout()` + `session()->invalidate()` + `regenerateToken()`.

**Routes (`routes/web.php`)** — `auth.discord.redirect` and `auth.discord.callback` under `guest` middleware (so an authenticated visitor doesn't loop through OAuth needlessly); `auth.logout` (POST) under `auth` middleware.

**Frontend** — `LoginButton.vue` is a native `<a>` (NOT Inertia `<Link>` — `<Link>` would XHR the cross-origin Discord redirect and break it) styled as the primary CTA with the inline-SVG `DiscordIcon`. `Home.vue` now branches on `usePage().props.auth`: logged-out shows tagline + subcopy + `<LoginButton>`; logged-in shows `home.welcome_back` greeting + `home.next_steps` copy. Every visible string still flows through `t()` (`NoHardcodedStringsTest` from plan 08 still passes).

**Tests (7 new Pest cases)** — `DiscordOAuthTest`: redirect URL contains `discord.com` + `client_id` + `scope=identify+email`; happy callback logs the user in; `InvalidStateException` → cancelled flash. `FirstLoginProvisioningTest`: first login creates exactly 1 of each row; D-018 defaults applied; re-login idempotent; `last_login_at` updates on every login (uses `$this->travel(5)->minutes()` to advance the clock between calls).

## Threat Surface (Recap from Plan)

| Threat ID | Mitigation In This Plan |
|-----------|-------------------------|
| T-1-01 (OAuth state CSRF tampering) | Did NOT call `->stateless()`; Socialite enforces state. Tested via `InvalidStateException` callback case. |
| T-1-02 (redirect_uri tampering) | `services.discord.redirect` reads `DISCORD_REDIRECT_URI` only — single source of truth; Discord portal exact-match check enforces server-side. No code change here vs plan 04. |
| T-1-03 (concurrent first-login race) | `users.discord_id` is `UNIQUE` (plan 10); `User::updateOrCreate` is the upsert path; the listener's player creation is gated by `$user->player === null`. |
| T-1-06 (CSRF post-login) | `$request->session()->regenerate()` runs after `Auth::login`. |
| T-1-07 (session hijacking) | Session driver=redis (prod) / array (test); `SameSite=Lax` + `HttpOnly` configured in plan 04. |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Cast `Socialite::driver('discord')` to `AbstractProvider` for PHPStan L8**
- **Found during:** Task 1 (post-pint, pre-commit PHPStan run)
- **Issue:** `Socialite::driver('discord')` returns `Laravel\Socialite\Contracts\Provider`, which has no `scopes()` method. Concrete `Two\AbstractProvider` does. PHPStan L8 fails: `Call to an undefined method Laravel\Socialite\Contracts\Provider::scopes()`.
- **Fix:** Imported `Laravel\Socialite\Two\AbstractProvider` and added a `/** @var AbstractProvider $driver */` annotation before calling `->scopes(...)`. Also annotated the `redirect()` return as `RedirectResponse` for the same reason. No runtime behaviour change — Socialite returns the concrete `AbstractProvider` at runtime; this is a pure type-narrowing fix.
- **Files modified:** `apps/web/app/Http/Controllers/Auth/DiscordController.php`
- **Commit:** `85f555e` (rolled into Task 1 commit)

### Pint Auto-corrections

Pint applied 3 style fixes across the seven new/edited PHP files: `concat_space`/`unary_operator_spaces` rules on the listener, `fully_qualified_strict_types`/`ordered_imports` on `AppServiceProvider` (added `use SocialiteProviders\Discord\Provider;`), and `fully_qualified_strict_types`/`ordered_imports` on `DiscordOAuthTest` (added `use App\Models\User;`). All three fixes are correct and were preserved.

### Other Deviations

**Configuration of OAuth credentials in tests** — Rather than add `DISCORD_CLIENT_ID/SECRET/REDIRECT_URI` to `phpunit.xml`, the tests set them inline via `config()->set()` in `beforeEach`. This keeps committed test config free of OAuth-shaped env keys and matches the convention that "tests don't need real secrets". Pre-existing `phpunit.xml` is unchanged.

## CLAUDE.md Adherence

- §1 D-021 — every artisan/composer/pest/pint/phpstan invocation went through `docker compose exec -T web …` (no host PHP).
- §3 — Pint clean (`./vendor/bin/pint --test` reports `PASS 59 files`); PHPStan L8 clean (`No errors`); the existing `phpstan-baseline.neon` was NOT regenerated.
- §4 — Pest, not PHPUnit; `it(...)` syntax; Feature tests under `tests/Feature/Auth/`.
- §6 — No `<meta name="csrf-token">` added (Pitfall 3 — Inertia handles XSRF via cookie). Discord `redirect_uri` reads from env (Pitfall 2). Session regenerate happens after `Auth::login` (T-1-06).
- §7 — Every new visible string flows through `t()` (Vue) or `__()` (PHP `auth.discord.error.cancelled` / `auth.discord.error.provider` / `auth.discord.success`). `NoHardcodedStringsTest` still green.
- §8 — Discord ID is canonical (D-002): `User::updateOrCreate(['discord_id' => …])`; bot/RCON architecture untouched.

## Test Results

```
Tests:    35 passed (128 assertions)
Duration: 0.81s
```

7 new tests added (3 in `DiscordOAuthTest`, 4 in `FirstLoginProvisioningTest`); 28 prior tests still green. Pint: 59 files clean. PHPStan L8: 24 files, no errors.

## Manual Smoke Gate (Per VALIDATION.md)

Live Discord OAuth round-trip is NOT covered by automated tests (cannot be mocked end-to-end). Operator gate:

1. Create dev Discord app at <https://discord.com/developers/applications>.
2. OAuth2 → Redirects → add `http://localhost:8000/auth/discord/callback` (no trailing slash, exact case).
3. Set `apps/web/.env`: `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback`.
4. `make up`; visit <http://localhost:8000/>; click "Log in with Discord".
5. Verify: redirect lands on `/` with logged-in user; DB has 1 row each in `users`/`players`/`player_privacy`. Re-login → row counts unchanged. `last_login_at` advanced.

## Commits

| Task | Commit  | Description                                                                  |
| ---- | ------- | ---------------------------------------------------------------------------- |
| 1    | 85f555e | feat(01-09): wire Discord OAuth flow (Socialite + listener + routes)         |
| 2    | 049435e | feat(01-09): wire Home.vue to Discord OAuth via LoginButton + DiscordIcon    |
| 3    | 9b277c1 | test(01-09): add Pest feature tests for Discord OAuth + first-login flow    |

## Self-Check: PASSED

- apps/web/app/Http/Controllers/Auth/DiscordController.php — exists
- apps/web/app/Http/Controllers/Auth/LogoutController.php — exists
- apps/web/app/Listeners/ProvisionFirstLogin.php — exists
- apps/web/resources/js/components/LoginButton.vue — exists
- apps/web/resources/js/components/icons/DiscordIcon.vue — exists
- apps/web/tests/Feature/Auth/DiscordOAuthTest.php — exists
- apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php — exists
- Commit 85f555e — present in `git log`
- Commit 049435e — present in `git log`
- Commit 9b277c1 — present in `git log`
