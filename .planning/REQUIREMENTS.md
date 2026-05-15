# Requirements: Trenchwars

**Defined:** 2026-05-03
**Core Value:** Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically.

> All requirements derived from `.planning/intel/requirements.md` (PRD source: `.docs/01-overview.md`). REQ-IDs preserve the slug-based naming used in intel synthesis. Requirements are grouped here into project-conventional categories for readability; the original IDs are authoritative and are what the traceability table maps.

## v1 Requirements

Requirements for round-1 release. Each maps to exactly one phase in `ROADMAP.md`.

### Platform & Tenancy

- [x] **REQ-platform-vision**: Game-agnostic data model (Game / GameRole / GameMatchType / GameMatchTypeRoleLimit) is implemented and HLL is a seeded preset, not hard-coded. Additional games can be added without code changes. (D-007)
- [x] **REQ-tenancy-multi-clan**: Multi-clan league platform — one deployment hosts many clans with public clan directory, public player profiles, public match calendar, and public bracket viewer all reachable without authentication. (D-006)
- [x] **REQ-tenancy-single-guild**: One shared league Discord guild — `discord_guild` table holds exactly one row, each clan binds to a Discord role id (not a separate guild), no per-clan Discord servers in the model. (D-003)

### Match Workflows

- [x] **REQ-goal-match-workflows**: A match can be created, slot-templated, signed up to, and scheduled without leaving the platform's structured surfaces. Replaces ad-hoc Discord scheduling. (D-010)

### RCON & Match History

- [x] **REQ-goal-rcon-history**: When a match is played on a registered match server, MatchResult and per-player MatchPlayerStat rows are populated automatically from CRCON events. (D-005, D-019)
- [x] **REQ-constraint-league-owns-servers**: League-owned HLL match servers are league-managed entries; CRCON deployment alongside each server is in scope; no per-clan-managed servers in round 1.

### Profiles & Privacy

- [x] **REQ-goal-public-profiles**: Player profiles render under user-controlled per-section flags (`show_real_name`, `show_discord_tag`, `show_clan_history`, `show_match_history`, `show_stats`) plus global tier (`public|community|clan|private`). (D-018)

### CMS & Editorial

- [x] **REQ-goal-cms**: Articles, Categories, and Events are first-class entities, editorially managed in Filament, and surfaced on public pages.

### Discord UX

- [x] **REQ-goal-discord-ux**: Slash commands `/clan`, `/match`, `/profile`, `/me` exist; RSVP buttons and slot-picker modals work; result announcements post to the host clan's announce channel.

### Internationalisation

- [x] **REQ-constraint-en-launch-i18n-ready**: All UI strings via `__()` / `t()`; translatable models use jsonb keyed by locale; adding a locale is config + content task. English at launch; multi-language possible without refactor. (D-013)

### Hosting & Constraints

- [x] **REQ-constraint-railway-deploy**: Five-service Railway topology (web, worker, bot, rcon-worker, db, redis); secrets in Railway env groups; `.env.example` documents shape. Not optimised for self-hosting elsewhere round 1. (D-014)
- [x] **REQ-constraint-single-guild**: Single Discord guild for the entire league. (Reinforces REQ-tenancy-single-guild and D-003.)

### Round-1 Acceptance Gates

- [x] **REQ-success-end-to-end-scrim**: End-to-end flow works without manual data entry on the happy path: Discord OAuth → clan create → roster build → scrim schedule → Discord signup → CRCON-played → auto-recorded MatchResult + MatchPlayerStat.
- [x] **REQ-success-tournament-end-to-end**: An 8-clan single-elim tournament can be created, seeded, brackets generated, matches materialised, played, results captured, advancements computed — without admin patching.
- [x] **REQ-success-public-browse**: All public surfaces (clans, players, calendar, bracket views, articles) are accessible without auth; SSR enabled in production for first paint on public pages.

## v2 Requirements

Deferred to a future release. Tracked but not in the current roadmap.

### Notifications & Search

- **NOTF-01**: User-configurable notification preferences (web bell + Discord DM rules) — suggested by M9 polish list and Open Questions.
- **NOTF-02**: Email transactional mail provider — currently no email need beyond OAuth.
- **SRCH-01**: Meilisearch-powered full-text search — round-1 uses Postgres `to_tsvector` only (CON-cms-search).

### Tournaments enhancements

- **TOUR-V2-01**: WebSocket live tournament updates — round-1 polls every 30s (CON-tournament-public-view).

### Multi-locale content

- **I18N-V2-01**: Ship CS / SK / PL / DE / RU / FR locale packs — round 1 is EN-only by D-013 but model is locale-agnostic from day one.

### Discord enhancements

- **DISC-V2-01**: Bot-managed per-clan voice channels — Open Question, deferred.
- **DISC-V2-02**: Discord-thread links on articles — discussion lives in Discord (CON-cms-comments-out-of-scope).

### Operational

- **OPS-V2-01**: GDPR right-to-erasure flow (anonymise vs delete) — Open Question.
- **OPS-V2-02**: Activity log partitioning + 12-month archive — current default indefinite, revisit at six months (CON-audit-retention).
- **OPS-V2-03**: Monitoring stack (Sentry / Logtail) beyond Railway logs — Open Question.

## Out of Scope

Explicitly excluded for round 1. Documented to prevent scope creep. Sourced from `REQ-non-goals-round-1` (which by its own acceptance "must be placed outside M1–M9") and from project constraints/ADRs.

| Feature | Reason |
|---------|--------|
| Native mobile apps | REQ-non-goals-round-1; web is mobile-first responsive (CON-frontend-goals) |
| Real-time spectator overlays | REQ-non-goals-round-1; high complexity, low round-1 value |
| Anti-cheat / VAC integrations | REQ-non-goals-round-1; CRCON-based stats only |
| Federation across multiple league installs | REQ-non-goals-round-1; single-install model intentionally |
| Per-clan custom domains | REQ-non-goals-round-1; one league domain |
| Per-clan theming | REQ-non-goals-round-1; single league brand only |
| Email/password authentication | D-002; community lives on Discord |
| Real-time chat / on-site comments / reactions | CON-cms-comments-out-of-scope; Discord covers it |
| Video uploads | Storage/bandwidth out of round-1 scope (CON-cms-media-library) |
| Meilisearch | Round-1 uses Postgres FTS (CON-cms-search) |
| WebSocket live tournament updates | Round-1 polls every 30s (CON-tournament-public-view) |
| Bot-managed per-clan voice channels | Out of round-1 (Open Questions) |
| Self-hosting outside Railway | REQ-constraint-railway-deploy |
| Laravel starter kits (Breeze/Jetstream) | D-017; ship session+email auth we don't need |

## Traceability

Mapping of v1 requirements to roadmap phases. Updated by `/gsd-roadmap`/`/gsd-plan-phase` workflows.

| Requirement | Phase | Status |
|-------------|-------|--------|
| REQ-constraint-railway-deploy | Phase 1 | Complete |
| REQ-constraint-en-launch-i18n-ready | Phase 1 | Complete |
| REQ-tenancy-single-guild | Phase 2 | Complete |
| REQ-constraint-single-guild | Phase 2 | Complete |
| REQ-tenancy-multi-clan | Phase 2 | Complete |
| REQ-goal-public-profiles | Phase 2 | Complete |
| REQ-platform-vision | Phase 3 | Complete |
| REQ-goal-match-workflows | Phase 4 | Complete |
| REQ-goal-discord-ux | Phase 5 | Complete |
| REQ-success-tournament-end-to-end | Phase 6 | Complete |
| REQ-goal-cms | Phase 7 | Complete |
| REQ-success-public-browse | Phase 7 | Complete |
| REQ-goal-rcon-history | Phase 8 | Complete |
| REQ-constraint-league-owns-servers | Phase 8 | Complete |
| REQ-success-end-to-end-scrim | Phase 8 | Complete |
| REQ-non-goals-round-1 | (Out of Scope) | N/A — acceptance is "none of these ship in round 1"; documented in Out of Scope table above |

**Coverage:**
- v1 requirements: 16 total (15 mappable + 1 structural exclusion)
- Mapped to phases: 15 of 15 mappable
- Unmapped (mappable): 0
- Structural exclusions correctly placed Out of Scope: 1 (REQ-non-goals-round-1)

Coverage: 15/15 mappable requirements mapped — complete.

---
*Requirements defined: 2026-05-03*
*Last updated: 2026-05-13 — Phase 4 close: REQ-goal-match-workflows flipped to Complete*
*Last updated: 2026-05-13 — Phase 5 close: REQ-goal-discord-ux confirmed Complete (was flipped during plan 05-12; 05-PHASE-VERIFICATION.md re-affirms via SC-1..SC-5 traceability)*
*Last updated: 2026-05-14 — Phase 6 close: REQ-success-tournament-end-to-end flipped to Complete (06-PHASE-VERIFICATION.md maps SC-1..SC-5 + 12 Pitfalls + 5 Open Questions LOCKED inline)*
*Last updated: 2026-05-14 — Phase 7 close: REQ-goal-cms + REQ-success-public-browse confirmed Complete (07-PHASE-VERIFICATION.md maps SC-1..SC-5 + 12 Pitfalls + 8 Open Questions LOCKED inline; ~50 D-07-* canonical bindings; PENDING_MANUAL_SMOKE 4-item operator walkthrough A-D)*
*Last updated: 2026-05-14 — Phase 8 close: REQ-goal-rcon-history + REQ-constraint-league-owns-servers + REQ-success-end-to-end-scrim flipped to Complete (08-PHASE-VERIFICATION.md maps SC-1..SC-5 + 12 Pitfalls + 5 Open Questions LOCKED inline; ~60 D-08-* canonical bindings; PENDING_MANUAL_SMOKE 4-item operator walkthrough A-D: live CRCON probe / two-clan SC-5 happy path / mid-match log gap / HMAC key rotation)*
*Last updated: 2026-05-15 — Phase 9 close: round-1 polish complete; no v1 REQ flips (Phase 9 is the buffer milestone — consumed polish backlog only; all 15 mappable v1 requirements were already flipped to Complete across Phases 1-8); 09-PHASE-VERIFICATION.md maps SC-1..SC-5 + 12 Pitfalls + 8 Open Questions LOCKED inline + ~40 D-09-* canonical bindings; PENDING_MANUAL_SMOKE 4-item operator walkthrough A-D: axe-core CI canonical first run / manual keyboard nav 10-step / rate-limit boundary smoke / Discord DM live receipt. v1.0 milestone ships pending operator smoke.*
