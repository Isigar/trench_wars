---
phase: 3
phase_name: Games & match types
gathered: 2026-05-13
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 3: Games & match types — Context

<domain>
## Phase Boundary

Provide a fully relational, admin-editable game model so HLL (and any future game) can be configured in Filament without code changes.

**Success Criteria** (from ROADMAP):
1. An admin can create or edit a Game and its GameRoles in Filament with `(game_id, key)` uniqueness enforced.
2. An admin can create a GameMatchType (e.g. "Scrim 50v50") and set `GameMatchTypeRoleLimit` capacities per role through Filament Relation Managers.
3. Seeded HLL data exists out of the box (Commander, Officer, SL, Rifleman, Assault, AR, Medic, Engineer, Support, HMG, AT, Sniper, Spotter, Tank Cmdr, Crewman + starter match types: Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War) and is fully editable post-seed.
4. Adding a new game requires zero code changes — only Filament data entry.

**Depends on**: Phase 2 (Clans & tags — complete)

**Requirements**: REQ-platform-vision

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — discuss phase was skipped per user setting (workflow.skip_discuss=true). Use ROADMAP phase goal, success criteria, codebase conventions (CLAUDE.md), and locked decisions (PROJECT.md D-001..D-021) to guide decisions.

### Locked Decisions Relevant to Phase 3
- **D-007** Generic Game/Role/MatchType tables; HLL seeded as preset (not hardcoded).
- **D-012** Filament covers every domain entity — GameResource, GameRoleResource, GameMatchTypeResource land in Phase 3.
- **D-013** Translatable user-facing strings via spatie/laravel-translatable (game name, role display labels, match type labels).
- **D-021** Container-only commands.

### Conventions
- All composer/artisan/pest/pint run via `make` aliases inside the web container.
- Pest tests, PHPStan L8, Pint must stay green.
- TypeScript types regenerate via spatie/laravel-typescript-transformer.
- Filament resources land in `apps/web/app/Filament/Resources/`.

</decisions>

<code_context>
## Existing Code Insights

Codebase context will be gathered during plan-phase research. Relevant prior work:
- Phase 1: Foundations — Filament v3 admin panel, audit log, Spatie permissions wired (default_guard=web).
- Phase 2: Clans & tags — established the pattern for Filament resources with translatable JSONB columns, Relation Managers, seeders.
- Existing translation surface: `apps/web/lang/en/admin.php` + auth/common/validation.
- Filament resources already shipped: User, Player, Role, Permission, Clan, ClanTag, ClanMembership, ClanInvite, ClanApplication, DiscordGuild.

</code_context>

<specifics>
## Specific Ideas

Schema-level shape (informed by ROADMAP success criteria):

**Tables:**
- `games` — id, key (unique slug like 'hll'), name (translatable JSONB), is_active, timestamps
- `game_roles` — id, game_id (fk), key (e.g. 'commander', 'sl'), display_name (translatable JSONB), sort_order, is_active, timestamps; unique (game_id, key)
- `game_match_types` — id, game_id (fk), key (e.g. 'scrim_50v50'), name (translatable JSONB), description (translatable JSONB), is_active, timestamps; unique (game_id, key)
- `game_match_type_role_limits` — id, game_match_type_id (fk), game_role_id (fk), capacity (int), sort_order, timestamps; unique (game_match_type_id, game_role_id)

**Seeders:**
- HLL Game with 15 roles (Commander, Officer, SL, Rifleman, Assault, AR, Medic, Engineer, Support, HMG, AT, Sniper, Spotter, Tank Cmdr, Crewman).
- 5 starter match types (Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War) with role limits.

**Filament:**
- GameResource (List/Create/Edit) with GameRoles + GameMatchTypes as Relation Managers.
- GameMatchTypeResource (List/Create/Edit) with GameMatchTypeRoleLimits as Relation Manager.
- Audit log via LogsActivity on all 4 models.

</specifics>

<deferred>
## Deferred Ideas

- Match-level integration (slot signups consuming role limits) — Phase 4.
- RCON ingest game-event mapping — Phase 8.
- Multi-game UI surfacing on public pages — Phase 4+ (game model is admin-facing in Phase 3).

</deferred>
