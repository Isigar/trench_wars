---
phase: 10-clan-applications
plan: "02"
subsystem: clan-applications
tags: [service, eligibility-guards, i18n, tdd]
dependency_graph:
  requires:
    - clans.accepts_applications column (plan 10-01)
    - clan_applications_one_pending_per_clan partial unique index (plan 10-01)
    - ClanNotRecruitingException / AlreadyInClanException / DuplicateApplicationException (plan 10-01)
  provides:
    - ClanApplicationService::apply() with 3 ordered eligibility guards
    - clans.applications.applied + apply_* + error.clan_not_recruiting + error.duplicate_application keys
    - clans.form.accepts_applications label + hint
    - bot.errors.clan_not_recruiting / already_in_clan / duplicate_application keys
  affects:
    - apps/web/app/Services/ClanApplicationService.php
    - apps/web/lang/en/clans.php
    - apps/web/lang/en/bot.php
    - apps/web/tests/Feature/Clans/ClanApplyServiceTest.php
tech_stack:
  added: []
  patterns:
    - TDD RED→GREEN idiom (test commit before implementation commit)
    - D-009 active-membership guard idiom (whereNull('left_at') reused from accept())
    - CLAN-03 pending-only duplicate guard (partial unique index as last-line defence T-10-02-02)
    - CLAN-04 accepts_applications boolean toggle guard
    - Phase 4 typed-exception → 422 mapping pattern (DomainException subclasses)
key_files:
  created:
    - apps/web/tests/Feature/Clans/ClanApplyServiceTest.php
  modified:
    - apps/web/app/Services/ClanApplicationService.php
    - apps/web/lang/en/clans.php
    - apps/web/lang/en/bot.php
decisions:
  - "Guard order is fixed: recruiting-toggle → already-in-clan → duplicate-pending; mirrors plan spec and aligns with the STRIDE threat register (T-10-02-01)"
  - "No DB::transaction on apply() — single INSERT (unlike accept() which requires atomic app-update + membership-create); partial unique index handles concurrent duplicate inserts (T-10-02-02)"
  - "AlreadyInClanException in apply() uses applicant.id (not app.applicant_user_id) — method takes a User directly, unlike accept() which reads from the existing ClanApplication row"
  - "clans.applications.error.already_in_clan already existed (accept() error); apply() reuses the same key as the message is semantically equivalent"
metrics:
  duration: "135s"
  completed: "2026-06-04"
  tasks_completed: 2
  files_changed: 4
requirements: [CLAN-01, CLAN-03, CLAN-04]
---

# Phase 10 Plan 02: ClanApplicationService::apply() + i18n Keys Summary

**One-liner:** `ClanApplicationService::apply()` with three ordered eligibility guards (not-recruiting, already-in-clan, duplicate-pending) plus all web/bot i18n keys for the application submission flow.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 (RED) | Failing service test — 6 behaviors | 90efb6b | apps/web/tests/Feature/Clans/ClanApplyServiceTest.php |
| 1 (GREEN) | apply() implementation + pint fix | 56316d8 | apps/web/app/Services/ClanApplicationService.php |
| 2 | i18n keys — clans.php + bot.php | 729cee1 | apps/web/lang/en/clans.php, apps/web/lang/en/bot.php |

## What Was Built

### Task 1 — ClanApplicationService::apply()

Added `apply(Clan $clan, User $applicant, ?string $message = null): ClanApplication` to `ClanApplicationService`. Three guards enforced in strict order:

1. **Guard 1 (CLAN-04):** `if (! $clan->accepts_applications)` → throws `ClanNotRecruitingException(__('clans.applications.error.clan_not_recruiting'))`
2. **Guard 2 (D-009):** `ClanMembership::where('user_id', $applicant->id)->whereNull('left_at')->exists()` → throws `AlreadyInClanException(__('clans.applications.error.already_in_clan'))`
3. **Guard 3 (CLAN-03):** `ClanApplication::where(...)->where('status', 'pending')->exists()` → throws `DuplicateApplicationException(__('clans.applications.error.duplicate_application'))`
4. Happy path: `ClanApplication::create([..., 'status' => 'pending', 'message' => $message])` — LogsActivity fires automatically.

No `DB::transaction` (single INSERT; unlike accept() which needs atomicity).

### Task 1 — ClanApplyServiceTest.php

Six Pest tests covering all six behaviors from the plan:
- Happy path with `null` message (asserts status='pending', message=null, persisted)
- Happy path with message (asserts message stored)
- Guard 1 — `accepts_applications=false` throws `ClanNotRecruitingException`; no row created
- Guard 2 — active ClanMembership in any clan throws `AlreadyInClanException`; no row created
- Guard 3 — prior pending application throws `DuplicateApplicationException`; pending count stays 1
- Declined-then-reapply edge — prior `status='declined'` row does NOT block; new pending row created (proves guard is pending-only, matching the partial unique index WHERE clause)

### Task 2 — i18n Keys

**clans.php `'applications'` block** — new sibling keys added:
- `'applied'` — "Your application has been submitted."
- `'apply_heading'` — "Apply to join"
- `'apply_button'` — "Submit application"
- `'message_placeholder'` — "Add a cover message (optional)…"
- `'not_accepting'` — "This clan is not currently accepting applications."
- `'error.clan_not_recruiting'` — "This clan is not accepting applications."
- `'error.duplicate_application'` — "You already have a pending application to this clan."

**clans.php `'form'` block** — new entry for CLAN-04 toggle:
- `'accepts_applications.label'` — "Accept applications"
- `'accepts_applications.hint'` — "When disabled, new applications to join this clan will be rejected."

**bot.php `'errors'` block** — three new keys:
- `'clan_not_recruiting'`, `'already_in_clan'`, `'duplicate_application'`

## Gate Results

| Gate | Result |
|------|--------|
| `make pest --filter=ClanApplyServiceTest` | PASS (6 passed, 17 assertions) |
| `make phpstan` L8 | PASS (No errors — 420 files) |
| `make pint --test` | PASS (656 files) |
| `make pest --filter=NoHardcodedStrings` | PASS (1 passed) |
| `php -l lang/en/clans.php` | PASS (No syntax errors) |
| `php -l lang/en/bot.php` | PASS (No syntax errors) |

## Deviations from Plan

**[Rule 1 - Style] Pint auto-fix on ClanApplicationService.php**
- **Found during:** Task 1 GREEN phase pint --test gate
- **Issue:** `unary_operator_spaces` + `not_operator_with_space` violations in the new `apply()` method (the `!` spacing around `$clan->accepts_applications`)
- **Fix:** Ran `pint app/Services/ClanApplicationService.php` inside the container; auto-corrected
- **Files modified:** apps/web/app/Services/ClanApplicationService.php
- **Commit:** Included in 56316d8

## Known Stubs

None — this plan ships pure service logic and i18n strings with no UI stubs.

## Threat Flags

No new security surface beyond the plan's threat model:
- T-10-02-01 mitigated: all three guards are enforced inside `apply()`; controllers (plan 10-03) cannot opt out.
- T-10-02-02 mitigated: partial unique index (plan 10-01) is the last-line defence for concurrent duplicate inserts.
- T-10-02-03 accepted: `apply()` takes `User` explicitly; caller (plan 10-03) passes `auth()->user()`.

## Self-Check: PASSED

Files confirmed present:
- apps/web/app/Services/ClanApplicationService.php — FOUND (modified)
- apps/web/tests/Feature/Clans/ClanApplyServiceTest.php — FOUND (created)
- apps/web/lang/en/clans.php — FOUND (modified)
- apps/web/lang/en/bot.php — FOUND (modified)

Commits confirmed:
- 90efb6b — FOUND (RED test)
- 56316d8 — FOUND (GREEN impl)
- 729cee1 — FOUND (i18n keys)
