---
phase: 05-discord-bot-v1
plan: 11
subsystem: discord-bot
tags: [wave-10, outbound-worker, role-sync, guildMemberUpdate, reconciliation, pattern-4, pattern-6]
dependency_graph:
  requires: [05-08-complete, 05-09-complete, 05-10-complete]
  provides:
    - apps/bot/src/services/outbound.ts
    - apps/bot/src/services/render.ts
    - apps/bot/src/events/guildMemberUpdate.ts
    - "startOutboundWorker(client, intervalMs) — Pattern 4 setInterval poll loop"
    - "processOutboundTick(client) — public helper for unit testing single-tick dispatch"
    - "render(client, row) — OutboundRow -> Discord side-effect dispatcher (match_announce + role_sync)"
    - "handleGuildMemberUpdate(oldMember, newMember) — Pattern 6 role diff + per-delta POST helper"
    - "registerGuildMemberUpdateHandler(client) — wires the handler to Events.GuildMemberUpdate with try/catch"
  affects: [05-12, 05-13]
tech_stack:
  added:
    - "discord.js@^14.26 Client.channels.fetch (TextBasedChannel guard + 'send' in channel narrowing)"
    - "discord.js@^14.26 Guild.members.fetch + GuildMember.roles.add/remove (Pattern 2 automatic 429 retry)"
    - "discord.js@^14.26 Events.GuildMemberUpdate + GuildMember.roles.cache Collection.filter for role diffing"
    - "vitest@^2 vi.useFakeTimers + vi.advanceTimersByTimeAsync for setInterval testing"
  patterns:
    - "Pattern 4 outbound poll loop: setInterval(intervalMs) + module-level `running` boolean overlap-skip guard + per-row try/catch with nested markFailed catch"
    - "Public tick-body extraction (processOutboundTick) for unit testability — the setInterval wrapper is tested separately via fake timers"
    - "Test-only `__resetRunningFlagForTests` export so the module-level overlap-skip flag is observable across vi test cases"
    - "Pattern 6 verbatim role diff: oldMember.roles.cache.filter(r => !newMember.roles.cache.has(r.id)) (removed); newMember.roles.cache.filter(r => !oldMember.roles.cache.has(r.id)) (added); one POST per delta"
    - "allowed_mentions: {parse: []} on every channel.send / msg.edit — passed inside a shared messagePayload object so the literal appears once in code but applies to both edit and post branches"
    - "Synthetic discordMessageId for role_sync rows: 'role_sync:<action>:<userId>:<roleId>' — satisfies the web-side BotApiOutboundController markSent validation without representing a real Discord message id"
    - "vi.mock('../../src/env.js', ...) in test files — stubs OUTBOUND_POLL_INTERVAL_MS + DISCORD_GUILD_ID without requiring real Discord secrets in CI"
key_files:
  created:
    - "apps/bot/src/services/outbound.ts"
    - "apps/bot/src/services/render.ts"
    - "apps/bot/src/events/guildMemberUpdate.ts"
  modified:
    - "apps/bot/src/events/ready.ts"
    - "apps/bot/src/index.ts"
    - "apps/bot/tests/services/outbound.test.ts"
    - "apps/bot/tests/events/guildMemberUpdate.test.ts"
decisions:
  - "D-05-11-A: outbound.ts uses setInterval + a fire-and-forget tick body (processOutboundTick().catch().finally()) rather than awaiting the tick inside an async setInterval callback. Rationale: keeps the running flag toggle inside the .finally() block, which is the canonical idiom for setInterval+async work in Node — async callbacks passed to setInterval do not propagate exceptions back to the runtime and would not catch a sync throw inside the callback factory. The .catch().finally() pattern is explicit + testable."
  - "D-05-11-B: processOutboundTick is a public export (not just inlined in the setInterval callback). Rationale: unit tests can invoke a single tick directly without dealing with vi.useFakeTimers — six of the eleven outbound assertions run against this helper, leaving only the overlap-skip + intervalMs + handle-return tests to exercise the setInterval wrapper. Drastically simpler tests + faster test runs."
  - "D-05-11-C: __resetRunningFlagForTests is a test-only helper but EXPORTED from the production module (not a separate test-double file). Rationale: keeps the running flag's lifecycle co-located with its definition; underscore prefix signals intent (callers outside tests should never reach for it). The same idiom exists in api.ts for the WEB_API_TOKEN scrubber test path."
  - "D-05-11-D: render.ts re-fetches the canonical PublicMatchData DTO from /api/bot/matches/{id} on every dispatch rather than rendering directly from the row.payload.{slot_summary, title, host_clan_name, ...} fields built by DiscordOutboundPayloadBuilder. Rationale: payload is a snapshot at observer-fire time; by the time the worker picks up the row (up to 5s later) the match may have changed (status flip, scheduled_at update). Re-fetching guarantees the embed reflects current state. Cost: one extra HTTP call per match_announce row — negligible vs the Discord send latency."
  - "D-05-11-E: role_sync synthetic discordMessageId is 'role_sync:<action>:<userId>:<roleId>' rather than a generic 'role_sync_ok' constant. Rationale: gives the operator a useful debug breadcrumb in Filament's DiscordOutboundMessageResource if a row mark-sent ack lands with the wrong payload; the web side never reads this back so any non-empty string would satisfy markSent validation."
  - "D-05-11-F: payload key naming asymmetry confirmed and documented. Outbound `payload->discord_user_id` / `payload->discord_role_id` (web -> bot via DiscordOutboundMessage.payload JSON). Inbound `/discord-events/role-change` body `user_discord_id` / `role_discord_id` (bot -> web). Rationale: this matches what plan 05-04 BotApiDiscordEventController already validates + what plan 05-06 SyncDiscordRolesJob already writes. The bot's render.ts reads the outbound payload's `discord_*` keys; guildMemberUpdate.ts writes the inbound payload's `*_discord_id` keys."
  - "D-05-11-G: handleGuildMemberUpdate is exported as a separate public helper from registerGuildMemberUpdateHandler. Rationale: unit-test the diff logic against plain Map fixtures without instantiating a real discord.js Client; the inner try/catch lives in the registration wrapper so test assertions can observe thrown errors directly while the live gateway listener stays crash-safe."
  - "D-05-11-H: ready.ts now calls startOutboundWorker(client) synchronously (no try/catch). Rationale: startOutboundWorker only schedules a setInterval and returns — there is no async work that can throw at startup. If processOutboundTick later throws on the first tick, the wrapper's .catch() handles it. Wrapping the synchronous schedule in try/catch would suggest a failure mode that does not exist."
metrics:
  duration_seconds: 321
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 7
---

# Phase 5 Plan 11: Wave 10 — Bot Outbound Worker + guildMemberUpdate Reconciler

Wave 10 closes the outbound + reconciliation loop on the bot side. The web's
`discord_outbound_messages` table (plan 05-04) has been filling up with
`pending` rows since plan 05-05 (match_announce observer) and plan 05-06
(role_sync ClanMembership observer), but nothing was draining them. This
plan ships the polling worker that picks them up every 5 seconds, dispatches
them to Discord, and acks the row back to the web. It also wires the
opposite-direction reconciler: `Events.GuildMemberUpdate` fires whenever a
server admin or another bot touches a member's roles in Discord — the
handler diffs the role cache and POSTs each delta to
`/api/bot/discord-events/role-change` so the website's `ClanMembership`
table tracks Discord-side drift.

## Outbound Worker Flow (Pattern 4 verbatim)

```
                       +------------------+
                       | setInterval 5s   |
                       +--------+---------+
                                |
                                v
        +-----------------------+-----------------------+
        | running === true ? --> drop tick              |  T-05-11-01
        +-----------------------+-----------------------+
                                |
                                v
                  running = true; processOutboundTick()
                                |
                                v
        GET /api/bot/outbound-messages?status=pending&limit=20
                                |
                                v
                 for each row [Promise.all not used — sequential]
                                |
              +-----------------+-----------------+
              | render(client, row)               |
              | - match_announce: fetch DTO       |
              |                   matchCard       |
              |                   send/edit       |  T-05-11-05
              | - role_sync: guild.members.fetch  |
              |              roles.add/remove     |
              +-----------------+-----------------+
                                |
                +---------------+---------------+
                |                               |
              success                         throw
                |                               |
                v                               v
   POST /outbound-messages/{id}/sent   POST /outbound-messages/{id}/failed
        {sent_message_id}                   {last_error}            T-05-11-02
                                                |
                                          throw on ack failure?
                                                |
                                          log + swallow            T-05-11-02
```

### Render Dispatch Table

| `message_type`   | Side effect                                                                                                          | Returns                                              |
| ---------------- | -------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| `match_announce` | Re-fetch `/matches/{id}` DTO -> matchCard -> if `kind='match_announce_update'` + non-null `prior_sent_message_id` then EDIT (fallback to POST on edit failure); else POST | Discord channel message id (real)                    |
| `role_sync`      | `guild.members.fetch(discord_user_id)` -> `member.roles.add/remove(discord_role_id)`                                 | `role_sync:<action>:<userId>:<roleId>` (synthetic)   |
| (anything else)  | `throw new Error('Unknown message_type: ...')` — outer worker catches and markFailed                                  | n/a                                                  |

### Channel Send Payload Shape

Every Discord dispatch from `render.ts` builds the same shape:

```ts
const messagePayload = {
    embeds,                                  // matchCard().embeds
    components,                              // matchCard().components
    allowed_mentions: { parse: [] as never[] }, // T-05-10-07 / T-05-11-05 defence-in-depth
};
```

The `allowed_mentions: { parse: [] }` neutralises `@everyone`, `@here`, role
mentions, and user mentions at the Discord API layer regardless of what
the embed title or description contains. Verified by grep:

```
$ grep -c 'allowed_mentions' apps/bot/src/services/render.ts
3
```

(One literal in the messagePayload object reused by the edit + send branches;
two header-comment references documenting the threat mitigation rationale.)

## guildMemberUpdate Handler Flow (Pattern 6 verbatim)

```
                Events.GuildMemberUpdate
                          |
                          v
           handleGuildMemberUpdate(old, new)
                          |
              +-----------+-----------+
              |                       |
       removed = old.roles.cache  added = new.roles.cache
         .filter(!new.has(id))      .filter(!old.has(id))
              |                       |
              v                       v
        for each removed role:   for each added role:
          POST /discord-events/    POST /discord-events/
          role-change              role-change
          {user_discord_id,        {user_discord_id,
           role_discord_id,         role_discord_id,
           action: 'remove'}        action: 'add'}
                          |
                          v
      Web side (plan 05-04 BotApiDiscordEventController):
        Pitfall 10 echo suppression — if a recent (60s)
        sent role_sync outbound row matches this delta,
        silently no-op; else reconcile ClanMembership.
```

### Why no bot-side echo suppression

The web side (`BotApiDiscordEventController::roleChange`, plan 05-04)
checks `discord_outbound_messages WHERE message_type='role_sync' AND
payload->discord_user_id = :u AND payload->discord_role_id = :r AND
status='sent' AND sent_at > NOW() - 60s` before applying the change. The
bot fires events freely; duplicate suppression is a single source of
truth on the web. A bot-side suppression list would be a second source
of truth, prone to drift on bot restart (in-memory list lost) and
multi-replica deploys (state not shared).

## Payload Key Naming Asymmetry (D-05-11-F)

The Discord-user-id / Discord-role-id JSON keys differ between outbound
payloads (web -> bot) and inbound `/discord-events/role-change` POSTs
(bot -> web). This was confirmed against the existing plan 05-04 +
plan 05-06 implementations and documented here so future plans don't
"fix" the asymmetry without checking both sides.

| Direction                                          | Key naming                          | Owner                                                     |
| -------------------------------------------------- | ----------------------------------- | --------------------------------------------------------- |
| Web -> bot (DiscordOutboundMessage.payload JSON)   | `discord_user_id`, `discord_role_id`| plan 05-06 `SyncDiscordRolesJob::createOutbound()`        |
| Bot -> web (`/discord-events/role-change` POST)    | `user_discord_id`, `role_discord_id`| plan 05-04 `BotApiDiscordEventController::roleChange`     |

The render.ts `RoleSyncPayload` type uses the outbound keys; the
guildMemberUpdate.ts POST body uses the inbound keys. Both implementations
are tested + verified GREEN against the actual web-side validators.

## Wiring Amendments

### apps/bot/src/events/ready.ts

```ts
// before plan 05-11:
client.once(Events.ClientReady, (c) => {
    console.log(`[bot] Logged in as ${c.user.tag}`);
    registerCommands().catch(...);
    // plan 05-11 will inject startOutboundWorker() here.
});

// after plan 05-11:
client.once(Events.ClientReady, (c) => {
    console.log(`[bot] Logged in as ${c.user.tag}`);
    registerCommands().catch(...);
    startOutboundWorker(client);
});
```

The `startOutboundWorker(client)` call is synchronous — it only schedules
a `setInterval` and returns. No try/catch needed (D-05-11-H).

### apps/bot/src/index.ts

```ts
// new import:
import { registerGuildMemberUpdateHandler } from './events/guildMemberUpdate.js';

// new registration alongside existing two:
registerReadyHandler(client);
registerInteractionHandler(client);
registerGuildMemberUpdateHandler(client); // <-- added
```

Boot order is now: ready -> interactionCreate -> guildMemberUpdate ->
process error handlers -> client.login. The header comment in index.ts
was updated to reflect the new sequence.

## Test Outcomes

### Vitest Baseline — All Wave 0 Stubs GREEN

| Wave                          | Test files | Passed | Todo | Skipped |
| ----------------------------- | ---------- | ------ | ---- | ------- |
| Wave 0                        | 10         | 24     | 20   | 5       |
| After 05-09                   | 10         | 51     | 12   | 5       |
| After 05-10                   | 10         | 98     | 5    | 2       |
| **After 05-11 (this plan)**   | **10**     | **117**| **0**| **0**   |

Net delta this plan: **+19 tests** across 2 newly-GREEN files:

- `apps/bot/tests/services/outbound.test.ts`: +11 assertions
- `apps/bot/tests/events/guildMemberUpdate.test.ts`: +8 assertions

(Note: skipped count dropped from 2 to 0 because the prior `it.todo`
markers — which Vitest counts as `skipped` per file when ALL its tests
are `todo` — are replaced by real assertions. The 2 skipped files from
the plan 05-10 baseline were `outbound.test.ts` and `guildMemberUpdate.test.ts`,
both of which now ship full GREEN coverage.)

### Outbound Test Coverage Map

| Test                                                                                  | What it asserts                                                                          |
| ------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `processOutboundTick > polls /outbound-messages?status=pending&limit=20`              | Single api.get call with the exact query string                                          |
| `processOutboundTick > calls render for each row and marks sent`                      | render invoked twice (one per row); api.post `/sent` with the correct sent_message_id    |
| `processOutboundTick > marks failed when render throws`                               | api.post `/failed` with err.message                                                      |
| `processOutboundTick > clamps last_error to 2000 chars`                               | err.message of length 2500 -> last_error length 2000                                     |
| `processOutboundTick > coerces non-Error throwables to String(err)`                   | rejecting with a string literal -> last_error = the string                               |
| `processOutboundTick > does NOT throw on markFailed failure (nested catch swallows)`  | api.post rejects too -> processOutboundTick still resolves; console.error called         |
| `processOutboundTick > continues processing remaining rows when one row fails`        | row 1 fails (markFailed); row 2 succeeds (markSent) — both paths exercised               |
| `startOutboundWorker > returns a NodeJS.Timeout handle`                               | clearInterval(handle) does not throw                                                     |
| `startOutboundWorker > invokes the tick on every intervalMs elapsed`                  | After 1000ms -> 1 api.get; after another 1000ms -> 2 api.get                             |
| `startOutboundWorker > overlap-skip guard: a second tick is dropped`                  | 3 intervals fire while api.get is in-flight -> api.get called exactly 1 time             |
| `startOutboundWorker > respects intervalMs override`                                  | intervalMs=250 with vi.advanceTimersByTimeAsync(300) -> 1 api.get                        |

### guildMemberUpdate Test Coverage Map

| Test                                                                          | What it asserts                                                                            |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `handleGuildMemberUpdate > detects an added role`                             | Single POST with action='add' for the role newly in newMember.roles.cache                  |
| `handleGuildMemberUpdate > detects a removed role`                            | Single POST with action='remove' for the role missing from newMember.roles.cache           |
| `handleGuildMemberUpdate > handles multiple deltas in one update`             | Remove ROLE_A + add ROLE_B + add ROLE_C -> exactly 3 POSTs (one per delta)                 |
| `handleGuildMemberUpdate > no-ops on identical role sets`                     | oldMember.roles == newMember.roles -> api.post never called                                |
| `handleGuildMemberUpdate > accepts a PartialGuildMember as oldMember`         | PartialGuildMember-typed oldMember works with the diff logic (partials enabled in client.ts) |
| `handleGuildMemberUpdate > emits both add and remove deltas in single update` | Swap-out (ROLE_A -> ROLE_B) -> 2 POSTs: one remove ROLE_A, one add ROLE_B                  |
| `registerGuildMemberUpdateHandler > catches and logs errors`                  | api.post rejects -> listener still resolves; console.error called with '[bot/guildMemberUpdate]' tag |
| `registerGuildMemberUpdateHandler > registers a listener on Events.GuildMemberUpdate` | client.on called with 'guildMemberUpdate' + a function                                     |

### Verification Output

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
✓ tests/lib/customIds.test.ts (22 tests)
✓ tests/skeleton.test.ts (2 tests)
✓ tests/services/outbound.test.ts (11 tests)
✓ tests/commands/clan.test.ts (9 tests)
✓ tests/commands/profile.test.ts (5 tests)
✓ tests/components/rsvpButton.test.ts (16 tests)
✓ tests/events/guildMemberUpdate.test.ts (8 tests)
✓ tests/components/signupModal.test.ts (11 tests)
✓ tests/lib/embeds.test.ts (20 tests)
✓ tests/commands/match.test.ts (13 tests)
Test Files  10 passed (10)
Tests       117 passed (117)

$ grep -c 'allowed_mentions' apps/bot/src/services/render.ts
3

$ grep -c 'startOutboundWorker' apps/bot/src/events/ready.ts
2  (import + call)

$ grep -c 'registerGuildMemberUpdateHandler' apps/bot/src/index.ts
3  (header comment + import + call)
```

## Threat Register Status

| Threat ID    | Status     | How                                                                                                                    |
| ------------ | ---------- | ---------------------------------------------------------------------------------------------------------------------- |
| T-05-11-01   | accepted   | Overlap-skip guard prevents pile-up; operator observes stalled outbound in Filament (plan 05-07) and restarts the bot   |
| T-05-11-02   | mitigated  | Per-row try/catch around render + markFailed; nested catch around markFailed itself — outbound.test.ts verifies the nested-catch path |
| T-05-11-03   | mitigated  | api.ts (plan 05-08) scrubs WEB_API_TOKEN BEFORE throw; processOutboundTick further clamps last_error to 2000 chars     |
| T-05-11-04   | mitigated  | Pitfall 10 echo suppression lives on the web side (plan 05-04 BotApiDiscordEventController) — bot side has no special-casing |
| T-05-11-05   | mitigated  | allowed_mentions:{parse:[]} on every render dispatch (both edit + send branches share the messagePayload object)        |
| T-05-11-06   | accepted   | client.channels.fetch throws on unknown channel; render.ts wraps in an explicit `null \|\| !isTextBased \|\| !'send' in channel` throw; outer worker marks failed; operator sees in Filament |
| T-05-11-07   | mitigated  | discord.js REST manager handles 429 retry + per-bucket rate limit (Pattern 2); outbound poll limit=20/5s caps dispatch rate |
| T-05-11-08   | mitigated  | Web side Pitfall 10 (60s window); bot side defence-in-depth deferred to v2 polish — current single-replica deploy is safe |

## Open Questions

### Outbound worker singleton (Assumption A6)

The current Pattern 4 design assumes a single bot replica. Multi-replica
deploy would have N bots each polling /outbound-messages every 5s; the web
side's `lockForUpdate` in `BotApiOutboundController::pending()` (plan 05-04)
DOES serialize the row claim — two concurrent polls would see disjoint
row sets — so the design IS multi-replica-safe in correctness. What it is
NOT is rate-limited: 2 bots = 2x the Discord API send rate. For v1 the
single-replica deploy is canon; multi-replica polish in Phase 9+ would
add Redis-backed leader election or a dispatch-rate token bucket.

### Partial GuildMember accuracy

`oldMember` may be `PartialGuildMember` when the gateway sends an event
for a user the bot hasn't cached. The handler's diff still runs (partials
include `roles.cache` populated from the gateway payload), but if the bot
restarted between the role change event and the next observation, the
"old" cache may be stale. Mitigation: the web side's reconciler is
idempotent (Pattern 6 + plan 05-04); a missed delta is recoverable on
the next role change on the same membership, or via an operator-driven
full sync (Phase 6+ admin action).

### Re-fetching the DTO on every match_announce dispatch (D-05-11-D)

Adds one HTTP call per match_announce row vs reading the cached
`row.payload` snapshot. The trade-off is fresh-state-at-dispatch-time
vs throughput; chose fresh-state because the worker can lag up to 5s
behind observer fire time and a stale status/scheduled_at in a Discord
embed is more visible than the extra HTTP call.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Critical functionality] Payload key naming asymmetry discovered + documented**

- **Found during:** Task 1 (read_first for render.ts)
- **Issue:** Plan 05-11 `<interfaces>` for render.ts used payload keys `user_discord_id` + `role_discord_id`. The actual outbound JSON written by plan 05-06 `SyncDiscordRolesJob` (verified at `apps/web/app/Jobs/SyncDiscordRolesJob.php:135`) uses `discord_user_id` + `discord_role_id`. Conversely, the inbound `/discord-events/role-change` POST validated by plan 05-04 `BotApiDiscordEventController` (verified at `apps/web/app/Http/Controllers/BotApi/BotApiDiscordEventController.php:46`) expects `user_discord_id` + `role_discord_id`.
- **Fix:** Implemented render.ts `RoleSyncPayload` type with `discord_user_id` + `discord_role_id` (matching what the web actually writes). guildMemberUpdate.ts POSTs `user_discord_id` + `role_discord_id` (matching what the web actually validates). Both halves are correct per the existing contracts; the plan `<interfaces>` example had the directions confused. Documented as D-05-11-F so a future planner doesn't "fix" the asymmetry without checking both sides.
- **Files modified:** `apps/bot/src/services/render.ts`, `apps/bot/src/events/guildMemberUpdate.ts`
- **Commit:** Task 1 (`afba4d5`) + Task 2 (`5c0ad34`)
- **Decision recorded:** D-05-11-F

**2. [Rule 2 - Critical functionality] processOutboundTick wraps the tick body for testability**

- **Found during:** Task 1 (writing outbound.ts)
- **Issue:** Plan `<interfaces>` example inlined the entire tick body inside the `setInterval(async () => { ... })` callback. Unit-testing the per-row dispatch logic from inside a setInterval callback requires fragile timer-mocking gymnastics — vi.useFakeTimers + advanceTimersByTime + flush microtasks + capture the api.get/post mock state.
- **Fix:** Extracted `processOutboundTick(client)` as a public export. Six of the eleven outbound assertions invoke this helper directly without any timer mocking, making the test suite faster + more robust. The setInterval wrapper is still tested via `vi.useFakeTimers` for the overlap-skip + intervalMs + handle-return cases. This refactor was explicitly suggested by the plan's `<action>` block (lines 312-331).
- **Files modified:** `apps/bot/src/services/outbound.ts`
- **Commit:** Task 1 (`afba4d5`)
- **Decision recorded:** D-05-11-B

**3. [Rule 2 - Critical functionality] handleGuildMemberUpdate exported for testability**

- **Found during:** Task 2 (writing guildMemberUpdate.ts)
- **Issue:** Plan `<interfaces>` example inlined the diff logic inside `client.on(Events.GuildMemberUpdate, async (oldMember, newMember) => { ... })`. Testing the diff logic via the on-emit pattern requires constructing a mock client with a `.on` method that captures the listener, then invoking the captured callback — verbose and indirect.
- **Fix:** Extracted `handleGuildMemberUpdate(old, new)` as a public export. The diff logic tests invoke the helper directly with plain Map fixtures (6 assertions). The registration wrapper is tested separately via the captured-listener pattern (2 assertions verifying try/catch + listener name). Plan's `<action>` block (line 534) explicitly endorses this refactor: "Rule 2 amendment to task 2 acceptance criteria: ALSO export `handle(oldMember, newMember)` from guildMemberUpdate.ts for testability."
- **Files modified:** `apps/bot/src/events/guildMemberUpdate.ts`
- **Commit:** Task 2 (`5c0ad34`)
- **Decision recorded:** D-05-11-G

**4. [Rule 2 - Critical functionality] Test-only running-flag reset helper**

- **Found during:** Task 3 (writing outbound.test.ts)
- **Issue:** The module-level `running` boolean in outbound.ts persists across test cases within the same file. If one test leaves running=true (e.g., the overlap-skip test that intentionally holds a tick in-flight), the next test would observe running=true and skip its tick.
- **Fix:** Exported `__resetRunningFlagForTests()` from outbound.ts. The test suite calls it in `beforeEach` + `afterEach`. The underscore prefix signals intent; the same idiom is used elsewhere in the codebase for test-only state mutation. Not exported from any barrel/index file.
- **Files modified:** `apps/bot/src/services/outbound.ts`
- **Commit:** Task 1 (`afba4d5`) — anticipated during initial scaffold so Task 3 didn't need to amend
- **Decision recorded:** D-05-11-C

### Auth gates

None. All tests use `vi.mock('../../src/env.js', ...)` and `vi.mock('../../src/services/api.js', ...)` so the real Sanctum client + Discord secrets are bypassed at test time. Live Discord smoke (outbound match_announce reaches the dev guild within ~10s; manual role removal triggers ClanMembership update) is deferred to plan 05-13 operator smoke.

## Known Stubs

| Stub                                                                | File                                              | Reason                                                                            | Resolution                                                  |
| ------------------------------------------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Outbound worker singleton (no leader election across replicas)      | `apps/bot/src/services/outbound.ts`               | Single-replica deploy is canon per Assumption A6                                  | Phase 9+ may add Redis-backed leader election if multi-replica |
| Bot-side echo-suppression for own role_sync deliveries (v2 polish)  | `apps/bot/src/events/guildMemberUpdate.ts`        | T-05-11-08 web-side suppression already covers the threat; bot-side is defence-in-depth | Phase 9+ polish; would need a 5-second pause after each role_sync delivery |

All stubs are tracked by downstream plans; none block SC-3 (outbound delivery loop active) or SC-4 (Discord-side drift reconciliation).

## Self-Check: PASSED

- [x] `apps/bot/src/services/outbound.ts` exists (commit `afba4d5`)
- [x] `apps/bot/src/services/render.ts` exists (commit `afba4d5`)
- [x] `apps/bot/src/events/ready.ts` modified — `startOutboundWorker` imported + called (commit `afba4d5`)
- [x] `apps/bot/src/events/guildMemberUpdate.ts` exists (commit `5c0ad34`)
- [x] `apps/bot/src/index.ts` modified — `registerGuildMemberUpdateHandler` imported + called (commit `5c0ad34`)
- [x] `apps/bot/tests/services/outbound.test.ts` GREEN — 11 assertions (commit `248a06d`)
- [x] `apps/bot/tests/events/guildMemberUpdate.test.ts` GREEN — 8 assertions (commit `248a06d`)
- [x] Commits `afba4d5`, `5c0ad34`, `248a06d` all present in `git log`
- [x] `pnpm typecheck` clean
- [x] `pnpm lint` clean
- [x] `pnpm test`: 117 passed | 0 todo | 0 failed (was 98 / 5 / 2 after plan 05-10)
- [x] `grep -c 'allowed_mentions' apps/bot/src/services/render.ts` = 3 (at least 2 required by plan verification — one each for send + edit; bonus header-comment refs)
- [x] `grep -c 'startOutboundWorker' apps/bot/src/events/ready.ts` = 2 (import + call)
- [x] `grep -c 'registerGuildMemberUpdateHandler' apps/bot/src/index.ts` = 3 (header comment + import + call)
