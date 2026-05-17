---
phase: 05-discord-bot-v1
plan: 10
subsystem: discord-bot
tags: [wave-9, embeds, buttons, modal-submit, rsvp, components, interactionCreate]
dependency_graph:
  requires: [05-08-complete, 05-09-complete]
  provides:
    - apps/bot/src/lib/embeds.ts
    - apps/bot/src/lib/buttons.ts
    - apps/bot/src/components/index.ts
    - apps/bot/src/components/rsvpButton.ts
    - apps/bot/src/components/signupModal.ts
    - "matchCard / clanCard / profileCard — canonical EmbedBuilder factories (RESEARCH Example 3)"
    - "openSignupModalButton / signupRoleButton / leaveRoleButton / rsvpButtons — ButtonBuilder factories"
    - "handleButton / handleModalSubmit — component dispatcher (apps/bot/src/components/index.ts)"
    - "buildSignupModal(matchId) — shared modal factory consumed by /match signup AND match_open_signup_modal button variant"
  affects: [05-11, 05-12, 05-13]
tech_stack:
  added:
    - "discord.js@^14.26 EmbedBuilder.setColor / setTitle / setDescription / addFields / setFooter"
    - "discord.js@^14.26 ActionRowBuilder<ButtonBuilder> + ButtonBuilder.setStyle(Success/Primary/Secondary)"
    - "discord.js@^14.26 ModalBuilder + TextInputBuilder + ModalSubmitInteraction"
    - "Discord <t:UNIX_TS:F> timestamp tag (auto-localizes to viewing user's timezone)"
  patterns:
    - "Discord field-limit slicing at build time (title 256, description 2000, field value 1024) — T-05-10-02 mitigation"
    - "Conditional field-add for nullable DTO fields — null/undefined entries skipped rather than rendered as '—' or empty strings"
    - "Single source of truth for the signup modal: buildSignupModal(matchId) is consumed by /match signup slash command AND by match_open_signup_modal button variant"
    - "Split button-routing at the dispatcher level: customId 'm:o:' prefix skips pre-defer (showModal must be INITIAL response per Pitfall 1 corollary); everything else gets the Pitfall 11 deferReply"
    - "translateError substring-match on err.message — apps/bot/src/services/api.ts (plan 05-08) interpolates the full JSON body into Error.message; structured JSON parse deferred to plan 05-12"
    - "encodeButtonId('m:o:<matchId>') reused for the modal customId — round-trippable scheme via decodeButtonId on submit"
key_files:
  created:
    - "apps/bot/src/lib/embeds.ts"
    - "apps/bot/src/lib/buttons.ts"
    - "apps/bot/src/components/index.ts"
    - "apps/bot/src/components/rsvpButton.ts"
    - "apps/bot/src/components/signupModal.ts"
  modified:
    - "apps/bot/src/events/interactionCreate.ts"
    - "apps/bot/src/commands/match.ts"
    - "apps/bot/tests/lib/embeds.test.ts"
    - "apps/bot/tests/components/rsvpButton.test.ts"
    - "apps/bot/tests/components/signupModal.test.ts"
decisions:
  - "D-05-10-A: matchCard renders ONLY the scalar fields that the actual spatie/laravel-data PublicMatchData DTO projects (id, game_match_type_id, host_clan_id, status, scheduled_at, title.en, description.en). The plan's <interfaces> block referenced nested fields (host_clan.name, game_match_type.display_name.en, slots[]) that are NOT in the DTO. Rationale: ship with the contract that actually exists; Phase 6+ can eager-load + extend the DTO to enrich the card without a breaking embed-shape change."
  - "D-05-10-B: Modal customId reuses encodeButtonId({kind: 'match_open_signup_modal', matchId}) on both the slash command (/match signup) AND the button (match_open_signup_modal variant) sides. Rationale: single source of truth + round-trippable via decodeButtonId in the submit handler. Avoids divergence between two encode schemes."
  - "D-05-10-C: buildSignupModal(matchId) is exported from apps/bot/src/components/signupModal.ts (NOT lib/buttons.ts) so the SUBMIT handler module also owns the BUILD path — locality of cohesion. Both /match signup (apps/bot/src/commands/match.ts) and the match_open_signup_modal button (apps/bot/src/components/rsvpButton.ts) import buildSignupModal from the same module that handles submission."
  - "D-05-10-D: translateError is exported from rsvpButton.ts and imported by signupModal.ts (single implementation across both surfaces). Substring-match on err.message is sufficient for v1 — the four error codes (match_not_open / capacity_full / tag_restricted / already_signed_up) are documented in apps/web/lang/en/bot.php and emitted by apps/web/app/Http/Controllers/Api/Bot/BotApiMatchSignupController.php (plan 05-04). Structured JSON.parse(message.split(':').slice(-1)[0]) is deferred to plan 05-12 polish; the user-visible behaviour is identical."
  - "D-05-10-E: interactionCreate.ts button branch now PEEKS at customId.startsWith('m:o:') to decide pre-defer. Rationale: showModal MUST be the INITIAL response (Pitfall 1 corollary; discord.js v14 refuses showModal on a deferred interaction). Alternative considered: have rsvpButton.handle return a flag indicating 'will showModal' — rejected as more complex than the prefix peek which is O(1) and free of new contracts."
  - "D-05-10-F: matchCard adds defensive guards on m.game_match_type_id (typeof check + non-empty) because the existing /match info test mock (plan 05-09) ships a partial PublicMatchData object without the field. Without the guard, .slice() throws. Rule 1 fix — the production /api/bot/matches/{id} endpoint always returns the field, but defensive rendering is cheap correctness insurance."
metrics:
  duration_seconds: 472
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 10
---

# Phase 5 Plan 10: Wave 9 — Bot Embeds + RSVP Button + Signup Modal

Wave 9 lights up the visual / UX layer of the bot. Plain-text slash command
responses from Wave 7 are replaced by `EmbedBuilder` cards with status-coloured
borders, Discord-localised timestamps, and a single Sign-up button on open
matches. The button + modal-submit dispatcher wires up the SC-2 signup flow
end-to-end on the Discord side: clicking Sign up pops a role-input modal,
the submit handler POSTs to `/api/bot/matches/{id}/signups`, and the typed
errors from plan 05-04's `MatchSignupController` are translated to friendly
user copy.

A small but consequential dispatcher refactor lands here: button interactions
that **open a modal** must NOT be pre-deferred (Pitfall 1 corollary), so the
button branch in `events/interactionCreate.ts` now peeks at the customId
prefix and routes modal-openers (`m:o:...`) without the usual `deferReply`
call.

## Embed Shape Tables

### matchCard

| Field         | Source                                     | Limit            | Notes                                                              |
| ------------- | ------------------------------------------ | ---------------- | ------------------------------------------------------------------ |
| color         | `statusColor(m.status)`                    | 24-bit int       | green=open, amber=locked, blue=played, red=cancelled, gray=draft   |
| title         | `m.title?.en ?? "Match <id>"`              | 256              | sliced before set                                                  |
| description   | `m.description?.en`                        | 2000             | omitted if empty                                                   |
| Status field  | `m.status`                                 | 1024 (inline)    | always present                                                     |
| Scheduled     | `<t:UNIX_TS:F>`                            | 1024 (inline)    | omitted if scheduled_at null/empty; auto-localises to viewer's tz  |
| Host clan     | `m.host_clan_id`                           | 1024 (inline)    | omitted if null                                                    |
| Match type    | `m.game_match_type_id`                     | 1024 (block)     | omitted if undefined/empty (Rule 1 defensive guard, D-05-10-F)     |
| footer        | `Match id: <m.id>`                         | 2048             |                                                                    |
| components    | `openSignupModalButton(m.id)` ActionRow    | 1 row × 1 button | ONLY when `status === 'open'`                                      |

### clanCard

| Field         | Source                          | Limit         | Notes                                |
| ------------- | ------------------------------- | ------------- | ------------------------------------ |
| color         | `0x0078d4` (brand blue)         | int           | fixed                                |
| title         | `c.name ?? c.slug`              | 256           |                                      |
| description   | `c.description?.en`             | 2000          | omitted if empty                     |
| Tag field     | `c.tag`                         | 1024 (inline) | always present                       |
| Slug          | `c.slug`                        | 1024 (inline) | always present                       |
| Members       | `c.active_member_count`         | 1024 (inline) | always present                       |
| Tags          | `c.tags[].slug` backtick-joined | 1024 (block)  | omitted if c.tags is empty           |
| footer        | `Clan id: <c.id>`               | 2048          |                                      |

### profileCard

| Field         | Source                | Limit          | Notes                                                              |
| ------------- | --------------------- | -------------- | ------------------------------------------------------------------ |
| color         | `0x666666` (gray)     | int            | fixed                                                              |
| title         | `Player: <p.slug>`    | 256            |                                                                    |
| Display name  | `p.displayName`       | 1024 (inline)  | always present                                                     |
| Country       | `p.countryCode`       | 1024 (inline)  | omitted if null                                                    |
| Discord       | `p.discordTag`        | 1024 (inline)  | omitted if undefined/null (privacy gate ran upstream)              |
| Bio           | `p.bio.en`            | 1024 (block)   | omitted if undefined/null/empty                                    |
| footer        | `Player id: <p.id>`   | 2048           |                                                                    |

## Button Variants

| Helper                       | customId scheme              | Style       | Used by                                                    |
| ---------------------------- | ---------------------------- | ----------- | ---------------------------------------------------------- |
| `openSignupModalButton(mid)` | `m:o:<mid>`                  | Success     | `matchCard()` when status='open'                           |
| `signupRoleButton(...)`      | `m:s:<mid>:<rid>`            | Primary     | Reserved for direct-button flows (NOT used in v1 matchCard) |
| `leaveRoleButton(...)`       | `m:l:<mid>:<rid>`            | Secondary   | Reserved for direct-button flows                            |
| `rsvpButtons(...)` (combo)   | both above in one ActionRow  | —           | Reserved for direct-button flows                            |

v1 matchCard ships ONE Sign-up button. The 15-role HLL cap (Scrim 50v50)
exceeds Discord's 25-button-per-message limit once Sign-up + Leave pairs are
factored in; the single-modal flow sidesteps the limit. Plan 05-12 polish may
introduce a `StringSelectMenu` pre-populated from the match's role list,
replacing the role-UUID text input.

## Component Dispatcher Routing

Located at `apps/bot/src/components/index.ts`; invoked by
`events/interactionCreate.ts`.

| Interaction type     | Dispatcher entry         | Routing branch                                                  |
| -------------------- | ------------------------ | --------------------------------------------------------------- |
| `ButtonInteraction`  | `handleButton`           | -> `rsvpButton.handle` -> `decodeButtonId` per-kind dispatch    |
| `ModalSubmitInteraction` | `handleModalSubmit`  | customId prefix `m:o` -> `signupModal.handle`; else "Unknown modal type." |

### rsvpButton sub-routing (within `handleButton`)

| `decodeButtonId.kind`         | Action                                                              |
| ----------------------------- | ------------------------------------------------------------------- |
| `match_open_signup_modal`     | `interaction.showModal(buildSignupModal(matchId))` — INITIAL response |
| `match_signup`                | `api.post('/matches/{mid}/signups', {game_role_id})` + editReply    |
| `match_leave`                 | `api.delete('/matches/{mid}/signups/{rid}')` + editReply            |
| `clan_apply`                  | editReply 'Clan applications are managed on the website.' (v1 stub) |
| `null` (malformed)            | editReply 'Unknown button.' (deferred) or reply ephemeral (undeferred) |

### Error translation (`translateError`)

| Substring in err.message | User-facing copy                                              |
| ------------------------ | ------------------------------------------------------------- |
| `match_not_open`         | "This match is not open for signups."                         |
| `capacity_full`          | "This role is full."                                          |
| `tag_restricted`         | "Your clan tags are not permitted on this match."             |
| `already_signed_up`      | "You are already signed up to this match."                    |
| (anything else)          | "Failed: <scrubbed message, first 200 chars>"                 |

## interactionCreate Refactor: Split Button Routing

Before (plan 05-09):

```ts
if (interaction.isButton() || interaction.isStringSelectMenu()) {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });
    await interaction.editReply('Component handlers ship in plan 05-10.');
    return;
}
```

After (plan 05-10):

```ts
if (interaction.isButton()) {
    if (interaction.customId.startsWith('m:o:')) {
        await handleButton(interaction); // -> showModal INITIAL response
        return;
    }
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });
    await handleButton(interaction);
    return;
}
if (interaction.isStringSelectMenu()) {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });
    await interaction.editReply('Select-menu handlers are not yet wired.');
    return;
}
```

Why split: `discord.js@14` refuses `interaction.showModal()` on a deferred
or replied interaction (Pitfall 1 corollary). The customId prefix `m:o:` is
the encodeButtonId scheme for `match_open_signup_modal` — the only button
variant that opens a modal in v1.

## match.ts Embed Upgrade

| Subcommand     | Wave 7 (plan 05-09)                              | Wave 9 (plan 05-10)                                                |
| -------------- | ------------------------------------------------ | ------------------------------------------------------------------ |
| `/match list`  | `editReply(formatMatchList(matches))` (plain)    | top-5 `matchCard(m).embeds` flat-mapped; empty list still emits 'No open matches.' so existing test assertion stays green |
| `/match info`  | `editReply(formatMatchInfo(m))` (plain)          | `editReply(matchCard(m))` — `{embeds, components}` shape           |
| `/match signup`| `interaction.showModal(<inline ModalBuilder>)`   | `interaction.showModal(buildSignupModal(matchId))` — shared factory |
| `/match leave` | unchanged                                        | unchanged                                                          |

The `buildSignupModal` import collapses the inline ModalBuilder from
plan 05-09; both `/match signup` and the button match_open_signup_modal
variant now construct the same modal from the same factory.

## Test Outcomes

### Vitest Baseline Drop

| Wave      | Test files | Passed | Todo | Skipped |
| --------- | ---------- | ------ | ---- | ------- |
| Wave 0    | 10         | 24     | 20   | 5       |
| After 05-09 | 10       | 51     | 12   | 5       |
| **After 05-10 (this plan)** | **10** | **98** | **5** | **2** |

Net delta this plan: **+47 tests** across 3 newly-GREEN files:

- `apps/bot/tests/lib/embeds.test.ts`: +20 assertions
- `apps/bot/tests/components/rsvpButton.test.ts`: +16 assertions
- `apps/bot/tests/components/signupModal.test.ts`: +11 assertions

Remaining Wave 0 stubs after this plan: 2 files × 5 todos
(`outbound.test.ts` plan 05-11 outbound worker; `guildMemberUpdate.test.ts`
plan 05-12 role sync).

### Coverage Highlights

| File                          | Coverage focus                                                              |
| ----------------------------- | --------------------------------------------------------------------------- |
| `embeds.test.ts`              | matchCard color/title/timestamp/status branches; clanCard tag rendering; profileCard privacy-gate field skipping; description slicing |
| `rsvpButton.test.ts`          | All 4 customId kinds; api.post/delete call args; 5 translateError mappings; Unknown button deferred vs undeferred reply path |
| `signupModal.test.ts`         | buildSignupModal customId + TextInput shape; happy path POST; UUID regex (malformed + empty); 4 error translations; bad customId fall-through |

## Threat Register Status

| Threat ID    | Status     | How                                                                                                       |
| ------------ | ---------- | --------------------------------------------------------------------------------------------------------- |
| T-05-10-01   | mitigated  | Backend MatchSignupController (plan 05-04) enforces D-010 row-lock + tag access + capacity                |
| T-05-10-02   | mitigated  | matchCard / clanCard / profileCard slice() guards at field-value-build time (1024) and description (2000) |
| T-05-10-03   | mitigated  | api.ts (plan 05-08) scrubs WEB_API_TOKEN BEFORE throw; translateError further redacts to friendly key-based message |
| T-05-10-04   | mitigated  | privacy gate ran upstream; profileCard skips undefined/null fields via conditional field-add              |
| T-05-10-05   | mitigated  | signupModal.handle UUID regex /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i validates shape before POST; backend uses Eloquent parameter binding |
| T-05-10-06   | accepted   | Backend AlreadySignedUpException catches the duplicate; bot shows the friendly message                    |
| T-05-10-07   | mitigated  | Embed text is rendered inside slash-command ephemeral replies (no @mention parsing); outbound worker (plan 05-11) will apply allowed_mentions: {parse: []} for defence-in-depth |
| T-05-10-08   | mitigated  | Backend `GameMatch::findOrFail` returns 404; api.ts throws; translateError shows generic 'Failed: …'      |

## Open Questions

### v1 ships role UUID via text input

The HLL game has up to 15 roles per match (Scrim 50v50). Discord's button
cap of 5×5=25 is technically large enough, but a busy UI with 15 buttons +
status indicators is impractical. v1 ships:

```
[Sign up button]
  -> modal pops
  -> user pastes role UUID (from /match info embed)
  -> submit -> POST /matches/{id}/signups
```

Plan 05-12 polish can replace the text input with a `StringSelectMenu`
pre-populated from the match's role list once the `PublicMatchData` DTO
eager-loads the slots/roles. This is a UX iteration, not a correctness
fix — the SC-2 contract (capacity / tag / idempotency) is enforced
server-side regardless of how the client picks the role.

### translateError substring matching vs structured JSON parse

The bot's `translateError` substring-matches on the `Error.message` because
`apps/bot/src/services/api.ts` (plan 05-08) builds the message via
template-string interpolation of the JSON response body. A structured
parse (`JSON.parse(message.split(': ').slice(-1)[0]).error`) would be more
robust but adds complexity to handle malformed JSON, truncated bodies, and
non-JSON 4xx responses. Plan 05-12 polish may revisit this; the v1
substring approach is correct for the 4 known error codes documented in
`apps/web/lang/en/bot.php` `bot.errors.*`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Plan `<interfaces>` references nested DTO fields that don't exist**

- **Found during:** Task 1
- **Issue:** Plan 05-10 `<interfaces>` block (lines 91-145) used `m.host_clan?.name`, `m.game_match_type?.display_name?.en`, `m.slots[]`, `slot.role?.display_name?.en`. The actual `apps/web/app/Data/PublicMatchData.php` ships ONLY the scalar projection (`host_clan_id`, `game_match_type_id`, no slots, no nested objects).
- **Fix:** Implemented matchCard against the contract that actually ships. Rendered host_clan_id, game_match_type_id, scheduled_at, status, title.en, description.en as scalar fields. The card is visually informative; Phase 6+ can enrich it by eager-loading the relations and extending the DTO.
- **Files modified:** `apps/bot/src/lib/embeds.ts`
- **Commit:** `68a89a4`
- **Decision recorded:** D-05-10-A

**2. [Rule 1 - Bug] matchCard crashed on partial PublicMatchData mock**

- **Found during:** Task 2 (test run)
- **Issue:** After upgrading `apps/bot/src/commands/match.ts /match info` from `formatMatchInfo` (plain text) to `matchCard(m)`, the existing `apps/bot/tests/commands/match.test.ts /match info` tests broke. The test mocks `api.get` to return a partial match object (no `game_match_type_id`); calling `.slice()` on undefined threw `TypeError`.
- **Fix:** Added defensive guard in `matchCard`: skip the 'Match type' field when `m.game_match_type_id` is not a non-empty string. The production `/api/bot/matches/{id}` endpoint always returns the field, but defensive rendering is cheap.
- **Files modified:** `apps/bot/src/lib/embeds.ts`
- **Commit:** `60bfdce`
- **Decision recorded:** D-05-10-F

### Auth gates

None. Tests use `vi.mock('../../src/services/api.js')` so the real Sanctum
client + env.ts requirements are bypassed at test time. Live Discord
manual smoke (button click + modal submit end-to-end against the dev
guild) is deferred to plan 05-13 operator smoke.

## Known Stubs

| Stub                                             | File                                          | Reason                                                                  | Resolution                                                |
| ------------------------------------------------ | --------------------------------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------- |
| `clan_apply` button -> redirect-to-web message   | `apps/bot/src/components/rsvpButton.ts`       | `/api/bot/clans/{slug}/applications` endpoint is RESEARCH Q2 future v1+ | Phase 6+ ships the endpoint; handler swaps to `api.post()` |
| String-select-menu placeholder                   | `apps/bot/src/events/interactionCreate.ts`    | No v1 emitters yet                                                       | Plan 05-12 may emit StringSelectMenu for role picking      |
| Match host_clan / game_match_type / slots rendered as IDs only | `apps/bot/src/lib/embeds.ts`        | spatie/laravel-data PublicMatchData ships scalar projection only         | Phase 6+ may extend DTO + eager-load relations             |

All stubs are tracked by downstream plans; none block SC-1 (embeds render),
SC-2 (modal submit -> signup via API), or SC-3 (RSVP button rendered on open
matches).

## Verification Output

```
$ docker compose run --rm --no-deps -v $PWD:/repo bot \
    sh -c "cd /repo/apps/bot && pnpm typecheck"
> tsc --noEmit
[clean]

$ docker compose run --rm --no-deps -v $PWD:/repo bot \
    sh -c "cd /repo/apps/bot && pnpm lint"
> eslint .
[clean]

$ docker compose run --rm --no-deps -v $PWD:/repo bot \
    sh -c "cd /repo/apps/bot && pnpm test"
✓ tests/lib/embeds.test.ts (20 tests)
✓ tests/components/rsvpButton.test.ts (16 tests)
✓ tests/components/signupModal.test.ts (11 tests)
✓ tests/lib/customIds.test.ts (22 tests)
✓ tests/commands/match.test.ts (13 tests)
✓ tests/commands/clan.test.ts (9 tests)
✓ tests/commands/profile.test.ts (5 tests)
✓ tests/skeleton.test.ts (2 tests)
Test Files  8 passed | 2 skipped (10)
Tests       98 passed | 5 todo (103)

$ grep -cE 'export function (matchCard|clanCard|profileCard)' apps/bot/src/lib/embeds.ts
3

$ grep -c 'showModal' apps/bot/src/components/rsvpButton.ts
2

$ grep -c 'handleModalSubmit\|handleButton' apps/bot/src/events/interactionCreate.ts
7
```

## Self-Check: PASSED

- [x] `apps/bot/src/lib/embeds.ts` exists (commit `68a89a4`)
- [x] `apps/bot/src/lib/buttons.ts` exists (commit `68a89a4`)
- [x] `apps/bot/src/components/index.ts` exists (commit `60bfdce`)
- [x] `apps/bot/src/components/rsvpButton.ts` exists (commit `60bfdce`)
- [x] `apps/bot/src/components/signupModal.ts` exists (commit `60bfdce`)
- [x] `apps/bot/src/events/interactionCreate.ts` modified (commit `60bfdce`) — split button routing, handleButton/handleModalSubmit wired
- [x] `apps/bot/src/commands/match.ts` modified (commit `60bfdce`) — matchCard embed upgrade + buildSignupModal reuse
- [x] `apps/bot/tests/lib/embeds.test.ts` GREEN — 20 assertions (commit `68a89a4`)
- [x] `apps/bot/tests/components/rsvpButton.test.ts` GREEN — 16 assertions (commit `fe847cd`)
- [x] `apps/bot/tests/components/signupModal.test.ts` GREEN — 11 assertions (commit `fe847cd`)
- [x] Commits `68a89a4`, `60bfdce`, `fe847cd` all present in `git log`
- [x] `pnpm typecheck` clean
- [x] `pnpm lint` clean
- [x] `pnpm test`: 98 passed | 5 todo | 0 failed (was 51 / 12 / 0 after plan 05-09)
