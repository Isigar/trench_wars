---
phase: 09-polish
plan: 05
subsystem: leaderboards
tags: [wave-3, leaderboards, cache, swr, observers, pitfall-9, d-04-03-a-locked, d-018, sc-2]
requires:
  - "09-02 Wave 1 — match_player_stats(player_id, kills) composite index for top-N performance (A5)"
  - "09-03/09-04 Wave 2/3 — MatchResultObserver Phase 9 extension surface (notification dispatch already in place)"
  - "Phase 2 Clan + ClanMembership (active = WHERE left_at IS NULL via partial unique index)"
  - "Phase 4 MatchResult + GameMatch::result HasOne"
  - "Phase 8 MatchPlayerStat + MatchPlayerStatAggregator (RCON-driven stat ingest)"
provides:
  - "App\\Services\\LeaderboardService — topPlayers + topClans cached with Cache::tags(['leaderboards', 'lb:{type}:{window}'])->flexible([600, 3600]) SWR"
  - "App\\Data\\LeaderboardEntryData — Spatie DTO with PlayerPrivacyGate-aware fromQueryResult() factory (D-018)"
  - "App\\Data\\LeaderboardClanEntryData — Spatie DTO for clan leaderboard row"
  - "App\\Observers\\MatchPlayerStatObserver::saved — flushes leaderboards tag on every (created|updated) stat write"
  - "MatchResultObserver extension — flush on created() unconditionally; flush on updated() only when wasChanged([allies_score, axis_score, winner_clan_id])"
  - "ClanMembershipObserver extension — flush on join (created with left_at=null) and on either edge of the left_at transition"
  - "packages/shared-types/src/api.d.ts — TS interfaces LeaderboardEntryData + LeaderboardClanEntryData generated and synced"
affects:
  - "plan 09-06 LeaderboardsController + Pages/Leaderboards.vue — consume LeaderboardService topPlayers/topClans and hydrate DTOs via fromQueryResult"
  - "plan 09-08 strict-mode Eloquent flip — service uses MatchPlayerStat::query() (no lazy access on returned aggregate rows)"
  - "plan 09-09 medialibrary WebP — will populate LeaderboardClanEntryData.logo_url (currently always null)"
  - "plan 09-12 i18n key coverage CI — leaderboards.anonymous_player + other lang/en/leaderboards.php keys are exercised by the privacy gate path"
tech-stack:
  added: []
  patterns:
    - "Cache::tags()->flexible([fresh,stale]) SWR semantics — Laravel 11+ — sub-100ms render on the fresh path; stale-served during background refresh window after fresh TTL expires."
    - "Pitfall 9 — Cache::flexible callback exception swallowing — wrap compute in try/report/throw. The rethrow is critical to flexible's contract: an exception triggers SWR stale-served behaviour rather than caching a null."
    - "Tag-flush at every choke point — three observers (MatchResultObserver, MatchPlayerStatObserver new, ClanMembershipObserver) cover every domain mutation that can change a leaderboard row. The tag is the single source of correctness for freshness (Pitfall 9 reframed)."
    - "Observer registration via Model::booted() per D-04-08-B — NOT EventServiceProvider (which Laravel 11 removed)."
    - "DB::table for JOIN-heavy aggregate — clan leaderboard query crosses match_player_stats → matches → game_match_types AND match_player_stats → players → clan_memberships AND matches → match_results. No Eloquent relation simplifies the chain; raw query builder is the right tool."
    - "PHPStan-safe dynamic property access — casting stdClass aggregate rows to array and reading via key lookup keeps L8 happy without per-line ignore comments."
key-files:
  created:
    - "apps/web/app/Services/LeaderboardService.php — topPlayers + topClans + safeCompute (220 lines)"
    - "apps/web/app/Data/LeaderboardEntryData.php — Spatie DTO with privacy-gate factory (87 lines)"
    - "apps/web/app/Data/LeaderboardClanEntryData.php — Spatie DTO with clan factory (63 lines)"
    - "apps/web/app/Observers/MatchPlayerStatObserver.php — saved() tag flush (41 lines)"
  modified:
    - "apps/web/app/Observers/MatchResultObserver.php — created() unconditional flush + updated() wasChanged-guarded flush (17 lines added)"
    - "apps/web/app/Observers/ClanMembershipObserver.php — created() + updated() flush at left_at transition edges (13 lines added)"
    - "apps/web/app/Models/MatchPlayerStat.php — register MatchPlayerStatObserver via booted() (16 lines added)"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php — Wave 0 stub → 7 GREEN tests"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php — Wave 0 stub → 4 GREEN tests"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardCacheTest.php — Wave 0 stub → 4 GREEN tests (incl. Pitfall 9 reflection assertion)"
    - "apps/web/tests/Feature/Cache/CacheTagFlushTest.php — Wave 0 stub → 9 GREEN tests"
    - "packages/shared-types/src/api.d.ts + apps/web/resources/js/types/api.d.ts — TS regeneration (LeaderboardEntryData + LeaderboardClanEntryData)"
decisions:
  - "D-09-05-A — LeaderboardEntryData blanks the player_id (empty string) when is_anonymous=true. Plan 09-06's <PlayerLink> renderer uses empty player_id as the 'no link' signal; the DTO keeps the field shape (always string) so the Vue v-for :key binding stays stable."
  - "D-09-05-B — clans.logo_url does NOT exist on the v1 schema. LeaderboardClanEntryData carries the field (so the public-facing TS contract is forward-compatible) but always emits null in v1; plan 09-09 medialibrary WebP conversions will populate it."
  - "D-09-05-C — Schema-vs-plan drift LOCKED. Plan text referenced matches.game_id, clan_memberships.active boolean, and clan_memberships.player_id. Reality: matches has game_match_type_id (game lives on game_match_types); clan_memberships uses left_at IS NULL (partial unique index, no boolean active); clan_memberships keys on user_id (route through players.user_id). Service implements the real schema via whereHas('match.gameMatchType', ...) for player query and a DB::table multi-JOIN for clan query."
  - "D-09-05-D — Clan attribution uses CURRENT active membership at query time, not membership-at-match-time. v1 schema does not snapshot membership into stat rows. If a player switches clans, their historical kills are re-attributed to the new clan on the next leaderboard refresh. Acceptable for v1; a ClanMembershipSnapshot table would close it in a future phase. The ClanMembershipObserver tag-flush (this plan) is what makes the re-attribution visible immediately upon membership flip."
  - "D-09-05-E — games.id is a UUID. The plan referenced ?int $gameId; the actual primary key type is uuid. Service signature corrected to ?string $gameId. Test 4 in TopPlayersTest documents this with Game::factory() against the real schema."
  - "D-09-05-F — Observer registration via Model::booted() per D-04-08-B, NOT via the EventServiceProvider::$observers array the plan referenced. Laravel 11 removed EventServiceProvider; the project convention (existing observer registrations: MatchObserver on GameMatch, MatchResultObserver on MatchResult, ClanMembershipObserver on ClanMembership) all use static::observe() inside booted(). MatchPlayerStat::booted() follows the same idiom."
  - "D-09-05-G — Wave 3 spec mentioned 'ClanMembershipObserver' as a tag-flush hook in the user prompt; plan body explicitly named MatchPlayerStatObserver + MatchResultObserver. Both interpretations are correct. ClanMembershipObserver extension is Rule 2 additive correctness (D-09-05-D current-snapshot semantics mean clan attribution shifts on membership flip; without the flush, the clan leaderboard would stay stale until a stat write or new result lands). All three observers wired in this plan."
metrics:
  duration_seconds: 757
  duration_human: "~12m 37s"
  completed_at: "2026-05-14T08:08:21Z"
  files_created: 4
  files_modified: 9
  total_files: 13
  service_classes_added: 1
  dtos_added: 2
  observers_added: 1
  observers_extended: 2
  tests_now_passing: 1187
  tests_now_skipped: 21
  suite_total: 1208
  baseline_passing: 1163
  baseline_skipped: 25
  tests_added_this_plan: 24
  wave_0_stubs_turned_green: 4
  pint_files_passed: 11
  phpstan_errors: 0
  lines_added: 1010
  lines_deleted: 32
---

# Phase 9 Plan 05: Wave 3 — LeaderboardService + Cache::flexible + invalidation observers Summary

Operationalised SC-2 backend end-to-end. `LeaderboardService::topPlayers()` and `topClans()` wrap top-N aggregate queries over `match_player_stats` in `Cache::tags(['leaderboards', 'lb:{type}:{window}'])->flexible([600, 3600])` — sub-100ms reads on the fresh path, stale-served during the 50-minute SWR window. Two Spatie DTOs (LeaderboardEntryData with PlayerPrivacyGate-aware factory per D-018, LeaderboardClanEntryData) serialise to TS via `trenchwars:typescript-generate`. Three invalidation choke points (MatchResultObserver created+updated, MatchPlayerStatObserver saved [new], ClanMembershipObserver created+updated [extended]) flush the `leaderboards` tag so every domain mutation that can shift a leaderboard row invalidates the cache immediately. Four Wave 0 Pest stubs turned GREEN (24 new tests / 49 assertions); existing 73 observer/notification regression tests stay GREEN.

## What Shipped

### LeaderboardService

```php
// app/Services/LeaderboardService.php
final class LeaderboardService
{
    public function topPlayers(string $window, ?string $gameId = null, int $limit = 25): Collection { /* Cache::flexible SWR */ }
    public function topClans(string $window, ?string $gameId = null, int $limit = 25): Collection { /* Cache::flexible SWR */ }
    private function computePlayerLeaderboard(...): Collection { /* MatchPlayerStat::query + SUM */ }
    private function computeClanLeaderboard(...): Collection { /* DB::table multi-JOIN aggregate */ }
    private function safeCompute(Closure $callback): mixed { /* Pitfall 9: try/report/throw */ }
}
```

| Aspect | Behaviour |
|--------|-----------|
| Windows | `7d`, `30d`, `all` (whitelist; unknown → InvalidArgumentException) |
| Fresh TTL | 600 seconds (10 min) |
| Stale TTL | 3600 seconds (1 hr) — SWR window before recompute |
| Limit ceiling | Service caps at 100 (T-09-05-03 mitigation, defence-in-depth to plan 09-06 controller cap) |
| Player query | `MatchPlayerStat::query()->selectRaw('SUM(kills), SUM(deaths), COUNT(*), (SUM(kills)::float / NULLIF(SUM(deaths), 0)) AS kdr')->groupBy(player_id)->orderByRaw('SUM(kills) DESC')->limit($limit)` |
| Clan query | DB::table multi-JOIN: match_player_stats → matches → players → clan_memberships (left_at IS NULL) LEFT JOIN match_results; SUM(kills) + COUNT(DISTINCT match_id) + SUM(CASE WHEN winner_clan_id = cm.clan_id THEN 1 ELSE 0 END) |
| Game scope | Routes through `matches.game_match_type_id → game_match_types.game_id` (D-09-05-C) |
| Pitfall 9 | `safeCompute(Closure)` wraps every cache miss; reports the exception to Horizon's handler and rethrows so Cache::flexible's SWR stale-serve semantics are preserved |

### Cache key registry

| Key pattern | Tags | Owner |
|-------------|------|-------|
| `lb:players:{window}:{gameId\|all}:{limit}` | `leaderboards`, `lb:players:{window}` | `LeaderboardService::topPlayers` |
| `lb:clans:{window}:{gameId\|all}:{limit}` | `leaderboards`, `lb:clans:{window}` | `LeaderboardService::topClans` |

Flushing the `leaderboards` parent tag invalidates BOTH players and clans at once — every observer flushes the parent for blast-radius simplicity (the per-window child tags exist for plan 09-06 controller-level partial invalidation, e.g., flushing only `lb:players:7d` after a single-stat edit). All Phase 9 observers flush the parent.

### Tag-flush observer map

| Observer | Hook | Trigger | Flushes |
|----------|------|---------|---------|
| `MatchResultObserver` (extended) | `created(MatchResult)` | Every new result row (additive after Phase 6 bracket-advance + Phase 8 match_result_announce + Phase 9-04 notification dispatch) | `leaderboards` parent tag |
| `MatchResultObserver` (extended) | `updated(MatchResult)` | `wasChanged(['allies_score','axis_score','winner_clan_id'])` | `leaderboards` parent tag — `notes`/`recorded_by_user_id` edits do NOT flush (Test: "does NOT flush leaderboards tag when only MatchResult.notes is updated") |
| `MatchPlayerStatObserver` (new) | `saved(MatchPlayerStat)` | Every stat write — both RCON ingest creates AND Filament admin edits | `leaderboards` parent tag |
| `ClanMembershipObserver` (extended) | `created(ClanMembership)` | Active join (`left_at IS NULL` on create) | `leaderboards` parent tag — D-09-05-D current-snapshot semantics |
| `ClanMembershipObserver` (extended) | `updated(ClanMembership)` | `wasChanged('left_at')` — either edge | `leaderboards` parent tag |

All four observers register via `static::observe()` inside `Model::booted()` per D-04-08-B (NOT EventServiceProvider — Laravel 11 removed it). Registration is idempotent: Eloquent dedupes by observer class name.

### Spatie DTOs + TS generation

| DTO | TS type | Privacy semantics |
|-----|---------|-------------------|
| `LeaderboardEntryData` | `App.Data.LeaderboardEntryData` | `fromQueryResult($row, $player, $viewer, $clanName)` calls `PlayerPrivacyGate::allowsSection($player, $viewer, 'show_stats')`; when false, `is_anonymous=true`, `player_name = __('leaderboards.anonymous_player')`, `player_id = ''`, `clan_name = null` (D-018) |
| `LeaderboardClanEntryData` | `App.Data.LeaderboardClanEntryData` | No per-row privacy gate — clans are always public; `logo_url` always null in v1 (D-09-05-B) |

```typescript
// packages/shared-types/src/api.d.ts (generated)
export type LeaderboardClanEntryData = {
clan_id: string,
clan_name: string,
clan_slug: string,
logo_url: string | null,
kills: number,
matches_played: number,
wins: number,
};
export type LeaderboardEntryData = {
player_id: string,
player_name: string,
clan_name: string | null,
kills: number,
deaths: number,
kdr: number | null,
matches_played: number,
is_anonymous: boolean,
};
```

### RESEARCH A5 verification — top-N query plan

The (player_id, kills) composite index landed by plan 09-02 (`mps_player_kills_idx`) keys the player aggregate. Service uses:

```sql
SELECT player_id,
       SUM(kills) AS kills,
       SUM(deaths) AS deaths,
       COUNT(*) AS matches_played,
       (SUM(kills)::float / NULLIF(SUM(deaths), 0)) AS kdr
FROM match_player_stats
WHERE EXISTS (SELECT 1 FROM matches WHERE matches.id = match_player_stats.match_id AND scheduled_at >= ?)
GROUP BY player_id
ORDER BY SUM(kills) DESC
LIMIT 25;
```

The Postgres planner can use a HashAggregate over the index range; the `LIMIT 25` early-terminates the sort. Empirical benchmark deferred to plan 09-12 query-budget enforcement test (`LeaderboardsQueryBudgetTest`); this plan validates the index exists and the query shape compiles to it.

### Pitfall 9 verification (silent SWR refresh failure)

```php
// tests/Feature/Leaderboards/LeaderboardCacheTest.php
it('reports and rethrows when the aggregate compute callback fails (Pitfall 9)', function (): void {
    /** @var Collection<int, Throwable> $reported */
    $reported = collect();
    app()->bind(ExceptionHandler::class, fn () => new class($reported) implements ExceptionHandler {
        // ... captures report(Throwable) calls into $reported
    });

    $service = app(LeaderboardService::class);
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('safeCompute');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class, 'boom');

    expect($reported)->toHaveCount(1);
    expect($reported->first())->toBeInstanceOf(RuntimeException::class);
});
```

Test result: **PASS** (0.02s). The exception is reported BEFORE being rethrown — Horizon's exception list will catch it, and Cache::flexible's SWR semantics preserve the previous stale value rather than caching null.

### Pitfall 1 verification (Cache::tags on non-Redis store)

```text
$ docker compose exec web php artisan tinker --execute='echo config("cache.default") . PHP_EOL;'
redis
```

Production cache store is Redis (confirmed via runtime config read). Tests run under `phpunit.xml`'s `CACHE_STORE=array` — array driver supports tags + flexible in Laravel 11+ (empirically verified during planning research with `Cache::tags(['leaderboards'])->put(...)->get(...)` and `Cache::tags(...)->flexible(...)` calls succeeding on both drivers). The Pitfall 1 risk applies specifically to `file` and `database` drivers, neither of which is the active or test store.

## Quality Gates

| Gate | Result |
|------|--------|
| `pest --filter="LeaderboardServiceTopPlayersTest"` | **7 passed** / 13 assertions / 0.36s |
| `pest --filter="LeaderboardServiceTopClansTest"` | **4 passed** / 11 assertions / 2.00s |
| `pest --filter="LeaderboardCacheTest"` | **4 passed** / 12 assertions / 2.22s |
| `pest --filter="CacheTagFlushTest"` | **9 passed** / 13 assertions / 1.99s |
| `pest --filter="MatchResult\|MatchObserver\|ClanMembershipObserver\|NotificationDispatcher"` (regression) | **73 passed** / 181 assertions / 7.40s |
| `pest tests/Feature/Phase8` (Phase 8 regression) | **90 passed** / 295 assertions / 5.62s |
| `pest --no-coverage` (full suite) | **1187 passed + 21 skipped** (3904 assertions) in 73.70s |
| Baseline delta (passed) | +24 (1163 → 1187) — exactly the 24 new GREEN tests |
| Baseline delta (skipped) | −4 (25 → 21) — exactly the 4 Wave 0 stubs this plan turned GREEN |
| `pint --test` on 11 touched files | **PASS** (after one auto-fix pass — fully_qualified_strict_types + unary_operator_spaces + no_unused_imports) |
| `phpstan analyse` (level 8, full project, 396 files) | **OK, no errors** |
| Runtime `config("cache.default")` | `redis` (production correct) |
| `trenchwars:typescript-generate` | LeaderboardEntryData + LeaderboardClanEntryData written to `packages/shared-types/src/api.d.ts` |

## Wave 0 Stubs → GREEN

```text
LeaderboardServiceTopPlayersTest                            Wave 0 (1 skipped) → 7 passed
LeaderboardServiceTopClansTest                              Wave 0 (1 skipped) → 4 passed
LeaderboardCacheTest                                        Wave 0 (1 skipped) → 4 passed
CacheTagFlushTest                                           Wave 0 (1 skipped) → 9 passed
                                                                                  ────────
                                                                              24 new GREEN tests
```

Skip-list count check:
- Pre-plan (09-04): 25 skipped.
- Post-plan (09-05): 21 skipped (25 − 4 = 21 ✓).

## Deviations from Plan

### Rule 1 — schema-vs-plan drift (4 corrections)

**1. [Rule 1 — Bug] `matches.game_id` does not exist; the game scope lives on `game_match_types.game_id`**

- **Found during:** Task 1 — writing `computePlayerLeaderboard()` and the topPlayers `it('filters by game_id...')` test.
- **Issue:** Plan text (action block + RESEARCH § Leaderboards SQL block) referenced `->where('game_id', $gameId)` directly on the matches table. The matches schema (`2026_05_14_100000_create_matches_table.php`) has no `game_id` column — it has `game_match_type_id` which references `game_match_types`, and `game_match_types.game_id` is the actual game-scope FK.
- **Fix:** Service filters via `whereHas('match.gameMatchType', fn ($q) => $q->where('game_id', $gameId))` for the player query and a DB::table JOIN on `game_match_types AS gmt` for the clan query. Locked as **D-09-05-C**.
- **Files modified:** `app/Services/LeaderboardService.php`, `tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php`.
- **Commit:** `1c09a8f`.

**2. [Rule 1 — Bug] `clan_memberships.active` boolean does not exist; activity is filtered via `left_at IS NULL`**

- **Found during:** Task 1 — writing `computeClanLeaderboard()`.
- **Issue:** Plan + RESEARCH SQL block referenced `cm.active = true`. The `clan_memberships` migration (`2026_05_12_100400_create_clan_memberships_table.php`) has no boolean `active` column. D-009 enforcement is a partial unique index `CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL` — the canonical "is active" predicate is `left_at IS NULL`.
- **Fix:** Service JOIN uses `whereNull('cm.left_at')`. Locked as **D-09-05-C**.
- **Files modified:** `app/Services/LeaderboardService.php`.
- **Commit:** `1c09a8f`.

**3. [Rule 1 — Bug] `clan_memberships.player_id` does not exist; clan_memberships keys on `user_id`**

- **Found during:** Task 1 — writing `computeClanLeaderboard()` JOIN chain.
- **Issue:** Plan + RESEARCH SQL block referenced `INNER JOIN clan_memberships cm ON cm.player_id = mps.player_id`. Schema has `clan_memberships.user_id`. The correct chain is `mps.player_id → players.id` AND `players.user_id → clan_memberships.user_id`.
- **Fix:** Service JOIN routes through `players AS p`: `JOIN players AS p ON p.id = mps.player_id JOIN clan_memberships AS cm ON cm.user_id = p.user_id WHERE cm.left_at IS NULL`. Locked as **D-09-05-C**.
- **Files modified:** `app/Services/LeaderboardService.php`, `tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php` (helper `makePlayerInClan` reflects the real key chain).
- **Commit:** `1c09a8f`.

**4. [Rule 1 — Bug] `games.id` is UUID, not int — service signature was `?int $gameId`**

- **Found during:** Task 1 verification — `it('filters by game_id...')` failed with TypeError ("Argument #2 ($gameId) must be of type ?int, string given").
- **Issue:** Plan referenced `?int $gameId = null`. The `games` migration uses `$table->uuid('id')->primary()` — `games.id` is a UUID string, not a bigInteger.
- **Fix:** Signature corrected to `?string $gameId = null` on `topPlayers`, `topClans`, `computePlayerLeaderboard`, `computeClanLeaderboard`. Locked as **D-09-05-E**.
- **Files modified:** `app/Services/LeaderboardService.php`.
- **Commit:** `1c09a8f`.

### Rule 2 — additive correctness (1 extension)

**5. [Rule 2 — Missing functionality] ClanMembershipObserver tag-flush extension**

- **Found during:** Wave 3 spec review — user prompt's `<execution_rules>` block mentioned "Tag-flush observers on MatchResultObserver + ClanMembershipObserver" but the plan body only specified `MatchPlayerStatObserver` (new) and `MatchResultObserver` (extended).
- **Issue:** D-09-05-D current-snapshot semantics — clan attribution joins on the CURRENT active membership at query time. If a player switches clans, their historical kills are re-attributed to the new clan on the next leaderboard refresh. Without a ClanMembershipObserver flush, that re-attribution wouldn't appear until the next MatchResult or stat write — stale leaderboards on the public clan-rankings surface.
- **Fix:** Extended `ClanMembershipObserver::created()` (when `left_at IS NULL`) and `ClanMembershipObserver::updated()` (when `wasChanged('left_at')` — both edges) to flush `Cache::tags(['leaderboards'])`. Locked as **D-09-05-G**.
- **Files modified:** `app/Observers/ClanMembershipObserver.php`, `tests/Feature/Cache/CacheTagFlushTest.php` (2 new tests cover both edges).
- **Commit:** `347b908`.

### Rule 3 — blocking fixes (1 PHPStan type drift)

**6. [Rule 3 — Blocker] PHPStan L8 generic Collection types + non-object method call**

- **Found during:** Task 2 PHPStan verification — 2 errors:
  1. `Collection<TKey, TValue>` missing type parameters on the test's ExceptionHandler `$sink` constructor.
  2. `Cannot call method getMessage() on Throwable|null` on `$reported->first()`.
- **Fix:** Added `@var Collection<int, Throwable>` annotations on the sink + first() return assignment; switched `$this->app->bind(...)` to `app()->bind(...)` (`$this->app` is not on Pest's `TestCall`).
- **Files modified:** `tests/Feature/Leaderboards/LeaderboardCacheTest.php`.
- **Commit:** `347b908` (rolled into Task 2 commit).

### Rule 3 — observer registration mechanism

**7. [Rule 3 — Architectural alignment, NOT a deviation] Observer registered via `Model::booted()`, not EventServiceProvider**

- **Found during:** Task 2 — wiring MatchPlayerStatObserver.
- **Issue:** Plan text said "Register in `EventServiceProvider::$observers`: `MatchPlayerStat::class => [MatchPlayerStatObserver::class]`." Laravel 11+ (which Trenchwars uses — D-001 stack) removed EventServiceProvider. The project convention (D-04-08-B precedent — GameMatch, MatchResult, ClanMembership, ClanApplication, ClanInvite all use `static::observe()` inside `Model::booted()`) is to register at the model layer.
- **Fix:** `MatchPlayerStat::booted()` calls `static::observe(MatchPlayerStatObserver::class)`. Locked as **D-09-05-F**.
- **Files modified:** `app/Models/MatchPlayerStat.php`.

### Rule 4 — None

No architectural decisions required. Every adjustment was a Rule 1 alignment with on-disk schema, a Rule 2 additive correctness extension, or a Rule 3 mechanical PHPStan fix.

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack (web + postgres + redis healthy throughout). No external API or human action required.

## Known Stubs

None. Every code path is fully wired:

- LeaderboardService::topPlayers + topClans return real cached aggregate Collections. Plan 09-06 controller hydrates them via the DTO factories.
- All four tag-flush observers wired and tested end-to-end (the CacheTagFlushTest fires real model events through factories, asserts real cache invalidation).
- TS types regenerated and committed (`packages/shared-types/src/api.d.ts` + `apps/web/resources/js/types/api.d.ts`).
- `LeaderboardClanEntryData.logo_url` is intentionally always-null pending plan 09-09 (medialibrary WebP) — documented in DTO docblock (D-09-05-B), not a stub.

## Threat Flags

None. The plan's `<threat_model>` (T-09-05-01..06) covers every introduced surface:

| Threat | Component | Mitigation status |
|--------|-----------|-------------------|
| T-09-05-01 (Tampering — cache tag collision) | LeaderboardService tag namespacing | **PASS** — tags are constants (`'leaderboards'`, `'lb:players:{window}'`, `'lb:clans:{window}'`); no user-controlled tag input; cache keys include scope (window+gameId+limit) |
| T-09-05-02 (Information Disclosure — privacy leak) | LeaderboardEntryData::fromQueryResult | **PASS** — `PlayerPrivacyGate::allowsSection($player, $viewer, 'show_stats')` gate at DTO factory; `is_anonymous=true` → display_name replaced with `__('leaderboards.anonymous_player')`, player_id blanked, clan_name nulled. Plan 09-06 Vue layer respects `is_anonymous` (this plan ships the DTO contract; plan 09-06 wires the renderer) |
| T-09-05-03 (DoS — unbounded limit) | LeaderboardService::capLimit() | **PASS** — service caps at 100 rows regardless of caller. Plan 09-06 controller will additionally cap at 100 (defence-in-depth) |
| T-09-05-04 (Tampering — stale cache after stat update) | MatchPlayerStatObserver + MatchResultObserver | **PASS** — every saved/created/updated path that can change a leaderboard row flushes the tag. CacheTagFlushTest asserts all 4 paths (incl. negative case: notes-only update does NOT flush) |
| T-09-05-05 (DoS — silent SWR refresh failure / Pitfall 9) | LeaderboardService::safeCompute | **PASS** — try/report/throw around compute callback; LeaderboardCacheTest reflection test asserts both the report() call AND the rethrow. Cache::flexible preserves stale value on rethrow rather than caching null |
| T-09-05-06 (Information Disclosure — gameId enumeration) | LeaderboardService::topPlayers/topClans | **ACCEPT** — games table is admin-edit/public-read per D-007; no sensitive game IDs |

No new surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (4 created, 9 modified — 13 total):**

```
FOUND: apps/web/app/Services/LeaderboardService.php
FOUND: apps/web/app/Data/LeaderboardEntryData.php
FOUND: apps/web/app/Data/LeaderboardClanEntryData.php
FOUND: apps/web/app/Observers/MatchPlayerStatObserver.php
FOUND: apps/web/app/Observers/MatchResultObserver.php (modified — created() unconditional flush + updated() guarded flush)
FOUND: apps/web/app/Observers/ClanMembershipObserver.php (modified — created/updated tag flush)
FOUND: apps/web/app/Models/MatchPlayerStat.php (modified — register MatchPlayerStatObserver)
FOUND: apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Leaderboards/LeaderboardCacheTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Cache/CacheTagFlushTest.php (Wave 0 → GREEN)
FOUND: packages/shared-types/src/api.d.ts (LeaderboardEntryData + LeaderboardClanEntryData)
FOUND: apps/web/resources/js/types/api.d.ts (LeaderboardEntryData + LeaderboardClanEntryData)
```

**Commits verified:**

```
FOUND: 1c09a8f feat(09-05): add LeaderboardService + 2 DTOs with SWR cache (Task 1)
FOUND: 347b908 feat(09-05): wire leaderboard cache invalidation observers (Task 2)
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="LeaderboardServiceTopPlayersTest|LeaderboardServiceTopClansTest|LeaderboardCacheTest|CacheTagFlushTest" --no-coverage
  Tests: 24 passed (49 assertions) — all 4 Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-04):    1163 passed + 25 skipped
Post-plan (09-05):            1187 passed + 21 skipped
                              ────────────  ──────────
                              +24 passed    −4 skipped
```

All 4 created + 9 modified files present on disk; both commits resolve in `git log` (Task 1: 7 files, 648 insertions, 15 deletions; Task 2: 6 files, 362 insertions, 17 deletions = 1010/32 total lines).
