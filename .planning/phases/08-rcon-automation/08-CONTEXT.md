---
phase: 8
phase_name: RCON automation
gathered: 2026-05-14
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 8: RCON automation — Context

<domain>
## Phase Boundary

Close the round-1 acceptance loop — when a match is played on a registered match server, results and per-player stats arrive automatically from CRCON, with manual override always available as a safety net.

**Success Criteria** (5 SCs):
1. Admin registers MatchServer in Filament with encrypted CRCON credentials + "Test Connection" verifies CRCON reachability + game state.
2. Booking a match against a server reserves `[scheduled_start − 5m, scheduled_end + 30m]` in `match_server_bookings`; Postgres EXCLUDE constraint prevents overlap.
3. When booked match runs, `rcon-worker` opens CRCON session, streams normalised events to `web` via HMAC-signed `POST /api/internal/match/{id}/events`; at match_end → auto-populate MatchResult (source='rcon') + per-player MatchPlayerStat rows.
4. CRCON failure modes (unreachable on session open, mid-match log gap, key rotated) degrade gracefully — match flagged for manual entry, admin sees error event, manual override wins.
5. Two clans complete full round-1 happy path end-to-end (OAuth → clan create → roster → scrim schedule → Discord signup → CRCON-played → auto-recorded result + per-player stats) — no manual entry on happy path.

**Depends on**: Phase 5 (Discord result announce), Phase 4 (matches/results)
**Requirements**: REQ-goal-rcon-history, REQ-constraint-league-owns-servers, REQ-success-end-to-end-scrim
</domain>

<decisions>
## Locked + Discretionary

- **D-005** RCON via CRCON; league deploys CRCON alongside servers.
- **D-019** CRCON live capture + manual override.
- **D-012** Filament MatchServerResource.
- **D-021** rcon-worker runs in `trenchwars-rcon-worker` container (Phase 1 scaffolded).
- **CON-arch-rcon-to-web-comm** — HMAC-signed worker→web messages, 60s replay window.
- **D-04-03-A** `App\Models\GameMatch`.

### Bot is thin display — RCON worker is "thin normaliser" (mirror principle, D-004 echoed).
- All business logic stays in `web` (Laravel).
- Worker is Node 22 + undici + ws + crypto — opens CRCON session, normalises wire format, signs+POSTs to web. NO DB. NO business logic.
</decisions>

<code_context>
- apps/rcon-worker/ scaffolded Phase 1.
- Phase 4 MatchResult model already supports source enum (manual/rcon).
- Phase 4 MatchSignupService row-lock pattern applies if RCON triggers result writes (use existing service or new MatchResultService).
- Phase 5 discord_outbound_messages can announce results.
</code_context>

<specifics>
## Specifics

**Tables:**
- `match_servers` (id, name, host, port_rcon, password_encrypted, port_query, region, is_active, ...)
- `match_server_bookings` (id, match_id, server_id, reserved_from, reserved_to, status enum, ...) with EXCLUDE constraint on overlapping ranges.
- `match_player_stats` (id, match_id, player_id, kills, deaths, assists, score, role_played, ...).
- `match_events` (raw stream from worker — id, match_id, event_type, payload JSONB, occurred_at, ...).

**Web endpoints:**
- POST /api/internal/match/{id}/events (HMAC-signed) — worker streams events.
- GET /api/internal/match-servers/{id}/credentials (HMAC-signed) — worker fetches CRCON creds.

**Filament:**
- MatchServerResource with TestConnectionAction (calls worker via internal channel or directly).
- MatchResult override action — locks the manual override even if RCON sends events.

**Worker (apps/rcon-worker/):**
- Polls or subscribes for bookings due now.
- Opens CRCON WebSocket session.
- Normalises events (game_start/round_start/player_kill/round_end/match_end).
- Signs payload (HMAC-SHA256 with shared secret, timestamp + nonce for replay protection).
- POSTs to web per event or per batch.
- Logs failures; resilient to connection drops.

**Security:**
- CRCON credentials encrypted at rest (Laravel encrypted cast).
- HMAC shared secret in env, never logged.
- Worker validates web TLS cert.
- Web validates HMAC + timestamp ≤ 60s + nonce single-use.
</specifics>

<deferred>
- Live web view of match in progress (Phase 9+).
- Replay file storage/parsing (out of scope v1).
- Cross-server scrim coordination (multi-server tournaments — Phase 9+).
- Per-player ELO recompute from RCON history (Phase 9+).
</deferred>
