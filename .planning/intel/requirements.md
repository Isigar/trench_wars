# Requirements (synthesized intel)

Derived from the single PRD-class document (01-overview.md). Each requirement is keyed `REQ-{slug}` with explicit source attribution. Where the PRD frames intent in goals/non-goals/success-criteria form, requirements are extracted verbatim.

source: /home/rtx/projects/trench-wars/.docs/01-overview.md
classification confidence: medium (PRD signal dominant: explicit Vision / Goals / Non-goals / Constraints / Success criteria sections)

---

## REQ-platform-vision
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Vision)
- scope: product framing
- description: A web platform where multiple clans organise competitive matches and tournaments — primarily for Hell Let Loose, designed so additional games can be added without code changes. Discord is the primary social layer; the website is the source of truth for clans, players, matches, results, and editorial content.
- acceptance: Game-agnostic data model is implemented (Game / GameRole / GameMatchType / GameMatchTypeRoleLimit). HLL is a seeded preset, not hard-coded. Cross-cuts D-007.

---

## REQ-tenancy-multi-clan
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Tenancy)
- scope: tenancy model
- description: Multi-clan league platform. One deployment hosts many clans. Public clan directory, public player profiles, public match calendar, public bracket viewer.
- acceptance: All four public surfaces (clan directory, player profiles, match calendar, bracket viewer) exist and are reachable without authentication.

---

## REQ-tenancy-single-guild
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Tenancy)
- scope: Discord topology
- description: One shared league Discord guild. Each clan = a Discord role inside that guild. There are no per-clan Discord servers in the model.
- acceptance: `discord_guild` table holds exactly one row. Each clan binds to a Discord role id (not a separate guild). Cross-cuts D-003.

---

## REQ-goal-match-workflows
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Goals 1)
- scope: scheduling
- description: Replace ad-hoc Discord scheduling with structured match/tournament workflows.
- acceptance: A match can be created, slot-templated, signed up to, and scheduled without leaving the platform's structured surfaces.

---

## REQ-goal-rcon-history
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Goals 2)
- scope: match history capture
- description: Capture match history per clan and per player automatically via CRCON.
- acceptance: When a match is played on a registered match server, MatchResult and per-player MatchPlayerStat rows are populated automatically from CRCON events. Cross-cuts D-005, D-019.

---

## REQ-goal-public-profiles
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Goals 3)
- scope: profile privacy
- description: Provide public profiles for clans and players with controllable privacy.
- acceptance: Player profiles render under user-controlled per-section flags + global tier (`public|community|clan|private`). Cross-cuts D-018.

---

## REQ-goal-cms
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Goals 4)
- scope: editorial / events
- description: Provide a CMS for league announcements and event calendar.
- acceptance: Articles, Categories, and Events are first-class entities, editorially managed in Filament, surfaced on public pages.

---

## REQ-goal-discord-ux
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Goals 5)
- scope: Discord UX
- description: Tight Discord UX: signups, RSVPs, results announcements happen via slash commands and channel posts.
- acceptance: Slash commands `/clan`, `/match`, `/profile`, `/me` exist; RSVP buttons + slot-picker modals work; result announcements post to the host clan's announce channel.

---

## REQ-non-goals-round-1
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Non-goals)
- scope: explicit out-of-scope (round 1)
- description: Native mobile apps; real-time spectator overlays; anti-cheat / VAC integrations; federation across multiple league installs; per-clan custom domains or per-clan theming.
- acceptance: None of the above ship in round 1. Roadmapper must place these outside M1–M9.

---

## REQ-constraint-league-owns-servers
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Constraints)
- scope: infrastructure ownership
- description: League owns the HLL game servers used for matches; CRCON is installed on each.
- acceptance: Match servers are league-managed entries; CRCON deployment is in scope; no per-clan-managed servers in round 1.

---

## REQ-constraint-single-guild
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Constraints)
- scope: Discord topology
- description: Single Discord guild for the entire league.
- acceptance: Same as REQ-tenancy-single-guild. Reinforces D-003.

---

## REQ-constraint-en-launch-i18n-ready
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Constraints)
- scope: i18n
- description: English at launch; multi-language must be possible without refactor.
- acceptance: All UI strings via `__()` / `t()`; translatable models use jsonb keyed by locale; adding a locale is config + content task. Cross-cuts D-013.

---

## REQ-constraint-railway-deploy
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Constraints)
- scope: hosting
- description: Deployed to Railway; not optimised for self-hosting elsewhere round 1.
- acceptance: Five-service Railway topology (web, worker, bot, rcon-worker, db, redis); secrets in Railway env groups. Cross-cuts D-014.

---

## REQ-success-end-to-end-scrim
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Round-1 success criteria)
- scope: round-1 acceptance gate
- description: Two clans can create accounts, build rosters, schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically.
- acceptance: End-to-end flow works without manual data entry on the happy path: Discord OAuth → clan create → roster build → scrim schedule → Discord signup → CRCON-played → auto-recorded MatchResult + MatchPlayerStat.

---

## REQ-success-tournament-end-to-end
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Round-1 success criteria)
- scope: round-1 acceptance gate
- description: Mods can run a single-elimination tournament across 8 clans end-to-end.
- acceptance: An 8-clan single-elim tournament can be created, seeded, brackets generated, matches materialised, played, results captured, advancements computed — without admin patching.

---

## REQ-success-public-browse
- source: /home/rtx/projects/trench-wars/.docs/01-overview.md (Round-1 success criteria)
- scope: round-1 acceptance gate
- description: A public visitor can browse clans, players, the calendar, bracket views, and articles.
- acceptance: All listed surfaces accessible without auth; SSR enabled in production for first paint on public pages.
