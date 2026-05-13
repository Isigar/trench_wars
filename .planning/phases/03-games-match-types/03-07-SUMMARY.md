---
phase: 03-games-match-types
plan: 07
subsystem: filament-admin
tags: [filament, admin, games, match-types, role-limits, jsonb, i18n, d-007, d-012]
dependency-graph:
  requires:
    - 03-01 (i18n key scaffolding for admin.game_match_type.* and admin.game_match_type_role_limit.*)
    - 03-02 (game_match_type_role_limits migration + capacity >= 0 CHECK)
    - 03-03 (GameMatchType + GameMatchTypeRoleLimit models with saving() cross-game guard)
    - 03-05 (HLL seeder — 5 GameMatchTypes visible in /admin/game-match-types)
    - 03-06 (GameResource + MatchTypesRelationManager — amended by task 3)
  provides:
    - GameMatchTypeResource at /admin/game-match-types (List/Create/Edit, navigationSort=11)
    - RoleLimitsRelationManager (inline CRUD with Pattern 3 cross-game-scoped Select)
    - MatchTypesRelationManager EditAction URL override (Pattern 2 click-through)
  affects:
    - 03-08 (admin presence test for GameMatchTypeResource — replaces Wave 0 stub)
    - 03-09 (i18n audit reaches the admin.game_match_type_role_limit.* + admin.game_match_type.tab.* keys)
tech-stack:
  added: []
  patterns:
    - "Pattern 2 (RESEARCH.md): Filament v3 two-resource workaround for nested RelationManagers — GameResource owns MatchTypesRelationManager, GameMatchTypeResource owns RoleLimitsRelationManager"
    - "Pattern 3 (RESEARCH.md): RelationManager Select scoped via getOwnerRecord()->game->roles() — admin UI cannot pick a cross-game role (Pitfall 10 UI half)"
    - "EditAction::make()->url(fn ($record) => OtherResource::getUrl('edit', ['record' => $record])) — Filament v3 click-through navigation idiom"
    - "Pitfall 2 TWO translatable fields (name + description) require TWO mutator coercions on Create + Edit pages (extends the single-field GameResource pattern)"
    - "Raw JSONB attribute read via $model->getAttributes()['col'] + json_decode — bypasses HasTranslations string accessor for PHPStan L8 compatibility in scoped Select option labels"
key-files:
  created:
    - apps/web/app/Filament/Resources/GameMatchTypeResource.php
    - apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/ListGameMatchTypes.php
    - apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/CreateGameMatchType.php
    - apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/EditGameMatchType.php
    - apps/web/app/Filament/Resources/GameMatchTypeResource/RelationManagers/RoleLimitsRelationManager.php
  modified:
    - apps/web/app/Filament/Resources/GameResource/RelationManagers/MatchTypesRelationManager.php
decisions:
  - "GameMatchTypeResource ships List/Create/Edit only — NO View page (ClanTagResource precedent; keeps the second-tier resource tight). Edit serves as inspect-+-mutate; admin uses the parent GameResource's table for catalog browsing"
  - "game_id Select on Create form uses ->relationship('game', 'key') (display key, not translatable name JSONB) — stable ordering and PHPStan-friendly. disabledOn('edit') because changing game_id would orphan all child RoleLimits"
  - "No DeleteAction at GameMatchTypeResource table level — cascade-delete reaches role_limits. Admin uses is_active toggle (mirrors GameResource convention; matches Phase 3 retirement model)"
  - "RoleLimitsRelationManager Select option labels read raw JSONB via getAttributes() + json_decode rather than via HasTranslations accessor — Larastan L8 resolves the typed-model accessor to string and would trip is_array. Functional behaviour identical; static-analysis-friendly"
  - "Pattern 3 cross-game scoping is the UI half of Pitfall 10 defense-in-depth; the model saving() listener (plan 03-03) is the API/Console half — both gates required by RESEARCH.md and the threat register"
metrics:
  duration_seconds: 305
  completed_at: "2026-05-13"
---

# Phase 03 Plan 07: GameMatchTypeResource + RoleLimitsRelationManager + MatchTypesRelationManager Rule-2 amendment — Summary

GameMatchTypeResource ships as the second-tier Filament resource (Pattern 2), backed by RoleLimitsRelationManager with a cross-game-scoped role Select (Pattern 3). MatchTypesRelationManager (plan 03-06) amended to navigate click-throughs to the new resource. SC-2 satisfied: admin can create a GameMatchType and set role capacities through Filament Relation Managers — and Pitfall 10 defense-in-depth is now complete at both UI and model layers.

## Plan Coverage

| Task | Done | Commit  | Files                                                                                                                                                                                            |
| ---- | ---- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1. GameMatchTypeResource.php + 3 Pages with TWO-field JSONB mutators                                  | yes | `b528f6a` | GameMatchTypeResource.php, Pages/{ListGameMatchTypes,CreateGameMatchType,EditGameMatchType}.php |
| 2. RoleLimitsRelationManager with Pattern 3 cross-game-scoped Select                                  | yes | `b3ec70a` | RoleLimitsRelationManager.php                                                                   |
| 3. Rule-2 amendment to MatchTypesRelationManager EditAction URL override                              | yes | `e1ce593` | GameResource/RelationManagers/MatchTypesRelationManager.php                                     |

## What Ships

### GameMatchTypeResource (Tabs: Profile + Audit)

- `$model = GameMatchType::class`, `$navigationIcon = 'heroicon-o-list-bullet'`, `$navigationSort = 11` (immediately after GameResource = 10 per Open Question Q3 RESOLVED)
- `getModelLabel()` / `getPluralModelLabel()` → `admin.game_match_type.label` / `admin.game_match_type.plural_label`
- Form Tab 1 (Profile, Section `admin.game_match_type.section.profile`):
  - `game_id` Select — `->relationship('game', 'key')->required()->searchable()->disabledOn('edit')` — admin picks the parent Game on Create; locked on Edit
  - `key` TextInput — `->required()->maxLength(64)->regex('/^[a-z0-9_]+$/')->disabledOn('edit')` — same idempotency rationale as GameResource
  - `name` KeyValue — `default(['en' => ''])`, required (Pitfall 2)
  - `description` KeyValue — `default(['en' => ''])`, NOT required (column nullable per migration) (Pitfall 2 SECOND field)
  - `is_active` Toggle — default true
- Form Tab 2 (Audit): renders `filament.partials.audit-tab` partial (Phase 1 plan 01-14)
- Table columns: `game.key` (mono, searchable, sortable), `key` (mono, searchable, sortable), `name` (en accessor), `is_active` (IconColumn)
- Table filters: `SelectFilter::make('game')->relationship('game', 'key')` — admin can scope by parent Game
- Actions: EditAction only — NO DeleteAction (cascade-delete reaches role_limits; admin uses is_active toggle)
- Routes: `/admin/game-match-types`, `/admin/game-match-types/create`, `/admin/game-match-types/{record}/edit` (3 routes — NO view)
- `getRelations()`: `[RoleLimitsRelationManager::class]`

### CreateGameMatchType + EditGameMatchType (Pitfall 2 — TWO-field variant)

```php
$data['name'] = $data['name'] ?: ['en' => ''];
$data['description'] = $data['description'] ?: ['en' => ''];
```

Both pages coerce BOTH translatable JSONB fields. This is the Pitfall 2 extension noted in RESEARCH.md: GameMatchType has two translatable fields (`name` + `description`), so the mutator must touch both. KeyValue returns `null` on empty submission; HasTranslations expects array.

### ListGameMatchTypes

Overrides `getHeaderActions()` to expose `Filament\Actions\CreateAction::make()` — standard ListRecords idiom.

### RoleLimitsRelationManager — Pattern 3 verbatim

- `$relationship = 'roleLimits'` (matches `GameMatchType::roleLimits()` HasMany — Pitfall 3 guard)
- `getTitle()` (STATIC) → `admin.game_match_type_role_limit.plural_label`
- Form fields:
  - `game_role_id` Select — Pattern 3 verbatim:
    ```php
    ->options(function (RelationManager $livewire): array {
        /** @var GameMatchType $matchType */
        $matchType = $livewire->getOwnerRecord();
        $game = $matchType->game;
        if ($game === null) {
            return [];
        }
        return $game->roles()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($role): array {
                $raw = $role->getAttributes()['display_name'] ?? null;
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                $label = is_array($decoded) ? ($decoded['en'] ?? $role->key) : $role->key;
                return [$role->id => $label];
            })
            ->toArray();
    })
    ->required()
    ->searchable()
    ```
  - `capacity` TextInput — `numeric()->minValue(0)->required()` (V5 form-layer gate; DB CHECK from plan 03-02 is second gate)
  - `sort_order` TextInput — `numeric()->default(0)`
- Table columns: `sort_order` (sortable), `role.display_name` (raw-JSONB extractor), `capacity` (numeric, sortable)
- `defaultSort('sort_order')`
- Header: CreateAction; row: EditAction + DeleteAction

### MatchTypesRelationManager (amended)

EditAction now uses URL override:

```php
Tables\Actions\EditAction::make()
    ->url(fn (GameMatchType $record): string => GameMatchTypeResource::getUrl('edit', ['record' => $record])),
```

Filament wires this as a native browser navigation instead of opening the default modal. Click-through path:

```
/admin/games/{game}/edit (MatchTypes tab)
   └─ row click "Edit"
       └─ /admin/game-match-types/{record}/edit
           └─ RoleLimits tab (RoleLimitsRelationManager — Pattern 3 scoped Select)
```

Plan 03-06 truth #5 ("clicking a row navigates to GameMatchTypeResource") is now TRUE.

## Pitfall 10 Defense-in-Depth (now complete)

| Layer | Component | Mechanism |
| ----- | --------- | --------- |
| UI (admin) | `RoleLimitsRelationManager::form()` `game_role_id` Select | `->options(fn ($livewire) => $livewire->getOwnerRecord()->game->roles()...)` — option list is built from roles of the SAME parent game; admin cannot select a cross-game role |
| Model (API/Console) | `GameMatchTypeRoleLimit::booted()` `static::saving()` listener (plan 03-03) | Throws `DomainException` when `matchType.game_id !== role.game_id`; catches writes that bypass the UI Select (mass-assignment, factory, console, future API) |

Both gates required by RESEARCH.md § Pitfall 10. The model guard alone is sufficient for correctness but slow (always catches at write time); the UI Select makes the admin UX preserve the invariant without round-tripping.

## Field Maps

**GameMatchTypeResource form (Profile tab):**

| Field       | Component  | Notes                                                                  |
| ----------- | ---------- | ---------------------------------------------------------------------- |
| game_id     | Select     | relationship('game','key'), required, searchable, disabledOn('edit')   |
| key         | TextInput  | required, maxLength 64, regex `^[a-z0-9_]+$`, disabledOn('edit')       |
| name        | KeyValue   | reorderable false, default ['en'=>''], required                        |
| description | KeyValue   | reorderable false, default ['en'=>''], NOT required (nullable JSONB)   |
| is_active   | Toggle     | default true                                                           |

**RoleLimitsRelationManager form:**

| Field        | Component | Notes                                                                                          |
| ------------ | --------- | ---------------------------------------------------------------------------------------------- |
| game_role_id | Select    | required, searchable, options scoped via `getOwnerRecord()->game->roles()` (Pattern 3)         |
| capacity     | TextInput | numeric, minValue 0, required                                                                  |
| sort_order   | TextInput | numeric, default 0                                                                             |

## Verification

```text
docker compose exec web ./vendor/bin/phpstan analyse  → No errors (full project surface)
docker compose exec web ./vendor/bin/pint --test       → PASS 220 files (full project, clean)
docker compose exec web ./vendor/bin/pest --filter=GameMatchType  → 18 passed (44 assertions)
docker compose exec web ./vendor/bin/pest --filter="Filament|GameResource" → 25 passed (32 assertions, no regression vs plan 03-06)
docker compose exec web php artisan route:list --path=admin/game-match-types → 3 routes (index, create, edit)
grep -c 'getOwnerRecord' RoleLimitsRelationManager.php → 3
grep -c 'GameMatchTypeResource::getUrl' MatchTypesRelationManager.php → 2 (Edit url chain + docblock)
```

Plan 03-08 will replace the Wave 0 `GameResourcesPresentTest::placeholder` stub with a full admin presence + 403 gate test that exercises BOTH /admin/games and /admin/game-match-types.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PHPStan L8 typed-model accessor on HasTranslations field**

- **Found during:** Task 2 (phpstan after first write of RoleLimitsRelationManager)
- **Issue:** Two phpstan errors:
  - `Cannot call method roles() on App\Models\Game|null.` — `$matchType->game` is BelongsTo and statically nullable
  - `Call to function is_array() with string will always evaluate to false.` — Larastan resolves `$role->display_name` to `string` because HasTranslations registers a typed accessor on the GameRole model; PHPStan therefore knows `is_array($role->display_name)` can never be true
- **Fix:**
  - Added `$game = $matchType->game; if ($game === null) { return []; }` early-return guard before calling `->roles()`
  - Read raw JSONB attribute via `$role->getAttributes()['display_name']` (bypasses HasTranslations accessor) + `json_decode(..., true)` + `is_array($decoded)` check
  - Applied same idiom to the table `role.display_name` column getStateUsing
- **Rationale:** The behaviour is identical (we still return the `['en']` sub-key with `$role->key` fallback) but the static-analysis-friendly form avoids touching the typed accessor. Other Filament resources in the codebase use the looser `fn ($record)` parameter so PHPStan doesn't see the typed model — in this case `mapWithKeys` provides typed `GameRole`, which made the accessor visible to Larastan
- **Files modified:** RoleLimitsRelationManager.php
- **Commit:** `b3ec70a` (single-commit task 2)

### Intentional Plan Deviations

None — all three tasks shipped exactly per acceptance criteria. Task 3 amendment lands as planned in 03-06's deferral note. No DeleteAction on GameMatchTypeResource matches the convention established by GameResource (plan 03-06 decision) and explicit plan acceptance.

## Auth Gates

None — D-021 stack already up; no MCP/external service auth required.

## Threat Flags

None — surface introduced (Filament resources at /admin/game-match-types) is in scope of the existing `<threat_model>`. The admin-access gate from Phase 1 plan 01-12 covers T-03-07-03 globally. Pitfall 10 defense-in-depth is complete (T-03-07-01 mitigated via Pattern 3 UI Select + model saving() listener; both prongs required).

## Self-Check: PASSED

- `apps/web/app/Filament/Resources/GameMatchTypeResource.php` — FOUND
- `apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/ListGameMatchTypes.php` — FOUND
- `apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/CreateGameMatchType.php` — FOUND
- `apps/web/app/Filament/Resources/GameMatchTypeResource/Pages/EditGameMatchType.php` — FOUND
- `apps/web/app/Filament/Resources/GameMatchTypeResource/RelationManagers/RoleLimitsRelationManager.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/RelationManagers/MatchTypesRelationManager.php` — MODIFIED (use import + EditAction url override)
- Commit `b528f6a` — FOUND
- Commit `b3ec70a` — FOUND
- Commit `e1ce593` — FOUND
- phpstan (full project): clean
- pint --test (full project): 220 files clean
- pest --filter=GameMatchType: 18 passed (44 assertions)
- pest --filter="Filament|GameResource": 25 passed (no regression)
- artisan route:list --path=admin/game-match-types: 3 routes registered
- grep getOwnerRecord in RoleLimitsRelationManager: 3 hits (Pattern 3 wired)
- grep GameMatchTypeResource::getUrl in MatchTypesRelationManager: 2 hits (Edit chain + docblock)
