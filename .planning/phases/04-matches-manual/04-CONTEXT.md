---
phase: 4
phase_name: Matches (manual)
gathered: 2026-05-13
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 4: Matches (manual) — Context

<domain>
## Phase Boundary

Make scheduling and recording matches the primary platform workflow — without RCON automation yet — covering creation, slot signups, capacity enforcement, tag-restricted access, and manual results.

**Success Criteria** (from ROADMAP):
1. A clan officer/leader can create a match by choosing a game match type; slots are materialised from `GameMatchTypeRoleLimit` and signups open automatically.
2. A logged-in player can sign up to a specific role slot, and the live count of confirmed signups can never exceed slot capacity (enforced by DB transaction with row lock).
3. A public visitor can view the match calendar at `/matches` and any match detail page at `/matches/{id}` with slot availability rendered.
4. An organiser/admin can enter or override a match result (winner, scores, MVPs) in Filament and the change is audited.
5. Tag-restricted matches reject signups from clans whose tags are not in `match_access_rules`, and creating a public match auto-creates a kept-in-sync `Event` row.

**Depends on**: Phase 3 (Games & match types — complete)

**Requirements**: REQ-goal-match-workflows

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
Implementation choices at Claude's discretion via workflow.skip_discuss. Use ROADMAP, codebase conventions, locked decisions (D-001..D-021), and Phase 1-3 patterns to guide.

### Locked Decisions Relevant to Phase 4
- **D-010** Match signups by role slot; capacity row-locked. (Hard requirement for SC-2 — `SELECT ... FOR UPDATE` in service transaction or PostgreSQL row lock via `lockForUpdate()`.)
- **D-011** Tournaments first-class round 1 (4 formats) — Phase 6 will use Phase 4's match primitives.
- **D-009** One active ClanMembership per player (Phase 2 ✓).
- **D-012** Filament covers every domain entity — MatchResource lands in this phase.
- **D-013** Translatable user-facing strings (match name/description JSONB).
- **D-018** Per-section + global tier player privacy — signup display must respect (consume Phase 2's PlayerPrivacyGate).
- **D-021** Container-only commands.

### Conventions Inherited
- Pest 4 (not PHPUnit), Pint preset Laravel, PHPStan L8.
- Filament resources in `apps/web/app/Filament/Resources/`.
- Public Vue pages in `apps/web/resources/js/pages/`.
- spatie/laravel-data DTOs + custom `trenchwars:typescript-generate` command (D-020).
- Activity log via `LogsActivity` trait.
- i18n keys live in `apps/web/lang/en/{matches,public,admin}.php` — extend admin.php with `admin.matches.*`.

</decisions>

<code_context>
## Existing Code Insights

To be gathered by gsd-phase-researcher. Known relevant prior work:
- Phase 3 ships Game/GameRole/GameMatchType/GameMatchTypeRoleLimit (slot capacity primitives).
- Phase 2 ships Clan/ClanTag/ClanMembership + PlayerPrivacyGate + PublicLayout/PublicPlayerData.
- Phase 1 ships Filament v3 panel + spatie/permission + activity_log.
- HLL seeded preset: Scrim 50v50 (15 RoleLimit rows / 50 slots), Skirmish 6v6 (5 rows / 6 slots) — direct fixture for Phase 4.

</code_context>

<specifics>
## Specific Ideas

Schema shape (informed by ROADMAP SC):

**Tables (likely):**
- `matches` — id, game_match_type_id (fk to game_match_types), title (translatable JSONB), description (translatable JSONB), scheduled_at, organiser_id (clan_membership_id or user_id), server_address (nullable), status (enum: draft/open/locked/played/cancelled), is_public (bool), created_at, updated_at
- `match_slots` — id, match_id (fk), game_role_id (fk to game_roles), slot_index (int — position within role's capacity), occupant_id (nullable fk to users/clan_memberships), confirmed_at, timestamps; unique (match_id, game_role_id, slot_index)
- `match_access_rules` — id, match_id, clan_tag_id (fk), allow (bool), timestamps; or composite policy table (allow_tags + deny_tags)
- `match_results` — id, match_id (1:1), winner_clan_id (nullable), allies_score (nullable), axis_score (nullable), notes (nullable), recorded_by (user_id), recorded_at
- `match_mvps` — id, match_result_id, player_id, category (kills/objective/etc), value, timestamps
- `events` — id, eventable_type, eventable_id (polymorphic — Match for Phase 4; Tournament for Phase 6), starts_at, ends_at, title (translatable JSONB), is_public, timestamps

**Routes:**
- Public: `GET /matches` (calendar), `GET /matches/{slug-or-id}` (detail)
- Auth: `POST /matches/{id}/signups`, `DELETE /matches/{id}/signups/{slot}`, `POST /matches` (officer create)

**Services:**
- `MatchSlotMaterialiserService` — given a GameMatchType, creates one MatchSlot per (GameRole, capacity-index).
- `MatchSignupService` — transactional, lockForUpdate on the slot, enforces capacity + tag-access rules + active-ClanMembership invariant.
- `MatchResultService` — enters/overrides result; writes activity_log with causer.

**Filament:**
- MatchResource (List/Create/View/Edit) + MatchSlotsRelationManager + MatchResultRelationManager.
- Audit log integration via LogsActivity on Match, MatchSlot, MatchResult.

**Public Vue pages (UI-SPEC candidate):**
- `pages/Matches/Index.vue` — calendar/list view with date filters, tag filter, status filter.
- `pages/Matches/Show.vue` — match detail with role-grouped slot grid, signup buttons, result display when status=played.
- Component patterns established Phase 2 (UserMenu, PublicLayout) reused.

</specifics>

<deferred>
## Deferred Ideas

- RCON live capture (Phase 8 — D-019).
- Discord slash commands `/match list|info|signup|leave` (Phase 5).
- Tournament bracket integration (Phase 6 — Match is the leaf primitive; bracket nodes own match references).
- Event calendar aggregation across Tournament + Match (Phase 7 CMS).
- Match result MVP statistics depth (initial Phase 4 keeps it simple: winner + scores + free-text notes; advanced per-player stats arrive Phase 8 from CRCON).

</deferred>
