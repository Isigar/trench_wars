---
phase: 9
phase_name: Polish
gathered: 2026-05-14
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 9: Polish — Context

<domain>
## Phase Boundary

Buffer milestone covering things every shipping product needs but don't fit cleanly into a feature-driven phase — notifications, search depth, leaderboards, mod tooling, performance, accessibility, hardening.

**Success Criteria** (5):
1. Notifications hub: web bell + Discord DM rules; default rules wired (match starting 1h/15m, match cancelled, result published).
2. Leaderboards: top clans + top players by stat windows from MatchPlayerStat aggregates.
3. Moderator tooling: bulk actions, ban/suspend, dispute resolution workflow in Filament; all audited.
4. Performance pass: N+1 eliminated; documented cache-key strategy; WebP image variants; round-1 public surface within target time budgets.
5. Accessibility + security pass: AA contrast, keyboard nav, focus rings; rate-limit + abuse-vector hardening documented.

**Depends on**: Phase 8 (everything else must exist before polishing)
**Requirements**: No new v1 requirements; consumes any open polish backlog from prior phases.
</domain>

<decisions>
## Locked + Discretionary

- **D-04-03-A** `App\Models\GameMatch`.
- **D-013** i18n every UI string.
- **D-018** PlayerPrivacyGate on leaderboards (privacy-aware).
- **D-021** Container-only.

Discretionary:
- Notifications backend: Laravel Notifications + DatabaseChannel for web bell + DiscordChannel via Phase 5 outbound infra.
- Leaderboard windows: 7d / 30d / all-time; cached aggregate queries.
- Moderator tooling: spatie/permission moderator role + Filament BulkActions.
- Performance: Laravel cache facade + tag-keyed strategy; spatie/image-optimizer for WebP variants.
- Accessibility: axe-core scan in CI; manual keyboard tests; focus-ring CSS audit.
</decisions>

<code_context>
- Phase 8 ships MatchPlayerStat aggregator — leaderboards consume.
- Phase 5 discord_outbound_messages can announce notifications via DM.
- Phase 4 GameMatch.scheduled_at + Schedule cron can dispatch "starting soon" notifications.
- Phase 7 SSR enabled — public surface already shipped.
- Phase 1 PlayerPrivacyGate respects show_to tier.
</code_context>

<specifics>
## Specifics

**Tables:**
- `notifications` (Laravel default — id, type, notifiable, data JSONB, read_at, ...).
- `user_notification_preferences` (id, user_id, channel, event_type, enabled, ...).
- `bans` (id, user_id, reason, expires_at, ban_type enum, issued_by_user_id, ...) — moderation.
- `match_disputes` (id, match_id, raised_by_user_id, status, resolution, ...) — dispute workflow.

**Routes/UI:**
- /notifications — Vue page with bell list; mark-read.
- /leaderboards — top clans + top players (filterable by window).
- Filament: ModeratorPanel (or extend Admin) with BulkActions for users/matches/disputes; AuditLog viewer enhancements.

**Performance:**
- `php artisan model:prune` for old activity_log / events.
- Cache: spatie/laravel-responsecache or Redis tagged cache for public pages.
- WebP variants via spatie/laravel-medialibrary conversions.

**Security/A11y:**
- Rate-limit: throttle middleware on sensitive endpoints; Laravel built-in.
- Abuse hardening: report user/clan flow; admin review queue.
- WCAG AA audit via axe-core integration in pnpm test:e2e (optional).
</specifics>

<deferred>
- Real-time notifications via WebSockets/Reverb (out of scope v1).
- ELO rating systems (Phase 10+).
- Multi-language UI (i18n key files only — actual translations beyond English deferred).
</deferred>
