---
phase: 10-clan-applications
plan: "01"
subsystem: clan-applications
tags: [migration, model, exceptions, schema, domain]
dependency_graph:
  requires: []
  provides:
    - clans.accepts_applications column (CLAN-04 boolean recruiting toggle)
    - clan_applications_one_pending_per_clan partial unique index (CLAN-03 DB-layer guard)
    - Clan model accepts_applications fillable + boolean cast
    - ClanNotRecruitingException (maps to bot error code clan_not_recruiting)
    - AlreadyInClanException (maps to bot error code already_in_clan)
    - DuplicateApplicationException (maps to bot error code duplicate_application)
  affects:
    - apps/web/app/Models/Clan.php
    - apps/web/app/Services/ClanApplicationService.php (plan 10-02 consumer)
    - apps/web/app/Http/Controllers/BotApi/BotApiClanApplicationController.php (plan 10-03 consumer)
tech_stack:
  added: []
  patterns:
    - D-009 partial-unique index idiom (raw DB::statement CREATE UNIQUE INDEX ... WHERE)
    - Phase 4 typed-exception → 422 mapping pattern (DomainException subclasses per failure mode)
    - Eloquent protected casts() method form (returns array<string, string>)
key_files:
  created:
    - apps/web/database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php
    - apps/web/app/Exceptions/ClanNotRecruitingException.php
    - apps/web/app/Exceptions/AlreadyInClanException.php
    - apps/web/app/Exceptions/DuplicateApplicationException.php
  modified:
    - apps/web/app/Models/Clan.php
decisions:
  - "accepts_applications defaults to true — existing clans accept applications; leaders opt OUT (CONTEXT.md decision 1)"
  - "Partial unique index on clan_applications WHERE status='pending' enforces CLAN-03 at DB layer, mirroring D-009 idiom"
  - "Three typed DomainException subclasses chosen over a single generic exception to enable distinct bot error codes per plan 10-03"
  - "casts() protected method form used (not $casts property) per modern Laravel 11+ idiom"
metrics:
  duration: "95s"
  completed: "2026-06-04"
  tasks_completed: 2
  files_changed: 5
requirements: [CLAN-03, CLAN-04]
---

# Phase 10 Plan 01: Schema + Exceptions Foundation Summary

**One-liner:** `clans.accepts_applications` boolean column + `clan_applications_one_pending_per_clan` partial unique index + Clan boolean cast + 3 typed DomainException subclasses for the application submission guards.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Migration: accepts_applications column + pending-per-clan partial unique index | b4c348a | apps/web/database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php |
| 2 | Clan model: fillable + boolean cast; 3 typed exceptions | 34065eb | apps/web/app/Models/Clan.php, ClanNotRecruitingException.php, AlreadyInClanException.php, DuplicateApplicationException.php |

## What Was Built

### Task 1 — Migration
Created `2026_06_04_100000_add_accepts_applications_to_clans.php` with:
- `clans.accepts_applications` (boolean, default `true`) — CLAN-04 recruiting toggle. Default `true` means existing clans accept applications without any operator action; leaders opt out.
- `clan_applications_one_pending_per_clan` partial unique index on `clan_applications (applicant_user_id, clan_id) WHERE status = 'pending'` — CLAN-03 DB-layer last-line defence behind the service guard (plan 10-02). Uses raw `DB::statement` (D-009 idiom; `Schema::unique()` cannot express `WHERE`).
- `down()` drops in reverse order — index first, then column.

### Task 2 — Clan model + exceptions
- `Clan::$fillable` extended with `'accepts_applications'`.
- `Clan::casts()` protected method added, returning `['accepts_applications' => 'boolean']`.
- Three typed exception classes created in `app/Exceptions/`, each a one-line-body `final class X extends \DomainException {}` with a doc-comment naming the failure mode and bot error code:
  - `ClanNotRecruitingException` → `clan_not_recruiting`
  - `AlreadyInClanException` → `already_in_clan`
  - `DuplicateApplicationException` → `duplicate_application`

## Gate Results

| Gate | Result |
|------|--------|
| `make artisan ARGS="migrate"` | PASS (20ms) |
| `make artisan ARGS="migrate:fresh --seed"` | PASS (full schema rebuild + all seeders) |
| `make phpstan` | PASS (No errors — 420 files) |
| `make pint ARGS="--test"` | PASS (655 files) |

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — this plan ships pure schema + model + exception primitives with no UI or data-flow stubs.

## Threat Flags

No new security surface beyond what the plan's threat model covers:
- T-10-01-01 mitigated: `clan_applications_one_pending_per_clan` partial unique index is present.
- T-10-01-02 accepted for this plan: `accepts_applications` is in `$fillable` here; write access is gated by `ClanPolicy::update` in plan 10-04 (`UpdateClanProfileRequest::authorize()`).

## Self-Check: PASSED

Files confirmed present:
- apps/web/database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php — FOUND
- apps/web/app/Models/Clan.php — FOUND (modified)
- apps/web/app/Exceptions/ClanNotRecruitingException.php — FOUND
- apps/web/app/Exceptions/AlreadyInClanException.php — FOUND
- apps/web/app/Exceptions/DuplicateApplicationException.php — FOUND

Commits confirmed:
- b4c348a — FOUND
- 34065eb — FOUND
