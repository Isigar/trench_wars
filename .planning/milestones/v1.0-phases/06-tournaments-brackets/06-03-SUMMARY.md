---
phase: 06-tournaments-brackets
plan: 03
subsystem: models
tags:
  - wave-2
  - models
  - factories
  - has-translations
  - logs-activity
  - d-04-03-b
  - pitfall-4
  - pitfall-11
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 factory + test RED stubs
    - .planning/phases/06-tournaments-brackets/06-02-SUMMARY.md  # 5 tables + CHECKs + partial UNIQUE
    - .planning/phases/04-matches-manual/04-03-SUMMARY.md         # Canonical Phase 4 model idiom (GameMatch)
  provides:
    - "5 Eloquent models — Tournament, TournamentParticipant, TournamentStage, TournamentBracket, TournamentStanding"
    - "Tournament HasTranslations on title + description (D-013); routeKey=slug; booted() registers TournamentObserver"
    - "TournamentBracket — 7 relations with explicit FK args (D-04-03-B): match_id, advances_to_bracket_id, loser_advances_to_bracket_id, participant_a_id, participant_b_id, winner_participant_id"
    - "TournamentObserver stub (empty saved/created/updated) — plan 06-10 replaces with real Event-sync + Discord outbound writers"
    - "5 real factories (Wave 0 stubs replaced): TournamentFactory + 4 siblings, with ->ofFormat / ->inStatus / ->active / ->withSeed helper states"
    - "5 GREEN Pest model tests (47 tests / 103 assertions) covering Pitfall 4 + Pitfall 11 + composite UNIQUEs + LogsActivity + D-04-03-B FK column verification"
  affects:
    - apps/web/app/Models/        # 5 new model files
    - apps/web/app/Observers/     # 1 new observer stub (TournamentObserver)
    - apps/web/database/factories/ # 5 factories overwritten (Wave 0 stubs → real)
    - apps/web/tests/Feature/Models/  # 5 RED stubs flipped to GREEN
tech-stack:
  added: []
  patterns:
    - "Phase 4 canonical model idiom — HasUuidPrimaryKey + HasFactory<XFactory> generic + LogsActivity (defaults + logFillable + logOnlyDirty + dontLogIfAttributesChangedOnly(['updated_at']) + setDescriptionForEvent)"
    - "Tournament additions on top of the canonical idiom — HasTranslations + \$translatable property + getRouteKeyName='slug' + booted() static::observe(TournamentObserver::class) + MorphOne<Event> event() relation"
    - "D-04-03-B explicit FK args wherever method name diverges from related class — match() / advancesTo() / loserAdvancesTo() / participantA() / participantB() / winnerParticipant() / organiser() / defaultGameMatchType() / stage()"
    - "Forward-declared observer stub pattern — ship empty saved/created/updated bodies so booted() never crashes at boot; later plan fills the real logic (matches Phase 4 plan 04-08 MatchObserver shape)"
    - "Pitfall 4 + Pitfall 11 DB-layer defence verification via QueryException expectations — Pitfall 11 uses DB::table()->update() to bypass the application layer (factory default starts NULL so the application save() path never trips the CHECK on a fresh row)"
key-files:
  created:
    - apps/web/app/Models/Tournament.php
    - apps/web/app/Models/TournamentParticipant.php
    - apps/web/app/Models/TournamentStage.php
    - apps/web/app/Models/TournamentBracket.php
    - apps/web/app/Models/TournamentStanding.php
    - apps/web/app/Observers/TournamentObserver.php
  modified:
    - apps/web/database/factories/TournamentFactory.php
    - apps/web/database/factories/TournamentParticipantFactory.php
    - apps/web/database/factories/TournamentStageFactory.php
    - apps/web/database/factories/TournamentBracketFactory.php
    - apps/web/database/factories/TournamentStandingFactory.php
    - apps/web/tests/Feature/Models/TournamentModelTest.php
    - apps/web/tests/Feature/Models/TournamentParticipantModelTest.php
    - apps/web/tests/Feature/Models/TournamentStageModelTest.php
    - apps/web/tests/Feature/Models/TournamentBracketModelTest.php
    - apps/web/tests/Feature/Models/TournamentStandingModelTest.php
decisions:
  - "D-06-03-A: TournamentBracket::match() uses the explicit FK arg 'match_id' per D-04-03-B because the method name match() does not match the related class GameMatch — Laravel's auto-inferred column would be 'game_match_id' which does not exist on tournament_brackets (FK is 'match_id' per migration 2026_05_15_100300). Verified via getForeignKeyName() === 'match_id' in TournamentBracketModelTest. Same rule applied to advancesTo()/loserAdvancesTo() which reference the same class as $this — explicit FK args 'advances_to_bracket_id' and 'loser_advances_to_bracket_id' are mandatory because Laravel cannot infer a sensible default when class === self."
  - "D-06-03-B: All 5 models use Spatie\\Activitylog\\Models\\Concerns\\LogsActivity (NOT the older Spatie\\Activitylog\\Traits\\LogsActivity that appears in plan 06-03 <interfaces>). The Concerns path is canonical in laravel-activitylog v5; Phase 4 GameMatch verified it on master. The plan's interfaces sample is acknowledged stale; the canonical Phase 4 idiom wins."
  - "D-06-03-C: getActivitylogOptions() on every Phase 6 model includes ->dontLogIfAttributesChangedOnly(['updated_at']). Phase 4 GameMatch does NOT have this clause; Phase 6 plan 06-03 explicitly requires it per the must_haves truth #3. Adopted Phase 6-wide so save() calls that only bump updated_at (e.g., touching a parent from a child observer) do not pollute activity_log with no-op rows."
metrics:
  duration: ~30m
  completed: 2026-05-13
  tasks: 2
  files_created: 6
  files_modified: 10
  commits: 2
---

# Phase 6 Plan 3: Wave 2 — 5 Models + Factories + Observer Stub Summary

5 Eloquent models for Phase 6 land with the verbatim Phase 4 canonical idiom + Tournament HasTranslations. Every cross-class / self-FK BelongsTo carries an explicit FK arg per D-04-03-B. TournamentObserver ships as a forward-declared stub (plan 06-10 replaces the bodies). 5 real factories replace the Wave 0 stubs; 5 GREEN Pest model tests cover Pitfall 4 (partial UNIQUE match_id), Pitfall 11 (no_self_advance CHECK), every composite UNIQUE defence from plan 06-02, and LogsActivity emission.

## What Landed

### 5 Eloquent Models — Canonical Trait Stack

| Model | HasUuidPrimaryKey | HasFactory<X> | LogsActivity | HasTranslations | Extras |
|-------|-------------------|---------------|--------------|-----------------|--------|
| `Tournament` | yes | yes (`TournamentFactory`) | yes | yes (`title`, `description`) | `getRouteKeyName='slug'`, `booted()`, MorphOne<Event> |
| `TournamentParticipant` | yes | yes (`TournamentParticipantFactory`) | yes | no | — |
| `TournamentStage` | yes | yes (`TournamentStageFactory`) | yes | no | — |
| `TournamentBracket` | yes | yes (`TournamentBracketFactory`) | yes | no | 2 self-FK + 1 GameMatch BelongsTo with explicit FK args |
| `TournamentStanding` | yes | yes (`TournamentStandingFactory`) | yes | no | decimal:2 casts on points + tiebreak_score |

All five emit `getActivitylogOptions()` with the same shape: `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->setDescriptionForEvent(fn (string $event) => "{Model} {$event}")`.

### Complete Relation Graph

| Model | Relation | Type | Target | FK column (explicit?) |
|-------|----------|------|--------|------------------------|
| `Tournament` | `game()` | BelongsTo | `Game` | inferred (`game_id`) |
| `Tournament` | `organiser()` | BelongsTo | `User` | **`organiser_user_id`** |
| `Tournament` | `defaultGameMatchType()` | BelongsTo | `GameMatchType` | **`default_game_match_type_id`** |
| `Tournament` | `participants()` | HasMany | `TournamentParticipant` | inferred |
| `Tournament` | `stages()` | HasMany | `TournamentStage` | inferred + `orderBy('ordinal')` |
| `Tournament` | `standings()` | HasMany | `TournamentStanding` | inferred |
| `Tournament` | `event()` | MorphOne | `Event` | (`eventable_type`, `eventable_id`) |
| `TournamentParticipant` | `tournament()` | BelongsTo | `Tournament` | inferred |
| `TournamentParticipant` | `clan()` | BelongsTo | `Clan` | inferred |
| `TournamentParticipant` | `bracketsAsA()` | HasMany | `TournamentBracket` | **`participant_a_id`** |
| `TournamentParticipant` | `bracketsAsB()` | HasMany | `TournamentBracket` | **`participant_b_id`** |
| `TournamentParticipant` | `bracketsAsWinner()` | HasMany | `TournamentBracket` | **`winner_participant_id`** |
| `TournamentStage` | `tournament()` | BelongsTo | `Tournament` | inferred |
| `TournamentStage` | `brackets()` | HasMany | `TournamentBracket` | inferred + `orderBy(round_number, position)` |
| `TournamentBracket` | `stage()` | BelongsTo | `TournamentStage` | **`tournament_stage_id`** |
| `TournamentBracket` | `participantA()` | BelongsTo | `TournamentParticipant` | **`participant_a_id`** |
| `TournamentBracket` | `participantB()` | BelongsTo | `TournamentParticipant` | **`participant_b_id`** |
| `TournamentBracket` | `winnerParticipant()` | BelongsTo | `TournamentParticipant` | **`winner_participant_id`** |
| `TournamentBracket` | `match()` | BelongsTo | `GameMatch` | **`match_id`** (D-04-03-B) |
| `TournamentBracket` | `advancesTo()` | BelongsTo | `TournamentBracket` (self) | **`advances_to_bracket_id`** |
| `TournamentBracket` | `loserAdvancesTo()` | BelongsTo | `TournamentBracket` (self) | **`loser_advances_to_bracket_id`** |
| `TournamentStanding` | `tournament()` | BelongsTo | `Tournament` | inferred |
| `TournamentStanding` | `stage()` | BelongsTo | `TournamentStage` | **`tournament_stage_id`** |
| `TournamentStanding` | `participant()` | BelongsTo | `TournamentParticipant` | **`participant_id`** |

**Total: 24 relations across 5 models.**

### D-04-03-B Compliance — Explicit FK Args Roll-Call

Every relation method where Laravel cannot infer the FK column from `{method_name}_id` (because the method name diverges from the class name, OR the relation is self-referential, OR the FK column name diverges from convention) ships an explicit FK arg. The 13 explicit FK args used in Phase 6:

| FK arg literal | Used by | Migration column |
|----------------|---------|------------------|
| `'organiser_user_id'` | `Tournament::organiser()` | `tournaments.organiser_user_id` |
| `'default_game_match_type_id'` | `Tournament::defaultGameMatchType()` | `tournaments.default_game_match_type_id` |
| `'participant_a_id'` | `TournamentParticipant::bracketsAsA()`, `TournamentBracket::participantA()` | `tournament_brackets.participant_a_id` |
| `'participant_b_id'` | `TournamentParticipant::bracketsAsB()`, `TournamentBracket::participantB()` | `tournament_brackets.participant_b_id` |
| `'winner_participant_id'` | `TournamentParticipant::bracketsAsWinner()`, `TournamentBracket::winnerParticipant()` | `tournament_brackets.winner_participant_id` |
| `'tournament_stage_id'` | `TournamentBracket::stage()`, `TournamentStanding::stage()` | `tournament_brackets.tournament_stage_id`, `tournament_standings.tournament_stage_id` |
| `'match_id'` | `TournamentBracket::match()` | `tournament_brackets.match_id` |
| `'advances_to_bracket_id'` | `TournamentBracket::advancesTo()` | `tournament_brackets.advances_to_bracket_id` |
| `'loser_advances_to_bracket_id'` | `TournamentBracket::loserAdvancesTo()` | `tournament_brackets.loser_advances_to_bracket_id` |
| `'participant_id'` | `TournamentStanding::participant()` | `tournament_standings.participant_id` |

(The remaining BelongsTo without explicit FK args — `Tournament::game()`, `TournamentParticipant::tournament()`, `TournamentParticipant::clan()`, `TournamentStage::tournament()`, `TournamentStanding::tournament()` — rely on the default snake-case inference because the method name matches the related class name and the column name follows the convention.)

The `TournamentBracketModelTest::D-04-03-B` assertions verify `match()->getForeignKeyName() === 'match_id'`, `advancesTo()->getForeignKeyName() === 'advances_to_bracket_id'`, and `loserAdvancesTo()->getForeignKeyName() === 'loser_advances_to_bracket_id'` at runtime, so a future regression to inferred FK columns triggers a hard test failure.

### TournamentObserver Status

| Method | Plan 06-03 body | Plan 06-10 body |
|--------|-----------------|------------------|
| `saved(Tournament $t)` | empty | `Event::updateOrCreate / delete` based on `is_public + status` |
| `created(Tournament $t)` | empty | `DiscordOutboundMessage::create(... 'tournament_announce' ...)` gated on `is_public` + organiser channel cfg |
| `updated(Tournament $t)` | empty | `wasChanged('status')` → `DiscordOutboundMessage::create(... 'tournament_status_update' ...)` |

The stub keeps `Tournament::booted()` safe to call at app boot. No Tournament observer test asserts side-effects in plan 06-03 — `TournamentObserverTest.php` (Wave 0 RED stub) remains placeholder until plan 06-10 flips it.

### 5 Real Factories — Wave 0 Replacement

| Factory | Helper states |
|---------|---------------|
| `TournamentFactory` | `->ofFormat(string)`, `->inStatus(string)` |
| `TournamentParticipantFactory` | `->active()`, `->withSeed(int)` |
| `TournamentStageFactory` | — (default `type='elim'`, `ordinal=1`) |
| `TournamentBracketFactory` | — (default `round_number=1`, `position=1`, all FKs NULL) |
| `TournamentStandingFactory` | — (counters default to 0, `rank=null`) |

Every factory now declares `protected $model = X::class;` (not the Wave 0 string FQN) and `@extends Factory<X>` generic (Wave 0 phpstan-ignore annotations dropped). Factory chain smoke test confirmed end-to-end:

```bash
$ docker compose exec web php artisan tinker --execute "App\Models\Tournament::factory()->create(); App\Models\TournamentBracket::factory()->create(); App\Models\TournamentStanding::factory()->create(); echo 'OK';"
OK
```

### 5 GREEN Pest Model Tests — Assertion Inventory

| Test file | `it()` blocks | Pest assertions |
|-----------|----------------|-----------------|
| `TournamentModelTest.php` | 12 | 28 |
| `TournamentParticipantModelTest.php` | 9 | 19 |
| `TournamentStageModelTest.php` | 8 | 17 |
| `TournamentBracketModelTest.php` | 11 | 24 |
| `TournamentStandingModelTest.php` | 6 | 15 |
| **Total** | **47** | **103** |

(Pest reports `Tests: 47 passed (103 assertions)`.)

Critical coverage milestones:

- **Pitfall 4 (partial UNIQUE `match_id`)** — `TournamentBracketModelTest::rejects_duplicate_match_id_via_partial_UNIQUE` (QueryException) + `allows_multiple_brackets_with_match_id_IS_NULL` (3 NULL rows persist OK).
- **Pitfall 11 (no_self_advance CHECK)** — `TournamentBracketModelTest::rejects_advances_to_bracket_id_self` + `rejects_loser_advances_to_bracket_id_self` (both via `DB::table()->update(...)` raw bypass — factory default starts NULL so the application save() path never reaches the CHECK).
- **D-04-03-B FK column verification** — `TournamentBracketModelTest::D-04-03-B_match_relation_uses_match_id_FK_column` + sibling for `advancesTo / loserAdvancesTo` (3 `getForeignKeyName()` assertions).
- **Composite UNIQUEs** — `(tournament_id, clan_id)` (TournamentParticipantModelTest), `(tournament_id, ordinal)` (TournamentStageModelTest), `(tournament_stage_id, round_number, position)` (TournamentBracketModelTest), `(tournament_stage_id, participant_id)` (TournamentStandingModelTest).
- **CHECK constraints** — `tournaments_format_check`, `tournaments_status_check` (TournamentModelTest), `tournament_participants_status_check` (TournamentParticipantModelTest), `tournament_stages_type_check` (TournamentStageModelTest).
- **HasTranslations** — `TournamentModelTest::round-trips_title_+_description_through_HasTranslations`.
- **LogsActivity** — every model test asserts `Activity::query()->where('subject_type', Model::class)->where('subject_id', $model->id)->where('event', 'created')->exists() === true`; TournamentModelTest also covers `event='updated'` on a `status` flip.

## Verification

| Gate | Result |
|------|--------|
| `pint --test` on all 11 task-1 files (5 models + 1 observer + 5 factories) | PASS — 11 files clean |
| `pint --test` on all 5 task-2 test files (after auto-fix) | PASS — 5 files clean |
| `phpstan analyse` on all 16 changed files | PASS — `[OK] No errors` |
| Full-project `phpstan analyse` (regression check) | PASS — `[OK] No errors` |
| `pest tests/Feature/Models/Tournament*ModelTest.php` | PASS — 47 tests / 103 assertions |
| `grep -l 'placeholder' apps/web/tests/Feature/Models/Tournament*.php \| wc -l` | 0 — no placeholder literals remain |
| Factory chain smoke test (`tinker --execute`) | OK |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PHPStan L8 errors on the first TournamentFactory authoring**

- **Found during:** Task 1 verification.
- **Issue:** Two PHPStan errors fired on the first pass:
  1. `Parse error in @phpstan-ignore: Unexpected T_OTHER '\`' after @phpstan-ignore` — caused by the docblock containing the literal string ``@phpstan-ignore`` (backtick-wrapped) which PHPStan's docblock parser tried to parse as a directive.
  2. `Parameter #1 $title of static method Illuminate\Support\Str::slug() expects string, array|string given` — Faker's `unique()->words(3, true)` signature returns `array|string` to PHPStan even though `true` guarantees a string at runtime.
- **Fix:** (a) Removed backticks around `@phpstan-ignore` in the docblock so PHPStan no longer treats it as a directive. (b) Coerced the Faker return to `string` via a `/** @var string $words */` local annotation before passing to `Str::slug()`.
- **Files modified:** `apps/web/database/factories/TournamentFactory.php`.
- **Commit:** `c4678db` (squashed into Task 1's commit).

**2. [Pint auto-fix - Style] 4 style issues across 4 test files**

- **Found during:** Task 2 verification.
- **Issue:** Pint flagged: `fully_qualified_strict_types` (use `Carbon` import instead of `\Illuminate\Support\Carbon::class` literal in two files), `method_argument_space` (line wrapping inside `expect(fn () => ... )->toThrow()`), `new_with_parentheses` (`new TournamentBracket()` → `new TournamentBracket`).
- **Fix:** `vendor/bin/pint` auto-fix applied; re-verified Pint + PHPStan + Pest after — all green, 47/47 tests still pass.
- **Files modified:** `TournamentModelTest.php`, `TournamentParticipantModelTest.php`, `TournamentStandingModelTest.php`, `TournamentBracketModelTest.php`.
- **Commit:** Folded into Task 2's commit `bc643cb`.

No other deviations. Plan executed as written. The plan's `<interfaces>` sample uses `Spatie\Activitylog\Traits\LogsActivity` (older path) — locked decision D-06-03-B records that we use the canonical `Spatie\Activitylog\Models\Concerns\LogsActivity` instead, matching Phase 4 GameMatch and every other Phase 1-5 model.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-03-01 (Tampering — match() FK inference bug) | mitigate | Explicit `'match_id'` arg in `belongsTo(GameMatch::class, 'match_id')` + runtime assertion `getForeignKeyName() === 'match_id'` in TournamentBracketModelTest |
| T-06-03-02 (Tampering — self-FK FK inference bug) | mitigate | Explicit `'advances_to_bracket_id'` / `'loser_advances_to_bracket_id'` args + runtime `getForeignKeyName()` assertions in TournamentBracketModelTest |
| T-06-03-03 (Tampering — booted() crashes at boot from missing observer class) | mitigate | TournamentObserver stub ships with empty methods alongside the model |
| T-06-03-04 (Repudiation — LogsActivity COUNT brittleness on factory chains) | mitigate | All LogsActivity assertions use `Activity::query()->where('subject_id', $model->id)->exists()` — id-scoped, not count-based |
| T-06-03-05 (Tampering — HasTranslations without `$translatable` property) | mitigate | `public array $translatable = ['title', 'description']` declared on Tournament + verified by `TournamentModelTest::round-trips_title_+_description_through_HasTranslations` |

## Threat Flags

None — Phase 6 plan 06-03 changes are purely model + factory + test additions inside the trust boundary already documented by the plan's `<threat_model>`. No new endpoints, no new auth paths, no new file access, no new schema (plan 06-02 owns the schema).

## Known Stubs

**TournamentObserver** (apps/web/app/Observers/TournamentObserver.php) — intentional forward-declared stub. saved/created/updated bodies are empty. Plan 06-10 replaces them with the real Event-sync + Discord outbound writers. This stub is the canonical pattern (matches Phase 4 plan 04-08 MatchObserver shape pre-fill-in) and is REQUIRED for `Tournament::booted()` to register the observer without a class-existence fatal at app boot.

No other stubs. The 5 model files + 5 factory files + 5 test files are fully implemented.

## Plan Linkages

- **Plan 06-04 (TournamentStatusService)** can now bind against `Tournament::$fillable` + `Tournament::booted()` + the `Activity()->` query chain.
- **Plan 06-05..06-09 (services)** consume the model relation graph documented above.
- **Plan 06-08 (BracketAdvancementService)** writes `tournament_brackets.advances_to_bracket_id` — the DB-layer no-self-advance CHECK exercised here is its safety net.
- **Plan 06-09 (StandingsCalculatorService)** writes to `tournament_standings.{wins,losses,draws,points,tiebreak_score,rank}` via the upsert pattern allowed by the `(tournament_stage_id, participant_id)` UNIQUE.
- **Plan 06-10 (DTOs + observer bodies)** replaces the TournamentObserver stub bodies and ships the Spatie\LaravelData DTOs that consume the model casts + relations.
- **Plan 06-11 (Filament admin resources)** binds `TournamentResource` against the Tournament model's translatable columns + relation graph.

## Self-Check: PASSED

- All 6 created files exist on disk:
  - `apps/web/app/Models/Tournament.php`
  - `apps/web/app/Models/TournamentParticipant.php`
  - `apps/web/app/Models/TournamentStage.php`
  - `apps/web/app/Models/TournamentBracket.php`
  - `apps/web/app/Models/TournamentStanding.php`
  - `apps/web/app/Observers/TournamentObserver.php`
- Both commits exist on `master`:
  - `c4678db` — feat(06-03): 5 Phase 6 models + TournamentObserver stub + 5 real factories
  - `bc643cb` — test(06-03): flip 5 Phase 6 model RED stubs to GREEN — Pitfall 4 + 11 + UNIQUE composites
- All 5 modified factory files lost their `@phpstan-ignore` annotations and now declare `protected $model = X::class;` with `@extends Factory<X>`.
- All 5 modified test files have no `placeholder` literal (grep returns 0 hits).
- `pest tests/Feature/Models/Tournament*ModelTest.php` → 47 passed, 103 assertions.
- Pint + PHPStan L8 clean on every changed file; full-project PHPStan regression check clean.
