# Phase 8: RCON automation — Research

**Researched:** 2026-05-14
**Domain:** Hell Let Loose Community RCON (CRCON) live event capture + HMAC-signed worker→web ingest + per-match server booking
**Confidence:** HIGH (CRCON streaming protocol, HMAC pattern, Postgres EXCLUDE, Laravel encrypted casts all verified against official sources)

## Summary

Phase 8 closes the round-1 acceptance loop by wiring `apps/rcon-worker` (a Node 22 thin normaliser, D-021 containerised) to CRCON's WebSocket log stream and to `apps/web` via an HMAC-signed internal HTTP channel. The worker is purely a wire-protocol translator — every business decision (status flips, MVP, result row writes) stays in Laravel services that mirror the Phase 4 `MatchResultService` / `MatchSignupService` row-lock pattern.

Three primary technical seams: (1) CRCON's `ws://{host}/ws/logs` streaming endpoint with Bearer-token auth + `last_seen_id` resumption, (2) HMAC-SHA256 over `(timestamp + raw body)` with 60s freshness window and Redis-backed single-use nonce store (CON-arch-rcon-to-web-comm, mirrors the Phase 5 bot-signature precedent), and (3) Postgres `EXCLUDE USING gist` on `tstzrange` with `btree_gist` for double-booking prevention on `match_server_bookings`.

**Primary recommendation:** Adopt **CRCON v9.7+ `/ws/logs` WebSocket** (Bearer-token authenticated) for live capture, **HMAC-SHA256 hex-encoded signature header `X-Rcon-Signature` with separate `X-Rcon-Timestamp` and `X-Rcon-Nonce` headers** for worker→web auth, **Postgres `tstzrange` + `EXCLUDE USING gist` with `btree_gist` extension** for booking overlap prevention, and **a `source` enum column on `match_results` (added in Phase 8 — does NOT exist in the Phase 4 migration)** so the manual-override-always-wins invariant is enforced by an updated `MatchResultService` that refuses to overwrite a row where `source='manual'`.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-005** RCON via CRCON; league deploys CRCON alongside servers.
- **D-019** CRCON live capture + manual override.
- **D-012** Filament MatchServerResource.
- **D-021** rcon-worker runs in `trenchwars-rcon-worker` container (Phase 1 scaffolded).
- **CON-arch-rcon-to-web-comm** — HMAC-signed worker→web messages, 60s replay window.
- **D-04-03-A** `App\Models\GameMatch`.
- **D-004 mirror** Bot is thin display — RCON worker is "thin normaliser" — All business logic stays in `web` (Laravel). Worker is Node 22 + undici + ws + crypto — opens CRCON session, normalises wire format, signs+POSTs to web. NO DB. NO business logic.

### Claude's Discretion
- Internal endpoint shapes, table column shape (within ContextMD listed columns).
- Worker reconnect / backoff strategy, batching policy, nonce store choice.
- Filament "Test Connection" action implementation (admin button → enqueue test job → worker polls and reports result, vs. synchronous HTTP probe from web — recommendation in this research).
- `match_results.source` enum column addition migration (Phase 4 did NOT ship it despite CONTEXT.md claim — **see Gap A1 below**).

### Deferred Ideas (OUT OF SCOPE — do not research)
- Live web view of match in progress (Phase 9+).
- Replay file storage/parsing (out of scope v1).
- Cross-server scrim coordination (multi-server tournaments — Phase 9+).
- Per-player ELO recompute from RCON history (Phase 9+).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-goal-rcon-history | When a match is played on a registered match server, MatchResult + per-player MatchPlayerStat rows populate automatically from CRCON events. | CRCON `/ws/logs` schema (verified), MatchPlayerStat upsert pattern, normalisation table, end-to-end happy path test. |
| REQ-constraint-league-owns-servers | League-managed `match_servers` entries with encrypted CRCON creds; no per-clan-managed servers in round 1. | Laravel `encrypted:array` cast, MatchServerResource Filament admin pattern, encrypted credential payload shape. |
| REQ-success-end-to-end-scrim | End-to-end flow without manual data entry on happy path. | End-to-end Pest test architecture (SC-5 simulation), CRCON event fixtures, MatchPlayerStat idempotent upsert, manual-override wins invariant. |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| CRCON WebSocket session management (connect, login, resume) | `apps/rcon-worker` (Node) | — | The wire-protocol concerns (reconnect, backoff, `last_seen_id` resumption) live in the worker so the web app never speaks CRCON's protocol directly. |
| Event normalisation (CRCON action string → canonical `match_event_type`) | `apps/rcon-worker` (Node) | — | Worker is the "thin normaliser" — translates CRCON shape to canonical shape so adding a new game in the future is data-only at the web side. |
| HMAC signing of outbound payloads | `apps/rcon-worker` (Node) | — | Signing key only lives in worker container env (`WEB_HMAC_SECRET`); web never sends to worker (one-way ingest). |
| HMAC verification + nonce dedupe | `apps/web` (Laravel middleware) | Redis (nonce store) | Stateless middleware on `/api/internal/match/{id}/events` gates ALL inbound RCON traffic. Redis stores seen nonces with 60s TTL = replay-window-aligned. |
| MatchResult upsert + status flip + manual-override invariant | `apps/web` Service (`MatchResultService::upsertFromRcon`) | Postgres (`source` enum + CHECK) | All write-path business logic stays in the same service that Filament's admin override uses — mirror of Phase 4 pattern; database CHECK is defence-in-depth. |
| MatchPlayerStat aggregation from event stream | `apps/web` Service (`MatchPlayerStatAggregatorService`) | Postgres (UNIQUE on `(match_id, player_id)`) | Idempotent upsert is the only safe shape — worker may resend events on reconnect; web replays the stream into the aggregate. |
| Server booking overlap prevention | Postgres (`EXCLUDE USING gist` constraint) | — | Race-free booking is a database-tier guarantee, not an application-tier check — same posture as Phase 2 "one active membership" partial UNIQUE. |
| Server credential encryption at rest | Laravel (`encrypted:array` cast) | Postgres `text` column | App-key-derived envelope encryption; rotating `APP_KEY` rotates secrets — no CRCON creds in plaintext anywhere. |
| Bot result announce with MVP stats | `apps/web` outbound builder | `apps/bot` (display only — Phase 5 wired) | Extends the existing Phase 5 `DiscordOutboundPayloadBuilder` with a new `match_result_announce` variant; bot consumes the existing outbox table. |

## Standard Stack

### Core (apps/rcon-worker)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `ws` | `^8.20` | WebSocket client for CRCON `/ws/logs` connection. | The de-facto Node WebSocket client; the `ws` library is the most popular WebSocket implementation for Node.js [CITED: https://github.com/websockets/ws]; `^8.18`+ stable since 2024, npm shows `8.20.1` current [VERIFIED: npm view ws version → 8.20.1]. |
| `undici` | `^7.x` | HTTP/1.1 client for outbound POST to web (HMAC-signed). | Undici v7 introduces stricter compliance with fetch() spec, WebSocketStream, caching, customizable interceptors [CITED: https://blog.platformatic.dev/undici-v7-is-here]; `8.20.1` available on npm but introduces dispatcher refactor — pin to v7 to avoid Node 22 built-in-fetch incompatibility documented at [CITED: https://github.com/nodejs/undici/issues/3901]. Already named in CLAUDE.md stack table. |
| `ioredis` | `^5.10` | Optional: in-process queue of pending events when web is unreachable. | Matches the bot's existing Redis client choice (Phase 5); npm shows `5.10.1` current [VERIFIED: npm view ioredis version → 5.10.1]. |
| `pino` | `^9` | Structured JSON logging — Railway log aggregator parses JSON. | Sub-millisecond logger; bot uses same pattern. npm shows `10.3.1` available [VERIFIED: npm view pino version → 10.3.1]; `^9` is the conservative pin proven in Phase 5. |
| `zod` | `^4` | Validate CRCON message shape AND own outbound payload shape before signing. | Phase 5 bot uses zod. npm shows `4.4.3` current [VERIFIED: npm view zod version → 4.4.3]. |
| `@trenchwars/shared-types` | workspace | Canonical TS types generated from `spatie/laravel-data` DTOs (D-020). | The internal events POST contract is a DTO emitted to `packages/shared-types`; the worker imports it so a shape change in Laravel is a type error in the worker on CI. |

### Core (apps/web)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^12` | Inherits — encrypted casts, route middleware, DB transactions. | Locked D-001. The encrypted cast will encrypt a model's attribute value using Laravel's built-in encryption features [CITED: https://laravel.com/docs/12.x/eloquent-mutators]. |
| `laravel/horizon` | `^5` | Already in stack (Phase 5+). Hosts the `match-server-test-connection` queue and any future RCON-side jobs. | CLAUDE.md stack table line 44. |
| `filament/filament` | `^3.3` | MatchServerResource + TestConnectionAction in admin panel. | D-012, locked. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Postgres `btree_gist` extension | — | Required for `EXCLUDE USING gist` on a composite of scalar (`server_id`) + range (`tstzrange`). | Enable in the `match_server_bookings` migration (mirrors the Phase 1 `uuid-ossp` / `citext` pattern — extensions land in their owning migration, NOT the postgres image). [CITED: https://amitavroy.com/articles/postgresql-gist-exclusion-constraintthe-database-evel-answer-to-double-bookings] |
| `node:crypto` (built-in) | Node 22 | HMAC-SHA256 signing in worker; `timingSafeEqual` for constant-time compare. | No external dep — Node std lib. Comparison functions must always take the same amount of time, regardless of where the mismatch occurs [CITED: https://medium.com/@mohanpathi.s/hmac-authentication-for-api-security-a-comprehensive-implementation-guide-for-node-js-ab01bebfeb68]. |
| `hash_hmac()` + `hash_equals()` (built-in PHP) | PHP 8.4 | HMAC verification in web middleware. | PHP std lib; `hash_equals` is the constant-time compare. No third-party HMAC package needed. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CRCON `/ws/logs` (streaming) | `POST /api/get_recent_logs` polling | Polling adds latency, more requests, and misses fast-emit events between polls. `/ws/logs` is purpose-built and already supports `last_seen_id` resume. Skip polling. |
| `ws` library | `WebSocket` from undici (`WebSocketStream`) | Undici v7 includes WebSocketStream [CITED: undici v7 blog] but it's newer and less battle-tested for long-lived connections to a Django/Channels backend. `ws` is the safer pick. |
| Custom nonce store | Reuse Redis (Phase 1 datastore) | Redis already provides atomic operations that prevent the race condition in the default dict storage [CITED: https://dev.to/raselmahmuddev/protecting-api-requests-using-nonce-redis-and-time-based-validation-11nd]. Using a separate datastore for nonces is wasteful. |
| Two-channel auth (HMAC for events + Sanctum for credentials fetch) | Single HMAC for both worker→web endpoints | Worker is one trust principal; Sanctum is designed for user/client tokens with abilities. HMAC is simpler for a single shared-secret machine principal. Stick with HMAC for ALL worker→web traffic. |
| HMAC over `(method + path + timestamp + body)` (Stripe-style) | HMAC over `(timestamp + body)` only (per CON-arch-rcon-to-web-comm) | The CONTEXT.md / PROJECT.md lock specifies `(timestamp + body)` only. Method/path coverage adds replay-across-endpoints protection but the spec is locked — follow it. |

**Installation (apps/rcon-worker):**
```bash
docker compose exec rcon-worker pnpm add ws@^8 undici@^7 ioredis@^5 pino@^9 zod@^4
docker compose exec rcon-worker pnpm add -D @types/ws @types/node
```

**Version verification (2026-05-14):**
- `ws@8.20.1` — published recent; [VERIFIED: npm view ws version]
- `undici@8.2.0` available — but pin to **`^7`** for Node 22 built-in-fetch compat [CITED: https://github.com/nodejs/undici/issues/3901]
- `ioredis@5.10.1` — [VERIFIED: npm view ioredis version]
- `pino@10.3.1` available — pin to `^9` matching bot precedent
- `zod@4.4.3` — [VERIFIED: npm view zod version]

## CRCON API

> Source: CRCON wiki "Developer Guides ‐ CRCON API" and "Developer Guides ‐ Streaming Logs" pages [CITED: https://github.com/MarechJ/hll_rcon_tool/wiki/Developer-Guides-%E2%80%90-CRCON-API].

### Base URL Pattern
Per-server, per-port: `http://(VPS IP):801X/api/` where `X` is the server index (8010 for server 1, 8011 for server 2, etc.) [CITED: CRCON API wiki].

### Authentication
**Two supported modes** (both verified from `rconweb/api/auth.py` source):
1. **Bearer token** (preferred for worker): `Authorization: Bearer <django-api-token>` header. Token created via CRCON admin panel `/admin` → Django API Keys → create key for a permissioned user, copy-once display [CITED: CRCON API wiki].
2. **Session cookie** (`sessionid`): POST `/api/do_login` with `{username, password}` JSON → server sets `sessionid` cookie [CITED: rconweb/api/auth.py: `BEARER = ("BEARER", "BEARER:")` + `/do_login` endpoint].

**Worker uses Bearer ONLY.** Storing username+password in `match_servers` would be a worse posture than storing a scoped API token; require the league IT operator to create a least-privileged Django user and an API token for the worker. The token is what gets encrypted in `match_servers.credentials_encrypted`.

### WebSocket Log Stream
- **URL:** `ws://{host}:{port}/ws/logs` (adjust port to `RCONWEB_PORT` or `RCONWEB_PORT_HTTPS`) [CITED: Streaming Logs wiki].
- **Available from:** CRCON v9.7.0+. **Pin the league to v10.0.0+** (Open Question — CRCON version standardisation) — v9→v10 had API changes [CITED: "Developer Guides ‐ v9.x to v10.0.0 API Changes" wiki page]. Verify with operator before Phase 8 ships.
- **Auth:** "Requires valid API key in headers; no username/password option." [CITED: Streaming Logs wiki]. Bearer-token only.
- **Request frame** (after connection open, client sends JSON):
  ```json
  { "actions": ["KILL", "TEAM KILL", "MATCH START", "MATCH ENDED", "CONNECTED", "DISCONNECTED", "TEAMSWITCH"] }
  ```
  - `{}` = no filter, only new logs since connect.
  - `{ "last_seen_id": "1711657986-1" }` = resume from a specific stream ID (USE THIS FOR RECONNECT).
  - `{ "actions": [...] }` = filter by `AllLogTypes` enum values.
- **Response frame** (batches up to 25 logs to avoid payload issues):
  ```json
  {
    "last_seen_id": "1711657986-1",
    "logs": [
      {
        "id": "1711657986-0",
        "log": {
          "version": 1,
          "timestamp_ms": 1711657986000,
          "action": "KILL",
          "player": "username",
          "steam_id_64_1": "76561199...",
          "weapon": "KARABINER 98K",
          "message": "parsed log content"
        }
      }
    ],
    "error": null
  }
  ```
  [CITED: Streaming Logs wiki, payload structure quoted verbatim].
- **Stream ID format:** `timestamp-increment` (e.g., `"1711657986-0"`) — suffix handles multiple logs at identical timestamps [CITED: Streaming Logs wiki].
- **Permissions required on the Django user that owns the token:** `api.can_view_log_stream_config`, `api.can_change_log_stream_config` [CITED: Streaming Logs wiki].

### Log Action Types (`AllLogTypes` enum)
Verified from `rcon/types.py` [CITED: https://github.com/MarechJ/hll_rcon_tool/blob/master/rcon/types.py]:

```
ADMIN | ADMIN ANTI-CHEAT | ADMIN BANNED | ADMIN IDLE | ADMIN KICKED | ADMIN MISC | ADMIN PERMA BANNED
CAMERA | CHAT | CHAT[Allies] | CHAT[Allies][Team] | CHAT[Allies][Unit] | CHAT[Axis] | CHAT[Axis][Team] | CHAT[Axis][Unit]
CONNECTED | DISCONNECTED
KILL | TEAM KILL | TEAMSWITCH
MATCH | MATCH ENDED | MATCH START
TK | TK AUTO | TK AUTO BANNED | TK AUTO KICKED
VOTE | VOTE COMPLETED | VOTE EXPIRED | VOTE PASSED | VOTE STARTED
```

**For Phase 8 we subscribe ONLY to:** `MATCH START`, `MATCH ENDED`, `KILL`, `TEAM KILL`, `CONNECTED`, `DISCONNECTED`, `TEAMSWITCH`. Everything else is noise (admin/vote/camera/chat) and would inflate `match_events` for zero round-1 value.

### REST endpoints worker may also call
- `GET /api/get_api_documentation` — discover all 278 endpoints at runtime (NOT needed for round 1) [CITED: CRCON API wiki].
- `GET /api/get_map_rotation` — `Authorization: Bearer <token>` header [CITED: CRCON API wiki cURL example]. **Use this for Test Connection** — it's a no-side-effect probe that proves the token + URL + server are alive.
- `POST /api/get_historical_logs` — backfill on session open IF we missed events. Body `{"log_type": "KILL", "limit": 9999}` [CITED: CRCON API wiki cURL example]. Less reliable than `last_seen_id` resume; prefer WebSocket resume.

## Worker Architecture

### System Architecture Diagram

```
┌──────────────────┐         ws://crcon:801X/ws/logs          ┌─────────────────────┐
│   apps/rcon-     │ ───────── Bearer <token> ──────────────▶ │  CRCON Django/      │
│   worker         │ ◀────── JSON log batches (≤25) ───────── │  Channels backend   │
│   (Node 22)      │         {logs:[...], last_seen_id}       │  (port 801X)        │
└──────────────────┘                                          └─────────────────────┘
        │
        │ 1. Validate (zod) shape
        │ 2. Normalise to canonical match_event_type
        │ 3. Buffer in-memory + Redis fallback queue
        │ 4. Build payload {events:[...]}
        │ 5. timestamp = now() (ms)  +  nonce = randomUUID()
        │ 6. signature = HMAC-SHA256(secret, timestamp + body)
        │
        ▼
┌──────────────────┐  POST /api/internal/match/{id}/events    ┌─────────────────────┐
│   undici fetch   │   X-Rcon-Timestamp: 1715693400000       │  apps/web Laravel   │
│   pool           │   X-Rcon-Nonce: <uuid>                  │  middleware:        │
│                  │   X-Rcon-Signature: <hex>               │  - verify timestamp │
│                  │   Content-Type: application/json        │    fresh ≤60s       │
│                  │   {events:[...]}                         │  - hash_equals      │
└──────────────────┘                                          │  - nonce SETNX Redis│
                                                              │    EX 120           │
                                                              └─────────────────────┘
                                                                       │
                                                                       │ enqueue
                                                                       ▼
                                                              ┌─────────────────────┐
                                                              │ Horizon job:        │
                                                              │ IngestMatchEvents   │
                                                              │  1. insert raw rows │
                                                              │     into            │
                                                              │     match_events    │
                                                              │  2. on MATCH_END:   │
                                                              │     dispatch        │
                                                              │     CloseMatchJob   │
                                                              └─────────────────────┘
                                                                       │
                                                                       ▼
                                                              ┌─────────────────────┐
                                                              │ MatchResultService::│
                                                              │  upsertFromRcon()   │
                                                              │ + MatchPlayerStat   │
                                                              │   AggregatorService │
                                                              │ + Discord outbound  │
                                                              │   (result announce  │
                                                              │   with per-player   │
                                                              │   MVP stats)        │
                                                              └─────────────────────┘
```

### Recommended Project Structure
```
apps/rcon-worker/
├── src/
│   ├── index.ts                 # Entry — starts BookingScheduler
│   ├── config.ts                # Env loader + zod schema for WEB_HMAC_SECRET, WEB_INTERNAL_URL
│   ├── booking/
│   │   └── BookingScheduler.ts  # Polls web every N s for bookings due now
│   ├── crcon/
│   │   ├── CrconClient.ts       # ws client + Bearer auth + last_seen_id resume
│   │   ├── CrconEventNormaliser.ts  # CRCON action → canonical match_event_type
│   │   └── types.ts             # zod schemas for CRCON wire shape (from /ws/logs)
│   ├── ingest/
│   │   ├── WebIngestClient.ts   # undici-based HMAC-signed POST
│   │   └── HmacSigner.ts        # node:crypto.createHmac + timing-safe verify (for tests)
│   ├── queue/
│   │   └── RedisFailoverQueue.ts  # buffer events when web unreachable
│   └── logging/
│       └── logger.ts            # pino instance
└── tests/
    ├── unit/                    # HmacSigner, CrconEventNormaliser, RedisFailoverQueue
    └── integration/             # vs. mock CRCON ws server + mock web
```

### Pattern 1: WebSocket reconnect with exponential backoff + jitter + `last_seen_id` resume

**What:** When the CRCON WebSocket disconnects (network blip, CRCON restart), the worker reconnects with exponential backoff plus jitter and re-subscribes from the last seen stream ID.

**When to use:** Every CRCON connection. There is no "happy path that never disconnects" — CRCON gets restarted, networks flake, Railway redeploys cycle the worker container.

**Example:**
```typescript
// Source: pattern verified against [CITED: https://dev.to/hexshift/robust-websocket-reconnection-strategies-in-javascript-with-exponential-backoff-40n1]
// and ws library practice. "Wait a base delay (1s, 2s, 4s, doubling each time) plus a random offset,
// up to a cap of 30 seconds, and reset on successful connection."
import { WebSocket } from 'ws';

class CrconClient {
  private ws: WebSocket | null = null;
  private lastSeenId: string | null = null;
  private attempt = 0;

  connect(): void {
    this.ws = new WebSocket(this.url, {
      headers: { Authorization: `Bearer ${this.token}` },
      handshakeTimeout: 10_000,
    });
    this.ws.on('open', () => {
      this.attempt = 0; // reset backoff on success
      this.ws!.send(JSON.stringify({
        last_seen_id: this.lastSeenId, // resume from where we left off
        actions: ['MATCH START', 'MATCH ENDED', 'KILL', 'TEAM KILL', 'CONNECTED', 'DISCONNECTED', 'TEAMSWITCH'],
      }));
    });
    this.ws.on('message', (data) => this.onMessage(data));
    this.ws.on('close', () => this.scheduleReconnect());
    this.ws.on('error', (err) => this.logger.warn({ err }, 'crcon ws error'));
  }

  private scheduleReconnect(): void {
    const base = Math.min(30_000, 1_000 * 2 ** this.attempt);
    const jitter = Math.random() * 1_000;
    setTimeout(() => this.connect(), base + jitter);
    this.attempt++;
  }
}
```

### Pattern 2: Application-level heartbeat (Pong-watchdog)

**What:** `ws` does NOT auto-detect a dead-but-half-open TCP connection. Send a ping every 30s; if no pong in 10s, terminate the socket and let reconnect fire.

**When to use:** Always. "Application-level heartbeats catch dead connections faster" [CITED: https://dev.to/axiom_agent/nodejs-websockets-in-production-socketio-vs-ws-scaling-and-reconnection-strategies-5b68].

**Example:**
```typescript
// Source: ws library docs + production pattern
let alive = true;
this.ws.on('pong', () => { alive = true; });
const heartbeat = setInterval(() => {
  if (!alive) { this.ws!.terminate(); return; }
  alive = false;
  this.ws!.ping();
}, 30_000);
this.ws.on('close', () => clearInterval(heartbeat));
```

### Pattern 3: BookingScheduler — open CRCON session only when a match is due

**What:** The worker doesn't keep an idle CRCON session forever. Every 30s it polls `GET /api/internal/bookings/due` (HMAC-signed) and opens a CRCON session for each booking whose `reserved_from <= now() AND reserved_to >= now() AND status='active'`.

**Why:** Per-server CRCON sessions cost connections at CRCON side; opening only during the booking window is cheaper and aligned with the "league owns servers" reality where match servers are not always running. Also matches the existing Phase 5 outbox-polling cadence (bot polls outbound messages on a similar schedule).

### Anti-Patterns to Avoid
- **Long-lived global CRCON session per worker process.** Open per-booking, close after `reserved_to`. (Long sessions hide auth/permission drift until match-time.)
- **Worker writing to Postgres directly.** Locked NO by D-004 mirror — web is the only DB writer.
- **Polling CRCON `/api/get_recent_logs` instead of `/ws/logs`.** Higher latency, more requests, can miss events between polls.
- **Re-deriving `MatchPlayerStat` rows on every event arrival.** The aggregator runs ONCE on `MATCH ENDED` over the full `match_events` stream — not per-event during the match (Pitfall 4).
- **HMAC over JSON-serialised body.** Sign the RAW bytes you send on the wire. Re-serialising on web for verification will pick a different key order in some objects and break the signature (Pitfall 1).

## HMAC Architecture

### Wire Format

**Headers on every worker→web POST** (matches CON-arch-rcon-to-web-comm spec + Stripe/GitHub webhook idiom):

```
X-Rcon-Timestamp: 1715693400000           # unix milliseconds, integer
X-Rcon-Nonce: 9f5d0a1c-4f4e-4a8e-...      # UUIDv4 from worker
X-Rcon-Signature: a3f1e9c2...             # lowercase hex HMAC-SHA256
Content-Type: application/json
```

**Signing input** (per CON-arch-rcon-to-web-comm — `HMAC SHA-256 over (timestamp + body)`):
```
input = timestamp_string + raw_body_bytes
signature = hex(hmac_sha256(WEB_HMAC_SECRET, input))
```

`timestamp_string` is the ASCII decimal of `X-Rcon-Timestamp`. The body bytes are the exact bytes posted (worker MUST sign before serialising, or capture the serialised string and sign that). Verification on web side runs over `$request->getContent()` which is the raw body.

### Worker signing (Node)

```typescript
// Source: node:crypto std lib — no external dep needed
import { createHmac, randomUUID } from 'node:crypto';
import { fetch } from 'undici';

function signAndPost(url: string, secret: string, payload: object) {
  const body = JSON.stringify(payload);
  const timestamp = Date.now().toString();
  const nonce = randomUUID();
  const signature = createHmac('sha256', secret).update(timestamp + body).digest('hex');
  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Rcon-Timestamp': timestamp,
      'X-Rcon-Nonce': nonce,
      'X-Rcon-Signature': signature,
    },
    body,
  });
}
```

### Web verification middleware (Laravel)

```php
// Source: hash_hmac() + hash_equals() PHP std lib; Redis nonce store pattern
// from [CITED: https://dev.to/raselmahmuddev/protecting-api-requests-using-nonce-redis-and-time-based-validation-11nd]
final class VerifyRconSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Rcon-Timestamp');
        $nonce     = $request->header('X-Rcon-Nonce');
        $sig       = $request->header('X-Rcon-Signature');
        $body      = $request->getContent(); // raw bytes — DO NOT re-serialise

        if (!$timestamp || !$nonce || !$sig) {
            abort(401, 'missing rcon auth headers');
        }
        // Freshness: 60s window per CON-arch-rcon-to-web-comm
        $age = abs((int) (microtime(true) * 1000) - (int) $timestamp);
        if ($age > 60_000) {
            abort(401, 'stale signature');
        }
        // Constant-time compare
        $expected = hash_hmac('sha256', $timestamp.$body, config('rcon.hmac_secret'));
        if (!hash_equals($expected, $sig)) {
            abort(401, 'bad signature');
        }
        // Single-use nonce — SETNX with TTL slightly > replay window
        $stored = Redis::set("rcon:nonce:$nonce", '1', 'EX', 120, 'NX');
        if ($stored === null || $stored === false) {
            abort(401, 'replayed nonce');
        }
        return $next($request);
    }
}
```

**Key invariants:**
1. `hash_equals` for constant-time compare — `hash_hmac` returns hex, `hash_equals` works on equal-length strings.
2. Nonce TTL = 120s (= 2× replay window) — guarantees the nonce stays in Redis past any clock-skew window.
3. Freshness check happens BEFORE nonce check — saves a Redis round-trip on stale requests.
4. Secret comes from `config('rcon.hmac_secret')` which reads `env('WEB_HMAC_SECRET')` (already in `.env.example`).

### Why nonce-on-web, not nonce-on-worker
CONTEXT.md asks "how worker stores last-used nonces locally OR how web tracks seen nonces." **Answer: web tracks them in Redis.** Reason: nonce uniqueness only matters server-side — the worker just generates a random one per request. Worker-side storage adds complexity, breaks horizontal scaling of workers, and provides zero security benefit (an attacker replaying a captured request bypasses worker storage entirely). The defence belongs at the gate.

## Schema (NEW tables)

### `match_servers`
```php
Schema::create('match_servers', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->text('name');
    $t->text('host');           // CRCON host:port (e.g., "crcon-1.league.example")
    $t->integer('port_rcon');   // CRCON web port (8010, 8011, ...)
    $t->text('region')->nullable();
    $t->jsonb('credentials_encrypted'); // {api_token: "..."} encrypted at app layer
    $t->boolean('is_active')->default(true);
    $t->timestampTz('last_test_at')->nullable();
    $t->text('last_test_status')->nullable(); // 'ok' | 'error'
    $t->text('last_test_error')->nullable();
    $t->timestamps();
});
DB::statement("ALTER TABLE match_servers ADD CONSTRAINT match_servers_last_test_status_check
               CHECK (last_test_status IS NULL OR last_test_status IN ('ok','error'));");
DB::statement('ALTER TABLE match_servers ALTER COLUMN id SET DEFAULT gen_random_uuid();');
```

Model casts:
```php
protected function casts(): array {
    return [
        'credentials_encrypted' => 'encrypted:array', // {"api_token":"..."}
        'last_test_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
```

The `encrypted:array` cast encrypts the JSON-serialised array using `APP_KEY` and stores it in the column [CITED: https://laravel.com/docs/12.x/eloquent-mutators]. "Make sure the associated database column is of sufficient size (typically `text` type or larger)" — `jsonb` is more than sufficient.

### `match_server_bookings` — with EXCLUDE constraint

```php
Schema::create('match_server_bookings', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('match_id');
    $t->uuid('server_id');
    $t->timestampTz('reserved_from');
    $t->timestampTz('reserved_to');
    $t->text('status')->default('active');
    $t->timestamps();
    $t->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $t->foreign('server_id')->references('id')->on('match_servers')->restrictOnDelete();
});
DB::statement('ALTER TABLE match_server_bookings ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement('ALTER TABLE match_server_bookings ADD CONSTRAINT match_server_bookings_status_check
               CHECK (status IN (\'active\',\'cancelled\',\'completed\'));');
// Enable extension in THIS migration (Phase 1 pattern — Pitfall 5 from Phase 1).
DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');
// Exclusion constraint — server can't host two overlapping active bookings.
DB::statement(<<<SQL
    ALTER TABLE match_server_bookings ADD CONSTRAINT match_server_bookings_no_overlap
    EXCLUDE USING gist (
        server_id WITH =,
        tstzrange(reserved_from, reserved_to, '[)') WITH &&
    ) WHERE (status = 'active');
SQL);
```

**Notes:**
- `tstzrange(..., '[)')` = half-open interval `[from, to)`. Two back-to-back bookings sharing an endpoint do NOT conflict — important so "scrim ends 21:00, next scrim starts 21:00" is legal.
- `WHERE (status = 'active')` = cancelled or completed bookings don't block new bookings (mirrors the reference "no two non-cancelled reservations…" idiom [CITED: https://amitavroy.com/articles/postgresql-gist-exclusion-constraintthe-database-evel-answer-to-double-bookings]).
- `btree_gist` extension enables combining standard scalar types (like integers) with range types inside a GiST index [CITED: same].
- Per-booking window per CONTEXT.md: when MatchAccessRule schedules a booking, compute `[scheduled_start − 5m, scheduled_end + 30m]` — give 5m for players to seat, 30m for overruns.

### `match_events` — raw event stream
```php
Schema::create('match_events', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('match_id');
    $t->text('event_type');          // canonical: game_start | round_start | player_kill | player_team_kill | player_connect | player_disconnect | team_switch | round_end | match_end
    $t->text('crcon_action')->nullable();   // raw CRCON action string (audit trail)
    $t->text('crcon_stream_id')->nullable(); // CRCON's "1711657986-0" — supports idempotency
    $t->jsonb('payload');
    $t->timestampTz('occurred_at');  // from CRCON's timestamp_ms
    $t->timestampTz('ingested_at')->useCurrent();
    $t->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $t->unique(['match_id', 'crcon_stream_id']);  // idempotency — re-delivery is harmless
    $t->index(['match_id', 'event_type', 'occurred_at']);
});
DB::statement('ALTER TABLE match_events ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement("ALTER TABLE match_events ADD CONSTRAINT match_events_type_check
               CHECK (event_type IN ('game_start','round_start','player_kill','player_team_kill',
                                     'player_connect','player_disconnect','team_switch',
                                     'round_end','match_end','manual_error'));");
```

**Canonical event shapes** (the `payload` JSONB column):

| canonical `event_type` | CRCON `action` source | payload shape |
|------------------------|-----------------------|---------------|
| `game_start` | `MATCH START` | `{ map: string, mode: string }` |
| `round_start` | (synthetic — emitted when first KILL after MATCH START arrives, or split per CRCON round events if present) | `{ round_number: int }` |
| `player_kill` | `KILL` | `{ killer: { steam_id_64, name }, victim: { steam_id_64, name }, weapon: string }` |
| `player_team_kill` | `TEAM KILL` | `{ killer: {...}, victim: {...}, weapon: string }` |
| `player_connect` | `CONNECTED` | `{ steam_id_64, name }` |
| `player_disconnect` | `DISCONNECTED` | `{ steam_id_64, name }` |
| `team_switch` | `TEAMSWITCH` | `{ steam_id_64, name, from_team, to_team }` |
| `round_end` | (synthetic from MATCH ENDED with `winning_team` payload) | `{ winning_team: 'allies'\|'axis', allies_score: int, axis_score: int }` |
| `match_end` | `MATCH ENDED` | `{ winning_team, allies_score, axis_score, ended_at }` |
| `manual_error` | (synthetic — worker emits on connect-failure to record degraded state) | `{ kind: 'unreachable'\|'auth_failed'\|'permission_denied', detail: string }` |

The `crcon_stream_id` UNIQUE on `(match_id, stream_id)` is the idempotency guarantee — if the worker reconnects and resends the same stream ID, the second INSERT collides and the ingest job skips that event without raising.

### `match_player_stats` — aggregated per-player
```php
Schema::create('match_player_stats', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('match_id');
    $t->uuid('player_id');
    $t->integer('kills')->default(0);
    $t->integer('deaths')->default(0);
    $t->integer('team_kills')->default(0);
    $t->integer('score')->default(0);   // CRCON does not emit per-player score in /ws/logs — derived
    $t->text('role_played')->nullable();
    $t->jsonb('weapons_used')->nullable(); // {weapon: int} count map for blog/MVP UI
    $t->timestamps();
    $t->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $t->foreign('player_id')->references('id')->on('players')->restrictOnDelete();
    $t->unique(['match_id', 'player_id']); // idempotency for aggregator upsert
});
DB::statement('ALTER TABLE match_player_stats ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement('ALTER TABLE match_player_stats ADD CONSTRAINT match_player_stats_nonneg_check
               CHECK (kills >= 0 AND deaths >= 0 AND team_kills >= 0 AND score >= 0);');
```

**Idempotent upsert in `MatchPlayerStatAggregatorService`:**
```php
// Source: pattern mirrors Phase 7 Article publish service idempotency
public function aggregate(GameMatch $match): void {
    DB::transaction(function () use ($match) {
        $events = MatchEvent::query()->where('match_id', $match->id)->get();
        $perPlayer = []; // [steam_id_64 => ['kills'=>0, 'deaths'=>0, ...]]
        foreach ($events as $e) {
            // ... compute from event_type + payload
        }
        foreach ($perPlayer as $steamId => $stats) {
            $player = Player::firstWhere('steam_id_64', $steamId);
            if (!$player) continue; // unmapped players are skipped — see Pitfall 6
            MatchPlayerStat::updateOrCreate(
                ['match_id' => $match->id, 'player_id' => $player->id],
                $stats,
            );
        }
    });
}
```

### `match_results.source` enum column — **REQUIRED ADDITION**

**Gap:** CONTEXT.md claims "Phase 4 MatchResult model already supports source enum (manual/rcon)" — but inspection of `apps/web/database/migrations/2026_05_14_100300_create_match_results_table.php` shows the column does NOT exist in the shipped schema. Phase 8 MUST add it via a follow-up migration:

```php
Schema::table('match_results', function (Blueprint $t) {
    $t->text('source')->default('manual')->after('axis_score');
});
DB::statement("ALTER TABLE match_results ADD CONSTRAINT match_results_source_check
               CHECK (source IN ('manual','rcon'));");
```

This is what makes the manual-override-always-wins invariant enforceable — `MatchResultService::upsertFromRcon()` refuses to overwrite rows where `source='manual'`. Without it, an RCON late delivery silently overwrites a human's corrected score.

## MatchResultService — extended write path

```php
final class MatchResultService
{
    // EXISTING from Phase 4 — kept unchanged
    public function upsert(GameMatch $match, array $data, User $causer): MatchResult { /* ... */ }

    // NEW for Phase 8 — RCON path
    public function upsertFromRcon(GameMatch $match, array $data): MatchResult
    {
        return DB::transaction(function () use ($match, $data): MatchResult {
            $existing = MatchResult::where('match_id', $match->id)->first();
            // INVARIANT: human override beats RCON. Always.
            if ($existing && $existing->source === 'manual') {
                return $existing; // silently skip; log an audit event for traceability
            }
            $rconUser = User::where('email', 'rcon-worker@system.trenchwars')->firstOrFail();
            $result = MatchResult::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'winner_clan_id' => $data['winner_clan_id'] ?? null,
                    'allies_score' => $data['allies_score'] ?? null,
                    'axis_score' => $data['axis_score'] ?? null,
                    'notes' => $data['notes'] ?? '(automated from CRCON)',
                    'recorded_by_user_id' => $rconUser->id,
                    'recorded_at' => $data['recorded_at'] ?? now(),
                    'source' => 'rcon',
                ],
            );
            if ($match->status !== 'played') {
                app(MatchStatusService::class)->transition($match, 'played', $rconUser);
            }
            return $result;
        });
    }
}
```

**Filament admin override path** (existing `upsert()`) automatically passes `source` through fillable → it writes `source='manual'` and locks the row. Phase 8 just needs to add `source` to the `$fillable` array in the model and the default Filament form.

## Failure Handling (matches D-019)

| Failure mode | Worker behaviour | Web behaviour | UI surface |
|--------------|-----------------|---------------|------------|
| CRCON unreachable on session open | Emit `manual_error` event with `kind='unreachable'`; retry connection with backoff for 5 minutes after `reserved_from`. After 5min, mark booking `status='completed'` and stop trying. | Set `gameMatch.manual_entry_required = true`; queue Filament admin notification. | "Auto-capture failed — please enter result manually" banner on match Filament resource. |
| Mid-match log gap (ws drops, reconnects) | `last_seen_id` resume — CRCON Redis stream replays missed events. Acceptable up to CRCON's `stream_size` retention. | No-op; idempotent inserts handle dupes. | None unless events show gap. |
| Bearer token rotated / 401 | Emit `manual_error` event with `kind='auth_failed'`. Stop retrying for this booking. | Same as unreachable — `manual_entry_required = true`. | Filament alert + admin sees `match_servers.last_test_status='error'` next time they hit Test Connection. |
| Web returns 5xx | Worker buffers events in Redis with key `rcon:queue:{match_id}` (LPUSH). Background task drains every 5s with backoff. | Receives the queued batch when it comes back. Nonces still validate (each retry generates a fresh nonce + timestamp, sigs over a new timestamp). | None if drained within 60s replay window. |
| Web returns 401 (signature mismatch) | Log + alert; stop sending for this match. This indicates secret drift — manual intervention needed. | Returns 401; Laravel logs the failure. | Operator alert — investigate `WEB_HMAC_SECRET` mismatch between web and worker. |
| Match end with no MATCH ENDED event captured | After `reserved_to`, scheduler triggers a "close match" job that derives result from accumulated KILL events (winner = higher kill team) — flag `manual_entry_required = true` because score is unconfirmed. | Job sets `source='rcon'` with low-confidence note, `manual_entry_required=true`. | Filament shows "RCON capture incomplete — please verify". |

`manual_entry_required` is a new boolean column on `matches` (add in Phase 8 migration alongside `source` enum). Default false. Filament resource shows a prominent badge when true. Manual entry via existing `upsert()` clears the flag.

## Filament: MatchServerResource

```php
final class MatchServerResource extends Resource
{
    protected static ?string $model = MatchServer::class;
    public static function form(Form $form): Form {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('host')->required()->placeholder('crcon-1.league.example'),
            TextInput::make('port_rcon')->numeric()->default(8010)->required(),
            Select::make('region')->options(['eu','na','apac'])->nullable(),
            TextInput::make('credentials_encrypted.api_token')
                ->password()->revealable()->required()
                ->label('CRCON API Token')
                ->dehydrateStateUsing(fn ($state) => ['api_token' => $state]),
            Toggle::make('is_active')->default(true),
        ]);
    }
    public static function table(Table $table): Table {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('host'),
            TextColumn::make('last_test_status')->badge()
                ->color(fn ($s) => $s === 'ok' ? 'success' : ($s === 'error' ? 'danger' : 'gray')),
            TextColumn::make('last_test_at')->dateTime()->since(),
        ])->actions([
            Action::make('test')
                ->label(__('admin.match_servers.actions.test'))
                ->icon('heroicon-o-signal')
                ->action(fn (MatchServer $r) => dispatch(new TestMatchServerConnectionJob($r->id))),
        ]);
    }
}
```

`TestMatchServerConnectionJob` (Horizon queue) decrypts the token, POSTs an HMAC-signed instruction `POST /api/internal/match-servers/{id}/test` (this is the inverted direction — web wants worker to probe), and updates `last_test_status`. Alternatively, since the web container can also speak HTTP to CRCON directly (the league hosts CRCON), the simpler implementation is: web container calls `GET /api/get_map_rotation` on CRCON itself with the decrypted bearer token and updates the status — **recommended** because it removes a worker round-trip and eliminates "test happens only when worker is up" failure mode.

**Recommendation:** Web does the Test Connection probe directly. Worker is only for live event capture during scheduled bookings.

## Bot Integration — extend Phase 5 outbound

Phase 5 shipped `discord_outbound_messages` with `message_type` CHECK including `match_announce`, `signup_open`, etc. Phase 8 adds:

```php
// New migration: 2026_05_XX_XXXXXX_extend_discord_outbound_message_types_for_match_result_announce.php
DB::statement("ALTER TABLE discord_outbound_messages DROP CONSTRAINT discord_outbound_messages_message_type_check;");
DB::statement("ALTER TABLE discord_outbound_messages ADD CONSTRAINT discord_outbound_messages_message_type_check
               CHECK (message_type IN ('clan_announce','match_announce','signup_open','signup_reminder',
                                       'tournament_announce','article_announce','match_result_announce'));");
```

`DiscordOutboundPayloadBuilder::buildMatchResultAnnounce(MatchResult $r): array` returns a Discord embed including:
- Final score (allies_score vs axis_score)
- Winner clan name
- Top 3 MVPs (kills - deaths from `MatchPlayerStat`)
- Link to match page

`MatchResultObserver` (already exists from Phase 6) gets a new branch: on save when `source='rcon'` AND `match_id` belongs to a match with `host_clan_id` set, insert the outbound row. Bot's existing poll loop (Phase 5) picks it up.

## Runtime State Inventory

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None pre-existing; Phase 8 introduces `match_servers`, `match_server_bookings`, `match_events`, `match_player_stats`. | Migrations in Phase 8 only — no data migration. |
| Live service config | CRCON is operated by league IT outside this repo. CRCON Django admin users + API tokens are managed in CRCON's own admin panel. | Document a "league IT onboarding" checklist: create least-priv Django user, generate API token with `can_view_log_stream_config` + `can_change_log_stream_config`, paste into Filament MatchServerResource form. |
| OS-registered state | None. Workers are container processes managed by Railway / docker-compose; no host registrations. | None. |
| Secrets / env vars | `WEB_HMAC_SECRET` already declared in `.env.example` line 62 [VERIFIED: codebase grep]; new key needed in Railway env group for the `rcon-worker` and `web` services (same value on both). CRCON API tokens stored in `match_servers.credentials_encrypted` (encrypted at rest via `APP_KEY` envelope encryption). | Generate a random 32-byte secret (`openssl rand -hex 32`) on Phase 8 deploy day; paste into Railway env group; restart `web` and `rcon-worker` services. |
| Build artifacts / installed packages | apps/rcon-worker package.json is empty of runtime deps (Phase 1 scaffold only — verified). | Phase 8 adds `ws`, `undici`, `ioredis`, `pino`, `zod`. Standard `pnpm add` flow inside container per D-021. |

## Common Pitfalls

### Pitfall 1: HMAC signature breaks because re-serialised JSON differs from signed JSON
**What goes wrong:** Worker calls `JSON.stringify(payload)` → signs that → POSTs. Web side reads `$request->json()->all()` → re-encodes → verifies signature → mismatch. Cause: key order is implementation-defined in JSON; different runtimes produce different bytes.
**Why it happens:** Developer reflex is to "parse and reserialise" for safety.
**How to avoid:** Sign and verify against the RAW request body. Worker: capture the exact string passed to `fetch(body: ...)` and HMAC THAT. Web: read `$request->getContent()` (raw bytes), do NOT call `->json()` until AFTER signature passes.
**Warning signs:** "All requests fail with 401 in production but work locally."

### Pitfall 2: Clock skew between worker and web exceeds 60s replay window
**What goes wrong:** Worker container clock drifts 90s from web container clock. Every request is rejected as "stale signature".
**Why it happens:** Containerised workloads don't always run NTP. Railway hosts do, but local docker-compose dev sometimes doesn't.
**How to avoid:** Both services rely on host clock (Docker passes host time through). On Railway, both services run on synced infra. For local dev, document `make` target verifies clocks with `docker compose exec web date` + `docker compose exec rcon-worker date`. Build the freshness check as `abs(now - timestamp)` (not just `now - timestamp`) so a worker clock AHEAD of web still works as long as within ±60s.
**Warning signs:** Sporadic 401s in dev that disappear after restart (clock catches up).

### Pitfall 3: CRCON `/ws/logs` returns logs predating the booking
**What goes wrong:** On reconnect, `last_seen_id: null` floods the worker with hours of stale KILL events that get attributed to the active match.
**Why it happens:** `null` means "include older logs" [CITED: Streaming Logs wiki]. CRCON Redis stream retains up to `stream_size` configured logs.
**How to avoid:** Initial connect on a new booking sends `{}` (empty object — "only new logs"); subsequent reconnects within the SAME booking session send `{ last_seen_id: <stored> }`. Worker resets `last_seen_id = null` on booking-end and uses `{}` for the next booking.
**Warning signs:** A match has thousands of KILL events spread over hours instead of a 60-minute window.

### Pitfall 4: MatchPlayerStat aggregator re-runs per-event during the match, causing N² DB load
**What goes wrong:** A naive "on every event, re-aggregate" approach scans all events for the match repeatedly. With 1000+ events per match this is O(N²) DB reads.
**Why it happens:** It feels intuitive to keep stats "live".
**How to avoid:** Insert events into `match_events` only. Run aggregation ONCE on `match_end` (or after a 60s quiet period if no MATCH ENDED arrives). Use `MatchPlayerStat::updateOrCreate(['match_id'=>$m->id, 'player_id'=>$p->id], $stats)` (UNIQUE on `(match_id, player_id)` guarantees idempotency on re-run).
**Warning signs:** Match page is slow during play; Horizon queue backs up.

### Pitfall 5: Player.steam_id_64 not populated → KILL events orphan
**What goes wrong:** CRCON emits Steam IDs in KILL events. The `players` table has a `steam_id_64` column but it's empty for users who only have Discord IDs.
**Why it happens:** The Phase 2 player record is created from Discord OAuth — Steam ID isn't part of OAuth payload.
**How to avoid:** Two layers: (a) `MatchPlayerStatAggregatorService` skips events where the Steam ID has no matching `players.steam_id_64` row (don't crash); (b) provide a Filament admin action "Backfill from RCON history" that lets a player or admin associate a Steam ID after the fact. Out of strict round-1 scope but document for v2; for round-1, the happy-path acceptance assumes players self-register their Steam ID via the public profile editor BEFORE their first scrim. **Add to OPEN QUESTIONS for Phase 8 discuss.**
**Warning signs:** "Match has 47 KILL events but only 2 MatchPlayerStat rows."

### Pitfall 6: Filament Test Connection button times out the admin request
**What goes wrong:** Sync HTTP call to CRCON inside a Filament action blocks the request thread; CRCON is slow or unreachable; admin sees a 30s spinner then PHP-FPM timeout.
**Why it happens:** Filament `Action::action()` runs synchronously on the request thread.
**How to avoid:** Dispatch a Horizon job (`TestMatchServerConnectionJob`), update `last_test_status` from the job, show a Filament notification "Test queued" immediately. Admin refreshes / polls to see result. Mirrors the bot outbound delivery indirection pattern from Phase 5.
**Warning signs:** Production logs show PHP-FPM 30s timeouts during admin server registration.

### Pitfall 7: btree_gist extension not enabled → migration fails on EXCLUDE constraint
**What goes wrong:** `ALTER TABLE match_server_bookings ADD CONSTRAINT … EXCLUDE USING gist (...)` fails with `ERROR: data type uuid has no default operator class for access method "gist"` on a fresh Postgres without `btree_gist`.
**Why it happens:** Phase 1 enabled `uuid-ossp` and `citext` but NOT `btree_gist` (Pitfall 5 from Phase 1 RESEARCH).
**How to avoid:** The `match_server_bookings` migration MUST run `CREATE EXTENSION IF NOT EXISTS btree_gist;` BEFORE the ALTER TABLE. Mirror the Phase 1 pattern: extensions land in their owning migration, not in the postgres Docker image.
**Warning signs:** Phase 8 migration fails on fresh DB with "data type X has no default operator class".

### Pitfall 8: Encrypted column rotation breaks on APP_KEY rotation
**What goes wrong:** Operator rotates `APP_KEY` for security; `match_servers.credentials_encrypted` becomes undecryptable; all CRCON connections fail.
**Why it happens:** Laravel's `encrypted` cast derives the ciphertext from `APP_KEY`. Rotating without `php artisan key:rotate --old-key=...` (or equivalent re-encrypt loop) destroys the data.
**How to avoid:** Document a "rotate APP_KEY" runbook that includes re-reading each `match_server`, calling `->update(['credentials_encrypted' => $current])` to re-encrypt under the new key. Round-1 we don't ship key rotation tooling — just document the dependency.
**Warning signs:** All `match_server` connection tests fail simultaneously after a key rotation.

### Pitfall 9: Worker leaks PII (Steam IDs, player names) into Railway logs
**What goes wrong:** `pino` default config logs payloads at debug; CRCON events include Steam IDs and player names; Railway aggregator stores them; potential GDPR exposure.
**Why it happens:** `console.log(event)` reflex during development.
**How to avoid:** Pino redact config — redact `steam_id_64`, `player`, `victim`, `killer` paths at INFO level; only log them at DEBUG which is disabled in production. Match Phase 5's logging redact list verbatim where possible.
**Warning signs:** Railway log search returns Steam IDs.

### Pitfall 10: Per-server CRCON sessions exceed CRCON connection limit during tournament
**What goes wrong:** 8-clan tournament with 4 simultaneous matches → 4 worker→CRCON sessions in parallel. CRCON's nginx default `worker_connections` is 1024 but Channels-side Daphne is more limited.
**Why it happens:** Unintended at small scale; surprise at tournament time.
**How to avoid:** Round-1 server pool is small (open question on launch sizing). Document the limit. For round-1 cap simultaneous active bookings per server-pool to a reasonable number; defer multi-server concurrency stress test to v2.
**Warning signs:** Tournament finals weekend shows scattered "CRCON connection refused" in worker logs.

### Pitfall 11: BookingScheduler races on poll-and-claim
**What goes wrong:** Two worker replicas (when scaled out) both pick up the same booking-due row and both open CRCON sessions for the same match — duplicate events into `match_events` (saved by `crcon_stream_id` UNIQUE, but wasteful).
**Why it happens:** Naive poll doesn't claim/lock.
**How to avoid:** Round 1 runs ONE rcon-worker replica only (D-014 — Railway 5-service single-replica per service). Document a "MUST NOT scale rcon-worker beyond 1 in round 1". For v2 introduce row-locking claim semantics (UPDATE … WHERE worker_id IS NULL RETURNING).
**Warning signs:** Match has 2× expected event count even though `crcon_stream_id` UNIQUE prevents duplication.

### Pitfall 12: Source enum default of 'manual' wins over RCON arrivals
**What goes wrong:** A user enters scores manually 5 minutes before MATCH ENDED arrives. RCON arrives and silently no-ops (good!) but the admin doesn't know RCON would have agreed/disagreed.
**Why it happens:** "Manual wins always" is intentional but uncommunicative.
**How to avoid:** Worker's `match_event` insert STILL succeeds; `MatchResultService::upsertFromRcon` logs an audit activity row "RCON arrived for match X but manual result locked it — score would have been Y" so the admin can verify after the fact.
**Warning signs:** Discrepancy between Filament MatchResult view and the underlying `match_events` aggregate.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework (web) | Pest ^3 + PHPUnit ^11 — already configured |
| Framework (worker) | Vitest ^2 — package.json already wired |
| Config file (web) | `apps/web/phpunit.xml`, `apps/web/tests/Pest.php` |
| Config file (worker) | `apps/rcon-worker/vitest.config.ts` (Wave 0) |
| Quick run command (web) | `make pest ARGS="--filter=PhaseEight"` |
| Quick run command (worker) | `docker compose exec rcon-worker pnpm vitest run` |
| Full suite (web) | `make pest` |
| Full suite (worker) | `docker compose exec rcon-worker pnpm test && pnpm typecheck && pnpm lint` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-goal-rcon-history | MATCH ENDED event triggers MatchResult upsert | feature (web) | `make pest ARGS="--filter=RconMatchResultIngestionTest"` | ❌ Wave 0 |
| REQ-goal-rcon-history | KILL events aggregate to MatchPlayerStat | feature (web) | `make pest ARGS="--filter=MatchPlayerStatAggregatorTest"` | ❌ Wave 0 |
| REQ-goal-rcon-history | Event ingest is idempotent on crcon_stream_id replay | feature (web) | `make pest ARGS="--filter=MatchEventIdempotencyTest"` | ❌ Wave 0 |
| REQ-constraint-league-owns-servers | credentials_encrypted decrypts roundtrip | unit (web) | `make pest ARGS="--filter=MatchServerCredentialEncryptionTest"` | ❌ Wave 0 |
| REQ-constraint-league-owns-servers | EXCLUDE constraint rejects overlapping booking | feature (web) | `make pest ARGS="--filter=MatchServerBookingOverlapTest"` | ❌ Wave 0 |
| REQ-success-end-to-end-scrim | E2E: schedule → book → simulate CRCON ws → assert MatchResult+stats+outbound | feature (web) | `make pest ARGS="--filter=ScrimE2EHappyPathTest"` | ❌ Wave 0 |
| (HMAC contract) | HMAC verify accepts good sig, rejects stale/bad/replayed | feature (web) | `make pest ARGS="--filter=VerifyRconSignatureTest"` | ❌ Wave 0 |
| (HMAC contract) | Worker HmacSigner generates verifiable signature | unit (worker) | `docker compose exec rcon-worker pnpm vitest run HmacSigner` | ❌ Wave 0 |
| (CRCON normalisation) | CrconEventNormaliser maps every AllLogTypes value we subscribe to | unit (worker) | `docker compose exec rcon-worker pnpm vitest run CrconEventNormaliser` | ❌ Wave 0 |
| (Worker reconnect) | CrconClient reconnects with backoff + last_seen_id resume | integration (worker) | `docker compose exec rcon-worker pnpm vitest run CrconClient.integration` | ❌ Wave 0 |
| (Manual override invariant) | source='manual' row is not overwritten by upsertFromRcon | feature (web) | `make pest ARGS="--filter=ManualOverrideWinsTest"` | ❌ Wave 0 |
| (Failure handling D-019) | Unreachable CRCON sets manual_entry_required=true | feature (web) | `make pest ARGS="--filter=RconUnreachableFlagsManualTest"` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `make pest ARGS="--filter=<TaskScope>"` + `docker compose exec rcon-worker pnpm vitest run --changed`
- **Per wave merge:** `make pest && make pint ARGS="--test" && make phpstan && docker compose exec rcon-worker pnpm test && pnpm typecheck && pnpm lint`
- **Phase gate:** Full suite green + manual end-to-end smoke on a real CRCON test instance before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `apps/web/tests/Feature/RconMatchResultIngestionTest.php` — covers REQ-goal-rcon-history
- [ ] `apps/web/tests/Feature/MatchPlayerStatAggregatorTest.php` — covers REQ-goal-rcon-history per-player
- [ ] `apps/web/tests/Feature/MatchEventIdempotencyTest.php` — covers crcon_stream_id UNIQUE
- [ ] `apps/web/tests/Feature/MatchServerCredentialEncryptionTest.php` — covers encrypted cast roundtrip
- [ ] `apps/web/tests/Feature/MatchServerBookingOverlapTest.php` — covers EXCLUDE constraint
- [ ] `apps/web/tests/Feature/ScrimE2EHappyPathTest.php` — covers SC-5 end-to-end (REQ-success-end-to-end-scrim)
- [ ] `apps/web/tests/Feature/VerifyRconSignatureTest.php` — covers HMAC middleware
- [ ] `apps/web/tests/Feature/ManualOverrideWinsTest.php` — covers D-019 invariant
- [ ] `apps/web/tests/Feature/RconUnreachableFlagsManualTest.php` — covers failure path
- [ ] `apps/rcon-worker/tests/unit/HmacSigner.test.ts` — covers signer contract
- [ ] `apps/rcon-worker/tests/unit/CrconEventNormaliser.test.ts` — covers normalisation
- [ ] `apps/rcon-worker/tests/integration/CrconClient.integration.test.ts` — covers reconnect + resume (mock ws server)
- [ ] `apps/rcon-worker/vitest.config.ts` — confirm Wave 0 config matches Phase 5 bot precedent

### End-to-End Happy Path Test (SC-5)
The cornerstone test simulates the entire flow without external dependencies:

```php
// apps/web/tests/Feature/ScrimE2EHappyPathTest.php
test('two clans complete full round-1 happy path', function () {
    // 1. Setup — two clans, two players each, OAuth-resolved User+Player rows (Phase 2 factories)
    // 2. Organise scrim — clanA captain schedules vs clanB (Phase 4 factories)
    // 3. Slots filled — each player signs up via bot API (Phase 5 sanctum tokens + acts-as middleware)
    // 4. Server registered — Filament admin path, encrypted creds (Phase 8 new)
    // 5. Booking — MatchAccessRule.bookServer($match, $serverId) writes match_server_bookings (Phase 8 new)
    // 6. Simulate CRCON wire frames — feed JSON events through HmacSigner → /api/internal/match/{id}/events
    //    Events: MATCH START, KILL × N, TEAM KILL, DISCONNECT, MATCH ENDED
    // 7. Assertions:
    //    - match_results row exists with source='rcon', auto-computed winner
    //    - match_player_stats rows exist for every player with kills/deaths > 0
    //    - matches.status = 'played'
    //    - discord_outbound_messages row exists with message_type='match_result_announce'
    //    - matches.manual_entry_required = false
});
```

This is the mechanical proof of REQ-success-end-to-end-scrim — no manual entry on the happy path.

## Security Domain

> `security_enforcement: true`, `security_asvs_level: 1`, `security_block_on: high` from `.planning/config.json`.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | HMAC-SHA256 worker→web with constant-time compare; Bearer-token worker→CRCON. |
| V3 Session Management | partial | Worker holds short-lived CRCON sessions only during scheduled bookings; no long-lived stateful session. |
| V4 Access Control | yes | Internal HMAC-protected endpoints not exposed to bot Sanctum group (separate route prefix `/api/internal/*`). Filament gates MatchServerResource behind `permission:manage-rcon`. |
| V5 Input Validation | yes | zod schemas on worker, Laravel `FormRequest` on web for the events POST; CRCON wire frames validated before normalisation. |
| V6 Cryptography | yes | Laravel `encrypted:array` cast (AES-256-GCM under `APP_KEY`); HMAC-SHA256 via PHP `hash_hmac` / Node `node:crypto`; never hand-roll. |
| V7 Error Handling and Logging | yes | Pino redact PII; web logs HMAC failures without leaking the secret or the candidate signature. |
| V9 Communications | yes | Worker→web HTTPS (Railway). Worker→CRCON SHOULD be HTTPS (CRCON supports `RCONWEB_PORT_HTTPS`); document operator setup. |
| V11 Business Logic | yes | Manual-override-wins invariant; idempotent event ingestion; race-free booking via Postgres EXCLUDE; row-locked status flip. |

### Known Threat Patterns

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Replay attack on internal events endpoint | Spoofing | 60s freshness + single-use nonce (Redis SETNX EX 120). |
| Tampering with body in transit | Tampering | HMAC over `(timestamp + raw body)`. |
| Secret leak via logs | Information disclosure | Pino redact list; Laravel logs sanitised before storage; never log full `WEB_HMAC_SECRET` or `Authorization` header. |
| Credential leak via DB dump | Information disclosure | `encrypted:array` cast; DB dump contains ciphertext only. |
| Denial-of-service via flood to internal endpoint | DoS | Throttle middleware on `/api/internal/match/{id}/events` (e.g., 600/min). Per-IP throttle is fine because only the worker container talks to it. |
| Manual data overwritten by stale RCON | Tampering | `source='manual'` lock + audit log entry on attempted overwrite. |
| Token theft from match_servers table | Spoofing | Encrypted at rest. APP_KEY rotation runbook documented. |
| WebSocket downgrade (ws:// instead of wss://) | Tampering | Operator setup checklist: require `wss://` in production; `ws://` allowed only on localhost-bound CRCON inside private network. |

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | CONTEXT.md says "Phase 4 MatchResult model already supports source enum" — **FALSE** based on shipped Phase 4 migration. Phase 8 MUST add the `source` enum + `manual_entry_required` flag. | `match_results.source` enum (NEW) section + Schema | High — without the column the manual-override invariant is unenforceable. Plan must include the migration. |
| A2 | CRCON v10.0.0+ is the league-deployed version. CRCON `/ws/logs` is v9.7.0+ but v9→v10 changed REST API shape [CITED: v9.x-to-v10 wiki]. | CRCON API section | Medium — if league runs v9 only, REST endpoint URLs may differ. Confirm with league IT before Phase 8 ships (Open Question). |
| A3 | A system user `rcon-worker@system.trenchwars` exists for `MatchResult.recorded_by_user_id` when source='rcon'. | MatchResultService section | Low — Phase 8 seeder adds the user. Document in plan. |
| A4 | Round-1 rcon-worker runs as a single replica (no horizontal scale). | Pitfall 11 | Low — D-014 already specifies single-replica services. Confirm not changed. |
| A5 | Players self-register Steam IDs before their first RCON-captured scrim, or events without a player_id mapping are silently dropped. | Pitfall 5 | Medium — without this assumption the happy path test must include a backfill flow. Flag in discuss-phase. |
| A6 | The cap of 25 logs per CRCON `/ws/logs` batch is documented and stable [CITED: Streaming Logs wiki]. | CRCON API section | Low — Streaming Logs wiki is current; bumped values would only reduce traffic. |
| A7 | The pre-booking window of `[scheduled_start − 5m, scheduled_end + 30m]` (per CONTEXT.md) is the right shape for HLL. | Schema → match_server_bookings | Medium — operators may want 10m/45m; finalise in discuss-phase. |

**If this table is empty:** Not empty — flag A1, A2, A5, A7 to discuss-phase / planner.

## Open Questions

1. **CRCON version standardisation** (carried from PROJECT.md Open Questions).
   - What we know: `/ws/logs` available v9.7.0+; REST API changed v9→v10.
   - What's unclear: Which version the league actually runs in production.
   - Recommendation: Block Phase 8 acceptance on a written answer; pin documentation to "tested against vX.Y.Z".
2. **Steam ID linkage flow for players** (Pitfall 5).
   - What we know: Phase 2 created `players.steam_id_64` nullable text column.
   - What's unclear: Whether the public profile editor (Phase 2) actually exposes the field.
   - Recommendation: Verify with a Filament/Vue audit; if missing, add it to Phase 8 scope.
3. **MatchServer connection test path** (CONTEXT.md says "via internal channel or directly").
   - What we know: Two viable shapes — sync web→CRCON or async via worker.
   - Recommendation: Web→CRCON direct (this RESEARCH's pick — simpler, doesn't depend on worker liveness).
4. **Should we persist CRCON-side conversation logs** (chat) for moderation audit?
   - What we know: Out of scope per CONTEXT.md deferred list.
   - Recommendation: Confirm and re-affirm "chat NOT captured" in Phase 8 scope at discuss-phase.
5. **What is the right behaviour when a clan's player Steam ID belongs to someone not on the clan roster?** (e.g., a ringer)
   - What we know: KILL events come from CRCON with raw Steam IDs.
   - Recommendation: Phase 8 records all KILL events regardless of clan affiliation; `MatchPlayerStat` is per-`player_id`, not per-clan, so this is fine.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Node 22 in `rcon-worker` container | Worker runtime | ✓ (per Phase 1 scaffold + docker-compose.yml) | — | — |
| `ws` npm package | Worker WebSocket | needs install | `^8.20` | — |
| `undici` npm package | Worker HTTP | needs install | `^7` (NOT v8 — Node 22 fetch compat) | — |
| Postgres 16 `btree_gist` extension | EXCLUDE constraint | available in postgres:16-alpine image | — | — |
| Redis 7 | Nonce store + worker failover queue | ✓ Phase 1 | — | — |
| CRCON v10+ live test instance | Manual smoke + E2E integration | ✗ external | — | Mock ws server in vitest integration test (see Wave 0 file list). |
| `WEB_HMAC_SECRET` env in both services | HMAC ops | declared in `.env.example` line 62 | generated at deploy time | — |

**Missing dependencies with no fallback:** None (the live CRCON instance is a manual-smoke requirement, not a CI requirement).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| CRCON polling `/api/get_recent_logs` every N seconds | CRCON `/ws/logs` WebSocket streaming | CRCON v9.7.0 (2024) | Lower latency, lower load, server-side filtering by action type. |
| Long-lived global RCON session per server | Per-booking ephemeral session | Round-1 design | Avoids permission/auth drift between matches; cleaner failure isolation. |
| Custom in-app nonce store | Redis SETNX with TTL | Industry standard | Atomic, no race condition, horizontally scalable. |
| Manual EXCLUDE without btree_gist | btree_gist + tstzrange + EXCLUDE USING gist | Postgres 9.6+ stable | Race-free booking at DB tier. |
| In-app password storage | Laravel `encrypted:array` cast | Laravel 9+ | One-line column-level encryption. |

**Deprecated/outdated:**
- `WEB_API_TOKEN`-style shared bearer for worker→web (alternative considered) — HMAC is the locked decision per CON-arch-rcon-to-web-comm.
- Polling CRCON over HTTP for new logs — superseded by `/ws/logs` in v9.7.

## Sources

### Primary (HIGH confidence)
- CRCON Developer Guide — CRCON API: https://github.com/MarechJ/hll_rcon_tool/wiki/Developer-Guides-%E2%80%90-CRCON-API
- CRCON Developer Guide — Streaming Logs: https://github.com/MarechJ/hll_rcon_tool/wiki/Developer-Guides-%E2%80%90-Streaming-Logs
- CRCON wiki home (page index): https://github.com/MarechJ/hll_rcon_tool/wiki
- CRCON `AllLogTypes` enum: https://github.com/MarechJ/hll_rcon_tool/blob/master/rcon/types.py
- CRCON auth module (`/do_login`, BEARER format): https://github.com/MarechJ/hll_rcon_tool/blob/master/rconweb/api/auth.py
- Laravel 12 Eloquent Mutators & Casting (encrypted casts): https://laravel.com/docs/12.x/eloquent-mutators
- PostgreSQL btree_gist + EXCLUDE pattern: https://amitavroy.com/articles/postgresql-gist-exclusion-constraintthe-database-evel-answer-to-double-bookings
- ws npm package (Node WebSocket client): https://github.com/websockets/ws + https://www.npmjs.com/package/ws
- undici v7 release notes: https://blog.platformatic.dev/undici-v7-is-here

### Secondary (MEDIUM confidence)
- WebSocket reconnect with exponential backoff: https://dev.to/hexshift/robust-websocket-reconnection-strategies-in-javascript-with-exponential-backoff-40n1
- Socket.IO vs ws production scaling: https://dev.to/axiom_agent/nodejs-websockets-in-production-socketio-vs-ws-scaling-and-reconnection-strategies-5b68
- HMAC + nonce + Redis pattern: https://dev.to/raselmahmuddev/protecting-api-requests-using-nonce-redis-and-time-based-validation-11nd
- HMAC SHA-256 node.js implementation guide: https://medium.com/@mohanpathi.s/hmac-authentication-for-api-security-a-comprehensive-implementation-guide-for-node-js-ab01bebfeb68
- PostgreSQL range types reference: https://www.postgresql.org/docs/current/rangetypes.html
- Preventing overlapping data in Postgres: https://blog.danielclayton.co.uk/posts/overlapping-data-postgres-exclusion-constraints/

### Tertiary (LOW confidence — verified against codebase / secondary, retained for completeness)
- undici dispatcher + Node 22 fetch compat issue: https://github.com/nodejs/undici/issues/3901 (consistent across multiple commenters)

## Metadata

**Confidence breakdown:**
- CRCON API specifics: HIGH — verified from official wiki pages + source files quoted directly.
- HMAC/nonce architecture: HIGH — matches Stripe/GitHub webhook idiom and the locked CON-arch-rcon-to-web-comm spec; cross-verified across multiple sources.
- Postgres EXCLUDE + btree_gist: HIGH — Postgres docs + multiple consistent secondary sources.
- Laravel encrypted casts: HIGH — official Laravel 12 docs.
- ws library reconnect pattern: MEDIUM — pattern is widely documented; no single canonical source, but consistent across credible blogs.
- undici v7 vs v8 Node-22 pinning: MEDIUM — issue thread is authoritative but pinning advice is conservative interpretation.
- match_results.source absence in shipped Phase 4 schema: HIGH — verified by reading the migration file directly.

**Research date:** 2026-05-14
**Valid until:** ~2026-06-13 (30 days — stable except CRCON which iterates fast; re-check CRCON version pin if Phase 8 ships >2 weeks from research date).
