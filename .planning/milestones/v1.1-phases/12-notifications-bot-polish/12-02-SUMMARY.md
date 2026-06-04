---
phase: 12-notifications-bot-polish
plan: "02"
subsystem: bot
tags: [bot, customIds, pagination, discord-components, tdd]
dependency_graph:
  requires: []
  provides: [list_page-customId, paginationButtons-factory]
  affects: [apps/bot/src/lib/customIds.ts, apps/bot/src/lib/buttons.ts]
tech_stack:
  added: []
  patterns: [strict-arity-decode, positive-integer-parse, null-on-malformed, bound-aware-button-disabling]
key_files:
  created:
    - apps/bot/tests/lib/buttons.test.ts
  modified:
    - apps/bot/src/lib/customIds.ts
    - apps/bot/tests/lib/customIds.test.ts
    - apps/bot/src/lib/buttons.ts
decisions:
  - list_page encodes pg:m:<page> (match) / pg:c:<page> (clan); 8 chars max overhead — well under 100-char customId budget
  - paginationButtons uses Math.max/min clamping so disabled buttons still carry a valid encodable customId
  - Number.isInteger + >0 check rejects floats, NaN, zero, negatives — strict positive-integer parse
metrics:
  duration: 84s
  completed: "2026-06-04"
  tasks: 2
  files: 4
---

# Phase 12 Plan 02: list_page customId + Prev/Next Pagination Buttons Summary

**One-liner:** list_page customId variant (pg:m/c:<page>, strict-arity decode, null-on-malformed) + bound-aware paginationButtons ActionRow factory via TDD.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | RED — failing tests for list_page customId + pagination buttons | b0d3de3 | tests/lib/customIds.test.ts, tests/lib/buttons.test.ts |
| 2 | GREEN — implement list_page + paginationButtons | fab3e3e | src/lib/customIds.ts, src/lib/buttons.ts |

## What Was Built

**customIds.ts — list_page variant:**
- Union extended: `| { kind: 'list_page'; listType: 'match' | 'clan'; page: number }`
- Encode: `pg:m:<page>` (match) or `pg:c:<page>` (clan)
- Decode: strict arity (`parts.length === 3`), listType allowlist (`m`→match, `c`→clan, else null), positive-integer page (`Number.isInteger(page) && page > 0`); any deviation returns null (T-12-02-T mitigation)

**buttons.ts — paginationButtons factory:**
- `paginationButtons(listType, page, lastPage): ActionRowBuilder<ButtonBuilder>`
- Prev ButtonBuilder: `encodeButtonId({ kind:'list_page', listType, page: Math.max(1, page-1) })`, disabled when `page <= 1`
- Next ButtonBuilder: `encodeButtonId({ kind:'list_page', listType, page: Math.min(lastPage, page+1) })`, disabled when `page >= lastPage`
- Both buttons use Secondary style

## Verification Gates

- vitest run: 212 tests passed (16 test files)
- tsc --noEmit: clean
- eslint .: clean

## Deviations from Plan

None — plan executed exactly as written.

## TDD Gate Compliance

- RED gate commit: b0d3de3 `test(12-02): add failing tests for list_page customId + pagination buttons`
- GREEN gate commit: fab3e3e `feat(12-02): list_page customId + Prev/Next pagination buttons`
- REFACTOR: not needed — implementation was clean on first pass

## Self-Check: PASSED

- apps/bot/src/lib/customIds.ts: FOUND
- apps/bot/src/lib/buttons.ts: FOUND
- apps/bot/tests/lib/customIds.test.ts: FOUND
- apps/bot/tests/lib/buttons.test.ts: FOUND
- RED commit b0d3de3: FOUND
- GREEN commit fab3e3e: FOUND
