---
phase: 04-matches-manual
plan: 08
subsystem: observers-event-sync
tags: [phase-4, wave-4, observer, polymorphic, event-sync, pattern-8, pitfall-12, sc-5]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-4-model-layer
  provides:
    - match-observer-saved-deleted-listeners
    - polymorphic-event-sync-invariant
    - sc-5-public-match-auto-creates-event
  affects:
    - apps/web/app/Observers/ (NEW directory)
    - apps/web/app/Models/GameMatch.php (Rule 2 amendment — booted() + import)
    - apps/web/tests/Feature/Observers/MatchEventSyncTest.php (Wave 0 → GREEN, 8 tests)
    - apps/web/tests/Feature/Models/EventModelTest.php (Rule 1 ripple fix)
    - apps/web/tests/Feature/Models/MatchModelTest.php (Rule 1 ripple fix)
    - apps/web/tests/Unit/Data/EventDataTest.php (Rule 1 ripple fix)
tech_stack:
  added: []
  patterns:
    - eloquent-model-observer-static-observe
    - eloquent-booted-registration
    - updateOrCreate-idempotent-upsert
    - polymorphic-eventable-type-fqn-string
    - observer-driven-vs-manual-factory-segregation
key_files:
  created:
    - apps/web/app/Observers/MatchObserver.php
  modified:
    - apps/web/app/Models/GameMatch.php
    - apps/web/tests/Feature/Observers/MatchEventSyncTest.php
    - apps/web/tests/Feature/Models/EventModelTest.php
    - apps/web/tests/Feature/Models/MatchModelTest.php
    - apps/web/tests/Unit/Data/EventDataTest.php
  deleted: []
decisions:
  - id: D-04-08-A
    decision: |
      **No `App\Models\Match as MatchModel` alias** — D-04-03-A LOCKED + D-04-07-C
      canonical Phase 4 idiom. Pattern 8 in 04-RESEARCH.md predates the GameMatch
      rename and aliases `App\Models\Match as MatchModel` (Pitfall 5). Phase 4
      implementations use `GameMatch` directly: MatchObserver uses
      `use App\Models\GameMatch;` and references `GameMatch::class` as the
      polymorphic eventable_type, matching MatchSignupService / MatchStatusService /
      MatchSlotMaterialiserService / Filament resources (D-04-06-D family).

  - id: D-04-08-B
    decision: |
      **Model-level booted() registration ONLY — no AppServiceProvider fallback.**

      The plan body marked the AppServiceProvider registration as conditional
      ("only if model-level booted() doesn't fire under test"). The 8-test
      MatchEventSyncTest suite proves the booted() registration fires reliably
      on every save (creation + update + delete). No need for the fallback —
      keeping observer registration at the model is the canonical Phase 3+
      pattern (GameMatchTypeRoleLimit::booted() uses the same idiom for the
      cross-game guard).

      AppServiceProvider remains untouched.

  - id: D-04-08-C
    decision: |
      **Observer-driven Event row coexists with manual Event::factory() — segregate
      by `is_public=false` on the underlying GameMatch.**

      Once MatchObserver is registered, `GameMatch::factory()->create()` (default
      is_public=true) fires `saved()` and auto-creates an Event row. Any
      subsequent manual `Event::factory()->create(['eventable_id' => $match->id])`
      on the same owner violates the `events_one_per_owner` UNIQUE constraint.

      Three pre-existing test files broke on this ripple effect; the auto-fix
      pattern is: for tests that need to create a manual Event for DTO/model
      assertions, set `is_public=false` on the GameMatch so the observer skips
      that match. For tests that exercise the observer-driven path, look up
      the auto-created Event via `Event::where(eventable_type, eventable_id)`
      rather than calling `Event::factory()`.

      The two patterns are mutually exclusive — pick one per test scenario.

metrics:
  duration_minutes: 11
  completed: 2026-05-13
---

# Phase 4 Plan 08: MatchObserver Polymorphic Event Sync Summary

**One-liner:** MatchObserver (Pattern 8 verbatim) registered on `GameMatch::booted()` via `static::observe(MatchObserver::class)` keeps the polymorphic `events` table coherent with `matches.is_public` and `matches.status` — `saved()` upserts via `Event::updateOrCreate` (idempotent against `events_one_per_owner` UNIQUE) when both conditions hold, deletes otherwise; `deleted()` cascades hard-delete (no FK on polymorphic eventable); 8 GREEN MatchEventSyncTest tests / 17 assertions cover create/draft/private/cancel/title-edit/scheduled_at-edit/delete/re-flip transitions per Pitfall 12 ($match->update() never Model::query()->update()); 3 ripple-effect test files (EventModelTest, EventDataTest, MatchModelTest) auto-fixed (Rule 1) by setting is_public=false on GameMatch when manual Event::factory() is intended, or looking up the observer-created Event when round-trip is intended.

## Performance

- **Duration:** ~11 min
- **Started:** 2026-05-13T14:40:00Z (approx)
- **Completed:** 2026-05-13T14:51:30Z
- **Tasks:** 2 / 2
- **Files modified:** 6 (1 created + 5 modified)

## Accomplishments

1. **MatchObserver class + GameMatch::booted() registration land Pattern 8 verbatim.** The `saved()` listener implements the SC-5 second-half invariant: any public, non-cancelled GameMatch save upserts a polymorphic Event row keyed by (eventable_type=`App\Models\GameMatch`, eventable_id=match.id). Any private OR cancelled save deletes the corresponding Event row. `deleted()` cascades the hard delete (events table has no FK on polymorphic eventable_id — observer is the only cleanup path). `Event::updateOrCreate(unique_keys, payload)` is idempotent against the `events_one_per_owner` UNIQUE constraint (plan 04-02 migration), so the flip-off → flip-on cycle never produces duplicate rows.

2. **8 GREEN MatchEventSyncTest tests cover the full transition matrix.** Exceeds plan's 5+ minimum; 17 assertions. Each transition uses `$match->update()` or `$match->save()` (never `Model::query()->update()` per Pitfall 12 — bulk updates skip model events). The test surface enumerates: (1) public-on-create creates Event; (2) draft-private-on-create creates NO Event; (3) public→private flip deletes Event; (4) status=cancelled deletes Event; (5) title edit propagates to Event.title (translatable JSONB written through via getTranslations); (6) scheduled_at edit propagates to Event.starts_at; (7) hard-delete cascades; (8) private→public re-creates Event (updateOrCreate idempotent).

3. **3 pre-existing test files auto-fixed (Rule 1) for observer ripple effect.** Before plan 04-08, `GameMatch::factory()->create()` (default is_public=true) did NOT create an Event; tests freely called `Event::factory()->create([eventable_id => $match->id])` to set up polymorphic fixtures. After plan 04-08, the observer fires and auto-creates Event #1; the manual factory call then violates events_one_per_owner. Auto-fix pattern (D-04-08-C): set `is_public=false` on GameMatch when manual Event creation is intended, OR look up the observer-created Event when the test exercises the round-trip.

## Task Commits

1. **Task 1: MatchObserver class + GameMatch::booted() registration** — `c04e16e` (feat) — 2 files (1 new); 87 lines added; PHPStan + Pint clean.
2. **Task 2: MatchEventSyncTest GREEN + 3 observer-aware ripple fixes** — `7415414` (test) — 4 files; 195 lines added / 35 removed; Pest full suite 404 passed / 7 incomplete (down from 8).

## Files Created/Modified

### Created (1)

| File | LOC | Notes |
|---|---|---|
| `apps/web/app/Observers/MatchObserver.php` | 71 | saved() upserts via updateOrCreate; deleted() cascades; uses GameMatch::class as polymorphic eventable_type FQN |

### Modified (5)

| File | Change |
|---|---|
| `apps/web/app/Models/GameMatch.php` | +13 lines — `use App\Observers\MatchObserver;` + `protected static function booted(): void { static::observe(MatchObserver::class); }` |
| `apps/web/tests/Feature/Observers/MatchEventSyncTest.php` | Wave 0 stub (placeholder) → 8 GREEN it() blocks / 17 assertions |
| `apps/web/tests/Feature/Models/EventModelTest.php` | Rule 1 ripple fix — 5 of 7 tests rewritten to use is_public=false on GameMatch so manual Event::factory() does not collide with observer write; 2 tests rewritten to look up observer-created Event for round-trip |
| `apps/web/tests/Feature/Models/MatchModelTest.php` | Rule 1 ripple fix — 1 test rewritten (replace Event::factory() with lookup of observer-created Event for the public match's MorphOne relation) |
| `apps/web/tests/Unit/Data/EventDataTest.php` | Rule 1 ripple fix — 5 tests rewritten to use is_public=false on underlying GameMatch when manual Event::factory() is intended |

## MatchObserver — Pattern 8 verbatim shape

```php
public function saved(GameMatch $match): void
{
    $shouldHaveEvent = $match->is_public && $match->status !== 'cancelled';

    if ($shouldHaveEvent) {
        Event::updateOrCreate(
            ['eventable_type' => GameMatch::class, 'eventable_id' => $match->id],
            [
                'starts_at' => $match->scheduled_at,
                'ends_at' => null,
                'title' => $match->getTranslations('title'),
                'is_public' => $match->is_public,
            ],
        );

        return;
    }

    Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->delete();
}

public function deleted(GameMatch $match): void
{
    Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->delete();
}
```

## GameMatch::booted() amendment

```php
protected static function booted(): void
{
    static::observe(MatchObserver::class);
}
```

`static::observe()` is idempotent — Eloquent dedupes by class name, so repeat boots are safe.

## Verification

| Gate | Command | Result |
|---|---|---|
| Plan filter | `docker compose exec web ./vendor/bin/pest --filter=MatchEventSyncTest --no-coverage` | **8 passed, 17 assertions** |
| Full Pest suite | `make pest` | **404 passed, 1042 assertions, 7 incomplete** (baseline 04-07: 396 passed / 8 incomplete → +8 / −1 ✓) |
| PHPStan L8 (full) | `make phpstan` | **No errors** |
| Pint full | `docker compose exec web ./vendor/bin/pint --test` | **clean, 278 files** |
| Observer file exists | `ls apps/web/app/Observers/MatchObserver.php` | **file present** |
| booted() observe-call present | `grep -c 'static::observe(MatchObserver' apps/web/app/Models/GameMatch.php` | **1** |
| AppServiceProvider untouched | `git diff master -- apps/web/app/Providers/AppServiceProvider.php` | **no changes** (D-04-08-B) |
| `placeholder` removed | `grep -c 'placeholder' apps/web/tests/Feature/Observers/MatchEventSyncTest.php` | **0** ✓ |
| events_one_per_owner UNIQUE never fires | MatchEventSyncTest "re-creates the Event row when is_public is flipped back to true" | **GREEN** — updateOrCreate idempotent |

## Decisions Made

- **D-04-08-A:** No `Match as MatchModel` alias — use `GameMatch` directly (D-04-03-A LOCKED + D-04-07-C continuation).
- **D-04-08-B:** Model-level `booted()` ONLY — no AppServiceProvider fallback added; the observer fires reliably under test.
- **D-04-08-C:** Segregate observer-driven Event from manual Event::factory() by setting `is_public=false` on the underlying GameMatch — established as the canonical ripple-fix pattern for the 3 pre-existing test files broken by the observer's introduction.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Observer ripple breaks 6 pre-existing tests across 3 files (EventModelTest, EventDataTest, MatchModelTest)**

- **Found during:** Task 2 — after registering the observer in Task 1, the existing test suite went from 396 passed → 6 failed (UniqueConstraintViolationException on events_one_per_owner).
- **Root cause:** `GameMatch::factory()->create()` (default is_public=true) now fires `MatchObserver::saved()` which auto-creates Event #1 inside the model's save. Subsequent manual `Event::factory()->create(['eventable_id' => $match->id])` calls — common in DTO/model test fixtures — collide on the UNIQUE constraint.
- **Fix:** Two patterns applied symmetrically across all 6 broken tests:
  - **Pattern A (manual Event scenarios):** Set `is_public=false` on the underlying `GameMatch::factory()->create()` so the observer skips that match — then `Event::factory()` can manually create the polymorphic row.
  - **Pattern B (round-trip / morph scenarios):** Replace `Event::factory()` with `Event::where('eventable_type', GameMatch::class)->where('eventable_id', $match->id)->firstOrFail()` to use the observer-created row.
- **Files modified:**
  - `apps/web/tests/Feature/Models/EventModelTest.php` — 5 tests Pattern A, 2 tests Pattern B
  - `apps/web/tests/Unit/Data/EventDataTest.php` — 5 tests Pattern A
  - `apps/web/tests/Feature/Models/MatchModelTest.php` — 1 test Pattern B
- **Commit:** `7415414`
- **Codified as:** D-04-08-C — segregation pattern for future Phase 6+ tests that touch the events table.

### Non-deviations (planned ambiguities resolved)

- **`App\Models\Match as MatchModel` alias in plan body and RESEARCH Pattern 8** — Per D-04-03-A LOCKED + D-04-07-C, the model class is `App\Models\GameMatch` (NOT `Match`). The plan body and Pattern 8 snippet preserve the original Pitfall 5 alias because they predate the rename. Used `use App\Models\GameMatch;` throughout (D-04-08-A); zero `match($x)` PHP expressions in the new observer file.

- **AppServiceProvider fallback registration deferred / dropped (D-04-08-B):** The plan body marks AppServiceProvider modification as conditional and recommends skipping. The 8-test suite proves model-level `booted()` fires reliably — no fallback needed.

## Auth Gates

None — observer wiring + tests, no auth-bearing operations.

## Known Stubs

7 Wave 0 stubs remain incomplete-by-design (down from 8 before plan 04-08):

| Stub | Flipped GREEN by |
|---|---|
| `Admin/MatchResourcePresentTest` + `MatchResourceCreateWizardTest` + `MatchAuditLogTest` | 04-09 |
| `Services/MatchResultServiceTest` | 04-09 |
| `Matches/MatchCalendarPageTest` + `MatchShowPageTest` + `MatchSignupControllerTest` | 04-10 |

One stub flipped GREEN by this plan:
- `Observers/MatchEventSyncTest` ✓

## Threat Surface Notes

Threat register T-04-08-01..06 dispositions:

| Threat ID | Disposition | Mitigation status |
|---|---|---|
| T-04-08-01 (Cancelled match retains Event row) | mitigate | **MITIGATED** — `saved()` deletes Event when `status=cancelled` OR `is_public=false`. Proven by `it deletes the Event when match.status transitions to cancelled`. |
| T-04-08-02 (events_one_per_owner UNIQUE violated by observer) | mitigate | **MITIGATED** — `Event::updateOrCreate` keys match the UNIQUE columns exactly; the flip-off → flip-on test proves idempotency. |
| T-04-08-03 (events.title cache drift from Match.title edit) | mitigate | **MITIGATED** — `saved()` overwrites `title` on every save via `getTranslations('title')`. Proven by `it updates the Event title when match.title is edited`. Pitfall 12 (bulk update bypass) is the only escape vector and is documented in the observer docblock + test file header. |
| T-04-08-04 (Observer writes lack causer attribution) | accept | **ACCEPTED** — Event model's LogsActivity trait (plan 04-03) captures the implicit auth causer via Spatie's default resolver; observer-driven writes attribute to whoever triggered the GameMatch save (admin/organiser). |
| T-04-08-05 (Polymorphic eventable_type spoofing) | mitigate | **MITIGATED STRUCTURALLY** — `eventable_type` is set BY the observer only (hard-coded `GameMatch::class`); never user input. Filament EventResource (plan 04-09) is read-only. |
| T-04-08-06 (Observer execution order with multiple listeners) | accept | **ACCEPTED** — Phase 4 has only MatchObserver on GameMatch; Phase 6 will add TournamentObserver on Tournament — separate class, no interference. The `booted()` registration is idempotent. |

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|---|---|---|---|
| `c04e16e` | Task 1 — MatchObserver + GameMatch::booted() | 2 | Pattern 8 verbatim; D-04-08-A no alias; D-04-08-B model-level registration only |
| `7415414` | Task 2 — MatchEventSyncTest GREEN + 3 ripple fixes | 4 | 8 it() blocks / 17 assertions; Pitfall 12 covered; D-04-08-C segregation pattern |

## Self-Check: PASSED

- `apps/web/app/Observers/MatchObserver.php` exists — verified by `ls` and PHPStan analysis
- `apps/web/app/Models/GameMatch.php` modified — `static::observe(MatchObserver::class)` present (grep verified, count=1)
- `apps/web/app/Providers/AppServiceProvider.php` UNCHANGED — `git diff` confirms (D-04-08-B)
- `apps/web/tests/Feature/Observers/MatchEventSyncTest.php` — Wave 0 stub replaced (`placeholder` count=0)
- `apps/web/tests/Feature/Models/EventModelTest.php` ripple fix applied
- `apps/web/tests/Feature/Models/MatchModelTest.php` ripple fix applied
- `apps/web/tests/Unit/Data/EventDataTest.php` ripple fix applied
- Commits `c04e16e`, `7415414` both present in `git log --oneline -3`
- `make pest --filter=MatchEventSyncTest`: 8 passed / 17 assertions
- Full Pest suite: 404 passed (+8 vs plan 04-07 close) / 7 incomplete (−1 from this plan's stub flip)
- `make phpstan` full: 0 errors
- `make pint --test` (full 278 files): clean
