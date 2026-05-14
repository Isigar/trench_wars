---
phase: 9
slug: polish
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-14
approved: 2026-05-14
---

# Phase 9 — Validation Strategy

## Test Infrastructure
- Pest 4 (web) + axe-core CLI (CI)
- `make pest ARGS="--filter=Notification or Leaderboard or Moderator or Polish"` quick
- Full suite (web + bot + worker)

## Per-Plan Map

| Plan | Wave | Focus |
|------|------|-------|
| 09-01 | 0 | Wave 0 scaffolding — RED stubs + strict-mode + Imagick verify + discord CHECK extension for user_dm |
| 09-02 | 1 | Migrations (notifications + user_notification_preferences + bans + match_disputes + abuse_reports) |
| 09-03 | 2 | Notification models + DatabaseChannel + DiscordChannel (outbox writer) |
| 09-04 | 3 | NotificationDispatcher service + Schedule cron for upcoming-match-1h-15m + result-published + cancelled |
| 09-05 | 3 | Leaderboard aggregator service + flexible-cache invalidation observer |
| 09-06 | 4 | Public leaderboard pages (top clans + top players, 7d/30d/all-time toggles) |
| 09-07 | 5 | Moderator tooling: Ban/Suspend BulkActions + match_disputes Filament workflow |
| 09-08 | 6 | Performance pass: shouldBeStrict + N+1 sweep + cache tags |
| 09-09 | 6 | WebP image variants (medialibrary conversions) |
| 09-10 | 7 | A11y pass: axe-core scan + focus rings + keyboard nav fixes |
| 09-11 | 7 | Security hardening: rate-limit + abuse_reports flow + report admin queue |
| 09-12 | 8 | [BLOCKING] Phase verification |

## Wave 0 Requirements
- AppServiceProvider strict-mode flip (after N+1 sweep in plan 09-08)
- Imagick extension verified in web container
- discord_outbound_messages CHECK constraint extension for user_dm

## Manual Smokes
- Notification bell flow + Discord DM receipt
- Leaderboard performance under load
- Moderator BulkAction audit trail
- A11y keyboard nav through every public flow
- Rate-limit boundary behaviour

**Approval:** 2026-05-14 autonomous workflow.
