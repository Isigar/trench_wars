# Phase 3: Games & match types - Research

**Researched:** 2026-05-13
**Domain:** Laravel 12 + Filament v3 admin Resources & Relation Managers + spatie/laravel-translatable JSONB + Postgres CHECK / partial-unique / FK cascade rules + spatie/laravel-data DTOs + spatie/laravel-activitylog
**Confidence:** HIGH — all infrastructure (Filament v3, spatie/laravel-translatable, spatie/laravel-data, spatie/laravel-activitylog) already installed in composer.lock and exercised by Phase 1/2; the work in Phase 3 is purely applying the established patterns to four new tables with two levels of Filament Relation Manager nesting.

---

## Summary

Phase 3 is a self-contained domain extension on top of a fully-functional Phase 2 codebase. Four new tables (`games`, `game_roles`, `game_match_types`, `game_match_type_role_limits`), four new Eloquent models, four new spatie/laravel-data DTOs, two new Filament Resources (`GameResource` + `GameMatchTypeResource`) with three Relation Managers (Roles + MatchTypes attached to GameResource; RoleLimits attached to GameMatchTypeResource), one new `GameSeeder` (HLL preset: 15 roles + 5 match types + capacity rows), and a Pest test surface that mirrors the Phase 2 model-tests + admin-presence-tests + seeder-idempotency pattern.

Three technical items deserve special planning attention:

1. **Two-level Relation Manager nesting in Filament v3.** Filament resource nesting is shallow by default — you cannot register a RelationManager inside a RelationManager. The canonical solution per Filament v3 docs is: GameResource has `MatchTypesRelationManager` (and `RolesRelationManager`); MatchType also exists as its OWN top-level resource (`GameMatchTypeResource`) which then exposes `RoleLimitsRelationManager`. The admin enters MatchTypes from the Game edit page (where the parent is fixed in scope), then clicks through to the MatchType edit page to configure RoleLimits. This is the same two-resource pattern Phase 2 used for `ClanResource` (Membership/Invite/Application as RelationManagers) + standalone `ClanMembershipResource`/`ClanInviteResource`/`ClanApplicationResource`. [VERIFIED: Context7 /websites/filamentphp_3_x relation-managers]

2. **HLL has 14 canonical infantry roles per faction, plus 1 Spotter (recon partner) = 15 league-roster total** that CONTEXT.md asks for. Confirmed against gamerant.com + hellletloose.fandom.com: Command (Commander, Officer/Squad Leader), Infantry (Rifleman, Assault, Automatic Rifleman, Medic, Support, Engineer, Anti-Tank, Machine Gunner), Recon (Sniper, Spotter), Armor (Tank Commander, Crewman). The CONTEXT.md lists "Commander, Officer, SL" as three separate roles — this treats "Officer" (any squad's spawning leader) and "SL" (Squad Leader doctrine variant for league play) as distinct roster entries, which is unusual but matches the success-criteria text verbatim. **Recommend confirming with operator whether Officer + SL are distinct or duplicate before seeding** — see Open Questions Q1. The role-key slugs below assume the CONTEXT.md spec.

3. **Filament v3 Repeater with HasMany relationship is the alternative pattern for RoleLimits inline.** Instead of a second-tier RelationManager on `GameMatchTypeResource`, RoleLimits could be edited as a Filament `Repeater::make('roleLimits')->relationship()` block directly inside the GameMatchType form. Pro: single edit screen, no click-through. Con: scales poorly past ~20 rows (15 roles × per-match-type is exactly at the threshold), and Filament Repeater's `Select::make('game_role_id')` would need careful scoping to only roles of the same `game_id`. **Recommend the RelationManager approach** for parity with Phase 2 `ClanResource.MembersRelationManager` pattern and so the table view supports search/sort. [VERIFIED: Context7 /websites/filamentphp_3_x forms/fields/repeater]

**Primary recommendation:** Plan in waves: (1) Wave 0 — test scaffolding stubs + composer no-op (no new packages needed); (2) migrations for 4 tables with FK + CHECK + UNIQUE constraints; (3) models + factories + LogsActivity + HasTranslations; (4) DTOs + TS regeneration; (5) Filament `GameResource` with `RolesRelationManager` + `MatchTypesRelationManager`; (6) Filament `GameMatchTypeResource` with `RoleLimitsRelationManager`; (7) `GameSeeder` + idempotency tests; (8) phase verification + ROADMAP update.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Hard constraints carried from prior decisions:**
- **D-007** Generic Game/GameRole/GameMatchType/GameMatchTypeRoleLimit relational tables; HLL is a seeded preset (NOT hard-coded). Adding a new game in P3+ is data-only (Filament data entry, zero code changes).
- **D-012** Filament covers every domain entity — `GameResource`, `GameMatchTypeResource` (and their RelationManagers) land in Phase 3.
- **D-013** Translatable user-facing strings via `spatie/laravel-translatable` JSONB columns — `game.name`, `game_role.display_name`, `game_match_type.name`, `game_match_type.description` are JSONB locale-keyed.
- **D-021** Container-only commands — all `composer`, `php artisan`, `pest`, `pint`, `phpstan`, `pnpm` invocations run inside the `web` container via `make` aliases.

**All other implementation choices are at Claude's discretion** (discuss phase was skipped per `workflow.skip_discuss=true`).

### Claude's Discretion
All implementation choices not covered by locked decisions above, including:
- Filament resource navigation grouping (`navigationGroup('Platform')` vs flat sidebar)
- Repeater vs nested RelationManager choice for RoleLimits (RESEARCH recommends RelationManager)
- Sort-order column inclusion on `game_roles` and `game_match_type_role_limits` for stable Filament list ordering
- Whether `is_active` boolean lives on `games` only or also on `game_roles` / `game_match_types`
- Exact Filament `Forms\Components` choice for translatable JSONB (`KeyValue` continues to be standard for EN-only — same as Phase 2)
- Seeder idempotency key — `key` (game-scoped) or `(game_id, key)` composite
- Whether `GameMatchTypeRoleLimit.capacity` defaults to 0 or 1 on row create
- Filament navigation icons (heroicon choices)
- Test scope: include policy/authorization tests or rely on existing FilamentPanelAccessTest

### Deferred Ideas (OUT OF SCOPE)
- **Match-level integration** (slot signups consuming role limits) — Phase 4
- **RCON ingest game-event mapping** — Phase 8
- **Multi-game UI surfacing on public pages** — Phase 4+ (game model is admin-facing in Phase 3)
- **Match-type-specific custom fields** (e.g., bo3 vs bo5 toggles for tournaments) — Phase 6 tournament builder
- **Game image / banner upload** — Phase 7 CMS storage primitives
- **Multi-locale Filament locale switcher for translatable fields** — Phase 7 CMS (per Phase 2 RESEARCH.md decision to defer LocaleSwitcher until then; EN-only KeyValue pattern continues)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-platform-vision | Game-agnostic data model (Game / GameRole / GameMatchType / GameMatchTypeRoleLimit) is implemented and HLL is a seeded preset, not hard-coded. Additional games can be added without code changes. (D-007) | Schema § (Section 2): 4 tables with `game_id` FKs; admin-editable via Filament Resources; HLL seeded via `GameSeeder` calling `firstOrCreate(['key' => 'hll'], ...)` idempotently. Adding a new game = create Game in Filament UI → admin enters roles/match-types/limits. Zero code changes required. |
</phase_requirements>

---

## Project Constraints (from CLAUDE.md)

| Constraint | Source | Phase 3 Impact |
|------------|--------|----------------|
| Container-only commands | CLAUDE.md §1 (D-021) | All migrations, seeders, tests run via `make` targets — never reference host `php artisan` |
| Pint preset (Laravel default) | CLAUDE.md §3 | All new PHP files pass `make pint ARGS="--test"` |
| PHPStan level 8 | CLAUDE.md §3 | Type annotations on `$translatable`, `$fillable`, relationship return types, factory `definition()` |
| Pest (NOT PHPUnit syntax) | CLAUDE.md §4 | All new tests use `it()`/`test()`/`expect()` |
| Feature tests in `apps/web/tests/Feature/`, Unit in `tests/Unit/` | CLAUDE.md §4 | Model + seeder tests live in `Feature/Models/` + `Feature/Database/`; DTO tests in `Unit/Data/` |
| Wave 0 test scaffolding precedes implementation | CLAUDE.md §4 | Plan 03-01 must scaffold all test stubs before plans 03-02..03-N add implementation |
| `apps/web/` is the Laravel root | CLAUDE.md §5 | All migration/seeder/model/resource paths are under `apps/web/`, not repo root |
| `__()` / `t()` for every UI string | CLAUDE.md §7 (D-013) | Every Filament label, navigation entry, action name, validation message uses `__()`. NoHardcodedStringsTest auto-covers Vue (Phase 3 ships no Vue) |
| PHP arrays only for canonical EN | CLAUDE.md §7 | New `lang/en/admin.php` keys appended (no new files; same convention as Phase 2 added `admin.clan.*`) |
| Translatable user content via JSONB columns | CLAUDE.md §7 | `game.name`, `game_role.display_name`, `game_match_type.name`, `game_match_type.description` are JSONB; trait `HasTranslations` on all four models |
| `declare(strict_types=1)` on every PHP file | CLAUDE.md §3 (implicit from Phase 1/2) | All new PHP files start with `declare(strict_types=1);` |
| Spatie permission guard `'web'` | CLAUDE.md §6 | Filament gate `can('admin-access')` already wired Phase 1; new resources inherit |
| `LogsActivity` trait on every domain entity | CLAUDE.md §6 + D-012 | All four new models (`Game`, `GameRole`, `GameMatchType`, `GameMatchTypeRoleLimit`) use `LogsActivity` |
| Composer stays in `apps/web/` | CLAUDE.md §5 | No new packages needed; if any are added they go to `apps/web/composer.json` |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Game/Role/MatchType/RoleLimit persistence | Database (Postgres tables + FKs + CHECKs + UNIQUE indexes) | API/Backend (Eloquent models with traits) | Schema is the contract; FKs prevent orphaned roles/limits; UNIQUE `(game_id, key)` enforces stability for the seeder firstOrCreate pattern |
| Admin CRUD for all four entities | Frontend Server / SSR (Filament Livewire) | Database | D-012: every domain entity has a Filament Resource. Filament's Repeater/RelationManager handles the create/read/update/delete with audit logging via `LogsActivity` |
| Translatable display strings | Database (JSONB column storage) | API/Backend (`HasTranslations` accessor/mutator) | spatie/laravel-translatable trait + JSONB column is the canonical pattern (Phase 2 established); Filament `KeyValue` renders the locale->string map in admin |
| Seeded HLL preset | API/Backend (`GameSeeder` Eloquent calls) | Database (rows inserted/updated) | Seeder reads array literals (15 roles + 5 match types + capacity matrix) and `firstOrCreate`s rows. Idempotent — re-runnable without overwriting admin edits |
| TypeScript DTO exports | Build-time (`artisan typescript:generate`) | Source-control (`api.d.ts` + `packages/shared-types/src/index.ts`) | Same Phase 1 pattern. Adds `GameData`, `GameRoleData`, `GameMatchTypeData`, `GameMatchTypeRoleLimitData` for future Phase 4+ Vue consumption |
| Audit trail on all 4 entities | Database (`activity_log` table) | API/Backend (`LogsActivity` trait) | D-012 audit infra from Phase 1 captures every Filament create/update/delete via the trait; per-resource Audit tab pattern from Phase 2 inherited |
| Phase 4 capacity enforcement | (Out of scope — Phase 4) | (Out of scope — Phase 4) | RoleLimit.capacity is just a number in Phase 3; Phase 4 introduces row-locked DB transactions to enforce it at signup time. CONTEXT.md "Deferred Ideas" |

---

## Standard Stack

### Core (already installed — Phase 1/2)

| Library | Version (verified composer.lock 2026-05-13) | Purpose for Phase 3 |
|---------|---------|---------------------|
| `laravel/framework` | `^12.0` | Eloquent models, migrations, routing | [VERIFIED: composer.json] |
| `filament/filament` | `^3.3` (3.3.50 in lockfile) | `GameResource`, `GameMatchTypeResource`, Relation Managers | [VERIFIED: composer.json] |
| `spatie/laravel-translatable` | `^6.14` | `HasTranslations` on Game/GameRole/GameMatchType for JSONB locale columns | [VERIFIED: composer.json, installed P2 plan 02-03] |
| `spatie/laravel-activitylog` | `^5.0` | `LogsActivity` trait on all 4 new models for D-012 audit | [VERIFIED: composer.json] |
| `spatie/laravel-data` | `^4.22` | `GameData`, `GameRoleData`, `GameMatchTypeData`, `GameMatchTypeRoleLimitData` DTOs | [VERIFIED: composer.json] |
| `spatie/laravel-typescript-transformer` | `^3.0` | `#[TypeScript]` attribute → `api.d.ts` regeneration | [VERIFIED: composer.json] |
| `spatie/laravel-permission` | `^7.4` | `admin-access` gate inherited from Phase 1 Filament panel provider | [VERIFIED: composer.json] |

### New — Phase 3 requires installation
**None.** All required packages are already in `composer.lock`. Wave 0 has no `composer require` step.

### Filament Translatable Plugin — confirmed NOT needed in Phase 3 (same as Phase 2)
`filament/spatie-laravel-translatable-plugin` is abandoned [VERIFIED: Phase 2 RESEARCH.md] and Phase 3 ships EN-only translatable JSONB (D-013). Use the `KeyValue` pattern (Phase 1 `player.bio`, Phase 2 `clan.description` + `clan_tag.label`) for every translatable field in Phase 3 forms. Locale switcher UI is deferred to Phase 7 CMS.

---

## Architecture Patterns

### System Architecture Diagram

```
Admin browser request
        │
        ▼
[Filament Livewire — admin panel gated by 'admin-access' permission (Phase 1)]
        │
        ├─ GET /admin/games                       → GameResource.ListGames
        ├─ GET /admin/games/create                → GameResource.CreateGame
        ├─ GET /admin/games/{record}              → GameResource.ViewGame
        ├─ GET /admin/games/{record}/edit         → GameResource.EditGame
        │                                           │
        │                                           ├─ RolesRelationManager (table+form on game.roles HasMany)
        │                                           │       │
        │                                           │       └─ inline create/edit/delete: GameRole rows
        │                                           │
        │                                           └─ MatchTypesRelationManager (table+form on game.matchTypes HasMany)
        │                                                   │
        │                                                   └─ inline create: GameMatchType rows
        │                                                       (then click record → MatchTypeResource.edit for RoleLimits)
        │
        ├─ GET /admin/game-match-types            → GameMatchTypeResource.ListGameMatchTypes
        ├─ GET /admin/game-match-types/create     → GameMatchTypeResource.CreateGameMatchType
        ├─ GET /admin/game-match-types/{r}/edit   → GameMatchTypeResource.EditGameMatchType
        │                                           │
        │                                           └─ RoleLimitsRelationManager (table+form on matchType.roleLimits HasMany)
        │                                                   │
        │                                                   └─ inline create/edit/delete: GameMatchTypeRoleLimit rows
        │                                                       (each row: pick GameRole + set capacity)
        │
        ▼
[Eloquent models with HasTranslations + LogsActivity]
        │
        ▼
[Postgres tables: games / game_roles / game_match_types / game_match_type_role_limits]
        │
        ▼
[activity_log writes (causer = auth()->user(), subject = the model)]


Build-time pipeline (unchanged from Phase 1):
spatie/laravel-data DTOs   ─► make artisan ARGS="typescript:generate"
        │                                                     │
        │                                                     ▼
        └────────────────────────────────────────► resources/js/types/api.d.ts (+ shared-types sync)
```

### Recommended Project Structure — new files in Phase 3

```
apps/web/
├── app/
│   ├── Models/
│   │   ├── Game.php                              (NEW — HasTranslations[name], LogsActivity)
│   │   ├── GameRole.php                          (NEW — HasTranslations[display_name], LogsActivity)
│   │   ├── GameMatchType.php                     (NEW — HasTranslations[name, description], LogsActivity)
│   │   └── GameMatchTypeRoleLimit.php            (NEW — pivot-ish model, LogsActivity, NO HasTranslations)
│   ├── Data/
│   │   ├── GameData.php                          (NEW — #[TypeScript], fromModel())
│   │   ├── GameRoleData.php                      (NEW — #[TypeScript], fromModel())
│   │   ├── GameMatchTypeData.php                 (NEW — #[TypeScript], fromModel())
│   │   └── GameMatchTypeRoleLimitData.php        (NEW — #[TypeScript], fromModel())
│   └── Filament/Resources/
│       ├── GameResource.php                      (NEW)
│       ├── GameResource/
│       │   ├── Pages/
│       │   │   ├── ListGames.php
│       │   │   ├── CreateGame.php
│       │   │   ├── ViewGame.php
│       │   │   └── EditGame.php                  (mutateFormDataBeforeSave for name JSONB)
│       │   └── RelationManagers/
│       │       ├── RolesRelationManager.php      (NEW — manages game.roles HasMany)
│       │       └── MatchTypesRelationManager.php (NEW — manages game.matchTypes HasMany)
│       ├── GameMatchTypeResource.php             (NEW)
│       └── GameMatchTypeResource/
│           ├── Pages/
│           │   ├── ListGameMatchTypes.php
│           │   ├── CreateGameMatchType.php
│           │   └── EditGameMatchType.php         (mutateFormDataBeforeSave for name+description JSONB)
│           └── RelationManagers/
│               └── RoleLimitsRelationManager.php (NEW — manages matchType.roleLimits HasMany)
├── database/
│   ├── migrations/
│   │   ├── 2026_05_13_100000_create_games_table.php                          (NEW)
│   │   ├── 2026_05_13_100100_create_game_roles_table.php                     (NEW)
│   │   ├── 2026_05_13_100200_create_game_match_types_table.php               (NEW)
│   │   └── 2026_05_13_100300_create_game_match_type_role_limits_table.php    (NEW)
│   ├── factories/
│   │   ├── GameFactory.php                       (NEW)
│   │   ├── GameRoleFactory.php                   (NEW)
│   │   ├── GameMatchTypeFactory.php              (NEW)
│   │   └── GameMatchTypeRoleLimitFactory.php     (NEW)
│   └── seeders/
│       ├── DatabaseSeeder.php                    (MODIFY — add GameSeeder::class to call list)
│       └── GameSeeder.php                        (NEW — HLL preset: 1 Game + 15 Roles + 5 MatchTypes + capacity matrix)
├── lang/en/
│   └── admin.php                                 (MODIFY — append 'game', 'game_role', 'game_match_type', 'game_match_type_role_limit' key groups; extend 'audit.subject' map)
└── tests/
    ├── Feature/
    │   ├── Models/
    │   │   ├── GameModelTest.php                 (NEW — relationships + LogsActivity + HasTranslations)
    │   │   ├── GameRoleModelTest.php             (NEW — UNIQUE (game_id, key) constraint test)
    │   │   ├── GameMatchTypeModelTest.php        (NEW — UNIQUE (game_id, key) + cascade behavior)
    │   │   └── GameMatchTypeRoleLimitModelTest.php (NEW — UNIQUE (game_match_type_id, game_role_id) + same-game invariant)
    │   ├── Admin/
    │   │   └── GameResourcesPresentTest.php      (NEW — /admin/games + /admin/game-match-types reachable; RelationManager tabs render)
    │   └── Database/
    │       └── GameSeederTest.php                (NEW — idempotency: run twice, count stays the same; admin-edited rows preserved)
    └── Unit/
        └── Data/
            └── GameDataTest.php                  (NEW — fromModel() returns expected JSONB locale array, role + match-type counts)
```

---

### Pattern 1: HasMany Relation Manager for `game.roles` and `game.matchTypes`

**What:** Game has many GameRoles and many GameMatchTypes. Admin edits Game's roles directly from the Game edit page via a RelationManager (no need to navigate away).

**When to use:** Any one-to-many ownership relation where the child is conceptually inside the parent (matches Phase 2 `ClanResource.MembersRelationManager` exactly).

```php
// Source: Phase 2 ClanResource/RelationManagers/MembersRelationManager.php + Context7 /websites/filamentphp_3_x relation-managers
declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';   // matches Game::roles() HasMany method

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label(__('admin.game_role.fields.key'))
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9_]+$/')
                ->helperText(__('admin.game_role.help.key_format')),

            Forms\Components\KeyValue::make('display_name')
                ->label(__('admin.game_role.fields.display_name'))
                ->keyLabel(__('admin.game_role.fields.display_name_locale'))
                ->valueLabel(__('admin.game_role.fields.display_name_text'))
                ->reorderable(false)
                ->default(['en' => ''])
                ->required(),

            Forms\Components\TextInput::make('sort_order')
                ->label(__('admin.game_role.fields.sort_order'))
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label(__('admin.game_role.fields.is_active'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.game_role.fields.sort_order'))->sortable(),
                Tables\Columns\TextColumn::make('key')->label(__('admin.game_role.fields.key'))->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('admin.game_role.fields.display_name'))
                    ->getStateUsing(fn ($record): string => is_array($record->display_name) ? ($record->display_name['en'] ?? '—') : '—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
```

**Critical:** The `Game::roles()` HasMany relationship in the model is the canonical source of truth — Filament's `protected static string $relationship` must match this method name exactly.

---

### Pattern 2: Two-Tier Filament Resource Pattern (Game → MatchType → RoleLimit)

**What:** Filament v3 does not support RelationManager nested inside RelationManager. The canonical pattern for two-level nesting is:

- Top-level Resource A (Game) — has RelationManager B (MatchTypes)
- Top-level Resource B (GameMatchType) — has RelationManager C (RoleLimits)

The user enters MatchTypes from the Game edit page → sees inline list → clicks a row → Filament routes to `/admin/game-match-types/{id}/edit` where RoleLimits are managed.

**Why this matches Phase 2 precedent:** Phase 2 has `ClanResource` (with MembersRelationManager) AND a top-level `ClanMembershipResource` for direct admin access. Phase 3 mirrors this exactly.

```php
// Source: Phase 2 ClanResource.php + ClanMembershipResource.php (verified codebase)
// In GameResource::getRelations():
public static function getRelations(): array
{
    return [
        RelationManagers\RolesRelationManager::class,
        RelationManagers\MatchTypesRelationManager::class,
    ];
}

// In GameMatchTypeResource::getRelations():
public static function getRelations(): array
{
    return [
        RelationManagers\RoleLimitsRelationManager::class,
    ];
}
```

**Alternative considered (and rejected):** Filament `Repeater::make('roleLimits')->relationship()` inline in the MatchType form. Pro: single edit screen. Con: when scaling to ~15 row capacity matrix per MatchType, the form becomes cramped; Repeater's per-row Select scoping to "only roles of THIS game" is awkward to express. RelationManager + table view scales cleanly. [VERIFIED: Context7 /websites/filamentphp_3_x forms/fields/repeater + relation-managers]

---

### Pattern 3: Scoping the GameRole Select inside RoleLimits RelationManager

**What:** When admin creates a `GameMatchTypeRoleLimit` row, they must pick a `GameRole`. The Select MUST be scoped to roles of the SAME `game_id` as the MatchType — picking a role from a different game would be a domain violation.

```php
// Source: Context7 /websites/filamentphp_3_x relation-managers "Access owner record in form callback"
// In RoleLimitsRelationManager::form():
Forms\Components\Select::make('game_role_id')
    ->label(__('admin.game_match_type_role_limit.fields.role'))
    ->options(function (RelationManager $livewire): array {
        /** @var \App\Models\GameMatchType $matchType */
        $matchType = $livewire->getOwnerRecord();

        return $matchType->game->roles()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($role) => [$role->id => $role->display_name['en'] ?? $role->key])
            ->toArray();
    })
    ->required()
    ->searchable(),
```

**Defense-in-depth:** Also enforce same-game invariant at the model layer via a `saving()` event listener OR a DB-level check via a custom trigger. Recommendation: keep the check in the Filament Select (cheap, clear) AND a Pest test that asserts `GameMatchTypeRoleLimit::create([...])` with mismatched game_id throws (this catches future API/Console writes). DB-level CHECK requires a custom function on Postgres because the relationship spans two tables — not worth the complexity unless we have real cross-tier risk.

---

### Pattern 4: KeyValue for Translatable JSONB (continuing Phase 2 pattern)

**What:** EN-only translatable JSONB in Filament forms uses `KeyValue` component, NOT the abandoned `filament/spatie-laravel-translatable-plugin`.

```php
// Source: ClanResource.php (Phase 2 verified codebase) + ClanTagResource.php
Forms\Components\KeyValue::make('name')
    ->label(__('admin.game.fields.name'))
    ->keyLabel(__('admin.game.fields.name_locale'))
    ->valueLabel(__('admin.game.fields.name_text'))
    ->reorderable(false)
    ->default(['en' => ''])
    ->required(),
```

**Required mutator** (Phase 2 Pitfall 6 — Filament KeyValue submits `null` on empty form):

```php
// In CreateGame, EditGame, CreateGameMatchType, EditGameMatchType pages:
/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
protected function mutateFormDataBeforeSave(array $data): array
{
    $data['name'] = $data['name'] ?: ['en' => ''];
    // For GameMatchType which has 2 translatable fields:
    // $data['description'] = $data['description'] ?: ['en' => ''];

    return $data;
}
```

---

### Pattern 5: Seeder Idempotency for Game + Roles + MatchTypes + RoleLimits

**What:** Seeder must be re-runnable without overwriting admin edits. Phase 2 established this pattern: `firstOrCreate(['unique_key' => $value], [other_attrs])`. The `unique_key` row stays once created; subsequent runs find it and skip.

**Critical for D-007:** Seeder seeds initial schema; admin edits are NOT clobbered on `php artisan db:seed --class=GameSeeder` re-run.

```php
// Source: Phase 2 ClanTagSeeder.php pattern + DiscordGuildSeeder.php singleton
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Game (unique key = 'hll')
        $hll = Game::firstOrCreate(
            ['key' => 'hll'],
            ['name' => ['en' => 'Hell Let Loose'], 'is_active' => true]
        );

        // 2. Roles — 15 entries per CONTEXT.md success criterion #3
        //    Idempotency key = (game_id, key) — matches the UNIQUE composite index
        /** @var list<array{key: string, display: string, sort: int}> $roles */
        $roles = [
            // Command
            ['key' => 'commander',      'display' => 'Commander',          'sort' => 10],
            ['key' => 'officer',        'display' => 'Officer',            'sort' => 20],
            ['key' => 'squad_leader',   'display' => 'Squad Leader',       'sort' => 30],
            // Infantry
            ['key' => 'rifleman',       'display' => 'Rifleman',           'sort' => 40],
            ['key' => 'assault',        'display' => 'Assault',            'sort' => 50],
            ['key' => 'automatic_rifleman', 'display' => 'Automatic Rifleman', 'sort' => 60],
            ['key' => 'medic',          'display' => 'Medic',              'sort' => 70],
            ['key' => 'engineer',       'display' => 'Engineer',           'sort' => 80],
            ['key' => 'support',        'display' => 'Support',            'sort' => 90],
            ['key' => 'machine_gunner', 'display' => 'Machine Gunner',     'sort' => 100],
            ['key' => 'anti_tank',      'display' => 'Anti-Tank',          'sort' => 110],
            // Recon
            ['key' => 'sniper',         'display' => 'Sniper',             'sort' => 120],
            ['key' => 'spotter',        'display' => 'Spotter',            'sort' => 130],
            // Armor
            ['key' => 'tank_commander', 'display' => 'Tank Commander',     'sort' => 140],
            ['key' => 'crewman',        'display' => 'Crewman',            'sort' => 150],
        ];

        foreach ($roles as $r) {
            GameRole::firstOrCreate(
                ['game_id' => $hll->id, 'key' => $r['key']],
                [
                    'display_name' => ['en' => $r['display']],
                    'sort_order' => $r['sort'],
                    'is_active' => true,
                ]
            );
        }

        // 3. Match types — 5 entries per CONTEXT.md
        /** @var list<array{key: string, name: string, description: string}> $matchTypes */
        $matchTypes = [
            ['key' => 'scrim_50v50',  'name' => 'Scrim 50v50',  'description' => '50-vs-50 competitive scrimmage'],
            ['key' => 'skirmish_6v6', 'name' => 'Skirmish 6v6', 'description' => 'Small-format 6-vs-6 skirmish'],
            ['key' => 'friendly',     'name' => 'Friendly',     'description' => 'Casual unranked friendly match'],
            ['key' => 'tournament',   'name' => 'Tournament',   'description' => 'Tournament-bracket match'],
            ['key' => 'clan_war',     'name' => 'Clan War',     'description' => 'Inter-clan ranked war'],
        ];

        $matchTypeIds = [];
        foreach ($matchTypes as $m) {
            $mt = GameMatchType::firstOrCreate(
                ['game_id' => $hll->id, 'key' => $m['key']],
                [
                    'name' => ['en' => $m['name']],
                    'description' => ['en' => $m['description']],
                    'is_active' => true,
                ]
            );
            $matchTypeIds[$m['key']] = $mt->id;
        }

        // 4. Role limits — capacity matrix per match type
        //    Scrim 50v50: full HLL composition (50 slots distributed across 15 roles)
        //    Skirmish 6v6: minimal composition (6 slots, infantry-only)
        //    Others: zero-capacity defaults — admin edits via Filament after seed
        $this->seedRoleLimits($hll, $matchTypeIds);
    }

    /**
     * Seed capacity matrix. Idempotency key = (game_match_type_id, game_role_id).
     *
     * @param  array<string, string>  $matchTypeIds
     */
    private function seedRoleLimits(Game $hll, array $matchTypeIds): void
    {
        $roles = $hll->roles()->get()->keyBy('key');

        // Scrim 50v50 capacities (50 total slots — example distribution)
        $scrim = [
            'commander' => 1, 'officer' => 4, 'squad_leader' => 4,
            'rifleman' => 14, 'assault' => 4, 'automatic_rifleman' => 4,
            'medic' => 4, 'engineer' => 4, 'support' => 4,
            'machine_gunner' => 2, 'anti_tank' => 2,
            'sniper' => 1, 'spotter' => 1,
            'tank_commander' => 1, 'crewman' => 0,  // 50 total
        ];

        foreach ($scrim as $roleKey => $capacity) {
            GameMatchTypeRoleLimit::firstOrCreate(
                [
                    'game_match_type_id' => $matchTypeIds['scrim_50v50'],
                    'game_role_id' => $roles[$roleKey]->id,
                ],
                ['capacity' => $capacity, 'sort_order' => $roles[$roleKey]->sort_order]
            );
        }

        // Skirmish 6v6 — 6 infantry slots
        $skirmish = [
            'squad_leader' => 1, 'rifleman' => 2, 'assault' => 1,
            'medic' => 1, 'support' => 1,
        ];
        foreach ($skirmish as $roleKey => $capacity) {
            GameMatchTypeRoleLimit::firstOrCreate(
                [
                    'game_match_type_id' => $matchTypeIds['skirmish_6v6'],
                    'game_role_id' => $roles[$roleKey]->id,
                ],
                ['capacity' => $capacity, 'sort_order' => $roles[$roleKey]->sort_order]
            );
        }

        // Friendly / Tournament / Clan War — leave capacity rows empty
        // Admin fills via Filament RelationManager UI (this honors D-007: zero code change)
    }
}
```

**Note on capacity matrix:** The 50v50 and 6v6 distributions above are RECOMMENDATIONS, not authoritative HLL competitive standards. Treat them as starter defaults — admins WILL edit them in Filament. [ASSUMED: distribution is illustrative; operator may tune for league rules. See Open Questions Q2.]

---

### Pattern 6: spatie/laravel-data DTO with HasTranslations

**What:** Same pattern as Phase 2 `ClanData::fromModel()` — use `getTranslations('field')` to surface the FULL JSONB array (`['en' => 'Hell Let Loose']`) rather than the active-locale scalar.

```php
// Source: Phase 2 ClanData.php (verified codebase) + Context7 /spatie/laravel-translatable
declare(strict_types=1);

namespace App\Data;

use App\Models\Game;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class GameData extends Data
{
    /**
     * @param  array<string, string>|null  $name
     * @param  list<GameRoleData>  $roles
     * @param  list<GameMatchTypeData>  $match_types
     */
    public function __construct(
        public string $id,
        public string $key,
        public ?array $name,
        public bool $is_active,
        public array $roles,
        public array $match_types,
    ) {}

    public static function fromModel(Game $game): self
    {
        /** @var list<GameRoleData> $roles */
        $roles = $game->relationLoaded('roles')
            ? $game->roles->map(fn ($r) => GameRoleData::fromModel($r))->all()
            : [];

        /** @var list<GameMatchTypeData> $matchTypes */
        $matchTypes = $game->relationLoaded('matchTypes')
            ? $game->matchTypes->map(fn ($m) => GameMatchTypeData::fromModel($m))->all()
            : [];

        return new self(
            id: $game->id,
            key: $game->key,
            name: $game->getTranslations('name') ?: null,
            is_active: $game->is_active,
            roles: $roles,
            match_types: $matchTypes,
        );
    }
}
```

---

### Anti-Patterns to Avoid

- **Hardcoding HLL roles in code.** D-007 explicitly says HLL is a SEEDED preset. Never `if ($game->key === 'hll') { ... }` in any controller, model, or service. The `is_active` boolean + `sort_order` integer cover every game-presence concern.
- **Using `Schema::unique(['game_id', 'key'])` then forgetting the composite index name.** Laravel auto-generates a long name; for the seeder to read INSERT errors cleanly, name the index explicitly: `$table->unique(['game_id', 'key'], 'game_roles_game_id_key_unique')`.
- **Two-level RelationManager nesting.** Filament v3 does not support this. Use the two-resource pattern (Pattern 2).
- **Cross-game roleLimit insertion via API.** Filament Select scoping (Pattern 3) catches it via UI; add a Pest test that creates a RoleLimit pointing at a role from a different game and assert it fails OR is invalid via a model `saving()` listener. Do NOT rely solely on UI scoping.
- **Capacity = `null` allowed.** Capacity must be a non-negative integer. Validate at form layer (`->numeric()->minValue(0)`) and DB layer (`CHECK (capacity >= 0)`).
- **Forgetting `LogsActivity` on `GameMatchTypeRoleLimit`.** The pivot-ish table is still a domain entity per D-012 — admins change capacity numbers and the audit log must record who/when. Treat it as a first-class model, not a pure pivot.
- **Adding accent colour / banner / image to `games` in P3.** Image storage primitives don't exist yet; CONTEXT.md "Deferred Ideas" lists multi-game UI for Phase 4+. Keep `games` schema minimal.
- **Filament forms with hardcoded English labels.** Every `->label('Game name')` violates D-013. Always `->label(__('admin.game.fields.name'))`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSONB locale storage for game/role/match-type names | Custom `protected $casts = [...] 'name' => 'array'` + manual locale resolution | `spatie/laravel-translatable` `HasTranslations` trait | Auto-handles fallback locale + locale switching via `app()->setLocale()`; same pattern Phase 2 used for `clan.description` |
| Audit log on Filament writes | Custom observer + log table | `spatie/laravel-activitylog` `LogsActivity` trait (already installed) | Per-resource Audit tab pattern (Phase 1 plan 01-14) auto-binds to it; no extra wiring |
| TypeScript DTO export for new entities | Hand-edit `api.d.ts` | `make artisan ARGS="typescript:generate"` (Phase 1 plan 01-15) | Regenerates from `#[TypeScript]`-attributed Data classes; syncs to `packages/shared-types/src/index.ts` |
| Filament form for nested HasMany | Custom Livewire form with manual save | `RelationManager` (Filament v3 first-class) | Handles create/edit/delete/reorder; integrates with audit log; matches Phase 2 precedent |
| Scoped Select inside RelationManager | Custom JS or duplicate form logic | `Select::options(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->...)` | Filament passes the parent via `$livewire->getOwnerRecord()`; the lambda is reactive and stays in PHP land [VERIFIED: Context7 /websites/filamentphp_3_x] |
| Seeder idempotency for translatable models | Manual exists() check + INSERT | `Model::firstOrCreate(['unique_key' => $val], [other_attrs])` (Phase 2 pattern) | The second-argument `[other_attrs]` runs ONLY on create, never on second-run lookup — admin edits to those attrs are preserved |
| Filament Resource Page mutators for JSONB null coercion | Custom save listener on the Eloquent model | `mutateFormDataBeforeSave()` on CreateXxx + EditXxx pages | Same Phase 2 Pitfall 6 mitigation; keep the coercion at the form layer where the null originates |

**Key insight:** Phase 3 is the simplest phase yet on this codebase — zero new packages, all patterns already proven by Phase 1 + Phase 2. The work is applying them four times.

---

## Common Pitfalls

### Pitfall 1: Composite UNIQUE index name collision with Schema default
**What goes wrong:** `$table->unique(['game_id', 'key'])` generates an auto-name like `game_roles_game_id_key_unique` which COLLIDES with the default name Laravel would generate for `$table->unique('key')` if both existed.
**Why it happens:** Both indexes pass through the same auto-naming hash truncation under the hood.
**How to avoid:** Always name composite indexes explicitly: `$table->unique(['game_id', 'key'], 'game_roles_game_id_key_unique');` and in the migration body, NEVER add a single-column `unique` on `key` — the UNIQUE scope is `(game_id, key)` per the success criterion.
**Warning signs:** Migration succeeds locally but `migrate:fresh` fails on CI with "duplicate key value violates unique constraint" on an unexpected index name.

### Pitfall 2: HasTranslations + Filament KeyValue + `null` JSONB column nullability
**What goes wrong:** `KeyValue::make('name')` returns `null` when form is submitted with all rows blank. The `HasTranslations` trait then chokes because `$model->name = null` writes `null` to the JSONB column, but the migration declared `jsonb('name')->nullable()` so the DB accepts it — the model now has `name = null` AND `$translatable = ['name']`, so accessing `$game->name` returns `null` (no fallback to default).
**Why it happens:** Phase 2 Pitfall 6 (verified by codebase EditClan.php) — the form data MUST be coerced to `['en' => '']` BEFORE save, not by the DB schema.
**How to avoid:** Apply `mutateFormDataBeforeSave()` mutator on EVERY Create + Edit page that touches a translatable JSONB field. `GameMatchType` has TWO translatable fields (`name` + `description`), so mutator must coerce BOTH.
**Warning signs:** Filament edit form looks fine, save succeeds, but `$game->name` returns null and templates display `—`.

### Pitfall 3: Filament RelationManager `$relationship` typo silently mounts blank table
**What goes wrong:** `protected static string $relationship = 'rolez'` doesn't throw — Filament finds no method, mounts an empty table, and create actions fail with confusing "method does not exist" errors when you click +.
**Why it happens:** Filament resolves `$relationship` lazily; the typo only surfaces when the relation is exercised.
**How to avoid:** Add a Pest test that GET-s `/admin/games/{record}/edit`, asserts 200, and checks the RelationManager tab content renders. `Admin/GameResourcesPresentTest.php` covers this.
**Warning signs:** Empty table where the seeded data should appear; clicking "Create" throws BadMethodCallException.

### Pitfall 4: spatie/laravel-data `Data::fromModel()` returns wrong type for translatable field
**What goes wrong:** `GameData::from($game)` (the package's default factory method) calls `$game->name` which returns the ACTIVE LOCALE STRING, then tries to assign it to `public ?array $name` — type mismatch.
**Why it happens:** Phase 2 RESEARCH already covered this (ClanData pattern). `spatie/laravel-data` auto-mapping uses the public accessor; for HasTranslations, the public accessor returns the locale-resolved string, not the JSONB array.
**How to avoid:** Always define `public static function fromModel(Game $game): self` on the Data class and call `$game->getTranslations('name')` explicitly. Document this in the Data class docblock.
**Warning signs:** `\TypeError` on DTO construction in tests: "Cannot assign string to property of type array".

### Pitfall 5: Seeder runs twice and creates duplicate role limits
**What goes wrong:** Seeder `GameMatchTypeRoleLimit::create(...)` (not `firstOrCreate`) creates duplicates on second run, violating the `UNIQUE (game_match_type_id, game_role_id)` index.
**Why it happens:** Developer copy-pastes the pattern from a non-idempotent context.
**How to avoid:** Always use `firstOrCreate([lookup_keys], [other_attrs])` and ensure `[lookup_keys]` matches the UNIQUE index columns exactly. Write a Pest test `GameSeederTest` that calls `$this->seed(GameSeeder::class)` twice and asserts table counts are unchanged after the second run.
**Warning signs:** Re-running seeder throws QueryException on the second insert.

### Pitfall 6: PHPStan L8 flags `public array $translatable` on new models
**What goes wrong:** Same as Phase 2 Pitfall 7 — `public array $translatable` without generics flags at L8.
**Why it happens:** Trait expects the property but doesn't declare its shape; L8 demands explicit list type.
**How to avoid:** Annotate every translatable property: `/** @var list<string> */ public array $translatable = ['name'];`
**Warning signs:** `make phpstan` reports "Property App\Models\Game::$translatable has no type specified".

### Pitfall 7: Cascade-on-delete for `game_match_type_role_limits` parent (matchType OR role)
**What goes wrong:** If an admin deletes a GameRole, all RoleLimits referencing it become orphan. If they delete a MatchType, same. Without explicit cascade rules, FK violations block deletion entirely (admin frustrated).
**Why it happens:** Default FK behaviour in Laravel is `noAction`/`restrict`.
**How to avoid:** Set `cascadeOnDelete()` on BOTH FKs in `game_match_type_role_limits` — if a parent (MatchType or Role) is deleted, the capacity rows go too. This is safe because RoleLimits are NOT historical records (unlike ClanMembership rows). However, in Phase 4+, signed-up match slots WILL reference these — at that point a softer `restrictOnDelete` may be needed. For Phase 3 isolation, cascade is correct.
**Warning signs:** Admin clicks "Delete role" in Filament, gets a stack trace about FK violation.

### Pitfall 8: Filament Resource registration order / navigation grouping
**What goes wrong:** Without `protected static ?string $navigationGroup = 'Platform'`, the new `GameResource` and `GameMatchTypeResource` appear at the top of the sidebar alphabetically, displacing User/Player. Existing operators expect User/Player at the top.
**Why it happens:** Filament sorts resources by `$navigationSort` within their group; without a group, they default to the unnamed group at the top.
**How to avoid:** Add `protected static ?string $navigationGroup = 'Platform';` and `protected static ?int $navigationSort = N` on each new resource. Recommend a `Platform` navigation group containing Games + GameMatchTypes; Clan resources stay in their existing group; User/Player stay in theirs.
**Warning signs:** Operator complaint: "Game resource jumped to the top of the sidebar."

### Pitfall 9: Seeder ordering — DatabaseSeeder calls before tables exist
**What goes wrong:** `DatabaseSeeder` calls `GameSeeder::class` but Phase 3 migrations haven't run yet on a fresh DB — Eloquent throws table-not-found.
**Why it happens:** `php artisan migrate:fresh --seed` runs migrations first THEN seeders, so this isn't a problem for the canonical path. But `php artisan db:seed` (without migrate:fresh) on an old schema state will fail.
**How to avoid:** Ensure `DatabaseSeeder` adds `GameSeeder::class` to the list AFTER the existing `ClanTagSeeder::class` entry. Order: PermissionSeeder → DiscordGuildSeeder → ClanTagSeeder → GameSeeder. Document in DatabaseSeeder comment.
**Warning signs:** Operator reports "table 'games' does not exist" when running `db:seed`.

### Pitfall 10: Cross-game role assignment via RoleLimit creation (no DB-level constraint)
**What goes wrong:** Admin creates a `GameMatchTypeRoleLimit` where `game_match_type_id` belongs to game A but `game_role_id` belongs to game B. Domain invariant violated.
**Why it happens:** No FK in standard schema design crosses two tables to enforce this — it's a tri-table invariant.
**How to avoid:** Two-pronged defense:
  1. Filament UI Select scoping (Pattern 3) makes it impossible from the admin UI.
  2. Eloquent `saving()` listener on `GameMatchTypeRoleLimit` model verifies `$model->matchType->game_id === $model->role->game_id` and throws DomainException otherwise.
  3. Pest test that creates a cross-game RoleLimit asserts the exception.
**Warning signs:** A RoleLimit row exists where matchType.game_id ≠ role.game_id — manifests as a Phase 4 slot-template oddity.

---

## Code Examples

### Migration: games table
```php
// Source: Phase 2 clans migration pattern + .docs/05-database-schema.md conventions
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('key')->unique();           // 'hll', 'cs2', 'r6s', etc — slug-safe
            $table->jsonb('name');                    // translatable
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE games ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE games ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE games ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE games ADD CONSTRAINT games_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
```

### Migration: game_roles table
```php
// Source: Phase 2 clan_tags migration pattern + composite unique
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->text('key');                  // 'commander', 'rifleman', etc — game-scoped
            $table->jsonb('display_name');         // translatable
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            // CRITICAL: composite UNIQUE on (game_id, key) — named explicitly per Pitfall 1
            $table->unique(['game_id', 'key'], 'game_roles_game_id_key_unique');
        });

        DB::statement('ALTER TABLE game_roles ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE game_roles ADD CONSTRAINT game_roles_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
        DB::statement("ALTER TABLE game_roles ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_roles ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_roles');
    }
};
```

### Migration: game_match_types table
```php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_match_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->text('key');                       // 'scrim_50v50', 'tournament', etc
            $table->jsonb('name');                      // translatable
            $table->jsonb('description')->nullable();   // translatable
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->unique(['game_id', 'key'], 'game_match_types_game_id_key_unique');
        });

        DB::statement('ALTER TABLE game_match_types ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE game_match_types ADD CONSTRAINT game_match_types_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
        DB::statement("ALTER TABLE game_match_types ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_match_types ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_match_types');
    }
};
```

### Migration: game_match_type_role_limits table
```php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_match_type_role_limits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_match_type_id');
            $table->uuid('game_role_id');
            $table->integer('capacity');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // cascadeOnDelete: deleting a MatchType or Role removes its capacity rows
            // (RoleLimits are configuration, not historical records — safe to lose).
            // Pre-Phase-4 NOTE: if Phase 4 introduces signed-up slots referencing RoleLimit, revisit.
            $table->foreign('game_match_type_id')->references('id')->on('game_match_types')->cascadeOnDelete();
            $table->foreign('game_role_id')->references('id')->on('game_roles')->cascadeOnDelete();

            $table->unique(
                ['game_match_type_id', 'game_role_id'],
                'gmtrl_match_type_role_unique'
            );
        });

        DB::statement('ALTER TABLE game_match_type_role_limits ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement('ALTER TABLE game_match_type_role_limits ADD CONSTRAINT gmtrl_capacity_check CHECK (capacity >= 0);');
        DB::statement("ALTER TABLE game_match_type_role_limits ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_match_type_role_limits ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_match_type_role_limits');
    }
};
```

### Model: Game.php
```php
declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Game {$event}");
    }

    /** @return HasMany<GameRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(GameRole::class)->orderBy('sort_order');
    }

    /** @return HasMany<GameMatchType, $this> */
    public function matchTypes(): HasMany
    {
        return $this->hasMany(GameMatchType::class);
    }
}
```

### Model: GameMatchTypeRoleLimit.php with same-game saving guard
```php
declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameMatchTypeRoleLimitFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GameMatchTypeRoleLimit extends Model
{
    /** @use HasFactory<GameMatchTypeRoleLimitFactory> */
    use HasFactory;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['game_match_type_id', 'game_role_id', 'capacity', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['capacity' => 'integer', 'sort_order' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /** @return BelongsTo<GameMatchType, $this> */
    public function matchType(): BelongsTo
    {
        return $this->belongsTo(GameMatchType::class, 'game_match_type_id');
    }

    /** @return BelongsTo<GameRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'game_role_id');
    }

    /**
     * Same-game invariant defense-in-depth (Pitfall 10).
     * Filament Select scoping is the primary guard; this catches API/Console writes.
     */
    protected static function booted(): void
    {
        static::saving(function (self $limit): void {
            $matchTypeGameId = $limit->matchType?->game_id;
            $roleGameId = $limit->role?->game_id;

            if ($matchTypeGameId !== null && $roleGameId !== null && $matchTypeGameId !== $roleGameId) {
                throw new DomainException(
                    'GameMatchTypeRoleLimit: matchType.game_id and role.game_id must match.'
                );
            }
        });
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | Source / When Changed | Impact |
|--------------|------------------|-----------------------|--------|
| Hardcoded game enums (`if game === HLL`) | Generic 4-table relational model with seeded preset | D-007 (locked) | Phase 3 adds new game = data entry, not code |
| `filament/spatie-laravel-translatable-plugin` | `KeyValue` form component for EN-only locale management | Phase 2 RESEARCH § (plugin abandoned) | Same approach continues — no plugin install needed |
| Filament v3 (current) | Filament v4 stable mid-2026 | Filament releases (Context7 shows v4.0.0-beta15 + v5.1.1) | Project is LOCKED to v3 (CLAUDE.md §2) — don't upgrade in Phase 3 |
| spatie/laravel-data v3 | v4.22 (installed) | Phase 1 install | Use `fromModel()` static factories as established Phase 2 pattern |
| Two-level RelationManager nesting | Two top-level Resources with their own RelationManagers | Filament v3 doc constraint | Pattern 2 in this research |

**Deprecated/outdated:**
- `filament/spatie-laravel-translatable-plugin` — abandoned per Phase 2 RESEARCH; KeyValue is the replacement
- Filament v3's `protected static string $navigationGroup` as untranslated string — D-013 requires `__('admin.nav.platform')` (we'll add this key)

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 with `pest-plugin-laravel` |
| Config file | `apps/web/phpunit.xml` |
| Quick run command | `make pest ARGS="--filter=Game"` |
| Full suite command | `make pest` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|--------------|
| REQ-platform-vision | `games` table exists; `key` UNIQUE | Feature | `make pest ARGS="--filter=GameModelTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-1) | `(game_id, key)` UNIQUE on game_roles | Feature | `make pest ARGS="--filter=GameRoleModelTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-1) | `(game_id, key)` UNIQUE on game_match_types | Feature | `make pest ARGS="--filter=GameMatchTypeModelTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-2) | `(game_match_type_id, game_role_id)` UNIQUE on RoleLimits | Feature | `make pest ARGS="--filter=GameMatchTypeRoleLimitModelTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-2) | Cross-game RoleLimit creation throws DomainException | Feature | `make pest ARGS="--filter=GameMatchTypeRoleLimitModelTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-3) | GameSeeder is idempotent — running twice does not duplicate | Feature | `make pest ARGS="--filter=GameSeederTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-3) | GameSeeder produces 1 Game + 15 Roles + 5 MatchTypes | Feature | `make pest ARGS="--filter=GameSeederTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-3) | GameSeeder respects admin edits on re-run (firstOrCreate preserves other_attrs) | Feature | `make pest ARGS="--filter=GameSeederTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-1) | `/admin/games` returns 200 for admin | Feature | `make pest ARGS="--filter=GameResourcesPresentTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-2) | `/admin/game-match-types` returns 200 for admin | Feature | `make pest ARGS="--filter=GameResourcesPresentTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-1) | RolesRelationManager mounted on GameResource edit | Feature | `make pest ARGS="--filter=GameResourcesPresentTest"` | ❌ Wave 0 |
| REQ-platform-vision (SC-2) | RoleLimitsRelationManager mounted on GameMatchTypeResource edit | Feature | `make pest ARGS="--filter=GameResourcesPresentTest"` | ❌ Wave 0 |
| REQ-platform-vision | GameData::fromModel returns JSONB locale array for `name` | Unit | `make pest ARGS="--filter=GameDataTest"` | ❌ Wave 0 |
| D-012 | All 4 models log activity on create/update | Feature | `make pest ARGS="--filter=ModelTest"` (covered in per-model tests) | ❌ Wave 0 |
| D-013 | No hardcoded strings in any Filament resource | Feature | NoHardcodedStringsTest (existing, auto-covers `__()` calls in Resources) | ✅ exists (Phase 1) |

### Sampling Rate
- **Per task commit:** `make pest ARGS="--filter=Game"` (Phase 3 only, ~5s expected)
- **Per wave merge:** `make pest` (full suite, ~17s baseline P2 + ~3s P3 additions)
- **Phase gate:** Full suite green + `make pint ARGS="--test"` + `make phpstan` before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Models/GameModelTest.php` — covers REQ-platform-vision base model
- [ ] `tests/Feature/Models/GameRoleModelTest.php` — covers SC-1 composite UNIQUE
- [ ] `tests/Feature/Models/GameMatchTypeModelTest.php` — covers SC-2 composite UNIQUE + cascade
- [ ] `tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php` — covers SC-2 + Pitfall 10 cross-game guard
- [ ] `tests/Feature/Database/GameSeederTest.php` — covers SC-3 idempotency
- [ ] `tests/Feature/Admin/GameResourcesPresentTest.php` — covers SC-1 + SC-2 Filament reachability + Pitfall 3 RelationManager rendering
- [ ] `tests/Unit/Data/GameDataTest.php` — covers DTO fromModel translatable handling
- [ ] `database/factories/GameFactory.php`, `GameRoleFactory.php`, `GameMatchTypeFactory.php`, `GameMatchTypeRoleLimitFactory.php` — Wave 0 scaffolding for factory-based tests
- [ ] `lang/en/admin.php` key extensions — Wave 0 prerequisite for any Filament resource (label) rendering

---

## Security Domain

`security_enforcement: true`, ASVS level 1.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|------------------|
| V2 Authentication | inherited | Filament panel auth via Discord OAuth from Phase 1; no new auth surface |
| V3 Session Management | inherited | No new session logic in Phase 3 |
| V4 Access Control | **yes** | Filament admin gate `can('admin-access')` from Phase 1 covers all four new resources; spatie/permission `default_guard = 'web'` ensures Filament `web` guard alignment (CLAUDE.md §6) |
| V5 Input Validation | **yes** | Game `key`, GameRole `key`, GameMatchType `key` validated by regex `/^[a-z0-9_]+$/` at form layer AND DB CHECK constraint; capacity validated `numeric()->minValue(0)` at form + `CHECK (capacity >= 0)` at DB |
| V6 Cryptography | no | No new encryption; all data is plaintext domain configuration |

### Known Threat Patterns for This Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Cross-game RoleLimit forgery via API | Tampering | Filament Select scoping (Pattern 3) + model `saving()` listener (Pitfall 10) + Pest test |
| Mass-assignment on RoleLimit | Tampering | Strict `$fillable` list; never `fill($request->all())` from any future controller |
| Capacity overflow exploit (Phase 4) | Elevation of Privilege | OUT OF SCOPE Phase 3; Phase 4 introduces DB transaction + row lock at signup time |
| Invalid `key` slug injection (path traversal in URL?) | Tampering | Filament uses `record` UUID, not `key`, in admin URLs. Regex CHECK constraint at DB layer prevents slash/dot characters even if a future controller misuses `key` |
| XSS via JSONB display_name | Tampering | All output goes through Filament's escaped Blade rendering OR Inertia's Vue `{{ }}` escaping in Phase 4+; no `v-html` ever applied to translatable fields |
| IDOR on GameMatchType edit | Elevation of Privilege | Filament admin-access gate is the boundary — no public edit surface in Phase 3 |
| Audit log tampering | Tampering | `activity_log` is append-only via `LogsActivity` (no Filament UI exposes edit/delete per CLAUDE.md §6) |

---

## Environment Availability

Step 2.6 — same environment as Phase 2.

| Dependency | Required By | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| Postgres 16 | All migrations + composite UNIQUE indexes + CHECK constraints | ✓ (via Docker) | 16.x [VERIFIED: Phase 1 + 2 plans] | — |
| `spatie/laravel-translatable` | Game.name + GameRole.display_name + GameMatchType.name/description JSONB | ✓ (installed Phase 2) | `^6.14` [VERIFIED: composer.json 2026-05-13] | — |
| `spatie/laravel-data` | All 4 DTOs | ✓ (installed Phase 1) | `^4.22` [VERIFIED] | — |
| `spatie/laravel-activitylog` | LogsActivity on all 4 models | ✓ (installed Phase 1) | `^5.0` [VERIFIED] | — |
| `filament/filament` | GameResource + GameMatchTypeResource + 3 RelationManagers | ✓ (installed Phase 1) | `^3.3` (3.3.50) [VERIFIED] | — |
| Pest 4 + PHPStan L8 + Pint | All quality gates | ✓ | [VERIFIED: Phase 1 + 2] | — |

**Missing dependencies:** None. Phase 3 has zero `composer require` steps.

---

## Assumptions Log

> Claims tagged `[ASSUMED]` should be confirmed with the user via `/gsd-discuss-phase` or accepted explicitly by the planner.

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | "Officer" and "Squad Leader" are distinct league-roster entries — CONTEXT.md success criterion #3 lists both | Pattern 5 § Seeder + Open Question Q1 | If they should be a single role, the seeder needs one fewer GameRole row (14 roles total instead of 15); SC-3 wording mentions "15 roles" so this is the safest reading |
| A2 | Scrim 50v50 capacity matrix (1 commander, 4 officers, 4 SLs, 14 riflemen, etc) is illustrative not authoritative HLL competitive standard | Pattern 5 § Seeder | If specific league rules dictate different defaults, admin can adjust via Filament after seed — no migration change needed (this is by design per D-007) |
| A3 | `cascadeOnDelete` on game_match_type_role_limits FKs is safe in Phase 3 | Pitfall 7 + Migration | If Phase 4 introduces signed-up match slots that reference RoleLimit rows directly, cascade-deleting will orphan history — revisit FK in Phase 4 wave 0 |
| A4 | `is_active` boolean on Game/GameRole/GameMatchType is sufficient for "deactivate without delete"; no separate `archived_at` timestamp needed | Schema § | If audit-trail of deactivation events is required, the `activity_log` covers it (event = "Game updated" with is_active=false delta) |
| A5 | Filament v3 supports `Select::options(fn (RelationManager $livewire) => ...)` for owner-scoped options | Pattern 3 | [VERIFIED via Context7 /websites/filamentphp_3_x relation-managers — high confidence] |
| A6 | Cross-game RoleLimit enforcement via Eloquent `saving()` listener is preferred over DB trigger | Pitfall 10 | DB trigger would be more bulletproof but adds Postgres-specific PL/pgSQL the team has to maintain; Eloquent listener covers all writes through the framework (which is the only documented path) |
| A7 | `navigationGroup = 'Platform'` is a sensible grouping name for Game + GameMatchType in the sidebar | Pitfall 8 | If a different name fits the design system, swap the i18n key — purely a UX label |
| A8 | The 5 starter match types named in CONTEXT.md (Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War) are the operator's intended set; PROJECT.md Open Questions mark this as "to confirm" | Pattern 5 § Seeder + Open Question Q2 | If the set changes, the seeder is a localized edit; UNIQUE `(game_id, key)` lets the operator delete the seeded ones and re-create with different keys |

---

## Open Questions

1. **Officer + Squad Leader: distinct or duplicate?**
   - What we know: HLL canonical roster (verified via gamerant.com + hellletloose.fandom.com) has 14 roles per faction; "Officer" and "Squad Leader" are typically synonymous (Officer = the player serving as squad leader). CONTEXT.md SC-3 lists Commander + Officer + SL as three separate roles totaling 15.
   - What's unclear: Is the league using "Officer" as the high-level coordination role (any squad leader) and "SL" as a SPECIFIC subtype, or are these the same person with different in-game contexts?
   - Recommendation: Seed both per CONTEXT.md verbatim (A1). If feedback during /gsd-verify-work or smoke says "these should be one", the change is a single `firstOrCreate` removal + one capacity row update — trivial.

2. **Are the 5 starter match-type capacities sensible defaults?**
   - What we know: CONTEXT.md names 5 match types; PROJECT.md Open Questions explicitly flag "Confirm initial HLL match-type set" as unresolved.
   - What's unclear: Should Friendly/Tournament/Clan War have pre-seeded capacity matrices or only blanks (admin fills via UI)?
   - Recommendation: Pre-seed Scrim 50v50 + Skirmish 6v6 (mathematically definite distributions); leave Friendly/Tournament/Clan War with no capacity rows (admin can copy from a similar match-type or define fresh). This is honest about what the platform knows vs leaves to operator judgment.

3. **`navigationGroup` label — what's the convention?**
   - What we know: Phase 1 didn't set a navigation group on any resource — they're all flat. Phase 2 also flat.
   - What's unclear: Should Phase 3 introduce groups now or defer until phases 4-8 add more resources?
   - Recommendation: Defer grouping to Phase 7 (CMS) or Phase 9 (Polish); flat is fine for 11 resources. Use `protected static ?int $navigationSort = 10` (Game) + `15` (GameMatchType) to nest them after existing resources but maintain flat layout (Pitfall 8 partial fix).

4. **GameMatchTypeRoleLimit lifecycle when admin deletes a Game.**
   - What we know: `cascadeOnDelete` on `game_id` propagates through GameRole and GameMatchType, and through them to RoleLimits.
   - What's unclear: Is "delete a Game" ever a real admin workflow, or is it "deactivate via is_active = false"?
   - Recommendation: Don't expose a hard-delete action on `GameResource` for now (mirrors Phase 2 `ClanTagResource` no-delete pattern). Admin sets `is_active = false` instead. Document in CONTEXT update post-research if operator agrees.

5. **Are role slugs case-sensitive in the regex CHECK?**
   - What we know: CHECK CONSTRAINT `key ~ '^[a-z0-9_]+$'` enforces lowercase + underscores.
   - What's unclear: Some HLL communities use "AT" or "MG" abbreviations admins might want as keys. Should we allow uppercase?
   - Recommendation: Lowercase-only enforcement matches the Player.slug + Clan.slug conventions established Phase 1/2. Operator can write "AT" in display_name; key stays `anti_tank`. Document this in admin help text.

---

## Sources

### Primary (HIGH confidence)
- `apps/web/composer.json` + composer.lock — all package versions verified 2026-05-13
- `apps/web/app/Models/Clan.php` + `ClanTag.php` + `ClanMembership.php` — Phase 2 model pattern with HasTranslations + LogsActivity (verified codebase 2026-05-13)
- `apps/web/app/Filament/Resources/ClanResource.php` + `ClanTagResource.php` — Phase 2 Filament Resource patterns (verified codebase 2026-05-13)
- `apps/web/app/Filament/Resources/ClanResource/RelationManagers/MembersRelationManager.php` — canonical RelationManager pattern (verified codebase 2026-05-13)
- `apps/web/app/Filament/Resources/ClanResource/Pages/EditClan.php` — mutateFormDataBeforeSave pattern for JSONB null coercion (verified codebase 2026-05-13)
- `apps/web/database/migrations/2026_05_12_*` — Phase 2 migration patterns including raw `DB::statement` usage (verified codebase 2026-05-13)
- `apps/web/database/seeders/ClanTagSeeder.php` + `DiscordGuildSeeder.php` — Phase 2 seeder idempotency pattern (verified codebase 2026-05-13)
- `apps/web/database/seeders/DatabaseSeeder.php` — current seed call order (verified codebase 2026-05-13)
- `apps/web/app/Data/ClanData.php` + `ClanTagData.php` — Phase 2 Data DTO fromModel pattern (verified codebase 2026-05-13)
- `apps/web/app/Concerns/HasUuidPrimaryKey.php` — UUID PK trait (verified codebase 2026-05-13)
- `apps/web/tests/Feature/Models/ClanMembershipModelTest.php` + `ClanModelTest.php` — Pest test patterns (verified codebase 2026-05-13)
- `apps/web/tests/Feature/Admin/ClanResourcesPresentTest.php` + `FilamentResourcesPresentTest.php` — Filament resource presence test pattern (verified codebase 2026-05-13)
- `apps/web/lang/en/admin.php` — existing i18n key structure to extend (verified codebase 2026-05-13)
- Context7 `/websites/filamentphp_3_x` — RelationManager, Select with $livewire->getOwnerRecord(), Repeater (fetched 2026-05-13)
- Context7 `/spatie/laravel-translatable` — getTranslations/setTranslations/setTranslation API (fetched 2026-05-13)
- Context7 `/websites/spatie_be_laravel-data_v4` — TypeScript transformer, Optional types, nested data (fetched 2026-05-13)
- `.planning/phases/02-clans-tags/02-RESEARCH.md` — Phase 2 patterns referenced throughout (verified 2026-05-13)
- `.planning/phases/02-clans-tags/02-PHASE-VERIFICATION.md` — Phase 2 verification template (verified 2026-05-13)
- `.planning/PROJECT.md` — D-001..D-021 locked decisions (verified 2026-05-13)
- `CLAUDE.md` — project conventions (verified 2026-05-13)

### Secondary (MEDIUM confidence)
- gamerant.com / hellletloose.fandom.com / steelseries.com — HLL canonical roster verification (web search 2026-05-13)
- Filament v3 docs https://filamentphp.com/docs/3.x/panels/resources/relation-managers — RelationManager registration + scoping (verified via Context7)

### Tertiary (LOW confidence — flagged as [ASSUMED] in Assumptions Log)
- A2: Scrim 50v50 capacity distribution illustrative, not authoritative HLL competitive rule
- A8: 5 starter match-type set is operator's intended (PROJECT.md Open Questions explicitly unresolved)

---

## Metadata

**Confidence breakdown:**
- Database schema (4 tables + FKs + UNIQUE + CHECK): HIGH — directly modeled on Phase 2 migration patterns
- Filament Resource + RelationManager patterns: HIGH — verified via Phase 2 codebase + Context7
- spatie/laravel-translatable JSONB: HIGH — same pattern as Phase 2 clan.description
- spatie/laravel-data DTO fromModel: HIGH — same pattern as Phase 2 ClanData
- Seeder idempotency (firstOrCreate composite key): HIGH — verified via Phase 2 ClanTagSeeder
- HLL canonical roster (15 roles): MEDIUM — CONTEXT.md SC-3 verbatim; community sources confirm 14 base roles, CONTEXT.md adds Squad Leader as distinct (A1)
- Capacity matrix defaults: LOW — illustrative; operator confirms in /gsd-verify-work or smoke

**Research date:** 2026-05-13
**Valid until:** 2026-08-13 (90 days — Filament v3 LTS-equivalent, spatie packages stable; HLL game rules don't change)
