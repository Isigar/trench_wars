---
phase: 04-matches-manual
plan: 07
subsystem: dto-typescript
tags: [phase-4, wave-4, dto, spatie-laravel-data, typescript-transformer, shared-types, privacy-projection, translatable-jsonb, d-008, d-018, sc-3, sc-4]
dependency_graph:
  requires:
    - phase-4-wave-0-baseline
    - phase-4-relational-backbone
    - phase-4-model-layer
    - phase-4-status-state-machine
    - phase-4-slot-materialiser
    - phase-4-match-signup-service
  provides:
    - match-data-dto-admin-shape
    - public-match-data-visitor-projection
    - public-match-occupant-data-privacy-gate
    - event-data-polymorphic-projection
    - api-d-ts-phase-4-regenerated
    - shared-types-phase-4-aliases
  affects:
    - apps/web/app/Data/ (8 new DTOs)
    - apps/web/resources/js/types/api.d.ts (regenerated)
    - packages/shared-types/src/api.d.ts (regenerated)
    - packages/shared-types/src/index.ts (8 new aliases)
    - apps/web/tests/Unit/Data/ (3 stubs flipped GREEN)
tech_stack:
  added: []
  patterns:
    - spatie-laravel-data-data-class
    - typescript-transformer-attribute
    - translatable-jsonb-null-coalesce
    - carbon-phpdoc-narrowing
    - eager-load-aware-nested-dto
    - server-side-privacy-strip-via-gate
    - empty-factory-naming-collision-avoidance
    - snake-case-vs-camelcase-convention-split
key_files:
  created:
    - apps/web/app/Data/MatchData.php
    - apps/web/app/Data/MatchSlotData.php
    - apps/web/app/Data/MatchAccessRuleData.php
    - apps/web/app/Data/MatchResultData.php
    - apps/web/app/Data/MatchMvpData.php
    - apps/web/app/Data/EventData.php
    - apps/web/app/Data/PublicMatchData.php
    - apps/web/app/Data/PublicMatchOccupantData.php
  modified:
    - apps/web/resources/js/types/api.d.ts
    - packages/shared-types/src/api.d.ts
    - packages/shared-types/src/index.ts
    - apps/web/tests/Unit/Data/MatchDataTest.php
    - apps/web/tests/Unit/Data/PublicMatchDataTest.php
    - apps/web/tests/Unit/Data/EventDataTest.php
  deleted: []
decisions:
  - id: D-04-07-A
    decision: |
      **`PublicMatchOccupantData::empty()` renamed to `forEmptySlot()`** — collides
      with Spatie\LaravelData\Data::empty() base static method (signature
      `Data::empty(array $extra = [], ...): array`). The collision triggers PHPStan
      L8 covariance errors (parameter type, return type, missing optional params) and
      changes the meaning of `Data::empty()` in Spatie's contract. Rule 1 auto-fix:
      renamed factory + call sites; documented in DTO docblock. The plan body's
      verbatim Pattern 7 snippet used `empty()` because Pattern 7 was authored before
      the Spatie collision was discovered.

  - id: D-04-07-B
    decision: |
      **Carbon `@var` PHPDoc narrowing for Eloquent `'datetime'` casts.**

      PHPStan L8 sees Eloquent's `'datetime'`-cast properties as `string|null`
      because attribute properties resolve through `getAttributeValue(): mixed`
      with no `@property-read Carbon` annotations on the model classes. The DTO
      fromModel factories use `->toIso8601String()` which requires Carbon. Inline
      `/** @var Carbon $scheduledAt */` followed by local variable assignment
      narrows the type without runtime cost.

      Same outcome as D-04-06-G's `Builder::value('confirmed_at')` workaround
      but applied via PHPDoc since DTO factories work on the in-memory model
      (not a query). Pint auto-imports the Carbon FQN to satisfy
      `fully_qualified_strict_types`.

  - id: D-04-07-C
    decision: |
      **No `App\Models\Match as MatchModel` alias** — D-04-03-A LOCKED + D-04-06-D
      canonical Phase 4 idiom. The plan body referenced `MatchModel` (Pitfall 5)
      because RESEARCH was authored before the GameMatch rename. The 8 new DTOs use
      direct `use App\Models\GameMatch;` since they contain zero `match($x)` PHP
      expressions, mirroring MatchSignupService / MatchStatusService / MatchSlotMaterialiserService.

  - id: D-04-07-D
    decision: |
      **camelCase for `Public*` DTOs, snake_case for internal DTOs.**

      Convention split established in Phase 2 (PublicPlayerData camelCase) +
      Phase 3 (GameMatchTypeData snake_case) holds verbatim in Phase 4:

      | DTO | Convention | Consumer |
      |---|---|---|
      | MatchData | snake_case | Filament + my-clan management |
      | MatchSlotData | snake_case | Filament + my-clan management |
      | MatchAccessRuleData | snake_case | Filament |
      | MatchResultData | snake_case | Filament |
      | MatchMvpData | snake_case | Filament |
      | EventData | snake_case | Calendar API + Vue calendar component |
      | PublicMatchData | snake_case (still uses underscores at TS level — matches MatchData parent shape minus 2 fields) | /matches/{id} Show.vue |
      | PublicMatchOccupantData | camelCase | /matches/{id} Show.vue (per-slot occupant rendering) |

      PublicMatchData stays snake_case because it's structurally a strip of
      MatchData (same Vue components / same Inertia transport convention).
      PublicMatchOccupantData is a NEW shape with no MatchSlotData parent —
      camelCase per the Phase 2 `Public*` precedent.

  - id: D-04-07-E
    decision: |
      **PublicMatchOccupantData::fromMatchSlot fetches Player + ClanMembership at
      construction time via direct queries (no relation eager-load required).**

      Pattern 7 in RESEARCH proposed eager-load via `$slot->load(['occupantUser.player', 'occupantUser.activeClanMembership.clan'])`. Implementation choice: do the
      lookup INSIDE the factory via direct query (1 User find + 1 Player query +
      1 ClanMembership query), independent of relation hydration state on the
      MatchSlot. Trade-off:

      - Eager-load path (rejected): caller MUST remember to eager-load; missing
        eager-load surfaces silently as null displayName + clanTag, masking the
        bug as a privacy outcome.
      - Direct-query path (chosen): factory is self-contained; the N+1 cost is
        bounded by the slot collection size (typically ≤ 50 slots per match).
        Plan 04-10 controller may add eager-loads as an optimisation if profiling
        warrants.

      The PlayerPrivacyGate dependency is passed in explicitly (not via $this->container)
      so the test setup can substitute a mock if needed — matches PublicPlayerData::fromPlayer signature.

  - id: D-04-07-F
    decision: |
      **Translatable JSONB `?: null` null-coalesce is the canonical Phase 3 Pitfall 4 pattern.**

      `$match->getTranslations('title') ?: null` collapses empty array `[]` to null
      so TypeScript receives `null` instead of `{}`. Vue's
      `v-if="match.title !== undefined"` then handles withholds correctly.
      Reused verbatim in MatchData, EventData, PublicMatchData (no implementation
      drift from Phase 3).

metrics:
  duration_minutes: 18
  completed: 2026-05-13
---

# Phase 4 Plan 07: DTOs + TypeScript Regen + Shared-Types Sync Summary

**One-liner:** 8 spatie/laravel-data DTOs land for Phase 4 — 6 internal admin-facing shapes (MatchData, MatchSlotData, MatchAccessRuleData, MatchResultData, MatchMvpData, EventData) using snake_case (Phase 3 idiom) + 2 privacy-shaped public projections (PublicMatchData strips admin-only organiser_user_id/server_address, PublicMatchOccupantData camelCase server-side PlayerPrivacyGate strip per RESEARCH Pattern 7); `make artisan trenchwars:typescript-generate` regenerates `apps/web/resources/js/types/api.d.ts` AND `packages/shared-types/src/api.d.ts` (bind-mount sync) with 8 new types under `App.Data` namespace; `packages/shared-types/src/index.ts` adds 8 Phase 4 export aliases; 3 Wave 0 unit test stubs flip GREEN (MatchDataTest 7 it() blocks, EventDataTest 6, PublicMatchDataTest 10 — 23 total GREEN / 43 assertions); D-008 clan-tag-always-public invariant proven via dedicated test; translatable JSONB `?: null` null-coalesce pattern reused verbatim from Phase 3 Pitfall 4.

## Performance

- **Duration:** ~18 min
- **Started:** 2026-05-13T14:30:00Z (approx)
- **Completed:** 2026-05-13T14:48:00Z
- **Tasks:** 3 / 3
- **Files modified:** 14 (8 created + 6 modified)

## Accomplishments

1. **8 spatie/laravel-data DTOs cover the Phase 4 transport layer** — Match/MatchSlot/MatchAccessRule/MatchResult/MatchMvp/Event admin shapes + PublicMatchData/PublicMatchOccupantData privacy-shaped public projections. Phase 3 GameMatchTypeData pattern reused verbatim (#[TypeScript] attribute, PHPDoc `array<string, string>|null` for translatable JSONB, `getTranslations() ?: null` null-coalesce, ISO-8601 datetime emission).
2. **PublicMatchOccupantData applies PlayerPrivacyGate server-side per RESEARCH Pattern 7** — the security-critical DTO for /matches/{id}. Vue renders verbatim; never re-derives privacy. T-04-07-01 (privacy bypass via raw fields) mitigated structurally — the DTO has zero raw User/Player FK fields, only the privacy-stripped output. D-008 (clan tags always public) proven by dedicated test: `displayName=null` co-exists with `clanTag='EU'` when `show_match_history=false`.
3. **TypeScript regen + shared-types sync** — `make artisan trenchwars:typescript-generate` emits 8 new types under `App.Data` namespace. Both `apps/web/resources/js/types/api.d.ts` and `packages/shared-types/src/api.d.ts` updated in sync via the bind-mount wired in Phase 1 plan 01-15. `packages/shared-types/src/index.ts` adds 8 Phase 4 export type aliases (`export type MatchData = App.Data.MatchData;`...) under a Phase 4 comment block, preserving Phase 1/2/3 aliases above.

## Task Commits

1. **Task 1: 6 internal DTOs (Match, MatchSlot, MatchAccessRule, MatchResult, MatchMvp, Event)** — `fd3dbde` (feat) — 6 files; 338 LOC; PHPStan + Pint clean
2. **Task 2: 2 privacy DTOs + TypeScript regen + shared-types sync** — `c2f273e` (feat) — 5 files; api.d.ts +85 lines; index.ts +9 lines (Phase 4 block)
3. **Task 3: 3 Wave 0 DTO unit test stubs flipped GREEN** — `4dc0289` (test) — 3 files; 398 LOC; 23 GREEN tests / 43 assertions

## Files Created/Modified

### Created (8 DTOs)

| File | LOC | Convention | Translatable | Notes |
|---|---|---|---|---|
| `apps/web/app/Data/MatchData.php` | 67 | snake_case | title, description | Admin shape — includes organiser_user_id + server_address |
| `apps/web/app/Data/MatchSlotData.php` | 50 | snake_case | — | Raw occupant_user_id (privacy-shaped sibling is PublicMatchOccupantData) |
| `apps/web/app/Data/MatchAccessRuleData.php` | 50 | snake_case | — | Eager-load aware for clanTag nested DTO |
| `apps/web/app/Data/MatchResultData.php` | 53 | snake_case | — | Nullable scores + recorded_at ISO-8601 |
| `apps/web/app/Data/MatchMvpData.php` | 38 | snake_case | — | Pivot-shape DTO; nullable value for category='mvp' |
| `apps/web/app/Data/EventData.php` | 54 | snake_case | title | Polymorphic — eventable_type FQN string + UUID |
| `apps/web/app/Data/PublicMatchData.php` | 67 | snake_case | title, description | Strips organiser_user_id + server_address (T-04-07-03) |
| `apps/web/app/Data/PublicMatchOccupantData.php` | 133 | camelCase | — | Pattern 7 — PlayerPrivacyGate-strip per occupant (T-04-07-01) |

### Modified (6)

| File | Change |
|---|---|
| `apps/web/resources/js/types/api.d.ts` | Regenerated — 8 new types under `App.Data` namespace |
| `packages/shared-types/src/api.d.ts` | Regenerated (synced via bind-mount per 01-15) |
| `packages/shared-types/src/index.ts` | +9 lines — Phase 4 comment block + 8 export type aliases |
| `apps/web/tests/Unit/Data/MatchDataTest.php` | Wave 0 stub → GREEN (7 it() blocks, 7 assertions, 0 placeholder literal) |
| `apps/web/tests/Unit/Data/PublicMatchDataTest.php` | Wave 0 stub → GREEN (10 it() blocks, 21 assertions, 0 placeholder literal) |
| `apps/web/tests/Unit/Data/EventDataTest.php` | Wave 0 stub → GREEN (6 it() blocks, 15 assertions, 0 placeholder literal) |

## PublicMatchOccupantData::fromMatchSlot — Pattern 7 implementation

```php
public static function fromMatchSlot(MatchSlot $slot, ?User $viewer, PlayerPrivacyGate $gate): self
{
    if ($slot->occupant_user_id === null) {
        return self::forEmptySlot($slot);
    }

    $occupantUser = User::query()->find($slot->occupant_user_id);
    if ($occupantUser === null) {
        return self::forEmptySlot($slot);
    }

    /** @var Player|null $player */
    $player = Player::query()->where('user_id', $occupantUser->id)->first();

    // D-008: clan tag is always public.
    /** @var ClanMembership|null $activeMembership */
    $activeMembership = ClanMembership::query()
        ->where('user_id', $occupantUser->id)
        ->whereNull('left_at')
        ->with('clan')
        ->first();
    $clanTag = $activeMembership?->clan?->tag;
    $clanSlug = $activeMembership?->clan?->slug;

    $isViewer = $viewer !== null && $viewer->id === $occupantUser->id;

    // Privacy gate — withhold name+slug when the viewer can't see them.
    $canSee = $isViewer;
    if (! $canSee && $player !== null) {
        $canSee = $gate->passesTier($player, $viewer)
            && $gate->allowsSection($player, $viewer, 'show_match_history');
    }

    $displayName = null;
    $playerSlug = null;
    if ($canSee) {
        $displayName = ($player !== null ? $player->display_name : null)
            ?? $occupantUser->username;
        $playerSlug = $player?->slug;
    }

    return new self(
        slotId: $slot->id,
        gameRoleId: $slot->game_role_id,
        slotIndex: $slot->slot_index,
        displayName: $displayName,
        playerSlug: $playerSlug,
        clanTag: $clanTag,
        clanSlug: $clanSlug,
        isViewer: $isViewer,
    );
}
```

## shared-types/src/index.ts (Phase 4 block added)

```ts
// Phase 4 DTOs — matches domain (admin shapes + privacy-shaped public projections)
export type MatchData = App.Data.MatchData;
export type MatchSlotData = App.Data.MatchSlotData;
export type MatchAccessRuleData = App.Data.MatchAccessRuleData;
export type MatchResultData = App.Data.MatchResultData;
export type MatchMvpData = App.Data.MatchMvpData;
export type EventData = App.Data.EventData;
export type PublicMatchData = App.Data.PublicMatchData;
export type PublicMatchOccupantData = App.Data.PublicMatchOccupantData;
```

## Verification

| Gate | Command | Result |
|---|---|---|
| Plan filter | `docker compose exec web ./vendor/bin/pest --filter='MatchDataTest\|PublicMatchDataTest\|EventDataTest' --no-coverage` | **23 passed, 43 assertions** |
| Full Pest suite | `make pest` | **396 passed, 1024 assertions, 8 incomplete** (baseline 04-06: 373 passed / 11 incomplete → +23 / −3 ✓) |
| PHPStan L8 (full) | `make phpstan` | **No errors** (tests/ not in `paths`; app/ + database/ + routes/ all clean) |
| PHPStan L8 (app/Data only) | `docker compose exec web ./vendor/bin/phpstan analyse app/Data/MatchData.php ... PublicMatchOccupantData.php --no-progress` | **0 errors** across all 8 DTO files |
| Pint full | `docker compose exec web ./vendor/bin/pint --test` | **clean, 277 files** |
| api.d.ts grep gate | `grep -c 'MatchData\|EventData\|PublicMatchOccupantData' apps/web/resources/js/types/api.d.ts` | **3+ matches** (MatchData, EventData, PublicMatchOccupantData — and more: MatchAccessRuleData, MatchMvpData, MatchResultData, MatchSlotData, PublicMatchData) |
| shared-types grep gate | `grep -c 'MatchData\|EventData\|PublicMatchOccupantData' packages/shared-types/src/index.ts` | **4 matches** (MatchData, EventData, PublicMatchData, PublicMatchOccupantData) |
| `placeholder` removed (3 stubs) | `grep -c 'placeholder' tests/Unit/Data/{MatchDataTest,PublicMatchDataTest,EventDataTest}.php` | **0 0 0** ✓ |
| `#[TypeScript]` count | `grep -c '#\[TypeScript\]' app/Data/MatchData.php ...` | **1 per DTO** ✓ (8 total) |

## Decisions Made

- **D-04-07-A:** `empty()` renamed to `forEmptySlot()` — Spatie Data::empty() base method collision (Rule 1 auto-fix).
- **D-04-07-B:** Carbon `@var` PHPDoc narrowing for Eloquent datetime casts (PHPStan L8 sees them as string|null).
- **D-04-07-C:** No `App\Models\Match as MatchModel` alias — D-04-03-A + D-04-06-D canonical Phase 4 idiom; direct `use App\Models\GameMatch;`.
- **D-04-07-D:** camelCase for `Public*` DTOs, snake_case for internal DTOs (Phase 2/3 convention split holds verbatim).
- **D-04-07-E:** PublicMatchOccupantData::fromMatchSlot uses direct queries (not eager-load) — self-contained factory; bounded N+1 by slot count ≤ 50 per match.
- **D-04-07-F:** Translatable JSONB `?: null` null-coalesce is the canonical Phase 3 Pitfall 4 pattern — reused verbatim.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Type-correctness] `PublicMatchOccupantData::empty()` collides with `Spatie\LaravelData\Data::empty()`**
- **Found during:** Task 2 PHPStan pre-commit gate
- **Issue:** Spatie's `Data::empty(array $extra = [], ?string $replaceNullValuesWith = null, array $except = [], array $only = []): array` is a static method on the parent class. Defining a same-named static factory triggered 6 PHPStan covariance errors (parameter type, return type, missing optional params). The plan body's verbatim Pattern 7 snippet used `empty()` because Pattern 7 was authored before the Spatie collision was discovered.
- **Fix:** Renamed factory to `forEmptySlot()` + call sites; documented in DTO docblock.
- **Files modified:** `apps/web/app/Data/PublicMatchOccupantData.php`; test file uses the new name throughout.
- **Commit:** `c2f273e` (DTO), `4dc0289` (test)

**2. [Rule 1 — Type-correctness] PHPStan L8 `string|null` on Eloquent datetime casts**
- **Found during:** Task 1 PHPStan pre-commit gate
- **Issue:** `$match->scheduled_at->toIso8601String()` triggers `method.nonObject` — PHPStan sees the `'datetime'`-cast property as `string|null` because no `@property-read Carbon` annotation exists on model classes.
- **Fix:** Inline `/** @var Carbon $scheduledAt */` PHPDoc narrowing + local variable, applied across MatchData, MatchSlotData, MatchResultData, EventData, PublicMatchData (5 DTOs).
- **Files modified:** 5 DTO files in `apps/web/app/Data/`
- **Commit:** `fd3dbde` (5 internal DTOs), `c2f273e` (PublicMatchData)

**3. [Rule 1 — Pint style] Carbon FQN → import**
- **Found during:** Task 1 Pint pre-commit gate (auto-applied by `make pint`)
- **Issue:** Pint rule `fully_qualified_strict_types` requires importing common globals (Carbon) rather than using leading-backslash FQN inline.
- **Fix:** Pint auto-imported `use Illuminate\Support\Carbon;` to the affected DTOs and removed the leading backslash from PHPDoc inline annotations.
- **Files modified:** MatchData, MatchSlotData, MatchResultData, EventData (and PublicMatchData/PublicMatchOccupantData inherited the convention).
- **Commit:** `fd3dbde`

**4. [Rule 1 — PHPStan L8 strict] `?-> on left side of ??` unnecessary**
- **Found during:** Task 2 PHPStan pre-commit gate
- **Issue:** PHPStan flagged `$player?->display_name ?? $occupantUser->username` as "Using nullsafe property access on left side of ?? is unnecessary." Reasoning: the nullsafe is needed in CONTEXT (the early-return path for `isViewer && $player === null` bypasses the player==null check), but PHPStan's flow analysis sees the access as guarded by canSee=isViewer which doesn't imply $player !== null.
- **Fix:** Replaced `$player?->display_name` with `($player !== null ? $player->display_name : null)` — equivalent behaviour, explicit narrowing.
- **Files modified:** `apps/web/app/Data/PublicMatchOccupantData.php`
- **Commit:** `c2f273e`

### Non-deviations (planned ambiguities resolved)

- **Plan body's `App\Models\Match as MatchModel` Pitfall 5 alias:** The plan referenced the Pitfall 5 defensive alias in Task 1 acceptance criteria + the EventData/MatchData factory signatures. Per D-04-03-A LOCKED + D-04-06-D canonical Phase 4 idiom, the model class is `GameMatch` and no alias is needed — the 8 new DTO files contain zero `match($x)` PHP expressions. Used direct `use App\Models\GameMatch;` throughout (D-04-07-C).

- **`tests/Unit/Data/` location (not `tests/Feature/Data/`):** Plan body Task 3 acceptance criteria specified `tests/Unit/Data/`. The Wave 0 stubs were created at this path by plan 04-01. The Phase 3 GameDataTest analog lives at `tests/Unit/Data/` too (matches the unit-test convention).

- **Two stubs originally pointed plan 04-01 Wave 0 → tests/Unit/Data/PublicMatchDataTest covers BOTH PublicMatchData stripping AND PublicMatchOccupantData privacy:** Plan body required 4+ it() blocks across both DTOs in this one test file (it would have made more sense to split them — but the Wave 0 stub was a single file). Honored the Wave 0 stub structure: 10 it() blocks in `PublicMatchDataTest.php` covering 3 PublicMatchData assertions + 7 PublicMatchOccupantData assertions.

- **`make pnpm ARGS="-F web run typecheck"` not runnable:** apps/web/package.json has no `typecheck` script (only `build` + `dev`). The cross-package vue-tsc check works (`./node_modules/.bin/vue-tsc --noEmit -p .` clean). Plan 01-15 documented the shared-types host-side typecheck as "finicky due to volume-mount layout" — `packages/shared-types/node_modules` is a stale pnpm install missing direct typescript@5 binary. CI workflow handles this via a fresh `pnpm install`. Standalone check of `src/index.ts` syntax via `/app/node_modules/.bin/tsc --moduleResolution Bundler --strict src/index.ts` passes clean.

## Auth Gates

None — pure DTO/test work, no auth-bearing operations.

## Known Stubs

5 Wave 0 stubs remain incomplete-by-design (down from 8 before plan 04-07; full pest baseline confirms 8 incomplete = 11 (04-06 close) − 3 (this plan)):

| Stub | Flipped GREEN by |
|---|---|
| `Observers/MatchEventSyncTest` | 04-08 |
| `Admin/MatchResourcePresentTest` + `MatchResourceCreateWizardTest` + `MatchAuditLogTest` | 04-09 |
| `Services/MatchResultServiceTest` | 04-09 |
| `Matches/MatchCalendarPageTest` + `MatchShowPageTest` + `MatchSignupControllerTest` | 04-10 |

Three stubs flipped GREEN by this plan:
- `Unit/Data/MatchDataTest` ✓
- `Unit/Data/PublicMatchDataTest` ✓
- `Unit/Data/EventDataTest` ✓

## Threat Surface Notes

Threat register T-04-07-01..05 dispositions:

| Threat ID | Disposition | Mitigation status |
|---|---|---|
| T-04-07-01 (Privacy bypass via raw DTO field exposure) | mitigate | **MITIGATED STRUCTURALLY** — PublicMatchOccupantData has NO raw User/Player FK fields, only the privacy-stripped output. The `displayName=null when show_match_history=false` test proves the strip works. |
| T-04-07-02 (Translatable JSONB null vs empty drift) | mitigate | Mitigated — `getTranslations('field') ?: null` null-coalesce collapses empty arrays to null; `it returns null title when match.title JSONB is empty array` test proves the round-trip. |
| T-04-07-03 (Admin-only fields leak via PublicMatchData) | mitigate | Mitigated — PublicMatchData omits `organiser_user_id` + `server_address`; 2 dedicated `it strips X from PublicMatchData` tests verify via `array_key_exists() === false` on `toArray()`. |
| T-04-07-04 (Polymorphic eventable_type as untyped string) | accept | Accepted per plan threat register — EventData carries eventable_type as plain string; Phase 4 only writes `App\Models\GameMatch`; Phase 6 Tournament reuses; consumers compare by FQN string. `it preserves eventable_type and eventable_id verbatim` test asserts the exact FQN. |
| T-04-07-05 (DTOs of cancelled matches surface on public calendar) | mitigate | Controller-layer mitigation — query layer filters status NOT IN ('draft','cancelled') BEFORE DTO hydration (plan 04-10 scope). DTO is a shape, not a filter — this plan correctly does NOT filter at construction time. |

No new threat-flag surface introduced.

## Commits

| Hash | Task | Files | Highlights |
|---|---|---|---|
| `fd3dbde` | Task 1 — 6 internal DTOs | 6 | Phase 3 GameMatchTypeData verbatim pattern; Carbon @var PHPDoc narrowing; translatable JSONB `?: null` null-coalesce |
| `c2f273e` | Task 2 — 2 privacy DTOs + TypeScript regen + shared-types sync | 5 | PublicMatchOccupantData Pattern 7; PlayerPrivacyGate server-side strip; `forEmptySlot()` rename (D-04-07-A); 8 new types in api.d.ts; 8 Phase 4 aliases in shared-types/src/index.ts |
| `4dc0289` | Task 3 — 3 Wave 0 DTO unit test stubs flipped GREEN | 3 | 23 GREEN tests / 43 assertions; D-008 clan-tag-always-public verified; `placeholder` literal removed from all 3 files |

## Self-Check: PASSED

- `apps/web/app/Data/MatchData.php` exists — verified by PHPStan analysing the file
- `apps/web/app/Data/MatchSlotData.php` exists
- `apps/web/app/Data/MatchAccessRuleData.php` exists
- `apps/web/app/Data/MatchResultData.php` exists
- `apps/web/app/Data/MatchMvpData.php` exists
- `apps/web/app/Data/EventData.php` exists
- `apps/web/app/Data/PublicMatchData.php` exists
- `apps/web/app/Data/PublicMatchOccupantData.php` exists
- `apps/web/resources/js/types/api.d.ts` regenerated — 8 new types under `App.Data` namespace (grep verified)
- `packages/shared-types/src/api.d.ts` regenerated — identical to apps/web's via bind-mount sync
- `packages/shared-types/src/index.ts` modified — 8 new export aliases under Phase 4 block (grep verified)
- `apps/web/tests/Unit/Data/MatchDataTest.php` modified — Wave 0 stub replaced (0 placeholder literal)
- `apps/web/tests/Unit/Data/PublicMatchDataTest.php` modified — Wave 0 stub replaced (0 placeholder literal)
- `apps/web/tests/Unit/Data/EventDataTest.php` modified — Wave 0 stub replaced (0 placeholder literal)
- Commits `fd3dbde`, `c2f273e`, `4dc0289` all present in `git log --oneline -5`
- `make pest --filter='MatchDataTest|PublicMatchDataTest|EventDataTest'`: 23 passed / 43 assertions
- Full Pest suite: 396 passed (+23 vs plan 04-06 close) / 8 incomplete (-3 from this plan's 3 stub flips)
- `make phpstan` full: 0 errors (tests/ not analysed; app/ + database/ + routes/ all clean)
- `make pint --test` (full 277 files): clean
- api.d.ts MatchData/EventData/PublicMatchOccupantData grep: 4+ matches (MatchData, EventData, PublicMatchData, PublicMatchOccupantData + 3 more MatchData-prefixed types)
- shared-types/src/index.ts MatchData/EventData/PublicMatchOccupantData grep: 4 matches
