---
phase: 05-discord-bot-v1
plan: 08
subsystem: discord-bot
tags: [wave-6, bot-core, env, discord-client, sanctum-client, customIds]
dependency_graph:
  requires: [05-01-complete, 05-04-complete, 05-07-complete]
  provides:
    - apps/bot/src/env.ts
    - apps/bot/src/client.ts
    - apps/bot/src/index.ts
    - apps/bot/src/services/api.ts
    - apps/bot/src/lib/customIds.ts
    - apps/bot/src/lib/colors.ts
    - apps/bot/src/types/apiContracts.ts
    - "encodeButtonId/decodeButtonId — round-trippable Discord component customIds (Pitfall 5)"
    - "api.request<T> — Sanctum bearer client with X-Bot-Acts-As-User + Pitfall 3 token scrub"
    - "createClient() — discord.js Client with GuildMembers privileged intent"
    - "statusColor(status) — GameMatch status -> Discord embed color int"
  affects: [05-09, 05-10, 05-11, 05-12, 05-13]
tech_stack:
  added:
    - "undici@^7 fetch + Headers — Web API HTTP client (over native fetch; gives Headers + better timeout/abort surface)"
    - "discord.js@^14.26 Client factory pattern (GatewayIntentBits + Partials)"
    - "vitest@^2 (5 describe blocks / 22 tests in customIds.test.ts)"
  patterns:
    - "Fail-fast env validation idiom: required(key) throws at module-load if undefined/empty; optional(key, fallback) returns string (rcon-worker analog, T-05-08-02 mitigation)"
    - "Token scrubbing BEFORE throw: text.replaceAll(env.WEB_API_TOKEN, '[REDACTED]') prevents Railway log capture of the Sanctum plaintext token (T-05-08-01 / Pitfall 3)"
    - "Short-prefix Discord customId scheme (m:s:, m:l:, m:o:, c:a:) keeps worst-case two-UUID payload at 77 chars under the 100-char Discord cap (Pitfall 5)"
    - "decodeButtonId returns null on structural malformation — downstream router maps null to 'unknown button, ignore' rather than crashing the interaction (T-05-08-04)"
    - "Privileged intent declared at code level (GuildMembers); operator smoke confirms Server Members Intent ON in Discord Developer Portal (VALIDATION.md Pitfall 6)"
    - "Discriminated-union ButtonAction with `as const` switch encoder + parts-array decoder — Discord component routing in plain TypeScript, no router framework"
key_files:
  created:
    - "apps/bot/src/env.ts"
    - "apps/bot/src/client.ts"
    - "apps/bot/src/services/api.ts"
    - "apps/bot/src/lib/customIds.ts"
    - "apps/bot/src/lib/colors.ts"
    - "apps/bot/src/types/apiContracts.ts"
    - "apps/bot/.env.example"
  modified:
    - "apps/bot/src/index.ts"
    - "apps/bot/tests/lib/customIds.test.ts"
decisions:
  - "D-05-08-A: ESM import extensions are explicit (.js) on local module imports. tsconfig.base.json sets module=NodeNext + moduleResolution=NodeNext (Phase 1 plan 01-01). Node 22 ESM requires explicit extensions for relative imports; tsc rewrites .ts -> .js at emit time. Pattern matches the rcon-worker precedent."
  - "D-05-08-B: clan_apply with empty clanId (decodeButtonId('c:a:')) returns a `{ kind: 'clan_apply', clanId: '' }` rather than null. The decoder enforces ONLY structural validity (segment count + prefix); UUID-shape validation is the caller's job (downstream handler in plan 05-10). Documented inline in the test (T-05-08-04 boundary case) so the contract is explicit."
  - "D-05-08-C: api.ts hard-codes the /api/bot prefix in request(). All bot API endpoints land under /api/bot/* per plan 05-04 routes; callers pass the suffix only (e.g., api.get('/matches/:id')). Cleaner than passing the prefix every call and matches the verbatim RESEARCH Example 1."
  - "D-05-08-D: Plan called for 8+ it() blocks; the GREEN test file ships 22 across 5 describe groups for crisper coverage: 4 encode + 4 decode + 4 round-trip + 6 malformed + 4 length-budget. Net gain +14 vs plan minimum."
metrics:
  duration_seconds: 366
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 9
---

# Phase 5 Plan 08: Wave 6 — Bot Core (env + Client + Web API + customIds)

Wave 6 lands the bot-side substrate every later Phase 5 plan depends on:
fail-fast env parsing, discord.js Client construction with privileged
intents, the undici-fetch Sanctum bearer API client (with token scrubbing
on errors), the round-trippable customId encode/decode pair (Pitfall 5
mitigation), the match-status-to-Discord-color mapping, and a re-export of
the @trenchwars/shared-types DTOs that the slash command handlers (05-09)
and the outbound worker (05-11) will consume.

## Acceptance Criteria

### Task 1 — `env.ts` + `client.ts` + `index.ts` + `.env.example` (commit `cf21568`)

- [x] `env.ts`:
  - exports `env` const with 5 required vars + 1 optional
    (`OUTBOUND_POLL_INTERVAL_MS` default `5000`)
  - `required()` throws `[bot/env] Missing required env var: ${key}` on
    undefined-or-empty (T-05-08-02 mitigation)
  - `optional()` returns string fallback when undefined-or-empty
- [x] `client.ts`:
  - `createClient()` returns `new Client({ intents, partials })`
  - intents: `Guilds | GuildMembers (privileged) | GuildModeration`
  - partials: `User | GuildMember | Channel`
  - Pitfall 6 comment block referencing portal-side toggle
- [x] `index.ts`:
  - replaces Phase 1 skeleton; ES module entry
  - `Events.ClientReady` log stub noting plan 05-09/05-11 deferred wiring
  - `process.on('uncaughtException' | 'unhandledRejection')` →
    `process.exit(1)`
  - top-level `await client.login(env.DISCORD_BOT_TOKEN)`
- [x] `.env.example`:
  - 6 keys + Discord Developer Portal comment header + privileged-intent
    reminder + empty values (Pitfall 3 — never commit token shapes)
- [x] `pnpm typecheck` clean; `pnpm lint` clean

### Task 2 — `api.ts` + `customIds.ts` + `colors.ts` + `apiContracts.ts` (commit `c497a8d`)

- [x] `services/api.ts`:
  - `api.request<T>(path, opts)` over undici `fetch` + `Headers`
  - Authorization: `Bearer ${env.WEB_API_TOKEN}` on every call
  - `Content-Type: application/json` when body present
  - Optional `actsAsDiscordId` → `X-Bot-Acts-As-User` header
  - Default method = GET (no body) or POST (body), overridable via
    `opts.method`
  - Non-OK response: read body text, `text.replaceAll(env.WEB_API_TOKEN,
    '[REDACTED]')`, throw `Error('Bot API METHOD PATH -> STATUS:
    SCRUBBED'.slice(0,500))` (T-05-08-01 / Pitfall 3)
  - `get<T>` / `post<T>` / `delete<T>` shortcuts via `Omit<CallOptions,
    'method'|'body'>`
- [x] `lib/customIds.ts`:
  - `ButtonAction` discriminated union (4 variants per `<interfaces>`)
  - `encodeButtonId(a)` switch on `a.kind` returning short-prefix string
  - `decodeButtonId(s)` parses with strict arity check; returns null on
    structural malformation (T-05-08-04 mitigation)
- [x] `lib/colors.ts`:
  - `statusColor(status: string): number` mapping 5 GameMatch statuses +
    default fallback
- [x] `types/apiContracts.ts`:
  - 18 type-only re-exports from `@trenchwars/shared-types` covering
    Phase 1-4 DTOs
  - `OutboundRow` interface (discord_outbound_messages row shape from
    plan 05-04 BotApiOutboundController::pending)
  - `BotApiErrorBody` interface (422 envelope from /api/bot/*)
- [x] `pnpm typecheck` clean; `pnpm lint` clean
- [x] `grep -c 'replaceAll(env.WEB_API_TOKEN' apps/bot/src/services/api.ts`
  = 2 (1 implementation + 1 doc reference)
- [x] `grep -c 'GuildMembers' apps/bot/src/client.ts` = 2 (1 intent + 1 doc
  reference)

### Task 3 — `customIds.test.ts` GREEN flip (commit `64711a4`)

- [x] Replaces 2 Wave 0 `it.todo` stubs with 22 real Vitest tests in 5
  describes:
  - 4 encode tests (one per variant)
  - 4 decode tests (one per variant)
  - 4 round-trip tests (encode → decode preserves ids)
  - 6 malformed-input tests (empty string, single colon, unknown prefix,
    wrong arity for match_signup, extra trailing segment for
    match_open_signup_modal, empty/missing clanId for clan_apply — D-05-08-B
    boundary documented inline)
  - 4 length-budget tests (Pitfall 5 — Discord 100-char cap); explicit
    assertion that two-UUID match_signup = 77 chars
- [x] `pnpm test` → 24 passed | 20 todo | 0 failed (was 2 passed | 22
  todo)
- [x] Vitest baseline drops by 22 net tests (1 test file flipped from
  pure-todo to GREEN; the 8 other Wave 0 stub files remain untouched)

## Interfaces Shipped

### `env.ts` — environment variable list

| Var | Kind | Default | Source |
|---|---|---|---|
| `DISCORD_BOT_TOKEN` | required | — | Discord Developer Portal → Bot → Reset Token |
| `DISCORD_APPLICATION_ID` | required | — | Discord Developer Portal → General Info → Application ID |
| `DISCORD_GUILD_ID` | required | — | Right-click guild in app (Developer Mode) → Copy Server ID |
| `WEB_API_URL` | required | — | docker-compose: `http://web-nginx`; Railway: web service public URL |
| `WEB_API_TOKEN` | required | — | `make artisan ARGS="trenchwars:bot:issue-token --name=bot-prod"` (plan 05-07) |
| `OUTBOUND_POLL_INTERVAL_MS` | optional | `5000` | bot internal — outbound worker poll cadence |

### `client.ts` — intent list

| Intent | Privileged | Purpose |
|---|---|---|
| `GatewayIntentBits.Guilds` | no | base guild events (channel/role/guild updates) |
| `GatewayIntentBits.GuildMembers` | **YES** | `guildMemberUpdate` (SC-4 role-sync reconciliation in plan 05-12); MUST be enabled in Discord Developer Portal |
| `GatewayIntentBits.GuildModeration` | no | audit-log-adjacent events; needed for future moderation surfaces |

Partials: `User`, `GuildMember`, `Channel` — required when discord.js delivers a payload referencing a not-yet-cached object (e.g., a member who joined while bot offline).

### `api.ts` — method signatures

```ts
api.request<T>(path: string, opts?: { actsAsDiscordId?: string; body?: unknown; method?: 'GET'|'POST'|'DELETE' }): Promise<T>
api.get<T>(path: string, opts?: { actsAsDiscordId?: string }): Promise<T>
api.post<T>(path: string, body: unknown, opts?: { actsAsDiscordId?: string }): Promise<T>
api.delete<T>(path: string, opts?: { actsAsDiscordId?: string }): Promise<T>
```

**Token scrub line (verbatim):**

```ts
const scrubbed = text.replaceAll(env.WEB_API_TOKEN, '[REDACTED]');
throw new Error(`Bot API ${method} ${path} -> ${res.status}: ${scrubbed.slice(0, 500)}`);
```

`String.prototype.replaceAll` is native on Node 22; no polyfill needed.

### `customIds.ts` — variant table

| Kind | Encoded shape | Worst-case length (UUID inputs) |
|---|---|---|
| `match_signup` | `m:s:<matchId>:<gameRoleId>` | 77 (`4 + 36 + 1 + 36`) |
| `match_leave` | `m:l:<matchId>:<gameRoleId>` | 77 |
| `match_open_signup_modal` | `m:o:<matchId>` | 40 (`4 + 36`) |
| `clan_apply` | `c:a:<clanId>` | 40 |

All under Discord's 100-char `customId` cap. The prefix scheme is robust to ID-length drift (could absorb up to ~30 additional bytes per id before bumping the cap).

### `apiContracts.ts` — shared-types DTO re-export list (18 total)

| Phase | Re-exports |
|---|---|
| 1 (identity) | `UserData`, `PlayerData`, `PlayerPrivacyData`, `PublicPlayerData` |
| 2 (clans) | `ClanData`, `ClanTagData`, `ClanMembershipData`, `ClanInviteData`, `ClanApplicationData` |
| 3 (game catalogue) | `GameData`, `GameRoleData`, `GameMatchTypeData`, `GameMatchTypeRoleLimitData` |
| 4 (matches) | `MatchData`, `MatchSlotData`, `MatchAccessRuleData`, `MatchResultData`, `MatchMvpData`, `EventData`, `PublicMatchData`, `PublicMatchOccupantData` |

Bot-specific shapes:
- `OutboundRow` — row shape from `/api/bot/outbound-messages?status=pending` (plan 05-04 BotApiOutboundController; consumed by plan 05-11 worker).
- `BotApiErrorBody` — 422 envelope from `/api/bot/*` (`{ error: 'capacity_full', message: 'i18n english' }`).

### `.env.example` — operator instructions (verbatim)

```
DISCORD_BOT_TOKEN=
DISCORD_APPLICATION_ID=
DISCORD_GUILD_ID=
WEB_API_URL=http://web-nginx
WEB_API_TOKEN=
OUTBOUND_POLL_INTERVAL_MS=5000
```

With a top comment block pointing to https://discord.com/developers/applications and reminding the operator to enable Privileged Gateway Intents → Server Members Intent (Pitfall 6).

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
Test Files  2 passed | 8 skipped (10)
Tests       24 passed | 20 todo (44)
```

## Threat Register Status

| Threat | Status | How |
|---|---|---|
| T-05-08-01 (token in error trace) | mitigated | `text.replaceAll(env.WEB_API_TOKEN, '[REDACTED]')` before throw in `api.ts` |
| T-05-08-02 (empty WEB_API_TOKEN silently fails) | mitigated | `required('WEB_API_TOKEN')` throws at module load |
| T-05-08-03 (forged customId) | accepted | Downstream signup endpoint enforces D-010 row-locked capacity + tag access; forged customId → 422, not overflow |
| T-05-08-04 (malformed customId crash) | mitigated | `decodeButtonId` returns null; tested via 6 malformed inputs |
| T-05-08-05 (wrong DISCORD_APPLICATION_ID) | mitigated | `required()` at boot; plan 05-09 registers commands against `applicationGuildCommands(applicationId, guildId)` — wrong appId = Discord 401 |
| T-05-08-06 (missing GuildMembers intent) | mitigated (code side) | Intent declared in `client.ts`; operator-side portal toggle deferred to VALIDATION.md smoke |
| T-05-08-07 (uncaughtException leaves outbound dispatching) | accepted | Container restart + Phase 9 stale-state admin cleanup handle it |

## Deviations from Plan

### Rule 3 — Blocking issue auto-fixed (environmental, no source change)

**1. `@trenchwars/shared-types` dist was stale (Phase 1 build only)**

- **Found during:** Task 2 verification (typecheck failed with 18× `TS2305 Module '"@trenchwars/shared-types"' has no exported member 'X'`)
- **Issue:** `packages/shared-types/dist/index.d.ts` only re-exported the 3 Phase 1 DTOs (`UserData`, `PlayerData`, `PlayerPrivacyData`). The source `src/index.ts` already declares all Phase 1-4 DTOs (Phase 4 plan 04-07 added them), but the dist had never been rebuilt since Phase 1.
- **Fix:** Rebuilt the package via `pnpm install && pnpm build` against the bot container with the full repo bind-mounted. The dist now exports all 18 DTOs.
- **Files modified:** `packages/shared-types/dist/index.d.ts` + `dist/index.js` (gitignored — not committed).
- **Commit:** none — build artefacts only; should be regenerated automatically by `make artisan ARGS="trenchwars:typescript-generate"` in CI but the local dev environment had drifted.
- **Note for next plan:** plan 05-09 will hit the same wall if shared-types regenerates between now and then. A more durable fix would be to add a CI gate that runs `pnpm -F @trenchwars/shared-types build` on every shared-types change, or to ship the dist in git. Out of scope for 05-08.

**2. Bot container image `node_modules` was stale (missing `discord.js`, `undici`, `ioredis`)**

- **Found during:** Task 2 verification (typecheck failed with `Cannot find module 'discord.js' or 'undici'`).
- **Issue:** The bot Docker image was built before Phase 5 plan 05-01 added the discord.js / undici / ioredis dependencies. `docker compose run --rm bot` was using the stale image which only had eslint + typescript + vitest.
- **Fix:** Bound the host repo into the container at runtime (`-v $PWD:/repo`) and ran `pnpm install` against the bind-mounted package.json. This rehydrated node_modules with the current deps.
- **Files modified:** `apps/bot/node_modules/` only (gitignored).
- **Note for next plan:** Plan 05-13 (operator smoke / VALIDATION.md) should add `docker compose build bot` to the bot-deploy checklist, OR the bot service should be amended in `docker-compose.yml` with a `volumes: - ./apps/bot:/app/apps/bot` bind mount mirroring the worker/web pattern. Out of scope for 05-08 — surfaced for plan 05-09 entry.

### Auth gates

None. All verification ran inside the dev container; no Discord token or Sanctum token needed at plan-execution time.

## Vitest Baseline Impact

- **Before:** 2 passed | 22 todo across 10 test files
- **After:** 24 passed | 20 todo across 10 test files (1 file flipped from
  all-todo to all-green)
- Wave 0 stubs remaining: 8 test files × 20 todos (rsvpButton, clan,
  embeds, signupModal, match, outbound, guildMemberUpdate, profile —
  all targeted by plans 05-09 / 05-10 / 05-11 / 05-12).

## Open Question (for next plan)

Should the bot validate API responses at runtime (e.g., `zod.parse(json)
as MatchData`) or trust the contract and cast `(await res.json()) as T`?
RESEARCH recommends trusting the contract for v1 — the web and bot ship
from the same monorepo, both type from the same shared-types package, and
TypeScript catches drift at build. Runtime validation deferred to Phase
9 polish if production observability reveals contract drift.

## Self-Check: PASSED

- [x] `apps/bot/src/env.ts` exists (commit `cf21568`)
- [x] `apps/bot/src/client.ts` exists (commit `cf21568`)
- [x] `apps/bot/src/index.ts` modified (commit `cf21568`)
- [x] `apps/bot/.env.example` exists (commit `cf21568`)
- [x] `apps/bot/src/services/api.ts` exists (commit `c497a8d`)
- [x] `apps/bot/src/lib/customIds.ts` exists (commit `c497a8d`)
- [x] `apps/bot/src/lib/colors.ts` exists (commit `c497a8d`)
- [x] `apps/bot/src/types/apiContracts.ts` exists (commit `c497a8d`)
- [x] `apps/bot/tests/lib/customIds.test.ts` modified to GREEN (commit `64711a4`)
- [x] Commits `cf21568`, `c497a8d`, `64711a4` all present in `git log`
