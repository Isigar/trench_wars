---
phase: 10-clan-applications
plan: "05"
subsystem: bot
tags: [bot, clan-applications, discord, api-post, translate-error]
dependency_graph:
  requires: [10-01, 10-03]
  provides: [live-clan-apply-slash, live-clan-apply-button, translateError-clan-codes]
  affects: [10-07]
tech_stack:
  added: []
  patterns: [api.post-with-actsAsDiscordId, translateError-substring-match, vitest-mock-resolved-value]
key_files:
  modified:
    - apps/bot/src/commands/clan.ts
    - apps/bot/src/components/rsvpButton.ts
    - apps/bot/tests/commands/clan.test.ts
    - apps/bot/tests/components/rsvpButton.test.ts
decisions:
  - "10-05-A: clan_apply button posts decoded.clanId (UUID) to /clans/{clanId}/applications — the web route is slug-bound ({clan:slug}); UUID path will be verified end-to-end in plan 10-07 (known_discrepancy option B)"
metrics:
  duration: 169s
  completed: "2026-06-04T08:57:40Z"
  tasks: 2
  files: 4
---

# Phase 10 Plan 05: Bot Clan Apply — Live API Calls Summary

**One-liner:** Flip bot `/clan apply` slash command and `clan_apply` button from redirect-to-web stubs to live `api.post('/clans/.../applications', {}, { actsAsDiscordId })`, extend `translateError` with 3 new clan error codes.

## Tasks Completed

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | /clan apply slash command → live api.post + test flip | d0ba5c9 | clan.ts, clan.test.ts |
| 2 | clan_apply button → live api.post + translateError extension + test flip | d0ba5c9 | rsvpButton.ts, rsvpButton.test.ts |

## What Was Built

### Task 1: /clan apply slash command
- Replaced the `if (sub === 'apply')` redirect-to-web stub body in `clan.ts` with a live `api.post('/clans/${slug}/applications', {}, { actsAsDiscordId: interaction.user.id })` call
- Added `import { translateError } from '../components/rsvpButton.js'` to `clan.ts` so translated errors surface correctly
- Success → `'Your application has been submitted.'`; error → `translateError(err)`
- Updated file-header comment to reflect live call (removed v1 redirect stub notes)
- Flipped the `/clan apply subcommand` describe block in `clan.test.ts` to 4 api.post assertions (defer-ephemeral-first, api.post called with slug path + actsAsDiscordId, success reply, clan_not_recruiting translated reply)

### Task 2: clan_apply button + translateError
- Replaced the `if (decoded.kind === 'clan_apply')` stub body in `rsvpButton.ts` with a live `api.post('/clans/${decoded.clanId}/applications', {}, { actsAsDiscordId: interaction.user.id })` call
- Added inline comment: "decoded.clanId is a UUID; the web route is slug-bound — flagged for end-to-end verification in plan 10-07"
- Extended `translateError` with 3 new branches before the `Failed:` fallthrough:
  - `clan_not_recruiting` → `'This clan is not accepting applications.'`
  - `already_in_clan` → `'You are already a member of a clan.'`
  - `duplicate_application` → `'You already have a pending application to this clan.'`
- Updated `translateError` doc-comment to list all 7 mapped codes
- Replaced `clan_apply (v1 redirect-to-web stub)` describe block in `rsvpButton.test.ts` with 3 live api.post cases
- Added 3 new `translateError` `it()` blocks for the clan codes

## Deviations from Plan

None — plan executed exactly as written. Tasks 1 and 2 were committed together (single atomic commit) because they are interdependent: the clan test's `clan_not_recruiting` error-reply assertion requires `translateError` to have the new mapping, and `translateError` lives in `rsvpButton.ts` (Task 2).

## Known Stubs

None — both stubs (slash command apply branch, clan_apply button branch) are now wired to live API calls.

## Cross-Plan Follow-up: UUID vs Slug Discrepancy (option B)

The `clan_apply` button's `decoded.clanId` is a UUID (encoded as `c:a:<clanId>`). The web route built in plan 10-03 is `POST /api/bot/clans/{clan:slug}/applications` (slug-bound). The button therefore posts a UUID to a slug-bound route — these will not match at runtime.

**Resolution chosen:** Option B (self-contained) — keep posting `decoded.clanId` from the button and flag it for plan 10-07 (phase-close verification). No production code currently creates a `clan_apply` button (only the decode path exists), so no live user flow is broken. The slash command path (`/clan apply <slug>`) is the shipping surface for CLAN-02 and is fully functional.

**Required follow-up in plan 10-07:** Verify end-to-end that either (a) the web route accepts UUID resolution OR (b) the button encoder switches from UUID to slug.

## Verification Gates

| Gate | Result |
|------|--------|
| `vitest run` (all 190 tests) | PASS |
| `vitest run commands/clan` (11 tests) | PASS |
| `vitest run components/rsvpButton` (20 tests) | PASS |
| `tsc --noEmit` | PASS (no output) |
| `eslint .` | PASS (no output) |

## Self-Check: PASSED

- `apps/bot/src/commands/clan.ts` — modified, contains `api.post`
- `apps/bot/src/components/rsvpButton.ts` — modified, contains `clan_not_recruiting`, `already_in_clan`, `duplicate_application`
- `apps/bot/tests/commands/clan.test.ts` — modified, no `not.toHaveBeenCalled` for apply
- `apps/bot/tests/components/rsvpButton.test.ts` — modified, contains `duplicate_application`
- Commit d0ba5c9 exists: confirmed
