---
phase: 5
phase_name: Discord bot v1
gathered: 2026-05-13
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 5: Discord bot v1 — Context

<domain>
## Phase Boundary

Move the day-to-day match interactions into Discord (slash commands, modals, RSVP buttons) so clan members can organise scrims without leaving Discord, while keeping `web` as the source of truth.

**Success Criteria** (from ROADMAP):
1. Discord user invokes `/clan info|list|apply`, `/match list|info|signup|leave`, `/profile`, `/me` — correct, privacy-aware responses inside 3s interaction window (deferReply for slow paths).
2. `/match signup` modal creates match_signups row visible on website immediately; clan-role membership rules enforced server-side.
3. Website match creation triggers an announce-channel embed with RSVP buttons; persisted in `discord_outbound_messages` (pending → sent | failed) for durability.
4. Joining/leaving a clan on the website triggers Discord role assign/remove via Horizon-retried jobs; manual Discord-side role changes reconcile via `guildMemberUpdate` hook.
5. All bot→web traffic uses Sanctum `bot:*` scoped token + `X-Bot-Acts-As-User` header; audit log attributes human causer correctly.

**Depends on**: Phase 4 (Matches manual — complete; GameMatch + signup service ready)

**Requirements**: REQ-goal-discord-ux

</domain>

<decisions>
## Implementation Decisions

### Locked Decisions Relevant to Phase 5
- **D-002** Discord ID is canonical user identity (already in User.discord_id from Phase 1).
- **D-003** One league Discord guild; clan = role inside guild.
- **D-004** Bot is thin display layer — NO DB writes from bot, NO business logic. Every interaction calls Laravel API.
- **D-006** Multi-clan league platform.
- **D-014** Railway 5-service deploy (web + bot + rcon-worker + postgres + redis).
- **D-015** pnpm-workspaces monorepo — bot lives in `apps/bot/`.
- **D-020** TS types from `spatie/laravel-data` → `packages/shared-types/` consumed by bot.
- **D-021** Container-only — bot runs in `trenchwars-bot` container (already up per Phase 1 docker-compose).
- **Bot stack** (CLAUDE.md §2): Node 22 + `discord.js@^14.26` + `undici` (HTTP) + TypeScript strict.

### Claude's Discretion
- Modal field shapes for `/match signup` (recommend role picker dropdown sourced from match's role list via web API).
- Embed visual design (RESEARCH should propose canonical layout — match card with role-slot grid).
- Outbound message worker (Horizon Job vs Node-side cron polling). **Recommend:** Laravel sends to `discord_outbound_messages` table; bot polls or web pushes via Redis pub/sub or webhook.
- Retry semantics for outbound messages (exponential backoff; 3 attempts then mark `failed` and require manual replay).
- Test surface: bot-side Vitest for unit logic; integration tests via web Pest hitting bot-facing API endpoints.

### Conventions Inherited
- Pest 4 for web tests; Vitest for bot tests.
- Pint + PHPStan L8 for web; `tsc --strict` + `prettier` for bot.
- spatie/laravel-data DTOs + custom typescript-generate for bot consumption.
- Activity log via LogsActivity on outbound messages + role-sync records.
- i18n: bot responses use English at v1 (translation deferred — TODO Phase 7/9).

</decisions>

<code_context>
## Existing Code Insights

To be gathered by gsd-phase-researcher. Known relevant prior work:
- Phase 1: apps/bot/ scaffolded but unused; Node 22 + discord.js installed; PNPM workspace wired.
- Phase 4: GameMatch model + MatchSignupService + public POST /matches/{id}/signups.
- Phase 2: User.discord_id, ClanMembership, Clan.discord_role_id, DiscordGuild singleton row.
- packages/shared-types already exports all Phase 3/4 DTOs as TS types — bot can import directly.

</code_context>

<specifics>
## Specific Ideas

**New tables (web side):**
- `discord_outbound_messages` — id, channel_id, message_type (enum: match_announce / role_sync / etc), payload (JSONB), status (pending/sent/failed), attempts, last_error, sent_message_id (nullable Discord message snowflake for follow-ups), causer_user_id, timestamps
- `bot_api_tokens` — Sanctum ability rows already exist; configure abilities like `bot:read`, `bot:act-as-user`, `bot:write-outbound`
- `discord_role_sync_jobs` — optional dedicated tracking table or piggyback on Laravel Horizon's job table

**Bot endpoints (Laravel API, all Sanctum + bot:* scoped):**
- `GET /api/bot/clans/{discord_role_id}` — clan info by Discord role
- `GET /api/bot/clans` — list
- `POST /api/bot/clans/{id}/applications` — apply on behalf of user
- `GET /api/bot/matches` / `GET /api/bot/matches/{id}` — list/show
- `POST /api/bot/matches/{id}/signups` — signup on behalf of user (uses MatchSignupService)
- `DELETE /api/bot/matches/{id}/signups/{slot}` — leave
- `GET /api/bot/users/me` — return privacy-respecting profile
- `GET /api/bot/outbound-messages?status=pending` — bot polls for pending outbound
- `POST /api/bot/outbound-messages/{id}/sent` / `POST /api/bot/outbound-messages/{id}/failed` — bot reports result

**Bot side (apps/bot/):**
- `src/index.ts` — entry, login, register slash commands on startup
- `src/commands/` — slash command handlers (one file per top-level command)
- `src/components/` — button + modal interaction handlers (RSVP buttons)
- `src/services/api.ts` — web API client with Sanctum token + X-Bot-Acts-As-User header
- `src/services/outbound.ts` — pending message polling worker
- `src/events/guildMemberUpdate.ts` — reconciliation hook for manual role changes
- `src/lib/embeds.ts` — canonical Discord embed builders (match card, clan card, profile)

**Filament additions:**
- DiscordOutboundMessageResource (read-only; admin can view + retry failed messages)

</specifics>

<deferred>
## Deferred Ideas

- i18n for bot responses (v1 ships English; multi-locale later via locale lookup on User).
- Slash command autocomplete (out-of-scope v1; basic commands only).
- Voice channel integration / event scheduling on Discord side (Phase 6+).
- Tournament-related Discord features (bracket announcement embeds) — Phase 6.
- RCON result-announce embeds — Phase 8.

</deferred>
