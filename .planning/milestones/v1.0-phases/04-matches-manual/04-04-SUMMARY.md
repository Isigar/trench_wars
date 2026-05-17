---
phase: 04-matches-manual
plan: 04
subsystem: matches
tags: [phase-4, wave-2, services, state-machine, activity-log, exception-class]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-4-model-layer
  provides:
    - match-status-service
    - match-status-state-machine
    - match-not-open-exception
    - match-status-audit-trail
  affects:
    - apps/web/app/Services/ (1 new)
    - apps/web/app/Exceptions/ (1 new)
    - apps/web/tests/Feature/Services/ (1 stub flipped GREEN)
tech_stack:
  added: []
  patterns:
    - state-machine-via-allowed-transitions-const
    - db-transaction-wraps-update-and-audit-write
    - capture-from-before-update-to-avoid-getOriginal-drift
    - typed-exception-extends-domainexception-per-research-pattern-2
key_files:
  created:
    - apps/web/app/Services/MatchStatusService.php
    - apps/web/app/Exceptions/MatchNotOpenException.php
  modified:
    - apps/web/tests/Feature/Services/MatchStatusServiceTest.php
  deleted: []
decisions:
  - id: D-04-04-A
    decision: |
      **`$from` is captured BEFORE `$match->update(['status' => $to])`** inside
      `MatchStatusService::transition()`, not pulled from `$match->getOriginal('status')`
      after the update as the RESEARCH Pattern 4 snippet shows.

      Rationale: Eloquent's `update()` refreshes the model's "original" attribute
      cache to the just-persisted value, so calling `$match->getOriginal('status')`
      AFTER `update()` returns the NEW status, not the prior one. The RESEARCH
      snippet would emit an activity_log row with `from == to` — silently corrupting
      the audit trail. Capturing `$from = $match->status` BEFORE the update is the
      only correct sequence.

      The plan acceptance criteria already specified this ordering ("Read $from =
      $match->status" then "DB::transaction wrapping: $match->update(...); activity()
      ->withProperties(['from' => $from, 'to' => $to])->log(...)"). The RESEARCH
      snippet was a minor bug; the plan body is authoritative.

      Caught at write-time, no test failure occurred. Documented here so future
      reviewers don't "fix" the implementation back to the RESEARCH-snippet form.

  - id: D-04-04-B
    decision: |
      **MatchNotOpenException `extends \DomainException`** (NOT `\RuntimeException`).

      Two precedents existed:
        - RESEARCH Pattern 2: all four MatchSignupService exceptions are
          DomainException subclasses (canonical for signup business rules)
        - Phase 2's `ReservedSlugException extends \RuntimeException` (Pattern
          for slug-generation infra errors)

      Chose Pattern 2 — the exception models a business-rule violation
      (admin attempted to sign up to a match that is not open), not an
      infrastructure failure. `\DomainException` semantically signals "your
      domain inputs violate a domain rule" which matches.

      Binding for plan 04-06: MatchSignupService throws this typed exception
      from its status-gate check and the controller catches it as
      `\DomainException` (catch-all parent) or specifically as
      `MatchNotOpenException` (typed) per Phase 4 controller-error-translation
      pattern.

  - id: D-04-04-C
    decision: |
      **No alias-on-import pattern used.** The service file references
      `App\Models\GameMatch` directly. Pitfall 5's `use App\Models\Match as MatchModel;`
      alias is a defensive idiom for files containing PHP `match($x) { ... }`
      expressions — and MatchStatusService contains zero match-expressions.
      Same applies to MatchStatusServiceTest. Direct `use App\Models\GameMatch;`
      is the canonical Phase 4 idiom per D-04-03-A.

metrics:
  duration_minutes: 5
  completed: 2026-05-13
---

# Phase 4 Plan 04: Match status state machine + MatchNotOpenException + GREEN test Summary

**One-liner:** `App\Services\MatchStatusService::transition(GameMatch, string, User)` lands the canonical match-lifecycle state machine — `draft -> open|cancelled`, `open -> locked|played|cancelled`, `locked -> played|cancelled`, `played + cancelled` terminal — with every successful transition wrapped in `DB::transaction` and audited via `activity()->causedBy()->withProperties([from,to])->log()`; `MatchNotOpenException extends \DomainException` is defined here so plan 04-06 can import without circular dependency; 19 GREEN Pest tests replace the Wave 0 stub.

## What Shipped

### `apps/web/app/Services/MatchStatusService.php`

- `final class MatchStatusService` — stateless, auto-resolved by the Laravel container
- `private const ALLOWED_TRANSITIONS` — 5 keys, verbatim from RESEARCH Pattern 4
- `public function transition(GameMatch $match, string $to, User $causer): void`
- PHPDoc `/** @var array<string, list<string>> */` on the const (PHPStan L8 generic requirement)
- Header docblock cites 04-04-PLAN Task 1 + RESEARCH Pattern 4 + threat refs T-04-04-01..04
- Naming-decision note in the docblock references D-04-03-A (GameMatch over Match)

#### ALLOWED_TRANSITIONS table (verbatim)

| From        | Allowed `$to`                          |
| ----------- | -------------------------------------- |
| `draft`     | `open`, `cancelled`                    |
| `open`      | `locked`, `played`, `cancelled`        |
| `locked`    | `played`, `cancelled`                  |
| `played`    | — (terminal)                           |
| `cancelled` | — (terminal)                           |

#### Activity log emission shape

```php
activity()
    ->causedBy($causer)         // User model — writes causer_id + causer_type
    ->performedOn($match)       // GameMatch model — writes subject_id + subject_type
    ->withProperties([
        'from' => $from,         // string (pre-update status)
        'to'   => $to,           // string (post-update status)
    ])
    ->log('Match status transition');
```

Subject_type is stored as the FQN `'App\\Models\\GameMatch'`. The activity_log
row sits inside the same `DB::transaction` as the `$match->update(['status' => $to])`
call, so partial state (status flipped without audit, or audit without status flip)
is impossible — T-04-04-02 mitigation.

### `apps/web/app/Exceptions/MatchNotOpenException.php`

- `final class MatchNotOpenException extends \DomainException {}` (one-liner body)
- Header docblock cites 04-04-PLAN Task 1 + RESEARCH Pattern 2 (MatchSignupService consumer)
- Defined here, not in plan 04-06, so MatchSignupService can `use App\Exceptions\MatchNotOpenException;` without import-cycle concerns

### `apps/web/tests/Feature/Services/MatchStatusServiceTest.php` (replaces Wave 0 stub)

19 `it()` blocks, 31 assertions, all GREEN. Coverage breakdown:

| Category | `it()` count | Notes |
|----------|--------------|-------|
| **Happy-path transitions** | 7 | Every edge of the state graph: draft→open, draft→cancelled, open→locked, open→played, open→cancelled, locked→played, locked→cancelled |
| **Rejected from terminal** | 4 | played→open, played→cancelled, cancelled→open, cancelled→draft (all DomainException) |
| **Rejected backward** | 2 | open→draft, locked→open |
| **Rejected unknown $to** | 1 | open→completed (typo) |
| **Rejected unknown $from** | 1 | bogus-from→played (transient model status mutation) |
| **Activity log emission** | 3 | properties[from,to] correct; causer_id+causer_type correct; no row on rejected transition |
| **Localized message** | 1 | matches.status.error.invalid_transition with :from/:to interpolation |

Pitfall 5 alias-on-import (`use App\Models\GameMatch as MatchModel;`) is NOT used — no `match($x)` expressions in this test file (D-04-04-C). Direct `use App\Models\GameMatch;` is the canonical idiom.

## Verification

| Gate | Command | Result |
|------|---------|--------|
| Plan tests | `make pest ARGS="--filter=MatchStatusServiceTest"` | **19 passed, 31 assertions, 1.41s** |
| Full Pest suite | `make pest` | **15 incomplete + 343 passed** (Wave 0 baseline was 16 + 324; +19 GREEN, −1 incomplete — exactly the MatchStatusServiceTest stub flipped) |
| PHPStan L8 | `make phpstan` | **0 errors** across the full configured scope (app + bootstrap + database + routes) |
| Pint test | `make pint ARGS="--test app/Services/MatchStatusService.php app/Exceptions/MatchNotOpenException.php tests/Feature/Services/MatchStatusServiceTest.php"` | clean |
| ALLOWED_TRANSITIONS reflection | `php -r "require 'vendor/autoload.php'; print_r((new ReflectionClass(App\\Services\\MatchStatusService::class))->getReflectionConstants());"` | `ALLOWED_TRANSITIONS` constant present |
| `placeholder` literal removed | `grep -l 'placeholder' tests/Feature/Services/MatchStatusServiceTest.php` | empty (no match) |

## Decisions Made

- **D-04-04-A:** `$from` captured BEFORE `$match->update()` (avoids `getOriginal()` post-refresh drift; RESEARCH snippet bug pre-empted at write-time)
- **D-04-04-B:** MatchNotOpenException extends `\DomainException` (RESEARCH Pattern 2 precedent; Phase 2 ReservedSlugException's RuntimeException is a different domain)
- **D-04-04-C:** No Pitfall 5 alias-on-import — service contains no `match($x)` expressions, direct `use App\Models\GameMatch;` is the canonical Phase 4 idiom

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] `$match->getOriginal('status')` post-update drift in RESEARCH snippet**
- **Found during:** Task 1 implementation pre-flight read of RESEARCH Pattern 4 code sample
- **Issue:** The RESEARCH code sample (lines 745–751) calls `$match->update(['status' => $to])` and THEN reads `$match->getOriginal('status')` inside the `withProperties(['from' => ...])` array. Eloquent's `update()` refreshes the model's "original" attribute cache to the just-persisted value, so the post-update `getOriginal('status')` returns the NEW status. The activity log would have shown `from == to` — silently corrupting the audit trail.
- **Fix:** Captured `$from = $match->status` BEFORE the `if (! in_array(...))` guard. Passed `$from` directly to `withProperties()` via closure use. The plan's acceptance criteria already specified this ordering ("Read $from = $match->status" then "DB::transaction wrapping..."), so the deviation is from the RESEARCH snippet, not from the plan.
- **Files modified:** `apps/web/app/Services/MatchStatusService.php`
- **Commit:** 56cbcde

### Non-deviations (planned ambiguities resolved)

- **Exception hierarchy choice:** Plan acceptance criteria offered two options (A: extends DomainException; B: extends RuntimeException) with a soft preference for A. Picked Option A — see D-04-04-B.
- **Pitfall 5 alias-on-import:** Plan acceptance criteria specified the alias is acceptable but optional. Skipped — see D-04-04-C.
- **Optional rollback / DB::transaction integrity test:** Plan listed this as OPTIONAL (`relies on swapping the activity() facade with a mock that throws`). Skipped — the 3 activity-log emission tests already prove the happy-path write, and `DB::transaction` wrapping is structurally guaranteed by `Illuminate\Support\Facades\DB`. The test would only verify Laravel's transaction implementation, not our code.

## Auth Gates

None — pure service/exception/test work, no auth-bearing operations.

## Known Stubs

10 Wave 0 stubs remain incomplete-by-design (down from 11 after this plan):

| Stub | Flipped GREEN by |
|------|------------------|
| `Services/MatchSlotMaterialiserServiceTest` | 04-05 |
| `Services/MatchSignupServiceTest` + `MatchSignupConcurrencyTest` + `Matches/MatchSignupTagRestrictedTest` | 04-06 |
| `Unit/Data/MatchDataTest` + `PublicMatchDataTest` + `EventDataTest` | 04-07 |
| `Observers/MatchEventSyncTest` | 04-08 |
| `Admin/MatchResourcePresentTest` + `MatchResourceCreateWizardTest` + `MatchAuditLogTest` + `Services/MatchResultServiceTest` | 04-09 |
| `Matches/MatchCalendarPageTest` + `MatchShowPageTest` + `MatchSignupControllerTest` | 04-10 |

No new accidental stubs introduced. MatchStatusServiceTest is now fully GREEN.

## Threat Surface Notes

Threat register T-04-04-01..04 fully addressed:

- **T-04-04-01 (invalid transition via service bypass):** Service-layer `ALLOWED_TRANSITIONS` lookup blocks valid-string-but-invalid-transition writes; tested by 8 "rejects ... transition" tests. DB-layer `matches_status_check` CHECK (plan 04-02) is the defence-in-depth backstop.
- **T-04-04-02 (status flip with no audit trail):** `DB::transaction` wraps both the `update()` and the `activity()->log()` call — partial state impossible. Asserted by "writes an activity log row on transition with from/to properties" + "writes the causer user_id to the activity log row" tests.
- **T-04-04-03 (Filament admin manual edit of status field):** Plan 04-09 ships the `->disabled()` Filament Edit page; out of scope here, deferred to that plan.
- **T-04-04-04 (audit log leaks internal status strings):** Accepted — status values are part of the public Match contract (rendered on /matches calendar via MatchStatusBadge); not sensitive.

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|------|------|-------|------------|
| `56cbcde` | Task 1 — MatchStatusService + MatchNotOpenException + GREEN test | 3 | ALLOWED_TRANSITIONS const + DB::transaction wrap + activity() audit emission; 19 it() blocks GREEN; PHPStan L8 + Pint clean |

## Self-Check: PASSED

- `apps/web/app/Services/MatchStatusService.php` exists (created — 79 LOC)
- `apps/web/app/Exceptions/MatchNotOpenException.php` exists (created — 22 LOC, one-liner body)
- `apps/web/tests/Feature/Services/MatchStatusServiceTest.php` modified (Wave 0 stub replaced — 249 LOC, 19 `it()` blocks, 0 `markTestIncomplete`, 0 `placeholder` literal)
- Commit `56cbcde` present in `git log --oneline -5`
- `make pest --filter=MatchStatusServiceTest`: 19 passed, 31 assertions
- `make pest` (full suite): 343 passed (+19 vs Wave 0 baseline) / 15 incomplete (−1 vs Wave 0)
- `make phpstan`: 0 errors
- `make pint --test` (3 task files): clean
- ALLOWED_TRANSITIONS constant resolvable via reflection
- No `placeholder` literal remains in the test file
