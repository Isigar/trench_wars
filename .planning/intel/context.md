# Context (synthesized intel)

Running notes from DOC-class sources. Verbatim with attribution. Downstream roadmapper consumes this for narrative starting points (especially the existing M1–M9 milestone breakdown) and unresolved TBDs.

---

## Topic: Project framing

source: /home/rtx/projects/trench-wars/.docs/README.md

Trenchwars — multi-clan competitive league platform for Hell Let Loose (and additional games later), with deep Discord integration and CRCON-driven match automation.

Status: Round 1 planning. No code yet. Last sync: 2026-05-03.

How to read these docs: Files are numbered to suggest reading order. Cross-references use relative links. Each file is meant to stand alone enough to drop into a task tracker.

### Index of source planning docs
- 01 Overview — Vision, scope, tenancy model, what is and isn't in round 1
- 02 Architecture — Services, monorepo layout, deployment, inter-service comms
- 03 Stack — Locked tech choices and library versions
- 04 Domain model — Entities and relationships, plain-language ERD
- 05 Database schema — Table-by-table column listing
- 06 Permissions & audit — Spatie roles, clan roles, activity log
- 07 Discord integration — OAuth, bot architecture, slash commands, single-guild model
- 08 RCON integration — CRCON adapter, match servers, event ingestion
- 09 Frontend — Inertia + Vue 3 plan: design system, components, routing, libs
- 10 i18n — Translation strategy, translatable content, locale handling
- 11 Tournaments — Formats, bracket generation, public bracket views
- 12 CMS & events — Articles, calendar events, publishing flow
- 13 API contracts — REST conventions, bot↔web contract, internal endpoints
- 14 Roadmap — Milestones M1–M9 with sequencing
- 15 Decisions log — Decisions made, with rationale (ADR-style)
- 16 Open questions — What's still TBD

---

## Topic: Glossary

source: /home/rtx/projects/trench-wars/.docs/README.md

- **Clan** — competitive group of players. Has tag, name, owner, members, internal roles.
- **Player** — public-facing profile linked 1:1 to a User. Has avatar, privacy settings, history.
- **User** — auth identity, sourced from Discord.
- **Match** — a single scheduled game. Has slots, signups, optionally a tournament link.
- **Tournament** — wrapper for many matches with a format (round-robin, single-elim, etc.).
- **Event** — calendar/CMS surface for a Match or Tournament.
- **CRCON** — `hll_rcon_tool` (https://github.com/MarechJ/hll_rcon_tool), the open-source HLL admin tool we integrate with.
- **League guild** — the single shared Discord server for the platform.

---

## Topic: Why no Laravel starter kit (rationale)

source: /home/rtx/projects/trench-wars/.docs/03-stack.md

- Breeze/Jetstream both ship session+email auth scaffolding we won't use.
- We want a single auth path: Discord OAuth, no fallback. Hand-rolling that is shorter than ripping out Breeze's auth views.
- We control frontend layout and styling from line one — no opinionated tailwind config to fight.

(Also captured as a locked decision in D-017.)

---

## Topic: Existing M1–M9 roadmap (round 1 starting point)

source: /home/rtx/projects/trench-wars/.docs/14-roadmap.md

Round 1 scope. Each milestone ends with something usable in production. Sizing assumes one experienced full-stack dev. Sequence is recommended; later milestones do not strictly require all of the previous. Total ≈ 10–12 weeks end-to-end for one dev. M1–M5 alone (~5 weeks) is a credible competitive-system MVP without tournaments, CMS, or RCON automation.

### M1 — Foundations · ~1 week
Outcome: Deployable Laravel app with Discord login, an admin panel, and the audit infrastructure.
- Monorepo skeleton (apps/web, apps/bot, apps/rcon-worker, packages/shared-types, docker, docs).
- Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Vite + TS + Tailwind v4.
- Discord Socialite OAuth, `users` + `players` + `player_privacy` tables.
- Filament v3 panel + base resources for User, Player, Role, Permission.
- spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-translatable, spatie/laravel-data.
- i18n plumbing: `lang/en`, Inertia shared `translations`, laravel-vue-i18n.
- Layouts (PublicLayout, AuthedLayout) + theme tokens + token CSS + Reka UI primitives wrappers.
- Railway deploy: web, worker, postgres, redis. CI: pint, phpstan, pest.
- TypeScript generation command `typescript:generate`.

### M2 — Clans & tags · ~1 week
Outcome: Clans exist as first-class entities; players have public profiles.
- Clans, ClanTags, ClanMembership, ClanInvite, ClanApplication.
- Public pages: clan directory, clan detail, player profile (privacy-aware).
- Filament resources: Clan, ClanTag, ClanMembership with audit tabs.
- Avatars: Discord-sourced + upload override via medialibrary.
- "My Clan" page for officers/leaders.
- Discord OAuth honours league guild membership check (toggle).

### M3 — Games & match types · ~3 days
Outcome: Admin can model any game's roles and match types fully in Filament.
- Games, GameRoles, GameMatchTypes, GameMatchTypeRoleLimits.
- Filament resource for Game with relation managers (Roles, Match Types).
- Each Match Type editor lets admin set role limits per role.
- Seeder for HLL: roles (Commander, Officer, SL, Rifleman, Assault, AR, Medic, Engineer, Support, HMG, AT, Sniper, Spotter, Tank Cmdr, Crewman) and a starting set of match types (Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War). All editable post-seed.

### M4 — Matches (manual) · ~1.5 weeks
Outcome: Matches can be created, signed up to, played, and results entered manually.
- Match, MatchTeam, MatchSlot (materialised from match-type templates), MatchSignup, MatchResult, MatchAccessRule.
- Public pages: match list (filterable, calendar), match detail (slots, signups, result), signup modal.
- Slot capacity enforcement via DB transaction with row lock.
- Tag-restricted match access.
- Manual result entry by organiser/admin.
- Filament resources for Match and MatchResult with audit tabs.
- Auto-create `Event` rows when a public match is created.

### M5 — Discord bot v1 · ~1.5 weeks
Outcome: Most match interactions can happen from Discord.
- apps/bot service: discord.js v14, TS, Sanctum machine token, Redis subscriber.
- Slash commands: `/clan info|list|apply`, `/match list|info|signup|leave`, `/profile`, `/me`.
- Modal slot picker for signup.
- RSVP buttons under match-announce embeds.
- Outbound announcer pipeline (`web` → Redis → bot) with `discord_outbound_messages` durability.
- Event hooks: guildMemberRemove, guildMemberUpdate (role removal), guildMemberAdd (verified link).
- Clan role sync on join/leave, with reconciliation worker.

### M6 — Tournaments & brackets · ~2 weeks
Outcome: Run an end-to-end tournament with bracket UI.
- Tournament, TournamentParticipant, TournamentStage, TournamentRound, TournamentBracket.
- Bracket generators: round-robin, single-elim, double-elim, swiss.
- Filament: Tournament resource with relation managers, seeding UI, format settings.
- Public tournament page with Bracket / Standings / Schedule / Participants tabs.
- Bracket viewer Vue components (single-elim cascade with SVG, RR matrix, DE stack, swiss list).
- Match materialisation when bracket nodes are ready.
- Standings calculator with tiebreakers.
- Reseed / forfeit / withdraw admin actions.

### M7 — CMS · ~1 week
Outcome: League can publish editorial content and curate the calendar.
- Articles, Categories, Events.
- Filament resources with translatable per-locale tabs.
- Tiptap editor; markdown-it rendering on public side.
- Scheduled publishing via Laravel Scheduler.
- Public blog index, article page, events calendar (vue-cal).
- Optional Discord announce on publish.
- Sitemap + meta tags.

### M8 — RCON automation · ~2 weeks
Outcome: Match results and player stats flow in automatically when matches are played on registered servers.
- MatchServer, MatchServerBooking with Postgres exclusion constraint.
- Filament: MatchServer resource with Test Connection action.
- apps/rcon-worker service: TS, ioredis stream consumer, undici, ws.
- CRCON adapter: connect, subscribe to live log, normalise events.
- POST `/api/internal/match/{id}/events` ingestion + signing.
- Result computation on `match_end`; MatchPlayerStat aggregation.
- Manual override always available.
- Failure handling: gap recovery via CRCON time-bounded log queries; explicit `error` event surface in admin.

### M9 — Polish · buffer
- Notifications hub (web bell + Discord DM rules).
- Search (Postgres FTS).
- Leaderboards (top clans, top players by stat windows).
- Mod tooling (bulk actions, bans, dispute resolution).
- Performance pass (N+1 audit, cache key strategy).
- Accessibility audit pass.
- Hardening pass (rate limits, abuse vectors).

---

## Topic: Open Questions (TBDs from source)

source: /home/rtx/projects/trench-wars/.docs/16-open-questions.md

These are unresolved at planning time. Per ingest manifest: surfaced as INFO/WARNING (advisory), not BLOCKER. Capture answers as decisions in `decisions.md`.

### Branding & content
- Brand name confirmed as "Trenchwars"?
- Logo / mark direction.
- Default accent colour confirmed (current placeholder: deep red on muted olive).
- Domain name / hostnames.

### Editorial
- Initial blog categories. Suggested: News, Match Reports, Tournament Updates, Community.
- Editorial team — who has `cms-editor` at launch?
- Cadence of league announcements (informs notification settings defaults).

### Matches & tournaments
- Confirm initial HLL match-type set: `Scrim 50v50`, `Skirmish 6v6`, `Friendly`, `Clan War`, `Tournament`. Anything to add?
- Default best-of for tournament finals.
- Whether forfeits award default scores or just a winner.
- Tiebreaker rules for round-robin: head-to-head → point differential → coin flip?
- Swiss tiebreakers: Buchholz, opponent points, head-to-head — order?
- Maximum tournament size we should design for (visual bracket limits).

### Discord
- Does logging in require being in the league guild, or only encouraged?
- Channel naming convention for clan announce channels (so admins don't have to paste IDs).
- Bot avatar / banner.
- Per-clan voice channels managed by the bot? (Out of round 1 by default.)

### RCON
- CRCON version we standardise on.
- Server pool sizing — how many active match servers at launch?
- Who manages the CRCON deploy on each server? (Probably league IT, but worth confirming.)
- Should we cache CRCON live logs ourselves for replay, or rely on CRCON's retention?

### Data & retention
- Activity log retention policy beyond round 1 (current default: indefinite).
- GDPR right-to-erasure flow: anonymise vs delete? Anonymise is preferred for match-history integrity.
- Backup cadence for Postgres on Railway.

### Future locales
- Order of additional languages (CS, SK, PL, DE, RU, FR?).
- Whether to translate clan-authored content (we don't translate; owner does).

### Performance & ops
- SSR on by default in production, or opt-in per route?
- Rate limit numbers for the public API.
- Email provider for transactional mail (currently no email need beyond OAuth — Discord covers DMs).
- Monitoring stack: Railway logs only, or add Sentry / Logtail?

### Naming conventions
- Slug rules (current default: lowercase, kebab, ASCII only — confirmed?).
- Clan tag length cap (suggested: 2–8 chars).
- Reserved words list for clan slugs (`admin`, `me`, `api`, etc.).
