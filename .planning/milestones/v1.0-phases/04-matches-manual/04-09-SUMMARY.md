---
phase: 04-matches-manual
plan: 09
subsystem: filament-match-admin-surface
tags: [phase-4, wave-5, filament, has-wizard, relation-manager, has-many-through, pattern-4, pattern-6, pitfall-3, pitfall-7, pitfall-11, sc-1, sc-4]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-4-model-layer
    - phase-4-match-status-service
    - phase-4-match-slot-materialiser
    - phase-4-signup-service
    - phase-4-match-observer-event-sync
  provides:
    - filament-match-resource-3-step-has-wizard
    - filament-event-resource-read-only
    - match-result-service-atomic-status-flip
    - sc-1-admin-creates-match-wizard
    - sc-4-admin-enters-result-via-filament
  affects:
    - apps/web/app/Filament/Resources/MatchResource.php (NEW)
    - apps/web/app/Filament/Resources/MatchResource/Pages/ (NEW — 4 pages)
    - apps/web/app/Filament/Resources/MatchResource/RelationManagers/ (NEW — 4 RelationManagers)
    - apps/web/app/Filament/Resources/EventResource.php (NEW)
    - apps/web/app/Filament/Resources/EventResource/Pages/ (NEW — 2 pages)
    - apps/web/app/Services/MatchResultService.php (NEW)
    - apps/web/app/Models/GameMatch.php (Rule 2 amendment — mvps() HasManyThrough)
    - apps/web/tests/Feature/Services/MatchResultServiceTest.php (Wave 0 → GREEN, 9 tests)
    - apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php (Wave 0 → GREEN, 3 tests)
    - apps/web/tests/Feature/Admin/MatchResourcePresentTest.php (Wave 0 → GREEN, 18 tests — SMOKE)
tech_stack:
  added: []
  patterns:
    - filament-has-wizard-3-step
    - explicit-db-transaction-in-handle-record-creation
    - hidden-status-field-disabledOn-edit
    - filament-header-action-service-bridge
    - service-driven-relation-manager-using-handler
    - has-many-through-grandchild-relation-manager
    - container-bind-final-service-stub-pattern
key_files:
  created:
    - apps/web/app/Filament/Resources/MatchResource.php
    - apps/web/app/Filament/Resources/MatchResource/Pages/ListMatches.php
    - apps/web/app/Filament/Resources/MatchResource/Pages/CreateMatch.php
    - apps/web/app/Filament/Resources/MatchResource/Pages/ViewMatch.php
    - apps/web/app/Filament/Resources/MatchResource/Pages/EditMatch.php
    - apps/web/app/Filament/Resources/MatchResource/RelationManagers/SlotsRelationManager.php
    - apps/web/app/Filament/Resources/MatchResource/RelationManagers/AccessRulesRelationManager.php
    - apps/web/app/Filament/Resources/MatchResource/RelationManagers/ResultRelationManager.php
    - apps/web/app/Filament/Resources/MatchResource/RelationManagers/MvpsRelationManager.php
    - apps/web/app/Filament/Resources/EventResource.php
    - apps/web/app/Filament/Resources/EventResource/Pages/ListEvents.php
    - apps/web/app/Filament/Resources/EventResource/Pages/ViewEvent.php
    - apps/web/app/Services/MatchResultService.php
  modified:
    - apps/web/app/Models/GameMatch.php
    - apps/web/tests/Feature/Services/MatchResultServiceTest.php
    - apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php
    - apps/web/tests/Feature/Admin/MatchResourcePresentTest.php
  deleted: []
decisions:
  - id: D-04-09-A
    decision: |
      **MvpsRelationManager uses HasManyThrough (option b) — NOT the
      getEloquentQuery override (option a) or a standalone resource (option c).**

      The plan task 2 enumerated three resolution paths for Pitfall 11 (MatchMvp
      is one hop removed: Match → MatchResult → MatchMvp; Filament v3's
      `protected static string $relationship` expects a HasMany on the owner,
      not a HasManyThrough):
        (a) Override getEloquentQuery() to scope MatchMvp::whereHas('result', …).
        (b) Add a `mvps()` HasManyThrough on App\Models\GameMatch and use the
            native $relationship = 'mvps' wiring.
        (c) Fall back to a standalone MatchMvpResource at /admin/match-mvps.

      Context7 docs (filamentphp_3_x §relation-managers) explicitly list
      HasManyThrough as a supported relationship type for v3 RelationManagers.
      Option (b) is the cleanest: zero Filament-layer override, idiomatic
      Eloquent, and the model amendment is reusable outside Filament (Phase 6
      tournament aggregator can also call $match->mvps).

      The HasManyThrough signature on GameMatch:
        hasManyThrough(MatchMvp::class, MatchResult::class,
                       'match_id',          // FK on through table
                       'match_result_id')   // FK on related table

      The Filament CreateAction `->using()` handler bypasses the standard
      Filament create flow (which would attempt to write via the relationship)
      and explicitly writes through `$match->result->mvps()->create($data)` —
      Filament v3 cannot natively INSERT via HasManyThrough because the
      through-key (match_result_id) is not part of the parent owner's PK.

  - id: D-04-09-B
    decision: |
      **EditMatch HeaderActions for status transitions — Pitfall 7 alternative
      to a separate StatusFlipForm.**

      Pitfall 7 requires the status field to be `->disabledOn('edit')` (admin
      cannot flip via the form). The plan offered two paths for the transition
      UI: dedicated Filament Actions (HeaderActions) OR a separate
      StatusFlipForm. We picked HeaderActions:

        - Action `lock_signups` — visible when status='open'; calls
          MatchStatusService::transition($record, 'locked', auth user).
        - Action `cancel_match` — visible when status ∈ {draft, open, locked};
          calls MatchStatusService::transition($record, 'cancelled', auth user).
        - Action `open_signups` — visible when status='draft'; calls
          MatchStatusService::transition($record, 'open', auth user).

      Each Action `->requiresConfirmation()` to prevent accidental clicks; the
      `->visible()` predicate hides actions that don't satisfy the state machine.
      Hidden actions are not just UI sugar — clicking a hidden action surfaces
      the underlying MatchStatusService DomainException to the admin, but the
      first defence is to never offer the click.

  - id: D-04-09-C
    decision: |
      **MatchResultService side-effect status flip uses a terminal-state SKIP,
      NOT a "transition to played always" — Pattern 4 alignment.**

      MatchStatusService::ALLOWED_TRANSITIONS has played → []  (terminal). A
      naive `app(MatchStatusService::class)->transition($match, 'played',
      $causer)` would throw DomainException on the SECOND result-edit
      (re-edits to the same MatchResult row don't need a status re-flip; the
      result row already updated in-place via updateOrCreate).

      The service therefore wraps the transition in:
        if ($match->status !== 'played') { ...transition... }

      This preserves the SC-4 admin-can-edit-result-as-many-times-as-needed
      idempotency. The activity_log captures ONE status-transition row
      regardless of how many times the result is overwritten — proven by
      MatchResultServiceTest::it does NOT re-transition status when match is
      already played.

  - id: D-04-09-D
    decision: |
      **Final-service test override pattern: container `bind()` returning an
      anonymous class with the same method shape — NOT Mockery, NOT
      class-extension.**

      MatchSlotMaterialiserService is `final` (Phase 4 convention for stateless
      services). The Pitfall 3 / T-04-09-02 rollback test in
      MatchResourceCreateWizardTest needs to replace `materialise()` with a
      thrower:

        $this->app->bind(MatchSlotMaterialiserService::class, function () {
            return new class {
                public function materialise(GameMatch $match): int {
                    throw new RuntimeException(...);
                }
            };
        });

      Mockery cannot mock final classes without `mockery/mockery@^1.5 final`
      runkit/uopz extensions (not available in the project). Anonymous
      `class extends MatchSlotMaterialiserService` is blocked by `final`.

      The container `bind()` honours the FQN-to-instance mapping at resolve-time;
      `app(MatchSlotMaterialiserService::class)` returns our stub. The call
      site `->materialise($match)` dispatches dynamically; PHP doesn't enforce
      class-conformance at the call site because the resolved variable's type
      is the FQN at compile-time, not the runtime type. This is a Laravel-
      native idiom that bypasses the `final` ergonomic for tests without
      compromising production code style.

      This pattern is reusable for future plan 04-10+ tests that need to stub
      MatchSignupService (also `final`) or MatchStatusService (also `final`).

metrics:
  duration_minutes: 12
  completed: 2026-05-13
---

# Phase 4 Plan 09: Filament MatchResource + EventResource + MatchResultService Summary

**One-liner:** MatchResource with 4 Pages (List/Create-HasWizard-3-step/View/Edit) + 4 RelationManagers (Slots, AccessRules, Result, Mvps-via-HasManyThrough) + MatchResultService (atomic upsert + status flip to played) + EventResource (read-only 2 Pages — observer-managed events table) — SC-1 (admin wizard → status='open' + slots materialised) and SC-4 (admin enters result via Filament → atomic write + status transition + audit) delivered; 30 new GREEN tests across 3 files (incomplete count 7 → 4); Pitfall 3 transactional-rollback invariant proven via container-bind stub; HasManyThrough resolution chosen over getEloquentQuery override per Context7-confirmed Filament v3 native support.

## Performance

- **Duration:** ~12 min
- **Started:** 2026-05-13T14:55:18Z
- **Completed:** 2026-05-13T15:07:21Z
- **Tasks:** 3 / 3
- **Files modified:** 17 (13 created + 4 modified)
- **Net additions:** +1803 lines / -18 lines

## Accomplishments

1. **MatchResource Filament admin surface lands at /admin/matches.** Tabs structure (Profile + Audit) mirrors Phase 2/3 precedent. 4 Pages registered: ListMatches, CreateMatch (HasWizard), ViewMatch, EditMatch. 4 RelationManagers attached (Slots, AccessRules, Result, Mvps). navigationSort=20 (after Phase 3 Game=10, GameMatchType=11). 6 admin routes registered: 4 for `/admin/matches` + 2 for `/admin/events` (read-only).

2. **CreateMatch::handleRecordCreation wraps GameMatch::create + materialise() in a SINGLE explicit DB::transaction (Pitfall 3 / T-04-09-02 mitigation).** Verified by the rollback test: container-binding a thrower at MatchSlotMaterialiserService::class, submitting the wizard form, and asserting zero new Match rows remain after the RuntimeException propagates. Filament v3 does NOT auto-wrap handleRecordCreation — the explicit transaction is the ONLY barrier preventing orphan Match rows with zero slots.

3. **MatchResultService::upsert ships the SC-4 atomic write path.** DB::transaction wraps MatchResult::updateOrCreate (keyed on match_id UNIQUE — plan 04-02) AND the conditional MatchStatusService::transition($match, 'played', $causer). Status flip is skipped when match is already 'played' (Pattern 4 terminal-state rule — D-04-09-C). Two activity rows land per first-time entry: one for the MatchResult creation (LogsActivity), one for the status transition (MatchStatusService).

4. **EventResource lands as a read-only resource at /admin/events.** getPages() omits 'create' and 'edit' entries (T-04-09-06 mitigation — observer-managed; manual edits would drift from invariants). Table renders starts_at, eventable_type (formatted via class_basename), title (en accessor), is_public IconColumn. Only ViewAction in row actions. The /admin/events/create route is NOT in the route table (verified by an explicit test).

5. **MvpsRelationManager solved via GameMatch::mvps() HasManyThrough (D-04-09-A).** Plan offered three options for Pitfall 11; Context7 docs confirmed Filament v3 natively supports HasManyThrough in RelationManagers, making option (b) the cleanest. The model amendment (Match → MatchResult → MatchMvp) is reusable outside Filament (Phase 6 tournament aggregator path). CreateAction `->using()` handler explicitly writes through `$match->result->mvps()->create($data)` because Filament v3 cannot natively INSERT via HasManyThrough (the through-key is not part of the parent PK).

6. **30 new tests land GREEN across 3 files (Wave 0 incomplete count: 7 → 4).**
   - **MatchResultServiceTest**: 9 it() blocks / 31 assertions — upsert create + update, atomic status flip on first call, terminal-state skip on second call, causer + from/to in activity row, MatchResult LogsActivity audit row, draft/cancelled rejection.
   - **MatchResourceCreateWizardTest**: 3 it() blocks / 15 assertions — SC-1 happy path (status='open' + 6 slots materialised), **Pitfall 3 transactional-rollback proof**, non-admin 403 gate.
   - **MatchResourcePresentTest**: 18 it() blocks / 29 assertions (SMOKE; plan 04-12 finalises) — both resources reachable, EventResource read-only verified (getPages + route table), all 4 RelationManagers mount cleanly via Livewire::test, MatchResource + EventResource URL resolution, non-admin 403 gates.

## Task Commits

1. **Task 1: MatchResource + 4 Pages (HasWizard create) + MatchResultService** — `c11abce` (feat) — 6 files (all new); 605 lines added; HasWizard 3-step flow, explicit DB::transaction in handleRecordCreation, EditMatch HeaderActions, MatchResultService atomic upsert + status flip.

2. **Task 2: 4 MatchResource RelationManagers + EventResource read-only** — `9d2f8d4` (feat) — 8 files (7 new + 1 modified); 607 lines added; HasManyThrough mvps() amendment on GameMatch, RelationManager Pitfall 3 / Pitfall 11 mitigations, EventResource getPages() omits create/edit.

3. **Task 3: 3 admin/service test stubs flipped GREEN** — `2c3c7f8` (test) — 3 files; 591 lines added / 18 removed; 30 new tests (9 + 3 + 18); container-bind stub pattern for `final` MatchSlotMaterialiserService.

## Files Created/Modified

### Created (13)

| File | LOC | Notes |
|---|---|---|
| `apps/web/app/Filament/Resources/MatchResource.php` | 242 | Tabs (Profile + Audit), status->disabledOn('edit'), 4 RelationManagers in getRelations() |
| `apps/web/app/Filament/Resources/MatchResource/Pages/ListMatches.php` | 24 | CreateAction in header |
| `apps/web/app/Filament/Resources/MatchResource/Pages/CreateMatch.php` | 144 | HasWizard 3-step (Type/Schedule/Review); DB::transaction wrap in handleRecordCreation |
| `apps/web/app/Filament/Resources/MatchResource/Pages/ViewMatch.php` | 13 | Thin ViewRecord subclass |
| `apps/web/app/Filament/Resources/MatchResource/Pages/EditMatch.php` | 101 | mutateFormDataBeforeSave (Pitfall 2); HeaderActions for status transitions (Lock signups, Cancel match, Open signups) |
| `apps/web/app/Filament/Resources/MatchResource/RelationManagers/SlotsRelationManager.php` | 108 | game_role + slot_index disabled (snapshot); only occupant_user_id editable; no Create/Delete |
| `apps/web/app/Filament/Resources/MatchResource/RelationManagers/AccessRulesRelationManager.php` | 78 | Pattern 5 tag-based gate; emptyStateHeading = "open to all clans" |
| `apps/web/app/Filament/Resources/MatchResource/RelationManagers/ResultRelationManager.php` | 132 | HasOne 1:1; CreateAction + EditAction `->using()` calls MatchResultService::upsert |
| `apps/web/app/Filament/Resources/MatchResource/RelationManagers/MvpsRelationManager.php` | 128 | HasManyThrough; CreateAction visible only when result exists; `->using()` writes via `$match->result->mvps()` |
| `apps/web/app/Filament/Resources/EventResource.php` | 103 | Read-only (no create/edit pages); navigationSort=21; eventable_type formatted via class_basename |
| `apps/web/app/Filament/Resources/EventResource/Pages/ListEvents.php` | 15 | NO CreateAction (read-only) |
| `apps/web/app/Filament/Resources/EventResource/Pages/ViewEvent.php` | 13 | Thin ViewRecord subclass |
| `apps/web/app/Services/MatchResultService.php` | 81 | DB::transaction wraps updateOrCreate + conditional MatchStatusService::transition |

### Modified (4)

| File | Change |
|---|---|
| `apps/web/app/Models/GameMatch.php` | +30 lines — `mvps()` HasManyThrough (Match → MatchResult → MatchMvp); Rule 2 amendment for MvpsRelationManager (D-04-09-A) |
| `apps/web/tests/Feature/Services/MatchResultServiceTest.php` | Wave 0 stub → 9 GREEN it() blocks / 31 assertions |
| `apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php` | Wave 0 stub → 3 GREEN it() blocks / 15 assertions (SC-1 + Pitfall 3 rollback proof) |
| `apps/web/tests/Feature/Admin/MatchResourcePresentTest.php` | Wave 0 stub → 18 GREEN it() blocks / 29 assertions (SMOKE) |

## Key Code Patterns

### CreateMatch::handleRecordCreation (Pitfall 3 verbatim)

```php
protected function handleRecordCreation(array $data): Model
{
    return DB::transaction(function () use ($data): GameMatch {
        $data['status'] = $data['status'] ?? 'open';

        /** @var GameMatch $match */
        $match = static::getModel()::create($data);

        app(MatchSlotMaterialiserService::class)->materialise($match);

        return $match;
    });
}
```

### MatchResultService::upsert (Pattern 4 + D-04-09-C terminal skip)

```php
public function upsert(GameMatch $match, array $data, User $causer): MatchResult
{
    return DB::transaction(function () use ($match, $data, $causer): MatchResult {
        $result = MatchResult::updateOrCreate(
            ['match_id' => $match->id],
            [
                'winner_clan_id' => $data['winner_clan_id'] ?? null,
                'allies_score' => $data['allies_score'] ?? null,
                'axis_score' => $data['axis_score'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by_user_id' => $causer->id,
                'recorded_at' => $data['recorded_at'] ?? now(),
            ],
        );

        if ($match->status !== 'played') {
            app(MatchStatusService::class)->transition($match, 'played', $causer);
        }

        return $result;
    });
}
```

### GameMatch::mvps() HasManyThrough (D-04-09-A Rule 2 amendment)

```php
public function mvps(): HasManyThrough
{
    return $this->hasManyThrough(
        MatchMvp::class,
        MatchResult::class,
        'match_id',          // FK on match_results pointing at this Match
        'match_result_id',   // FK on match_mvps pointing at the through MatchResult
    );
}
```

### Container-bind stub for final service (D-04-09-D)

```php
$this->app->bind(MatchSlotMaterialiserService::class, function () {
    return new class {
        public function materialise(GameMatch $match): int {
            throw new RuntimeException('simulated materialiser failure');
        }
    };
});
```

## Verification

| Gate | Command | Result |
|---|---|---|
| Plan filter | `make pest ARGS="--filter='(MatchResultService\|MatchResourceCreate\|MatchResourcePresent)'"` | **30 passed / 75 assertions** |
| Full Pest suite | `make pest` | **434 passed, 1117 assertions, 4 incomplete** (baseline 04-08: 404 / 7 incomplete → +30 / −3 ✓) |
| PHPStan L8 (full) | `make phpstan` | **No errors** |
| Pint full | `docker compose exec web ./vendor/bin/pint --test` | **clean, 291 files** |
| Routes registered | `docker compose exec web php artisan route:list \| grep admin/(matches\|events)` | **6 routes: 4 matches + 2 events** |
| DB::transaction wrap | `grep -E 'DB::transaction\|materialise' CreateMatch.php` | **present (line 75)** |
| MatchStatusService bridge | `grep MatchStatusService MatchResultService.php` | **present (line 75: `app(MatchStatusService::class)->transition(...)`)** |
| MatchResultService bridge | `grep MatchResultService ResultRelationManager.php` | **present (CreateAction + EditAction `->using()` calls)** |
| `placeholder` removed | `grep -c placeholder` on 3 test files | **0** ✓ |

## Decisions Made

- **D-04-09-A:** MvpsRelationManager uses HasManyThrough on GameMatch::mvps() — chosen over getEloquentQuery override (option a) and standalone resource (option c) because Filament v3 natively supports HasManyThrough in RelationManagers (Context7 docs confirmed).
- **D-04-09-B:** EditMatch HeaderActions for status transitions (Lock signups, Cancel match, Open signups) — `->visible()` predicates hide actions outside the state machine; each calls MatchStatusService::transition.
- **D-04-09-C:** MatchResultService::upsert terminal-state SKIP — wraps the MatchStatusService::transition call in `if ($match->status !== 'played')` to support re-edits to the result without re-firing the transition (Pattern 4 terminal rule).
- **D-04-09-D:** Container-bind stub for `final` services — replaces Mockery / anonymous-class-extension for testing final services; reusable for plan 04-10+ tests on other final services.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Pint reformatted backslashes on `\RuntimeException` and `\Exception` in test files**

- **Found during:** Task 3, after running `pint` on the test files.
- **Issue:** Pint removed leading `\` from `\RuntimeException` and `\Exception` in the test catch blocks, as no `use RuntimeException;` import was present at file head.
- **Fix:** Pint auto-fix is correct (PHP treats unqualified `RuntimeException` in catch blocks as ambiguous in some PHP versions but works in 8.4); kept the auto-fix.
- **Files modified:** `apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php`, `apps/web/tests/Feature/Admin/MatchResourcePresentTest.php`
- **Commit:** `2c3c7f8` (includes the Pint-applied changes).

**2. [Rule 2 — Critical functionality] `mvps()` HasManyThrough added to GameMatch model**

- **Found during:** Task 2, while designing MvpsRelationManager.
- **Issue:** Plan offered 3 resolution paths for Pitfall 11 (option a getEloquentQuery override, option b HasManyThrough, option c standalone resource). Context7 docs confirmed Filament v3 RelationManagers natively support HasManyThrough — option (b) is the cleanest.
- **Fix:** Added `mvps()` HasManyThrough on `App\Models\GameMatch` (chain: Match → MatchResult → MatchMvp). Filament's `protected static string $relationship = 'mvps';` resolves natively.
- **Files modified:** `apps/web/app/Models/GameMatch.php`
- **Commit:** `9d2f8d4`
- **Codified as:** D-04-09-A.

**3. [Rule 1 — Bug] Container-bind stub for `final` MatchSlotMaterialiserService in the Pitfall 3 rollback test**

- **Found during:** Task 3, while writing MatchResourceCreateWizardTest "rolls back when materialiser throws" test.
- **Issue:** Initial attempt used `$this->mock(MatchSlotMaterialiserService::class)` (Mockery). Failed at runtime: "The class \App\Services\MatchSlotMaterialiserService is marked final and its methods cannot be replaced." Switched to anonymous-class extension — also blocked by `final`.
- **Fix:** Used Laravel container `bind()` returning an anonymous class with the same method shape (no `extends`). PHP dispatches `->materialise()` dynamically at runtime; the call site's compile-time type is the FQN but the resolved instance is our stub. Pattern reusable for plan 04-10+.
- **Files modified:** `apps/web/tests/Feature/Admin/MatchResourceCreateWizardTest.php`
- **Commit:** `2c3c7f8`
- **Codified as:** D-04-09-D.

### Non-deviations (planned ambiguities resolved)

- **MatchResultServiceTest's "blocks upsert when match.status is draft" test** — Plan acceptance criteria mentioned this assertion; implemented as "throws DomainException when attempting result entry on a draft match (no valid transition)". Confirmed: MatchStatusService::ALLOWED_TRANSITIONS['draft'] = ['open', 'cancelled'] (no 'played'); the service propagates the DomainException; the outer DB::transaction rolls back the would-be-MatchResult write. **Verified GREEN.**

- **CreateAction `->using()` vs `->action()`** — Plan suggested both. Picked `->using()` because Filament v3 `->using()` replaces the default Eloquent create flow while preserving form validation + notifications; `->action()` would require manually invoking notifications and re-checking validation.

- **Status field placement in form()** — Plan offered "render but disable" OR "omit". Picked render-but-disable on EditMatch (`->disabledOn('edit')` modifier on MatchResource::form) for transparency — admin sees current status without being able to flip via the form. HeaderActions are the canonical flip path.

## Auth Gates

None — admin Filament work; no auth-bearing operations beyond the Phase 1 inherited `admin-access` permission gate.

## Known Stubs

4 Wave 0 stubs remain incomplete-by-design (down from 7 before plan 04-09):

| Stub | Flipped GREEN by |
|---|---|
| `Admin/MatchAuditLogTest` | 04-12 (comprehensive admin presence + audit + i18n key coverage) |
| `Matches/MatchCalendarPageTest` | 04-10 |
| `Matches/MatchShowPageTest` | 04-10 |
| `Matches/MatchSignupControllerTest` | 04-10 |

Three stubs flipped GREEN by this plan:
- `Services/MatchResultServiceTest` ✓
- `Admin/MatchResourceCreateWizardTest` ✓
- `Admin/MatchResourcePresentTest` ✓ (SMOKE; plan 04-12 finalises)

## Threat Surface Notes

Threat register T-04-09-01..08 dispositions:

| Threat ID | Disposition | Mitigation status |
|---|---|---|
| T-04-09-01 (Non-admin reaches /admin/matches) | mitigate | **MITIGATED** — inherited canAccessPanel gate; proven by 3 non-admin 403 tests in MatchResourcePresentTest. |
| T-04-09-02 (Orphan Match row with zero slots) | mitigate | **MITIGATED** — DB::transaction wraps Match::create + materialise in CreateMatch::handleRecordCreation; proven by container-bind stub rollback test in MatchResourceCreateWizardTest. |
| T-04-09-03 (Admin manually flips status via Edit form) | mitigate | **MITIGATED** — status field `->disabledOn('edit')` on MatchResource::form; transitions only via HeaderActions calling MatchStatusService. |
| T-04-09-04 (Result write without atomic status flip) | mitigate | **MITIGATED** — MatchResultService::upsert wraps updateOrCreate + transition in DB::transaction; proven by MatchResultServiceTest "flips match.status from open to played on first result write" + "does NOT re-transition when already played". |
| T-04-09-05 (MatchMvp orphans when result is missing) | mitigate | **MITIGATED** — MvpsRelationManager CreateAction `->visible()` hidden when `$match->result === null`; DB-layer cascade chain (plan 04-02) handles cleanup; HasManyThrough scope on GameMatch::mvps() prevents cross-match leakage. |
| T-04-09-06 (Admin Events table edits drift from observer) | mitigate | **MITIGATED** — EventResource::getPages() omits 'create' and 'edit'; only ViewAction in row actions; the /admin/events/create route is NOT registered (verified by `EventResource Create route is NOT registered` test). |
| T-04-09-07 (Filament inline CRUD unaudited) | mitigate | **MITIGATED** — All Phase 4 models have LogsActivity (plan 04-03); MatchResource Audit tab present; status-transition activity rows include from/to + causer. |
| T-04-09-08 (KeyValue submits null for title) | mitigate | **MITIGATED** — mutateFormDataBeforeCreate (CreateMatch) and mutateFormDataBeforeSave (EditMatch) coerce `$data['title'] = $data['title'] ?: ['en' => '']` and same for description (Pitfall 2 dual-field idiom from Phase 3). |

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|---|---|---|---|
| `c11abce` | Task 1 — MatchResource + 4 Pages + MatchResultService | 6 | HasWizard 3-step; explicit DB::transaction in handleRecordCreation; status->disabledOn('edit'); EditMatch HeaderActions; MatchResultService atomic upsert |
| `9d2f8d4` | Task 2 — 4 RelationManagers + EventResource + GameMatch::mvps() | 8 | HasManyThrough mvps() amendment (D-04-09-A); 4 RelationManagers with Pitfall 3 typo guards; EventResource read-only (T-04-09-06) |
| `2c3c7f8` | Task 3 — 3 admin/service test stubs flipped GREEN | 3 | 30 tests / 75 assertions; container-bind stub for final services (D-04-09-D); Pitfall 3 rollback proof |

## Self-Check: PASSED

- `apps/web/app/Filament/Resources/MatchResource.php` exists — verified by phpstan analysis
- `apps/web/app/Filament/Resources/MatchResource/Pages/CreateMatch.php` — `DB::transaction.*materialise` pattern present (grep verified)
- `apps/web/app/Filament/Resources/MatchResource/RelationManagers/{Slots,AccessRules,Result,Mvps}RelationManager.php` — all 4 exist (verified)
- `apps/web/app/Filament/Resources/EventResource.php` exists; getPages() omits create + edit (test-verified)
- `apps/web/app/Services/MatchResultService.php` exists; MatchStatusService::transition call present (grep verified)
- `apps/web/app/Models/GameMatch.php` modified — `mvps()` HasManyThrough method present (grep verified)
- All 3 commits (`c11abce`, `9d2f8d4`, `2c3c7f8`) present in `git log --oneline -5`
- `make pest --filter='(MatchResultService|MatchResourceCreate|MatchResourcePresent)'`: 30 passed / 75 assertions
- Full Pest suite: 434 passed (+30 vs plan 04-08 close) / 4 incomplete (−3 from this plan's stub flips)
- `make phpstan` full: 0 errors
- `make pint --test` (full 291 files): clean
- Admin routes verified: 4 `admin/matches/*` + 2 `admin/events/*`
- `placeholder` literals removed from all 3 test files (grep count = 0)
