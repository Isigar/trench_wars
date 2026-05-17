---
phase: 03-games-match-types
plan: 04
subsystem: domain/dtos
tags:
  - wave-2
  - dtos
  - spatie-laravel-data
  - typescript-transformer
  - has-translations
  - pitfall-4
  - d-007
  - d-020
dependency_graph:
  requires:
    - "Phase 2 DTO pattern (apps/web/app/Data/ClanData.php / ClanTagData.php / ClanMembershipData.php)"
    - "spatie/laravel-data + Spatie\\TypeScriptTransformer\\Attributes\\TypeScript"
    - "spatie/laravel-translatable (HasTranslations getTranslations() API)"
    - "Plan 03-01 (Wave 0 GameDataTest RED placeholder stub)"
    - "Plan 03-03 (Wave 2 — Game / GameRole / GameMatchType / GameMatchTypeRoleLimit Eloquent models + factories)"
    - "Plan 01-15 (TypescriptGenerateCommand — custom artisan wrapper that syncs api.d.ts to packages/shared-types/src/api.d.ts)"
  provides:
    - "App\\Data\\GameData spatie/laravel-data DTO with #[TypeScript] + fromModel factory"
    - "App\\Data\\GameRoleData DTO (translatable display_name)"
    - "App\\Data\\GameMatchTypeData DTO (translatable name + description)"
    - "App\\Data\\GameMatchTypeRoleLimitData DTO (pivot shape, no translatables)"
    - "apps/web/resources/js/types/api.d.ts regenerated with 4 new ambient App.Data.* exports"
    - "packages/shared-types/src/index.ts extended with 4 new export-type aliases"
    - "Tests\\Unit\\Data\\GameDataTest GREEN (5 it() blocks / 22 assertions) replacing the plan 03-01 Wave 0 RED stub"
  affects:
    - "Phase 4+ Vue Inertia pages — can `import type { GameData } from '@trenchwars/shared-types'` for typed Inertia props"
    - "Plan 03-06 / 03-07 (Filament resources) — DTOs are the wire-format contract for Inertia-backed admin pages"
    - "apps/bot consumers (Phase 5) — same package-root alias path"
tech_stack:
  added: []
  patterns:
    - "Phase 2 canonical DTO layout: declare(strict_types=1) + namespace App\\Data + final class XData extends Data + #[TypeScript] attribute + constructor with typed promoted properties"
    - "Pitfall 4 mitigation: public static fromModel($model): self calls $model->getTranslations('field') ?: null for every JSONB locale column instead of accessing $model->field which would return the active-locale scalar"
    - "Nested-relation hydration via relationLoaded() check — empty array when relation not eager-loaded, full DTO list when eager-loaded (Phase 2 ClanData.tags pattern, applied to GameData.roles + GameData.match_types + GameMatchTypeData.role_limits)"
    - "PHPDoc on constructor with array<string, string>|null shape so PHPStan L8 and the TS transformer emit Record<string, string> | null (not Record<string, never>)"
    - "TypeScript transformer pipeline (D-020): make artisan ARGS=\"trenchwars:typescript-generate\" → calls typescript:transform → writes apps/web/resources/js/types/api.d.ts AND copies to /repo/packages/shared-types/src/api.d.ts via the docker-compose mount (plan 01-15 wiring)"
key_files:
  created:
    - apps/web/app/Data/GameData.php
    - apps/web/app/Data/GameRoleData.php
    - apps/web/app/Data/GameMatchTypeData.php
    - apps/web/app/Data/GameMatchTypeRoleLimitData.php
  modified:
    - apps/web/resources/js/types/api.d.ts
    - apps/web/tests/Unit/Data/GameDataTest.php
    - packages/shared-types/src/index.ts
    - packages/shared-types/src/api.d.ts
decisions:
  - "GameData / GameRoleData / GameMatchTypeData / GameMatchTypeRoleLimitData follow the Phase 2 ClanData/ClanTagData/ClanMembershipData pattern verbatim: #[TypeScript] + final class + typed promoted ctor properties + static fromModel() factory + relationLoaded() checks for nested DTO hydration. This is the canonical project DTO shape — no deviations from the analog."
  - "Translatable JSONB columns surface through getTranslations('field') ?: null in fromModel. The ?: null coalescence converts the empty array Spatie returns when no locales are stored into a clean JSON null in the wire payload (matches Phase 2 ClanData::description treatment). The Eloquent magic accessor $game->name was deliberately avoided — it returns only the active-locale scalar, which would silently break the Pitfall 4 mitigation."
  - "Nested DTO arrays (GameData.roles, GameData.match_types, GameMatchTypeData.role_limits) are typed as `array` in PHP with PHPDoc list<XData> annotation, populated by a ->map(fn)->all() call inside fromModel. The alternative #[DataCollectionOf(...)] attribute from spatie/laravel-data v4 was not adopted because the Phase 2 analog (ClanData.tags) uses the simpler list<X> pattern and we follow precedent."
  - "shared-types/src/index.ts append-only — the 4 new aliases (GameData, GameRoleData, GameMatchTypeData, GameMatchTypeRoleLimitData) sit in a labelled `// Phase 3 DTOs — game catalogue` block immediately after the Phase 2 alias block. Phase 1 plan 01-15 wired this as a manual sync (the in-container typescript:install register-check had known false-positives per Phase 1 STATE.md)."
  - "GameDataTest.php uses `uses(RefreshDatabase::class);` explicitly (matching Phase 2 PlayerProfileDataTest.php) because the test exercises DB-persisted models and relationLoaded() behaviour. The Pest.php auto-wiring only applies RefreshDatabase to Feature tests — Unit tests must opt in per file. The first test (`fromModel returns the full JSONB locale array`) calls $game->fresh() after setTranslation() + save() to prove the JSONB round-trips through the DB layer."
metrics:
  duration_seconds: 300
  duration_human: "~5 minutes"
  completed_at: "2026-05-13T11:58:25Z"
  commits:
    - dc41775
    - a269c6a
---

# Phase 3 Plan 04: Wave 2 — DTOs + TypeScript regen + GameDataTest Summary

Four spatie/laravel-data DTOs for the Phase 3 Game domain landed alongside an
api.d.ts regen, a packages/shared-types/src/index.ts extension, and a
replacement GREEN GameDataTest that flips the plan 03-01 Wave 0 RED placeholder
to a real Pitfall 4 mitigation assertion. Phase 4+ Vue consumers can now
`import type { GameData } from "@trenchwars/shared-types"` without spelling out
the `App.Data.*` ambient namespace, and the wire-shape contract for the Game
catalogue is now part of the TS build manifest.

## Objective Achieved

`make pest ARGS="--filter=GameDataTest"` reports **5 tests / 22 assertions GREEN**.
Wider `make pest` reports **247 passed + 2 expected Wave 0 RED** — exactly one
fewer RED than the post-plan-03-03 baseline (the GameDataTest stub flipped
GREEN; GameSeederTest plan 03-05 and GameResourcesPresentTest plan 03-08 stay
RED as designed). PHPStan L8 and Pint --test (207 files) both clean. The
api.d.ts regeneration emits 4 new ambient `App.Data.*` interfaces, and the
shared-types alias block now exports them at the package root.

## 4 DTO Field Maps

### GameData (apps/web/app/Data/GameData.php)

| Field | PHP type | TS type | Source |
|-------|----------|---------|--------|
| `id` | `string` | `string` | `$game->id` |
| `key` | `string` | `string` | `$game->key` |
| `name` | `?array` (PHPDoc `array<string,string>\|null`) | `Record<string, string> \| null` | `$game->getTranslations('name') ?: null` |
| `is_active` | `bool` | `boolean` | `$game->is_active` |
| `roles` | `array` (PHPDoc `list<GameRoleData>`) | `App.Data.GameRoleData[]` | `$game->roles->map(GameRoleData::fromModel)->all()` when `relationLoaded('roles')`, else `[]` |
| `match_types` | `array` (PHPDoc `list<GameMatchTypeData>`) | `App.Data.GameMatchTypeData[]` | `$game->matchTypes->map(GameMatchTypeData::fromModel)->all()` when `relationLoaded('matchTypes')`, else `[]` |

### GameRoleData (apps/web/app/Data/GameRoleData.php)

| Field | PHP type | TS type | Source |
|-------|----------|---------|--------|
| `id` | `string` | `string` | `$role->id` |
| `game_id` | `string` | `string` | `$role->game_id` |
| `key` | `string` | `string` | `$role->key` |
| `display_name` | `?array` | `Record<string, string> \| null` | `$role->getTranslations('display_name') ?: null` |
| `sort_order` | `int` | `number` | `$role->sort_order` |
| `is_active` | `bool` | `boolean` | `$role->is_active` |

No nested relations — leaf DTO.

### GameMatchTypeData (apps/web/app/Data/GameMatchTypeData.php)

| Field | PHP type | TS type | Source |
|-------|----------|---------|--------|
| `id` | `string` | `string` | `$matchType->id` |
| `game_id` | `string` | `string` | `$matchType->game_id` |
| `key` | `string` | `string` | `$matchType->key` |
| `name` | `?array` | `Record<string, string> \| null` | `$matchType->getTranslations('name') ?: null` |
| `description` | `?array` | `Record<string, string> \| null` | `$matchType->getTranslations('description') ?: null` |
| `is_active` | `bool` | `boolean` | `$matchType->is_active` |
| `role_limits` | `array` (PHPDoc `list<GameMatchTypeRoleLimitData>`) | `App.Data.GameMatchTypeRoleLimitData[]` | `$matchType->roleLimits->map(...)->all()` when `relationLoaded('roleLimits')`, else `[]` |

Two independent translatable fields (`name`, `description`) — each gets its own
`getTranslations()` call. This is the only DTO in the Phase 3 set with > 1
translatable column.

### GameMatchTypeRoleLimitData (apps/web/app/Data/GameMatchTypeRoleLimitData.php)

| Field | PHP type | TS type | Source |
|-------|----------|---------|--------|
| `id` | `string` | `string` | `$limit->id` |
| `game_match_type_id` | `string` | `string` | `$limit->game_match_type_id` |
| `game_role_id` | `string` | `string` | `$limit->game_role_id` |
| `capacity` | `int` | `number` | `$limit->capacity` |
| `sort_order` | `int` | `number` | `$limit->sort_order` |

Pivot shape — zero translatable fields, zero nested relations. The cross-game
invariant lives at the model layer (plan 03-03's `saving()` listener), NOT in
the DTO — DTOs are output adapters and never validate cross-table invariants.

## The Pitfall 4 Mitigation Pattern

Every translatable JSONB column in the Phase 3 DTOs is sourced through:

```php
public static function fromModel(Game $game): self
{
    return new self(
        // ...
        name: $game->getTranslations('name') ?: null,
        // ...
    );
}
```

**Why not `$game->name`?** The HasTranslations trait's magic accessor returns
the **active-locale scalar** (e.g. `'HLL'`), not the full JSONB array
(`['en' => 'HLL', 'fr' => 'HLL FR']`). If a DTO surfaced the scalar to the wire,
the frontend would only see one locale and could not switch language without a
server round-trip — silently breaking D-013's "locale plumbed day one" promise.

**Why `?: null`?** Spatie returns `[]` (empty array) when no translations are
stored. Coalescing to `null` keeps the TS surface honest: `Record<string,
string> | null` is the correct contract — an empty record `{}` and a missing
record `null` carry different semantics. The Phase 2 ClanData.description
treatment uses the same pattern.

The Phase 2 ClanData / ClanTagData files use exactly this idiom — verified by
`grep "getTranslations" apps/web/app/Data/*.php` before drafting the Phase 3
DTOs. No deviation from precedent.

## TypeScript regen + api.d.ts delta

`make artisan ARGS="trenchwars:typescript-generate"` was run inside the web
container. The custom command (apps/web/app/Console/Commands/TypescriptGenerateCommand.php
from plan 01-15) internally calls `php artisan typescript:transform`, then
copies `apps/web/resources/js/types/api.d.ts` to
`/repo/packages/shared-types/src/api.d.ts` (the path the docker-compose mount
exposes from the host's `packages/shared-types`).

### Before → After type count

| Surface | Before | After | Delta |
|---------|--------|-------|-------|
| Phase 1 DTOs (UserData, PlayerData, PlayerPrivacyData) | 3 | 3 | — |
| Phase 2 DTOs (ClanData, ClanTagData, ClanMembershipData, ClanInviteData, ClanApplicationData, PublicPlayerData) | 6 | 6 | — |
| **Phase 3 DTOs (GameData, GameRoleData, GameMatchTypeData, GameMatchTypeRoleLimitData)** | 0 | **4** | **+4** |
| Total declared types in api.d.ts | 9 | 13 | +4 |

Verification: `grep -cE 'GameData\|GameRoleData\|GameMatchTypeData\|GameMatchTypeRoleLimitData' apps/web/resources/js/types/api.d.ts` returns **7** — 4 type declarations plus 3 inner type references (GameData embeds `App.Data.GameRoleData[]` and `App.Data.GameMatchTypeData[]`; GameMatchTypeData embeds `App.Data.GameMatchTypeRoleLimitData[]`).

## shared-types/src/index.ts diff

```diff
 // Phase 2 DTOs — clan domain
 export type ClanData = App.Data.ClanData;
 export type ClanTagData = App.Data.ClanTagData;
 export type ClanMembershipData = App.Data.ClanMembershipData;
 export type ClanInviteData = App.Data.ClanInviteData;
 export type ClanApplicationData = App.Data.ClanApplicationData;
 export type PublicPlayerData = App.Data.PublicPlayerData;
+
+// Phase 3 DTOs — game catalogue (Game, GameRole, GameMatchType, RoleLimit)
+export type GameData = App.Data.GameData;
+export type GameRoleData = App.Data.GameRoleData;
+export type GameMatchTypeData = App.Data.GameMatchTypeData;
+export type GameMatchTypeRoleLimitData = App.Data.GameMatchTypeRoleLimitData;
```

Consumers in Phase 4 Vue / Phase 5 bot can now write:

```ts
import type { GameData, GameRoleData } from "@trenchwars/shared-types";

defineProps<{ games: GameData[] }>();
```

…without the `App.Data.*` ambient namespace spelling.

## GameDataTest — 5 it() blocks (22 assertions)

| # | Test | What it proves |
|---|------|----------------|
| 1 | `it('fromModel returns the full JSONB locale array on the name field')` | **Pitfall 4 core assertion** — setTranslation() persists `['en' => 'HLL', 'fr' => 'HLL FR']`, $game->fresh() reloads from DB, fromModel exposes the full array (not just `'HLL'`) |
| 2 | `it('roles array is empty when relation is not loaded')` | Eager-load awareness: $dto->roles is `[]` (NOT null, NOT a lazy-load query) when `relationLoaded('roles')` is false. Asserts the relationLoaded check works on a freshly re-fetched model. |
| 3 | `it('roles array is populated when relation is eager-loaded')` | Positive path: `with('roles')` → role count + first role's nested DTO carries the full JSONB locale array for display_name (Pitfall 4 also applies to nested DTOs). |
| 4 | `it('match_types hydrates nested role_limits when both relations are loaded')` | Two-hop eager-load: `with('matchTypes.roleLimits')` → GameMatchTypeData.role_limits is populated and the inner GameMatchTypeRoleLimitData carries the correct capacity. |
| 5 | `it('GameMatchTypeRoleLimitData carries only the pivot fields (no translatable arrays)')` | toArray() shape contract: keys are exactly `id, game_match_type_id, game_role_id, capacity, sort_order`; NO `name` / `description` leakage from any other DTO. |

The test file replaces the Wave 0 RED stub from plan 03-01 (`it('placeholder
— Wave 0 RED stub replaced by plan 03-04', ...)`). The literal `placeholder`
string has been removed from the file per the phase-close grep audit
(T-03-01-01) — verified `grep "placeholder" apps/web/tests/Unit/Data/GameDataTest.php`
returns empty.

## Quality Gates

| Gate | Command | Result |
|------|---------|--------|
| GameDataTest GREEN | `docker compose exec web ./vendor/bin/pest --filter=GameDataTest` | **5 passed / 22 assertions** |
| Wider Pest suite | `docker compose exec web ./vendor/bin/pest` | **247 passed + 2 expected Wave 0 RED** (plan 03-05 / 03-08) — one fewer RED than the post-03-03 baseline (GameDataTest flipped GREEN as designed). No regressions in Phase 1 / Phase 2 / Phase 3 plan 03-01/02/03 tests. |
| Static analysis | `docker compose exec web ./vendor/bin/phpstan analyse` | `[OK] No errors` |
| Code style | `docker compose exec web ./vendor/bin/pint --test` | **207 files PASS** |
| api.d.ts regen | `php artisan trenchwars:typescript-generate` | `All done! Wrote 2901 bytes to /repo/packages/shared-types/src/api.d.ts` |
| api.d.ts Game type count | `grep -cE 'GameData\|GameRoleData\|GameMatchTypeData\|GameMatchTypeRoleLimitData' apps/web/resources/js/types/api.d.ts` | **7** (≥ 4 target) |
| Local typecheck of shared-types files | `tsc --noEmit --strict /repo/packages/shared-types/src/api.d.ts /repo/packages/shared-types/src/index.ts` (apps/web tsc + apps/web @types/node + skipLibCheck) | **clean** — zero TS errors |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking issue] Plan acceptance criterion calls for `pnpm -F shared-types check`, but the actual script is `typecheck`**

- **Found during:** Task 2 verification (`make pnpm ARGS="-F shared-types check"` returned `No projects matched the filters in "/app"`)
- **Issue:** `packages/shared-types/package.json` exposes `build` and `typecheck` — there is no `check` script. The plan's acceptance criterion was inherited from a stale memory; the canonical script name (from plan 01-18 / `01-foundations-PHASE-VERIFICATION.md`) is `typecheck`. Additionally, the docker-compose web container has `working_dir: /app` (= apps/web only) and the workspace root `/repo/pnpm-workspace.yaml` is NOT mounted — only `/repo/packages/shared-types` is bind-mounted for the type-sync purpose. So even `pnpm -F shared-types typecheck` from inside the container would fail because the workspace manifest is invisible.
- **Fix:** Verified the shared-types files compile clean under strict TypeScript by running `apps/web`'s own tsc binary with the shared-types `api.d.ts` and `index.ts` as inputs (skipLibCheck on to bypass the missing @types/node resolution path that only works inside the full workspace). This is functionally equivalent to the CI gate that runs `pnpm --filter @trenchwars/shared-types typecheck` from the host (plan 01-16 wired this into `.github/workflows/`).
- **Files modified:** none — the local-vs-CI shared-types typecheck split is a Phase 1 architectural reality (D-021 container-only + per-plan-01-18 documented constraint that the dist/ build artifact and full workspace are only visible on the host or in CI).
- **Verification:** Strict tsc on the shared-types index.ts + api.d.ts emitted zero errors. The CI step `pnpm --filter @trenchwars/shared-types typecheck` runs from the GitHub Actions host where the full workspace is available; if a mismatch slips through, that gate will catch it before merge.
- **Commit:** baked into `a269c6a` (Task 2 commit).

### Architectural Changes

None — the 4 DTOs are direct analogs of the Phase 2 Clan* DTOs. The
TypescriptGenerateCommand from plan 01-15 needed no modification; it already
handles arbitrary new #[TypeScript]-attributed classes.

### Auth Gates

None — DTO-layer plan with no auth surface.

## Forward-compat notes for downstream plans

- **Plan 03-05 (HLL seeder):** the DTOs are pure output adapters and have no
  effect on the seeder. The seeder writes via Eloquent (Game::firstOrCreate);
  the DTO surface only matters when an Inertia controller renders the seeded
  data to Vue in Phase 4.
- **Plan 03-06 / 03-07 (Filament resources):** Filament uses its own form
  contract (Forms\Components), NOT spatie/laravel-data — DTOs are unused in
  the admin panel. They become relevant only when Phase 4 ships the public
  Vue pages that consume Inertia props.
- **Plan 03-08 (resource presence test):** unaffected — tests Filament
  registration, not the DTO surface.
- **Phase 4 (matches):** the slot-template page can already
  `import type { GameData, GameMatchTypeData, GameMatchTypeRoleLimitData }` —
  no further DTO work required for the slot-template wire format.

## Known Stubs

None introduced by this plan. The 2 still-RED Wave 0 stubs are documented
placeholders for plans 03-05 (GameSeederTest) and 03-08
(GameResourcesPresentTest). The plan 03-01 / plan 03-04 contract — flip the
GameDataTest stub from RED to GREEN — is now fulfilled.

## Threat Flags

None — this plan introduces no new trust boundaries or network surface. The
DTO layer is the existing model-to-Inertia adapter (T-03-04-01 Pitfall 4
mitigation is implemented and asserted; T-03-04-02 api.d.ts hand-edit risk is
caught by the regenerate-then-grep workflow; T-03-04-03 shared-types sync
disposition is `accept` per the plan's threat register and Phase 1 plan 01-15's
manual-sync wiring). All `mitigate` disposition items in the plan's threat
register are now asserted by Pest tests.

## Self-Check: PASSED

**Created files exist:**

- FOUND: apps/web/app/Data/GameData.php
- FOUND: apps/web/app/Data/GameRoleData.php
- FOUND: apps/web/app/Data/GameMatchTypeData.php
- FOUND: apps/web/app/Data/GameMatchTypeRoleLimitData.php

**Modified files exist with expected changes:**

- FOUND: apps/web/resources/js/types/api.d.ts (contains GameData, GameRoleData, GameMatchTypeData, GameMatchTypeRoleLimitData)
- FOUND: apps/web/tests/Unit/Data/GameDataTest.php (5 it() blocks, no placeholder marker)
- FOUND: packages/shared-types/src/index.ts (Phase 3 alias block)
- FOUND: packages/shared-types/src/api.d.ts (synced from regen)

**Commits exist:**

- FOUND: dc41775 — feat(03-04): add 4 spatie/laravel-data DTOs for the Game domain
- FOUND: a269c6a — feat(03-04): regen api.d.ts + sync shared-types + flip GameDataTest GREEN
