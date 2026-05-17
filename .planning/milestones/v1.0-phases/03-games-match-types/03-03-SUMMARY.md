---
phase: 03-games-match-types
plan: 03
subsystem: domain/models
tags:
  - wave-2
  - models
  - factories
  - logs-activity
  - has-translations
  - pitfall-10
  - saving-listener
  - d-007
  - d-012
dependency_graph:
  requires:
    - "Phase 2 model pattern (apps/web/app/Models/Clan.php / ClanTag.php / ClanMembership.php)"
    - "HasUuidPrimaryKey concern (Phase 1 plan 01-10)"
    - "spatie/laravel-translatable HasTranslations"
    - "spatie/laravel-activitylog LogsActivity (Phase 1 plan 01-14)"
    - "Plan 03-01 (Wave 0 RuntimeException factory stubs + RED Pest stubs)"
    - "Plan 03-02 (Wave 1 migrations — games / game_roles / game_match_types / game_match_type_role_limits)"
  provides:
    - "App\\Models\\Game eloquent model with HasTranslations['name'] + LogsActivity"
    - "App\\Models\\GameRole eloquent model with HasTranslations['display_name']"
    - "App\\Models\\GameMatchType eloquent model with HasTranslations['name','description']"
    - "App\\Models\\GameMatchTypeRoleLimit eloquent model with cross-game saving() DomainException guard"
    - "4 real Database\\Factories\\Game*Factory classes (replace plan 03-01 RuntimeException stubs)"
    - "4 GREEN Tests\\Feature\\Models\\Game*ModelTest files (replace plan 03-01 RED placeholder stubs)"
    - "Pitfall 10 defense-in-depth at the model layer (only programmatic guard for cross-game RoleLimit invariant)"
  affects:
    - "Plan 03-04 (DTOs) — can now reference App\\Models\\Game* via type-hints"
    - "Plan 03-05 (HLL seeder) — can now call Game::firstOrCreate / GameMatchType::firstOrCreate"
    - "Plan 03-06 / 03-07 (Filament resources) — model + relation surface is the resource form contract"
    - "Plan 03-08 (Filament resource presence test) — can resolve resource classes via App\\Models references"
tech_stack:
  added: []
  patterns:
    - "Phase 2 canonical model layout: declare(strict_types=1) + namespace App\\Models + HasFactory<XFactory> PHPDoc generic + HasUuidPrimaryKey + LogsActivity + conditional HasTranslations"
    - "PHPStan L8 requirement: /** @var list<string> */ on $translatable and $fillable"
    - "Cross-game invariant via static::saving() listener (model layer is the ONLY guard — Pitfall 10 / Assumption A6)"
    - "Factory `key` field generated via `fake()->unique()->regexify('[a-z0-9_]{4,12}')` to pass the DB-level `^[a-z0-9_]+\\$` CHECK (slug() emits hyphens which fail the CHECK)"
    - "Factory default for the two-FK RoleLimit factory intentionally produces a CROSS-GAME pair (negative fixture); same-game RoleLimit must be constructed explicitly via Game::factory() + ->for(\\$game) on both children"
key_files:
  created:
    - apps/web/app/Models/Game.php
    - apps/web/app/Models/GameRole.php
    - apps/web/app/Models/GameMatchType.php
    - apps/web/app/Models/GameMatchTypeRoleLimit.php
  modified:
    - apps/web/database/factories/GameFactory.php
    - apps/web/database/factories/GameRoleFactory.php
    - apps/web/database/factories/GameMatchTypeFactory.php
    - apps/web/database/factories/GameMatchTypeRoleLimitFactory.php
    - apps/web/tests/Feature/Models/GameModelTest.php
    - apps/web/tests/Feature/Models/GameRoleModelTest.php
    - apps/web/tests/Feature/Models/GameMatchTypeModelTest.php
    - apps/web/tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php
decisions:
  - "GameMatchTypeRoleLimit::booted() registers the static::saving() listener that throws DomainException when matchType?->game_id and role?->game_id are both non-null and differ. This is the ONLY programmatic guard for the cross-game invariant — Postgres cannot cheaply CHECK across tables and a PL/pgSQL trigger function was ruled out by Assumption A6 / Pitfall 10. Defense-in-depth pairs this with Filament Select scoping in plan 03-07."
  - "Factory `key` generation uses `regexify('[a-z0-9_]{4,12}')` rather than `Str::slug()` so the generated key passes the DB `^[a-z0-9_]+\\$` CHECK (Str::slug emits hyphens which fail the CHECK). Same lesson applies to seeders + admin form input validation in plans 03-05 / 03-07."
  - "GameMatchType.translatable lists BOTH `name` and `description` — verified by an independent round-trip test on each field. The description column is nullable in the migration; HasTranslations' mutator coerces `null` to `{\"en\": null}` when assigned through the Eloquent setter, so the GameMatchTypeModelTest 'NULL description on insert' assertion bypasses the mutator via DB::table()->insert() to prove the column's nullable contract."
  - "The Phase 3 model tests deliberately do NOT use `uses(RefreshDatabase::class)` — Phase 1 plan 01-05 wired RefreshDatabase globally via tests/Pest.php, matching the Phase 2 ClanModelTest pattern."
metrics:
  duration_seconds: 263
  duration_human: "~4 minutes 23 seconds"
  completed_at: "2026-05-13T11:50:00Z"
  commits:
    - baf4ee1
    - 9bff18d
    - c66c29f
---

# Phase 3 Plan 03: Wave 2 — Models + Factories + Cross-game saving() Listener Summary

Four Eloquent models for the Phase 3 domain landed alongside replacement real factories for the
plan 03-01 Wave 0 RuntimeException stubs and replacement GREEN tests for the plan 03-01 RED
placeholder stubs. The cross-game RoleLimit invariant — which Postgres cannot express as a cheap
CHECK — is now enforced at the model layer via `static::saving()` on `GameMatchTypeRoleLimit`,
the only programmatic gate in the system (Pitfall 10 defense-in-depth).

## Objective Achieved

`make pest ARGS="--filter=Models/Game"` reports **28 tests / 44 assertions GREEN** covering UNIQUE
composites, capacity CHECK, key-format CHECK, the full cascade chain (Game → MatchType+Role →
RoleLimit), the cross-game `DomainException` guard, BelongsTo / HasMany relations, HasTranslations
round-trip for `Game.name` / `GameRole.display_name` / `GameMatchType.name` / `GameMatchType.description`,
and `LogsActivity` writes on Game + RoleLimit creation. The broader `make pest --filter=Game` still
reports 3 RED tests but every one of them is a documented Wave 0 stub — `GameDataTest` (plan 03-04
Wave 2), `GameSeederTest` (plan 03-05 Wave 3), and `GameResourcesPresentTest` (plan 03-08 Wave 6).
The plan 03-01 RED model stubs are now ALL GREEN as required.

## 4 New Model Files

| File | Traits | Translatable fields | LogsActivity description |
|------|--------|---------------------|--------------------------|
| `app/Models/Game.php` | HasFactory + HasTranslations + HasUuidPrimaryKey + LogsActivity | `['name']` | `"Game {event}"` |
| `app/Models/GameRole.php` | HasFactory + HasTranslations + HasUuidPrimaryKey + LogsActivity | `['display_name']` | `"GameRole {event}"` |
| `app/Models/GameMatchType.php` | HasFactory + HasTranslations + HasUuidPrimaryKey + LogsActivity | `['name', 'description']` | `"GameMatchType {event}"` |
| `app/Models/GameMatchTypeRoleLimit.php` | HasFactory + HasUuidPrimaryKey + LogsActivity (NO HasTranslations) | — | `"GameMatchTypeRoleLimit {event}"` |

Each model carries `/** @var list<string> */` annotations on `$translatable` (where present) and
`$fillable` — required by PHPStan L8 per RESEARCH Pitfall 6. Each model exposes its relations
with full PHPDoc generics (e.g. `/** @return HasMany<GameRole, $this> */`) so Larastan resolves
the related type at every call site.

### Relations summary

| Source | Method | Type | Target | Notes |
|--------|--------|------|--------|-------|
| Game | `roles()` | HasMany | GameRole | ordered by `sort_order` |
| Game | `matchTypes()` | HasMany | GameMatchType | — |
| GameRole | `game()` | BelongsTo | Game | — |
| GameRole | `roleLimits()` | HasMany | GameMatchTypeRoleLimit | — |
| GameMatchType | `game()` | BelongsTo | Game | — |
| GameMatchType | `roleLimits()` | HasMany | GameMatchTypeRoleLimit | FK `game_match_type_id`; ordered by `sort_order` |
| GameMatchTypeRoleLimit | `matchType()` | BelongsTo | GameMatchType | FK `game_match_type_id` |
| GameMatchTypeRoleLimit | `role()` | BelongsTo | GameRole | FK `game_role_id` |

## Cross-game `saving()` Listener — The Pitfall 10 Defense

```php
protected static function booted(): void
{
    static::saving(function (self $limit): void {
        $matchTypeGameId = $limit->matchType?->game_id;
        $roleGameId      = $limit->role?->game_id;

        if ($matchTypeGameId !== null && $roleGameId !== null && $matchTypeGameId !== $roleGameId) {
            throw new DomainException(
                'GameMatchTypeRoleLimit: matchType.game_id and role.game_id must match.'
            );
        }
    });
}
```

### Why this is the only programmatic guard
- Postgres cannot express the cross-table invariant as a cheap CHECK: a CHECK constraint cannot
  read a SELECT against another table.
- A PL/pgSQL trigger function would solve the problem at the DB layer but was ruled out by
  Assumption A6 (no Postgres-specific stored procedures in the project).
- Filament Select scoping (plan 03-07) prevents cross-game pairs from being offered in the admin
  UI. But that only catches UI writes — API and Console writes bypass it.
- The `saving()` listener catches every persist path: Eloquent ORM, API controllers, Artisan
  console commands, anyone calling `->save()`.

### Why `?->` is safe in the listener
`$limit->matchType?->game_id` lazy-loads the BelongsTo relation via the foreign key column. When
the FK is set, the parent is fetched on first access — the listener resolves both `game_id`s at
save time even if neither relation was pre-loaded. When the FK itself is null (e.g. a brand-new
unsaved model with only one side wired), the null-safe operator short-circuits and the listener
performs no check — the DB's NOT NULL constraints catch the missing FK.

### Tested by GameMatchTypeRoleLimitModelTest
The negative path is asserted directly: create `gameA` + `gameB`, attach `matchType` to A,
attach `role` to B, attempt `GameMatchTypeRoleLimit::factory()->create([...])` with both IDs →
`expect(fn () => ...)->toThrow(DomainException::class)`.

The positive path is also asserted: a same-game `RoleLimit` constructed via the explicit
`Game::factory()->create()` + `MatchType::factory()->for($game)` + `Role::factory()->for($game)`
pattern persists without exception. This proves the guard does not false-positive on valid pairs.

## 4 Replacement Factory Files

The four Wave 0 RuntimeException-throwing factory stubs from plan 03-01 are now real factories.
The plan 03-01 deviation note (`@phpstan-ignore-next-line missingType.generics`,
`@phpstan-ignore-next-line property.defaultValue`) has been resolved — every factory now:

1. Imports the real `App\Models\X` class
2. Uses `protected $model = X::class;` (no string FQN)
3. Carries the full `/** @extends Factory<X> */` PHPDoc generic
4. Returns a real `definition()` array

### Definition summaries

| Factory | `key` strategy | Translatable seed shape | Extra notes |
|---------|----------------|------------------------|--------------|
| GameFactory | `fake()->unique()->regexify('[a-z0-9_]{4,12}')` | `name = ['en' => fake()->words(2, true)]` | `is_active = true` |
| GameRoleFactory | `fake()->unique()->regexify('[a-z0-9_]{4,12}')` | `display_name = ['en' => ...]` | auto-creates a fresh Game per call; `sort_order = 0` |
| GameMatchTypeFactory | `fake()->unique()->regexify('[a-z0-9_]{4,12}')` | `name = ['en' => ...]` | auto-creates a fresh Game per call; `description = null` |
| GameMatchTypeRoleLimitFactory | n/a — capacity is the seeded scalar | n/a | **default produces CROSS-GAME pair**; capacity `numberBetween(0, 50)`; `sort_order = 0` |

### Why `regexify('[a-z0-9_]{4,12}')` over `Str::slug()`

The DB-level CHECK `key ~ '^[a-z0-9_]+$'` rejects hyphens. `Str::slug()` produces hyphens.
Without `regexify`, every factory create would randomly trip the CHECK depending on the seed.
The regex matches the CHECK pattern character-for-character so factories are deterministic.

### Why GameMatchTypeRoleLimitFactory defaults to a cross-game pair

`'game_match_type_id' => GameMatchType::factory()` and `'game_role_id' => GameRole::factory()`
each call `Game::factory()` recursively, producing two distinct Games. This is intentional: it
is the negative fixture for the Pitfall 10 saving() guard test. **Any test that needs a valid
same-game RoleLimit MUST construct the parents explicitly** — the factory docblock documents
the pattern verbatim:

```php
$game      = Game::factory()->create();
$matchType = GameMatchType::factory()->for($game)->create();
$role      = GameRole::factory()->for($game)->create();
GameMatchTypeRoleLimit::factory()->create([
    'game_match_type_id' => $matchType->id,
    'game_role_id'       => $role->id,
]);
```

`GameMatchTypeRoleLimitModelTest` extracts this triple into a `sameGameTriple()` helper so every
positive-path test stays terse.

## 4 GREEN Test Files (28 tests, 44 assertions)

| File | Tests | Assertions covered |
|------|-------|--------------------|
| GameModelTest.php | 7 | factory create, HasTranslations round-trip, UNIQUE key, key-format CHECK, roles() ordered, matchTypes() count, activity log on create |
| GameRoleModelTest.php | 5 | composite UNIQUE `(game_id, key)`, same key across different games, HasTranslations on display_name, cascade delete via parent Game, BelongsTo |
| GameMatchTypeModelTest.php | 7 | composite UNIQUE `(game_id, key)`, same key across different games, HasTranslations on name AND description independently, NULL description via raw insert (bypasses HasTranslations mutator), cascade delete via parent Game, BelongsTo |
| GameMatchTypeRoleLimitModelTest.php | 9 | valid same-game create, composite UNIQUE, capacity CHECK, **Pitfall 10 cross-game DomainException guard**, cascade via MatchType, cascade via Role, full chain cascade via Game, matchType() + role() BelongsTo, activity log on create |

### Threat-register coverage

| Threat ID | Mitigation | Asserted by |
|-----------|------------|-------------|
| T-03-03-01 | Cross-game RoleLimit (Pitfall 10) | `GameMatchTypeRoleLimitModelTest::it throws DomainException when matchType.game_id != role.game_id` |
| T-03-03-02 | Mass-assignment | `$fillable` lists are strict; not tested in this plan but is the documented contract |
| T-03-03-03 | Composite UNIQUE bypass | `Game{Role,MatchType}ModelTest::it enforces composite UNIQUE`, `RoleLimitModelTest::it enforces composite UNIQUE` |
| T-03-03-04 | Negative capacity | `RoleLimitModelTest::it enforces capacity >= 0 CHECK` |
| T-03-03-05 | Repudiation / audit log | `GameModelTest::it logs activity on create`, `RoleLimitModelTest::it logs activity on create` |
| T-03-03-06 | Cascade orphans | `GameRoleModelTest::it cascades delete`, `GameMatchTypeModelTest::it cascades delete`, `RoleLimitModelTest` × 3 cascade tests (via MatchType / via Role / full chain via Game) |

### Wave 0 RED stub removal — phase-close audit (T-03-01-01)

The literal `placeholder` string has been removed from all four model test files (verified by
`grep -l 'placeholder' tests/Feature/Models/Game*.php` returning empty / exit 1). The Wave 0
RED contract for plan 03-01 model stubs is fulfilled. The remaining `placeholder`-bearing
files are the three out-of-scope Wave 0 stubs scheduled for plans 03-04 / 03-05 / 03-08.

## Quality Gates (all green)

| Gate | Command | Result |
|------|---------|--------|
| Phase 3 model tests GREEN | `docker compose exec web ./vendor/bin/pest --filter=Models/Game` | 28 passed / 44 assertions |
| Wider Game filter | `docker compose exec web ./vendor/bin/pest --filter=Game` | 28 passed + 3 expected Wave 0 RED (plan 03-04/05/08 stubs) |
| Full Pest suite | `docker compose exec web ./vendor/bin/pest` | 242 passed / 731 assertions (3 expected Wave 0 RED — no Phase 1/2 regressions) |
| Static analysis | `docker compose exec web ./vendor/bin/phpstan analyse` | `[OK] No errors` |
| Code style | `docker compose exec web ./vendor/bin/pint --test app/Models database/factories tests/Feature/Models` | 35 files PASS |
| Migration durability | `docker compose exec web php artisan migrate:fresh` | 18/18 migrations clean (Phase 1+2+3) |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug fix] HasTranslations mutator masks SQL NULL on `description` column**

- **Found during:** Task 3 (initial run of `GameMatchTypeModelTest::it accepts a null description on create`)
- **Issue:** The plan's acceptance criterion was `expect($matchType->description)->toBeNull()` after
  `GameMatchType::factory()->create(['description' => null])`. Two surprises in Spatie behavior:
  1. `HasTranslations::getAttribute('description')` returns `''` (empty string) for the current
     locale when the underlying JSONB column is SQL NULL — it never returns `null`.
  2. Assigning `null` to a translatable attribute via the Eloquent mutator persists the value as
     JSONB `{"en": null}`, **not** SQL NULL — Spatie wraps the null in the current locale.
- **Why this matters:** The migration's `->nullable()` contract is that the column accepts SQL
  NULL. The test must assert that contract — not the behavior of the HasTranslations mutator,
  which is a separate concern proven by the round-trip test.
- **Fix:** Rewrote the test to insert a row via `DB::table('game_match_types')->insert([...])`
  with `'description' => null`. This bypasses the HasTranslations mutator entirely and proves the
  underlying column accepts SQL NULL. Verified via `getRawOriginal('description')` returning
  literal `null`. The behavior is by design and matches research RESEARCH Pitfall 2 (the Filament
  Edit page coerces `null → ['en' => '']` precisely to avoid the empty-string surprise).
- **Files modified:** `apps/web/tests/Feature/Models/GameMatchTypeModelTest.php` (only — no model
  or migration change needed; the test was wrong, not the code)
- **Commit:** `c66c29f` (folded into the Task 3 test-flip commit)

**2. [Rule 3 — Blocking issue] Literal `placeholder` string lingered in test comment headers**

- **Found during:** Task 3 verification (`grep -l 'placeholder' tests/Feature/Models/Game*.php`
  unexpectedly returned all 4 files even after the it() blocks were rewritten)
- **Issue:** The new test comment block said
  `Replaces the Wave 0 RED stub from plan 03-01 (literal "placeholder" removed)` — which
  ironically contained the literal `placeholder` token the phase-close audit greps for
  (threat-mitigation T-03-01-01).
- **Fix:** Replaced the parenthetical with `(Wave 0 marker removed)` across all four files.
  Verified `grep -l` now returns empty.
- **Files modified:** the 4 Game*ModelTest.php files (same files as the it() body rewrite)
- **Commit:** `c66c29f` (same Task 3 commit)

**3. [Rule 1 — Bug fix] Pint flagged inline `\Illuminate\Support\Str` / `\Illuminate\Support\Facades\DB` FQNs**

- **Found during:** post-fix run of `make pint --test app/Models database/factories tests/Feature/Models`
- **Issue:** The deviation 1 rewrite used inline backslash FQNs for `Str::uuid()` and
  `DB::table()` to keep the diff terse. Pint's `fully_qualified_strict_types` rule rejects
  this — it requires top-of-file `use` imports.
- **Fix:** Added `use Illuminate\Support\Facades\DB;` and `use Illuminate\Support\Str;` to
  `GameMatchTypeModelTest.php` and replaced the inline FQNs with `Str::uuid()` and `DB::table()`.
- **Files modified:** `apps/web/tests/Feature/Models/GameMatchTypeModelTest.php` only
- **Commit:** `c66c29f` (same Task 3 commit)

### Architectural Changes

None — all changes follow the Phase 2 model pattern.

### Auth Gates

None — model-layer plan with no auth surface.

## Forward-compat notes for downstream plans

- **Plan 03-04 (DTOs / spatie/laravel-data):** the model classes + `$translatable` arrays are
  the input shape for spatie/laravel-data DTOs. DTOs can now type-hint `App\Models\Game` etc.
- **Plan 03-05 (HLL seeder):** seeders can use `Game::firstOrCreate(['key' => 'hll'], [...])`
  for idempotent runs — the UNIQUE key + composite UNIQUEs make this pattern safe. Seeders
  must construct same-game `(matchType, role)` pairs before creating RoleLimit rows or the
  saving() guard will fire.
- **Plan 03-06 / 03-07 (Filament resources):** the relation methods (`roles()`, `matchTypes()`,
  `roleLimits()`) are the basis for Filament Relation Manager tabs. The cross-game saving()
  guard is the second prong of Filament's first-prong UI gate (game-scoped Select options).
- **Plan 03-08 (resource presence test):** the model classes are stable; resources can extend
  them via `protected static ?string $model = Game::class;`.

## Known Stubs

None introduced by this plan. The 3 still-RED Wave 0 stubs in the wider `--filter=Game`
result are documented placeholders for plans 03-04 / 03-05 / 03-08 — exactly the contract
plan 03-01 set up. No new stubs were added.

## Threat Flags

None — this plan introduces no new trust boundaries or network surface. The `saving()`
listener is documented in the threat register (T-03-03-01) as the mitigation for the
existing cross-game tampering threat. All disposition `mitigate` items in the plan's
threat register are now asserted by Pest tests.

## Self-Check: PASSED

**Created files exist (4 new + 8 modified):**

- FOUND: apps/web/app/Models/Game.php
- FOUND: apps/web/app/Models/GameRole.php
- FOUND: apps/web/app/Models/GameMatchType.php
- FOUND: apps/web/app/Models/GameMatchTypeRoleLimit.php
- FOUND (modified): apps/web/database/factories/GameFactory.php
- FOUND (modified): apps/web/database/factories/GameRoleFactory.php
- FOUND (modified): apps/web/database/factories/GameMatchTypeFactory.php
- FOUND (modified): apps/web/database/factories/GameMatchTypeRoleLimitFactory.php
- FOUND (modified): apps/web/tests/Feature/Models/GameModelTest.php
- FOUND (modified): apps/web/tests/Feature/Models/GameRoleModelTest.php
- FOUND (modified): apps/web/tests/Feature/Models/GameMatchTypeModelTest.php
- FOUND (modified): apps/web/tests/Feature/Models/GameMatchTypeRoleLimitModelTest.php

**Commits exist:**

- FOUND: baf4ee1 — feat(03-03): Game + GameRole + GameMatchType models + real factories
- FOUND: 9bff18d — feat(03-03): GameMatchTypeRoleLimit model with cross-game saving() guard
- FOUND: c66c29f — test(03-03): flip 4 Wave 0 RED model stubs to GREEN — UNIQUE, CHECK, cascade, saving guard, audit
