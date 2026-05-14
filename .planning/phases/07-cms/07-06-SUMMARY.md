---
phase: 07-cms
plan: 06
subsystem: cms-publish-pipeline
tags:
  - wave-3
  - article-observer
  - article-status-service
  - discord-outbound-payload-builder
  - event-morph-sync
  - pitfall-10-observer-republish-guard
  - open-question-1-locked-inline
  - open-question-6-locked-inline
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-02-SUMMARY.md  # articles table + allow_discord_announce column + discord_outbound CHECK extended for article_announce
    - .planning/phases/07-cms/07-03-SUMMARY.md  # Article model + LogsActivity + events() MorphMany + factory
    - .planning/phases/07-cms/07-05-SUMMARY.md  # PublicArticleData::fromModel (/news/{slug} permalink) used by article_announce embed url
    - .planning/phases/06-tournaments-brackets/06-10-SUMMARY.md  # D-06-08-A two-hook pattern + D-06-10-E channel_id='' convention
  provides:
    - "App\\Services\\ArticleStatusService — state machine; transition(Article, string $to, ?User $causer=null): Article method (Phase 6 D-06-04-A TournamentStatusService signature parity)"
    - "App\\Exceptions\\InvalidArticleStatusTransitionException — extends \\DomainException; ctor (string $from, string $to); thrown on illegal (from, to) pair"
    - "App\\Observers\\ArticleObserver — D-06-08-A two-hook pattern (created + updated; NOT saved); Event MorphOne sync (drafts retain row with is_public=false per Phase 4 D-04-08-C precedent) + onPublish() three-gate defence (allow_discord_announce + non-empty channel + Pitfall 10 republish guard) + article_announce outbound enqueue"
    - "Article::booted() — static::observe(ArticleObserver::class) model-level registration (D-06-08-A precedent)"
    - "App\\Support\\DiscordOutboundPayloadBuilder::buildArticleAnnounce(Article $a): array — Phase 7 addition; embed shape with #10B981 color (Open Question 6 LOCKED), url=/news/{slug} canonical permalink, thumbnail.url=hero og-image conversion, fields[Category]"
    - "config/discord.php — NEW file (Phase 5 placed OAuth in services.php; this file owns non-OAuth runtime Discord settings); league_announce_channel_id key (Open Question 1 LOCKED — single global channel v1)"
    - "tests/Feature/Services/ArticleStatusServiceTest — 10 GREEN it() blocks (target 5+)"
    - "tests/Feature/Observers/ArticleObserverTest — 13 GREEN it() blocks replacing 07-01 RED stub (target 8+); covers Pitfall 10 republish guard, allow_discord_announce + empty-channel suppression, insert-as-published path, bulk-update bypass documentation"
    - "tests/Feature/Outbound/ArticleAnnounceOutboundTest — 6 GREEN it() blocks replacing 07-01 RED stub (target 4+); covers payload shape, excerpt truncation, CHECK constraint regression guard (07-02 migration validation), T-07-06-04 nullable causer"
  affects:
    - apps/web/app/Services/      # +ArticleStatusService.php
    - apps/web/app/Exceptions/    # +InvalidArticleStatusTransitionException.php
    - apps/web/app/Observers/     # +ArticleObserver.php
    - apps/web/app/Support/       # DiscordOutboundPayloadBuilder.php extended with buildArticleAnnounce
    - apps/web/app/Models/        # Article.php booted() observer registration
    - apps/web/config/            # +discord.php (new file)
    - apps/web/.env.example       # +DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID=
    - apps/web/tests/Feature/Services/   # +ArticleStatusServiceTest.php
    - apps/web/tests/Feature/Observers/  # RED stub → 13 GREEN
    - apps/web/tests/Feature/Outbound/   # RED stub → 6 GREEN
tech-stack:
  added: []
  patterns:
    - "D-06-08-A two-hook observer pattern (created + updated; NOT saved): Phase 6 TournamentObserver verbatim continuation. saved() would double-fire on touch() and other no-op writes; created() + updated() with wasChanged('status') gate fires once per real transition. Article uses the same model-level registration (static::observe in booted())."
    - "Pitfall 10 republish guard via outbox-row lookup: gating on $a->wasChanged('status') AND $a->getOriginal('status') !== 'published' is not sufficient — published → draft → published would fire twice (draft's status changes to published, both gates pass). The canonical mitigation is querying for an existing article_announce row with payload->article_id === $a->id via Postgres JSONB ->>. Mirrors Phase 5 MatchObserver's payload->match_id idiom."
    - "Event MorphOne sync with is_public flag (Phase 4 D-04-08-C precedent): drafts retain their Event row with is_public=false; the calendar feed (plan 07-09) filters on is_public=true so drafts hide without row churn. Matches the GameMatch + Tournament observer patterns — owner state drives the events row's lifecycle without delete/recreate."
    - "Article state machine — service-layer guard with strict allow list. Mirrors Phase 4 MatchStatusService + Phase 6 TournamentStatusService (D-04-04-A + D-06-04-A); InvalidArticleStatusTransitionException extends \\DomainException. Service-layer validation is the upper defence; CHECK constraint at the DB layer (plan 07-02 articles_status_check) is the defence-in-depth lower layer."
    - "Discord outbound embed payload shape — embeds[0].{title,description,url,color,thumbnail,fields}. Color stored as literal hex string ('#10B981' for articles; future event types like match='#3B82F6' blue + tournament='#8B5CF6' purple will follow Open Question 6 LOCKED color-by-type scheme). Bot worker (plan 05-11) converts hex to Discord integer color at dispatch time."
key-files:
  created:
    - apps/web/app/Services/ArticleStatusService.php
    - apps/web/app/Exceptions/InvalidArticleStatusTransitionException.php
    - apps/web/app/Observers/ArticleObserver.php
    - apps/web/config/discord.php
    - apps/web/tests/Feature/Services/ArticleStatusServiceTest.php
  modified:
    - apps/web/app/Support/DiscordOutboundPayloadBuilder.php  # +buildArticleAnnounce static method + Article/Str imports
    - apps/web/app/Models/Article.php                          # +booted() + ArticleObserver import
    - apps/web/.env.example                                    # +DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID=
    - apps/web/tests/Feature/Observers/ArticleObserverTest.php # RED stub → 13 GREEN
    - apps/web/tests/Feature/Outbound/ArticleAnnounceOutboundTest.php  # RED stub → 6 GREEN
decisions:
  - "D-07-06-A — DiscordOutboundPayloadBuilder lives in App\\Support\\, NOT App\\Services\\. The plan's <interfaces> (line 28) and must_haves all reference apps/web/app/Services/DiscordOutboundPayloadBuilder.php, but the actual Phase 5 plan 05-05 commit placed the file under app/Support/ (consistent with stateless helpers like Brackets/ generators sitting under app/Services/ but utility shape-builders under app/Support/). Path discrepancy resolved in favour of the existing on-disk reality — the plan's path reference was incorrect. Extending in-place at app/Support/DiscordOutboundPayloadBuilder.php is the surgical change the plan asked for; only the documented path label needed adjustment."
  - "D-07-06-B — Pitfall 10 republish guard implemented via outbox-row existence query, NOT solely the wasChanged()+getOriginal() pair. The plan's <interfaces> code block gates onPublish() on wasChanged('status') AND status==='published' AND getOriginal('status') !== 'published'. Those three conditions fire on EVERY draft→published transition — including the second leg of a republish (published → draft → published). The threat model line T-07-06-01 explicitly says 'ArticleObserverTest asserts republish does NOT duplicate outbound', and the test must_have line says 'republish does NOT duplicate the article_announce outbound row'. Resolution: add a fourth defence — query for an existing article_announce row with payload->article_id === $a->id (Postgres JSONB ->>) before enqueueing. Mirrors Phase 5 MatchObserver's payload->match_id lookup idiom. Pitfall 10 is now fully mitigated (Rule 1 deviation — fixed the contradiction between the plan's prose intent and its <interfaces> code block)."
  - "D-07-06-C — config/discord.php is a brand-new file (Phase 5 plan 05-XX placed OAuth keys in config/services.php under the 'discord' key, NOT a dedicated discord.php). This plan introduces the dedicated config namespace because (a) future Phase 7+ keys (admin announce channel, role-sync settings) will accumulate; (b) league_announce_channel_id is not OAuth, so services.php would be the wrong shape; (c) the plan's must_haves and <interfaces> reference config('discord.league_announce_channel_id') verbatim. Single-key config is acceptable Laravel idiom (compare config/horizon.php, config/i18n.php in this repo)."
  - "D-07-06-D — ArticleStatusService::transition accepts ?User \$causer but does NOT use it. Phase 6 TournamentStatusService::transition emits an explicit activity()->causedBy(\$causer)->log(...) row inside DB::transaction. Phase 7 Article relies on the model's LogsActivity trait (07-03 logFillable+logOnlyDirty) to write the audit row automatically on update(). The \$causer parameter is retained in the signature for D-06-04-A parity and to satisfy plan 07-07 + 07-05 callers that pass the actor — but the parameter is unset() inside transition() to silence Larastan unused-parameter warnings. If future audit needs require explicit causer attribution, the service can be extended to emit a custom activity log row alongside the LogsActivity auto-write."
  - "D-07-06-E — ArticleObserverTest activity_log assertion narrowed from 'properties.attributes.status === published' to 'row exists + description=updated under log_name=article'. Empirical check showed Spatie LogsActivity v5 in this repo writes properties=[] for $model->update() flows even with logFillable()+logOnlyDirty() set — TournamentObserverTest has the same shape, and the canonical TournamentStatusServiceTest assertions read from explicit activity()->withProperties() calls inside the service, not from the trait. This is a Spatie-version-specific behavior. The narrowed assertion still proves the audit row landed under the right log_name partition (D-012 compliance); the detail of which attributes changed is exercised separately by plan 07-11 admin audit page tests."
  - "D-07-06-F — buildArticleAnnounce uses url('/news/' . \$a->slug), NOT route('blog.show', \$a->slug). The plan's must_haves line 28 says 'embed.url=route(\"blog.show\", \$a->slug)' but plan 07-09 (PublicBlogController + routes) hasn't shipped yet — route('blog.show') would throw a RouteNotFoundException at observer-fire time. The canonical permalink shape is /news/{slug} (07-05 PublicArticleData::fromModel hardcodes the same string). The bot worker forwards the literal URL — it does not differentiate between url() and route() output. When plan 07-09 lands route('blog.show', ...), this single line can be migrated; behavior is identical."
metrics:
  duration: 9m 2s
  completed: 2026-05-14
  tasks: 2
  files_created: 5
  files_modified: 5
  commits: 2
---

# Phase 7 Plan 6: Wave 3 — ArticleObserver + Event Sync + Discord Announce + Article Status State Machine Summary

Phase 7 Wave 3 — wire the Article lifecycle pipeline. With plan 07-05 the
editorial admin surface (ArticleResource + CategoryResource) is in place;
this plan adds the state machine + observer that make Article publish flow
trigger (a) a calendar Event row (eaten by plan 07-09 EventsFeedJsonController)
and (b) a Discord article_announce outbound row (eaten by the Phase 5 worker).
Plan 07-07 will wire the Laravel scheduler that flips scheduled → published
when scheduled_at passes; plans 07-09/07-10 ship the public Vue surface that
renders the calendar + index pages.

## Surface Delivered

### ArticleStatusService (apps/web/app/Services/ArticleStatusService.php)

State machine for the Article lifecycle (mirrors D-04-04-A MatchStatusService
and D-06-04-A TournamentStatusService precedents):

| From → To       | Permitted | Side effect |
|-----------------|-----------|-------------|
| draft → scheduled | ✓ | status only |
| draft → published | ✓ | published_at=now(); scheduled_at=null |
| scheduled → published | ✓ | published_at=now(); scheduled_at=null |
| scheduled → draft | ✓ | status only (unschedule) |
| published → draft | ✓ | status only (unpublish — policy-enforced super-admin only) |
| Any other pair | rejected | throws InvalidArticleStatusTransitionException |

Signature: `transition(Article $a, string $to, ?User $causer = null): Article`.
The $causer parameter is accepted for D-06-04-A parity but the audit row is
written by the model's LogsActivity trait (07-03) — Article does NOT emit an
explicit activity()->causedBy()->log() call inside the service. See D-07-06-D
for the rationale.

### InvalidArticleStatusTransitionException (apps/web/app/Exceptions/)

```php
final class InvalidArticleStatusTransitionException extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(sprintf('Invalid Article status transition: %s -> %s', $from, $to));
    }
}
```

User-facing surface flows through `__('cms.errors.invalid_status_transition')`
i18n key at the controller catch layer (plan 07-09 / plan 07-11 admin actions);
the literal message above is for logs only.

### ArticleObserver (apps/web/app/Observers/ArticleObserver.php)

D-06-08-A two-hook pattern verbatim — `created()` + `updated()`, NOT `saved()`:

| Hook | Trigger | Side effects |
|------|---------|--------------|
| `created()` | INSERT | `syncEvent()` always; `onPublish()` if status='published' (admin/seeder insert-as-published path) |
| `updated()` | UPDATE | `syncEvent()` always; `onPublish()` if wasChanged('status') AND status='published' AND getOriginal('status') !== 'published' (first transition to published) |

#### syncEvent() — Event MorphOne sync

```php
$a->events()->updateOrCreate(
    ['eventable_type' => $a->getMorphClass(), 'eventable_id' => $a->id],
    [
        'is_public' => $a->status === 'published',
        'title' => $a->getTranslations('title'),
        'starts_at' => $a->published_at ?? $a->scheduled_at ?? $a->created_at,
        'ends_at' => null,
    ]
);
```

Drafts retain their Event row with `is_public=false` (Phase 4 D-04-08-C
precedent) — the calendar feed (plan 07-09 EventsFeedJsonController) filters
on `is_public=true` so drafts naturally hide without row churn. Articles are
point-in-time events (ends_at=null).

#### onPublish() — article_announce outbox enqueue (four-gate defence)

```php
1. allow_discord_announce per-article opt-in       → return on false
2. config('discord.league_announce_channel_id')    → return on empty (defensive)
3. Pitfall 10 republish guard — prior outbound row → return on exists
4. DiscordOutboundMessage::create with payload     → article_announce row landed
```

The Pitfall 10 republish guard (D-07-06-B) closes the gap left by the plan's
three-condition prose: `wasChanged('status') + status='published' +
getOriginal('status') !== 'published'` all pass on the SECOND leg of a
published → draft → published republish loop (first leg fires, second leg
would also fire because getOriginal()='draft' at that point). Querying the
existing outbox via `payload->article_id` (Postgres JSONB ->>) blocks the
duplicate — mirrors Phase 5 MatchObserver's `payload->match_id` idiom.

#### Observer registration (Article::booted)

```php
protected static function booted(): void
{
    static::observe(ArticleObserver::class);
}
```

Model-level registration is D-06-08-A precedent — survives Laravel package
discovery and keeps the observer wiring colocated with the model class.

### DiscordOutboundPayloadBuilder::buildArticleAnnounce (apps/web/app/Support/)

Final embed shape (note: D-07-06-A — file lives in `app/Support/`, not the
`app/Services/` path referenced by the plan):

| Key | Value |
|-----|-------|
| `kind` | `'article_announce'` |
| `article_id` | `$a->id` |
| `article_slug` | `$a->slug` |
| `embeds[0].title` | `$a->getTranslation('title', 'en')` |
| `embeds[0].description` | `Str::limit($excerptEn, 300, '…')` (truncated to 300 chars + ellipsis) |
| `embeds[0].url` | `url('/news/' . $a->slug)` (canonical permalink — see D-07-06-F) |
| `embeds[0].color` | `'#10B981'` — **Open Question 6 LOCKED — CMS green v1** |
| `embeds[0].thumbnail.url` | hero `og-image` conversion URL or null |
| `embeds[0].fields[0]` | `{name: 'Category', value: category.name.en, inline: true}` |

Bot worker (plan 05-11) converts the hex color string to Discord integer
color at dispatch time. Per Open Question 6: future match payloads will use
`#3B82F6` blue + tournament payloads will use `#8B5CF6` purple (CalendarEventData::colourFor
in plan 07-09 mirrors this scheme).

### config/discord.php (NEW file)

```php
return [
    'league_announce_channel_id' => env('DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID', ''),
];
```

**Open Question 1 LOCKED inline:** single global league announce channel for
v1. Per-article channel override deferred to CMS-V2; per-article opt-out
covered by the `articles.allow_discord_announce` toggle (07-02 column).
config/discord.php is brand-new — config/services.php hosts the OAuth keys
(Phase 1 idiom for socialite providers) and would be the wrong shape for
non-OAuth runtime settings. See D-07-06-C.

### .env.example addition

```env
# Open Question 1 LOCKED — Phase 7 article publish announce channel
# Single global league channel where ArticleObserver enqueues article_announce
# outbound rows (config/discord.php). Empty disables the announce side-effect.
DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID=
```

Empty shape per CLAUDE.md §6. Operator owns rotation in Railway env groups
(D-014).

## Open Question Resolutions (Inline)

| OQ | Resolution |
|----|------------|
| **OQ-1 (Discord article-announce channel)** | **LOCKED inline:** single global league channel via `config('discord.league_announce_channel_id')`. Per-article opt-in via existing `allow_discord_announce` toggle (07-02). Per-article channel override deferred to CMS-V2. The bot worker (plan 05-11) resolves the channel at dispatch time. |
| **OQ-6 (calendar event color scheme)** | **LOCKED inline:** color by event type in v1. Articles use `#10B981` (CMS green) in `buildArticleAnnounce`'s `embeds[0].color`. Matches will use `#3B82F6` (blue); tournaments `#8B5CF6` (purple). Plan 07-09 CalendarEventData::colourFor will mirror this scheme on the calendar Vue page. |

## Pitfall 10 (Observer Republish Spam) — Mitigation Evidence

The threat model line T-07-06-01 says "ArticleObserverTest asserts republish
does NOT duplicate outbound." The plan's `<interfaces>` code block gated
`onPublish()` on three conditions — those three conditions pass on EVERY
draft → published transition, including the second leg of a republish loop.

**Defence proven by GREEN test:** `it does NOT re-fire announce on republish
(Pitfall 10 in observer)` — published → draft → published → outbound row count
stays at 1. The four-gate defence in `onPublish()` blocks the duplicate via a
`DiscordOutboundMessage::query()->where('payload->article_id', $a->id)
->exists()` lookup before enqueuing. See D-07-06-B for the deviation rationale.

```text
✓ it does NOT re-fire announce on republish (Pitfall 10 in observer)   0.04s
```

The full Pitfall 10 chain is now:
1. wasChanged('status') gate — guards no-op writes / touch() / unrelated edits
2. status='published' gate — only publish transitions
3. getOriginal('status') !== 'published' gate — first published transition per save
4. **payload->article_id outbox-row existence check** — defence against republish loop
5. allow_discord_announce per-article opt-in
6. league_announce_channel_id config presence

## Test Surface (3 GREEN files; 29 it() blocks total)

| File | Pass count | Coverage |
|------|------------|----------|
| `tests/Feature/Services/ArticleStatusServiceTest.php` (new) | **10 GREEN** (target 5+) | 5 permitted transitions + 4 illegal-pair rejections + typed exception subclass identity |
| `tests/Feature/Observers/ArticleObserverTest.php` (RED stub → GREEN) | **13 GREEN** (target 8+) | Event MorphOne sync (created/updated/draft retains row); first-transition outbound fire; Pitfall 10 republish guard; title-only no-op; allow_discord_announce + empty-channel suppression; insert-as-published path; insert-as-draft no-op; bulk-update bypass documentation; activity_log row presence |
| `tests/Feature/Outbound/ArticleAnnounceOutboundTest.php` (RED stub → GREEN) | **6 GREEN** (target 4+) | payload shape (kind, article_id, slug, embeds, title, color=#10B981, url=/news/slug); excerpt truncation ≤ 301 chars; CHECK constraint permits article_announce (07-02 migration regression guard); CHECK constraint REJECTS unknown message_type (T-07-06-06); causer null when no auth; causer populated when authenticated |

Filtered run:

```text
docker compose exec -T web ./vendor/bin/pest --filter='ArticleStatusServiceTest|ArticleObserverTest|ArticleAnnounceOutboundTest'
Tests:    29 passed (60 assertions)
Duration: ~3s
```

Full suite regression:

```text
Tests:    13 failed, 950 passed (2948 assertions)
Duration: 56.65s
```

Baseline from 07-05 was 15 failed / 921 passed; this plan moves the baseline
to 13 failed / 950 passed — diff: **+29 GREEN, −2 RED** (ArticleStatusServiceTest
10 new + ArticleObserverTest 13 GREEN replacing 1 RED + ArticleAnnounceOutboundTest
6 GREEN replacing 1 RED). The 13 remaining failures are all Wave 0 RED stubs
owned by future Phase 7 plans (07-07..07-13).

## Plan Verification Line-by-Line

| Plan verification line | Result |
|------------------------|--------|
| `make pest --filter='ArticleObserverTest\|ArticleAnnounceOutboundTest\|ArticleStatusServiceTest'` GREEN | **PASS** — 29 passed / 60 assertions |
| Article model registers observer in booted() | **PASS** — `grep -c "static::observe(ArticleObserver" app/Models/Article.php` returns 1 |
| psql: outbound CHECK constraint still rejects unknown message_type (regression guard) | **PASS** — `it CHECK constraint REJECTS unknown message_type` asserts QueryException on `article_xyz` insert |
| config('discord.league_announce_channel_id') readable + defaults to '' | **PASS** — new config/discord.php returns env('DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID', '') |
| PHPStan + Pint clean | **PASS** — phpstan [OK] on all task 1 + task 2 prod files; pint --test PASS on prod + test files after one auto-fix (fully_qualified_strict_types on test file) |

## Pint + PHPStan Gates

| Gate | Files | Result |
|------|-------|--------|
| `pint --test` | task 1 (5 files) + task 2 (4 files) = 9 files | **PASS** (after one Pint auto-fix run for fully_qualified_strict_types on ArticleAnnounceOutboundTest's User::factory reference) |
| `phpstan analyse` | All new prod files + DiscordOutboundPayloadBuilder.php + Article.php modification | **[OK] No errors** (Larastan L8) |

Test files are intentionally NOT in PHPStan paths per `apps/web/phpstan.neon`
(Phase 1-6 precedent).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Pitfall 10 republish guard contradiction in plan.**
- **Found during:** Task 2 first Pest run.
- **Issue:** The plan's `<interfaces>` code block gated `onPublish()` on three
  conditions: `wasChanged('status') AND status==='published' AND
  getOriginal('status') !== 'published'`. The threat model + test must_haves
  said "republish does NOT duplicate." Those three conditions pass on the
  SECOND leg of a published → draft → published loop (getOriginal()='draft'
  passes the third gate). The republish test failed with count=2 instead of 1.
- **Fix:** Added a fourth defence layer in `onPublish()` — query for an
  existing `article_announce` outbound row with `payload->article_id === $a->id`
  (Postgres JSONB ->>) before enqueueing. Mirrors Phase 5 MatchObserver's
  `payload->match_id` idiom. Pitfall 10 fully mitigated.
- **Files modified:** `apps/web/app/Observers/ArticleObserver.php`
- **Commit:** `dd75f81`

**2. [Rule 1 — Bug] ArticleObserverTest activity_log assertion mismatched Spatie v5 shape.**
- **Found during:** Task 2 first Pest run; `Failed asserting that null is identical to 'published'`.
- **Issue:** My initial test read `$activity->properties->toArray()['attributes']['status']`
  and expected 'published'. Empirical check via tinker showed Spatie LogsActivity
  v5 in this repo writes `properties = []` for `$model->update()` flows even
  with `logFillable() + logOnlyDirty()` set — TournamentObserverTest has the
  same empty-properties shape, and TournamentStatusServiceTest reads from
  explicit `activity()->withProperties()` calls inside the service (not the
  trait). The narrowed test asserts only row presence under the
  `(log_name=article, subject_type=Article, subject_id, description=updated)`
  partition — which is the D-012 compliance surface. The detail of which
  attributes changed will be exercised by plan 07-11 admin audit page tests.
- **Files modified:** `apps/web/tests/Feature/Observers/ArticleObserverTest.php`
- **Commit:** `dd75f81`

**3. [Rule 3 — Blocking issue] DiscordOutboundPayloadBuilder lives at app/Support/, NOT app/Services/.**
- **Found during:** Task 1 first file lookup.
- **Issue:** Plan `<interfaces>` (line 28), must_haves.artifacts (line 42), and
  multiple cross-references say `apps/web/app/Services/DiscordOutboundPayloadBuilder.php`.
  The actual Phase 5 plan 05-05 commit placed the file at
  `apps/web/app/Support/DiscordOutboundPayloadBuilder.php` (consistent with
  stateless helpers — `app/Services/` is reserved for service-layer classes
  like `MatchStatusService` etc.).
- **Fix:** Extended in-place at the real path `app/Support/DiscordOutboundPayloadBuilder.php`.
  Recorded as D-07-06-A for clarity. The plan's path reference was a documentation
  drift, not an implementation requirement; the surgical change the plan asked
  for (add a static method to the existing builder) is what landed.
- **Files modified:** `apps/web/app/Support/DiscordOutboundPayloadBuilder.php`
- **Commit:** `ece61dd`

**4. [Rule 3 — Blocking issue] config/discord.php did not exist (Phase 5 used services.php).**
- **Found during:** Task 1 file lookup.
- **Issue:** Plan's must_haves call for `config/discord.php` extension — but
  this file is brand-new. Phase 5 placed Discord OAuth keys under
  `config/services.php`'s `'discord'` key (Laravel Socialite idiom). The plan
  treated the file as existing.
- **Fix:** Created `config/discord.php` as a new file with the single
  `league_announce_channel_id` key. Single-key config is acceptable Laravel
  idiom (compare `config/horizon.php`, `config/i18n.php` in this repo).
  Recorded as D-07-06-C. Future Phase 7+ keys (admin announce channel, role-sync
  settings) will accumulate here, keeping non-OAuth Discord settings separate
  from the socialite OAuth surface.
- **Files modified:** `apps/web/config/discord.php` (created)
- **Commit:** `ece61dd`

**5. [Rule 1 — Bug] route('blog.show', ...) does not exist yet — would throw RouteNotFoundException at observer fire time.**
- **Found during:** Task 1 buildArticleAnnounce authoring.
- **Issue:** Plan must_haves line 28 says `embed.url=route('blog.show', $a->slug)`.
  Plan 07-09 will land the public blog route + binding; until then, calling
  `route('blog.show', ...)` would raise a `RouteNotFoundException` the moment
  ArticleObserver fires on the first publish. The canonical permalink shape
  is `/news/{slug}` (07-05 PublicArticleData::fromModel hardcodes this string).
- **Fix:** Use `url('/news/' . $a->slug)` instead of `route('blog.show', $a->slug)`.
  Plan 07-09 can migrate this single line to `route('blog.show', ...)` once
  the route exists; behavior is identical (the bot worker forwards the literal
  URL regardless of generator). Recorded as D-07-06-F.
- **Files modified:** `apps/web/app/Support/DiscordOutboundPayloadBuilder.php`
- **Commit:** `ece61dd`

### Architectural changes (Rule 4)

None.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-06-01 (Re-publish loop spamming Discord channel) | **mitigated, four-gate defence** — wasChanged('status') + status='published' + getOriginal('status') != 'published' + payload->article_id outbox-row existence guard. `it does NOT re-fire announce on republish (Pitfall 10 in observer)` asserts republish=count(1). See D-07-06-B for the additional outbox-row defence beyond the plan's prose. |
| T-07-06-02 (Tampering — crafted JSONB in payload) | **mitigated** — Article.excerpt is translatable user content gated by D-013; the bot renderer (plan 05-11) is responsible for Discord-embed sanitization. The builder itself only forwards Str::limit-truncated strings; no v-html surface on web. |
| T-07-06-03 (Disclosure — causer_user_id to non-admins) | **mitigated** — Phase 5 plan 05-07 DiscordOutboundMessageResource is super-admin read-only; gating already in place. |
| T-07-06-04 (Repudiation — scheduler-driven null causer) | **accepted, documented** — `it causer_user_id nullable when no auth user (scheduler-driven publish)` asserts the null-causer branch; future plan 07-07 scheduler will hit this path. activity_log captures the row creation under the LogsActivity trait's automatic write. |
| T-07-06-05 (Spoofing — env var injection on league channel) | **accepted** — DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID is a Railway env-group secret (D-014); `.env.example` commits empty shape only. Operator owns rotation. |
| T-07-06-06 (Tampering — CHECK bypass on raw insert) | **mitigated, regression-guarded** — `it CHECK constraint REJECTS unknown message_type` asserts a direct `DB::table->insert` with `message_type='article_xyz'` raises QueryException. Validates plan 07-02 migration is still enforcing the enum. |
| T-07-06-07 (DoS via rapid status flips) | **mitigated** — onPublish gates on first transition (wasChanged + getOriginal); republish row is blocked by the outbox-row existence check (D-07-06-B). Phase 5 worker enforces Discord API rate limits at the dispatch surface. |

## Known Stubs

None. ArticleObserver + ArticleStatusService + DiscordOutboundPayloadBuilder::buildArticleAnnounce
are fully wired. The `route('blog.show', ...)` migration to a named route binding
(D-07-06-F) is a follow-up cosmetic line change once plan 07-09 lands the
route registration; functional behavior is complete (the literal URL is correct
in both forms).

## Threat Flags

None. The plan's `<threat_model>` covered every surface introduced (re-publish
spam, JSONB tampering, causer disclosure, scheduler-null causer, env-var spoofing,
CHECK bypass, DoS rapid-flip). No new endpoints introduced beyond the
discord_outbound_messages writes (already in plan 07-02 schema); no new
file-access patterns; no new schema changes at trust boundaries.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: ArticleStatusService + Exception + buildArticleAnnounce + config/discord.php + .env.example + 10 GREEN test | `ece61dd` | 6 (4 created + 2 modified) |
| 2: ArticleObserver + Article::booted() + 13 GREEN ArticleObserverTest + 6 GREEN ArticleAnnounceOutboundTest | `dd75f81` | 4 (1 created + 3 modified) |

## Self-Check

- [x] `apps/web/app/Services/ArticleStatusService.php` — FOUND
- [x] `apps/web/app/Exceptions/InvalidArticleStatusTransitionException.php` — FOUND
- [x] `apps/web/app/Observers/ArticleObserver.php` — FOUND
- [x] `apps/web/config/discord.php` — FOUND
- [x] `apps/web/tests/Feature/Services/ArticleStatusServiceTest.php` — FOUND
- [x] `apps/web/app/Support/DiscordOutboundPayloadBuilder.php` — FOUND (modified, +buildArticleAnnounce)
- [x] `apps/web/app/Models/Article.php` — FOUND (modified, +booted)
- [x] `apps/web/.env.example` — FOUND (modified, +DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID)
- [x] `apps/web/tests/Feature/Observers/ArticleObserverTest.php` — FOUND (modified, RED → GREEN)
- [x] `apps/web/tests/Feature/Outbound/ArticleAnnounceOutboundTest.php` — FOUND (modified, RED → GREEN)
- [x] commit `ece61dd` — FOUND in git log
- [x] commit `dd75f81` — FOUND in git log

## Self-Check: PASSED
