# Phase 2: Clans & tags - Context

**Gathered:** 2026-05-12
**Status:** Ready for planning
**Mode:** Auto-generated (discuss skipped via workflow.skip_discuss)

<domain>
## Phase Boundary

Stand up clans as first-class entities with tags, memberships, invites/applications, and public-facing pages — including privacy-aware player profiles.

**Success criteria (from ROADMAP):**
1. A public visitor can browse a clan directory at `/clans` and open a clan detail page at `/clans/{slug}` without authentication.
2. A public visitor can open any player profile at `/players/{slug}` and only see fields permitted by that player's per-section flags + global `show_to` tier.
3. A clan leader/officer can manage their clan from the "My Clan" page (edit profile, invite/accept members, assign roles) with audit log entries written for every change.
4. The `discord_guild` table holds exactly one row, and each clan stores a `discord_role_id` rather than its own guild id.
5. A player has at most one active `ClanMembership` (enforced by partial unique index), and membership history is preserved when they leave or move clans.

**Requirements:** REQ-tenancy-single-guild, REQ-constraint-single-guild, REQ-tenancy-multi-clan, REQ-goal-public-profiles

**Depends on:** Phase 1 (Foundations) — complete

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — discuss phase was skipped per user setting (workflow.skip_discuss=true).

Use ROADMAP phase goal, success criteria, locked decisions (PROJECT.md D-001..D-021), CLAUDE.md conventions, `.docs/04-domain-model.md`, `.docs/05-database-schema.md`, and the codebase patterns established in Phase 1 to guide every implementation call.

**Hard constraints carried from prior decisions:**
- D-003 single league Discord guild → `discord_guild` table holds exactly one row; clans store `discord_role_id` not their own guild id
- D-009 one active `ClanMembership` per player → enforced by partial unique index (`WHERE left_at IS NULL`)
- D-008 tags m:n on clans → `clan_clan_tag` pivot
- D-012 Filament covers every domain entity → ClanResource, ClanTagResource, ClanMembershipResource land in Filament admin
- D-013 i18n plumbed → all UI strings via `__()` / `t()`; clan/translatable fields use spatie/laravel-translatable JSONB columns
- D-018 per-section + global tier player privacy → /players/{slug} renders only what privacy flags allow

</decisions>

<code_context>
## Existing Code Insights

Codebase context will be gathered during plan-phase research. Relevant prior-phase artefacts:

- `apps/web/database/migrations/` — Phase 1 already shipped `users`, `players`, `player_privacy`, `activity_log`, `permission_*` (spatie) tables. The clan-related migrations land in this phase.
- `apps/web/app/Filament/Resources/` — Phase 1 shipped `UserResource`, `PlayerResource`. The pattern (separate `Pages/`, `RelationManagers/`, `*Resource.php`) is established and should be followed for ClanResource family.
- `apps/web/app/Models/` — Phase 1 shipped `User`, `Player`, `PlayerPrivacy` with `LogsActivity` trait, factories, and `/** @use HasFactory<...> */` PHPDoc tags for PHPStan L8. New Phase 2 models follow the same pattern.
- `apps/web/lang/en/{auth,common,validation,admin}.php` — Phase 1 i18n namespaces. Phase 2 adds `clans.php` and extends `admin.php`/`common.php`.
- `apps/web/resources/js/Pages/` — Inertia Vue page pattern. Phase 2 introduces `Clans/Index.vue`, `Clans/Show.vue`, `Players/Show.vue`, `MyClan/Index.vue` (or equivalents).
- `packages/shared-types/` — generated TS types from `spatie/laravel-data`. Phase 2's new DTOs (`ClanData`, `ClanMembershipData`, etc.) regenerate here.

</code_context>

<specifics>
## Specific Ideas

No specific requirements gathered — discuss phase skipped.

Phase 2 should refer to:
- `.docs/04-domain-model.md` "Clans & taxonomy" section (Clan, ClanTag, ClanMembership, ClanInvite, ClanApplication)
- `.docs/05-database-schema.md` (clans, clan_tags, clan_clan_tag pivot, clan_memberships partial unique index, clan_invites, clan_applications)
- `.docs/16-open-questions.md` — clan tag length cap suggested 2-8 chars
- `.planning/REQUIREMENTS.md` — REQ-tenancy-multi-clan, REQ-tenancy-single-guild, REQ-constraint-single-guild, REQ-goal-public-profiles

</specifics>

<deferred>
## Deferred Ideas

None — discuss phase skipped.

Notifications via Discord bot DM (invite/application) are explicitly deferred to Phase 5 (Discord bot v1) per ROADMAP dependency chain.

</deferred>
