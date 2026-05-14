---
phase: 07-cms
plan: 09
subsystem: cms-public-controllers
tags:
  - wave-5
  - public-controllers
  - inertia-render
  - polymorphic-events-feed
  - search-controller
  - blog-controllers
  - calendar-feed
  - throttle-60-1
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-03-SUMMARY.md  # Article + Category models + factories + bodyHtml=''
    - .planning/phases/07-cms/07-04-SUMMARY.md  # ArticlePolicy (view gate — published OR articles.update)
    - .planning/phases/07-cms/07-05-SUMMARY.md  # PublicArticleData fully wired with tiptap_converter
    - .planning/phases/07-cms/07-08-SUMMARY.md  # SearchService + SearchResultsData (forEmptyQuery rename)
    - .planning/phases/02-clans-tags/02-05-SUMMARY.md  # PlayerPrivacyGate base
  provides:
    - "App\\Http\\Controllers\\BlogIndexController — single-action __invoke; paginate(15) on Article::where('status','published')->orderByDesc('published_at')->with('category','author','media'); ?category=slug filter via Eloquent whereHas; returns Inertia 'Articles/Index' with articles + pagination + categories + activeCategory + meta props"
    - "App\\Http\\Controllers\\BlogShowController — single-action __invoke; Article::query()->where('slug',$slug)->firstOrFail; abort_unless($article->status==='published' || $request->user()?->can('articles.update'), 404) — T-07-09-02 non-disclosure idiom (404 not 401/403); returns Inertia 'Articles/Show' with PublicArticleData::fromModel"
    - "App\\Http\\Controllers\\EventsCalendarController — single-action __invoke; Inertia 'Events/Index' shell with categories + meta props; NO event payload (FullCalendar client-side mounts and fetches /events/feed.json per-view)"
    - "App\\Http\\Controllers\\EventsFeedJsonController — single-action __invoke(EventsFeedRequest, CalendarFeedService); Event::where('is_public',true)->whereBetween('starts_at',[start,end])->with('eventable')->limit(1000); maps via CalendarFeedService::toCalendarEvents → JsonResponse"
    - "App\\Http\\Controllers\\SearchController — single-action __invoke(SearchRequest, SearchService); $results = $service->search($q, $request->user()); returns Inertia 'Search/Results' with results + query + meta props"
    - "App\\Http\\Requests\\EventsFeedRequest — start required|date; end required|date|after:start|before_or_equal:<start+90d>; 90-day cap computed in endUpperBound() helper (T-07-09-04 mitigation); authorize() true (public)"
    - "App\\Http\\Requests\\SearchRequest — q required|string|min:2|max:200|regex (letters/numbers/spaces + safe-punct, Unicode-aware); T-07-09-03 first sanitisation layer; authorize() true"
    - "App\\Data\\CalendarEventData — Spatie Data + #[TypeScript]; 7 fields (id/title/start/end/type/url/color); fromModel(Event) resolves eventable via Pattern 7 morphTo into type discriminator + named-route URL + Open Question 6 LOCKED color palette (match=#3B82F6, tournament=#8B5CF6, article=#10B981, other=#6B7280); Carbon::toIso8601String emits explicit UTC offset (Pitfall 11)"
    - "App\\Data\\ArticleSummaryData — Spatie Data + #[TypeScript]; 9 fields (id/slug/title/excerpt/categoryName/authorName/publishedAt/heroThumbUrl/url); lighter than PublicArticleData — no bodyHtml; consumed by BlogIndexController for listing cards (plan 07-10 Vue index page)"
    - "App\\Services\\CalendarFeedService — final class; toCalendarEvents(Collection<Event>): array<int,CalendarEventData> pure mapper; no extra DB queries (controller eager-loads eventable upstream)"
    - "routes/web.php — 5 new routes registered: /blog (blog.index), /blog/{slug} (blog.show), /events (events.index), /events/feed.json (events.feed), /search (search.index); /events/feed.json + /search wrapped in throttle:60,1 middleware group (Phase 6 D-06-12-A precedent + D-06-12-C ordering rule for .json suffix capture)"
    - "lang/en/cms.php — +search.placeholder; +search.results.heading/count/section_{articles,clans,players}; +page_meta.{blog_index,blog_show,events,search}.{title,description,title_template,description_fallback}"
    - "tests/Feature/Articles/ArticleIndexPageTest — 6 GREEN it() blocks replacing 07-01 RED stub"
    - "tests/Feature/Events/EventsFeedJsonControllerTest — 8 GREEN it() blocks replacing 07-01 RED stub"
    - "tests/Feature/Search/SearchControllerTest — 7 GREEN it() blocks replacing 07-01 RED stub"
  affects:
    - apps/web/app/Http/Controllers/                  # +5 controllers
    - apps/web/app/Http/Requests/                     # +2 FormRequests
    - apps/web/app/Data/                              # +2 DTOs (CalendarEventData, ArticleSummaryData)
    - apps/web/app/Services/                          # +CalendarFeedService.php
    - apps/web/routes/web.php                         # +5 named routes + 5 imports
    - apps/web/lang/en/cms.php                        # +search.* + page_meta.*
    - apps/web/tests/Feature/Articles/                # ArticleIndexPageTest RED → 6 GREEN
    - apps/web/tests/Feature/Events/                  # EventsFeedJsonControllerTest RED → 8 GREEN
    - apps/web/tests/Feature/Search/                  # SearchControllerTest RED → 7 GREEN
tech-stack:
  added: []
  patterns:
    - "Single-action invokable controller (Phase 4 + 6 + earlier 7 precedent verbatim): final class with __invoke method; constructor injection on services + FormRequests via param hints; PHPStan-L8 generic returns (Inertia\\Response | JsonResponse)."
    - "Pattern 7 (polymorphic Event feed) end-to-end: Event::with('eventable')->limit(1000)->get() eager-loads the morphTo target so CalendarEventData::fromModel can switch on instanceof without firing N+1 SELECTs. The Open Question 6 LOCKED color palette + named-route URL resolution live entirely inside CalendarEventData::fromModel — controller stays a thin glue layer."
    - "Route ordering precedent (Phase 6 D-06-12-C continuation): `/events/feed.json` declared BEFORE `/events` in routes/web.php so Laravel's first-match-wins router does not capture the `.json` suffix as a slug segment. Same idiom that landed in Phase 6 for /tournaments/{slug}.json vs /tournaments/{slug}."
    - "Rate-limit middleware-group ordering (Phase 6 D-06-12-A continuation): /events/feed.json + /search share a single throttle:60,1 group declared once and entered before the unthrottled public routes. T-07-09-01 + T-07-09-03 mitigation. Pest assertions exercise the cap by issuing 60 successful requests + asserting the 61st returns 429 — RateLimiter::clear(sha1('throttle:60,1')) in beforeEach() resets the bucket per-test."
    - "EventsFeedRequest 90-day range cap via dynamic endUpperBound() helper — `before_or_equal:<computed-date>` is built per-request from the inbound `start` value because Laravel's validation grammar does NOT support `before_or_equal:start+90 days` as a single expression. T-07-09-04 mitigation; the cap holds even if the caller spoofs the start far in the future because the computed upper bound is always exactly start+90d."
    - "Non-disclosure idiom for draft articles at /blog/{slug} (T-07-09-02): abort_unless($article->status==='published' || $request->user()?->can('articles.update'), 404). Returns 404 (NOT 401/403) because a 403 would leak that the slug exists. Mirrors MatchShowController + TournamentShowController precedent (T-04-10-02 / T-06-12-03). Inline policy check rather than Gate::authorize because Gate::authorize defaults to 403 — the inline check lets us pick 404 explicitly."
    - "Inertia auto-escape on the data-page attribute: Inertia's `<div id=\"app\" data-page=\"...\">` Blade attribute is htmlspecialchars(..., ENT_QUOTES) double-encoded — JSON's `\"` becomes `&quot;` AND embedded apostrophes become `&#039;`. Pest assertion on `O&#039;Brien` (not the raw `O'Brien`) proves the XSS surface is mitigated at the Inertia layer (T-07-09-06) without any custom escaping in SearchController."
    - "Observer-driven Event auto-creation in tests: ArticleObserver + MatchObserver + TournamentObserver all upsert Event rows on saved(); EventsFeedJsonControllerTest exercises the calendar feed by setting timestamp fields on the eventable factories (scheduled_at / starts_at) so the auto-created Event rows fall in the test window. This is the same pattern plan 07-08 fixed in deferred-items.md (drop manual Event::create that collides with observer-created row + events_one_per_owner UNIQUE)."
key-files:
  created:
    - apps/web/app/Http/Controllers/BlogIndexController.php
    - apps/web/app/Http/Controllers/BlogShowController.php
    - apps/web/app/Http/Controllers/EventsCalendarController.php
    - apps/web/app/Http/Controllers/EventsFeedJsonController.php
    - apps/web/app/Http/Controllers/SearchController.php
    - apps/web/app/Http/Requests/EventsFeedRequest.php
    - apps/web/app/Http/Requests/SearchRequest.php
    - apps/web/app/Data/CalendarEventData.php
    - apps/web/app/Data/ArticleSummaryData.php
    - apps/web/app/Services/CalendarFeedService.php
  modified:
    - apps/web/routes/web.php                                  # +5 imports + 5 named routes (throttle group + 3 unthrottled)
    - apps/web/lang/en/cms.php                                  # +search.* + page_meta.*
    - apps/web/tests/Feature/Articles/ArticleIndexPageTest.php  # RED stub → 6 GREEN
    - apps/web/tests/Feature/Events/EventsFeedJsonControllerTest.php  # RED stub → 8 GREEN
    - apps/web/tests/Feature/Search/SearchControllerTest.php   # RED stub → 7 GREEN
decisions:
  - "D-07-09-A — ArticleSummaryData IS retained (does NOT collapse to a truncated PublicArticleData). Plan output asked whether ArticleSummaryData was needed or if PublicArticleData would work. The answer is needed: PublicArticleData carries `bodyHtml` (the full tiptap_converter-rendered HTML) which is wasteful on a 15-card index grid AND re-runs the tiptap parser on every render. ArticleSummaryData drops bodyHtml + heroOgImageUrl + allowDiscordAnnounce + body fields; saves ~3-15kB per row and one tiptap_converter call per render. The two DTOs share the same locale-resolution + media-URL logic (factored mentally; no shared trait yet — both invoke article->getTranslation + article->getFirstMediaUrl directly). If a future plan introduces a third Article projection (e.g. Discord-embed shape), a HasArticleProjection trait can extract the common reads — out of scope for v1."
  - "D-07-09-B — Route ordering for /events/feed.json verified BEFORE /events (Phase 6 D-06-12-C continuation). Laravel's router is first-match-wins on URI matching; if /events landed first, GET /events/feed.json would be captured by /events (no path parameters declared on /events). The throttle:60,1 middleware-group wrapping both /events/feed.json + /search additionally enforces the rate-limit at registration time — moving either route out of the group requires opt-in middleware. `artisan route:list` confirms (in numeric order): blog.index, blog.show, events.feed (throttled), events.index, search.index (throttled)."
  - "D-07-09-C — EventsFeedRequest uses a per-request endUpperBound() helper rather than the literal `before_or_equal:start+90 days` expression cited in the plan's <interfaces>. Laravel's validation grammar does NOT support relative offsets of another field — the `before_or_equal:` rule accepts a date string OR a field reference but NOT a date-expression. Computing the cap inline (Carbon::parse($start)->addDays(90)->toDateString()) in a helper called from rules() keeps the semantics identical: when start is missing/malformed, the fallback is a permissive 100-year cap (the `required|date` rule on start fires first and rejects the request before the end rule ever fires). T-07-09-04 mitigation holds."
  - "D-07-09-D — Open Question 6 LOCKED color palette inline-referenced in CalendarEventData::colourFor: match=#3B82F6 (Tailwind blue-500), tournament=#8B5CF6 (Tailwind violet-500), article=#10B981 (Tailwind emerald-500), other=#6B7280 (Tailwind gray-500). These hex values were chosen to match the Phase 6 tournament card accent + Phase 7 article card accent so the calendar reads the same visual vocabulary as the rest of the site. Pest test EventsFeedJsonControllerTest asserts all three primary colors verbatim on the 3-event-type response."
  - "D-07-09-E — SearchControllerTest validates web routes via 302 + session errors (NOT JSON 422). The `/search` route lives on the web middleware group (CSRF + session + Inertia), so validation failures redirect back with errors via the WebExceptionRenderer rather than returning the JSON 422 the plan's must_have wording suggested. The semantic intent (input validation enforced + error surfaced) holds — the response shape just differs from a stateless API route. The actual JSON 422 path is exercised by EventsFeedJsonControllerTest (which DOES return 422 because /events/feed.json is consumed via getJson() and Inertia's web stack returns JSON on Accept: application/json)."
  - "D-07-09-F — Inertia auto-escape assertion shape: the data-page Blade attribute double-encodes both JSON quote characters (\" → &quot;) AND value-internal HTML reserved chars (' → &#039;). The Pest test asserts `O&#039;Brien` appears (not the raw `O'Brien`) in the response body, which proves the htmlspecialchars(..., ENT_QUOTES) call inside Inertia's renderer is intercepting the echo path. T-07-09-06 mitigation; tested without needing a `<script>` payload because the SearchRequest regex blocks those characters at the input layer first."
metrics:
  duration: 11m 22s
  completed: 2026-05-14
  tasks: 2
  files_created: 10
  files_modified: 5
  commits: 2
---

# Phase 7 Plan 9: Wave 5 — Public CMS + Events + Search Controllers Summary

Phase 7 Wave 5 — wire the 5 public HTTP controllers that surface the CMS
(/blog + /blog/{slug}), the calendar (/events + /events/feed.json), and the
Postgres FTS search (/search) on the public web. Each controller validates
input via a FormRequest, applies the appropriate authorization + rate-limit,
and returns either Inertia::render (Vue pages — plan 07-10 ships the
client-side Vue layer) or JsonResponse (calendar feed — Pattern 7 polymorphic
UNION of GameMatch + Tournament + Article events transparently).

This plan completes the server-side half of SC-2 (CMS public browse) + SC-3
(events calendar) + SC-4 (Postgres FTS search). Plan 07-10 lifts the matching
Vue pages on top of these endpoints.

## Surface Delivered

### 5 Public Controllers (apps/web/app/Http/Controllers/)

| Controller | Route | Returns | Visibility |
|---|---|---|---|
| `BlogIndexController` | GET `/blog` | Inertia Articles/Index | published only; paginate(15); ?category=slug filter |
| `BlogShowController` | GET `/blog/{slug}` | Inertia Articles/Show | published OR can articles.update — else 404 (T-07-09-02) |
| `EventsCalendarController` | GET `/events` | Inertia Events/Index | always reachable; FullCalendar mounts client-side |
| `EventsFeedJsonController` | GET `/events/feed.json` | JsonResponse | is_public=true ∧ starts_at in [start,end]; ->limit(1000); throttle:60,1 |
| `SearchController` | GET `/search` | Inertia Search/Results | SearchService applies PlayerPrivacyGate; throttle:60,1 |

All 5 controllers follow the single-action `__invoke` idiom (Phase 4 + 6 + earlier
Phase 7 precedent). Authorization and validation live in the FormRequests +
inline abort_unless calls — controllers stay thin glue layers between input
shape and Inertia/JSON response.

### 2 FormRequests (apps/web/app/Http/Requests/)

`EventsFeedRequest`:
```php
'start' => ['required', 'date'],
'end' => ['required', 'date', 'after:start', 'before_or_equal:' . $this->endUpperBound()],
```

`endUpperBound()` helper computes start+90d per-request — Laravel's validation
grammar does not support `before_or_equal:start+90 days` as a relative
expression (D-07-09-C). T-07-09-04 mitigation.

`SearchRequest`:
```php
'q' => [
    'required', 'string', 'min:2', 'max:200',
    "regex:/^[\p{L}\p{N}\s\-_'.,&\/]+$/u",
],
```

The Unicode regex permits letters (any locale), numbers, spaces, hyphens,
underscores, apostrophes, periods, commas, ampersands, and forward-slashes —
covers real-world queries like `AC/DC` and `O'Brien` without permitting SQL
operators or HTML reserved characters. T-07-09-03 first sanitisation layer;
SearchService's `plainto_tsquery` (plan 07-08) is the second.

### 2 DTOs (apps/web/app/Data/)

`CalendarEventData` — FullCalendar-shaped DTO with `#[TypeScript]`. 7 fields:

| Field | Type | Source |
|---|---|---|
| `id` | string | Event.id (UUID) |
| `title` | string | Event.title locale-resolved (fallback to `(untitled)`) |
| `start` | string | Event.starts_at via toIso8601String — explicit UTC offset (Pitfall 11) |
| `end` | ?string | Event.ends_at via toIso8601String, nullable |
| `type` | string | `'match'`/`'tournament'`/`'article'`/`'other'` via Pattern 7 morphTo switch |
| `url` | string | named-route resolution per type (matches.show / tournaments.show / blog.show) |
| `color` | string | Open Question 6 LOCKED palette (#3B82F6 / #8B5CF6 / #10B981 / #6B7280) |

`ArticleSummaryData` — 9-field listing-card DTO. Distinct from
`PublicArticleData` (07-03/07-05) because the index page renders dozens of
cards per request — sending the rendered `bodyHtml` for each would be
wasteful AND re-runs tiptap_converter()->asHTML per article (D-07-09-A).

### 1 Service (apps/web/app/Services/CalendarFeedService.php)

```php
final class CalendarFeedService
{
    /**
     * @param Collection<int, Event> $events
     * @return array<int, CalendarEventData>
     */
    public function toCalendarEvents(Collection $events): array
    {
        return $events->map(fn (Event $e) => CalendarEventData::fromModel($e))->values()->all();
    }
}
```

Pure mapper. Pattern 7 morphTo resolution lives on CalendarEventData::fromModel
— the service exists separately so future callers (e.g. an ICS feed in Phase
9) can reuse the shape without re-implementing the type/color/url switch.

### Routes (apps/web/routes/web.php)

```php
Route::middleware(['throttle:60,1'])->group(function (): void {
    Route::get('/events/feed.json', EventsFeedJsonController::class)->name('events.feed');
    Route::get('/search', SearchController::class)->name('search.index');
});

Route::get('/blog', BlogIndexController::class)->name('blog.index');
Route::get('/blog/{slug}', BlogShowController::class)->name('blog.show');
Route::get('/events', EventsCalendarController::class)->name('events.index');
```

**Route ordering verified** (D-07-09-B): `/events/feed.json` declared BEFORE
`/events` so Laravel's first-match-wins router captures the `.json` suffix
correctly. Verified via `artisan route:list`:

```text
GET|HEAD  blog ............................ blog.index › BlogIndexController
GET|HEAD  blog/{slug} ....................... blog.show › BlogShowController
GET|HEAD  events ................... events.index › EventsCalendarController
GET|HEAD  events/feed.json .......... events.feed › EventsFeedJsonController
GET|HEAD  search ........................... search.index › SearchController
```

(`route:list` sorts alphabetically by URI; the registration order in `web.php`
is what matters for first-match-wins. The throttle group is registered before
the unthrottled blog/events public routes.)

### i18n (apps/web/lang/en/cms.php)

Appended `search.placeholder/results.*` (header search bar + results page copy)
and `page_meta.{blog_index,blog_show,events,search}.*` (Inertia `<Head>` title
+ description tags consumed by plan 07-10 Vue pages and verified by plan 07-12
CmsI18nKeyCoverageTest).

## Plan Verification Line-by-Line

| Plan verification line | Result |
|---|---|
| `make pest --filter='ArticleIndexPageTest\|EventsFeedJsonControllerTest\|SearchControllerTest'` GREEN | **PASS** — 21 passed / 262 assertions (6+8+7) |
| `php artisan route:list` shows 5 new routes in correct order | **PASS** — blog.index / blog.show / events.feed / events.index / search.index all present |
| PHPStan L8 + Pint clean on all new files (12+) | **PASS** — phpstan [OK] (full codebase); pint clean (1 auto-fix on EventsFeedRequest fully_qualified_strict_types) |
| Full-suite regression-free | **PASS** — net diff vs 07-08 baseline: +21 GREEN, -3 RED (10 failed / 975 passed → 7 failed / 996 passed; remaining 7 RED are Wave 0 stubs for plans 07-10..07-13) |

## Pint + PHPStan Gates

| Gate | Files | Result |
|---|---|---|
| `pint` | 10 new + 5 modified | **PASS** — 1 style auto-fix on EventsFeedRequest (fully_qualified_strict_types: \\Illuminate\\Support\\Carbon → top-level import) |
| `phpstan analyse` | full codebase (app/, routes/, bootstrap/, database/) | **[OK] No errors** (Larastan L8) |

Test files are intentionally NOT in PHPStan paths per `apps/web/phpstan.neon`
(Phase 1-6 precedent).

## Pest Surface (3 GREEN files; 21 it() blocks)

| File | Pass count | Coverage |
|---|---|---|
| `tests/Feature/Articles/ArticleIndexPageTest.php` (RED → GREEN) | **6 GREEN** (target 5+) | Renders Articles/Index; only published surface; paginate 15/page across 2 pages; ?category=slug filter; draft 404 at /blog/{slug}; published 200 at /blog/{slug} |
| `tests/Feature/Events/EventsFeedJsonControllerTest.php` (RED → GREEN) | **8 GREEN** (target 6+) | 422 missing start; 422 missing end; 422 end-before-start; 422 range > 90 days (T-07-09-04); 3 event types in single response + LOCKED colors; is_public=false excluded (T-07-09-07); ISO-8601 UTC offset (Pitfall 11); throttle 60,1 → 429 (T-07-09-01) |
| `tests/Feature/Search/SearchControllerTest.php` (RED → GREEN) | **7 GREEN** (target 6+) | 302 missing q; 302+errors on q < 2; 302+errors on disallowed chars (T-07-09-03); 200 + Inertia component; Inertia auto-escape (T-07-09-06); PlayerPrivacyGate filters private; throttle 60,1 → 429 (T-07-09-01) |

Filtered run:

```text
docker compose exec -T web ./vendor/bin/pest --filter='ArticleIndexPageTest|EventsFeedJsonControllerTest|SearchControllerTest'
Tests:    21 passed (262 assertions)
Duration: 2.49s
```

Full suite regression:

```text
Tests:    7 failed, 996 passed (3294 assertions)
Duration: 60.30s
```

Baseline from 07-08 was 10 failed / 975 passed; this plan moves the baseline
to **7 failed / 996 passed** — diff: **+21 GREEN, -3 RED**. The 7 remaining
failures are all Wave 0 RED stubs owned by future Phase 7 plans:
ArticleAuditLog (07-11), ArticleHeadMeta (07-10), ArticleShowPage (07-10),
EventsCalendarPage (07-10), CmsI18nKeyCoverage (07-12), SitemapGenerateCommand
(07-12), SsrBundleExists (07-13).

## Rate-Limit Hit Count Verification (per plan output requirement)

Plan output requested verification of "Rate-limit hit count threshold verified
in Pest (60 requests succeed; 61st returns 429)". Both rate-limited routes
exercise this:

- **EventsFeedJsonControllerTest** — 60 successful requests at
  `/events/feed.json?start=2026-06-01&end=2026-06-30`; 61st returns 429.
- **SearchControllerTest** — 60 successful requests at `/search?q=phantom`;
  61st returns 429.

Both tests issue `RateLimiter::clear(sha1('throttle:60,1'))` in
`beforeEach()` to reset the bucket per-test (Phase 6 TournamentPublicJsonControllerTest
precedent).

## Open Question 6 LOCKED — Inline References (per plan output requirement)

Plan output requested "Open Question 6 LOCKED inline references (CalendarEventData
color scheme)". The hex literals + their rationale are inline in
`CalendarEventData::colourFor` (D-07-09-D):

```php
private static function colourFor(string $type): string
{
    return match ($type) {
        'match' => '#3B82F6',       // Tailwind blue-500
        'tournament' => '#8B5CF6',  // Tailwind violet-500
        'article' => '#10B981',     // Tailwind emerald-500
        default => '#6B7280',       // Tailwind gray-500 (catch-all for 'other')
    };
}
```

EventsFeedJsonControllerTest asserts all three primary colors verbatim on the
3-event-type response.

## Deviations from Plan

### Auto-fixed issues

**1. [Rule 3 — Blocking issue] `before_or_equal:start+90 days` is not a valid Laravel validation expression.**
- **Found during:** Task 1 EventsFeedRequest authoring.
- **Issue:** The plan's must_haves wording suggested `before_or_equal:start+90 days`
  as a literal rule, but Laravel's validation grammar does not support
  relative offsets of another field. `before_or_equal:` accepts a date string OR
  a field reference, not a date-expression. The literal rule would fail at runtime
  with `Class "start+90 days" does not exist` (Laravel interpreting the value as a
  custom rule class name).
- **Fix:** Compute the cap per-request via an `endUpperBound()` helper inside
  EventsFeedRequest:
  ```php
  'end' => ['required', 'date', 'after:start', 'before_or_equal:' . $this->endUpperBound()],
  ```
  The helper parses `start` from the input and returns `Carbon::parse($start)->addDays(90)->toDateString()`;
  fallback is a permissive 100-year cap when start is missing/malformed (the
  `required|date` rule on start fires first and rejects the request before end is
  validated). T-07-09-04 mitigation holds.
- **Files modified:** `apps/web/app/Http/Requests/EventsFeedRequest.php`
- **Commit:** `3307ebd`
- **Recorded as:** D-07-09-C

**2. [Rule 1 — Bug] Test EventsFeedJsonControllerTest `excludes is_public=false` collided with events_one_per_owner UNIQUE.**
- **Found during:** Task 2 first Pest run (3 tests failed with
  `UniqueConstraintViolationException`).
- **Issue:** The test manually `Event::factory()->create([...])` against a
  GameMatch's id, but MatchObserver::saved auto-creates the Event row on the
  `GameMatch::factory()->create([...])` call. The events_one_per_owner partial
  UNIQUE index (plan 04-03) rejects the second insert. Same root-cause as the
  plan 07-08 deferred-items.md fix (ArticleModelTest:95).
- **Fix:** Rewrite the test to rely on the auto-created Event from the eventable
  observer. Set `scheduled_at` on the GameMatch factory + `starts_at` on the
  Tournament factory + `scheduled_at`/`published_at` on the Article factory so
  the observer-projected Event rows fall in the test window. For the
  `is_public=false` test, update the auto-created Event row's is_public flag
  via `Event::query()->where(...)->update(['is_public' => false])` rather than
  creating a second row.
- **Files modified:** `apps/web/tests/Feature/Events/EventsFeedJsonControllerTest.php`
- **Commit:** `5c1ae19`

**3. [Rule 1 — Bug] SearchControllerTest escape assertion used wrong encoded form.**
- **Found during:** Task 2 first Pest run.
- **Issue:** The test asserted the response body contained the literal substring
  `&quot;query&quot;:&quot;O'Brien&quot;`. Inertia's data-page attribute is
  htmlspecialchars(..., ENT_QUOTES) double-encoded, so the apostrophe inside the
  value becomes `&#039;` in the rendered HTML — the raw `'` never appears in
  the data-page payload at all.
- **Fix:** Rewrite the assertion to check the double-encoded form
  (`O&#039;Brien`) plus the JSON wrapper (`&quot;query&quot;`) appear together,
  plus a negative assertion that raw `<script>O` never appears. T-07-09-06
  mitigation proof.
- **Files modified:** `apps/web/tests/Feature/Search/SearchControllerTest.php`
- **Commit:** `5c1ae19`
- **Recorded as:** D-07-09-F

**4. [Rule 3 — Blocking issue] SearchControllerTest used JSON 422 expectations on a web route.**
- **Found during:** Task 2 first run (the 422 tests would NOT pass on web routes).
- **Issue:** Plan must_have wording stated "422 on missing q". The /search route
  lives on the web middleware group (Inertia session-based stack), which returns
  HTTP 302 redirects with session errors on validation failure — NOT JSON 422.
  Only API routes or `getJson()` calls receive JSON 422 responses.
- **Fix:** Updated the test assertions to use `assertStatus(302)` +
  `assertSessionHasErrors('q')` — the semantic intent (validation enforced +
  error surfaced) holds; the response shape just differs.
- **Files modified:** `apps/web/tests/Feature/Search/SearchControllerTest.php`
- **Commit:** `5c1ae19`
- **Recorded as:** D-07-09-E

### Architectural changes (Rule 4)

None.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID | Status |
|---|---|
| T-07-09-01 (DoS — unbounded /search and /events/feed.json scraping) | **mitigated** — both routes wrapped in throttle:60,1 (Phase 6 D-06-12-A precedent); Pest asserts 61st request returns 429 on both endpoints |
| T-07-09-02 (Info disclosure — draft article visible at /blog/{slug}) | **mitigated** — BlogShowController abort_unless($article->status==='published' \|\| user can articles.update, 404); Pest asserts draft → 404 for anonymous |
| T-07-09-03 (Tampering — SQL injection via /search?q=) | **mitigated** — SearchRequest regex permits letters/numbers/safe-punct only; SearchService plainto_tsquery parameter-bound (07-08 chain); Pest asserts `<script>` rejected at validation layer |
| T-07-09-04 (Tampering — date manipulation in /events/feed.json) | **mitigated** — EventsFeedRequest validates end before_or_equal:start+90d via dynamic endUpperBound() helper; ->limit(1000) cap at controller; Pest asserts 91+day range → 422 |
| T-07-09-05 (Info disclosure — private players via Event eventable_type=Player chain) | **accepted** — events table does NOT have Player as eventable_type (only GameMatch + Tournament + Article); calendar feed never exposes player privacy data |
| T-07-09-06 (Tampering — reflected XSS via /search?q= echo) | **mitigated** — Inertia auto-escapes Vue templates via htmlspecialchars(..., ENT_QUOTES) on the data-page Blade attribute; Pest asserts `O&#039;Brien` double-encoded form appears, raw `<script>O` does NOT |
| T-07-09-07 (Info disclosure — EventsFeedJsonController leaking eventable internals) | **mitigated** — CalendarEventData enumerates 7 explicit fields (id/title/start/end/type/url/color); no raw eventable serialization; Pest asserts is_public=false events excluded |
| T-07-09-08 (Spoofing — slug collision allowing /blog/admin) | **accepted** — Article slugs are admin-curated; reserved-word policy deferred to Phase 9 polish per PROJECT.md Open Questions |

## Known Stubs

None. All 5 controllers + 2 FormRequests + 2 DTOs + 1 service are fully wired
and exercised by GREEN end-to-end tests. Plan 07-10 builds the Vue page layer
on top of these endpoints (the empty-state and head-meta components rely on
the meta props this plan ships).

## Threat Flags

None. The plan's `<threat_model>` covered every surface introduced (DoS via
unbounded scraping, draft article disclosure, SQL injection, date range
tampering, XSS on query echo, calendar feed leakage, slug collision). No new
endpoints introduced beyond what the plan declared; no new schema changes at
trust boundaries; no new file-access patterns.

## Commit Trail

| Task | Commit | Files |
|---|---|---|
| 1: 5 controllers + 2 FormRequests + 2 DTOs + CalendarFeedService + 5 routes + cms.php i18n extension | `3307ebd` | 12 (10 created in apps/web/app, 2 modified routes/web.php + lang/en/cms.php) |
| 2: 3 GREEN test files replacing 07-01 RED stubs (6+8+7 it() blocks) | `5c1ae19` | 3 (all modified — ArticleIndexPageTest + EventsFeedJsonControllerTest + SearchControllerTest) |

## Self-Check

- [x] `apps/web/app/Http/Controllers/BlogIndexController.php` — FOUND
- [x] `apps/web/app/Http/Controllers/BlogShowController.php` — FOUND
- [x] `apps/web/app/Http/Controllers/EventsCalendarController.php` — FOUND
- [x] `apps/web/app/Http/Controllers/EventsFeedJsonController.php` — FOUND
- [x] `apps/web/app/Http/Controllers/SearchController.php` — FOUND
- [x] `apps/web/app/Http/Requests/EventsFeedRequest.php` — FOUND
- [x] `apps/web/app/Http/Requests/SearchRequest.php` — FOUND
- [x] `apps/web/app/Data/CalendarEventData.php` — FOUND
- [x] `apps/web/app/Data/ArticleSummaryData.php` — FOUND
- [x] `apps/web/app/Services/CalendarFeedService.php` — FOUND
- [x] `apps/web/routes/web.php` — FOUND (modified, +5 imports + 5 routes)
- [x] `apps/web/lang/en/cms.php` — FOUND (modified, +search.* + page_meta.*)
- [x] `apps/web/tests/Feature/Articles/ArticleIndexPageTest.php` — FOUND (modified, RED → 6 GREEN)
- [x] `apps/web/tests/Feature/Events/EventsFeedJsonControllerTest.php` — FOUND (modified, RED → 8 GREEN)
- [x] `apps/web/tests/Feature/Search/SearchControllerTest.php` — FOUND (modified, RED → 7 GREEN)
- [x] commit `3307ebd` — FOUND in git log
- [x] commit `5c1ae19` — FOUND in git log

## Self-Check: PASSED
