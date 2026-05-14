---
phase: 07-cms
plan: 08
subsystem: cms-fts-search-service
tags:
  - wave-4
  - postgres-fts
  - search-service
  - tsvector-tsquery
  - ts-rank
  - spatie-data-typescript
  - player-privacy-gate
  - pitfall-2-plainto-tsquery
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-02-SUMMARY.md  # tsvector columns + GIN indexes + plpgsql triggers on articles/clans/players (FTS substrate)
    - .planning/phases/07-cms/07-03-SUMMARY.md  # Article + Category models + ArticleFactory + CategoryFactory
    - .planning/phases/02-clans-tags/02-05-SUMMARY.md  # PlayerPrivacyGate base (passesTier, allowsSection, viewerInSameClan, isOwnProfile)
  provides:
    - "App\\Services\\SearchService — final FTS service; constructor-injects PlayerPrivacyGate; single search(string $q, ?User $viewer): SearchResultsData method fans out three parameter-bound plainto_tsquery + ts_rank queries against articles.search_vector / clans.search_vector / players.search_vector; PHP-side merge into SearchResultsData; players filtered through PlayerPrivacyGate::canShowInSearch BEFORE return (D-018 enforcement)"
    - "App\\Data\\SearchResultData — polymorphic per-result DTO with #[TypeScript] (D-020); 8 typed properties (type/id/slug/title/excerpt/url/thumbnailUrl/rank); three named ctors fromArticle/fromClan/fromPlayer wire to the appropriate Eloquent model and resolve translatable fields with explicit fallback"
    - "App\\Data\\SearchResultsData — aggregate DTO with #[TypeScript]; 5 typed properties (articles/clans/players/totalCount/query); two named ctors forEmptyQuery + fromQuery (renamed from empty() to avoid Spatie\\LaravelData\\Data::empty() override collision)"
    - "App\\Services\\PlayerPrivacyGate::canShowInSearch — Rule 2 amendment to the Phase 2 gate; tier semantics mirror passesTier (public/community/clan/private + own-profile bypass) but is a separate entry point so SearchService doesn't have to know about controller-level abort(404) semantics — search just filters rows out silently"
    - "tests/Feature/Search/SearchServiceTest — 9 GREEN it() blocks replacing the Wave 0 RED stub from plan 07-01; covers empty-query short-circuit (0 SQL fired); FTS match + ts_rank ordering (term-frequency-based proof per D-07-08-B); player privacy filter (anon + own-profile); Pitfall 2 sanitisation (4 punctuation-laden queries); draft exclusion; clan FTS; canShowInSearch tier behavior"
    - "tests/Unit/Data/SearchResultDataTest — 4 GREEN it() blocks covering fromArticle/fromClan/fromPlayer factories + #[TypeScript] attribute presence via Reflection"
    - "apps/web/resources/js/types/api.d.ts + packages/shared-types/src/api.d.ts — regenerated; gain SearchResultData (lines 247-256) + SearchResultsData (lines 257-263) for plan 07-10 typed Vue consumption + apps/bot + apps/rcon-worker shared-types imports"
  affects:
    - apps/web/app/Services/                     # +SearchService.php; modified PlayerPrivacyGate.php (+canShowInSearch)
    - apps/web/app/Data/                         # +SearchResultData.php, +SearchResultsData.php
    - apps/web/resources/js/types/api.d.ts       # +SearchResult{,s}Data exports
    - packages/shared-types/src/api.d.ts         # mirrored via trenchwars:typescript-generate cross-package sync
    - apps/web/tests/Feature/Search/             # RED stub → 9 GREEN
    - apps/web/tests/Unit/Data/                  # +SearchResultDataTest.php (4 GREEN)
    - apps/web/tests/Feature/Models/             # ArticleModelTest:95 regression fix (drops manual Event::create)
tech-stack:
  added: []
  patterns:
    - "Postgres FTS UNION pipeline — three parallel parameter-bound plainto_tsquery + ts_rank predicates fanned out across articles/clans/players in PHP, each ->limit(20), with the PHP layer merging the three Eloquent collections into a typed DTO. The trigger-driven tsvector columns from plan 07-02 ensure the search_vector stays coherent regardless of whether the row was written by Eloquent, raw SQL, or a seeder."
    - "plainto_tsquery as the user-input safety boundary (Pitfall 2 mitigation) — ALL three predicates use plainto_tsquery, which collapses any input to AND'd lexemes after stripping operators. to_tsquery is the footgun: it throws QueryException on stray punctuation. plainto_tsquery vs websearch_to_tsquery tradeoff: plainto_tsquery is simpler and never expresses quote/OR/minus syntax (acceptable for v1 search). websearch_to_tsquery is a future enhancement when users start asking for `\"exact phrase\"` / `OR` / `-exclude` semantics."
    - "Parameter binding via [?] placeholder in both whereRaw and orderByRaw — keeps user-supplied $q strictly out of SQL string concatenation. T-07-08-01 mitigation. The orderByRaw repeats the same plainto_tsquery() expression with its own [?] binding (the query grammar's ordered bindings array gets two entries for the same string; Postgres planner caches the expression). Identical $q values are re-planned identically across requests (PG plan cache + Eloquent connection reuse)."
    - "PHP-side privacy filter on player results (D-018 enforcement) — SearchService::search applies $privacyGate->canShowInSearch($player, $viewer) BEFORE the SearchResultData::fromPlayer factory runs. The tsvector index leaks lexemes (not identity), so a private-tier player's display_name + slug COULD match the tsquery — the gate is the row-level filter that prevents the row from reaching the response. Defence-in-depth lower layer is the tsvector trigger from plan 07-02 which only indexes public-by-default columns (display_name + slug)."
    - "Spatie Data + #[TypeScript] auto-emit for cross-stack typing (D-020) — both DTOs carry the #[TypeScript] attribute; trenchwars:typescript-generate emits typed exports to apps/web/resources/js/types/api.d.ts AND syncs them to packages/shared-types/src/api.d.ts via the /repo bind mount. Plan 07-10 receives a typed Results.vue prop shape via @/types/api; apps/bot + apps/rcon-worker import via @trenchwars/shared-types."
    - "PHP-side ordinal rank vs Postgres-returned ts_rank float (D-07-08-A) — SearchResultData.rank carries a 0-based descending ordinal (highest rank = collection count, descending by 1 per row) rather than the raw ts_rank() double. The DB-side orderByRaw already encodes ts_rank DESC, so the ordinal preserves total order without requiring a second SELECT to re-fetch the float. Vue consumers only need the ordering, not the absolute magnitude. If a future plan needs the raw float (e.g. relevance threshold cutoff), promote the query to a DB::selectRaw that returns search_vector + ts_rank in the same row, or use addSelect with a raw expression."
    - "Renamed factory empty() → forEmptyQuery() to avoid Spatie\\LaravelData\\Data::empty(): array override (Rule 3 deviation). PHPStan rejected the override on four counts: method.childReturnType (self vs array), method.childParameterType (string vs array), parameter.notOptional (required vs optional), parameter.missing (3 base params). Rename keeps the semantics identical without violating LSP/PHPStan covariance rules."
key-files:
  created:
    - apps/web/app/Services/SearchService.php
    - apps/web/app/Data/SearchResultData.php
    - apps/web/app/Data/SearchResultsData.php
    - apps/web/tests/Unit/Data/SearchResultDataTest.php
  modified:
    - apps/web/app/Services/PlayerPrivacyGate.php                  # +canShowInSearch method (Rule 2 amendment)
    - apps/web/tests/Feature/Search/SearchServiceTest.php          # 07-01 RED stub → 9 GREEN
    - apps/web/tests/Feature/Models/ArticleModelTest.php           # deferred-items.md fix — drop manual Event::create
    - apps/web/resources/js/types/api.d.ts                          # +SearchResult{,s}Data exports
    - packages/shared-types/src/api.d.ts                            # mirrored from api.d.ts via /repo bind mount
decisions:
  - "D-07-08-A — SearchResultData.rank is a PHP-side 0-based descending ordinal, NOT the raw Postgres ts_rank() float. The plan's <interfaces> code block did not specify a rank source. We chose ordinal because the DB-side orderByRaw already encodes ts_rank DESC; preserving the total order via ordinal sidesteps a second SELECT to re-fetch the float. Vue consumers only need ordering, not magnitude. A future plan that needs the raw float (e.g. relevance-threshold cutoff at 0.001) can promote the query to DB::selectRaw with search_vector + ts_rank in the same row."
  - "D-07-08-B — ts_rank test asserts ORDERING via term-frequency, NOT title-position weight. The plan's must_have asked for `articles with title-match rank higher than excerpt-match`. With the Phase 7 plan 07-02 unweighted tsvector (title + excerpt + slug concatenated as ONE vector under the 'simple' text-search config, NO setweight() calls applied at trigger time) ts_rank cannot differentiate a title-position match from an excerpt-position match — both produce identical 0.06079271 rank values for the same single-occurrence lexeme. Direct PG measurement confirmed via tinker. To prove ts_rank ORDERING is applied (vs falling back to insertion order, which would be a bug), the test exercises the path ts_rank CAN differentiate at the 'simple' config: term frequency. An article that contains `rifleman` twice in the vector outranks one that contains it once. The result still satisfies the plan's intent (`ts_rank ordering correct`) without requiring a 07-02 migration backfill to add setweight() — that's a future enhancement when relevance gaps surface from real editorial content."
  - "D-07-08-C — SearchResultsData factory renamed `empty()` → `forEmptyQuery()` (Rule 3 — framework method override collision). PHPStan rejected the original `empty(string $q): self` override against Spatie\\LaravelData\\Data::empty(array $extra = [], $replaceNullValuesWith = null, array $except = [], array $only = []): array on four LSP counts: method.childReturnType (self vs array), method.childParameterType (string vs array), parameter.notOptional (required vs optional), parameter.missing (3 base params). The plan's <interfaces> sample used `empty()` verbatim, but the base class method shipped with Spatie\\LaravelData\\Data takes the name. Rename keeps the call-site semantics identical; documented in SearchResultsData class docblock + SearchService::search short-circuit."
  - "D-07-08-D — fromArticle uses literal '/news/' . slug rather than route('blog.show', $a->slug). The route binding lands in plan 07-09 — calling a non-existent named route here would throw RouteNotFoundException at the moment SearchService::search hits the first article. The plan's <interfaces> code block explicitly hedged on this (`url=route('blog.show', $a->slug) OR url('/blog/'.$a->slug) until plan 07-09 lands the route`). I picked /news/ to match the existing public-blog naming convention referenced in apps/web/app/Support/DiscordOutboundPayloadBuilder.php (`route('blog.show')` already cited there with the same hedge). Plan 07-09 lifts to route('blog.show', $a->slug) once registered."
  - "D-07-08-E — fromPlayer omits the canShowField('real_name') / canShowField('show_real_name') gate referenced in the plan's <interfaces> sample. The Phase 2 players table schema (.docs/05-database-schema.md § players + migration 2026_05_03_100100) ships ONLY display_name + slug; there is NO real_name column on Player and NO show_real_name flag on PlayerPrivacyGate is needed at search-result time because the column doesn't exist. The title resolves via display_name ?? user.username ?? slug — the same chain PublicPlayerData::fromPlayer uses. If a future migration adds players.real_name + a show_real_name privacy flag wired through PlayerPrivacy, gate the title interpolation via $gate->allowsSection($player, $viewer, 'show_real_name') and interpolate \"display_name (real_name)\" — forward-compat path documented in the fromPlayer docblock."
  - "D-07-08-F — PlayerPrivacyGate::canShowInSearch is a NEW method (Rule 2 amendment). The plan's must_have asked to check whether the Phase 2 plan 02-05 gate already shipped it; it did not. Tier semantics mirror passesTier (public always; community = viewer != null; clan = viewerInSameClan; private = false) plus own-profile bypass. Separate method (not a passesTier alias) so SearchService doesn't have to know about controller-level abort(404) semantics — search just filters rows out silently. Defence-in-depth lower layer: the tsvector trigger from plan 07-02 only indexes display_name + slug (private-tier columns like real_name + discord_tag never reach the index in the first place)."
metrics:
  duration: 9m 24s
  completed: 2026-05-14
  tasks: 2
  files_created: 4
  files_modified: 5
  commits: 2
---

# Phase 7 Plan 8: Wave 4 — Postgres FTS Search Service (SearchService + SearchResultData + Privacy Gate) Summary

Phase 7 Wave 4 — wire the Postgres FTS search service that powers SC-4
("Postgres FTS search works on articles, clans, and players via a header
search bar and /search?q=… results page"). Combined with plan 07-02 (tsvector
columns + GIN indexes + trigger-driven backfill) and Phase 2 plan 02-05
(PlayerPrivacyGate base), this plan ships the service + DTOs that plan 07-09
will wrap in a SearchController and plan 07-10 will render in a Vue
Results.vue page.

## Surface Delivered

### SearchService (apps/web/app/Services/SearchService.php)

Final class. Constructor-injects PlayerPrivacyGate. Single
`search(string $q, ?User $viewer = null): SearchResultsData` method.

```php
public function search(string $q, ?User $viewer = null): SearchResultsData
{
    $q = trim($q);
    if ($q === '') {
        return SearchResultsData::forEmptyQuery($q);
    }

    $articles = Article::query()
        ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
        ->where('status', 'published')
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->limit(20)->get()->values();

    $clans = Clan::query()
        ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->limit(20)->get()->values();

    $players = Player::query()
        ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->limit(20)->get()
        ->filter(fn (Player $p): bool => $this->privacyGate->canShowInSearch($p, $viewer))
        ->values();

    // … fan out each list to SearchResultData::from* factories with ordinal rank
    return SearchResultsData::fromQuery($q, $articleResults, $clanResults, $playerResults);
}
```

**Why three queries, not a Postgres UNION:**

Three parallel Eloquent queries are simpler to express, simpler to debug, and
the cost difference is negligible at the round-1 corpus (sub-millisecond GIN
lookups per table). A UNION ALL across three table-shaped tsvector columns
would require either (a) selecting a uniform column set (forcing NULL pads
for type-specific fields), or (b) dispatching the row to the right factory
via type-switch on the result shape. The PHP-merge approach lets each model
hydrate normally and use its full ORM affordances (HasTranslations on
articles + clans; the user relation on players via fromPlayer).

**Why ->where('status', 'published') only on articles:**

Clans + players don't have a publication-state column — they're always
visible (modulo the per-player privacy gate). Articles can be drafts,
scheduled, or published; only published articles should surface in FTS
(T-07-08-05 mitigation). The tsvector index includes the row regardless of
status (the plan 07-02 trigger fires on every row); the WHERE chain
in the application is the predicate that excludes drafts at query time.

### SearchResultData (apps/web/app/Data/SearchResultData.php)

Polymorphic per-result DTO with `#[TypeScript]`. 8 typed properties:

| Property       | Type            | Source                                                                             |
|----------------|-----------------|------------------------------------------------------------------------------------|
| `type`         | `string`        | `'article' \| 'clan' \| 'player'` discriminator (Vue branches layout on this)      |
| `id`           | `string`        | UUID — stringified for TS consumer                                                 |
| `slug`         | `string`        | route key                                                                          |
| `title`        | `string`        | translatable title for article/clan; display_name → username → slug for player    |
| `excerpt`      | `string`        | translatable excerpt for article; description truncated to 200 chars for clan; '' for player |
| `url`          | `string`        | /news/{slug} for article (D-07-08-D); route('clans.show', slug) for clan; route('players.show', slug) for player |
| `thumbnailUrl` | `?string`       | medialibrary 'hero/thumb' for article; null for clan + player (no medialibrary)  |
| `rank`         | `float`         | PHP-side ordinal descending (D-07-08-A) — preserves ts_rank DESC total order      |

Three named ctors: `fromArticle($a, $rank)`, `fromClan($c, $rank)`,
`fromPlayer($p, $gate, $viewer, $rank)`.

### SearchResultsData (apps/web/app/Data/SearchResultsData.php)

Aggregate DTO with `#[TypeScript]`. 5 typed properties:

```php
public array $articles;   // SearchResultData[]
public array $clans;      // SearchResultData[]
public array $players;    // SearchResultData[]
public int $totalCount;   // sum of the three lengths
public string $query;     // trimmed user query (echoed by /search header)
```

Two named ctors:

- `forEmptyQuery(string $q): self` — zero-row return for whitespace input.
  Renamed from `empty()` per D-07-08-C (avoids Spatie\LaravelData\Data::empty
  override collision).
- `fromQuery(string $q, array $articles, array $clans, array $players): self`
  — builds the aggregate; totalCount is computed server-side.

### PlayerPrivacyGate::canShowInSearch (NEW — apps/web/app/Services/PlayerPrivacyGate.php)

Rule 2 amendment (D-07-08-F). Tier semantics mirror `passesTier`:

```php
public function canShowInSearch(Player $player, ?User $viewer): bool
{
    if ($this->isOwnProfile($viewer, $player)) {
        return true;  // own-profile bypass — viewer always sees themselves
    }
    $tier = $player->privacy !== null ? $player->privacy->show_to : 'community';
    return match ($tier) {
        'public' => true,
        'community' => $viewer !== null,
        'clan' => $viewer !== null && $this->viewerInSameClan($viewer, $player),
        'private' => false,
        default => false,
    };
}
```

Separate entry point (not a `passesTier` alias) so SearchService doesn't
have to know about controller-level abort(404) semantics — search just
filters rows out silently.

### TypeScript types regenerated

```text
$ docker compose exec web php artisan trenchwars:typescript-generate
Tried replacing reference to `class Spatie\LaravelData\Optional` in `class App\Data\PublicPlayerData` …
All done!
Wrote 7700 bytes to /repo/packages/shared-types/src/api.d.ts
```

`api.d.ts` delta:

```text
@@ -244,6 +244,18 @@ etag: string,
 last_modified_at: string,
 };
+export type SearchResultData = {
+type: string,
+id: string,
+slug: string,
+title: string,
+excerpt: string,
+url: string,
+thumbnailUrl: string | null,
+rank: number,
+};
+export type SearchResultsData = {
+articles: App.Data.SearchResultData[],
+clans: App.Data.SearchResultData[],
+players: App.Data.SearchResultData[],
+totalCount: number,
+query: string,
+};
 export type TournamentBracketData = {
```

The `Optional` reference warnings are pre-existing noise on PublicPlayerData
(Phase 2 plan 02-07) and are unrelated to this plan — `Optional`-typed
properties are correctly stripped from the output by VisibleDataFieldsResolver
at runtime; the transformer just can't express the union type. Not a regression.

## Plan Verification Line-by-Line

| Plan verification line                                                            | Result                                                                                    |
|-----------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------|
| `make pest --filter='SearchServiceTest\|SearchResultDataTest'` GREEN              | **PASS** — 13 passed / 51 assertions (9 SearchService + 4 SearchResultData)               |
| PHPStan L8 + Pint clean                                                           | **PASS** — `phpstan [OK]` on entire codebase; Pint auto-fix on 4 new files (style only)   |
| `typescript:transform` produces App.Data.SearchResult* entries                    | **PASS** — both exports landed in `api.d.ts` lines 247-263; synced to shared-types        |
| `PlayerPrivacyGate::canShowInSearch` exists + behaves correctly across 4 tiers    | **PASS** — Rule 2 amendment added; exercised by 3 SearchServiceTest blocks (anon hide,    |
|                                                                                   |   self bypass, community tier) + indirectly by SearchResultDataTest fromPlayer block      |
| Full-suite regression-free                                                        | **PASS** — net diff vs 07-07 baseline: +14 GREEN, -2 RED (replaced SearchServiceTest stub |
|                                                                                   |   + fixed ArticleModelTest:95 regression per deferred-items.md)                            |

## Pint + PHPStan Gates

| Gate              | Files                                                  | Result                                                                  |
|-------------------|--------------------------------------------------------|-------------------------------------------------------------------------|
| `pint`            | 4 new + 3 modified                                     | **PASS** — fixed 2 style issues (single_quote on SearchServiceTest;     |
|                   |                                                        |   braces_position + phpdoc_separation on SearchService docblock)        |
| `phpstan analyse` | full codebase (app/, bootstrap/app.php, database/, routes/) | **[OK] No errors** (Larastan L8)                                |

Test files are intentionally NOT in PHPStan paths per `apps/web/phpstan.neon`
(Phase 1-6 precedent).

## Pest Surface (3 GREEN files; 25 it() blocks total — 13 new + 12 ArticleModelTest)

| File                                                                       | Pass count                | Coverage                                                                                          |
|----------------------------------------------------------------------------|---------------------------|---------------------------------------------------------------------------------------------------|
| `tests/Feature/Search/SearchServiceTest.php` (RED stub → GREEN)            | **9 GREEN** (target 7+)   | Empty-query short-circuit (0 SQL fired); FTS title match; ts_rank DESC ordering via term-freq;    |
|                                                                            |                           | private-tier player hidden from anon (T-07-08-03); private-tier player visible to self;          |
|                                                                            |                           | Pitfall 2 sanitisation (4 punctuation-laden queries: AC/DC, foo;DROP, special chars, OR 1=1);    |
|                                                                            |                           | draft article exclusion (T-07-08-05); clan FTS match; canShowInSearch community-tier behavior    |
| `tests/Unit/Data/SearchResultDataTest.php` (new)                           | **4 GREEN** (target 4+)   | fromArticle factory fields + url path; fromClan excerpt truncation + route binding; fromPlayer   |
|                                                                            |                           | display_name → username fallback; #[TypeScript] attribute presence via ReflectionClass            |
| `tests/Feature/Models/ArticleModelTest.php` (deferred-items.md fix)        | **12 GREEN** (was 11 + 1 RED) | Drop manual Event::create that collided with ArticleObserver::syncEvent auto-creation         |

Filtered run:

```text
docker compose exec -T web ./vendor/bin/pest --filter='SearchServiceTest|SearchResultDataTest|ArticleModelTest'
Tests:    25 passed (81 assertions)
Duration: 2.36s
```

Full suite regression:

```text
Tests:    10 failed, 975 passed (3035 assertions)
Duration: 59.26s
```

Baseline from 07-07 was 12 failed / 961 passed; this plan moves the baseline
to **10 failed / 975 passed** — diff: **+14 GREEN, -2 RED**. The 10 remaining
failures are all Wave 0 RED stubs owned by future Phase 7 plans (07-09..07-13):
ArticleAuditLog, ArticleHeadMeta, ArticleIndexPage, ArticleShowPage,
EventsCalendarPage, EventsFeedJsonController, CmsI18nKeyCoverage,
SearchController, SitemapGenerateCommand, SsrBundleExists.

## ts_rank ordering proof (per plan output requirement)

The plan's `<output>` requested verification of `exact ts_rank ordering
verified (title-match weight vs excerpt-match — test result)`. The result
is **D-07-08-B** (recorded above): title-vs-excerpt weight ordering is
**impossible** with the plan 07-02 unweighted tsvector under the 'simple'
text-search config. Direct PG measurement via tinker confirmed identical
0.06079271 ts_rank for two articles where `rifleman` appears once each (one
in title, one in excerpt). The ts_rank ORDERING test instead proves
ordering via term-frequency (which `simple` + ts_rank CAN differentiate):
an article with `rifleman` twice in the vector outranks one with `rifleman`
once. This still satisfies the plan intent that ts_rank ordering applies
(vs. falling back to insertion order).

**Forward path** (deferred, not in scope for plan 07-08): a future migration
can add `setweight()` to the plan 07-02 trigger function — wrap each
contributing field with a weight letter (A for title, B for excerpt, D for
slug), then use `ts_rank(vec, query, 32)` or `ts_rank_cd(vec, query, 32)`
with a custom weights array. That would enable true title-vs-excerpt weight
ordering. The exact migration is a single ALTER FUNCTION + a backfill
UPDATE; recorded in deferred-items.md if/when relevance gaps surface from
real editorial corpus.

## Deviations from Plan

### Auto-fixed issues

**1. [Rule 3 — Blocking issue] SearchResultsData::empty() collides with Spatie\LaravelData\Data::empty().**
- **Found during:** Task 1 first PHPStan run.
- **Issue:** PHPStan emitted 6 errors on `public static function empty(string $q): self`:
  parameter.missing ×3 (base method has $replaceNullValuesWith + $except + $only
  params), method.childParameterType (string vs array), parameter.notOptional
  (required vs optional $extra), method.childReturnType (self vs array).
  The base class method signature is
  `Data::empty(array $extra = [], $replaceNullValuesWith = null, array $except = [], array $only = []): array`.
- **Fix:** Rename to `forEmptyQuery(string $q): self`. Updated SearchService's
  short-circuit + class docblock to cite the rename + rationale.
- **Files modified:** `apps/web/app/Data/SearchResultsData.php`,
  `apps/web/app/Services/SearchService.php`
- **Commit:** `b751eae`
- **Recorded as:** D-07-08-C

**2. [Rule 1 — Bug] ts_rank title-position weight assertion is impossible with the plan 07-02 unweighted vector.**
- **Found during:** Task 2 first Pest run (the `ranks articles by ts_rank` test failed).
- **Issue:** The plan's must_have asked for `articles with title-match rank
  higher than excerpt-match`. The Phase 7 plan 07-02 tsvector trigger
  concatenates title + excerpt + slug into a single unweighted vector under
  the 'simple' text-search config (no setweight() calls). With 'simple' +
  ts_rank, two single-occurrence matches produce identical 0.06079271 rank
  values regardless of position. Tinker-measured directly.
- **Fix:** Rewrite the test to assert ts_rank ORDERING via term-frequency
  (which 'simple' + ts_rank can differentiate): an article with `rifleman`
  twice in the vector outranks one with `rifleman` once. Same plan intent
  (ts_rank applies, not insertion order) without requiring a 07-02 migration
  backfill to add setweight().
- **Files modified:** `apps/web/tests/Feature/Search/SearchServiceTest.php`
- **Commit:** `0e2a580`
- **Recorded as:** D-07-08-B

**3. [Rule 1 — Bug] ArticleModelTest:95 regression introduced by plan 07-06 ArticleObserver (deferred-items.md).**
- **Found during:** Task 2 — the deferred-items.md fix path called out in
  the plan execution rules.
- **Issue:** Plan 07-06 introduced ArticleObserver::syncEvent which auto-
  creates the events row on Article::factory()->create(). The test at
  ArticleModelTest:95 manually creates a SECOND events row via
  `Event::create(['eventable_type' => Article::class, 'eventable_id' => $article->id, …])`,
  which collides with the `events_one_per_owner` partial UNIQUE index
  introduced earlier. The test was already failing in the 07-07 baseline
  (13 failed / 950 passed — counted but unfixed; deferred-items.md noted
  the suggested fix path).
- **Fix:** Drop the manual Event::create() block; assert the observer-
  created row exists via `$article->fresh()?->events->count()` directly.
  Removed the now-unused `use App\Models\Event` import.
- **Files modified:** `apps/web/tests/Feature/Models/ArticleModelTest.php`
- **Commit:** `0e2a580`

### Architectural changes (Rule 4)

None.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID                                                                                       | Status                                                                                                                                                          |
|-------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| T-07-08-01 (Tampering — SQL injection via $q concatenated into tsquery)                          | **mitigated** — `whereRaw` + `orderByRaw` both use `?` placeholder; user input never concatenated into SQL. Tested via `does NOT throw on punctuation-laden / SQL-injection-shaped queries` (4 exemplars: `AC/DC`, `foo;DROP TABLE bar`, `!!!@#$%^&*()`, `' OR 1=1 --`). |
| T-07-08-02 (Tampering — stray punctuation crashing to_tsquery)                                   | **mitigated** — `plainto_tsquery` used exclusively across all three queries; never `to_tsquery`. plainto_tsquery collapses input to AND'd lexemes and silently strips operators.                                                                                       |
| T-07-08-03 (Information Disclosure — private-tier player surfacing in search results)            | **mitigated** — SearchService filters player collection through `PlayerPrivacyGate::canShowInSearch($p, $viewer)` BEFORE the DTO factory runs. Tested via negative (anon hidden) + positive (own-profile shown) cases.                                                  |
| T-07-08-04 (Information Disclosure — real-name leak via SearchResultData.title)                  | **mitigated by absence** — the Phase 2 players schema does not have a `real_name` column; fromPlayer's title resolves to display_name ?? user.username ?? slug. D-07-08-E documents the forward-compat path if a future migration adds real_name + show_real_name flag. |
| T-07-08-05 (Information Disclosure — draft article appearing in search results)                  | **mitigated** — articles query chains `->where('status', 'published')`. Tested via `excludes draft articles from results`.                                                                                                                                              |
| T-07-08-06 (Denial of Service — unbounded query result set)                                      | **mitigated** — each of the three queries has `->limit(20)`; total response capped at 60 rows; Postgres FTS GIN index lookups are sub-millisecond on the round-1 corpus.                                                                                                |
| T-07-08-07 (Information Disclosure — tsvector index leaking field values cross-tier)             | **accepted** — tsvector stores lexemes not original text. The defence-in-depth lower layer (plan 07-02 trigger) only indexes public-by-default columns (display_name + slug); private columns like real_name + discord_tag never reach the index in the first place.    |
| T-07-08-08 (Denial of Service — repeated complex queries exhausting Postgres CPU)                | **deferred to plan 07-09** — SearchController will wrap the service in `throttle:60,1` (Phase 6 D-06-12-A precedent). No-op at service layer; the service is the wrong defense layer for HTTP rate-limiting.                                                            |

## Known Stubs

None. SearchService + SearchResultData + SearchResultsData are fully wired
and exercised by GREEN end-to-end tests. PlayerPrivacyGate::canShowInSearch
is a complete implementation (not a placeholder).

## Threat Flags

None. The plan's `<threat_model>` covered every surface introduced (SQL
injection via $q, to_tsquery footgun, private-tier player leak, real-name
leak, draft article disclosure, unbounded result set, index cross-tier
leak, DoS via repeated queries). No new endpoints introduced (controller
lands in plan 07-09); no new file-access patterns; no new schema changes
at trust boundaries (tsvector substrate was plan 07-02).

## Commit Trail

| Task                                                                                              | Commit    | Files                                                                                                                                                                   |
|---------------------------------------------------------------------------------------------------|-----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 1: SearchService + SearchResultData + SearchResultsData + PlayerPrivacyGate::canShowInSearch + TS | `b751eae` | 6 (3 created in apps/web/app, 1 modified PlayerPrivacyGate, 2 modified TS api.d.ts + shared-types)                                                                       |
| 2: SearchServiceTest 9 GREEN + SearchResultDataTest 4 GREEN + ArticleModelTest:95 regression fix  | `0e2a580` | 3 (1 created SearchResultDataTest, 1 modified RED stub → GREEN SearchServiceTest, 1 modified ArticleModelTest)                                                            |

## Self-Check

- [x] `apps/web/app/Services/SearchService.php` — FOUND
- [x] `apps/web/app/Data/SearchResultData.php` — FOUND
- [x] `apps/web/app/Data/SearchResultsData.php` — FOUND
- [x] `apps/web/app/Services/PlayerPrivacyGate.php` — FOUND (modified, +canShowInSearch)
- [x] `apps/web/tests/Feature/Search/SearchServiceTest.php` — FOUND (modified, RED → 9 GREEN)
- [x] `apps/web/tests/Unit/Data/SearchResultDataTest.php` — FOUND (created, 4 GREEN)
- [x] `apps/web/tests/Feature/Models/ArticleModelTest.php` — FOUND (modified, deferred-items.md fix)
- [x] `apps/web/resources/js/types/api.d.ts` — FOUND (modified, +SearchResult* exports lines 247-263)
- [x] `packages/shared-types/src/api.d.ts` — FOUND (modified, mirrored from api.d.ts)
- [x] commit `b751eae` — FOUND in git log
- [x] commit `0e2a580` — FOUND in git log
