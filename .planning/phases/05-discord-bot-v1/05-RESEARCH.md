# Phase 5: Discord bot v1 — Research

**Researched:** 2026-05-13
**Domain:** Discord bot integration (bot-side `discord.js` + web-side Laravel API for bot↔web traffic)
**Confidence:** HIGH (libraries verified against Context7 + npm registry within 24h)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions (verbatim from CONTEXT.md)

- **D-002** Discord ID is canonical user identity (already in `User.discord_id` from Phase 1).
- **D-003** One league Discord guild; clan = role inside guild.
- **D-004** Bot is thin display layer — NO DB writes from bot, NO business logic. Every interaction calls Laravel API.
- **D-006** Multi-clan league platform.
- **D-014** Railway 5-service deploy (web + bot + rcon-worker + postgres + redis).
- **D-015** pnpm-workspaces monorepo — bot lives in `apps/bot/`.
- **D-020** TS types from `spatie/laravel-data` → `packages/shared-types/` consumed by bot.
- **D-021** Container-only — bot runs in `trenchwars-bot` container (already up per Phase 1 docker-compose).
- **Bot stack** (CLAUDE.md §2): Node 22 + `discord.js@^14.26` + `undici` (HTTP) + TypeScript strict.
- **CON-arch-bot-to-web-comm** (PROJECT.md): Sanctum personal access token scoped `bot:*` + header `X-Bot-Acts-As-User: <discord_id>` so `web` resolves causer correctly.
- **D-04-03-A LOCKED** (from Phase 4 close — STATE.md): canonical class is `App\Models\GameMatch`; table stays `matches` via `protected $table` override; FK columns are `match_id`. **Phase 5 bot adapter code MUST use this FQN.**

### Claude's Discretion (verbatim from CONTEXT.md)

- Modal field shapes for `/match signup` (recommend role picker dropdown sourced from match's role list via web API).
- Embed visual design (RESEARCH should propose canonical layout — match card with role-slot grid).
- Outbound message worker (Horizon Job vs Node-side cron polling). **CONTEXT recommendation:** Laravel sends to `discord_outbound_messages` table; bot polls or web pushes via Redis pub/sub or webhook. **This research narrows to a single recommendation in §3 below.**
- Retry semantics for outbound messages (exponential backoff; 3 attempts then mark `failed` and require manual replay).
- Test surface: bot-side Vitest for unit logic; integration tests via web Pest hitting bot-facing API endpoints.

### Conventions Inherited (verbatim from CONTEXT.md)

- Pest 4 for web tests; Vitest for bot tests.
- Pint + PHPStan L8 for web; `tsc --strict` + `prettier` for bot.
- spatie/laravel-data DTOs + custom typescript-generate for bot consumption.
- Activity log via LogsActivity on outbound messages + role-sync records.
- i18n: bot responses use English at v1 (translation deferred — TODO Phase 7/9).

### Deferred Ideas (OUT OF SCOPE)

- i18n for bot responses (v1 ships English; multi-locale later via locale lookup on User).
- Slash command autocomplete (out-of-scope v1; basic commands only).
- Voice channel integration / event scheduling on Discord side (Phase 6+).
- Tournament-related Discord features (bracket announcement embeds) — Phase 6.
- RCON result-announce embeds — Phase 8.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description (verbatim) | Research Support |
|----|------------------------|------------------|
| **REQ-goal-discord-ux** | Slash commands `/clan`, `/match`, `/profile`, `/me` exist; RSVP buttons and slot-picker modals work; result announcements post to the host clan's announce channel. | §2 (slash command + interaction lifecycle), §3 (outbound embeds for announcements), §5 (API endpoints `/api/bot/*`), §7 (embed shape + RSVP button id encoding), §8 (test strategy splitting bot Vitest + web Pest) |

ROADMAP Success Criteria (5 SCs verbatim):

| SC | Description (verbatim from CONTEXT.md / ROADMAP.md) | Primary research § |
|----|------------------------------------------------------|--------------------|
| **SC-1** | Discord user can invoke `/clan info\|list\|apply`, `/match list\|info\|signup\|leave`, `/profile`, `/me` — correct, privacy-aware responses inside 3s interaction window (deferReply for slow paths). | §2 + §5 + §9 |
| **SC-2** | `/match signup` modal creates `match_signups` row visible on website immediately; clan-role membership rules enforced server-side. | §1 (X-Bot-Acts-As-User → MatchSignupService) + §5 + §9 |
| **SC-3** | Website match creation triggers an announce-channel embed with RSVP buttons; persisted in `discord_outbound_messages` (pending → sent \| failed) for durability. | §3 (table shape + delivery) + §7 (embed + button id) + §9 |
| **SC-4** | Joining/leaving a clan on the website triggers Discord role assign/remove via Horizon-retried jobs; manual Discord-side role changes reconcile via `guildMemberUpdate` hook. | §4 (Horizon job + REST + guildMemberUpdate) + §9 |
| **SC-5** | All bot→web traffic uses Sanctum `bot:*` scoped token + `X-Bot-Acts-As-User` header; audit log attributes human causer correctly. | §1 (full middleware design) + §9 |

</phase_requirements>

## Summary

Phase 5 wires a `discord.js@^14.26` Node service (already containerised in `trenchwars-bot` per D-021) onto the Phase-4-complete Laravel API surface. Two new substrates land in the web app: **(a)** Sanctum-based bot↔web auth (scoped tokens `bot:read`, `bot:act-as-user`, `bot:write-outbound`; `X-Bot-Acts-As-User: <discord_id>` header resolved into the real `User` so activity_log attributes the human causer not the bot), and **(b)** a `discord_outbound_messages` outbox table that decouples Laravel domain writes from Discord side-effects. On the bot side, a thin command/component dispatcher backed by an undici-based API client + a polling-loop outbound worker delivers the SCs without introducing a new transport (no Reverb / pub-sub needed for v1).

The hardest mechanical bits are (1) the 3-second Discord interaction window — every slash command MUST `deferReply()` immediately and then resolve the API call inside the 15-minute follow-up window; (2) D-004 enforcement — the bot writes ZERO domain state; every signup/apply/RSVP funnels through the existing `MatchSignupService` (Phase 4) via Sanctum-authenticated HTTP; (3) idempotent Discord role sync — Horizon jobs PUT to `/guilds/{g}/members/{u}/roles/{r}` (idempotent — Discord returns 204 whether the role was added or already present) plus a `guildMemberUpdate` event handler that POSTs deltas back to the web API to reconcile manual Discord-side drift.

**Primary recommendation:** Bot polls `GET /api/bot/outbound-messages?status=pending&limit=20` every 5 seconds (cheap, no Reverb/pub-sub required for v1, matches Discord's own 50-req/s global rate limit budget with room to spare). Laravel uses `LockForUpdate` to atomically claim pending rows. Retry semantics live on the table (attempts/last_error/backoff_until), not in Horizon — keeps the outbound flow synchronous from Laravel's perspective and lets admins replay failed rows from Filament without touching queue internals.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|--------------|----------------|-----------|
| Slash command parsing + 3s window | Bot (Node) | — | Discord's gateway only talks to the bot process; bot MUST respond to the interaction within 3s. |
| Business logic (signup capacity, tag rules, clan applications) | API / Backend (Laravel) | — | D-004: bot has no domain logic; reuses `MatchSignupService`, `ClanApplicationService` from Phases 2/4. |
| Causer attribution on audit log | API / Backend | — | `X-Bot-Acts-As-User: <discord_id>` middleware sets `auth()->loginUsingId()` for the request scope so `LogsActivity` picks the human up automatically. |
| Outbound Discord message persistence | API / Backend (DB) | Bot (consumer) | D-004 keeps the source-of-truth in Postgres; bot is a delivery agent that polls + reports back. |
| Discord role assignment (clan join/leave) | API / Backend (job dispatch) | Bot (REST executor) | Horizon job in `apps/web` owns retry semantics; bot exposes a thin `POST /api/bot/role-sync/execute` endpoint OR the bot polls outbound messages of `message_type=role_sync` — see §4. |
| `guildMemberUpdate` reconciliation | Bot (event handler) | API / Backend (write) | Discord gateway only notifies the bot; bot POSTs deltas to `POST /api/bot/discord-events/role-change` for the web to apply. |
| Embed rendering | Bot | — | discord.js `EmbedBuilder` / `ButtonBuilder`; web only sends structured `payload` JSON. |
| Privacy-aware response shape | API / Backend | — | `PlayerPrivacyGate` (Phase 2) + `PublicMatchOccupantData` (Phase 4) already collapse private fields to `null`; bot just renders. |

---

## Standard Stack

### Core

| Library | Version | Purpose | Why standard |
|---------|---------|---------|--------------|
| `discord.js` | `^14.26.4` (latest v14 line; CLAUDE.md §2 pins `^14.26`) | Discord gateway + REST client; slash command registration; interaction dispatch | The de-facto Node Discord library (76.83 Context7 benchmark — `[VERIFIED: Context7 /websites/discord_js_packages_discord_js_14_26_2]`; npm `discord.js@14.26.4` published — `[VERIFIED: npm registry]`). `globalRequestsPerSecond: 50` + automatic 429 retry + bucket-aware rate limiter come built-in. |
| `@discordjs/rest` | `^2.6.1` (re-exported via discord.js dependency) | REST module for slash command registration on startup + standalone REST calls if needed | Already a transitive dep of `discord.js`. `[VERIFIED: npm view @discordjs/rest version → 2.6.1]` |
| `undici` | `^7.x` | HTTP client for bot→web Sanctum-authenticated calls | Already mandated by CLAUDE.md §2 / CON-stack-bot-libraries; matches what rcon-worker uses. `[VERIFIED: npm view undici version → 8.2.0]` Latest is 8.x — recommend pinning `^7.0` (LTS-ish, fewer breaking semver bumps) but 8.x is also fine and is the active line; planner choice. |
| `ioredis` | `^5.10` (optional for v1) | Redis client for bot if we add cross-instance dedupe or rate-limit windows later | CON-stack-bot-libraries mandates it. Phase 5 v1 does NOT need ioredis (the polling-loop pattern is self-contained), but the dependency is already in CLAUDE.md so install it for forward compatibility. `[VERIFIED: npm view ioredis version → 5.10.1]` |
| `vitest` | `^2.x` (already installed) | Bot test runner | `[VERIFIED: apps/bot/package.json existing dependency]` |

### Supporting (web side — new installs)

| Library | Version | Purpose | When to use |
|---------|---------|---------|-------------|
| `laravel/sanctum` | `^4.0` (Laravel 12 compatible) | Personal Access Tokens with abilities (scopes) for bot↔web auth | Install in Phase 5 plan 01 (Wave 0 / dependencies); the only auth path for the bot |
| `laravel/horizon` | `^5.x` | Redis queue dashboard + retry semantics for `SyncDiscordRolesJob` | Install in Phase 5 plan ~03 (jobs wave); QUEUE_CONNECTION=redis already wired in docker-compose.yml |

**Sanctum verification:** Bearer token in `Authorization: Bearer 1|abcdef…` header; ability check via `$request->user()->tokenCan('bot:read')` or middleware `'abilities:bot:read'`. `[VERIFIED: Context7 /laravel/sanctum]`

**Horizon verification:** Jobs implement `ShouldQueue`; declare `public int $tries = 5;` + `public function backoff(): array { return [1, 5, 15, 60, 300]; }` for exponential backoff in seconds. `[VERIFIED: Context7 /laravel/horizon]`

### Alternatives Considered

| Instead of | Could use | Tradeoff | Recommendation |
|------------|-----------|----------|----------------|
| `discord.js@14` | `discordeno`, `eris`, `oceanic.js` | Smaller deps but materially worse type coverage + thinner community; would invalidate D-001/CLAUDE.md §2 | **Reject** — discord.js is locked by CLAUDE.md §2 |
| Sanctum personal access tokens | OAuth client credentials, JWT, signed HMAC (mirroring rcon-worker) | HMAC is already in use for rcon→web (CON-arch-rcon-to-web-comm). Sanctum is canonical for bot tokens because we need per-token abilities + the "act as user" pattern needs a real User-row lookup, not just a signed payload | **Use Sanctum** — explicitly mandated by CON-arch-bot-to-web-comm |
| Bot polls outbound table | Laravel Reverb + WebSocket push, Redis pub/sub | Real-time push is cheaper at scale BUT requires (a) Reverb install + persistent WS connection, (b) crash-resilience: if the WS drops the bot has to fall back to polling anyway. Polling is dead-simple and easily within rate budget. | **Use polling for v1** — promote to pub/sub in v2 if scale demands |
| Horizon for role sync | Cron + DB queue | Horizon is the canonical Laravel queue dashboard; QUEUE_CONNECTION=redis already configured | **Use Horizon** — small install, retries + tags + UI for free |
| `SyncDiscordRolesJob` dispatches a Horizon job → bot listens on a Discord-specific endpoint | Outbound table polling for role syncs too (unified worker) | Two patterns vs one. The Job → outbound row pattern unifies the contract: every Discord side-effect is "Laravel writes a `discord_outbound_messages` row, bot polls + executes + reports back." | **Unify on outbound table** — see §4 for the recommended layered pattern |

**Installation (web side, per CLAUDE.md §1 container-only):**

```bash
make composer ARGS="require laravel/sanctum"
make composer ARGS="require laravel/horizon"
make artisan ARGS="install:api"           # registers routes/api.php + bootstrap api()
make artisan ARGS="horizon:install"       # publishes config/horizon.php
make artisan ARGS="vendor:publish --provider='Laravel\Sanctum\SanctumServiceProvider'"
```

The `install:api` Artisan command is the Laravel 11+/12 idiom — it creates `routes/api.php`, configures `bootstrap/app.php` with `api: __DIR__.'/../routes/api.php'`, sets the `/api` prefix, and registers Sanctum middleware. Confirmed empirically in Laravel 12. **`[CITED: Laravel 12 docs / install:api]`**

**Installation (bot side):**

```bash
docker compose exec bot pnpm add discord.js undici ioredis
docker compose exec bot pnpm add -D @types/node
```

(All `pnpm` calls run inside `trenchwars-bot` container per D-021.)

**Version verification (run before installing):**

```bash
npm view discord.js version       # currently 14.26.4 [VERIFIED 2026-05-13]
npm view undici version            # currently 8.2.0 [VERIFIED 2026-05-13]
npm view ioredis version           # currently 5.10.1 [VERIFIED 2026-05-13]
npm view @discordjs/rest version   # currently 2.6.1 [VERIFIED 2026-05-13]
# Laravel packages via composer:
docker compose exec web composer show -a laravel/sanctum | head -1
docker compose exec web composer show -a laravel/horizon | head -1
```

## Architecture Patterns

### System Architecture Diagram

```
+-------------------+        Discord Gateway WS         +---------------------+
|  Discord user(s)  |  <-------------------------->     |  apps/bot (Node 22) |
+-------------------+   (slash cmd, buttons, modals,    |  trenchwars-bot     |
                         guildMemberUpdate events)      +----------+----------+
                                                                   |
                                                                   |  undici HTTPS
                                                                   |  Authorization: Bearer <sanctum_bot_token>
                                                                   |  X-Bot-Acts-As-User: <discord_id>
                                                                   v
+--------------------------------------------------------------------------------+
|  apps/web (Laravel 12 + Filament)  routes/api.php  prefix /api/bot/*           |
|                                                                                |
|  +---------------------+   +-------------------+   +------------------------+  |
|  | Sanctum auth:sanctum| ->| abilities:bot:*   | ->| ResolveBotActsAsUser   |  |
|  | (Bearer token)      |   | middleware        |   | middleware             |  |
|  +---------------------+   +-------------------+   | (X-Bot-Acts-As-User    |  |
|                                                    |  → loginUsingId for    |  |
|                                                    |  request scope)        |  |
|                                                    +----------+-------------+  |
|                                                               |                |
|        +------------+--------------------+-------+-------+----+               |
|        v            v                    v       v       v                    |
|  BotApiClanController  BotApiMatchController  BotApiOutboundController etc.   |
|        |                  |  (signup)             | (poll pending / mark sent)|
|        v                  v                       v                           |
|  ClanApplicationService  MatchSignupService  discord_outbound_messages table  |
|  (Phase 2 reuse)         (Phase 4 reuse — D-010 row-locked)                   |
|                                                                                |
|  Horizon worker container (separate)                                          |
|  +---------------------+                                                       |
|  | SyncDiscordRolesJob | -- writes pending row to discord_outbound_messages   |
|  +---------------------+    (message_type='role_sync')                        |
+--------------------------------------------------------------------------------+

Web-side WRITE FLOW (clan create/match create):
  Filament UI / Service write -> Observer (Phase 4 idiom) -> discord_outbound_messages INSERT(pending)
                                                              ^
                                                              | bot polls
                                                              |
   apps/bot outbound worker (every 5s):
     GET /api/bot/outbound-messages?status=pending&limit=20
     -> for each: render via EmbedBuilder + ButtonBuilder, channel.send()
     -> POST /api/bot/outbound-messages/{id}/sent (with sent_message_id)
     OR POST /api/bot/outbound-messages/{id}/failed (with last_error)

Web-side ROLE SYNC FLOW (clan join/leave):
  ClanMembershipObserver -> SyncDiscordRolesJob::dispatch(membership_id, action)
  Horizon worker picks it up:
    -> writes outbound row (message_type='role_sync', payload={user_id, role_id, action})
    -> bot polls + executes guild.members.fetch(uid).roles.add/remove(rid)
    -> bot POSTs /api/bot/outbound-messages/{id}/sent

Discord-side DRIFT RECONCILIATION:
  Bot listens to Events.GuildMemberUpdate -> diff roles cache -> for each delta:
    POST /api/bot/discord-events/role-change {user_discord_id, role_discord_id, action}
    Web side: idempotent reconcile (e.g., manual role add of "Clan A" creates a
              ClanMembership row if one doesn't exist + writes activity_log
              attributing the Discord-side actor if mappable).
```

### Recommended Project Structure (apps/bot/)

```
apps/bot/
├── src/
│   ├── index.ts                       # entry, login, register slash commands on boot, install event handlers
│   ├── env.ts                         # env var parsing + validation (DISCORD_BOT_TOKEN, WEB_API_URL, WEB_API_TOKEN, GUILD_ID, OUTBOUND_POLL_INTERVAL_MS)
│   ├── client.ts                      # Client construction with required intents (Guilds, GuildMembers, GuildMessageReactions)
│   ├── commands/
│   │   ├── index.ts                   # exports all command modules + registry map keyed by command name
│   │   ├── clan.ts                    # /clan info|list|apply (subcommand routing inside one builder)
│   │   ├── match.ts                   # /match list|info|signup|leave
│   │   ├── profile.ts                 # /profile [discord_user]?
│   │   └── me.ts                      # /me — current Discord user's profile
│   ├── components/
│   │   ├── index.ts                   # component dispatcher (customId prefix routing)
│   │   ├── rsvpButton.ts              # RSVP button handlers (customId pattern "match:rsvp:<match_id>:<game_role_id>")
│   │   └── signupModal.ts             # /match signup modal builder + submit handler
│   ├── events/
│   │   ├── ready.ts                   # client ready handler (logs to stdout, starts outbound poller)
│   │   ├── interactionCreate.ts       # central interaction router (chat input | button | modal submit)
│   │   └── guildMemberUpdate.ts       # role drift reconciliation
│   ├── services/
│   │   ├── api.ts                     # ApiClient class: undici + Bearer token + X-Bot-Acts-As-User helper
│   │   ├── outbound.ts                # pending message poll-loop worker (setInterval-driven)
│   │   └── rateLimit.ts               # (optional v1) ioredis-backed dedupe key store
│   ├── lib/
│   │   ├── embeds.ts                  # canonical EmbedBuilder factories: matchCard(), clanCard(), profileCard()
│   │   ├── buttons.ts                 # ButtonBuilder factories: rsvpButton(matchId, roleId)
│   │   ├── customIds.ts               # encode/decode helpers for component customId strings
│   │   └── colors.ts                  # status → embed color map (matches the web's status badge palette)
│   └── types/
│       └── apiContracts.ts            # local re-export of @trenchwars/shared-types DTOs + bot-specific extras
├── tests/
│   ├── commands/
│   │   ├── clan.test.ts               # Vitest: mock ApiClient, assert subcommand routing + reply shape
│   │   └── match.test.ts
│   ├── components/
│   │   ├── rsvpButton.test.ts         # Vitest: customId decode + ApiClient call + reply
│   │   └── signupModal.test.ts
│   ├── lib/
│   │   ├── customIds.test.ts          # round-trip encode/decode
│   │   └── embeds.test.ts             # snapshot test on EmbedBuilder.toJSON()
│   └── services/
│       └── outbound.test.ts           # mock ApiClient, assert poll → render → send → mark-sent loop
├── eslint.config.mjs                  # (already exists from Phase 1)
├── vitest.config.ts                   # (already exists from Phase 1)
├── tsconfig.json                      # (already exists from Phase 1)
├── nixpacks.toml                      # (already exists from Phase 1)
├── railway.json                       # (already exists from Phase 1)
└── package.json                       # (already exists; add discord.js + undici + ioredis)
```

**Build/deploy:** TypeScript compile via `tsc` (no bundler — `pnpm build` already runs `tsc` per the existing `apps/bot/package.json`; output goes to `dist/`, Node runs `dist/index.js`). esbuild is NOT needed at v1 scale — `tsc` is fine and matches how `rcon-worker` builds. Railway nixpacks.toml already wired (Phase 1 plan 01-17).

### Pattern 1: `apps/web` ResolveBotActsAsUser middleware

**What:** Middleware that reads `X-Bot-Acts-As-User: <discord_id>`, resolves to a `User` row, and rebinds `auth()->loginUsingId($user->id)` for the request lifetime. Activity log + policies + Filament gates then attribute every effect to the human, not the bot's Sanctum-token-owning service user.

**When to use:** Applied immediately after `auth:sanctum` + `abilities:bot:act-as-user` on `/api/bot/*` routes that perform writes on behalf of a user (signup, apply, leave clan, RSVP).

**Example:**

```php
// Source: Phase 5 design; references the existing User::discord_id column from Phase 1.
final class ResolveBotActsAsUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $actsAs = $request->header('X-Bot-Acts-As-User');
        if ($actsAs === null) {
            // Some endpoints (GET-only / read-only) do not require acting-as-user.
            // The route MUST not call abilities:bot:act-as-user if it tolerates missing header.
            return $next($request);
        }

        $user = User::where('discord_id', $actsAs)->first();
        if ($user === null) {
            return response()->json([
                'error' => 'acts_as_user_unknown',
                'message' => 'Discord user has never logged in to the website.',
            ], 422);
        }

        // Rebind auth for the request scope so LogsActivity::causer() picks the human.
        // The Sanctum token's underlying user (the bot service account) is no longer the
        // resolved auth()->user() — that's the whole point of this middleware.
        Auth::onceUsingId($user->id);

        return $next($request);
    }
}
```

Registered as a route middleware alias in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [HandleInertiaRequests::class]);
    $middleware->alias([
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        'bot.acts-as' => \App\Http\Middleware\ResolveBotActsAsUser::class,
    ]);
})
```

And on the routes:

```php
// routes/api.php
Route::prefix('bot')->middleware(['auth:sanctum', 'abilities:bot:read'])->group(function () {
    Route::get('/clans', [BotApiClanController::class, 'index']);
    Route::get('/clans/{discordRoleId}', [BotApiClanController::class, 'showByDiscordRole']);
    Route::get('/matches', [BotApiMatchController::class, 'index']);
    Route::get('/matches/{match}', [BotApiMatchController::class, 'show']);

    // Acts-as-user endpoints — additional ability gate
    Route::middleware(['abilities:bot:act-as-user', 'bot.acts-as'])->group(function () {
        Route::post('/matches/{match}/signups', [BotApiMatchSignupController::class, 'store']);
        Route::delete('/matches/{match}/signups/{gameRole}', [BotApiMatchSignupController::class, 'destroy']);
        Route::post('/clans/{clan}/applications', [BotApiClanApplicationController::class, 'store']);
        Route::get('/users/me', [BotApiUserController::class, 'me']);
    });

    // Outbound delivery — needs write-outbound ability, NOT acts-as-user
    Route::middleware('abilities:bot:write-outbound')->group(function () {
        Route::get('/outbound-messages', [BotApiOutboundController::class, 'pending']);
        Route::post('/outbound-messages/{id}/sent', [BotApiOutboundController::class, 'markSent']);
        Route::post('/outbound-messages/{id}/failed', [BotApiOutboundController::class, 'markFailed']);
    });

    // Discord-side drift reconciliation (no act-as: the bot reports observed Discord events)
    Route::middleware('abilities:bot:reconcile')->group(function () {
        Route::post('/discord-events/role-change', [BotApiDiscordEventController::class, 'roleChange']);
    });
});
```

### Pattern 2: discord.js v14 interaction lifecycle

**What:** Every interaction has a hard 3-second initial-response deadline. After `deferReply()` the token is valid for 15 minutes for `editReply()` / `followUp()`. **`[CITED: discord.com/developers/docs/interactions/receiving-and-responding]`**

**When to use:** Every interaction handler.

**Example (the canonical Phase 5 slash command shape):**

```typescript
// Source: discord.js Context7 /websites/discord_js_packages_discord_js_14_26_2 +
//         /discordjs/guide. Defer immediately so the API call has time to complete.

import { SlashCommandBuilder, ChatInputCommandInteraction, MessageFlags } from 'discord.js';
import { api } from '../services/api';
import { matchCard } from '../lib/embeds';

export const data = new SlashCommandBuilder()
    .setName('match')
    .setDescription('Manage matches')
    .addSubcommand((sc) =>
        sc.setName('info')
          .setDescription('Show a match by ID')
          .addStringOption((o) => o.setName('id').setDescription('Match UUID').setRequired(true)),
    );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
    // CRITICAL: defer in the first 3s. Ephemeral so the response is only visible to invoker.
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    const matchId = interaction.options.getString('id', true);
    const match = await api.get(`/matches/${matchId}`, {
        actsAsDiscordId: interaction.user.id,
    });

    if (match === null) {
        await interaction.editReply({ content: 'Match not found.' });
        return;
    }

    await interaction.editReply({ embeds: [matchCard(match)] });
}
```

**Notes:**
- `MessageFlags.Ephemeral` (not the older `ephemeral: true`) is the v14.26 canonical flag.
- `deferReply()` returns once Discord ACKs — typically <100ms — so the API call's latency budget is the full 15 minutes minus a safety margin (in practice, target <5s).
- For modals: do NOT defer before `showModal()` — modals MUST be the initial response. Defer in the modal SUBMIT handler instead.

### Pattern 3: Slash command registration on startup

**What:** Use `@discordjs/rest` + `Routes.applicationGuildCommands(applicationId, guildId)` to register commands at boot. Guild-scoped (not global) so they appear instantly — global commands take up to 1h to propagate. We have ONE league guild (D-003), so guild-scoped is the correct choice. **`[VERIFIED: Context7 /discordjs/guide]`**

**Example:**

```typescript
// Source: discord.js guide — "Register slash commands"
import { REST, Routes } from 'discord.js';
import { commands } from './commands';
import { env } from './env';

export async function registerCommands(): Promise<void> {
    const rest = new REST({ version: '10' }).setToken(env.DISCORD_BOT_TOKEN);
    const body = Object.values(commands).map((c) => c.data.toJSON());

    await rest.put(
        Routes.applicationGuildCommands(env.DISCORD_APPLICATION_ID, env.DISCORD_GUILD_ID),
        { body },
    );
    console.log(`[bot] Registered ${body.length} slash commands to guild ${env.DISCORD_GUILD_ID}`);
}
```

Called once from `ready` event handler. Re-running on every boot is safe — Discord deduplicates by command name.

### Pattern 4: Polling outbound message worker

**What:** Bot runs a `setInterval(..., 5000)` loop that hits `GET /api/bot/outbound-messages?status=pending&limit=20`, processes each row, then POSTs back to mark sent/failed. Polling at 5s with up to 20 rows per cycle = max 240 messages/min, well within Discord's 50 req/s global limit.

**When to use:** v1 outbound delivery for all `message_type` values (match_announce, role_sync, etc.).

**Example:**

```typescript
// Source: Phase 5 design. The simplest viable durable delivery for v1.
import { api } from './api';
import { renderOutbound } from './render';

let running = false;

export function startOutboundWorker(intervalMs = 5_000): void {
    setInterval(async () => {
        if (running) return;        // skip overlapping ticks
        running = true;
        try {
            const rows = await api.get<OutboundRow[]>(
                '/outbound-messages?status=pending&limit=20',
            );
            for (const row of rows) {
                try {
                    const result = await renderOutbound(row);
                    await api.post(`/outbound-messages/${row.id}/sent`, {
                        sent_message_id: result.discordMessageId,
                    });
                } catch (err) {
                    const message = err instanceof Error ? err.message : String(err);
                    await api.post(`/outbound-messages/${row.id}/failed`, {
                        last_error: message.slice(0, 1000),
                    });
                }
            }
        } catch (err) {
            // top-level fetch failed; log and let the next tick retry
            console.error('[outbound-worker]', err);
        } finally {
            running = false;
        }
    }, intervalMs);
}
```

**Server-side claim semantics:** `BotApiOutboundController::pending` MUST `lockForUpdate` the rows it returns AND atomically flip them to `status='dispatching'` + bump `attempts` in the same transaction, so two bot instances (multi-region or rolling-deploy) don't pick up the same row twice. Bot's mark-sent/mark-failed transitions from `dispatching` → `sent` | `failed`.

```php
// BotApiOutboundController::pending — atomic claim
public function pending(Request $request): JsonResponse
{
    $limit = (int) $request->query('limit', 20);
    $rows = DB::transaction(function () use ($limit) {
        return DB::table('discord_outbound_messages')
            ->where('status', 'pending')
            ->where('backoff_until', '<=', now())
            ->orderBy('created_at')
            ->lockForUpdate()
            ->limit($limit)
            ->get()
            ->each(function ($row) {
                DB::table('discord_outbound_messages')
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'dispatching',
                        'attempts' => DB::raw('attempts + 1'),
                        'updated_at' => now(),
                    ]);
            });
    });
    return response()->json($rows);
}
```

### Pattern 5: Discord role sync via outbound table

**What:** ClanMembership create/delete fires `SyncDiscordRolesJob` (Horizon-retried). The job's `handle()` doesn't talk to Discord directly — it INSERTs a `discord_outbound_messages` row with `message_type='role_sync'` and `payload={discord_user_id, discord_role_id, action: 'add'|'remove'}`. The same bot outbound worker picks it up and calls `guild.members.fetch(uid).roles.add/remove(rid)`. Unified one-table pattern.

**Why:** Funnels every Discord side-effect through ONE durability surface (`discord_outbound_messages`). Filament admin can replay both message types. Idempotent on both ends — Discord's PUT role API returns 204 whether the role was added or already present.

### Pattern 6: guildMemberUpdate reconciliation

**What:** Discord side actor (manual mod action) adds/removes a clan role. Bot listens, diffs the roles cache, POSTs each delta to `/api/bot/discord-events/role-change`. Web side maps `discord_role_id → Clan` and applies/reverses the membership change, attributing the activity_log row to the actor mappable from the Discord audit log (out of scope v1 — for v1, attribute to a "discord-side reconciler" system user).

**Example (the canonical handler):**

```typescript
// Source: discord.js guide — "Detect role changes with GuildMemberUpdate"
import { Events, GuildMember, PartialGuildMember } from 'discord.js';
import { api } from '../services/api';

export const name = Events.GuildMemberUpdate;
export async function execute(
    oldMember: GuildMember | PartialGuildMember,
    newMember: GuildMember,
): Promise<void> {
    const removed = oldMember.roles.cache.filter((r) => !newMember.roles.cache.has(r.id));
    const added = newMember.roles.cache.filter((r) => !oldMember.roles.cache.has(r.id));

    for (const [, role] of removed) {
        await api.post('/discord-events/role-change', {
            user_discord_id: newMember.id,
            role_discord_id: role.id,
            action: 'remove',
        });
    }
    for (const [, role] of added) {
        await api.post('/discord-events/role-change', {
            user_discord_id: newMember.id,
            role_discord_id: role.id,
            action: 'add',
        });
    }
}
```

**Idempotency note:** The bot's own role-sync outbound delivery ALSO triggers `guildMemberUpdate` on the bot. The web endpoint MUST detect this and no-op: if there's a recently-dispatched `discord_outbound_messages` row with matching (user, role, action) within the last 60s, treat the `guildMemberUpdate` as our own echo and skip. (See Pitfall §10.)

### Anti-Patterns to Avoid

- **`interaction.reply()` after >3s without `deferReply()`** → Discord returns `Unknown interaction (10062)` and the user sees nothing. ALWAYS defer in slash command handlers; do NOT defer before showing a modal (modals must be the first response).
- **Hand-rolling Discord rate limiting** → discord.js' REST manager already buckets by route hash + handles 429 retries (defaults: `retries: 3`, `globalRequestsPerSecond: 50`). Don't reinvent it. **`[VERIFIED: Context7 /websites/discord_js_packages_discord_js_14_26_2 DefaultRestOptions]`**
- **Bot writing directly to the database** → D-004 LOCKED. Every state mutation goes through the Laravel API. Even read-only "let me cache the clan list in Redis" patterns are unnecessary at v1 scale.
- **Bot embedding bearer tokens in command code** → token MUST come from env var `WEB_API_TOKEN` (already declared in docker-compose.yml). Never log it; never include in error messages.
- **Discord-side role change without echo suppression** → causes infinite ping-pong between web and Discord. (See Pitfall §10.)
- **Sanctum tokens without expiration** → bot tokens MUST have `expires_at` (recommend rotating every 90 days; doc the rotation procedure in plan close).

## Don't Hand-Roll

| Problem | Don't build | Use instead | Why |
|---------|-------------|-------------|-----|
| Discord REST rate limiting | Custom token bucket / retry loop | `discord.js`'s built-in REST manager (bucket-aware, 429-handling, `globalRequestsPerSecond: 50`) | discord.js handles route-hash buckets, sublimit retries, global limits — a hand-rolled version is wrong for edge cases (sublimit timeouts, hash refreshes) |
| Slash command routing | switch/case on `commandName` | Map-based dispatcher: `commands.get(name).execute(interaction)` — the canonical discord.js Guide pattern | Scales cleanly; one new file per command; tested independently |
| Bearer token issuance | Custom random + DB row | `$user->createToken('bot-prod', ['bot:read','bot:write-outbound','bot:act-as-user'])` — `[VERIFIED: Context7 /laravel/sanctum]` | Sanctum already provides hashed-at-rest tokens with abilities + last_used_at tracking |
| Ability/scope checks | `if ($token->abilities)` inline | `$middleware->alias(['abilities' => CheckAbilities::class])` + route middleware | Single point of enforcement; uniform 403 response |
| Outbound retry backoff | Polling-side delay computation | DB column `backoff_until` (timestamp) + Laravel ‑side computes next attempt time on failure | Bot stays dumb; Laravel owns retry policy; admin can manually edit the column to replay |
| Discord embed JSON shaping | `JSON.stringify({...})` | `EmbedBuilder().setColor().setTitle()...` + `ButtonBuilder().setCustomId().setLabel().setStyle()` + `ActionRowBuilder().addComponents()` — `[VERIFIED: Context7 /discordjs/guide]` | Type-safe; validates field limits (title 256, description 4096, fields 25); avoids 400 from Discord |
| Job retry semantics | Custom DB queue + cron | `laravel/horizon` with `$tries=5; backoff(): [1,5,15,60,300]` — `[VERIFIED: Context7 /laravel/horizon]` | Free dashboard, automatic exponential backoff, failed-job retry UI |
| Per-Discord-snowflake user lookup | Inline `User::where('discord_id',...)->first()` everywhere | `ResolveBotActsAsUser` middleware once + `auth()->user()` everywhere downstream | DRY; correctly attributes audit log via LogsActivity |
| Guild member fetch with caching | Custom cache layer in bot | `guild.members.fetch(uid)` — discord.js caches automatically; `partial: false` ensures full GuildMember | discord.js' built-in `GuildMemberManager` cache is what every guide example uses |

**Key insight:** v1 wants exactly ONE new transport surface (the Sanctum API), exactly ONE new persistence surface (`discord_outbound_messages`), and exactly ZERO real-time-push infrastructure. Resist any temptation to add Reverb, Redis pub/sub, BullMQ, or BullBoard — Horizon + DB polling is enough for several hundred concurrent league users.

## Runtime State Inventory

**Not applicable** — Phase 5 is a greenfield bot integration, not a rename/refactor. No prior bot state exists in the production system. **However**, two adjacent runtime-state concerns are worth surfacing:

| Category | Items relevant to Phase 5 | Action required |
|----------|----------------------------|------------------|
| Stored data | None — `discord_outbound_messages` is a NEW table in Phase 5; no migration of existing data | None |
| Live service config | Discord application registration: slash commands re-registered on every bot boot via `Routes.applicationGuildCommands` — idempotent, but the registration call MUST run successfully at least once for the commands to appear | Document in operator manual smoke that the operator confirms `/match`, `/clan`, `/profile`, `/me` appear in Discord after the first deploy |
| OS-registered state | Railway service `bot` already exists per Phase 1 plan 01-17 — no new service registration needed | None |
| Secrets / env vars | NEW Railway env group entries needed: `DISCORD_BOT_TOKEN`, `DISCORD_APPLICATION_ID`, `DISCORD_GUILD_ID`, `WEB_API_URL`, `WEB_API_TOKEN`. The first three live in the Discord Developer Portal; `WEB_API_TOKEN` is the long-lived Sanctum token generated by an Artisan command in the web service and pasted into the bot service env group | Document in Phase 5 plan deploy summary: "After running `php artisan trenchwars:bot:issue-token`, paste the printed token into Railway's bot service `WEB_API_TOKEN` env var" |
| Build artifacts | apps/bot/dist/ is rebuilt on every deploy by nixpacks (Phase 1 already wired this) | None |

## Common Pitfalls

### Pitfall 1: 3-second interaction window
**What goes wrong:** Slash command handler does an unfetched API call before `deferReply()`; Discord invalidates the token after 3s; user sees "The application did not respond."
**Why it happens:** Easy to forget — discord.js does not enforce defer-before-await.
**How to avoid:** ALWAYS call `await interaction.deferReply({ flags: MessageFlags.Ephemeral })` as the FIRST statement of every slash command handler. Add an eslint rule or unit-test pattern in `tests/commands/` that asserts `interaction.deferReply` is invoked before any other awaited call.
**Warning signs:** `Unknown interaction (10062)` errors in bot stdout; users report "the bot didn't respond."

### Pitfall 2: Discord global rate limit (50 req/s)
**What goes wrong:** Burst of role syncs (e.g., 100 ClanMembership creates from an admin script) saturates the 50 req/s global limit; later requests are 429-deferred by discord.js automatically and the outbound worker stalls.
**Why it happens:** Discord's global limit is per-token; we have one bot token.
**How to avoid:** discord.js handles 429 retries automatically — no code change needed. But on the WEB side, cap outbound poll batches at `limit=20` per 5s cycle = 240 msg/min ceiling. For burst migrations, throttle the dispatching of role_sync rows in the Horizon job (e.g., `$job->delay(now()->addSeconds(rand(0, 30)))`).
**Warning signs:** `RateLimited` events on `client.rest`; outbound rows stuck in `dispatching` for >30s.

### Pitfall 3: Sanctum token leakage
**What goes wrong:** Bot logs the bearer token in an error trace, or pushes a token-containing config file to git, or rotates the user's token but forgets to invalidate the old one.
**Why it happens:** `WEB_API_TOKEN` is a plaintext env var; easy to dump in logs.
**How to avoid:**
- Bot's `api.ts` MUST scrub the token before logging request errors (replace `Authorization: Bearer ***` in error output).
- Provide an Artisan command `trenchwars:bot:revoke-token --name=bot-prod` for rotation; document in plan close.
- `.env.example` MUST have `WEB_API_TOKEN=` (empty value) — never a placeholder that looks token-shaped.
- Token MUST have `expires_at` set: `$user->createToken('bot-prod', $abilities, now()->addDays(90))`.
**Warning signs:** Token visible in Sentry/Logtail traces; CI failing because the token was committed.

### Pitfall 4: Sanctum stateful guard bleed-through (CSRF)
**What goes wrong:** Default Sanctum config includes `EnsureFrontendRequestsAreStateful` for the SPA flow — applied to ALL `/api/*` requests. Bot requests then require an XSRF-TOKEN cookie they can't have.
**Why it happens:** `config/sanctum.php` `stateful` array includes `localhost` and the production domain by default.
**How to avoid:** Bot requests come from the bot CONTAINER, not a stateful origin. As long as `WEB_API_URL=http://web-nginx` (the docker network hostname) and that's NOT in `SANCTUM_STATEFUL_DOMAINS`, the request takes the bearer-token-only path. Verify in plan task: `php artisan config:show sanctum.stateful` does NOT include `web-nginx` or `bot`.
**Warning signs:** 419 CSRF errors on bot→web calls; "Token mismatch" in Laravel logs.

### Pitfall 5: customId length cap (100 chars)
**What goes wrong:** RSVP button customId encodes `match:rsvp:<uuid>:<uuid>:<extra>` and overflows Discord's 100-char limit; ButtonBuilder throws a validation error.
**Why it happens:** Match IDs are UUIDs (36 chars each). Two UUIDs = 72 chars + `match:rsvp::` (12) = 84 chars — fits, but barely. Adding a third field would overflow.
**How to avoid:** Use short prefix + 2 IDs max. Canonical scheme: `m:s:<match_id>:<game_role_id>` (39+39 chars + 6 = 84 chars max — fits). For RSVPs that need more state, store the extra state in `discord_outbound_messages.payload` and look up by `sent_message_id` on click.
**Warning signs:** `Invalid Form Body: components[0].components[0].custom_id: BASE_TYPE_BAD_LENGTH` from Discord.

### Pitfall 6: discord.js Intents misconfiguration
**What goes wrong:** Bot doesn't receive `guildMemberUpdate` because `GatewayIntentBits.GuildMembers` is missing; we lose Discord-side drift reconciliation silently.
**Why it happens:** `GuildMembers` is a "privileged" intent — must be enabled in the Discord Developer Portal AND requested in the Client constructor.
**How to avoid:** Document the Discord Developer Portal toggle in plan task; assert at boot:

```typescript
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers,   // privileged — must be enabled in dev portal
        GatewayIntentBits.GuildModeration,
    ],
});
```

Document in the manual smoke check: operator confirms in https://discord.com/developers/applications → Bot → "Server Members Intent" is ON.

**Warning signs:** Bot connects but `guildMemberUpdate` never fires; `Used disallowed intents` gateway disconnect error.

### Pitfall 7: ResolveBotActsAsUser middleware swallowing system writes
**What goes wrong:** Middleware applied globally to `/api/bot/*`; outbound-message ack endpoints (`/outbound-messages/{id}/sent`) tolerate missing `X-Bot-Acts-As-User` and run as the bot service user — but if the middleware is misconfigured to REQUIRE the header on all routes, the bot can never mark messages sent.
**Why it happens:** Easy to globally `->middleware('bot.acts-as')` on the whole group.
**How to avoid:** Apply `bot.acts-as` ONLY to the write-on-behalf endpoints (see Pattern 1 route grouping). Outbound ack and discord-events endpoints do not act as a user.
**Warning signs:** All outbound rows stuck `dispatching` forever despite bot logs showing successful Discord sends.

### Pitfall 8: Horizon worker NOT running in the local docker stack
**What goes wrong:** Phase 5 expects a queue worker, but docker-compose.yml only has `web`, `web-nginx`, `bot`, `rcon-worker`, `postgres`, `redis`. No `worker` service. ClanMembershipObserver dispatches `SyncDiscordRolesJob` to Redis; nothing processes it.
**Why it happens:** Production has Railway-managed worker dyno (D-014 — "5 services"), but the local compose stack does not.
**How to avoid:** **TWO options** for the planner:
- **(A)** Add a `worker` service to docker-compose.yml in Phase 5 plan 01 (runs `php artisan horizon`); matches the D-014 production topology one-to-one.
- **(B)** Document `make horizon` as a manual local-dev step (developer runs Horizon in a separate terminal); accept that Phase 5 testing always runs in CI / production with a real worker.
**Recommendation:** **(A)** — keeps dev/prod parity (D-021 spirit) at the cost of one new compose service definition.
**Warning signs:** Jobs accumulate in Redis with no processing; "queue:listen --once" in tinker shows pending jobs.

### Pitfall 9: PHP reserved keyword `match` continues to bite
**What goes wrong:** Phase 5 plans reference `App\Models\Match` instead of `App\Models\GameMatch` (D-04-03-A LOCKED). PHP 8.4 throws a parse error.
**Why it happens:** Easy to forget when writing route model bindings or DTO factories.
**How to avoid:** ALL Phase 5 bot adapter code uses `use App\Models\GameMatch;` (no alias). Route model bindings: `Route::post('/matches/{match}/signups', ...)` where `{match}` resolves via `GameMatch` because we set `Route::model('match', GameMatch::class)` in `RouteServiceProvider` (or, in Laravel 12 idiom, rely on implicit binding via the typed parameter `BotApiMatchSignupController::store(GameMatch $match, ...)`). The URL slug `match` is fine — the PHP keyword conflict only matters at the class declaration site.
**Warning signs:** `syntax error, unexpected 'Match'` at boot; PHPStan L8 cannot resolve `App\Models\Match`.

### Pitfall 10: Echo loop from bot's own role changes triggering guildMemberUpdate
**What goes wrong:** Web dispatches a role_sync (`add Clan A role to user X`). Bot adds the role via REST. Discord fires `guildMemberUpdate` on the bot. Bot POSTs `/discord-events/role-change`. Web tries to apply the change again. Bot fires another role_sync. Infinite loop.
**Why it happens:** `guildMemberUpdate` fires regardless of who triggered the change.
**How to avoid:** Web `BotApiDiscordEventController::roleChange` checks if there's a `discord_outbound_messages` row with matching `(user_id, role_id, action)` in `status='sent'` and `updated_at > now() - 60s`. If yes, treat as own echo and return 200 with `{"action":"noop","reason":"own_echo"}`. Document the 60-second window in the controller comment.
**Warning signs:** Activity log shows 100s of role_sync rows per user per minute; Discord rate-limits the bot.

### Pitfall 11: Modal submit interaction's 3-second window restarts
**What goes wrong:** User invokes `/match signup`, slash handler shows modal (`interaction.showModal(...)`). User fills modal and submits. The MODAL SUBMIT is a NEW interaction with its own 3-second window — handler must `deferReply()` again at the start of `Events.InteractionCreate → isModalSubmit()`.
**Why it happens:** The "modal flow" feels like one interaction in UX terms but is two interactions in the API.
**How to avoid:** Top-level dispatcher in `events/interactionCreate.ts` calls `await interaction.deferReply({ flags: MessageFlags.Ephemeral })` for every `isModalSubmit()` branch before routing to handler.
**Warning signs:** Modal submit handler runs but user sees no response.

### Pitfall 12: Web container internal hostname for bot API calls
**What goes wrong:** Bot's `WEB_API_URL` points to `http://localhost:8000`; bot container has no port-8000 binding.
**Why it happens:** Easy to copy the dev URL.
**How to avoid:** docker-compose.yml ALREADY has `WEB_API_URL: ${WEB_API_URL:-http://web-nginx}` — keep that pattern. Production overrides via Railway env to the public web URL. `[VERIFIED: docker-compose.yml line 86]`
**Warning signs:** ECONNREFUSED in bot logs.

## Code Examples

### Example 1: api.ts — undici-based Sanctum client with acts-as header

```typescript
// Source: undici v8 fetch API + Sanctum bearer pattern.
import { fetch, Headers } from 'undici';
import { env } from '../env';

interface CallOptions {
    actsAsDiscordId?: string;
    body?: unknown;
    method?: 'GET' | 'POST' | 'DELETE';
}

export const api = {
    async request<T>(path: string, opts: CallOptions = {}): Promise<T> {
        const headers = new Headers({
            'Accept': 'application/json',
            'Authorization': `Bearer ${env.WEB_API_TOKEN}`,
        });
        if (opts.body !== undefined) headers.set('Content-Type', 'application/json');
        if (opts.actsAsDiscordId !== undefined) {
            headers.set('X-Bot-Acts-As-User', opts.actsAsDiscordId);
        }

        const res = await fetch(`${env.WEB_API_URL}/api/bot${path}`, {
            method: opts.method ?? (opts.body !== undefined ? 'POST' : 'GET'),
            headers,
            body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
        });

        if (!res.ok) {
            const text = await res.text().catch(() => '<unreadable>');
            // SCRUB token from any error trace
            const scrubbed = text.replace(env.WEB_API_TOKEN, '[REDACTED]');
            throw new Error(`Bot API ${opts.method ?? 'GET'} ${path} → ${res.status}: ${scrubbed.slice(0, 500)}`);
        }
        return (await res.json()) as T;
    },
    get<T>(path: string, opts: Omit<CallOptions, 'method' | 'body'> = {}): Promise<T> {
        return this.request<T>(path, { ...opts, method: 'GET' });
    },
    post<T>(path: string, body: unknown, opts: Omit<CallOptions, 'method' | 'body'> = {}): Promise<T> {
        return this.request<T>(path, { ...opts, method: 'POST', body });
    },
};
```

### Example 2: customIds.ts — round-trippable button id encoding

```typescript
// Pitfall 5 mitigation — UUIDs are 36 chars; we have 100-char budget.
// Format: "m:s:<match_id>:<game_role_id>"   (signup)
//         "m:l:<match_id>:<game_role_id>"   (leave)
//         "c:a:<clan_id>"                   (apply to clan)

export type ButtonAction =
    | { kind: 'match_signup'; matchId: string; gameRoleId: string }
    | { kind: 'match_leave'; matchId: string; gameRoleId: string }
    | { kind: 'clan_apply'; clanId: string };

export function encodeButtonId(a: ButtonAction): string {
    switch (a.kind) {
        case 'match_signup': return `m:s:${a.matchId}:${a.gameRoleId}`;
        case 'match_leave':  return `m:l:${a.matchId}:${a.gameRoleId}`;
        case 'clan_apply':   return `c:a:${a.clanId}`;
    }
}

export function decodeButtonId(s: string): ButtonAction | null {
    const parts = s.split(':');
    if (parts[0] === 'm' && parts[1] === 's' && parts.length === 4)
        return { kind: 'match_signup', matchId: parts[2], gameRoleId: parts[3] };
    if (parts[0] === 'm' && parts[1] === 'l' && parts.length === 4)
        return { kind: 'match_leave', matchId: parts[2], gameRoleId: parts[3] };
    if (parts[0] === 'c' && parts[1] === 'a' && parts.length === 3)
        return { kind: 'clan_apply', clanId: parts[2] };
    return null;
}
```

### Example 3: embeds.ts — canonical match card

```typescript
// Source: discord.js v14 EmbedBuilder + ActionRowBuilder + ButtonBuilder.
import { EmbedBuilder, ActionRowBuilder, ButtonBuilder, ButtonStyle } from 'discord.js';
import type { PublicMatchData } from '@trenchwars/shared-types';
import { encodeButtonId } from './customIds';
import { statusColor } from './colors';

export function matchCard(m: PublicMatchData): {
    embeds: EmbedBuilder[];
    components: ActionRowBuilder<ButtonBuilder>[];
} {
    const embed = new EmbedBuilder()
        .setColor(statusColor(m.status))
        .setTitle(m.name ?? `${m.host_clan?.name ?? 'TBD'} vs TBD`)
        .setDescription(`**Type:** ${m.game_match_type?.display_name ?? '—'}\n**Status:** ${m.status}`)
        .addFields(
            { name: 'Scheduled', value: `<t:${Math.floor(new Date(m.scheduled_at).getTime() / 1000)}:F>`, inline: true },
            { name: 'Host', value: m.host_clan?.name ?? '—', inline: true },
        )
        .setTimestamp();

    // One RSVP row per role group, capped at Discord's 5 buttons per row + 5 rows max.
    // For Scrim 50v50 (15 roles), we'd exceed; v1 ships a SINGLE "View roles" button
    // that pops an ephemeral select-menu — recommend planner takes this design call.

    const components: ActionRowBuilder<ButtonBuilder>[] = [];

    if (m.status === 'open') {
        const signupBtn = new ButtonBuilder()
            .setCustomId(`m:open:${m.id}`)        // opens the role-select modal/menu
            .setLabel('Sign up')
            .setStyle(ButtonStyle.Success);
        const leaveBtn = new ButtonBuilder()
            .setCustomId(`m:leave:${m.id}`)
            .setLabel('Leave')
            .setStyle(ButtonStyle.Secondary);
        components.push(new ActionRowBuilder<ButtonBuilder>().addComponents(signupBtn, leaveBtn));
    }

    return { embeds: [embed], components };
}
```

## State of the Art

| Old approach | Current approach | When changed | Impact |
|--------------|------------------|--------------|--------|
| Message commands (`!signup`) | Slash commands + interactions | Discord deprecated message-based bot UX (2022); v14 is interaction-first | Bot MUST use slash commands; no prefix-based path |
| `interaction.reply({ ephemeral: true })` | `interaction.reply({ flags: MessageFlags.Ephemeral })` | discord.js 14.13+ flagged `ephemeral` as deprecated; v14.26 prefers MessageFlags | Use the new flag API |
| Polyfilled fetch (node-fetch) | Node 22 built-in fetch / undici fetch | Node 18 LTS shipped global fetch | Use undici directly for fine-grained connection control (rcon-worker precedent) |
| `routes/api.php` registered by default in Laravel 10 | `php artisan install:api` required in Laravel 11+/12 | Laravel 11 streamlined bootstrap | Phase 5 plan 01 MUST run `install:api` |
| Sanctum config in `config/sanctum.php` only | Sanctum 4 still uses that config; abilities API unchanged | Stable since 3.x | No change |

**Deprecated / outdated:**
- `MessageEmbed` (renamed `EmbedBuilder` in v14)
- `MessageButton` (renamed `ButtonBuilder` in v14)
- `client.on('rateLimit', ...)` → `client.rest.on('rateLimited', ...)`
- `interaction.deferred = true` as a check → use `interaction.deferred` property AFTER `deferReply()` (read-only)

## Assumptions Log

| # | Claim | Section | Risk if wrong |
|---|-------|---------|---------------|
| A1 | Sanctum v4 is Laravel 12 compatible without breaking changes | §"Standard Stack" — supporting | Plan 01 install fails; pin to `^3.x` and verify |
| A2 | Horizon v5 supports PHP 8.4 + Laravel 12 | §"Standard Stack" — supporting | Plan ~03 install fails; check `composer show -a laravel/horizon` after install |
| A3 | Adding a `worker` service to docker-compose.yml is in-scope for Phase 5 (Pitfall 8 recommendation A) | §11 Pitfall 8 | Forces choice between (a) modifying compose vs (b) doc-only local-dev step; planner decides |
| A4 | `WEB_API_URL=http://web-nginx` (already in compose) is NOT in `SANCTUM_STATEFUL_DOMAINS` by default | §11 Pitfall 4 | If a future plan changes Sanctum config, bot calls 419 — verification step: check `php artisan config:show sanctum.stateful` |
| A5 | Discord's REST PUT `/guilds/{g}/members/{u}/roles/{r}` is idempotent and returns 204 whether the role was added or already present | §Pattern 5 | If non-idempotent, role-sync retries double-add — research suggests verifying via WebFetch on discord.com/developers/docs/resources/guild#add-guild-member-role |
| A6 | One bot instance is sufficient for v1 (no sharding) | §3 outbound delivery | Multi-region deploy would require Redis-backed leader election or sticky outbound claim — Pattern 4's `lockForUpdate` works for multi-instance but adds latency |
| A7 | The Filament `DiscordOutboundMessageResource` (mentioned in CONTEXT.md) is a read-only resource with a "Retry" custom action that flips `status='failed' → 'pending'` | §3 + CONTEXT.md "Filament additions" | If admin needs more than retry (edit payload, change target channel), shape needs revisit in plan task design |
| A8 | One Discord guild = D-003 LOCKED; we do not need multi-guild support in v1 | §"Architecture Patterns" | Multi-guild would force per-guild outbound row + token issuance — out of scope per D-003 |
| A9 | Slash commands are guild-scoped (instant) not global (1h propagation) — bot registers on every boot to ONE guild_id env var | §Pattern 3 | If we ever go multi-guild, the registration pattern changes; D-003 prevents this in v1 |
| A10 | `customId` 100-char limit applies after PUT to Discord (not on the local Buffer) | §Pitfall 5 | If discord.js validates earlier, our 84-char design has more slack than expected — net positive |
| A11 | Bot has no need for `MessageContent` privileged intent (we don't read message text, only interaction payloads) | §Pitfall 6 | If a future feature needs message scanning (e.g., "delete spam"), enable in dev portal |

## Open Questions

1. **Channel resolution: how does the bot know which channel is the host clan's announce channel?**
   - What we know: `discord_outbound_messages` has a `channel_id` column (CONTEXT.md specifics).
   - What's unclear: WHO populates the channel_id when a match is created? Recommendation: add a `discord_announce_channel_id` text column to `clans` table in Phase 5 plan 02 migration (admin-editable via Filament ClanResource). The MatchObserver from Phase 4 reads `$match->hostClan->discord_announce_channel_id` and writes it into the outbound row.
   - Recommendation: solve in plan 02 (migrations) — add the column + Filament edit field; document fallback (`null` → use guild's general default channel ID from `discord_guild` singleton).

2. **Bot acting-as-user when the Discord user has never logged into the website**
   - What we know: D-002 says Discord ID is canonical; first login auto-creates User+Player+PlayerPrivacy (Phase 1 plan 01-09).
   - What's unclear: Does `/match signup` from a Discord user who has never visited the website succeed (auto-provision) or fail (ResolveBotActsAsUser 422)?
   - Recommendation: FAIL with a friendly ephemeral message: "You need to log in at https://trenchwars.example.com first." Auto-provisioning from Discord-only signal bypasses the OAuth audit trail and the PlayerPrivacy default-construction logic — keep the website login as the canonical first-touch surface.

3. **Filament admin retry action for failed outbound messages**
   - What we know: CONTEXT.md "Filament additions" lists `DiscordOutboundMessageResource (read-only; admin can view + retry failed messages)`.
   - What's unclear: Retry flips status from `failed` → `pending`? Or duplicates the row? Recommendation: flip + zero `attempts` + clear `backoff_until` — simpler and preserves the row's audit trail.
   - Recommendation: Solve in plan ~10 (Filament resource plan).

4. **Bot service account User in the database — needed?**
   - What we know: Sanctum tokens are issued from a User row.
   - What's unclear: Do we create a dedicated `bot-service` User row to own the bot's Sanctum token, or attach it to an existing admin user?
   - Recommendation: Dedicated row with `discord_id='SYSTEM_BOT'`, `username='Trenchwars Bot'`, soft-deletable but never deleted. `php artisan trenchwars:bot:issue-token` seeds the row + issues the token. Activity log entries from outbound-ack endpoints (where `ResolveBotActsAsUser` does NOT run) attribute to this user — clearly disambiguated from human admins.

5. **`/me` vs `/profile` — what's the boundary?**
   - What we know: ROADMAP lists both as distinct commands.
   - Recommendation: `/me` = current Discord user's own profile (no privacy filter; they see everything about themselves); `/profile [@user]` = look up someone else's profile (PlayerPrivacyGate applies). Document in plan task design.

## Environment Availability

| Dependency | Required by | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| Docker + docker-compose | All dev work (D-021) | ✓ | (per Phase 1) | — |
| Node 22 (host or container) | Bot dev | ✓ | (per Phase 1 + bot Dockerfile) | — |
| pnpm 9.15+ | Workspace install | ✓ | (per Phase 1 corepack) | — |
| Discord Developer App (with bot, slash command perms, GuildMembers intent) | Bot connects to Discord | **?** | — | Operator MUST create one and supply DISCORD_BOT_TOKEN / DISCORD_APPLICATION_ID / DISCORD_GUILD_ID. Document in plan 01 deploy summary. |
| Redis 7 (running) | Sanctum tokens session + Horizon queue | ✓ | (per docker-compose.yml line 139) | — |
| Postgres 16 (running) | Sanctum tokens + outbound table | ✓ | (per docker-compose.yml line 121) | — |
| Laravel Sanctum | Bot↔web auth | ✗ | — | **Install in Phase 5 plan 01** (`composer require laravel/sanctum && php artisan install:api`) |
| Laravel Horizon | Role-sync job retries | ✗ | — | **Install in Phase 5 plan ~03** (`composer require laravel/horizon && php artisan horizon:install`) |
| `discord.js`, `undici`, `ioredis` (bot side) | Bot runtime | ✗ | — | **Install in Phase 5 plan 01** via `docker compose exec bot pnpm add discord.js undici ioredis` |
| `worker` docker-compose service | Horizon worker locally | ✗ | — | **Add in Phase 5 plan 01** (Pitfall 8, recommendation A) — or document as manual `make horizon` step (recommendation B) |

**Missing dependencies with no fallback:**

- Discord Developer Application setup (operator gates this — must be done before plan 01 can dry-run; document in plan 01 prereqs)

**Missing dependencies with fallback:**

- All Laravel/Node libraries (install scripted in plan 01)
- `worker` service (planner-decision in plan 01)

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework (web) | Pest 4.7 + pestphp/pest-plugin-laravel 4.0 (Phase 1+ established) |
| Framework (bot) | Vitest 2.x (Phase 1 established; `apps/bot/vitest.config.ts` already wired) |
| Config files | `apps/web/phpunit.xml`, `apps/web/tests/Pest.php`, `apps/bot/vitest.config.ts` |
| Quick run command (web) | `make pest ARGS="--filter=BotApi"` |
| Full suite (web) | `make pest` |
| Quick run command (bot) | `docker compose exec bot pnpm test --filter=match` |
| Full suite (bot) | `docker compose exec bot pnpm test` |

### Phase Requirements → Test Map

| Req / SC | Behavior | Test type | Automated command | File exists? |
|----------|----------|-----------|-------------------|--------------|
| SC-1 | `/match info` slash command renders correct ephemeral embed | bot unit | `docker compose exec bot pnpm test commands/match` | ❌ Wave 0 |
| SC-1 | `/clan list` slash command renders paginated clan list | bot unit | `docker compose exec bot pnpm test commands/clan` | ❌ Wave 0 |
| SC-1 | `/profile`, `/me` respect PlayerPrivacy tier | bot unit + web feature | `pnpm test commands/profile` + `pest --filter=BotApiUserMeTest` | ❌ Wave 0 |
| SC-1 | All slash command handlers call `deferReply` before any await | bot static | `pnpm test commands/_deferReply.test.ts` (custom AST/regex check or convention test) | ❌ Wave 0 |
| SC-2 | POST `/api/bot/matches/{m}/signups` invokes MatchSignupService and returns slot DTO | web feature | `make pest ARGS="--filter=BotApiMatchSignupTest"` | ❌ Wave 0 |
| SC-2 | Missing X-Bot-Acts-As-User on signup → 422 | web feature | `make pest ARGS="--filter=BotApiMatchSignupTest::missing_acts_as_returns_422"` | ❌ Wave 0 |
| SC-2 | Token without `bot:act-as-user` ability → 403 | web feature | `make pest ARGS="--filter=BotApiAbilitiesTest"` | ❌ Wave 0 |
| SC-3 | MatchObserver create writes pending outbound row | web feature | `make pest ARGS="--filter=DiscordOutboundOnMatchCreateTest"` | ❌ Wave 0 |
| SC-3 | GET `/api/bot/outbound-messages?status=pending` atomically claims (transitions to dispatching + bumps attempts) | web feature | `make pest ARGS="--filter=BotApiOutboundClaimTest"` | ❌ Wave 0 |
| SC-3 | POST `/outbound-messages/{id}/sent` transitions dispatching → sent + stores sent_message_id | web feature | `make pest ARGS="--filter=BotApiOutboundAckTest"` | ❌ Wave 0 |
| SC-3 | Bot outbound worker poll → render → mark-sent loop (mocked API client) | bot unit | `pnpm test services/outbound` | ❌ Wave 0 |
| SC-4 | ClanMembership create dispatches SyncDiscordRolesJob | web feature | `make pest ARGS="--filter=SyncDiscordRolesJobDispatchTest"` | ❌ Wave 0 |
| SC-4 | SyncDiscordRolesJob writes outbound row on handle() | web feature | `make pest ARGS="--filter=SyncDiscordRolesJobTest"` | ❌ Wave 0 |
| SC-4 | guildMemberUpdate handler diffs cache + POSTs deltas (mocked API client) | bot unit | `pnpm test events/guildMemberUpdate` | ❌ Wave 0 |
| SC-4 | POST `/discord-events/role-change` no-ops when matching outbound row was sent in last 60s (Pitfall 10) | web feature | `make pest ARGS="--filter=DiscordEventRoleChangeEchoSuppressionTest"` | ❌ Wave 0 |
| SC-5 | ResolveBotActsAsUser correctly rebinds auth so activity_log causer is the human | web feature | `make pest ARGS="--filter=ResolveBotActsAsUserMiddlewareTest"` | ❌ Wave 0 |
| SC-5 | Missing token → 401; wrong abilities → 403; unknown discord_id → 422 | web feature | `make pest ARGS="--filter=BotApiAuthMatrixTest"` | ❌ Wave 0 |
| SC-5 | activity_log row for signup-via-bot shows causer=User (not bot service account) | web feature | included in `BotApiMatchSignupTest::audit_log_attributes_human_causer` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `make pest ARGS="--filter=BotApi"` (~ few seconds) + `pnpm test --filter=<module>` (~1s)
- **Per wave merge:** `make pest` (full web suite — must stay green at 493 + Phase-5-delta) + `pnpm test` (full bot suite)
- **Phase gate:** All gates green: Pest full + Pint + PHPStan L8 + vue-tsc + shared-types typecheck + `pnpm --filter @trenchwars/bot test` + `pnpm --filter @trenchwars/bot run lint` + `pnpm --filter @trenchwars/bot run typecheck`

### Wave 0 Gaps

All test files for Phase 5 are NEW (none exist yet). Wave 0 of Phase 5 plan 01 MUST create stub test files (matching Phase 4 plan 04-01 idiom):

- [ ] `apps/web/tests/Feature/Bot/BotApiMatchSignupTest.php` — Wave 0 stub with `markTestIncomplete('Placeholder Wave 0 — Phase 5 plan 06 implements')`
- [ ] `apps/web/tests/Feature/Bot/BotApiMatchSignupAbilitiesTest.php` — same idiom
- [ ] `apps/web/tests/Feature/Bot/BotApiOutboundClaimTest.php`
- [ ] `apps/web/tests/Feature/Bot/BotApiOutboundAckTest.php`
- [ ] `apps/web/tests/Feature/Bot/BotApiUserMeTest.php`
- [ ] `apps/web/tests/Feature/Bot/BotApiAuthMatrixTest.php`
- [ ] `apps/web/tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php`
- [ ] `apps/web/tests/Feature/Bot/ResolveBotActsAsUserMiddlewareTest.php`
- [ ] `apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php`
- [ ] `apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php`
- [ ] `apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php`
- [ ] `apps/bot/tests/commands/match.test.ts` — Vitest stub: `it.todo('match info renders embed')`
- [ ] `apps/bot/tests/commands/clan.test.ts`
- [ ] `apps/bot/tests/commands/profile.test.ts`
- [ ] `apps/bot/tests/components/rsvpButton.test.ts`
- [ ] `apps/bot/tests/components/signupModal.test.ts`
- [ ] `apps/bot/tests/services/outbound.test.ts`
- [ ] `apps/bot/tests/events/guildMemberUpdate.test.ts`
- [ ] `apps/bot/tests/lib/customIds.test.ts`
- [ ] `apps/bot/tests/lib/embeds.test.ts`

## Security Domain

### Applicable ASVS L1 Categories

| ASVS category | Applies | Standard control |
|---------------|---------|------------------|
| V2 Authentication | yes | Sanctum bearer tokens; `expires_at` set on bot token (90-day rotation) |
| V3 Session Management | yes | Sanctum + `last_used_at` tracking; stateless bearer-only path; no Sanctum stateful cookies on `/api/bot/*` |
| V4 Access Control | yes | Sanctum abilities (`bot:read`, `bot:act-as-user`, `bot:write-outbound`, `bot:reconcile`) + `ResolveBotActsAsUser` middleware; route-level granularity |
| V5 Input Validation | yes | FormRequest classes on every POST endpoint (`StoreBotMatchSignupRequest`, `MarkOutboundSentRequest`, `RoleChangeEventRequest`); uuid/snowflake type validation |
| V6 Cryptography | yes | Sanctum tokens hashed at rest (SHA-256, Sanctum-default); TLS in production via Railway; no hand-rolled crypto |
| V7 Error Handling | yes | API responses return generic error keys (matches.signup.error.*), not stack traces; bot scrubs token from log output |
| V8 Data Protection | yes | PlayerPrivacy gate (Phase 2) already applied to `/api/bot/users/me`; bot never sees private fields it shouldn't |
| V9 Communication | yes | TLS in production (Railway-managed); HTTP inside docker network in dev (D-021 boundary, accepted) |
| V13 API Security | yes | Bearer-only path; abilities-based authz; rate limiting per route via Laravel `throttle:bot` (60 req/min) |

### Known Threat Patterns for Phase 5

| Pattern | STRIDE | Standard mitigation |
|---------|--------|---------------------|
| Stolen bot token used to act as arbitrary user via X-Bot-Acts-As-User | Spoofing / EoP | Token has limited abilities; `bot:act-as-user` ability is required AND `ResolveBotActsAsUser` only resolves users with valid `discord_id` (a stolen token can spoof to ANY existing user — accept; mitigated by token rotation + `last_used_at` monitoring); audit-log attribution still tracks the impersonated user, alerting forensically |
| Discord-side actor manually adds clan role to bypass website ClanApplication flow | Tampering | guildMemberUpdate handler triggers reconciliation; web side could either auto-apply OR require explicit officer approval (recommend: auto-apply for v1 because Discord-side mod intent is trusted by D-003; log conspicuously) |
| Replay of an old `/outbound-messages/{id}/sent` POST | Replay / Tampering | Idempotent in current design (mark-sent on already-sent row = no-op + 200) — explicit test in BotApiOutboundAckTest |
| Slash command flooding (script attack) | DoS | Discord-side rate limits per user (5/sec)) + Laravel `throttle:bot,60,1` per token; outbound poller is independent so DoS on slash side cannot starve outbound delivery |
| RSVP button id forged with arbitrary match_id | Tampering / EoP | MatchSignupService already enforces D-010 row-locked capacity + tag access; signup will reject if state doesn't permit. Audit-log captures the (forged) attempt |
| `X-Bot-Acts-As-User` header set to admin's discord_id from a stolen bot token | EoP | Service-level: Sanctum tokens have abilities + expiry; rotation procedure; monitor `last_used_at` for anomalies. Defense-in-depth: ResolveBotActsAsUser CAN optionally check `User->is_admin` and reject acts-as on admin users (v1 trade-off — recommend: do NOT add this gate; admin actions from Discord are explicitly out of scope for the bot anyway) |
| Outbound message payload contains injectable content (script tags, @everyone mentions) | Injection | Discord embeds don't execute script; `@everyone` mentions controlled by `allowed_mentions` field in MessagePayload — bot MUST set `allowed_mentions: { parse: [] }` on every outbound to prevent mass-ping abuse via crafted match names |

## Sources

### Primary (HIGH confidence)

- Context7 `/websites/discord_js_packages_discord_js_14_26_2` — discord.js v14.26 API (Client, REST, EmbedBuilder, ButtonBuilder, ChatInputCommandInteraction, ModalSubmitInteraction, DefaultRestOptions, RateLimitData)
- Context7 `/discordjs/guide` — discord.js Guide patterns (slash command registration, interactionCreate dispatcher, modal flow, button collectors, role management, GuildMemberUpdate)
- Context7 `/laravel/sanctum` — Sanctum personal access tokens, abilities, CheckAbilities + CheckForAnyAbility middleware, Sanctum::actingAs test helper
- Context7 `/laravel/horizon` — job retries, failed-job retry REST API, tagging, Silenced interface
- npm registry verifications (2026-05-13): discord.js@14.26.4, @discordjs/rest@2.6.1, undici@8.2.0, ioredis@5.10.1, vitest@4.1.6
- Phase 4 verification report (`.planning/phases/04-matches-manual/04-PHASE-VERIFICATION.md`) — D-04-03-A LOCKED canonical naming binding for Phase 5+ + complete MatchSignupService primitive
- `apps/web/composer.json` — confirmed Sanctum / Horizon NOT yet installed
- `apps/web/bootstrap/app.php` — confirmed no `api:` routes registration; `install:api` Artisan required
- `docker-compose.yml` — bot service already configured with env: WEB_API_URL, WEB_API_TOKEN, DISCORD_BOT_TOKEN, DISCORD_APPLICATION_ID

### Secondary (MEDIUM confidence)

- WebSearch (2026-05-13) — Discord interaction 3s window + 15-min followUp window confirmed via discordjs.guide/slash-commands/response-methods + discord.com/developers/docs/interactions/receiving-and-responding

### Tertiary (LOW confidence)

- None — all critical claims verified against Context7 or empirically against the codebase

## Project Constraints (from CLAUDE.md)

| Directive | Compliance plan |
|-----------|-----------------|
| §1 Container-only commands | All `composer require`, `pnpm add`, `artisan`, `pest` invocations in Phase 5 plans use `make` aliases or `docker compose exec` — no host PHP/Node calls |
| §2 Stack & versions | Bot stack pinned to Node 22 + discord.js@^14.26 + undici (per CLAUDE.md table); Sanctum + Horizon added as new entries (planner: update CLAUDE.md §2 table in plan 01) |
| §3 Pint + PHPStan L8 | All new web-side PHP files must pass `make pint ARGS="--test"` + `make phpstan` |
| §4 Pest, not PHPUnit syntax | All new web tests use `it('does the thing', ...)` / `expect(...)` |
| §5 Path conventions | Bot code in `apps/bot/`; web-side controllers/middleware/jobs in `apps/web/app/Http/`, `apps/web/app/Jobs/`; migration in `apps/web/database/migrations/` |
| §6 Security — never commit secrets | `.env.example` has empty `WEB_API_TOKEN=` placeholder (already in compose); Sanctum tokens issued by Artisan command, never committed |
| §6 Security — bot uses scoped token | Implemented exactly as Pattern 1 above (abilities `bot:read`, `bot:act-as-user`, `bot:write-outbound`, `bot:reconcile`) |
| §6 Security — activity log append-only | LogsActivity trait on `DiscordOutboundMessage` model; Filament admin UI for outbound resource MUST NOT expose edit/delete on activity_log rows |
| §7 i18n every UI string via `__()` / `t()` | Bot responses in English at v1 are explicit deferred (CONTEXT.md); web-side error keys (`matches.signup.error.*`) reuse Phase 4 lang/en/matches.php — no new keys hardcoded |
| §8 Bot is a thin display layer | Architecturally enforced: bot writes ZERO DB state; every action calls Laravel API |
| §8 One Discord guild | discord.js client registers commands only to `env.DISCORD_GUILD_ID` (one value, not a list) |
| §9 D-04-03-A LOCKED (`App\Models\GameMatch`) | All Phase 5 bot controllers use `use App\Models\GameMatch;` — no aliases |

## Metadata

**Confidence breakdown:**

- Standard stack: **HIGH** — all libraries verified against Context7 + npm registry within the last 24h; versions pinned and currently maintained
- Architecture (Sanctum + outbound table + Horizon job pattern): **HIGH** — every component is canonical Laravel/discord.js idiom; the unified outbound-table pattern is a clear design choice (alternatives explicitly considered and rejected with rationale)
- Pitfalls: **HIGH** — Pitfall 1 (3s window), Pitfall 2 (rate limit), Pitfall 4 (Sanctum stateful), Pitfall 6 (intents), Pitfall 10 (echo loop), Pitfall 11 (modal submit window) are all sourced from Context7/Discord docs; Pitfall 5 (customId length) and Pitfall 8 (worker service) are codebase-empirical
- Validation architecture: **HIGH** — every SC maps to a named test; Wave 0 stub list is exhaustive
- Open questions: **MEDIUM** — Q1 (channel resolution) and Q3 (Filament retry shape) are real design calls; planner should resolve in plan tasks rather than research

**Research date:** 2026-05-13
**Valid until:** 2026-06-13 (30-day estimate; discord.js ships frequently — verify version pin if planning slips past June)
