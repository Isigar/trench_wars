# Phase 4 — Matches (manual) — Verification Report

**Date:** 2026-05-13
**Phase status:** PENDING_MANUAL_SMOKE (automated gates: PASS — see Manual smoke section)

---

## Phase metadata

| Property | Value |
|----------|-------|
| Phase | 4 |
| Name | Matches (manual) |
| Slug | matches-manual |
| Plans | 13 plans (04-01 through 04-13) |
| Completed date | 2026-05-13 |
| Phase 3 foundation | Phase 3 COMPLETE (2026-05-13) |
| Canonical model name | `App\Models\GameMatch` (D-04-03-A LOCKED — supersedes earlier `Match` working name; `match` is a PHP 8.x reserved keyword for the `match` expression so the class is `GameMatch` while the underlying table remains `matches` via `protected $table` override) |

---

## [BLOCKING] Quality gates — RESULT: PASS

| Gate | Command | Result |
|------|---------|--------|
| Pest (full suite) | `docker compose exec web ./vendor/bin/pest --no-coverage` | **493 passed** (1459 assertions), 0 failed, 0 incomplete, 22.99s |
| Pint | `docker compose exec web ./vendor/bin/pint --test` | **PASS** — 295 files clean |
| PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | **[OK] No errors** |
| vue-tsc (web) | `docker compose exec web /app/node_modules/.bin/vue-tsc --noEmit` | **PASS** — 0 errors |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | **PASS** — clean |
| Placeholder Wave-0 stubs | `docker compose exec web ./vendor/bin/pest --no-coverage` (0 incomplete) | **PASS** — 0 incomplete tests in entire suite |
| NoHardcodedStringsTest | included in Pest 493 above | **PASS** |
| migrate:fresh + seed | `docker compose exec web php artisan migrate:fresh --seed --force` | **PASS** — all migrations + 4 seeders ran clean |

**Test growth across phases:**

| Phase | Total tests after phase | Phase contribution |
|-------|--------------------------|--------------------|
| Phase 1 close (01-18) | ~94 tests | +94 |
| Phase 2 close (02-14) | 214 tests | +120 |
| Phase 3 close (03-10) | 278 tests | +64 |
| Phase 4 close (04-13) | **493 tests** | **+215** |

Phase 4 contributed 215 tests / 637 assertions — counted via filtered Pest run on Phase-4-named classes:
`MatchModel|MatchSlotModel|MatchAccessRuleModel|MatchResultModel|MatchMvpModel|EventModel|MatchStatusService|MatchSlotMaterialiser|MatchSignup|MatchResultService|MatchData|EventData|PublicMatchData|MatchObserver|MatchResource|MatchAuditLog|MatchCalendar|MatchShow|MatchSignupController|MatchSignupTagRestricted|MatchEventSync`.

---

## ROADMAP Success Criteria mapping

| SC | Description (verbatim from ROADMAP) | Test file(s) | Pest filter | Status |
|----|-------------------------------------|--------------|-------------|--------|
| SC-1 | A clan officer/leader can create a match by choosing a game match type; slots are materialised from `GameMatchTypeRoleLimit` and signups open automatically. | `tests/Feature/Admin/MatchResourceCreateWizardTest.php` (Filament wizard end-to-end), `tests/Feature/Services/MatchSlotMaterialiserServiceTest.php` (snapshot semantics — 50 slots for Scrim 50v50, 6 for Skirmish 6v6, sort_order preservation, snapshot-at-create proof), `tests/Feature/Services/MatchStatusServiceTest.php` (`draft → open` transition opens signups) | `--filter='MatchResourceCreateWizard\|MatchSlotMaterialiser\|MatchStatusService'` | **PASS** |
| SC-2 | A logged-in player can sign up to a specific role slot, and the live count of confirmed signups can never exceed slot capacity (enforced by DB transaction with row lock). | `tests/Feature/Services/MatchSignupServiceTest.php` (5-guard order, capacity exceeded throws, idempotent re-signup, lockForUpdate parent-row strategy — D-04-06-A/B), `tests/Feature/Services/MatchSignupConcurrencyTest.php` (`pcntl_fork`-driven concurrent race: 50 forks racing on last slot; exactly one wins — D-04-06-E manual commit + truncate workaround for `RefreshDatabase` fork-safety) | `--filter='MatchSignupService\|MatchSignupConcurrency'` | **PASS** (pcntl present per D-04-01-C; concurrency test executes the fork path, not the dual-connection fallback) |
| SC-3 | A public visitor can view the match calendar at `/matches` and any match detail page at `/matches/{id}` with slot availability rendered. | `tests/Feature/Matches/MatchCalendarPageTest.php` (calendar status filters; private matches hidden; pagination; tag-eligible visibility), `tests/Feature/Matches/MatchShowPageTest.php` (detail page renders; private hidden; slot status/availability), `tests/Feature/Matches/MatchSignupControllerTest.php` (POST/DELETE /matches/{id}/signups — happy path + capacity exceeded → 422 game_role_id key + status guard + idempotency + tag-restricted 422 general + guest redirect — D-04-10-A/E) | `--filter='MatchCalendarPage\|MatchShowPage\|MatchSignupController'` | **PASS** |
| SC-4 | An organiser/admin can enter or override a match result (winner, scores, MVPs) in Filament and the change is audited. | `tests/Feature/Services/MatchResultServiceTest.php` (`upsert` atomically writes MatchResult + MVPs + flips status to `played`; terminal-state SKIP — D-04-09-C — supports re-edits without re-firing transition), `tests/Feature/Admin/MatchAuditLogTest.php` (12 it() blocks proving D-012 across all 6 Phase 4 models + 2 services; LogsActivity rows for MatchResult/MatchMvp create; `withProperties()` from/to on status transition — D-04-12-A) | `--filter='MatchResultService\|MatchAuditLog'` | **PASS** |
| SC-5 | Tag-restricted matches reject signups from clans whose tags are not in `match_access_rules`, and creating a public match auto-creates a kept-in-sync `Event` row. | `tests/Feature/Matches/MatchSignupTagRestrictedTest.php` (player without eligible clan tag → `TagRestrictedException` → 422 general; empty access rules → all clans permitted, per D-04-06-C "Empty rules = open"), `tests/Feature/Observers/MatchEventSyncTest.php` (MatchObserver fires on save: `is_public=true` creates Event; cascade delete; status='cancelled' soft-deletes Event; observer registered on GameMatch::booted() — D-04-08-A/B) | `--filter='MatchSignupTagRestricted\|MatchEventSync'` | **PASS** |

**SC verification commands:**

```bash
# SC-1: Filament wizard end-to-end + slot materialiser snapshot semantics
docker compose exec web ./vendor/bin/pest --filter='MatchResourceCreateWizard|MatchSlotMaterialiser|MatchStatusService' --no-coverage

# SC-2: D-010 row-locked capacity enforcement + pcntl-driven concurrency race
docker compose exec web ./vendor/bin/pest --filter='MatchSignupService|MatchSignupConcurrency' --no-coverage

# SC-3: Public calendar + show + signup controller
docker compose exec web ./vendor/bin/pest --filter='MatchCalendarPage|MatchShowPage|MatchSignupController' --no-coverage

# SC-4: Manual result entry + audit log integration
docker compose exec web ./vendor/bin/pest --filter='MatchResultService|MatchAuditLog' --no-coverage

# SC-5: Tag-restricted access rules + observer-synced events
docker compose exec web ./vendor/bin/pest --filter='MatchSignupTagRestricted|MatchEventSync' --no-coverage
```

---

## Requirements traceability

| Requirement | Description | Test file(s) | Status |
|-------------|-------------|--------------|--------|
| REQ-goal-match-workflows | A match can be created, slot-templated, signed up to, and scheduled without leaving the platform's structured surfaces. Replaces ad-hoc Discord scheduling. (D-010) | All 5 SCs above — collectively prove the requirement is satisfied: SC-1 (creation + materialisation), SC-2 (capacity enforcement), SC-3 (public surface), SC-4 (manual result entry + audit), SC-5 (tag-restricted access + auto-Event). The full 215-test Phase 4 suite plus the cross-phase 493-test total Pest run proves the requirement landed without breaking prior phases. | **PASS** |

REQ-goal-match-workflows is the single requirement mapped to Phase 4 in `REQUIREMENTS.md`. All 5 success criteria collectively prove this requirement is satisfied:
- D-010 row-locked capacity contract (no oversubscription under concurrency) → SC-2
- Structured match-creation workflow replacing Discord scheduling → SC-1 + SC-3 + SC-4
- Tag-restricted clan access (matches m:n match_access_rules) → SC-5
- Audit integration (D-012) on every domain mutation → SC-4 + SC-5

---

## Locked Decisions Honored

### Project-level decisions (PROJECT.md D-### table)

| Decision | Honored | Evidence |
|----------|---------|----------|
| **D-010** Match signups by role slot; capacity row-locked | YES | `MatchSignupService::signup()` wraps the 5-guard sequence in `DB::transaction()` with `lockForUpdate()` on the parent GameMatch row (single serialisation point per match — D-04-06-A/B); proven under `pcntl_fork` concurrent contention in `MatchSignupConcurrencyTest`; capacity check via `where('game_role_id', ...)->count() < limit` inside the locked transaction. |
| **D-012** Filament + spatie/activitylog audit infra | YES | All 6 Phase 4 models (`GameMatch`, `MatchSlot`, `MatchAccessRule`, `MatchResult`, `MatchMvp`, `Event`) use the `LogsActivity` trait; `MatchStatusService::transition()` writes explicit `activity()->withProperties(['from','to'])->log(...)`; `MatchAuditLogTest` covers both paths with 12 it() blocks; `MatchResource` ships `Audit` tab + global `/admin/audit` page integration. |
| **D-013** i18n plumbed; EN at launch; every UI string via `__()` / `t()` | YES | `apps/web/lang/en/matches.php` shipped in plan 04-01 + audited end-to-end in plan 04-12 (88 unique Phase 4 keys cross-referenced); `apps/web/lang/en/admin.php` extended with the `match`, `match_access_rule`, `match_result`, `match_mvp`, `event` namespaces; `NoHardcodedStringsTest` GREEN across full suite. |
| **D-018** Per-section + global tier player privacy | YES | `PublicMatchOccupantData` collapses to `null` for slots where the occupying user's privacy tier excludes a public viewer; Vue `Matches/Show.vue` branches on `!== null` (D-04-11-A); audited indirectly via `MatchShowPageTest`. |

### Phase-4-specific architectural choices (codified in 04-CONTEXT.md assumptions A1–A5 + 04-RESEARCH.md)

| Choice | Codified as | Honored | Evidence |
|--------|-------------|---------|----------|
| Match class name is `GameMatch` (singular `Match` is the PHP 8 `match` reserved keyword in `match($x)` expressions) | A4 / D-04-01-B → superseded by **D-04-03-A** LOCKED | YES | `App\Models\GameMatch` is the canonical FQN; `protected $table = 'matches'` keeps the SQL table unchanged; `BelongsTo<GameMatch, $this>` relationships pass `match_id` as the explicit FK arg (D-04-03-B); no `App\Models\Match as MatchModel` alias anywhere in the codebase (D-04-04-C / D-04-05-B / D-04-06-D / D-04-07-C / D-04-08-A). |
| Snapshot-at-create slot materialisation — slot.game_role_id FKs to game_roles (not RoleLimit); subsequent RoleLimit edits do NOT retroactively rewrite open match_slots | A1 / **D-04-05-A** | YES | `MatchSlotMaterialiserServiceTest` proves: (a) 50 slots created for Scrim 50v50, 6 for Skirmish 6v6; (b) `slot.game_role_id` is FK to `game_roles`, not `game_match_type_role_limits`; (c) edits to RoleLimit `capacity` AFTER materialisation do not change the existing slot count; (d) `sort_order` is captured as a value snapshot from RoleLimit at create time. |
| Access rule semantics — empty `match_access_rules` = open to all clans (Pattern 5) | A2 / **D-04-06-C** | YES | `MatchSignupService::ensureTagAllowed()` early-returns when the match has zero rules; `MatchSignupTagRestrictedTest::empty_rules_path` and `MatchSignupServiceTest::it_allows_signup_when_no_access_rules` both pass. |
| Polymorphic Event sync — no FK from events; observer + `events_one_per_owner` UNIQUE constraint guarantees integrity | A3 / **D-04-02-A** + **D-04-08-A/B/C** | YES | `events_one_per_owner UNIQUE (eventable_type, eventable_id)` verified via `\d events` in psql; `GameMatch::booted()` registers `MatchObserver`; `MatchEventSyncTest` proves create/update/delete chain; observer-driven Event is segregated from manual `Event::factory()` by `is_public=false` on test fixtures (D-04-08-C). |
| `App\Models\GameMatch` direct import — no Pitfall 5 alias-on-import anywhere | **D-04-04-C / D-04-05-B / D-04-06-D / D-04-07-C / D-04-08-A** | YES | grep for `App\\\\Models\\\\Match as` returns 0 hits across `apps/web/{app,tests,database}` after refactor; canonical idiom is `use App\Models\GameMatch;` everywhere. |
| MvpsRelationManager uses Filament v3 native `HasManyThrough` on `GameMatch::mvps()` (chosen over `getEloquentQuery` override or standalone resource) | **D-04-09-A** | YES | `MatchResourcePresentTest` (25 it() blocks per D-04-12-B) includes the HasManyThrough scope-mount test; Filament v3 RelationManager `assertCanSeeTableRecords` on MVPs works. |

---

## Pest full suite snapshot

**Executed:** `docker compose exec web ./vendor/bin/pest --no-coverage`

```
Tests:    493 passed (1459 assertions)
Duration: 22.99s
```

**All test classes PASS. 0 failures, 0 skipped, 0 incomplete.**

Phase 4 added the following test classes (sourced from plans 04-03/04/05/06/07/08/09/10/12):

| Test class | Tests | Plan source |
|------------|-------|-------------|
| `Tests\Feature\Models\MatchModelTest` | ~12 | 04-03 |
| `Tests\Feature\Models\MatchSlotModelTest` | ~9 | 04-03 |
| `Tests\Feature\Models\MatchAccessRuleModelTest` | ~6 | 04-03 |
| `Tests\Feature\Models\MatchResultModelTest` | ~8 | 04-03 |
| `Tests\Feature\Models\MatchMvpModelTest` | ~6 | 04-03 |
| `Tests\Feature\Models\EventModelTest` | ~7 | 04-03 |
| `Tests\Feature\Services\MatchStatusServiceTest` | 19 | 04-04 |
| `Tests\Feature\Services\MatchSlotMaterialiserServiceTest` | ~8 | 04-05 |
| `Tests\Feature\Services\MatchSignupServiceTest` | ~15 | 04-06 |
| `Tests\Feature\Services\MatchSignupConcurrencyTest` | 1 (pcntl_fork) | 04-06 |
| `Tests\Feature\Matches\MatchSignupTagRestrictedTest` | ~4 | 04-06 |
| `Tests\Unit\Data\MatchDataTest` | ~4 | 04-07 |
| `Tests\Unit\Data\EventDataTest` | ~4 | 04-07 |
| `Tests\Unit\Data\PublicMatchDataTest` | ~5 | 04-07 |
| `Tests\Feature\Observers\MatchEventSyncTest` | ~6 | 04-08 |
| `Tests\Feature\Admin\MatchResourceCreateWizardTest` | ~6 | 04-09 |
| `Tests\Feature\Services\MatchResultServiceTest` | ~6 | 04-09 |
| `Tests\Feature\Matches\MatchCalendarPageTest` | ~8 | 04-10 |
| `Tests\Feature\Matches\MatchShowPageTest` | ~6 | 04-10 |
| `Tests\Feature\Matches\MatchSignupControllerTest` | ~9 | 04-10 |
| `Tests\Feature\Admin\MatchResourcePresentTest` | 25 | 04-09 (18 smoke) + 04-12 (+7 comprehensive) |
| `Tests\Feature\Admin\MatchAuditLogTest` | 12 | 04-12 |

Total: 215 Phase 4 tests / 637 assertions (delta from Phase 3 close of 278 → 493).

---

## Static analysis snapshot

| Tool | Command | Result |
|------|---------|--------|
| Pint (style) | `./vendor/bin/pint --test` | PASS — 295 files clean |
| PHPStan L8 | `./vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | [OK] No errors |
| NoHardcodedStringsTest | included in Pest suite | PASS |
| vue-tsc | `/app/node_modules/.bin/vue-tsc --noEmit` | PASS — 0 type errors |
| shared-types typecheck | `corepack pnpm --filter @trenchwars/shared-types run typecheck` (host) | PASS — clean |

**PHPStan baseline note**: `apps/web/phpstan-baseline.neon` absorbs vendor-internal deprecation traces from Filament v3 + PHP 8.4 (RESEARCH Pitfall 9, established in Phase 1). Phase 4 added Eloquent-datetime-cast `@var Carbon` PHPDoc narrowing in DTO factories (D-04-07-B) + Builder::value coercion for PHPStan L8 typed-coercion (D-04-06-G); these are application-level annotations, not baseline additions. Current run reports `[OK] No errors`.

---

## Grep gate verification

Run-time invariants from plan 04-13 acceptance criteria:

| Gate | Command | Expected | Actual |
|------|---------|----------|--------|
| `DB::transaction` wraps signup service | `grep -c 'DB::transaction' apps/web/app/Services/MatchSignupService.php` | ≥ 1 | **4** |
| `lockForUpdate` row-lock present | `grep -c 'lockForUpdate' apps/web/app/Services/MatchSignupService.php` | ≥ 1 | **6** |
| MatchObserver registered on the canonical model class | `grep -c 'static::observe(MatchObserver' apps/web/app/Models/GameMatch.php` | ≥ 1 | **1** |
| `events_one_per_owner` constraint visible | `psql \\d events` | constraint present | **`events_one_per_owner UNIQUE CONSTRAINT, btree (eventable_type, eventable_id)`** |

All gates PASS.

> **Note on the `MatchObserver` grep target:** The plan acceptance criterion text originally referenced `apps/web/app/Models/Match.php`; the actual model file is `apps/web/app/Models/GameMatch.php` per **D-04-03-A LOCKED** (canonical class name binding established in plan 04-03 and re-affirmed at the executor spawn for this plan). The grep target was corrected to `GameMatch.php` and returns the expected 1 match. Future Phase 5+ executors MUST use `App\Models\GameMatch` (not `App\Models\Match`).

---

## Manual smoke checklist — RESULT: PENDING (manual smoke required by operator)

The automated test suite exercises Filament resource reachability + RelationManager render via Livewire integration tests + the full Filament wizard flow via `Livewire::test(CreateMatch::class)`. The following manual smokes require a live browser session against the running stack (`make up` → `http://localhost:8000`).

### A. [PENDING] Admin Filament Match wizard end-to-end (SC-1)

1. Log in via Discord → navigate to `/admin`.
2. Click **Matches** in the sidebar → **New Match**.
3. Wizard step 1 (Profile): pick **Hell Let Loose** → **Scrim 50v50** match type → set scheduled_at, host_clan, name → Next.
4. Wizard step 2 (Access): leave access_rules empty (open to all) → Next.
5. Wizard step 3 (Review) → **Create**.
6. Verify:
   - [ ] Match landing page shows status = `open` (auto-transition from `draft` per `MatchStatusService` in plan 04-04 + materialiser side-effect in plan 04-09)
   - [ ] **Slots** RelationManager shows exactly **50 rows** (15 roles × Scrim 50v50 capacity matrix)
   - [ ] All slots show `occupant_user_id = null` (unfilled)
   - [ ] Slot `sort_order` is dense 1..50 (snapshot-at-create per D-04-05-A)

### B. [PENDING] Concurrent signup race — two browsers, last slot (SC-2)

1. Pre-condition: create a small match (e.g., Skirmish 6v6 → 6 slots) and let 5 different users sign up (admin can also impersonate via `actingAs` in tinker for setup speed).
2. Open the same match page in two private/incognito windows as two different (eligible) users.
3. Click **Sign up to a Rifleman slot** in both windows within 100ms of each other.
4. Verify:
   - [ ] Exactly ONE returns 201 (slot occupied by that user)
   - [ ] The OTHER returns 422 with i18n key `matches.signup.error.capacity_full` (D-04-10-A — `CapacityExceededException` → `game_role_id` errors key)
   - [ ] Database shows slot count is unchanged (no oversubscription)
   - [ ] `activity_log` has 1 update row attributed to the winning user via the LogsActivity trait on MatchSlot

### C. [PENDING] Public visitor calendar + privacy strip (SC-3, SC-5)

1. Open `/matches` as a logged-out user.
2. Verify:
   - [ ] Calendar lists upcoming matches with status + start time
   - [ ] Status filter (`upcoming | played | cancelled`) works (URL query param `?status=…`)
   - [ ] `is_public=false` matches do NOT appear in the list
3. Click into a match.
4. Verify:
   - [ ] Slot occupant display name shows for users whose privacy tier is `public | community`
   - [ ] Slot occupant displays as anonymous placeholder for `clan | private` tier users (D-04-11-A — Vue `Matches/Show.vue` branches on `displayName !== null`)
   - [ ] Tag-restricted match shows the rule list publicly; "Sign up" button is conditionally disabled per the logged-in user's clan tag eligibility

### D. [PENDING] Admin manual result entry + audit (SC-4)

1. As admin, navigate to a match with status=`locked` (or `open`).
2. Use **Results RelationManager → Create** to fill: winner_clan, score_1, score_2.
3. Add 2 MVPs via the **MVPs RelationManager** (HasManyThrough — D-04-09-A; reachable via the parent GameMatch resource).
4. Save.
5. Verify:
   - [ ] Match status auto-flips to `played` (terminal transition via `MatchResultService::upsert` — D-04-09-C; second edit does NOT re-fire the transition)
   - [ ] `/admin/audit` shows: 1 result create row + 2 mvp create rows + 1 status transition row with `properties.from='locked', properties.to='played'` (D-04-12-A — explicit `withProperties` path)
   - [ ] On the Match edit page, the **Audit** tab renders these rows with the admin's username as causer

### E. [PENDING] Cancel match → Event soft-delete (SC-5)

1. As admin, open a match with status=`open` and `is_public=true`.
2. Confirm an `events` row exists for this match (e.g., via Filament Events resource or `psql` query).
3. Click the **Cancel match** HeaderAction (status → `cancelled`).
4. Verify:
   - [ ] Match `status = cancelled`, `is_public` unchanged
   - [ ] The corresponding `Event` row is removed (MatchObserver fires on the status change; the `events_one_per_owner` constraint + observer sync produces a single source of truth)
   - [ ] `/matches` calendar no longer shows this match (cancelled matches hidden by default; available via `?status=cancelled` filter)

### Operator outcome line

| Check | Result | Notes |
|-------|--------|-------|
| A. Filament Match wizard end-to-end | _PENDING_ | _(operator fills after smoke)_ |
| B. Concurrent signup race | _PENDING_ | _(operator fills after smoke)_ |
| C. Public visitor calendar + privacy strip | _PENDING_ | _(operator fills after smoke)_ |
| D. Admin manual result entry + audit | _PENDING_ | _(operator fills after smoke)_ |
| E. Cancel match → Event soft-delete | _PENDING_ | _(operator fills after smoke)_ |

**Phase 4 status (post-smoke):** _(operator marks COMPLETE or BLOCKED-ON-FIX)_

---

## Must-have traceability

| M# | Must-have | Source | Result |
|----|-----------|--------|--------|
| M1 | Full Pest suite GREEN (Phase 1 + 2 + 3 + 4, no skipped placeholders, no `placeholder.*Wave 0` literals remaining) | 04-13 acceptance | PASS — 493/493 + 0 incomplete |
| M2 | Pint --test clean; PHPStan L8 clean; vue-tsc clean; shared-types pnpm typecheck clean | 04-13 acceptance | PASS — all gates green |
| M3 | TypeScript types regenerated (final consolidation) — api.d.ts contains all 8 Phase 4 DTOs; packages/shared-types/src/index.ts has 8 corresponding export type aliases | 04-13 acceptance | PASS — 8/8 DTOs present in both files (verified via grep) |
| M4 | 04-PHASE-VERIFICATION.md mapping every SC + REQ-goal-match-workflows to a passing test | this document | PASS |
| M5 | ROADMAP.md updated: Phase 4 marked 13/13 Complete; Phase 4 plan list = the actual 13 entries (not Phase-2-paste placeholder) | 04-13 acceptance | PASS — `[x]` flipped + 04-13 row marked complete + bottom progress table updated |
| M6 | REQUIREMENTS.md traceability table: REQ-goal-match-workflows status flipped from In Progress → Complete | 04-13 acceptance | PASS — single-line update |
| M7 | Grep gates green: DB::transaction count > 0 in MatchSignupService.php; lockForUpdate count > 0; static::observe(MatchObserver in GameMatch.php; events_one_per_owner constraint visible in psql | 04-13 acceptance | PASS — 4/6/1/present |
| M8 | Manual smoke checklist documented for operator (Filament UI walk-through) | 04-13 acceptance | PASS — 5 smokes A–E documented |

---

## Plan-Level Deviations from Phase 4

The following Rule 1/2/3 deviations were encountered across plans 04-01..04-12 and resolved inline (none required Rule 4 architectural escalation). All deviations are documented per-plan in `04-XX-SUMMARY.md`. The single most impactful deviation was the **`Match` → `GameMatch` rename in plan 04-03** which superseded D-04-01-B and became D-04-03-A LOCKED for the rest of the phase.

### Cross-cutting / load-bearing (codified as bound LOCKED decisions for Phase 5+)

| ID | Decision | Plan | Why |
|----|----------|------|-----|
| **D-04-03-A** | Class is `App\Models\GameMatch`; table stays `matches` via `protected $table`. | 04-03 | The `match` keyword is reserved in PHP 8 for the `match($x){}` expression; using `App\Models\Match` was working in plan 04-01 Wave 0 stubs (string FQN only — never instantiated) but breaks at the real model layer when Eloquent relationships build/resolve. Single binding for plans 04-04..04-13 and forward to Phase 5+. |
| **D-04-03-B** | Every `BelongsTo<GameMatch, $this>` passes `match_id` as an explicit FK arg. | 04-03 | Laravel cannot infer `match_id` from a relation method named `match()` when the related class is `GameMatch` (the method name + class name no longer match). |
| **D-04-05-A** | Slot snapshot semantics: `slot.game_role_id` FKs to `game_roles` (NOT `game_match_type_role_limits`); `slot.sort_order` is a value snapshot at materialisation time. RoleLimit edits post-materialisation do NOT retroactively rewrite open slots. | 04-05 | Resolves A1; locks the contract for Phase 5 (Discord bot signups) + Phase 8 (RCON live capture) which both read `slot.game_role_id` directly. |
| **D-04-06-A/B** | 5-guard order: status → tag → idempotency → capacity → claim. Lock PARENT `GameMatch` row via `lockForUpdate()->findOrFail()` (not individual slots) for single serialisation point. | 04-06 | D-010 implementation locked; explicit guard order enables cheap-first short-circuit and produces deterministic exception ordering for SC-3 controller error mapping (D-04-10-A). |
| **D-04-08-A/B** | No `Match as MatchModel` alias anywhere; `MatchObserver` registered ONLY via model-level `GameMatch::booted()` (no AppServiceProvider fallback). | 04-08 | Continuation of D-04-03-A naming binding; canonical Phase 4 idiom. |
| **D-04-09-A** | MvpsRelationManager uses Filament v3 native HasManyThrough on `GameMatch::mvps()` (over `getEloquentQuery` override or standalone resource). | 04-09 | Context7 docs confirmed Filament v3 native HasManyThrough RM support; cleanest of three options. |
| **D-04-12-A** | LogsActivity does NOT populate `properties.attributes` in this project (PHP 8.4 / Laravel 12 / spatie-activitylog ^5.0); empirical, not docs-claimed. Explicit `activity()->withProperties()` IS the only path to populated properties JSON. | 04-12 | Codebase-specific deviation surfaced by audit-log integration tests; D-012 is satisfied via description + causer + event triple for trait-driven rows; `withProperties()`-driven rows (MatchStatusService) carry the rich shape. |

### Notable per-plan inline fixes (Rule 1/2/3 auto-fixes — all green at commit)

- **04-03:** `BelongsTo<GameMatch, $this>` explicit FK arg propagated through all 6 child models (RuleErrorAvoidance). SoftDelete-aware FK cascade tests use `forceDelete()` to fire DB-level cascade (D-04-03-C).
- **04-04:** `$from` captured BEFORE `$match->update()` to avoid `getOriginal()` post-refresh drift (D-04-04-A); MatchNotOpenException extends `\DomainException` (D-04-04-B).
- **04-05:** PHPStan L8 null-guard on `$match->gameMatchType` (BelongsTo nullable in PHPStan view) — pathological-null returns 0, semantically equivalent to empty roleLimits (D-04-05-C).
- **04-06:** `MatchSignupConcurrencyTest` manually commits + truncates around `pcntl_fork` because `RefreshDatabase` is fork-unsafe (D-04-06-E). `MatchSignupTagRestrictedTest` lives at `tests/Feature/Matches/` (plan body said `Services/` — Rule 1 alignment with Wave 0 stub location, D-04-06-F).
- **04-07:** `PublicMatchOccupantData::empty()` renamed to `forEmptySlot()` to avoid Spatie Data::empty() base method collision (D-04-07-A); Carbon `@var` PHPDoc narrowing for Eloquent datetime casts to satisfy PHPStan L8 (D-04-07-B).
- **04-08:** Pre-existing tests broken by observer's introduction were ripple-fixed by setting `is_public=false` on factory-created GameMatches in tests that build manual `Event::factory()` rows — segregates observer-driven vs manual Event (D-04-08-C).
- **04-09:** EditMatch HeaderActions for status transitions; `MatchResultService::upsert` terminal-state SKIP (re-edits don't re-fire transition) via `if ($match->status !== 'played')` wrapper around the transition call (D-04-09-C). Container-bind stub pattern for `final` services to avoid Mockery/anonymous-class-extension (D-04-09-D).
- **04-10:** 4-exception catch order ends with CapacityExceededException → `game_role_id`; status/tag/idempotency → `general`. Order mirrors service guard order (D-04-10-A). `GameMatch::getRouteKeyName() => 'id'` explicit override documents UUID-binding contract (D-04-10-B). `MatchShowController` eager-loads `slots.role` to avoid N+1 in groupBy role-resolution path (D-04-10-C).
- **04-11:** Privacy rendering uses `!== null` (matches generated TS nullable contract — D-04-11-A); MatchStatusBadge is a standalone Phase 4 composite (NOT a 5-variant extension of StatusBadge — D-04-11-B); no `dayjs` runtime dep — native `Intl.DateTimeFormat` saves ~7kB gzip (D-04-11-C); TextInput `type` prop union expanded with `'date'` (D-04-11-D); v-if `>` comparisons refactored into named computed booleans to avoid NoHardcodedStringsTest regex false-match (D-04-11-E).
- **04-12:** LogsActivity properties.attributes empty array empirical finding (D-04-12-A — see above); MatchResourcePresentTest upgraded from 18 smoke (04-09) to 25 comprehensive blocks via `assertCanSeeTableRecords` direct-mount on all 4 RelationManagers (Phase 3 plan 03-08 Pitfall 3 idiom — D-04-12-B); same-game fixtures mandatory for RelationManager tests depending on materialiser invariant (D-04-12-C).

### Threat register dispositions (T-04-XX-NN)

All `mitigate` dispositions across plans 04-01..04-12 are resolved per their plan SUMMARYs; no `accept` disposition required additional follow-up; no new threat-flag surface introduced in plan 04-13 (final close work touches only docs + frontmatter).

---

## Plan-13 specifics

This plan's task list intentionally compressed all close work into a single Task 1:
1. Regenerate api.d.ts via `trenchwars:typescript-generate` (idempotent — all 8 Phase 4 DTOs survived plans 04-08..04-12 source changes).
2. Author this `04-PHASE-VERIFICATION.md`.
3. Update `ROADMAP.md`: flip Phase 4 top-level `[ ]` → `[x]`; flip plan 04-13 row `[ ]` → `[x]`; update bottom progress table to `13/13 | Complete | 2026-05-13`.
4. Update `REQUIREMENTS.md`: REQ-goal-match-workflows row in Traceability table flipped from `In Progress` → `Complete`.
5. Run final quality gate sweep (Pest + Pint + PHPStan L8 + vue-tsc + shared-types typecheck + migrate:fresh+seed) — all GREEN.
6. Grep gates verified (DB::transaction + lockForUpdate + MatchObserver registration on GameMatch + events_one_per_owner constraint visible).
7. STATE.md updated via `gsd-sdk` state-handler verbs (advance-plan + update-progress + record-metric + add-decision + record-session).

No Rule 1/2/3 deviations encountered during this close plan's execution beyond the documented `MatchObserver` grep target correction (target file is `GameMatch.php` per D-04-03-A, not the plan-text-cited `Match.php`); this is a plan-text typo, not a code change, and the grep gate passes as expected.

---

## Out-of-Scope Items Deferred to Future Phases

The following Phase 4 integration points are explicitly out of scope and live in Phase 5+ plans:

| Out-of-scope item | Lives in | Reason |
|-------------------|----------|--------|
| Discord bot `/match signup` modal driving MatchSignupService | **Phase 5** (Discord bot v1) | Phase 5 builds the bot adapter; Phase 4 provides the `MatchSignupService` primitive Phase 5 consumes via the Sanctum `bot:*` scoped token + `X-Bot-Acts-As-User` header. |
| Match RSVP buttons + outbound Discord announcements on match create | **Phase 5** | `discord_outbound_messages` table + `pending → sent | failed` durability live in Phase 5; the `MatchObserver`'s Event sync + `MatchResource` create flow stay phase-4-local. |
| Tournament bracket → Match materialisation (single-elim/double-elim/round-robin/swiss) | **Phase 6** (Tournaments & brackets) | Phase 6's `BracketGeneratorService` will call `MatchSlotMaterialiserService` to spawn matches per round; the contract surface (game_match_type + scheduled_at + host_clan) is already locked. |
| CMS Event aggregation of editorial + auto-Event match rows on `/events` calendar | **Phase 7** (CMS) | Phase 7 builds the public `/events` calendar consuming the polymorphic `events` table that Phase 4 populated via `MatchObserver`; the data model is ready. |
| RCON live capture writing MatchResult + MatchPlayerStat (source='rcon') | **Phase 8** (RCON automation) | Phase 8 will use `MatchResultService::upsert` with a different `source` value; the terminal-state SKIP logic (D-04-09-C) already supports the auto+manual interleaving without re-firing transitions. |
| Browser tests (Playwright/Dusk) on the 5 manual smokes A–E | **Phase 9** (Polish) — deferred from Phase 1 | P1 explicitly deferred browser tests (CLAUDE.md §4); the operator smoke checklist in this report covers the gap until Phase 9. |

---

## Sign-off

Phase 4 verified complete pending operator manual smokes; ROADMAP.md + REQUIREMENTS.md + STATE.md updated; ready for Phase 5 (Discord bot v1).

**Phase 5 hand-off note:** Phase 4 provides the complete server-side primitive set for Discord-driven match interactions:
- `App\Models\GameMatch` (D-04-03-A LOCKED canonical name — Phase 5 bot code MUST use this FQN)
- `MatchSignupService` (D-010 row-locked) for `/match signup` modal handling
- `MatchData` + `PublicMatchData` + `MatchSlotData` DTOs (regenerated to TS in `apps/web/resources/js/types/api.d.ts` + `packages/shared-types/src/index.ts`) for cross-process consumption
- `MatchObserver` → `Event` sync — Phase 5's outbound Discord embed flow can subscribe to the same observer chain
- `MatchResultService::upsert` terminal-state SKIP logic (D-04-09-C) ready for the future RCON path in Phase 8 to share with the admin manual path
- Audit log integration (D-012) proven across all 6 Phase 4 models — Phase 5 bot writes attribute via `X-Bot-Acts-As-User` will land in the same activity_log surface with the human causer correctly attributed

**Reviewed by:** Claude Opus 4.7 (1M context) — automated verification executor
**Date:** 2026-05-13
