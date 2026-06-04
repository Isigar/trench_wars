---
phase: 12-notifications-bot-polish
plan: "04"
subsystem: bot
tags: [bot, pagination, discord-components, button-handler, tdd]
dependency_graph:
  requires: [12-02, 12-03]
  provides: [list_page-button-handler, pg-routing-no-defer]
  affects:
    - apps/bot/src/components/rsvpButton.ts
    - apps/bot/src/events/interactionCreate.ts
    - apps/bot/tests/components/rsvpButton.test.ts
tech_stack:
  added: []
  patterns:
    - interaction-update-as-initial-response
    - pg-prefix-no-defer-routing
    - inline-fetch-and-update-in-button-handler
    - error-path-must-acknowledge-interaction
key_files:
  created: []
  modified:
    - apps/bot/src/components/rsvpButton.ts
    - apps/bot/src/events/interactionCreate.ts
    - apps/bot/tests/components/rsvpButton.test.ts
decisions:
  - pg: buttons routed in the no-pre-defer branch of interactionCreate.ts alongside m:o: — interaction.update() IS the initial response; deferReply would claim the 3s window and block update()
  - list_page branch inlines api.get + payload construction in rsvpButton.ts rather than reusing renderMatchListPage/renderClanListPage — the command renderers call editReply on ChatInputCommandInteraction; button handler needs update() on ButtonInteraction; inlining avoids a forced refactor of the 12-03 render functions
  - error path calls interaction.update() (not reply/editReply) to prevent "application did not respond" — pg: buttons are undeferred so update() is the only valid acknowledgement
  - formatClanList logic duplicated inline (one-liner map) rather than importing a private function from clan.ts — keeps rsvpButton self-contained; deduplication deferred to future polish
metrics:
  duration: 240
  completed: "2026-06-04"
  tasks: 2
  files: 3
---

# Phase 12 Plan 04: Prev/Next Button Handler (list_page) Summary

**One-liner:** pg: button interactions bypass ephemeral pre-defer, re-fetch the requested page via api.get, and call interaction.update() to mutate the same Discord message with new embeds + refreshed Prev/Next buttons + "Page X of Y".

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | RED — failing tests for list_page handler + pg: routing | ba2a8f4 | tests/components/rsvpButton.test.ts |
| 2 | GREEN — implement list_page branch + pg: dispatch routing | 89c7e32 | src/components/rsvpButton.ts, src/events/interactionCreate.ts |

## What Was Built

**interactionCreate.ts — pg: no-defer routing:**
- Extended the no-pre-defer condition from `startsWith('m:o:')` to `startsWith('m:o:') || startsWith('pg:')`
- `pg:` buttons join `m:o:` in the branch where `handleButton()` is called WITHOUT a preceding `deferReply()`
- All other buttons still receive the ephemeral `deferReply` (regression-safe)

**rsvpButton.ts — list_page branch:**
- Added before the deferred-only branches (`match_signup`, `match_leave`, `clan_apply`)
- `listType === 'match'`: fetches `/matches?page=N`, builds matchCard embeds + paginationButtons row, calls `interaction.update({ content, embeds, components })`
- `listType === 'clan'`: fetches `/clans?page=N`, formats clan list text, calls `interaction.update({ content, embeds: [], components })`
- Empty / single-page cases call `update()` without components (consistent with command renderers)
- Error path calls `update()` with error text to guarantee the interaction is always acknowledged
- Imports: `paginationButtons` (12-02), `matchCard` (existing embeds), `ClanData/ListMeta/PublicMatchData` types

## Verification Gates

- vitest run: 232 tests passed (16 test files) — +11 new tests vs 221 baseline
- tsc --noEmit: clean
- eslint .: clean

## Deviations from Plan

**[Rule 3 - Approach] Inlined fetch logic instead of reusing renderMatchListPage/renderClanListPage**

- **Found during:** GREEN implementation
- **Issue:** The plan suggested calling the shared renderers from 12-03. Those renderers take `ChatInputCommandInteraction` and call `editReply()`. A `ButtonInteraction` exposes `update()` not `editReply()`. Calling `editReply()` on a ButtonInteraction that was never deferred would fail at runtime.
- **Fix:** Inlined the API fetch + payload construction directly in the `list_page` branch of `rsvpButton.ts`. The fetch paths (`/matches?page=N`, `/clans?page=N`), envelope destructuring (`{ data, meta }`), `paginationButtons()` call, and "Page X of Y" template string are all verbatim equivalents of the 12-03 renderers — identical behavior, different output target (`update()` vs `editReply()`).
- **Files modified:** `apps/bot/src/components/rsvpButton.ts`
- **Commit:** 89c7e32

## TDD Gate Compliance

- RED gate commit: ba2a8f4 `test(12-04): list_page handler updates same message + pg routing`
- GREEN gate commit: 89c7e32 `feat(12-04): list_page handler re-fetches page and updates the same message`
- REFACTOR: not needed — implementation was clean on first pass

## Known Stubs

None — all data flows from live API fetches.

## Threat Flags

None — no new network endpoints, auth paths, file access patterns, or schema changes introduced. The `actsAsDiscordId` forwarding follows the identical trust model as existing rsvp branches (T-12-04-S: accepted per plan threat register).

## Self-Check: PASSED

- apps/bot/src/components/rsvpButton.ts: FOUND
- apps/bot/src/events/interactionCreate.ts: FOUND
- apps/bot/tests/components/rsvpButton.test.ts: FOUND
- RED commit ba2a8f4: FOUND
- GREEN commit 89c7e32: FOUND
