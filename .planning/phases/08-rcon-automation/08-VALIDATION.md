---
phase: 8
slug: rcon-automation
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-14
approved: 2026-05-14
---

# Phase 8 — Validation Strategy

## Test Infrastructure
- Pest 4 (web) + Vitest (rcon-worker)
- `make pest ARGS="--filter=Rcon or MatchServer or MatchPlayerStat"` quick
- `make pnpm ARGS="-F @trenchwars/rcon-worker test"` for worker

## Per-Plan Map

| Plan | Wave | Focus |
|------|------|-------|
| 08-01 | 0 | Wave 0 — composer/pnpm deps + factory stubs + RED stubs + i18n |
| 08-02 | 1 | Migrations (match_servers + match_server_bookings + match_player_stats + match_events + MatchResult source + manual_entry_required) |
| 08-03 | 2 | MatchServer + MatchServerBooking models w/ encrypted casts + factories + relationship tests |
| 08-04 | 2 | MatchPlayerStat + MatchEvent models + factories |
| 08-05 | 3 | HMAC middleware (ValidateRconHmacSignature) + nonce store + replay window |
| 08-06 | 3 | Internal API endpoints (POST /api/internal/match/{id}/events + credential fetch) |
| 08-07 | 4 | MatchEventNormaliser service + MatchEventIngestService (idempotent upsert keyed (match_id, crcon_stream_id)) |
| 08-08 | 4 | MatchPlayerStatAggregator (rolls up events on match_end) + MatchResult auto-populate (source='rcon') |
| 08-09 | 5 | MatchServerResource Filament + TestConnectionAction + booking RelationManager |
| 08-10 | 6 | rcon-worker: ws client + CRCON session + event stream + HMAC signer + reconnect |
| 08-11 | 6 | rcon-worker: booking poller + match lifecycle + failure handling (graceful degrade) |
| 08-12 | 7 | E2E ScrimE2EHappyPathTest (SC-5 capstone) + bot result announce extension + audit |
| 08-13 | 8 | [BLOCKING] Phase verification |

## Wave 0 Requirements
- composer require (encrypted casts already available in Laravel core)
- pnpm add ws + verify undici v7 pinning for Node 22 + verify @types/ws
- Pest RED stubs + worker Vitest stubs

## Manual Smokes
- Live CRCON connection from MatchServerResource → green status badge
- Two-clan scrim full happy path (SC-5) on a real CRCON-backed server
- Mid-match log gap recovery
- HMAC key rotation flow

**Approval:** 2026-05-14 autonomous workflow.
