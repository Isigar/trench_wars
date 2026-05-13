---
phase: 05-discord-bot-v1
plan: 09
subsystem: discord-bot
tags: [wave-7, slash-commands, interactionCreate, deferReply, showModal, registerCommands]
dependency_graph:
  requires: [05-01-complete, 05-04-complete, 05-08-complete]
  provides:
    - apps/bot/src/commands/index.ts
    - apps/bot/src/commands/match.ts
    - apps/bot/src/commands/clan.ts
    - apps/bot/src/commands/profile.ts
    - apps/bot/src/commands/me.ts
    - apps/bot/src/services/registerCommands.ts
    - apps/bot/src/events/ready.ts
    - apps/bot/src/events/interactionCreate.ts
    - "commands Map<string, CommandModule> — discord.js Guide registry pattern"
    - "registerCommands() — guild-scoped slash command registration (Pattern 3)"
    - "registerReadyHandler(client) + registerInteractionHandler(client) — event wiring entry points (plan 05-11 will inject startOutboundWorker into the ready handler)"
  affects: [05-10, 05-11, 05-12, 05-13]
tech_stack:
  added:
    - "discord.js@^14.26 SlashCommandBuilder addSubcommand + addUserOption + addStringOption"
    - "discord.js@^14.26 ModalBuilder + TextInputBuilder + ActionRowBuilder (Pitfall 1 corollary — showModal as initial response)"
    - "discord.js@^14.26 REST + Routes.applicationGuildCommands (Pattern 3 — guild-scoped registration)"
    - "discord.js@^14.26 ChatInputCommandInteraction + Interaction type guards (isChatInputCommand / isModalSubmit / isButton / isStringSelectMenu / isRepliable)"
    - "MessageFlags.Ephemeral — replaces the deprecated `ephemeral: true` option (discord.js v14.18+ migration)"
  patterns:
    - "deferReply ephemeral as FIRST awaited statement in every non-modal handler (Pitfall 1 — 3s window mitigation)"
    - "showModal as INITIAL response in /match signup — no defer first (Pitfall 1 corollary; discord.js refuses showModal on deferred/replied interaction)"
    - "modal customId encoded via encodeButtonId({kind: 'match_open_signup_modal', matchId}) from plan 05-08 — links the signup slash command to the plan 05-10 modal submit handler via the canonical scheme"
    - "interactionCreate dispatcher branches on type guards; modal + button + string-select handlers all deferReply at the dispatcher level (Pitfall 11 — modal submit's 3s window restarts)"
    - "State-aware error fallback (isRepliable / !replied / deferred) in top-level dispatcher try/catch — Pitfall 1 corollary at the recovery path"
    - "Guild-scoped registration via Routes.applicationGuildCommands — instant propagation vs the 1h global delay (D-003 single league guild)"
    - "Idempotent registerCommands: rest.put atomically REPLACES the guild's command set; safe to re-run on every boot"
    - "actsAsDiscordId = interaction.user.id on every per-user api call — forwards to X-Bot-Acts-As-User header (SC-5 attribution)"
key_files:
  created:
    - "apps/bot/src/commands/index.ts"
    - "apps/bot/src/commands/match.ts"
    - "apps/bot/src/commands/clan.ts"
    - "apps/bot/src/commands/profile.ts"
    - "apps/bot/src/commands/me.ts"
    - "apps/bot/src/services/registerCommands.ts"
    - "apps/bot/src/events/ready.ts"
    - "apps/bot/src/events/interactionCreate.ts"
  modified:
    - "apps/bot/src/index.ts"
    - "apps/bot/tests/commands/match.test.ts"
    - "apps/bot/tests/commands/clan.test.ts"
    - "apps/bot/tests/commands/profile.test.ts"
decisions:
  - "D-05-09-A: /profile v1 ships as a redirect-to-web stub instead of calling /api/bot/users/me with actsAsDiscordId=targetUser.id. Rationale: routing acts-as to the SUBJECT user makes PlayerPrivacyGate see subject==viewer (own-profile bypass), which leaks all privacy tiers. The correct fix (viewer-aware /api/bot/users/by-discord/{id} endpoint) is deferred to plan 05-12. SC-1 satisfied: command registered + invocable + privacy-aware (the website's gate handles it)."
  - "D-05-09-B: /clan apply v1 ships as a redirect-to-web stub instead of calling api.post('/clans/{slug}/applications'). Rationale: per RESEARCH Open Question Q2, the /api/bot/clans/{id}/applications endpoint is documented as 'future v1+' — not in plan 05-04's route list. SC-1 satisfied: command registered + invocable + redirect message is privacy-aware (the website's gate handles application policy)."
  - "D-05-09-C: Modal customId scheme reuses encodeButtonId from plan 05-08 (m:o:<matchId>) rather than introducing a separate modal-id encoder. Rationale: customIds.ts already declares match_open_signup_modal as a discriminated union variant; using it here keeps the customId scheme single-sourced and round-trippable for plan 05-10's modal submit decoder."
  - "D-05-09-D: registerCommands().catch() in ready.ts logs the error but does NOT process.exit(1). Rationale: gateway connection is live; outbound worker (plan 05-11) is independent of slash command registration; an operator with a stale registration can fix + redeploy without disrupting bot uptime. T-05-09-06 mitigation."
  - "D-05-09-E: interactionCreate.ts deferReply for modal submit + button + string-select happens at the dispatcher level, NOT inside each per-component handler. Rationale: Pitfall 11 (modal 3s window restarts) is a structural concern; deferring at the dispatcher means future component handlers in plan 05-10 cannot accidentally forget. Per-handler logic in plan 05-10 will use editReply (interaction is already deferred)."
  - "D-05-09-F: CommandModule interface uses union type covering SlashCommandBuilder, SlashCommandOptionsOnlyBuilder, SlashCommandSubcommandsOnlyBuilder. Rationale: addSubcommand/addStringOption return narrowed builder types in discord.js@14; a single concrete type would force every command module to cast. The union accepts all three return shapes from SlashCommandBuilder chain methods."
metrics:
  duration_seconds: 353
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 12
---

# Phase 5 Plan 09: Wave 7 — Slash Command Surface

Wave 7 ships the four slash commands users invoke against the bot:
`/clan`, `/match`, `/profile`, `/me`. Each command defers reply ephemeral
within the 3s Discord interaction window (Pitfall 1), forwards the
invoking user's Discord id as `X-Bot-Acts-As-User` (SC-5 attribution),
and calls the Phase 5 plan 04 web API client to fetch privacy-aware
data. The `/match signup` subcommand is the structural exception — it
opens a modal as the INITIAL response (Pitfall 1 corollary). The
modal submit handler lands in plan 05-10.

A central `interactionCreate` dispatcher routes chat-input commands to
the `commands` Map, defers modal submit + button + string-select
interactions (Pitfall 11), and provides state-aware error recovery.

`registerCommands` runs once at boot inside the `ready` handler; it uses
`Routes.applicationGuildCommands` for instant propagation (D-003 single
league guild).

## Subcommand Table

| Command | Subcommand | Verb | Calls | actsAsDiscordId | Notes |
|---|---|---|---|---|---|
| `/clan` | `info <slug>` | GET | `api.get('/clans/{slug}')` | invoker | deferReply first |
| `/clan` | `list` | GET | `api.get('/clans')` | invoker | deferReply first |
| `/clan` | `apply <slug>` | — | none (v1 stub) | — | redirect-to-web message (D-05-09-B) |
| `/match` | `list` | GET | `api.get('/matches')` | invoker | deferReply first |
| `/match` | `info <id>` | GET | `api.get('/matches/{id}')` | invoker | deferReply first |
| `/match` | `signup <id>` | — | `interaction.showModal(...)` | — | NO defer (Pitfall 1 corollary); modal customId `m:o:<matchId>` |
| `/match` | `leave <id> <role>` | DELETE | `api.delete('/matches/{id}/signups/{role}')` | invoker | deferReply first |
| `/profile` | (single `<@user>` option) | — | none (v1 stub) | — | redirect-to-web message (D-05-09-A) |
| `/me` | (no options) | GET | `api.get('/users/me')` | invoker | deferReply first; own-profile bypass |

## deferReply Pattern Verification

`grep -c 'deferReply' apps/bot/src/commands/*.ts`:

```
me.ts: 2     (1 call + 1 doc reference)
profile.ts: 2 (1 call + 1 doc reference)
clan.ts: 2    (1 call + 1 doc reference)
match.ts: 2   (1 call + 1 doc reference)
index.ts: 0   (registry only)
```

Every non-modal command branch defers reply ephemeral as the FIRST
awaited statement. `/match signup` is the lone exception and is asserted
by `match.test.ts` to NOT call `deferReply` (Pitfall 1 corollary —
modals must be the INITIAL response).

## Signup Modal Flow

```text
User invokes /match signup id:<UUID>
       |
       v
match.ts execute()
       |
       +-- options.getSubcommand() === 'signup'
       |
       +-- interaction.showModal(
       |     new ModalBuilder()
       |       .setCustomId('m:o:<UUID>')   // plan 05-08 encodeButtonId
       |       .setTitle('Sign up to match')
       |       .addComponents(<role TextInput>)
       |   )
       |
       v
Discord renders the modal to the user (instant, no follow-up needed)
       |
       v
User submits the modal
       |
       v
interactionCreate.ts dispatcher
       |
       +-- isModalSubmit() === true
       +-- interaction.deferReply({flags: Ephemeral})  // Pitfall 11 — fresh 3s
       +-- "Modal submit handlers ship in plan 05-10." (placeholder)
```

Plan 05-10 will replace the placeholder editReply with
`signupModal.handleSubmit(interaction)` that decodes the customId via
`decodeButtonId` (plan 05-08), reads the role TextInput, and calls
`api.post('/matches/{id}/signups', {role_id})`.

## registerCommands: Guild-Scoped (Pattern 3)

```ts
await rest.put(
    Routes.applicationGuildCommands(env.DISCORD_APPLICATION_ID, env.DISCORD_GUILD_ID),
    { body },
);
```

NOT `applicationCommands` (global). Why:
- **Instant propagation**: guild-scoped commands update in <1s.
  Global commands take up to 1h (Discord CDN cache).
- **Single league guild** (D-003): no upside to global.
- **Idempotent**: `rest.put` atomically replaces the guild's command
  list; re-running on every boot is safe.

T-05-09-05 (wrong guild) mitigated: `env.DISCORD_GUILD_ID` is
fail-fast at module-load (plan 05-08 env.ts).

T-05-09-06 (registerCommands fails -> bot keeps running) mitigated:
`registerCommands().catch()` in ready.ts logs the error but does NOT
crash the bot; gateway + outbound worker stay alive.

## interactionCreate Dispatch Table

| Type guard | Routed to | deferReply at dispatcher? | Notes |
|---|---|---|---|
| `isChatInputCommand()` | `commands.get(name)?.execute(interaction)` | NO — per-command handler defers itself | unknown name -> ephemeral "Unknown command." |
| `isModalSubmit()` | placeholder editReply (plan 05-10) | YES — Pitfall 11 fresh 3s window | plan 05-10 wires `signupModal.handleSubmit` |
| `isButton()` | placeholder editReply (plan 05-10) | YES — Pitfall 11 fresh 3s window | plan 05-10 wires `decodeButtonId` routing |
| `isStringSelectMenu()` | placeholder editReply (plan 05-10) | YES — Pitfall 11 fresh 3s window | plan 05-10 wires the select-menu router |
| anything else | (fall through, no reply) | — | non-repliable interactions ignored |

State-aware error fallback in the top-level try/catch:
- `!isRepliable()` -> log only
- `!replied && !deferred` -> `reply({content: 'An error occurred.', ephemeral})`
- `deferred` -> `editReply('An error occurred.')`
- `.catch(() => {})` on the recovery path — Discord may have already
  closed the interaction window.

## Open Question Resolutions

### Q2 — `/clan apply` endpoint (deferred to Phase 6+)

RESEARCH flags `/api/bot/clans/{id}/applications` as "future v1+".
**Resolution (D-05-09-B)**: ship `/clan apply <slug>` as a slash command
stub that editReplies a redirect-to-web message. SC-1 satisfied: the
command is registered + invocable; the website's clan-application policy
gate enforces correctness. Phase 6+ will swap the redirect for
`api.post('/clans/{slug}/applications', {actsAsDiscordId})`.

### Q5 — `/profile` viewer-aware lookup (deferred to plan 05-12)

`/api/bot/users/me` + `X-Bot-Acts-As-User=targetUser.id` makes
`PlayerPrivacyGate` see `subject == viewer` (own-profile bypass) —
leaking all privacy tiers. WRONG.

**Resolution (D-05-09-A)**: ship `/profile <@user>` as a redirect-to-web
stub. Plan 05-12 will introduce
`/api/bot/users/by-discord/{discord_id}` accepting two identities:
- subject (in URL) — the user being inspected
- viewer (in `X-Bot-Acts-As-User`) — the user invoking the command

`PlayerPrivacyGate` then sees `subject != viewer` and applies the
correct tier filtering.

## Threat Register Status

| Threat | Status | How |
|---|---|---|
| T-05-09-01 (slash command spam) | mitigated | Discord per-user rate limits (5/sec) + plan 05-04's `throttle:bot,60,1` web-side |
| T-05-09-02 (forgot deferReply -> 3s expires) | mitigated | All 4 command files defer reply as first awaited statement (asserted by tests); dispatcher defers on modal/button/select paths |
| T-05-09-03 (modal flow loses signup data) | mitigated (partial) | interactionCreate ships placeholder editReply for modal submit; plan 05-10 completes the flow |
| T-05-09-04 (/profile leaks private fields) | mitigated | /profile ships v1 redirect-to-web stub; viewer-aware endpoint deferred to plan 05-12 |
| T-05-09-05 (wrong guild registration) | mitigated | env.DISCORD_GUILD_ID fail-fast at module load |
| T-05-09-06 (registerCommands fails) | mitigated | try/catch + console.error in ready.ts; bot stays alive |
| T-05-09-07 (err.message leaks token) | mitigated | api.ts (plan 05-08) scrubs `env.WEB_API_TOKEN` BEFORE throw |

## Vitest Baseline Impact

- **Before**: 24 passed | 20 todo across 10 test files
- **After**: 51 passed | 12 todo across 10 test files (3 files flipped
  from all-todo to all-green)
- **Net gain**: +27 tests across 3 newly-GREEN files
  (match.test.ts +13, clan.test.ts +9, profile.test.ts +5)
- Remaining Wave 0 stubs: 5 test files × 12 todos
  (rsvpButton, embeds, signupModal, outbound, guildMemberUpdate —
  targeted by plans 05-10 / 05-11 / 05-12)

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
Test Files  5 passed | 5 skipped (10)
Tests       51 passed | 12 todo (63)

$ grep -c 'deferReply' apps/bot/src/commands/*.ts
me.ts:2 / profile.ts:2 / clan.ts:2 / match.ts:2 (4 files; 4 call sites + 4 doc refs)

$ grep -c 'showModal' apps/bot/src/commands/match.ts
4 (1 call site + 3 doc references)
```

## Deviations from Plan

None required. The plan's `<interfaces>` block was sufficient to ship
each module verbatim. Two implementation decisions documented above
(D-05-09-A redirect-to-web for /profile; D-05-09-B redirect-to-web for
/clan apply) were pre-resolved in the plan's `<interfaces>` section
under "RECOMMENDATION for execution" — followed literally.

One small naming refinement: the `CommandModule` interface uses a
union of `SlashCommandOptionsOnlyBuilder | SlashCommandSubcommandsOnlyBuilder`
plus a structural `{ name: string; toJSON: () => unknown }` fallback
(D-05-09-F). The plan's example showed the structural shape only; the
union accepts discord.js@14's chain-narrowed builder types so
`/clan`, `/match` (subcommands) and `/profile` (options-only),
`/me` (no options) all type cleanly without a cast.

### Auth gates

None. Tests use `vi.mock('../../src/services/api.js')` so the real
Sanctum client + env.ts requirements are bypassed at test time. Live
Discord registration (the `make` target that would exercise
`Routes.applicationGuildCommands` end-to-end) is deferred to plan 05-13
operator smoke.

## Known Stubs

| Stub | File | Reason | Resolution |
|---|---|---|---|
| `/clan apply` redirect-to-web message | apps/bot/src/commands/clan.ts | `/api/bot/clans/{slug}/applications` endpoint not yet shipped (RESEARCH Q2 — "future v1+") | Phase 6+ ships the endpoint; this handler swaps to `api.post()` |
| `/profile <@user>` redirect-to-web message | apps/bot/src/commands/profile.ts | `/api/bot/users/by-discord/{id}` viewer-aware endpoint not yet shipped (RESEARCH Q5) | Plan 05-12 ships the endpoint; this handler swaps to `api.get()` |
| Modal submit placeholder editReply | apps/bot/src/events/interactionCreate.ts | Real submit handler ships in plan 05-10 | Plan 05-10 wires `signupModal.handleSubmit(interaction)` |
| Button + select-menu placeholder editReply | apps/bot/src/events/interactionCreate.ts | Real component routing ships in plan 05-10 | Plan 05-10 wires `decodeButtonId` routing |
| Plain-text formatMatchList / formatMatchInfo / formatClanInfo / formatClanList / formatMe | apps/bot/src/commands/*.ts | Embed builders ship in plan 05-10 | Plan 05-10 replaces each formatter with the corresponding `EmbedBuilder` (matchListEmbed, matchInfoEmbed, clanCard, profileCard) |

All stubs are tracked by downstream plans (05-10, 05-12, or
Phase 6+); none block SC-1 (slash commands invocable + defer-within-3s
+ privacy-aware responses).

## Self-Check: PASSED

- [x] `apps/bot/src/commands/index.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/commands/match.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/commands/clan.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/commands/profile.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/commands/me.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/services/registerCommands.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/events/ready.ts` exists (commit `acefa10`)
- [x] `apps/bot/src/events/interactionCreate.ts` exists (commit `8303b37`)
- [x] `apps/bot/src/index.ts` modified — replaces inline ClientReady stub with register*Handler calls (commit `8303b37`)
- [x] `apps/bot/tests/commands/match.test.ts` GREEN — 13 tests (commit `7ac710c`)
- [x] `apps/bot/tests/commands/clan.test.ts` GREEN — 9 tests (commit `7ac710c`)
- [x] `apps/bot/tests/commands/profile.test.ts` GREEN — 5 tests (commit `7ac710c`)
- [x] Commits `acefa10`, `8303b37`, `7ac710c` all present in `git log`
- [x] `pnpm typecheck` clean
- [x] `pnpm lint` clean
- [x] `pnpm test`: 51 passed | 12 todo | 0 failed (was 24/20/0)
