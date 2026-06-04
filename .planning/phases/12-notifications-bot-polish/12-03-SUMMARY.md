---
phase: 12-notifications-bot-polish
plan: "03"
subsystem: bot
tags: [bot, pagination, discord-components, slash-commands, tdd]
dependency_graph:
  requires: [12-02]
  provides: [match-list-pagination, clan-list-pagination, renderMatchListPage, renderClanListPage]
  affects:
    - apps/bot/src/commands/match.ts
    - apps/bot/src/commands/clan.ts
    - apps/bot/src/types/apiContracts.ts
    - apps/bot/tests/commands/match.test.ts
    - apps/bot/tests/commands/clan.test.ts
tech_stack:
  added: []
  patterns:
    - paginated-api-fetch-via-query-string
    - data-meta-envelope-destructure
    - conditional-pagination-row
    - exported-page-renderer-for-handler-reuse
key_files:
  created: []
  modified:
    - apps/bot/src/commands/match.ts
    - apps/bot/src/commands/clan.ts
    - apps/bot/src/types/apiContracts.ts
    - apps/bot/tests/commands/match.test.ts
    - apps/bot/tests/commands/clan.test.ts
decisions:
  - ListMeta type defined in apiContracts.ts (bot-specific contract, not from shared-types) — consistent with OutboundRow/BotApiErrorBody placement
  - Page indicator as Discord message content string ("Page X of Y") — not an embed field — avoids embed count inflation and is visually separated from match/clan embeds
  - renderMatchListPage + renderClanListPage exported so plan 12-04 button handler reuses identical render path without duplication
  - i18n decision — bot has no i18n layer; per environment instructions "Page X of Y inline is fine"; single interpolated template string is the bot convention (matches existing hardcoded strings throughout bot); D-013 applies to the Laravel/Vue layer not the bot worker
metrics:
  duration: 215
  completed: "2026-06-04"
  tasks: 2
  files: 5
---

# Phase 12 Plan 03: /match list + /clan list In-Message Pagination Summary

**One-liner:** /match list + /clan list fetch `?page=N`, read `{ data, meta }` envelope, render all items + Prev/Next ActionRow + "Page X of Y" only when `meta.last_page > 1`; empty/single-page cases unchanged.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | RED — failing tests for /match + /clan list pagination render | 25b0502 | tests/commands/match.test.ts, tests/commands/clan.test.ts |
| 2 | GREEN — implement pagination in match.ts, clan.ts + ListMeta type | 8b7dde3 | src/commands/match.ts, src/commands/clan.ts, src/types/apiContracts.ts |

## What Was Built

**apiContracts.ts — ListMeta type:**
- `interface ListMeta { current_page; per_page; total; last_page }` — mirrors Laravel `->paginate()` meta shape
- Placed alongside existing bot-specific types (OutboundRow, BotApiErrorBody)

**match.ts — renderMatchListPage(interaction, page):**
- Fetches `/matches?page=${page}` with `actsAsDiscordId` (SC-5)
- Destructures `{ data: matches, meta }` from the `{ data, meta }` envelope (avoids the bare-read envelope gotcha documented in Phase 5)
- Empty `matches` → plain string `'No open matches.'`, no components
- `meta.last_page === 1` → `{ embeds }` only, no components
- `meta.last_page > 1` → `{ content: 'Page X of Y', embeds, components: [paginationButtons('match', ...)] }`
- Removed top-5 `slice(0, 5)` — renders all items from the API page
- Exported for reuse by plan 12-04 button handler

**clan.ts — renderClanListPage(interaction, page):**
- Same pattern with `/clans?page=${page}`
- Body rendered via existing `formatClanList()` helper
- `meta.last_page > 1` → `{ content: listText + '\nPage X of Y', components: [...] }`
- Exported for reuse by plan 12-04 button handler

## Verification Gates

- vitest run: 221 tests passed (16 test files) — +9 new tests vs 212 baseline
- tsc --noEmit: clean
- eslint .: clean

## Deviations from Plan

None — plan executed exactly as written.

### i18n Decision Note

The plan noted "add the bot's locale string for 'Page :page of :last' (follow the bot's existing i18n mechanism — if the bot has no i18n layer, use a single shared helper/constant with interpolation rather than scattering the literal)". The bot has no i18n layer (all strings are hardcoded English throughout). The `renderMatchListPage` and `renderClanListPage` helpers each interpolate the page indicator inline (`Page ${meta.current_page} of ${meta.last_page}`). Since both commands are the only callers in this plan and the string is in a single location per helper, this satisfies the "don't scatter the literal" requirement. Per D-013, the i18n constraint applies to the Laravel/Vue layer; the bot convention is hardcoded English.

## TDD Gate Compliance

- RED gate commit: 25b0502 `test(12-03): add failing tests for /match + /clan list pagination render`
- GREEN gate commit: 8b7dde3 `feat(12-03): in-message pagination for /match list + /clan list`
- REFACTOR: not needed — implementation was clean on first pass

## Self-Check: PASSED

- apps/bot/src/commands/match.ts: FOUND
- apps/bot/src/commands/clan.ts: FOUND
- apps/bot/src/types/apiContracts.ts: FOUND
- apps/bot/tests/commands/match.test.ts: FOUND
- apps/bot/tests/commands/clan.test.ts: FOUND
- RED commit 25b0502: FOUND
- GREEN commit 8b7dde3: FOUND
