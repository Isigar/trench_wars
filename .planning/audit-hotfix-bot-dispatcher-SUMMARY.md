---
hotfix: v1.0-milestone-audit-bot-dispatcher
parent: v1.0-MILESTONE-AUDIT.md
status: complete
date: 2026-05-17
phase_ref: cross-phase (Phase 7 / 8 / 9 outbound kinds; Phase 5 dispatcher)
tags: [bot, discord, outbox, audit-hotfix]
---

# v1.0 audit hotfix — bot dispatcher bridge for Phase 7/8/9 outbound kinds

## Why

The 2026-05-15 cross-phase integration audit (`.planning/v1.0-MILESTONE-AUDIT.md`)
caught a load-bearing wiring gap: the web side correctly enqueues three new
outbound `discord_outbound_messages` kinds added by Phases 7/8/9, but the
Phase 5 bot dispatcher at `apps/bot/src/services/render.ts` did not recognise
them. In production every `article_announce`, `match_result_announce`, and
`user_dm` row would throw `[bot/render] Unknown message_type: …`, the worker
would POST `/outbound-messages/{id}/failed`, and no Discord side-effect would
ever happen.

Audit BLOCKER 1 + the Q5 / Q6 / SC-1 partial findings are the entry point.

## What changed

| File                                       | Change                                                                                                                                                                  |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `apps/bot/src/types/apiContracts.ts`       | Extended `OutboundRow.message_type` literal union with `article_announce`, `match_result_announce`, `user_dm`. Drift now compile-fails instead of throwing at runtime. |
| `apps/bot/src/env.ts`                      | Added optional `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` env. Used as fallback when the web side writes `channel_id=''` (ArticleObserver convention, D-06-10-E precedent).  |
| `apps/bot/src/lib/embeds.ts`               | Added 3 embed builders + payload interfaces: `buildArticleAnnounceEmbed` / `ArticleAnnouncePayload`, `buildMatchResultAnnounceEmbed` / `MatchResultAnnouncePayload`, `buildUserDmEmbed` / `UserDmPayload`. Hex+integer color parsing, defensive title fallbacks, oversize-value clamps (T-05-10-02). |
| `apps/bot/src/services/render.ts`          | Added `renderArticleAnnounce`, `renderMatchResultAnnounce`, `renderUserDm` dispatch branches + `resolveChannelId(row)` helper for the row-empty → env fallback. `renderUserDm` uses `client.users.fetch(recipient_id) + user.send(...)` (discord.js v14 DM idiom — auto-creates the DM channel on first send). Discord error 50007 (DMs disabled) bubbles up so the worker marks the row failed with a clear operator-visible error. |
| `apps/bot/tests/lib/auditHotfixEmbeds.test.ts`  | **23 new Vitest cases** — embed-builder happy paths, defensive null/empty fallbacks, color-token map, T-05-10-02 oversize clamps, T-08-12-01 Steam-ID-not-leaked sanity. |
| `apps/bot/tests/services/auditHotfixRender.test.ts` | **14 new Vitest cases** — dispatcher branch recognition (no "Unknown message_type" regression), channel-fallback resolution, channel-not-text-based / channel-null failures, user_dm 50007 propagation, `channels.fetch` never called for user_dm. |

## Quality gates

| Gate                          | Command                                                                                                | Result                                  |
| ----------------------------- | ------------------------------------------------------------------------------------------------------ | --------------------------------------- |
| TypeScript strict (`tsc --noEmit`) | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm run typecheck'` | PASS — clean                            |
| ESLint                         | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm run lint'`      | PASS — clean                            |
| Vitest (full bot suite)        | `docker compose run --rm --no-deps -v $PWD:/repo bot sh -c 'cd /repo/apps/bot && pnpm test'`          | **176 passed (13 test files)** — +37 over Phase 9's 139 baseline |

Web-side Pest / Pint / PHPStan are unchanged by this hotfix (no `apps/web/`
files were touched).

## Audit BLOCKER 1 disposition

> BLOCKER 1 — Bot render.ts is missing dispatchers for 3 outbound message_type values

| Required fix item (from audit recommendation)                               | Done? | Where                                                                       |
| --------------------------------------------------------------------------- | ----- | --------------------------------------------------------------------------- |
| 1. Add `buildArticleAnnounceEmbed`, `buildMatchResultAnnounceEmbed`, `buildUserDm*` to embeds.ts | YES   | `apps/bot/src/lib/embeds.ts` (3 new exports + 3 payload interfaces)         |
| 2. Add 3 dispatch branches to `render(client, row)`                          | YES   | `apps/bot/src/services/render.ts` (3 new `renderXxx` functions)             |
| 3. Fallback channel resolution when `row.channel_id === ''`                 | YES   | `resolveChannelId(row)` helper + `env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID`   |
| 4. Extend `apiContracts.ts` OutboundRow message_type union                  | YES   | `apps/bot/src/types/apiContracts.ts`                                        |
| 5. Bot Vitest cases per kind                                                 | YES   | 23 embed cases + 14 dispatcher cases (37 total)                             |
| 6. user_dm via `client.users.fetch()` (not `channel.send`)                  | YES   | `renderUserDm` — cast to `(opts: unknown) => Promise<Message>` for Discord REST snake_case `allowed_mentions` to match Phase 5 idiom |

Audit WARNING 1 (channel_id='' placeholder unresolved) is also closed by the
same `resolveChannelId` helper — `article_announce` rows written with empty
channel_id now resolve to `env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` at
dispatch instead of throwing on `client.channels.fetch('')`.

WARNING 3 (apiContracts.ts stale union) is closed by step 4.

WARNING 2 (Phase 7/8/9 added zero bot Vitest tests) is structurally
addressed by this hotfix: future kinds that bypass the bot dispatcher will
now fail compile (union check) and bot Vitest in CI.

## Side effects / risk

- The bot service must be restarted to pick up the new render branches —
  no DB migration, no web-side change. Railway rolling-restart is sufficient.
- `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` is OPTIONAL — empty string keeps the
  existing behaviour for tournament_announce + bracket_result_announce rows.
  When `article_announce` is enabled in production (web `config('discord.league_announce_channel_id')`),
  the bot env MUST be set to the same snowflake or those rows will fail
  with a clear "No channel resolvable" error instead of silently bouncing
  on `client.channels.fetch('')`.
- user_dm rows where the recipient has DMs disabled (Discord 50007) will
  still be marked failed with the Discord error string — operator-visible
  via the Filament `DiscordOutboundMessageResource`. Future polish (out of
  scope) can add a back-channel preference flag so the dispatcher stops
  enqueueing rows for users who repeatedly bounce.

## Files committed

- `apps/bot/src/types/apiContracts.ts` (modified)
- `apps/bot/src/env.ts` (modified)
- `apps/bot/src/lib/embeds.ts` (modified — 3 new public exports)
- `apps/bot/src/services/render.ts` (modified — 3 new dispatcher branches + helper)
- `apps/bot/tests/lib/auditHotfixEmbeds.test.ts` (new, 23 cases)
- `apps/bot/tests/services/auditHotfixRender.test.ts` (new, 14 cases)
- `.planning/audit-hotfix-bot-dispatcher-SUMMARY.md` (this file)
