---
phase: 5
slug: discord-bot-v1
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-13
approved: 2026-05-13
---

# Phase 5 — Validation Strategy

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Web framework** | Pest 4 (PHP) |
| **Bot framework** | Vitest (TypeScript) |
| **Quick web** | `make pest ARGS="--filter=Bot"` |
| **Quick bot** | `make pnpm ARGS="-F @trenchwars/bot test"` |
| **Full** | `make pest && make pnpm ARGS="-F @trenchwars/bot test"` |

## Sampling Rate

- After every commit: filtered Pest + Vitest scope (~20s)
- After every wave: full suite (~90s)
- Before phase verify: web + bot suites + Pint + PHPStan + tsc all GREEN

## Per-Plan Coverage Map (planner-populated)

| Plan | Wave | Coverage focus |
|------|------|----------------|
| 05-01 | 0 | Wave 0 scaffolding + Sanctum/Horizon install + worker compose entry + stubs |
| 05-02 | 1 | Migrations (discord_outbound_messages, clans.discord_announce_channel_id) |
| 05-03 | 2 | ResolveBotActsAsUser middleware + Sanctum scoped abilities |
| 05-04 | 3 | BotApi controllers (clan, match, signup, outbound, discord-events) |
| 05-05 | 4 | MatchObserver outbound row writer + Match-announce embed payload |
| 05-06 | 5 | SyncDiscordRolesJob + ClanMembershipObserver + 60s echo suppression |
| 05-07 | 6 | DiscordOutboundMessageResource (Filament) + retry action + Artisan token-issue |
| 05-08 | 7 | Bot core (apps/bot/src/index, client, env, api service) |
| 05-09 | 8 | Bot slash commands (/clan, /match, /profile, /me) |
| 05-10 | 9 | Bot components (RSVP buttons, signup modal) + embed builders |
| 05-11 | 10 | Bot outbound worker + guildMemberUpdate event handler |
| 05-12 | 11 | i18n + audit log integration + admin presence tests |
| 05-13 | 12 | [BLOCKING] Phase verification + ROADMAP + REQUIREMENTS + final gates |

## Wave 0 Requirements

- composer require laravel/sanctum; php artisan install:api
- composer require laravel/horizon (if not already)
- docker-compose adds `worker` service (Horizon runner)
- 20 RED test stubs per RESEARCH.md inventory

## Manual-Only Verifications

| Behavior | Why Manual | Instructions |
|----------|------------|--------------|
| Live Discord guild interaction smoke (slash commands, RSVP button, signup modal) | Requires real Discord guild + bot token | Operator runs `/clan list` in test guild, gets ephemeral reply within 3s |
| Outbound delivery end-to-end | Bot + web + discord live | Create match via Filament; verify announce embed appears in clan channel within ~10s |
| Role sync on join/leave | Live Discord roles | Player joins clan via website; verify Discord role assigned within 30s |
| guildMemberUpdate reconciliation | Manual Discord role edit | Admin removes role in Discord; verify ClanMembership ended on website |
| Sanctum bot:* token misuse rejected | Manual API replay | curl with wrong scope → 403 |

## Validation Sign-Off

- [x] All tasks will have `<automated>` verify
- [x] Wave 0 covers RED stubs
- [x] No watch-mode flags
- [x] `nyquist_compliant: true`

**Approval:** 2026-05-13 autonomous workflow.
