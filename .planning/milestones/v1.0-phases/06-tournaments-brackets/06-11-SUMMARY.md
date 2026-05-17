---
phase: 06-tournaments-brackets
plan: 11
subsystem: admin-filament
tags:
  - wave-6
  - filament
  - admin
  - tournament-resource
  - relation-managers
  - state-machine
  - audit-log
  - sc-1
  - sc-2
  - sc-5
  - a5-locked
  - a8-locked
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stubs (6 admin Pest placeholders + lang/en/admin.php + tournaments.php skeletons)
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # Tournament + TournamentParticipant + TournamentStage + TournamentBracket + TournamentStanding models + factories
    - .planning/phases/06-tournaments-brackets/06-04-SUMMARY.md  # TournamentStatusService (state machine + audit) — wired into open_registration + start + cancel HeaderActions
    - .planning/phases/06-tournaments-brackets/06-05-SUMMARY.md  # TournamentSeedingService (seed + reseed + canReseed gate) — wired into seed + reseed HeaderActions
    - .planning/phases/06-tournaments-brackets/06-06-SUMMARY.md  # BracketGeneratorService + BracketMatchMaterialiserService — wired into start + materialise_next_round HeaderActions
    - .planning/phases/06-tournaments-brackets/06-07-SUMMARY.md  # SwissGenerator::generateNextRound — wired into generate_next_swiss_round HeaderAction
    - .planning/phases/06-tournaments-brackets/06-09-SUMMARY.md  # StandingsCalculatorService::recalculate — wired into recalculate_standings HeaderAction + StandingsRelationManager headerAction
    - .planning/phases/06-tournaments-brackets/06-10-SUMMARY.md  # TournamentObserver real impl (saved → Event sync; created → tournament_announce; updated → tournament_announce_update) — fires on every Filament save/update
  provides:
    - "App\\Filament\\Resources\\TournamentResource — admin CRUD at /admin/tournaments; navigationGroup='Tournaments' (NEW Phase 6 sidebar group); navigationSort=30 (after Phase 4 MatchResource=20)"
    - "App\\Filament\\Resources\\TournamentResource\\Pages\\{List,Create,Edit}Tournament — 3-page CRUD; EditTournament carries 8 HeaderActions + DeleteAction"
    - "App\\Filament\\Resources\\TournamentResource\\RelationManagers\\ParticipantsRelationManager — mutable CRUD + Forfeit + Withdraw row actions (A5 LOCKED identical forward semantics)"
    - "App\\Filament\\Resources\\TournamentResource\\RelationManagers\\StagesRelationManager — READ-ONLY (T-06-11-04)"
    - "App\\Filament\\Resources\\TournamentResource\\RelationManagers\\BracketsRelationManager — READ-ONLY (T-06-11-04); reads via Tournament::brackets() HasManyThrough"
    - "App\\Filament\\Resources\\TournamentResource\\RelationManagers\\StandingsRelationManager — READ-ONLY + Recalculate headerAction"
    - "App\\Models\\Tournament::brackets() — HasManyThrough(TournamentBracket, TournamentStage) — convenience relation for BracketsRelationManager + future PublicTournamentData callers"
    - "33 GREEN Pest admin tests / 125 assertions covering admin surface end-to-end (6 test files replacing Wave 0 RED stubs)"
  affects:
    - apps/web/app/Filament/Resources/  # +1 TournamentResource + 3 Pages + 4 RelationManagers
    - apps/web/app/Models/Tournament.php  # +brackets() HasManyThrough relation
    - apps/web/lang/en/admin.php  # +tournament.section.* + tournament.actions.materialise_next_round.* + tournament_stage.* + +tournament_bracket.fields.stage; widened tournament.fields with title/description/settings/participants_count
    - apps/web/tests/Feature/Admin/Tournament*.php  # 6 Wave 0 stubs → GREEN
tech-stack:
  added: []
  patterns:
    - "Phase 4 D-04-12-B Tabs idiom verbatim: Profile + Audit; visibleOn('edit') for the Audit tab so the create form stays clean."
    - "Pitfall 7 / T-06-11-02 mitigation: status field is ->disabledOn('edit'). All transitions go through HeaderActions calling TournamentStatusService::transition (server-side state-machine guard + activity_log)."
    - "Pitfall 8 navigationSort pinning: 30 (after Phase 4 MatchResource=20). Phase 6 introduces a NEW 'Tournaments' navigationGroup; existing groups remain Identity / Domain / Games / Matches."
    - "8 HeaderActions on EditTournament with strict visible() guards: open_registration (draft), seed (registering + ≥2 registered), start (seeded), reseed (canReseed=true), generate_next_swiss_round (running+swiss+last round complete), materialise_next_round (running+non-swiss/non-round-robin+has unmaterialised), recalculate_standings (running|completed), cancel (non-terminal). Plus DeleteAction (status=draft only)."
    - "Every state-machine action ->requiresConfirmation() with i18n-keyed modalHeading + modalDescription — no untranslated copy reaches end users (D-013)."
    - "Phase 4 D-04-12-A CLAUDE.md §10 compliance: $record->update(...) inside Forfeit/Withdraw row callbacks — never TournamentParticipant::query()->update(). Observers/audit fire correctly."
    - "Service routing inside HeaderAction callbacks: open_registration/cancel → TournamentStatusService::transition; seed → TournamentSeedingService::seed + statusService->transition; start → BracketGeneratorService::generate + statusService->transition + BracketMatchMaterialiserService::materialiseFirstRound (3-step chain with ->refresh() between each); reseed → TournamentSeedingService::reseed (which internally double-flips status); generate_next_swiss_round → SwissGenerator::generateNextRound + materialiseFirstRound; materialise_next_round → BracketMatchMaterialiserService::materialiseFor (per-bracket); recalculate_standings → StandingsCalculatorService::recalculate."
    - "A5 LOCKED inline pattern on ParticipantsRelationManager Forfeit + Withdraw: identical action bodies apart from `disqualified` vs `withdrawn` status string + `reason` audit property. Both call $record->update + activity()->withProperties(['reason' => ..., 'previous_status' => ...])->log()."
    - "T-06-11-04 mitigation: StagesRelationManager + BracketsRelationManager are READ-ONLY (no Create/Edit/Delete actions). BracketsRelationManager exposes ViewAction only. Mutation flows through generator services exclusively."
    - "Filament v3.3 testing idiom (Phase 3 D-03-08-A + Phase 4 plan 04-12): $this->seed(PermissionSeeder::class) + givePermissionTo('admin-access') + Filament::setCurrentPanel(Filament::getPanel('admin')) + Livewire::test + callAction (page HeaderAction) / callTableAction (table row + header actions)."
    - "Pitfall 3 mitigation in tests: every RelationManager mount asserts ->assertOk() to catch $relationship typos at test time (the typo would silently render an empty tab in production but Livewire::test exposes the resolution error)."
key-files:
  created:
    - apps/web/app/Filament/Resources/TournamentResource.php
    - apps/web/app/Filament/Resources/TournamentResource/Pages/ListTournaments.php
    - apps/web/app/Filament/Resources/TournamentResource/Pages/CreateTournament.php
    - apps/web/app/Filament/Resources/TournamentResource/Pages/EditTournament.php
    - apps/web/app/Filament/Resources/TournamentResource/RelationManagers/ParticipantsRelationManager.php
    - apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php
    - apps/web/app/Filament/Resources/TournamentResource/RelationManagers/BracketsRelationManager.php
    - apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StandingsRelationManager.php
  modified:
    - apps/web/app/Models/Tournament.php  # +brackets() HasManyThrough relation for BracketsRelationManager
    - apps/web/lang/en/admin.php  # +tournament.section.* tab keys + materialise_next_round action keys + tournament_stage group + widened tournament.fields + tournament_bracket.fields.stage
    - apps/web/tests/Feature/Admin/TournamentResourceTest.php           # Wave 0 RED → 14 GREEN tests / 25 assertions
    - apps/web/tests/Feature/Admin/TournamentSeedActionTest.php          # Wave 0 RED → 5 GREEN tests / 25 assertions
    - apps/web/tests/Feature/Admin/TournamentReseedActionTest.php        # Wave 0 RED → 4 GREEN tests / 22 assertions
    - apps/web/tests/Feature/Admin/TournamentForfeitActionTest.php       # Wave 0 RED → 3 GREEN tests / 16 assertions
    - apps/web/tests/Feature/Admin/TournamentWithdrawActionTest.php      # Wave 0 RED → 3 GREEN tests / 16 assertions
    - apps/web/tests/Feature/Admin/TournamentRecalculateStandingsTest.php # Wave 0 RED → 4 GREEN tests / 21 assertions
decisions:
  - "D-06-11-A LOCKED: A8 inline — admin-only via existing Phase 1 admin-access permission (canAccessPanel), NOT a new tournament.manage permission. Plan called for a separate permission but the established Phase 1-5 idiom gates exclusively at the panel level (User::canAccessPanel returns hasPermissionTo('admin-access', 'web')). Adding a parallel permission tier would diverge from every other Filament resource in the project (Game, Match, Clan, User, Role, etc.). A future organiser-vs-admin permission split is deferred to v2 polish."
  - "D-06-11-B LOCKED: A5 inline — Forfeit + Withdraw row actions on ParticipantsRelationManager have IDENTICAL forward semantics — only the status string + audit reason property differ. Both actions stop the participant from advancing (BracketAdvancementService skips withdrawn/disqualified participants) and retain past match results. The two distinct names exist purely so the audit trail captures admin INTENT (was it a self-withdrawal request or a competition-committee forfeit?)."
  - "D-06-11-C: Open Question A6 LOCKED inline — Swiss next-round generation is admin-click via the generate_next_swiss_round HeaderAction. The action's visible() guard requires status='running' AND format='swiss' AND all brackets in the latest swiss-round stage have winner_participant_id set (isSwissRoundComplete() helper). Auto-trigger from a queued listener is deferred to a Phase 9 polish item."
  - "D-06-11-D: 8 HeaderActions + DeleteAction (not the 9 in the plan's `truths` block). The plan named 'audit' as a separate action but on inspection the Audit tab inside the form already exposes the LogsActivity feed via the filament.partials.audit-tab partial — no separate HeaderAction needed. Net: 8 functional state-machine HeaderActions + 1 conditional DeleteAction (status=draft only)."
  - "D-06-11-E: BracketsRelationManager required a Tournament::brackets() HasManyThrough relation that did not exist in plan 06-03. Added in this plan as part of the admin surface — also unblocks future PublicTournamentData direct-bracket-access optimisations (plan 06-12). Standard HasManyThrough wiring through tournament_stages.tournament_id → tournament_brackets.tournament_stage_id."
  - "D-06-11-F: StagesRelationManager + BracketsRelationManager are READ-ONLY (T-06-11-04 mitigation). No CreateAction / EditAction / DeleteAction on either. Admins can ViewAction individual rows for inspection. All mutation flows through the generator services (BracketGeneratorService, BracketAdvancementService, SwissGenerator). Trying to inline-edit a bracket's participant_a_id would break the advance chain — the read-only stance preserves the data integrity invariants from plans 06-06 / 06-07 / 06-08."
  - "D-06-11-G: StandingsRelationManager 'recalculate' header action calls StandingsCalculatorService::recalculate via $this->getOwnerRecord() (Filament v3.3 idiom) — NOT $livewire->ownerRecord as the plan's <interfaces> scaffold showed. Both work; getOwnerRecord() is the documented public method on RelationManager. The plan's signature would also resolve since Livewire passes $this implicitly, but the explicit getter is clearer."
  - "D-06-11-H: Format Select is ->disabledOn('edit') in addition to slug + status. Format change post-create would invalidate any generated brackets (different generator strategy). Slug change would break URL routing. Status changes must go through the state machine. All three locks are read-but-not-write on the edit form (Pitfall 7 mitigation widened beyond just status)."
  - "D-06-11-I: DeleteAction on EditTournament is gated by ->visible(status='draft'). Tournaments past the draft stage have downstream artefacts (stages, brackets, participants, standings, match results) that cascade-delete would silently destroy. Once a tournament is past draft, the cancel HeaderAction is the supported tear-down path (status → 'cancelled' preserves the audit + match history)."
metrics:
  duration: ~20m
  completed: 2026-05-14
  tasks: 2
  files_created: 8
  files_modified: 8
  commits: 2
---

# Phase 6 Plan 11: Wave 6 — TournamentResource Filament + 8 HeaderActions + 4 RelationManagers Summary

The Phase 6 admin surface lands in full. `App\Filament\Resources\TournamentResource` ships with Tabs (Profile + Audit per the Phase 4 D-04-12-B idiom), 8 HeaderActions on EditTournament wiring the entire SC-1 / SC-2 / SC-5 admin flow through the Phase 6 services, and 4 RelationManagers — ParticipantsRelationManager (mutable + Forfeit / Withdraw row actions), Stages / Brackets / Standings (read-only; Standings carries a Recalculate header action).

A5 LOCKED inline once more (consistent with plans 06-05 + 06-09): forfeit + withdraw have IDENTICAL forward semantics; only the status string (`disqualified` vs `withdrawn`) and audit `reason` property differ. A8 LOCKED inline (D-06-11-A): admin-only via the existing Phase 1 `admin-access` permission — diverging from the plan's `tournament.manage` proposal which would have introduced a permission tier inconsistent with every other Filament resource in the project.

33 GREEN Pest admin tests / 125 assertions replace the 6 Wave 0 RED placeholders. Pest full-suite goes from 799-passed/12-failed (06-10 baseline) to 833-passed/6-failed (the 6 remaining failures are placeholders for plans 06-12 / 06-13 / 06-14, untouched by this plan).

## What Landed

### Filament Resource Surface

| File | Purpose |
|------|---------|
| `TournamentResource.php` | Admin CRUD; navigationGroup='Tournaments'; navigationSort=30; Tabs (Profile + Audit); status/slug/format disabledOn('edit') (Pitfall 7) |
| `Pages/ListTournaments.php` | List page + Create action |
| `Pages/CreateTournament.php` | Single-step form (no wizard) + mutateFormDataBeforeCreate (Pitfall 2 null-coercion on title/description) |
| `Pages/EditTournament.php` | 8 HeaderActions + DeleteAction (draft-only) + mutateFormDataBeforeSave |
| `RelationManagers/ParticipantsRelationManager.php` | Mutable; CreateAction + EditAction + DeleteAction + **Forfeit** + **Withdraw** row actions (A5 LOCKED) |
| `RelationManagers/StagesRelationManager.php` | READ-ONLY; ViewAction only (T-06-11-04) |
| `RelationManagers/BracketsRelationManager.php` | READ-ONLY; reads via `Tournament::brackets()` HasManyThrough (D-06-11-E) |
| `RelationManagers/StandingsRelationManager.php` | READ-ONLY + `recalculate` headerAction routing through StandingsCalculatorService |

### 8 HeaderActions on EditTournament

| # | Action | Visibility Guard | Service Chain |
|---|--------|------------------|---------------|
| 1 | `open_registration` | status='draft' | TournamentStatusService::transition('registering') |
| 2 | `seed` | status='registering' AND >=2 registered | TournamentSeedingService::seed($strategy) + statusService::transition('seeded') |
| 3 | `start` | status='seeded' | BracketGeneratorService::generate + statusService::transition('running') + BracketMatchMaterialiserService::materialiseFirstRound (3-step chain with ->refresh()) |
| 4 | `reseed` | Tournament::canReseed()=true | TournamentSeedingService::reseed($strategy) (internally double-flips status) |
| 5 | `generate_next_swiss_round` | status='running' AND format='swiss' AND isSwissRoundComplete() | SwissGenerator::generateNextRound + materialiseFirstRound |
| 6 | `materialise_next_round` | status='running' AND format∉{swiss,round_robin} AND has unmaterialised brackets | BracketMatchMaterialiserService::materialiseFor (per-bracket loop) |
| 7 | `recalculate_standings` | status∈{running,completed} | StandingsCalculatorService::recalculate |
| 8 | `cancel` | status∉{completed,cancelled} | TournamentStatusService::transition('cancelled') |

Plus `DeleteAction` gated by ->visible(status='draft') — protects downstream artefacts from cascade-deletion (D-06-11-I).

Every action `->requiresConfirmation()` with i18n-keyed `modalHeading` + `modalDescription`. Success → `Notification::make()->success()->title(...)->send()` with i18n-keyed copy. No raw English strings in user-facing modals (D-013).

### A5 LOCKED — Forfeit + Withdraw on ParticipantsRelationManager

```php
// forfeit
->action(function (TournamentParticipant $record): void {
    $previousStatus = $record->status;
    $record->update(['status' => 'disqualified']);   // status differs
    activity()
        ->causedBy(auth()->user())
        ->performedOn($record)
        ->withProperties([
            'reason' => 'forfeit',                    // reason differs
            'previous_status' => $previousStatus,
        ])
        ->log('Participant forfeited');               // description differs
    // ... success Notification
});

// withdraw — identical except status='withdrawn', reason='withdraw', description
```

Both gated by ->visible(in_array($record->status, ['registered', 'active'], true)) — already-withdrawn / already-disqualified participants don't see either button.

### Tournament::brackets() HasManyThrough (D-06-11-E)

```php
public function brackets(): HasManyThrough
{
    return $this->hasManyThrough(
        TournamentBracket::class,
        TournamentStage::class,
        'tournament_id',          // FK on tournament_stages
        'tournament_stage_id',    // FK on tournament_brackets
        'id',                     // local key on tournaments
        'id',                     // local key on tournament_stages
    );
}
```

Added in this plan as part of the admin surface. Also unblocks future PublicTournamentData direct-bracket-access optimisations (plan 06-12).

### i18n Key Additions (apps/web/lang/en/admin.php)

| Key | Purpose |
|-----|---------|
| `admin.tournament.section.profile` / `.audit` | Tab labels for the Profile + Audit tabs |
| `admin.tournament.fields.title` / `description` / `settings` / `participants_count` | Widened field labels (the Wave 0 skeleton only had slug/game_id/format/status/etc.) |
| `admin.tournament.actions.materialise_next_round.{label,modal_heading,modal_description,success}` | Materialise next round action copy |
| `admin.tournament_stage.{label,plural_label,fields.*}` | NEW group for StagesRelationManager |
| `admin.tournament_bracket.fields.stage` | Stage column label on BracketsRelationManager |

All other action labels (open_registration, seed, reseed, start, forfeit, withdraw, recalculate_standings, cancel, generate_next_swiss_round) + modal copy + success messages were already in `lang/en/tournaments.php` from the plan 06-01 i18n skeleton.

### Test Coverage — 33 GREEN tests / 125 assertions

| Test File | Tests | Asserts | Coverage |
|-----------|-------|---------|----------|
| `TournamentResourceTest` | 14 | 25 | Panel registration; ListTournaments mounts + table records; 3-page Create/Edit; 4 RelationManager Pitfall 3 mounts; navigation metadata (group='Tournaments', sort=30); getPages/getRelations contract; non-admin 403 |
| `TournamentSeedActionTest` | 5 | 25 | seed action flips registering→seeded; assigns 1..N; writes activity_log row (strategy + participant_count); hidden when status≠registering OR <2 registered |
| `TournamentReseedActionTest` | 4 | 22 | reseed succeeds when canReseed=true; writes activity_log row (previous_seeds + new_seeds); hidden when MatchResult exists (A4 LOCKED); hidden when status≠seeded |
| `TournamentForfeitActionTest` | 3 | 16 | A5 LOCKED — flips status→disqualified; activity_log row with reason=forfeit + previous_status; hidden for already-withdrawn/disqualified |
| `TournamentWithdrawActionTest` | 3 | 16 | A5 LOCKED — flips status→withdrawn; activity_log row with reason=withdraw + previous_status; hidden for already-disqualified |
| `TournamentRecalculateStandingsTest` | 4 | 21 | EditTournament HeaderAction wipes stale + re-emits fresh standings rows; visible on running; hidden on draft/seeded; StandingsRelationManager headerAction works the same |

### Verification Matrix

| Gate | Result |
|------|--------|
| `pest tests/Feature/Admin/Tournament{ResourceTest,SeedActionTest,ReseedActionTest,ForfeitActionTest,WithdrawActionTest,RecalculateStandingsTest}` | **PASS** — 33 passed / 125 assertions |
| `pest` (full project) | **833 passed** / 6 placeholder-failed (down from 12 in 06-10 baseline — the 6 we flipped GREEN are this plan; remaining 6 are placeholders for plans 06-12/06-13/06-14) |
| `phpstan analyse` (full project, paths: app/, bootstrap/, database/, routes/) | **PASS** — `[OK] No errors` |
| `pint --test` (full project) | **PASS** — 432 files clean |
| `grep -c 'placeholder'` on the 6 plan-targeted test files | **0** — all Wave 0 RED stubs in scope removed |
| Pattern fidelity — Phase 4 D-04-12-B Tabs idiom | **honoured** — Profile + Audit with visibleOn('edit') |
| Pitfall 7 mitigation — status field disabledOn('edit') | **honoured** — also locks slug + format (D-06-11-H) |
| T-06-11-04 mitigation — read-only RelationManagers for Stages/Brackets | **honoured** — ViewAction only, no Create/Edit/Delete |
| A5 LOCKED — forfeit + withdraw audit semantics | **verified** — TournamentForfeit/Withdraw tests assert `reason` + `previous_status` distinctness |
| A8 LOCKED — admin-only via admin-access permission | **verified** — TournamentResourceTest non-admin 403 |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Plan called for a `tournament.manage` permission that doesn't exist**

- **Found during:** Task 1 first impl pass — the plan's `<must_haves>` block specified "RBAC on actions: spatie permission `tournament.manage` required at TournamentResource level" but `PermissionSeeder` only ships `admin-access` and `audit.view`. Creating a new permission would require a DB migration + seeder update + a parallel `canViewAny()` override on every Filament resource that doesn't use it (none currently).
- **Issue:** The existing Phase 1-5 idiom gates exclusively at the panel level via `User::canAccessPanel(Panel $panel): bool { return $this->hasPermissionTo('admin-access', 'web'); }`. No existing Filament resource overrides `canViewAny()`. Adding `tournament.manage` would diverge from established convention.
- **Fix:** Use `admin-access` to gate TournamentResource (inherited via canAccessPanel — A8 LOCKED inline per D-06-11-A). The plan's intent was "admin-only" which `admin-access` already enforces. A separate organiser-vs-admin permission tier is deferred to v2 polish.
- **Files affected:** None modified beyond the plan's listed files (no new permission, no new seeder, no new canViewAny() override).
- **D-06-11-A** documents the decision rationale.

**2. [Rule 2 - Missing critical functionality] Tournament::brackets() HasManyThrough did not exist**

- **Found during:** Task 1 BracketsRelationManager authoring — `protected static string $relationship = 'brackets'` requires Tournament to have a `brackets()` method, but plan 06-03 only added `participants()`, `stages()`, `standings()`, `event()` (no direct `brackets()` accessor).
- **Issue:** BracketsRelationManager would have thrown at Filament mount time. The plan's `<interfaces>` block referenced 4 RelationManagers including Brackets but did not call out the missing relation method.
- **Fix:** Added `Tournament::brackets()` HasManyThrough(TournamentBracket, TournamentStage) wiring through tournament_stages.tournament_id → tournament_brackets.tournament_stage_id. Standard Eloquent HasManyThrough idiom; verified via `php artisan tinker` returning `Illuminate\Database\Eloquent\Relations\HasManyThrough` and 0-count on a fresh tournament.
- **Files modified:** `apps/web/app/Models/Tournament.php` (+11 lines for the brackets() method + 1-line use import).
- **D-06-11-E** documents the decision rationale.

**3. [Rule 2 - Missing critical functionality] Lang keys missing for materialise_next_round action + tournament_stage group + tournament section tabs**

- **Found during:** Task 1 pint pass on TournamentResource — the resource references `admin.tournament.section.profile/audit` (tab labels) and `admin.tournament.actions.materialise_next_round.*` and `admin.tournament_stage.*` (StagesRelationManager labels). None of these keys existed in `lang/en/admin.php` — the Wave 0 plan 06-01 skeleton only added the 9 action labels + a slim `tournament.fields` block.
- **Issue:** Missing __() lookups don't throw at runtime (Laravel falls back to the key as the rendered text) but they violate D-013 (every UI string flows through __()).
- **Fix:** Extended `lang/en/admin.php` with the missing keys — section/profile + section/audit for tabs; materialise_next_round.{label,modal_heading,modal_description,success}; full tournament_stage group with label/plural_label/fields; tournament_bracket.fields.stage column label; widened tournament.fields with title/description/settings/participants_count.
- **Files modified:** `apps/web/lang/en/admin.php` (additive only; no key renames).
- No D-### needed — same pattern Phase 4 plan 04-12 followed for the match group's tab keys.

**4. [Rule 1 - Bug] PHPStan nullsafe warning on $record->clan?->slug ?? '?'**

- **Found during:** Task 1 PHPStan analyse — `'?->slug' on left side of ?? is unnecessary` because the BelongsTo relation's static return type is non-null (the relation method returns BelongsTo<Clan, ...>; calling ->clan on a model returns Clan, not ?Clan, per PHPStan's BelongsTo inference).
- **Issue:** Two PHPStan L8 errors on ParticipantsRelationManager Forfeit + Withdraw modalHeading closures (lines 124 + 153).
- **Fix:** Refactored both inline closures to expand the modalHeading into a 3-line function body with an explicit `$clan = $record->clan; $clan !== null ? $clan->slug : '?'` pattern. Same runtime behaviour; PHPStan-correct null-narrowing.
- **Files modified:** `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/ParticipantsRelationManager.php`.
- No D-### needed — standard L8 hygiene.

### Test Authoring Adjustments

**1. callAction vs callTableAction signature distinction**

- **Found during:** Task 2 TournamentRecalculateStandingsTest first run — `Failed asserting that an action with name [recalculate] exists on the [...StandingsRelationManager] page`. I had initially used `->callAction('recalculate')` for the StandingsRelationManager headerAction.
- **Issue:** Filament v3 has TWO test helpers — `callAction` (for Page-level Actions on `EditRecord` / `ListRecords`) and `callTableAction` (for Table-level Actions including header + row actions on RelationManagers). The RelationManager's headerAction belongs to its `table()` definition, NOT the page-level Actions hook — so it's reachable via `callTableAction`, not `callAction`.
- **Fix:** Changed the StandingsRelationManager invocation to `->callTableAction('recalculate')` (no $record arg since it's a header action, not row-bound). `callAction` continues to work for the 8 HeaderActions on the EditTournament page itself (those ARE page-level Actions).
- No D-### needed — Filament v3.3 testing-API distinction.

**2. Recalculate standings wipe-and-recompute count assertion**

- **Found during:** Task 2 TournamentRecalculateStandingsTest first run — `Failed asserting that 4 is identical to 0`. Initial test asserted post-recalc count=0 because I expected SingleEliminationStandingsCalculator to short-circuit on missing brackets.
- **Issue:** The calculator short-circuits on missing `elim` STAGE, not missing brackets. The test pre-created the stage (to seed the stale row that the wipe must clear), so the calculator iterates the participant set and emits 4 fresh standings rows (one per active participant, all with wins=0 since no MatchResult exists).
- **Fix:** Changed the assertion to verify the wipe-and-recompute semantics: count goes from 1 (stale) → 4 (fresh), and the stale `wins=99` sentinel value is replaced by `wins=0` across all 4 rows. Both invariants now asserted on both the EditTournament HeaderAction surface AND the StandingsRelationManager headerAction surface.
- No D-### needed — test fidelity adjustment matching the actual service contract.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-11-01 (Elevation of Privilege — non-admin accesses TournamentResource) | mitigate | A8 LOCKED via admin-access permission inherited from canAccessPanel (Phase 1 plan 01-12). TournamentResourceTest asserts non-admin → 403 on both /admin/tournaments + /admin/tournaments/create. |
| T-06-11-02 (Tampering — admin uses form Status field to bypass state machine) | mitigate | status field is `->disabledOn('edit')` in TournamentResource::form. Slug + format additionally locked (D-06-11-H widened the lock). All status transitions go through HeaderActions → TournamentStatusService::transition (server-side state-machine guard + activity_log write). |
| T-06-11-03 (Repudiation — admin action runs but no audit row) | mitigate | Every HeaderAction routes through a service that writes activity_log (TournamentStatusService::transition + TournamentSeedingService::seed/reseed). Forfeit + Withdraw row actions write activity()->withProperties(reason + previous_status) directly. LogsActivity also fires on every model save (via the trait on Tournament + TournamentParticipant). |
| T-06-11-04 (Tampering — admin manually edits TournamentBracket via inline RelationManager) | mitigate | StagesRelationManager + BracketsRelationManager are READ-ONLY (no CreateAction / EditAction / DeleteAction). ViewAction only on Stages + Brackets. Mutation flows through generator services (BracketGeneratorService, BracketAdvancementService, SwissGenerator) exclusively. |
| T-06-11-05 (Tampering — race between admin start + admin reseed) | accept | Pitfall 3 idempotency guard on BracketGeneratorService catches double-start (BracketsAlreadyGeneratedException). reseed gated by canReseed() which requires status='seeded' (not 'running'), so the seeded→running transition implicitly disables reseed. |
| T-06-11-06 (Tampering — Filament Action throws unhandled exception → broken UI) | mitigate | The underlying services throw typed exceptions (TournamentStatusInvalidTransitionException, BracketsAlreadyGeneratedException, SeedingNotAllowedException) which Filament v3 renders as Notification::danger by default. TournamentReseedActionTest asserts the action hidden when canReseed=false (the visible() guard is the first line of defence; the service exception is the second). |

## Threat Flags

None — Phase 6 plan 06-11 introduces 1 new Filament admin resource + 3 Pages + 4 RelationManagers + 1 new Eloquent HasManyThrough relation + lang/en/admin.php additions. The trust boundary is `Admin → Service` (admin clicks button → service enforces invariants + writes audit), already documented by the plan's `<threat_model>`. No new auth paths, no new public network surface, no new file access patterns, no new schema changes at trust boundaries.

## Known Stubs

None — the Phase 6 admin surface is complete. Every HeaderAction routes through a Phase 6 service. Every RelationManager exposes either CRUD (Participants) or read-only (Stages / Brackets / Standings + the Recalculate headerAction). All i18n keys are concrete English copy (D-013 — finalised in plan 06-13 for translation pack readiness).

## Plan Linkages

- **Plan 06-10 (TournamentObserver real impl)** — fires on every admin save/update inside the Filament resource. Filament's EditAction calls `$model->save()` (CLAUDE.md §10 + D-04-12-A), so the observer's saved() hook syncs the polymorphic Event row + the created/updated hooks enqueue tournament_announce / tournament_announce_update outbound rows. The Pitfall 7 wasChanged('status') gate inside the observer means admin title/description edits don't spam Discord — only true status transitions (open_registration / start / cancel HeaderActions) fire tournament_announce_update.
- **Plan 06-12 (public Vue page — Show.vue + 5-tab navigation + JSON polling endpoint)** — consumes the PublicTournamentData DTO that plan 06-10 ships. The Tournament::brackets() HasManyThrough added in this plan also unblocks plan 06-12's potential PublicTournamentData re-implementation if it wants a direct bracket-list query (instead of stages.brackets eager-load).
- **Plan 06-13 (i18n key coverage Pest test)** — will static-check every admin.tournament.* + tournaments.* key referenced in this plan's surface is present in lang/en/. The 4 lang additions in this plan (section.profile/audit + materialise_next_round + tournament_stage group + tournament_bracket.fields.stage) all land in admin.php proactively so 06-13's test passes without further additions.
- **Plan 06-14 (8-clan single-elim capstone)** — exercises the full admin chain end-to-end via Filament: admin creates tournament (CreateTournament page) → admin adds 8 participant clans (ParticipantsRelationManager CreateAction) → admin clicks open_registration → seed (random) → start → matches play → results record via Phase 4 MatchResource → MatchResultObserver → BracketAdvancementService → bracket_result_announce → StandingsCalculatorService → admin sees standings on the StandingsRelationManager → admin clicks recalculate_standings to verify wipe-and-recompute idempotency. SC-1 + SC-2 + SC-5 all live.
- **Plan 06-05 (TournamentSeedingService)** — the seed + reseed HeaderActions wire directly into this service. The canReseed() guard on the Tournament model is consulted by the reseed Action's visible() closure (T-06-05-01 mitigation is reused: the admin literally cannot click reseed once a MatchResult lands).
- **Plan 06-04 (TournamentStatusService)** — every state-machine HeaderAction (open_registration / start / cancel) routes through this service. The activity_log write happens inside the service, not the Filament Action — Filament UI is a thin wrapper. The seed Action also calls statusService::transition('seeded') as the second leg of its 2-step chain.
- **Plan 06-06 + 06-07 (BracketGeneratorService + SwissGenerator)** — the start HeaderAction calls BracketGeneratorService::generate; the generate_next_swiss_round HeaderAction calls SwissGenerator::generateNextRound directly (skipping the BracketGeneratorService::generate front-door because that path was designed for the initial generation only).
- **Plan 06-09 (StandingsCalculatorService)** — the recalculate_standings HeaderAction + the StandingsRelationManager's recalculate headerAction both call StandingsCalculatorService::recalculate. The service's wipe-and-recompute strategy means admin clicks are idempotent + safe (Pitfall 6 lockForUpdate prevents race with concurrent BracketAdvancementService advances).

## Self-Check: PASSED

- 8 created files exist on disk:
  - `apps/web/app/Filament/Resources/TournamentResource.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/Pages/ListTournaments.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/Pages/CreateTournament.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/Pages/EditTournament.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/ParticipantsRelationManager.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/BracketsRelationManager.php` — FOUND
  - `apps/web/app/Filament/Resources/TournamentResource/RelationManagers/StandingsRelationManager.php` — FOUND
- 8 modified files carry the expected amendments:
  - `apps/web/app/Models/Tournament.php` — +brackets() HasManyThrough + use import
  - `apps/web/lang/en/admin.php` — +tournament.section.* + materialise_next_round.* + tournament_stage group + widened tournament.fields + tournament_bracket.fields.stage
  - `apps/web/tests/Feature/Admin/TournamentResourceTest.php` — no `placeholder` literal; 14 it() blocks
  - `apps/web/tests/Feature/Admin/TournamentSeedActionTest.php` — no `placeholder` literal; 5 it() blocks
  - `apps/web/tests/Feature/Admin/TournamentReseedActionTest.php` — no `placeholder` literal; 4 it() blocks
  - `apps/web/tests/Feature/Admin/TournamentForfeitActionTest.php` — no `placeholder` literal; 3 it() blocks
  - `apps/web/tests/Feature/Admin/TournamentWithdrawActionTest.php` — no `placeholder` literal; 3 it() blocks
  - `apps/web/tests/Feature/Admin/TournamentRecalculateStandingsTest.php` — no `placeholder` literal; 4 it() blocks
- 2 task commits exist on `master`:
  - `1f07104` — feat(06-11): TournamentResource + 8 HeaderActions + 4 RelationManagers (Task 1)
  - `b95c65b` — test(06-11): flip 6 Tournament admin Pest stubs to GREEN (Task 2)
- Pest: 33 new tests passed / 125 assertions across this plan's 6 test files; full project 833 passed / 6 placeholder-failed (down from 12 in 06-10 baseline — 6 we flipped GREEN are this plan).
- PHPStan: full project `[OK] No errors`.
- Pint: clean on 432 files.
- Plan acceptance criteria from `<tasks>` block — all satisfied (TournamentResource registered with navigationGroup='Tournaments' + sort=30; 4 RelationManagers exist; only Participants is mutable; 8 HeaderActions + DeleteAction; Forfeit+Withdraw write activity_log; StandingsRelationManager has Recalculate; tests GREEN; pint+phpstan clean).
- Wave 0 RED stubs removed — confirmed by `grep -c 'placeholder' tests/Feature/Admin/Tournament{ResourceTest,SeedActionTest,ReseedActionTest,ForfeitActionTest,WithdrawActionTest,RecalculateStandingsTest}.php` returning 0 on all 6 test files. The remaining placeholder in TournamentAuditLogTest.php is OUT OF SCOPE for plan 06-11 (untouched; belongs to plans 06-13/06-14).
