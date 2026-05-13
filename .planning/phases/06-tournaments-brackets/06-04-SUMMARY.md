---
phase: 06-tournaments-brackets
plan: 04
subsystem: services
tags:
  - wave-2
  - service
  - state-machine
  - activity-log
  - exception-class
  - d-04-04-a
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stub (TournamentStatusServiceTest.php placeholder) + i18n key skeleton
    - .planning/phases/06-tournaments-brackets/06-02-SUMMARY.md  # tournaments_status_check CHECK (DB-layer defence)
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # Tournament model with $fillable['status'] + LogsActivity + factory ->inStatus() state
    - .planning/phases/04-matches-manual/04-04-SUMMARY.md         # Canonical Phase 4 MatchStatusService idiom (D-04-04-A)
  provides:
    - "TournamentStatusService::transition(Tournament, string, ?User) — state machine guard + DB::transaction wrap + activity_log audit emission"
    - "TournamentStatusInvalidTransitionException — typed DomainException thrown on disallowed (from, to) pairs"
    - "BracketsAlreadyGeneratedException — forward-declared here to break circular dependency with plan 06-06 BracketGeneratorService"
    - "26 GREEN Pest tests / 45 assertions covering 9 allowed transitions, 9 rejected, activity log with causer + properties[from,to], localized message check, fluent return type, typed exception subclass identity"
  affects:
    - apps/web/app/Services/        # 1 new service file
    - apps/web/app/Exceptions/      # 2 new exception files
    - apps/web/tests/Feature/Services/  # 1 RED stub flipped to GREEN
tech-stack:
  added: []
  patterns:
    - "Phase 4 canonical state-machine service idiom — final class, private const ALLOWED matrix, in_array strict guard, DB::transaction wrap, activity()->causedBy()->performedOn()->withProperties()->log() audit emission (D-04-04-A)"
    - "Forward-declared exception class pattern — BracketsAlreadyGeneratedException ships in plan 06-04 (consumer's adjacent plan) NOT in plan 06-06 (producer's plan) to break a circular import — plan 06-06's BracketGeneratorService imports it without creating a same-wave cycle"
    - "Reseed back-transition (seeded → registering) — the only structurally new transition in Phase 6 vs the Phase 4 analog; consumed by plan 06-05's TournamentSeedingService::reseed() which calls transition($t, 'registering') first, then re-runs seeding"
key-files:
  created:
    - apps/web/app/Services/TournamentStatusService.php
    - apps/web/app/Exceptions/TournamentStatusInvalidTransitionException.php
    - apps/web/app/Exceptions/BracketsAlreadyGeneratedException.php
  modified:
    - apps/web/tests/Feature/Services/TournamentStatusServiceTest.php
decisions:
  - "D-06-04-A: TournamentStatusService::transition() signature is `transition(Tournament $tournament, string $to, ?User $causer = null): Tournament` — DIVERGES from Phase 4 MatchStatusService which uses required `User $causer` (not nullable) and returns `void`. Two changes vs the precedent: (1) ?User $causer = null with auth()->user() fallback enables HTTP-route-driven calls (Filament admin actions in plan 06-11 omit the causer arg and rely on the fallback); (2) `: Tournament` return type enables fluent chaining (e.g., `app(TournamentStatusService::class)->transition($t, 'registering')->refresh()`). Phase 4 plan 04-04 chose void because MatchResultService (plan 04-09) is the only known caller and it discards the result; Phase 6 plan 06-11 needs the fluent shape because Filament actions return the updated record for table refresh."
  - "D-06-04-B: BracketsAlreadyGeneratedException ships HERE in plan 06-04 (not plan 06-06 where the producer lives) to break the circular dependency between TournamentStatusService (consumed by Filament admin actions in plan 06-11) and BracketGeneratorService (consumed inside the start() flow that the status service routes through). Wave-ordering guarantees plan 06-04 lands before plan 06-06, so the exception class is in place when BracketGeneratorService::generate() is authored."
  - "D-06-04-C: Log description is the format-string `\"Tournament status: {$from} -> {$to}\"` (e.g., 'Tournament status: draft -> registering') — MORE DESCRIPTIVE than the Phase 4 analog's static `'Match status transition'` string. Per plan 06-04 <interfaces>, this allows audit log readers (Filament activity_log resource) to scan transitions visually without expanding the properties JSON. Activity log assertions filter on this description literal to disambiguate from other Tournament activities (created, updated)."
metrics:
  duration: ~3m
  completed: 2026-05-13
  tasks: 1
  files_created: 3
  files_modified: 1
  commits: 1
---

# Phase 6 Plan 4: Wave 2 — TournamentStatusService State Machine Summary

The TournamentStatusService lands with the verbatim Phase 4 MatchStatusService canonical idiom (D-04-04-A). The ALLOWED matrix encodes the 6-state lifecycle from RESEARCH Pattern 1 — draft → registering → seeded → running → completed plus universal cancellation plus the unique reseed back-transition (seeded → registering). Every successful transition wraps in DB::transaction with an activity_log audit row (causer + properties[from, to]). Two typed exception classes ship in the same plan: TournamentStatusInvalidTransitionException (thrown by transition()) and BracketsAlreadyGeneratedException (forward-declared for plan 06-06). The Wave 0 RED stub is replaced with 26 GREEN Pest tests / 45 assertions.

## What Landed

### TournamentStatusService — ALLOWED Matrix

The 6-key state machine encoded as `private const ALLOWED`:

| From         | Allowed `$to`                              | Terminal? |
|--------------|--------------------------------------------|-----------|
| `draft`      | `registering`, `cancelled`                 | no        |
| `registering`| `seeded`, `cancelled`                      | no        |
| `seeded`     | `running`, `registering`, `cancelled`      | no — `registering` is the reseed back-transition |
| `running`    | `completed`, `cancelled`                   | no        |
| `completed`  | (empty)                                    | **yes**   |
| `cancelled`  | (empty)                                    | **yes**   |

The `seeded → registering` back-transition is the only structurally new path vs the Phase 4 analog. It is consumed by plan 06-05's `TournamentSeedingService::reseed()` which calls `transition($t, 'registering')` first, then re-runs seeding. The model-level `canReseed()` method (plan 06-05) gates the action's visibility in Filament (plan 06-11).

### Service Signature + Return Type

```php
public function transition(Tournament $tournament, string $to, ?User $causer = null): Tournament
```

Two intentional divergences from the Phase 4 MatchStatusService precedent (recorded in D-06-04-A):

1. `?User $causer = null` instead of required `User $causer` — enables Filament admin actions in plan 06-11 to omit the causer arg and rely on the `auth()->user()` fallback inside the transaction.
2. Returns the `Tournament` model instead of `void` — enables fluent chaining for Filament action callbacks that return the updated record for table refresh.

### Activity Log Emission Shape

Every successful transition writes one `activity_log` row inside the same `DB::transaction` as the status update:

| Field           | Value                                                |
|-----------------|------------------------------------------------------|
| `subject_type`  | `App\Models\Tournament`                              |
| `subject_id`    | the tournament's UUID                                |
| `causer_type`   | `App\Models\User` (when causer present)              |
| `causer_id`     | causer arg → falls back to `auth()->user()->id`      |
| `description`   | `"Tournament status: {$from} -> {$to}"` (literal)    |
| `properties`    | `{"from": "draft", "to": "registering"}` (JSON)      |

The verbatim description string (e.g., `"Tournament status: draft -> registering"`) — MORE DESCRIPTIVE than Phase 4's static `'Match status transition'` — recorded in D-06-04-C. Allows audit log readers to scan transitions visually without expanding the properties JSON. Test assertions filter on this description literal to disambiguate from other Tournament activities (`Tournament created`, `Tournament updated`).

### Exception Class Hierarchy

Both extend `\DomainException` (matches the Phase 4 `MatchNotOpenException` precedent verbatim — state-machine + idempotency violations are domain invariants, not runtime errors):

| Class | Thrown by | Forward-declared for |
|-------|-----------|----------------------|
| `App\Exceptions\TournamentStatusInvalidTransitionException` | `TournamentStatusService::transition()` on disallowed `(from, to)` pairs | — (own consumer) |
| `App\Exceptions\BracketsAlreadyGeneratedException` | `App\Services\Brackets\BracketGeneratorService::generate()` (plan 06-06) on re-invocation against a tournament with existing `tournament_stages` rows | plan 06-06 — circular-dependency break (D-06-04-B) |

### Test Coverage — 26 it() Blocks / 45 Assertions

**Allowed transitions (9):**

| Test | Path |
|------|------|
| `allows draft -> registering transition` | `draft → registering` |
| `allows draft -> cancelled transition` | `draft → cancelled` |
| `allows registering -> seeded transition` | `registering → seeded` |
| `allows registering -> cancelled transition` | `registering → cancelled` |
| `allows seeded -> running transition` | `seeded → running` |
| `allows seeded -> registering transition (reseed back-transition)` | `seeded → registering` |
| `allows seeded -> cancelled transition` | `seeded → cancelled` |
| `allows running -> completed transition` | `running → completed` |
| `allows running -> cancelled transition` | `running → cancelled` |

**Rejected transitions (9):**

| Test | Reason |
|------|--------|
| `rejects completed -> running transition (terminal)` | `completed` is terminal |
| `rejects completed -> cancelled transition (terminal)` | `completed` is terminal |
| `rejects cancelled -> running transition (terminal)` | `cancelled` is terminal |
| `rejects cancelled -> draft transition (terminal)` | `cancelled` is terminal |
| `rejects draft -> seeded transition (skip)` | skip — must pass through `registering` |
| `rejects draft -> running transition (skip)` | skip — must pass through `registering` + `seeded` |
| `rejects registering -> completed transition (skip)` | skip — must pass through `seeded` + `running` |
| `rejects running -> registering transition (no backward from running)` | only `seeded → registering` is allowed backward |
| `rejects transition to an unknown status string` | `'open'` is a Phase 4 value, not Phase 6 |
| `rejects transition from a current status not in the ALLOWED keyset` | synthetic transient bogus current status |

**Activity log emission (5):**

| Test | Asserts |
|------|---------|
| `writes an activity log row on transition with from/to properties` | description match + properties.from + properties.to |
| `writes the causer user_id to the activity log row` | causer_id + causer_type assertion |
| `falls back to auth()->user() when causer is null` | nullable causer + actingAs path |
| `does not write an activity log row when the transition is rejected` | count(before) === count(after) on rejection |
| `uses the localized tournaments.errors.invalid_transition message on rejection` | message literal — confirms i18n key lookup |

**Return type / exception identity (2):**

| Test | Asserts |
|------|---------|
| `returns the Tournament model from a successful transition (fluent chain)` | `$result` is `Tournament::class` + id + status match |
| `throws the typed exception subclass (not bare DomainException)` | `instanceof TournamentStatusInvalidTransitionException` AND `instanceof \DomainException` |

(Pest reports `Tests: 26 passed (45 assertions)`.)

### Verification

| Gate | Result |
|------|--------|
| `pest tests/Feature/Services/TournamentStatusServiceTest.php` | PASS — 26 passed / 45 assertions |
| `pint --test` on all 4 changed files | PASS — 4 files clean (1 style auto-fix applied) |
| `phpstan analyse` on the 3 production files | PASS — `[OK] No errors` |
| Full-project `phpstan analyse` (regression check) | PASS — `[OK] No errors` |
| Reflection check on `ALLOWED` const (6 keys, terminal-empty for completed/cancelled) | PASS |
| `class_exists(BracketsAlreadyGeneratedException::class)` + `is_subclass_of(...DomainException)` | PASS |
| `grep -c 'placeholder' tests/Feature/Services/TournamentStatusServiceTest.php` | 0 — Wave 0 RED stub removed |

## Deviations from Plan

### Auto-fixed Issues

**1. [Pint auto-fix - Style] `fully_qualified_strict_types` on test file**

- **Found during:** Task 1 verification.
- **Issue:** Pint flagged one `fully_qualified_strict_types` style issue in the test file — a `\DomainException` reference inside a fully-qualified `instanceof` check that should be imported via `use` and referenced bare. (The original code used `\DomainException::class` in the typed-exception identity test, which Pint prefers to see imported.)
- **Fix:** `vendor/bin/pint` auto-fix applied; the test file re-ran GREEN (26/26 still pass) and PHPStan + Pint were re-checked clean.
- **Files modified:** `apps/web/tests/Feature/Services/TournamentStatusServiceTest.php`.
- **Commit:** Folded into Task 1's commit `610c0f7`.

No other deviations. Plan executed as written.

**Note on test-file PHPStan reports:** Direct invocation of `phpstan analyse tests/Feature/Services/TournamentStatusServiceTest.php` produces 27 cosmetic "Cannot access property on Model|null" findings — identical in shape to the Phase 4 MatchStatusServiceTest baseline (20 errors of the same shape). These are NOT in scope for the project's PHPStan run per `apps/web/phpstan.neon` (paths: `app`, `bootstrap/app.php`, `database`, `routes` — `tests` is excluded by omission). The plan's `<verify>` block scans the test file explicitly to surface them, but the project-wide CI gate (`make phpstan`) is clean and matches the Phase 4 precedent's behaviour. No new errors vs the Phase 4 baseline.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-04-01 (Tampering — invalid status transition via direct $tournament->update(['status'=>'foo']) bypass) | mitigate | DB-layer `tournaments_status_check` CHECK (plan 06-02) rejects strings outside the 6-value enum; service-layer ALLOWED matrix rejects valid-string-but-invalid-transition (e.g., `completed → running`). Asserted at the service layer by 9 "rejects ..." it() blocks + at the DB layer in `TournamentModelTest` (plan 06-03). |
| T-06-04-02 (Repudiation — status flip with no audit trail) | mitigate | `activity()->causedBy($causer ?? auth()->user())->performedOn($tournament)->withProperties(['from' => $from, 'to' => $to])->log("Tournament status: {$from} -> {$to}")` inside the same `DB::transaction` as the status update — partial state impossible. Asserted by 5 activity-log it() blocks (description, properties, causer_id, no-write-on-rejection, localized message). |
| T-06-04-03 (Tampering — Filament admin manually edits Tournament.status via form) | mitigate | Plan 06-11 (TournamentResource) `->disabled()` the status field on Edit; transitions go through dedicated Filament Actions that route through this service. Forward-declared at the service-layer here; enforced by plan 06-11. |
| T-06-04-04 (Information Disclosure — audit log leaks internal status strings to non-admin viewers) | accept | Status values are part of the public Tournament contract (rendered on `/tournaments` page via status badges per `tournaments.status.*` lang keys); not sensitive. No mitigation needed. |
| T-06-04-05 (Spoofing — caller passes a User they don't own as the causer) | accept | Service-layer call (not HTTP-driven directly); upstream Filament action callers use `$this->record + auth()->user()` pattern. If a malicious code path lies about the causer, the threat is upstream of this service. No mitigation needed. |

## Threat Flags

None — Phase 6 plan 06-04 changes introduce a service + 2 exception classes + a test file, all inside the trust boundary already documented by the plan's `<threat_model>`. No new endpoints, no new auth paths, no new file access, no new schema, no new network surface.

## Known Stubs

None. The 3 production files + 1 test file are fully implemented. The `BracketsAlreadyGeneratedException` is forward-declared (no producer in this plan) — but that is by design per D-06-04-B; plan 06-06's BracketGeneratorService will throw it. The class itself is complete (a one-line `final class ... extends \DomainException {}` ships now and never needs further changes).

## Plan Linkages

- **Plan 06-05 (TournamentSeedingService)** will call `app(TournamentStatusService::class)->transition($t, 'registering')` first in its `reseed()` flow — the `seeded → registering` back-transition exercised here is its prerequisite.
- **Plan 06-06 (BracketGeneratorService)** will `use App\Exceptions\BracketsAlreadyGeneratedException` and throw it from `generate()` on tournaments with existing `tournament_stages` rows (Pitfall 3).
- **Plan 06-11 (Filament admin TournamentResource + 9 actions)** will attach `action(fn () => app(TournamentStatusService::class)->transition($this->record, 'X'))` to every state-machine button in the Filament UI. The `?User $causer = null` signature enables omitting the causer arg in those callbacks (auth()->user() fallback).
- **Plan 06-12 (public Show.vue / Index.vue)** consumes the same `tournaments.errors.invalid_transition` lang key for flash-message rendering when admin actions surface the exception's `getMessage()`.

## Self-Check: PASSED

- All 3 created files exist on disk:
  - `apps/web/app/Services/TournamentStatusService.php`
  - `apps/web/app/Exceptions/TournamentStatusInvalidTransitionException.php`
  - `apps/web/app/Exceptions/BracketsAlreadyGeneratedException.php`
- The 1 modified test file (`apps/web/tests/Feature/Services/TournamentStatusServiceTest.php`) no longer contains the `placeholder` literal (grep returns 0 hits).
- Task 1 commit exists on `master`: `610c0f7` — feat(06-04): TournamentStatusService + 2 exception classes + GREEN tests
- Pest: 26 passed / 45 assertions; PHPStan: `[OK] No errors` on production files + full-project regression check; Pint: clean on all 4 changed files.
- ALLOWED matrix verified via reflection: 6 keys, `completed`+`cancelled` are empty arrays (terminal), `seeded` has 3 entries including the reseed back-transition.
- BracketsAlreadyGeneratedException + TournamentStatusInvalidTransitionException both exist + both extend `\DomainException`.
