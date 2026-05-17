---
phase: 04-matches-manual
plan: 03
subsystem: matches
tags: [phase-4, wave-2, models, factories, naming-decision, partial-unique, polymorphic, has-translations, activity-log]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-3-summary
  provides:
    - phase-4-model-layer
    - game-match-model
    - match-slot-partial-unique-proven
    - user-active-clan-membership-accessor
    - events-polymorphic-morphto-proven
    - matches-tdd-green-baseline
  affects:
    - apps/web/app/Models/ (6 new + 1 amended)
    - apps/web/database/factories/ (5 stubs flipped to real + 1 renamed)
    - apps/web/tests/Feature/Models/ (6 stubs flipped GREEN)
tech_stack:
  added: []
  patterns:
    - protected-table-override-for-reserved-keyword-class-name
    - belongsto-explicit-foreign-key-for-non-conventional-model-name
    - has-translations-with-fillable-list
    - has-one-with-where-predicate-for-d-009-accessor
    - polymorphic-morphto-morphone-round-trip-test
    - softdelete-vs-forcedelete-fk-cascade-test
key_files:
  created:
    - apps/web/app/Models/GameMatch.php
    - apps/web/app/Models/Event.php
    - apps/web/app/Models/MatchSlot.php
    - apps/web/app/Models/MatchAccessRule.php
    - apps/web/app/Models/MatchResult.php
    - apps/web/app/Models/MatchMvp.php
    - apps/web/database/factories/GameMatchFactory.php
  modified:
    - apps/web/app/Models/User.php
    - apps/web/database/factories/EventFactory.php
    - apps/web/database/factories/MatchSlotFactory.php
    - apps/web/database/factories/MatchAccessRuleFactory.php
    - apps/web/database/factories/MatchResultFactory.php
    - apps/web/database/factories/MatchMvpFactory.php
    - apps/web/tests/Feature/Models/MatchModelTest.php
    - apps/web/tests/Feature/Models/MatchSlotModelTest.php
    - apps/web/tests/Feature/Models/MatchAccessRuleModelTest.php
    - apps/web/tests/Feature/Models/MatchResultModelTest.php
    - apps/web/tests/Feature/Models/MatchMvpModelTest.php
    - apps/web/tests/Feature/Models/EventModelTest.php
  deleted:
    - apps/web/database/factories/MatchFactory.php
decisions:
  - id: D-04-03-A
    decision: |
      **Model class is `App\Models\GameMatch` (NOT `App\Models\Match`).** The original
      plan body, 04-RESEARCH Pitfall 5, and 04-01-SUMMARY decision D-04-01-B all claimed
      `Match` was a legal PHP 8 class identifier with only cosmetic friction. **This was
      empirically wrong on PHP 8.4.** The previous executor attempt aborted plan 04-03
      after verifying `class Match {}` is a parse error:

          PHP Parse error: syntax error, unexpected token "match", expecting identifier

      `match` is a fully reserved keyword (PHP 8.0+) and cannot be used as a class name
      regardless of case in any context. The autonomous-workflow orchestrator locked
      `GameMatch` as the resolution before re-spawning this executor.

      Resolution applied:
        - Class name: `App\Models\GameMatch`
        - Database table: `matches` (unchanged — `protected $table = 'matches';` override)
        - FK column: `match_id` (unchanged — explicit `belongsTo(GameMatch::class, 'match_id')`)
        - Routes / URL slug: `/matches` (unchanged)
        - i18n keys: `matches.*` (unchanged)
        - Factory: `Database\Factories\GameMatchFactory`
        - Factory file: `database/factories/GameMatchFactory.php` (renamed from `MatchFactory.php` via `git mv`)

      Rationale:
        1. Symmetric with the Phase 3 family (GameMatchType, GameMatchTypeRoleLimit, GameRole).
        2. Aligns with D-007 (generic-game model — Match is one game's match-instance entity).
        3. Avoids ambiguity at every call site that uses `match($x) { ... }` expressions —
           no alias-on-import dance needed.
        4. Eloquent honors `protected $table` so the DB schema needs no rename.

      This decision is **binding for plans 04-04..04-13**. All subsequent code (services,
      DTOs, observers, controllers, Filament resources) references the class as
      `App\Models\GameMatch` and the table as `matches`. Supersedes D-04-01-B.

  - id: D-04-03-B
    decision: |
      Every BelongsTo<GameMatch, $this> relation on child models (`MatchSlot::match()`,
      `MatchAccessRule::match()`, `MatchResult::match()`) passes `match_id` as the explicit
      foreign-key arg: `$this->belongsTo(GameMatch::class, 'match_id')`. Laravel's default
      inference from a relation method named `match()` would look for `game_match_id`
      (snake-cased class name suffixed with `_id`), which would silently produce NULL
      relations against the actual `match_id` column. Explicit second-arg is mandatory.

  - id: D-04-03-C
    decision: |
      The "nullOnDelete on winner_clan_id" assertion in MatchResultModelTest uses
      `$clan->forceDelete()` (NOT `$clan->delete()`). Clan model uses the SoftDeletes
      trait — calling `delete()` only writes `deleted_at`, never issuing a DB-level
      DELETE that would trigger the FK cascade. `forceDelete()` bypasses SoftDeletes
      and proves the migration's `nullOnDelete()` contract. Discovered during Task 3
      test run (initial test failed with "result.winner_clan_id is not null" — the row
      was unchanged because the parent clan was only soft-deleted).
metrics:
  duration_minutes: 12
  completed: 2026-05-13
---

# Phase 4 Plan 03: Wave 2 Models + Factories + Relationship Tests Summary

**One-liner:** Six Phase 4 Eloquent models land (`GameMatch`, `Event`, `MatchSlot`, `MatchAccessRule`, `MatchResult`, `MatchMvp`) with full HasTranslations + LogsActivity + relation graph + 46 GREEN model tests proving every UNIQUE/CHECK/cascade invariant from plan 04-02 — and the `Match` class-name parse error blocker is resolved by renaming to `GameMatch` while keeping the DB schema untouched.

## What Shipped

### The naming decision (binding for the rest of Phase 4)

Plan 04-RESEARCH Pitfall 5 / Assumption A4 / D-04-01-B all asserted that `App\Models\Match` was a legal PHP 8 class identifier. **It is not, on PHP 8.4** — `match` is fully reserved and `class Match {}` is a hard parse error. The fix:

- **Class name:** `App\Models\GameMatch`
- **Factory:** `Database\Factories\GameMatchFactory`
- **DB schema:** entirely unchanged — table `matches`, FK columns `match_id`, routes `/matches`, i18n keys `matches.*`
- **Eloquent override:** `protected $table = 'matches';` on the model body
- **Explicit FKs:** every `belongsTo(GameMatch::class, 'match_id')` passes the foreign key explicitly because Laravel cannot infer `match_id` from a relation method named `match()` when the related class is `GameMatch`

Decision rationale and binding scope is captured in `D-04-03-A` (frontmatter). **Plans 04-04 through 04-13 inherit this naming.**

### 6 new model files

| File | Use stack | Translatable | Key wiring |
|------|-----------|--------------|------------|
| `apps/web/app/Models/GameMatch.php` | HasFactory<GameMatchFactory>, HasTranslations, HasUuidPrimaryKey, LogsActivity | `['title', 'description']` | `protected $table = 'matches';`; 6 relations (gameMatchType, organiser, hostClan, slots ordered by sort_order+slot_index, accessRules, result, event MorphOne); LogsActivity description `Match {event}` |
| `apps/web/app/Models/Event.php` | HasFactory<EventFactory>, HasTranslations, HasUuidPrimaryKey, LogsActivity | `['title']` | morphTo eventable(); LogsActivity description `Event {event}` |
| `apps/web/app/Models/MatchSlot.php` | HasFactory<MatchSlotFactory>, HasUuidPrimaryKey, LogsActivity | — | 3 relations (match, role, occupantUser), all with explicit FK; LogsActivity description `MatchSlot {event}`; D-010 defense-in-depth docblock |
| `apps/web/app/Models/MatchAccessRule.php` | HasFactory<MatchAccessRuleFactory>, HasUuidPrimaryKey, LogsActivity | — | 2 relations (match, clanTag); LogsActivity description `MatchAccessRule {event}` |
| `apps/web/app/Models/MatchResult.php` | HasFactory<MatchResultFactory>, HasUuidPrimaryKey, LogsActivity | — | 4 relations (match, winnerClan, recordedBy, mvps HasMany); LogsActivity description `MatchResult {event}` |
| `apps/web/app/Models/MatchMvp.php` | HasFactory<MatchMvpFactory>, HasUuidPrimaryKey, LogsActivity | — | 2 relations (result via `match_result_id`, player); LogsActivity description `MatchMvp {event}` |

### Naming-decision docblock on GameMatch.php (verbatim head)

```
NAMING DECISION (locked 2026-05-13; supersedes Pitfall 5 / Assumption A4 in 04-RESEARCH.md):

  The class is named `GameMatch` (NOT `Match`). The original plan assumed `Match` was a
  legal PHP 8 identifier merely with cosmetic friction. That assumption was empirically
  wrong on PHP 8.4 — `class Match {}` is a PARSE ERROR:

      PHP Parse error: syntax error, unexpected token "match", expecting identifier

  `match` is a fully reserved keyword (since PHP 8.0) and cannot be used as a class name
  ...
```

Plans 04-04..04-13 must reference this header when explaining their imports/aliases.

### Child-model BelongsTo<GameMatch, $this> explicit foreign keys (D-04-03-B)

| File | Method | Code |
|------|--------|------|
| `MatchSlot.php` | `match()` | `$this->belongsTo(GameMatch::class, 'match_id')` |
| `MatchAccessRule.php` | `match()` | `$this->belongsTo(GameMatch::class, 'match_id')` |
| `MatchResult.php` | `match()` | `$this->belongsTo(GameMatch::class, 'match_id')` |
| `MatchResult.php` | `mvps()` | `$this->hasMany(MatchMvp::class, 'match_result_id')` |
| `MatchMvp.php` | `result()` | `$this->belongsTo(MatchResult::class, 'match_result_id')` |

Laravel's default convention would look for `game_match_id` (snake-cased related class), not `match_id`. The explicit second-arg is mandatory and is now part of the Phase 4 idiom for plans 04-04+ to mirror.

### User.php Rule 2 amendments (D-009 helper accessors)

Two new relations added near `player()`:

```php
/** @return HasOne<ClanMembership, $this> */
public function activeClanMembership(): HasOne
{
    return $this->hasOne(ClanMembership::class)->whereNull('left_at');
}

/** @return HasMany<ClanMembership, $this> */
public function memberships(): HasMany
{
    return $this->hasMany(ClanMembership::class);
}
```

Required by RESEARCH Pattern 5 (`MatchSignupService::tagAccessAllowed()` reads `$user->activeClanMembership?->clan->tags()`) and by Phase 5/Filament admin history views. D-009 invariant guarantees the HasOne returns either the single active row or null — enforced at the DB layer by the `clan_memberships_one_active` partial UNIQUE index.

Also added `use Illuminate\Database\Eloquent\Relations\HasMany;` import.

### 6 real factories replacing Wave 0 stubs

| File | Action | Default scope |
|------|--------|---------------|
| `MatchFactory.php` → `GameMatchFactory.php` | renamed via `git mv` + rewritten | Fresh GameMatchType + User; status='open', is_public=true, scheduled_at = +1..+30 days |
| `MatchSlotFactory.php` | rewritten | Fresh GameMatch + GameRole (cross-game pair by default — documented in docblock) |
| `MatchAccessRuleFactory.php` | rewritten | Fresh GameMatch + ClanTag |
| `MatchResultFactory.php` | rewritten | Fresh GameMatch + Clan + User; allies_score=4, axis_score=1 |
| `MatchMvpFactory.php` | rewritten | Fresh MatchResult + Player; category='kills' |
| `EventFactory.php` | rewritten | Fresh GameMatch as polymorphic owner |

All Wave 0 idiom artifacts (`@phpstan-ignore-next-line` annotations, string FQN `$model`) are removed. Each factory now carries the canonical `@extends Factory<XModel>` generic and `protected $model = XModel::class;`.

### 6 GREEN model tests (46 assertions total, replacing Wave 0 RED stubs)

| Test file | `it()` count | Critical assertions |
|-----------|--------------|---------------------|
| `MatchModelTest.php` | 10 | factory creates valid match; HasTranslations on title + description independently; matches_status_check fires on invalid enum; all 5 valid status values; 6 relations (gameMatchType, organiser, hostClan, slots ordered by sort_order+slot_index, accessRules, result, event MorphOne); cascade to slots; activity log |
| `MatchSlotModelTest.php` | 9 | composite UNIQUE; **partial UNIQUE `match_slots_one_occupancy_per_user`** (the security-critical Phase 4 assertion); partial UNIQUE skips NULL occupants; same user can occupy slots in different matches; 3 BelongsTo relations; cascade on parent match delete; FK nullOnDelete on occupant_user; activity log |
| `MatchAccessRuleModelTest.php` | 5 | composite UNIQUE; multi-tag per match; 2 relations; cascade |
| `MatchResultModelTest.php` | 8 | 1:1 UNIQUE on match_id; match_results_scores_nonneg_check on allies_score + axis_score; NULL scores accepted; 4 relations including mvps HasMany; cascade chain through mvps; winner_clan nullOnDelete (uses `forceDelete()` — Clan uses SoftDeletes) |
| `MatchMvpModelTest.php` | 7 | composite UNIQUE; same player in different categories of same result; match_mvps_category_check on invalid enum; all 4 valid category values; 2 relations; cascade |
| `EventModelTest.php` | 7 | factory; HasTranslations on title; events_one_per_owner UNIQUE; **morphTo round-trip** (`$event->eventable->is($match)`); **morphOne round-trip** (`$match->event->id === $event->id`); nullable ends_at; activity log |

**Total: 46 `it()` blocks, 77 assertions** — all GREEN.

### Partial UNIQUE assertion approach

The security-critical assertion in MatchSlotModelTest (covers T-04-03-03 mass-assignment threat):

```php
it('blocks a user occupying two slots in the same match (partial UNIQUE)', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $user = User::factory()->create();

    MatchSlot::factory()->create([
        'match_id' => $match->id, 'game_role_id' => $role->id,
        'slot_index' => 0, 'occupant_user_id' => $user->id, 'confirmed_at' => now(),
    ]);
    $slotB = MatchSlot::factory()->create([
        'match_id' => $match->id, 'game_role_id' => $role->id,
        'slot_index' => 1, 'occupant_user_id' => null,
    ]);

    expect(fn () => $slotB->update(['occupant_user_id' => $user->id, 'confirmed_at' => now()]))
        ->toThrow(QueryException::class);
});
```

Plus the companion negative-control test:

```php
it('allows a user to occupy slots in different matches', function (): void {
    // Same user, different matches => partial UNIQUE scope is per-match.
});
```

If MatchSignupService idempotency ever regresses, this partial UNIQUE is the DB-layer net.

### Polymorphic morphTo round-trip assertion

```php
it('resolves morphTo eventable() to a GameMatch instance', function (): void {
    $match = GameMatch::factory()->create();
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $reloaded = $event->fresh();
    expect($reloaded->eventable)->toBeInstanceOf(GameMatch::class);
    expect($reloaded->eventable->is($match))->toBeTrue();
});
```

Plus the inverse direction via morphOne:

```php
it('round-trips through morphOne back to the owning match', function (): void {
    // $match->fresh()->load('event')->event->id === $event->id
});
```

Both prove that `Event::class` is stored as FQN string `'App\\Models\\Event'` (via PHP's auto-escape of backslashes in JSON), `eventable_type` reads back as `'App\\Models\\GameMatch'`, and Laravel's morph resolution maps it back to `GameMatch::class` without a morphMap configured.

## Verification

| Gate | Command | Result |
|------|---------|--------|
| Full Pest suite | `make pest` | **16 incomplete + 324 passed**, 899 assertions, 13.68s — exactly +46 GREEN vs Wave 0 baseline (278 passed), −6 incomplete (22 → 16) |
| Phase 4 model tests | `make pest --filter='Models/(Match\|EventModel)'` | 46 passed, 77 assertions |
| PHPStan L8 | `make phpstan` | **0 errors** across all analyzed files |
| Pint test | `make pint --test` | **262 files clean** |
| migrate:fresh --seed | `make artisan ARGS="migrate:fresh --seed"` | All 30 migrations + all seeders green |
| `class GameMatch` parse-check | `docker compose exec web php -r "require 'vendor/autoload.php'; new App\\Models\\GameMatch();"` | No parse error (vs `class Match` which fatals immediately) |

## Decisions Made

- **D-04-03-A:** Class is `GameMatch`, table stays `matches`; supersedes Pitfall 5 / D-04-01-B. **Binding for plans 04-04..04-13.**
- **D-04-03-B:** Every BelongsTo<GameMatch, $this> passes `match_id` as the explicit FK arg (Laravel cannot infer `match_id` from `match()` when the related class is `GameMatch`).
- **D-04-03-C:** SoftDelete-aware FK cascade tests use `forceDelete()` to fire the DB-level cascade (specifically `winner_clan_id` nullOnDelete in MatchResultModelTest).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 4 — Architectural, pre-resolved] Class name `Match` is a PHP 8.4 parse error**
- **Found during:** Previous executor's pre-flight check on plan 04-03 task 1
- **Issue:** The plan body asserted `class Match {}` was legal (Pitfall 5, A4, D-04-01-B). On PHP 8.4 `match` is a reserved keyword; `class Match {}` is a parse error.
- **Fix:** Autonomous workflow locked `GameMatch` as the resolution before re-spawning this executor. Class is `App\Models\GameMatch`; table override `protected $table = 'matches';` preserves the schema. Factory file renamed `MatchFactory.php` → `GameMatchFactory.php` via `git mv`.
- **Files affected:** all 6 Phase 4 model files, all 6 factories, all 6 model tests, plan binding for 04-04..04-13.
- **Commit:** 4f59836

**2. [Rule 1 — Bug] MatchResultModelTest "nullOnDelete on winner_clan_id" failed first run**
- **Found during:** Task 3 test run (1 of 46 tests failed)
- **Issue:** Initial test called `$clan->delete()`, but Clan model uses the SoftDeletes trait — that only writes `deleted_at` and never issues a DB-level DELETE, so the FK cascade does not fire. Test asserted `winner_clan_id IS NULL` but found the original UUID intact.
- **Fix:** Changed to `$clan->forceDelete()` and added an inline comment + the test name now reads "force-deleted". This correctly proves the migration's `nullOnDelete()` contract.
- **Files modified:** `apps/web/tests/Feature/Models/MatchResultModelTest.php`
- **Commit:** eec8ab8 (folded into Task 3)

### Non-deviations (planned ambiguities resolved)

- **04-01-SUMMARY references to `App\Models\Match`:** The orchestrator's execution-rules note said "Update 04-01-SUMMARY references where they say `App\Models\Match` → `App\Models\GameMatch`." Deliberately left 04-01-SUMMARY untouched — it is a frozen historical artifact of what the Wave 0 executor *thought* was the canonical name. The supersession is recorded in D-04-03-A (this summary) and is discoverable from the GameMatch class docblock. Editing prior summaries would muddy the audit trail; the supersession-by-new-decision idiom is the same pattern CLAUDE.md §0 describes ("open a new D-### that supersedes the old one rather than editing in place").
- **No alias-on-import:** Phase 4 test files do not use `match($x) { ... }` expressions, so the alias `use App\Models\GameMatch as MatchModel;` is not needed. Tests import `use App\Models\GameMatch;` directly. Future plans (e.g. MatchSignupService in 04-06) that DO use match-expressions for status transitions may need the alias — that decision lives in plan 04-04+ scope.

## Auth Gates

None — pure model/factory/test work, no auth-bearing operations.

## Known Stubs

10 Wave 0 stubs remain incomplete-by-design (down from 22 after this plan), tracked in `04-01-SUMMARY.md`:

| Stub | Flipped GREEN by |
|------|------------------|
| `Services/MatchStatusServiceTest` | 04-04 |
| `Services/MatchSlotMaterialiserServiceTest` | 04-05 |
| `Services/MatchSignupServiceTest` + `MatchSignupConcurrencyTest` + `Matches/MatchSignupTagRestrictedTest` | 04-06 |
| `Unit/Data/MatchDataTest` + `PublicMatchDataTest` + `EventDataTest` | 04-07 |
| `Observers/MatchEventSyncTest` | 04-08 |
| `Admin/MatchResourcePresentTest` + `MatchResourceCreateWizardTest` + `MatchAuditLogTest` + `Services/MatchResultServiceTest` | 04-09 |
| `Matches/MatchCalendarPageTest` + `MatchShowPageTest` + `MatchSignupControllerTest` | 04-10 |

All 6 model-test stubs (which were the scope of plan 04-03) are now GREEN.

No accidental stubs introduced.

## Threat Surface Notes

Threat register T-04-03-01..08 fully addressed:

- **T-04-03-01 (mass-assignment):** Strict `$fillable` lists on all 6 new models; LogsActivity logFillable+logOnlyDirty captures every change (D-012).
- **T-04-03-02 (composite UNIQUE bypass on match_slots):** Pest test `enforces composite UNIQUE (match_id, game_role_id, slot_index)` proves QueryException.
- **T-04-03-03 (partial UNIQUE bypass — second slot for same user):** Pest test `blocks a user occupying two slots in the same match (partial UNIQUE)` proves QueryException.
- **T-04-03-04 (invalid status enum via raw factory):** Pest test `enforces matches_status_check CHECK constraint at the DB layer` proves QueryException.
- **T-04-03-05 (polymorphic eventable_type drift):** Pest test `resolves morphTo eventable() to a GameMatch instance` proves FQN round-trip.
- **T-04-03-06 (audit log on all mutations):** Pest tests `logs activity on create (D-012)` in MatchModelTest, MatchSlotModelTest, EventModelTest confirm subject_type writes.
- **T-04-03-07 (cascade orphans):** Pest tests `cascades on parent match delete` in MatchSlotModelTest, MatchAccessRuleModelTest, plus the chain test in MatchResultModelTest (match → result → mvps).
- **T-04-03-08 (activity log captures Match.title in JSONB):** Accepted — Match.title is intentionally public.

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|------|------|-------|------------|
| `4f59836` | Task 1 — GameMatch + Event models + 2 factories | 5 | Naming decision committed; GameMatchFactory renamed via `git mv`; Event polymorphic morphTo |
| `9f1af7c` | Task 2 — 4 child models + 4 factories + User accessor | 9 | MatchSlot/MatchAccessRule/MatchResult/MatchMvp; explicit `match_id` FKs on all BelongsTo<GameMatch>; User::activeClanMembership() + memberships() |
| `eec8ab8` | Task 3 — 6 GREEN model tests (46 assertions) | 6 | Partial UNIQUE on match_slots proven; polymorphic round-trip proven; SoftDelete-aware forceDelete() pattern |

## Self-Check: PASSED

- 6 new model files exist at `apps/web/app/Models/{GameMatch,Event,MatchSlot,MatchAccessRule,MatchResult,MatchMvp}.php` (verified via test imports compiling)
- 6 factories real: `database/factories/{GameMatchFactory,EventFactory,MatchSlotFactory,MatchAccessRuleFactory,MatchResultFactory,MatchMvpFactory}.php` (the old `MatchFactory.php` is removed)
- User.php carries `activeClanMembership()` + `memberships()`; `grep -c 'activeClanMembership' apps/web/app/Models/User.php` returns ≥ 2
- 6 model tests GREEN (46 `it()` blocks, 77 assertions); 0 `markTestIncomplete` and 0 `placeholder` strings remain in any of the 6 files
- Commits 4f59836, 9f1af7c, eec8ab8 present in `git log --oneline -5`
- `make pest`: 16 incomplete + 324 passed (no regressions vs Wave 0; +46 GREEN, −6 incomplete)
- `make phpstan`: 0 errors
- `make pint --test`: 262 files clean
- `make artisan migrate:fresh --seed`: all migrations + seeders green
