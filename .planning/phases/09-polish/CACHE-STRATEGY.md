# Phase 9 — Cache Strategy

**Source authority:** `09-RESEARCH.md` § "Cache Strategy" + plan `09-05` (leaderboard cache) + plan `09-08` (query budgets).

**Why this exists:** Plan 09-08 task 2 mandates a cache-strategy doc colocated with the phase summaries (NOT inside `apps/web/`) so future plans + doc consumers can refer to a single registry of cache keys, tags, TTLs, and invalidation hooks.

## 1. Tagged Cache Key Registry

| Cache key                                          | Tags                                       | TTL (fresh, stale) | Invalidated by                                                            |
|----------------------------------------------------|--------------------------------------------|--------------------|---------------------------------------------------------------------------|
| `lb:players:{window}:{game_id|all}:{limit}`        | `leaderboards`, `lb:players:{window}`      | (600s, 3600s)      | `MatchResultObserver::created/updated` flushes `leaderboards`             |
| `lb:clans:{window}:{game_id|all}:{limit}`          | `leaderboards`, `lb:clans:{window}`        | (600s, 3600s)      | same as `lb:players:*`                                                    |
| `lb:games:dropdown`                                | `games:dropdown`                           | 900s (15m)         | `GameObserver::saved/deleted` flushes `games:dropdown` (plan 09-08)       |
| `clan:directory:page:{n}:{tag_filter_hash}`        | `clans`, `clans:directory`                 | (1800s, 7200s)     | `ClanObserver::saved`, `ClanTagObserver::saved` flush `clans:directory`   |
| `cms:articles:index:page:{n}`                      | `cms`, `cms:articles`                      | (300s, 1800s)      | `ArticleObserver::saved` flushes `cms:articles` (Phase 7)                 |
| `home:hero:articles`                               | `cms`, `cms:home`                          | (300s, 1800s)      | `ArticleObserver::published` flushes `cms:home`                           |
| `player:profile:{id}:{viewer_tier}`                | `players`, `player:{id}`                   | (300s, 1800s)      | `PlayerObserver::saved`, `PlayerPrivacyObserver::saved` flush `player:{id}` |

## 2. TTL Guidelines

- **Fresh TTL** — duration during which the cached value is served without a refresh attempt.
- **Stale TTL** — `Cache::flexible()` SWR window. Within stale-but-not-expired, Laravel serves the cached value and queues a background refresh after-response. Outside the stale window, the next caller pays the recompute cost.
- **Tuple `(fresh, stale)`** means `Cache::tags(...)->flexible(key: ..., ttl: [$fresh, $stale], callback: ...)`.
- **Single value** (e.g. `lb:games:dropdown` = 900s) means `Cache::tags(...)->remember(key: ..., ttl: $ttl, callback: ...)` — no SWR, recompute fires inside the request that found the value expired.

## 3. Invalidation Observer Map

Every domain mutation that changes a public surface MUST end its observer hook with a tag flush. No exceptions.

| Observer                                  | Hook                  | Tag flushed                                       | Source              |
|-------------------------------------------|------------------------|---------------------------------------------------|---------------------|
| `MatchResultObserver::created/updated`    | `created`, `updated`   | `leaderboards`                                    | plan 09-05          |
| `ClanObserver::saved`                     | `saved`                | `clans:directory`                                 | Phase 2             |
| `ClanTagObserver::saved`                  | `saved`                | `clans:directory`                                 | Phase 2             |
| `ArticleObserver::saved`                  | `saved`                | `cms:articles`                                    | Phase 7             |
| `ArticleObserver::published` (event)      | scoped publish event   | `cms:home`                                        | Phase 7             |
| `PlayerObserver::saved`                   | `saved`                | `player:{id}`                                     | Phase 3             |
| `PlayerPrivacyObserver::saved`            | `saved`                | `player:{id}`                                     | Phase 3             |
| `GameObserver::saved/deleted`             | `saved`, `deleted`     | `games:dropdown` (added in plan 09-08)            | plan 09-08          |

**Rule:** The plan-checker rejects any new model whose observer modifies leaderboard-feeding state (match results, player stats, clan rosters) without a corresponding `Cache::tags(...)->flush()` call.

## 4. Cache Key Conventions

1. **Always include scope** — `window`, `game_id`, `tag_filter_hash`, `viewer_tier`. NEVER cache a viewer-specific result under a non-viewer-keyed key.
2. **Hash long discriminators** — tag filter sets via `sha1(json_encode($sorted))` to keep keys under 250 chars.
3. **Privacy-tier suffix** — viewer-aware caches (`player:profile:*`) include `{viewer_tier}` per D-018. Tiers are `public` (anon), `community` (auth'd non-clan), `clan` (same clan), `private` (self only).
4. **Tag namespace** — top-level tag is the domain (`leaderboards`, `clans`, `cms`, `players`); secondary tags scope finer (`lb:players:{window}`, `clans:directory`, `cms:articles`, `player:{id}`). Flushing the top-level tag drops every secondary tag in its tree.
5. **Separate ephemeral data from rarely-changing data.** The `lb:games:dropdown` cache (rarely-changing; flushed only on Game admin edits) lives under its own `games:dropdown` tag so the high-frequency `leaderboards` flush (every MatchResult write) does NOT drop the dropdown.

## 5. Why `Cache::flexible` over `Cache::remember`

Public pages tolerate up-to-1-hour-stale leaderboards in exchange for sub-100ms render times. `flexible()` serves stale within the second window and queues a background refresh after response (Laravel 11+ feature). `remember()` blocks the request on the recompute every time the fresh window expires.

## 6. Tagged Caches Require Redis

`Cache::tags()` is only supported on `redis` and `memcached` cache stores. File and database drivers throw. Plan 0 verified `CACHE_STORE=redis` in `.env.example` AND `config/cache.php`'s default; `phpunit.xml` pins `CACHE_STORE=array` for tests (array driver supports tags too).

## 7. Query Budgets (locked in plan 09-08)

Representative public-page budgets enforced via Pest tests:

| Page                          | Budget (queries)       | Test file                                                       |
|-------------------------------|------------------------|-----------------------------------------------------------------|
| `/leaderboards` cold + data   | 6                      | `LeaderboardsQueryBudgetTest` it('… on cold cache …')           |
| `/leaderboards` warm cache    | 4                      | `LeaderboardsQueryBudgetTest` it('… on warm cache …')           |
| `/leaderboards` empty DB      | 4                      | `LeaderboardsQueryBudgetTest` it('… with empty database …')     |
| `/clans` cold (10 clans)      | 8                      | `ClansQueryBudgetTest` it('… on cold load …')                   |
| `/clans` filter / paginate    | 8                      | `ClansQueryBudgetTest` it('… with tag filter …')                |
| `/clans` empty DB             | 8                      | `ClansQueryBudgetTest` it('… empty database')                   |

**Deviation note (plan 09-08):** The original plan target was `/leaderboards ≤ 4` queries. Measured cold-with-data is 6 (2 cached aggregates + 3 hydration IN-lookups + 1 cached games dropdown). The 3-query hydration trio (players, player_privacy, clan_memberships) is the canonical Pattern 6 eager-load shape; collapsing into a hand-written JOIN would bypass Eloquent + privacy accessors. Cold-cache budget raised to 6; warm-cache + empty-state remain ≤ 4.

## 8. Cross-Plan References

- Plan 09-05 — seeds the `leaderboards` tag + `LeaderboardService::topPlayers/topClans` + `MatchResultObserver` flush.
- Plan 09-08 — adds `games:dropdown` tag + `Model::shouldBeStrict()` + the two query-budget tests above.
- Plan 09-11 (planned) — extends cache tag set for `notifications:*` (per-user) + `abuse_reports:*`.
