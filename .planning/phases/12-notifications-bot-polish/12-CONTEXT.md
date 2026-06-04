# Phase 12: Notifications & bot polish - Context

**Gathered:** 2026-06-04
**Status:** Ready for planning
**Mode:** Orchestrator-authored (skip_discuss). KEY FINDING: NOTF-01 is largely pre-built (Phase 9 09-06); BOT-01 is the real new work.

<domain>
## Phase Boundary
**Goal:** Users have full control over which notifications they receive and how; the Discord bot's list commands support pagination.
- **NOTF-01** — account-settings notification-preferences UX (per event-type × channel; change persists; dispatcher honors).
- **BOT-01** — `/match list` + `/clan list` paginate via INTERACTIVE in-message controls (buttons), not a page argument (SC wording: "interactive controls in the same message").
</domain>

<decisions>
## Implementation Decisions

### NOTF-01 — VERIFY + discoverability (do NOT rebuild)
ALREADY SHIPPED by Phase 9 plan 09-06 (verified this session): `app/Http/Controllers/Account/NotificationPreferencesController.php` (edit renders the full 5 event-type × 2 channel matrix with default-policy fallback; update persists via updateOrCreate keyed on user+event+channel, unp_unique race-safe), routes `account.notification-preferences.edit/update` (web.php:167-170), `resources/js/pages/Account/NotificationPreferences.vue` (Reka Switch grid), and the dispatcher honors prefs via `User::enabledNotificationChannels($eventType)` used by all 5 Notification classes. Tests exist (UserNotificationPreferencesTest, NotificationsBellTest).
Phase 12 NOTF-01 scope is therefore:
1. **Discoverability** — ensure the notification-preferences page is reachable from a visible account/user nav (check the app header / user dropdown / any Account settings menu). If no nav link exists, add one (i18n label, D-013). If already linked, no change.
2. **End-to-end honor test** — add a Feature test proving the full loop: POST a pref change (e.g. disable `discord` for `match_starting_soon`) → assert `User::enabledNotificationChannels('match_starting_soon')` no longer includes `discord` (and the matching Notification's `via()` reflects it). This closes the SC's "honor those choices from that point forward" with an integration test if one doesn't already exist.
Do NOT rebuild the matrix/controller/Vue — they are complete.

### BOT-01 — button-based in-message pagination (the new work)
The web list endpoints already return `{ data: [...], meta: { current_page, per_page, total, last_page } }` (BotApiMatchController/BotApiClanController index). Bot side:
1. **customIds** (apps/bot/src/lib/customIds.ts): add a pagination action to the ButtonAction union + encode/decode, e.g. `kind: 'list_page'` encoded as `pg:m:<page>` (match) / `pg:c:<page>` (clan) — short prefix, well under the 100-char budget. Strict arity decode (parts.length === 3).
2. **Buttons** (apps/bot/src/lib/buttons.ts): Prev/Next ButtonBuilder factory for a list+page, disabled at bounds (page<=1 disables Prev; page>=last_page disables Next).
3. **List commands** (commands/match.ts `/match list`, commands/clan.ts `/clan list`): fetch with `?page=N` (default 1) via api.get<{data,meta}>; render the page's items (matchCard each for matches; the existing clan list formatter) + an ActionRow with Prev/Next buttons when `meta.last_page > 1`; show "Page X of Y" (i18n). The list responses are `{ data, meta }` — read both (envelope gotcha [[project_bot_web_data_envelope]]).
4. **Handler**: a button branch (interactionCreate / rsvpButton dispatch) for `list_page` that re-fetches the requested page and `interaction.update()`s the SAME message with the new page + updated buttons (NOT a new reply — "in the same message").
5. Empty-list + single-page cases: no buttons (existing behavior preserved). `/match list` currently top-5 slice — replace with API pagination (per_page from meta).
</decisions>

<code_context>
## Existing Code Insights
- WEB (NOTF-01, complete): Account/NotificationPreferencesController.php, routes/web.php:167-170, pages/Account/NotificationPreferences.vue, User::enabledNotificationChannels (User.php:200), the 5 Notifications/*.php (via() uses enabledNotificationChannels). App header / user-menu Vue component for the nav link (find it — likely components/ or a layout).
- BOT (BOT-01): apps/bot/src/lib/customIds.ts (encode/decode + ButtonAction union), apps/bot/src/lib/buttons.ts (openSignupModalButton factory analog), apps/bot/src/commands/match.ts (`/match list` top-5 slice) + clan.ts (`/clan list`), apps/bot/src/events/interactionCreate.ts + components/rsvpButton.ts (button dispatch), apps/bot/src/lib/embeds.ts (matchCard). Tests: tests/lib/customIds.test.ts, tests/commands/match.test.ts + clan.test.ts, tests/components/rsvpButton.test.ts.

## Verification (Docker available)
- Web: `make pest ARGS="--filter=<X>"`, `make pint`, `make phpstan`, `(cd apps/web && node_modules/.bin/vue-tsc --noEmit)`.
- Bot: `cd apps/bot && node_modules/.bin/vitest run && node_modules/.bin/tsc --noEmit && node_modules/.bin/eslint .` (the bulk of this phase).
</code_context>

<specifics>
- BOT-01 customId must round-trip (decode null on malformed — existing T-05-08-04 pattern). Test: list_page encode/decode; list command renders Prev/Next only when last_page>1, disabled at bounds; handler updates the same message to the requested page.
- NOTF-01: prefer adding the integration honor-test even if a unit test exists, to nail the SC end-to-end.
</specifics>

<deferred>
## Deferred
- Per-notification mute schedules / digests (NOTF-V2-02).
- Select-menu jump-to-page (v1.1 ships Prev/Next only).
</deferred>
