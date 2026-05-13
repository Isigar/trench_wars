---
phase: 03-games-match-types
plan: 06
subsystem: filament-admin
tags: [filament, admin, games, jsonb, i18n, d-012]
dependency-graph:
  requires:
    - 03-03 (Game/GameRole/GameMatchType models with HasMany roles/matchTypes)
    - 03-05 (seeded HLL row + 15 GameRoles + 5 GameMatchTypes; visible in /admin/games once GameResource ships)
    - 01-12 (Filament panel + admin-access permission gate)
    - 01-14 (audit-tab.blade.php partial)
    - 02-12 (ClanResource Tabs + KeyValue + mutateFormData pattern — canonical analog)
  provides:
    - GameResource at /admin/games (List/Create/View/Edit)
    - RolesRelationManager inline-CRUD for GameRole on Game edit page
    - MatchTypesRelationManager inline-CRUD for GameMatchType (modal EditAction, navigation deferred)
  affects:
    - 03-07 (Rule-2 amendment to MatchTypesRelationManager.php — override EditAction URL to GameMatchTypeResource)
    - 03-08 (admin presence test + 403 gate replaces Wave 0 stub)
    - 03-09 (i18n audit of admin.game.*, admin.game_role.*, admin.game_match_type.* keys)
tech-stack:
  added: []
  patterns:
    - "Filament v3 Tabs(Profile+Audit) on Resource form() — mirrors ClanResource analog"
    - "KeyValue::make('jsonb_field')->default(['en' => ''])->required() for HasTranslations JSONB columns (Pattern 4)"
    - "mutateFormDataBeforeCreate/Save coerce null translatable → ['en' => ''] (Pitfall 2)"
    - "RelationManager $relationship = HasMany method name (Pitfall 3) — silent failure on typo"
    - "RelationManager::getTitle() is STATIC in Filament v3 (corrected during phpstan)"
    - "disabledOn('edit') idiom for immutable post-create fields"
    - "navigationSort=10 keeps new resources after Phase 1/2 (sorts 1-8); flat layout, no nav group"
key-files:
  created:
    - apps/web/app/Filament/Resources/GameResource.php
    - apps/web/app/Filament/Resources/GameResource/Pages/ListGames.php
    - apps/web/app/Filament/Resources/GameResource/Pages/CreateGame.php
    - apps/web/app/Filament/Resources/GameResource/Pages/EditGame.php
    - apps/web/app/Filament/Resources/GameResource/Pages/ViewGame.php
    - apps/web/app/Filament/Resources/GameResource/RelationManagers/RolesRelationManager.php
    - apps/web/app/Filament/Resources/GameResource/RelationManagers/MatchTypesRelationManager.php
  modified: []
decisions:
  - "GameResource ships with NO DeleteAction at the table level (Open Question Q4 deferred): cascade-delete from Game to roles + match-types + role-limits is destructive; admin uses is_active toggle to hide a game instead. If hard-delete is needed later, expose via custom confirm-modal Action on the Edit page (not via standard DeleteAction)."
  - "Pattern 2 second-tier navigation (MatchTypesRelationManager → GameMatchTypeResource::edit) DEFERRED to plan 03-07 task 3 Rule-2 amendment. Reason: GameMatchTypeResource ships in wave 5 (plan 03-07); referencing it from wave 4 would phpstan-fail. Default modal-based EditAction ships in this plan; admin sees RoleLimits only after plan 03-07 lands."
  - "RelationManager::getTitle() must be STATIC in Filament v3 (Rule 1 fatal-error fix; ctx7 docs confirm). The PHP signature `public static function getTitle(Model $ownerRecord, string $pageClass): string` is the canonical form."
  - "key field disabledOn('edit') on GameResource — T-03-06-03 mitigation. Admin can set the key on create but not edit; this preserves GameSeeder::firstOrCreate(['key' => $key]) idempotency. The seeder would create a duplicate row if the key were renamed post-seed."
  - "ListGames overrides getHeaderActions() to register Filament\\Actions\\CreateAction::make() — the standard pattern Pint auto-imports as Action + ActionGroup type aliases."
metrics:
  duration_seconds: 202
  completed_at: "2026-05-13"
---

# Phase 03 Plan 06: GameResource Filament admin + Roles/MatchTypes RelationManagers — Summary

GameResource (Tabs: Profile + Audit) + 4 Pages + 2 RelationManagers using `spatie/laravel-translatable` JSONB columns through Filament v3 `KeyValue` components. D-012 first-tier game-domain admin surface; SC-1 satisfied.

## Plan Coverage

| Task | Done | Commit | Files |
|------|------|--------|-------|
| 1. GameResource.php + 4 Pages (List/Create/View/Edit) with mutateFormDataBefore* JSONB coercion | yes | `fa95640` | GameResource.php, Pages/{ListGames,CreateGame,EditGame,ViewGame}.php |
| 2. RolesRelationManager + MatchTypesRelationManager (Pattern 1 inline CRUD) | yes | `03110cc` | RelationManagers/{RolesRelationManager,MatchTypesRelationManager}.php |

## What Ships

### GameResource (Tabs: Profile + Audit)

- `$model = Game::class`, `$navigationIcon = 'heroicon-o-puzzle-piece'`, `$navigationSort = 10` (after all Phase 1/2 resources sorted 1-8)
- `getModelLabel()`/`getPluralModelLabel()` → `admin.game.label`/`admin.game.plural_label`
- Form Tab 1 (Profile): `key` TextInput (regex `^[a-z0-9_]+$`, maxLength 64, `disabledOn('edit')`) + `name` KeyValue (default `['en' => '']`, reorderable false, required) + `is_active` Toggle (default true)
- Form Tab 2 (Audit): Placeholder rendering `filament.partials.audit-tab` partial (Phase 1 plan 01-14)
- Table columns: `key` (mono, searchable, sortable), `name` (getStateUsing → `['en']`), `is_active` (IconColumn boolean), `created_at` (sortable)
- Actions: ViewAction + EditAction only — NO DeleteAction (intentional, see decisions)
- Routes registered: `/admin/games`, `/admin/games/create`, `/admin/games/{record}`, `/admin/games/{record}/edit`
- `getRelations()`: `[RolesRelationManager::class, MatchTypesRelationManager::class]`

### CreateGame::mutateFormDataBeforeCreate + EditGame::mutateFormDataBeforeSave

Both mutators coerce `$data['name'] = $data['name'] ?: ['en' => '']` — Pitfall 2 mitigation. KeyValue returns `null` on empty submission; HasTranslations expects an array.

### ListGames

Overrides `getHeaderActions()` to expose `Filament\Actions\CreateAction::make()`. Pint auto-imported `Action` and `ActionGroup` types for the array return docblock.

### RolesRelationManager

- `$relationship = 'roles'` (matches `Game::roles()` HasMany — Pitfall 3 guard)
- `getTitle()` (STATIC) → `admin.game_role.plural_label`
- Form: `key` (regex, helperText), `display_name` KeyValue (default `['en' => '']`, required), `sort_order` numeric (default 0), `is_active` Toggle (default true)
- Table: sort_order (sortable, default sort) + key (mono, searchable) + display_name (en accessor) + is_active (boolean)
- Header: CreateAction; row: EditAction + DeleteAction

### MatchTypesRelationManager

- `$relationship = 'matchTypes'` (matches `Game::matchTypes()`)
- `getTitle()` (STATIC) → `admin.game_match_type.plural_label`
- Form: `key` (regex), `name` KeyValue (required), `description` KeyValue (NOT required, nullable JSONB), `is_active` Toggle
- Table: key (mono, searchable) + name (en accessor) + is_active (boolean); default sort by key
- Header: CreateAction; row: EditAction (DEFAULT modal-based — Pattern 2 URL override deferred) + DeleteAction
- Inline comment flags the URL-override amendment incoming from plan 03-07 task 3

## Field Maps

**GameResource form (Profile tab):**

| Field | Component | Notes |
|-------|-----------|-------|
| key | TextInput | required, maxLength 64, regex `^[a-z0-9_]+$`, disabledOn('edit') |
| name | KeyValue | reorderable false, default ['en'=>''], required |
| is_active | Toggle | default true |

**RolesRelationManager form:**

| Field | Component | Notes |
|-------|-----------|-------|
| key | TextInput | required, regex, maxLength 64 |
| display_name | KeyValue | reorderable false, default ['en'=>''], required |
| sort_order | TextInput | numeric, default 0 |
| is_active | Toggle | default true |

**MatchTypesRelationManager form:**

| Field | Component | Notes |
|-------|-----------|-------|
| key | TextInput | required, regex, maxLength 64 |
| name | KeyValue | reorderable false, default ['en'=>''], required |
| description | KeyValue | reorderable false, default ['en'=>''], NOT required (nullable JSONB) |
| is_active | Toggle | default true |

## Verification

```text
docker compose exec web ./vendor/bin/phpstan analyse  → No errors (full project surface)
docker compose exec web ./vendor/bin/pint --test app/Filament/Resources/GameResource.php app/Filament/Resources/GameResource → 7 files, no style issues
docker compose exec web ./vendor/bin/pest --filter="Filament|GameResource"  → 25 passed (32 assertions)
docker compose exec web php artisan route:list --path=admin/games  → 4 routes: index, create, view, edit
```

The Wave 0 stub `GameResourcesPresentTest::placeholder` now passes (class exists). Plan 03-08 will replace it with a full admin presence + 403 gate test.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] RelationManager::getTitle() must be STATIC in Filament v3**

- **Found during:** Task 2 (phpstan bootstrap)
- **Issue:** Initial implementation declared `getTitle()` as an instance method matching the docblock-style PHP signature, which triggered a PHP fatal error during Filament panel registration: `Cannot make static method Filament\Resources\RelationManagers\RelationManager::getTitle() non static`.
- **Fix:** Changed both RelationManagers' `getTitle()` to `public static function getTitle(...)`. Verified via Context7 — Filament v3 docs confirm `public static function getTitle(Model $ownerRecord, string $pageClass): string` is the canonical form.
- **Files modified:** RolesRelationManager.php, MatchTypesRelationManager.php
- **Commit:** Included in `03110cc` (pre-commit fix during task 2 phpstan loop)

**2. [Rule 1 - Style] Pint fully_qualified_strict_types auto-fix**

- **Found during:** Task 2 (Pint --test)
- **Issue:** 3 files had inline FQN type annotations (`\Filament\Actions\Action` in ListGames docblock; `\Illuminate\Database\Eloquent\Model` in RelationManagers' `getTitle()` parameter).
- **Fix:** `make pint` auto-imported the symbols via `use` statements. Standard Laravel Pint preset behavior.
- **Files modified:** ListGames.php, RolesRelationManager.php, MatchTypesRelationManager.php
- **Commit:** Included in `fa95640` (ListGames) and `03110cc` (both RelationManagers)

### Intentional Plan Deviations

**3. [Pattern 2 click-through deferred to plan 03-07]**

Plan 03-06 ships the **default modal-based EditAction** on MatchTypesRelationManager. The Pattern 2 URL override (`->url(fn ($record) => GameMatchTypeResource::getUrl('edit', ['record' => $record]))`) is a Rule-2 amendment scheduled for plan 03-07 task 3 (after wave 5 ships `GameMatchTypeResource`). The plan's task 2 acceptance criteria explicitly call this out as the resolution path — no architectural change, just a wave-ordering constraint. The MatchTypesRelationManager source carries an inline comment flagging the incoming amendment.

**4. [No DeleteAction on GameResource table]**

Plan task 1 acceptance specifies "no DeleteAction at the table level — admin uses is_active toggle". Open Question Q4 (CONTEXT.md) recommended deferring hard-delete; this plan honours that. Verified in source — only ViewAction + EditAction are exposed. Admin must hard-delete via DB if absolutely needed (future plan can surface a custom modal-confirmed Action on the Edit page).

## Auth Gates

None — D-021 stack already up; no MCP/external service auth required.

## Threat Flags

None — surface introduced (Filament resources at /admin/games) is in scope of the existing `<threat_model>`. The admin-access gate from Phase 1 plan 01-12 covers T-03-06-01 globally.

## Self-Check: PASSED

- `apps/web/app/Filament/Resources/GameResource.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/Pages/ListGames.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/Pages/CreateGame.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/Pages/EditGame.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/Pages/ViewGame.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/RelationManagers/RolesRelationManager.php` — FOUND
- `apps/web/app/Filament/Resources/GameResource/RelationManagers/MatchTypesRelationManager.php` — FOUND
- Commit `fa95640` — FOUND
- Commit `03110cc` — FOUND
- phpstan: clean
- pint --test: clean
- pest (Filament + GameResource subset): 25 passed
- artisan route:list --path=admin/games: 4 routes registered
