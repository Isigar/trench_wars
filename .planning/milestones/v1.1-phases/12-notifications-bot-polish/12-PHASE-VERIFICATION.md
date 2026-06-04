# Phase 12 — Notifications & Bot Polish: Phase Verification

**Verified:** 2026-06-04
**Executor:** claude-sonnet-4-6 (plan 12-05)

## Gate Results

| Gate | Command | Result | Detail |
|------|---------|--------|--------|
| Web — Pest | `make pest` | PASSED | 1370 tests, 4817 assertions |
| Web — Pint | `make pint --test` | PASSED | 675 files (1 style fix applied inline) |
| Web — PHPStan | `make phpstan` | PASSED | No errors (level 8, 427 files) |
| Web — vue-tsc | `vue-tsc --noEmit` | PASSED | No output (clean) |
| Bot — Vitest | `vitest run` | PASSED | 232 tests, 16 test files |
| Bot — tsc | `tsc --noEmit` | PASSED | No output (clean) |
| Bot — ESLint | `eslint .` | PASSED | No output (clean) |

**Schema state:** `migrate:fresh --seed` run before Pest.

## Requirement Traceability

### NOTF-01 — Notification preferences UX + dispatcher honor

**Success Criteria:**
> A user can open their account-settings page and see all notification event-types with their current preference (in-app / Discord DM / both / none) per channel, change any preference, and have the notification dispatcher honor those choices from that point forward.

| SC Component | Delivering Plan | Evidence |
|---|---|---|
| Preferences matrix (5 event-types × 2 channels, default-policy fallback) | 09-06 (pre-built) | `NotificationPreferencesController::edit()`, `NotificationPreferences.vue` |
| Nav discoverability (account-settings dropdown link) | 12-01 | `UserMenu.vue` `href="/account/notification-preferences"`, i18n key `common.nav.notification_preferences` |
| Dispatcher honors stored prefs (`via()` checks `enabledNotificationChannels`) | 09-06 (pre-built) | All 5 `Notifications/*.php` classes use `User::enabledNotificationChannels($eventType)` in `via()` |
| End-to-end honor test (POST pref → fresh() model → via() assert) | 12-01 | `NotificationPreferencesHonorTest.php` (2 tests, 7 assertions) |

**Status: PASSED** — all components tested green; honor test runs in `make pest`.

### BOT-01 — /match list + /clan list in-message pagination

**Success Criteria:**
> When `/match list` or `/clan list` returns more results than fit on a single Discord response, the user can navigate to subsequent pages using interactive controls in the same message.

| SC Component | Delivering Plan | Evidence |
|---|---|---|
| `list_page` customId: encode (`pg:m/<page>`, `pg:c/<page>`), decode (strict arity, null-on-malformed) | 12-02 | `customIds.ts`, `tests/lib/customIds.test.ts` |
| `paginationButtons` factory (Prev/Next bound-aware ActionRow) | 12-02 | `buttons.ts`, `tests/lib/buttons.test.ts` |
| `/match list` renders paginated results + "Page X of Y" + Prev/Next row | 12-03 | `commands/match.ts` `renderMatchListPage()`, `tests/commands/match.test.ts` |
| `/clan list` renders paginated results + "Page X of Y" + Prev/Next row | 12-03 | `commands/clan.ts` `renderClanListPage()`, `tests/commands/clan.test.ts` |
| Prev/Next button handler: `pg:` no-defer routing + `interaction.update()` same message | 12-04 | `rsvpButton.ts` `list_page` branch, `interactionCreate.ts` `startsWith('pg:')`, `tests/components/rsvpButton.test.ts` |

**Status: PASSED** — all components tested green; 232 bot Vitest tests pass.

### Live-Discord Smoke Test

**Status: human_needed** — The automated test suite covers all logic paths (encode/decode round-trip, disabled-at-bounds buttons, update() vs reply() interaction routing). A live Discord guild smoke test (clicking Prev/Next on a real bot response) requires a running bot instance connected to a guild and cannot be automated in CI. Operator must perform this smoke test before production deploy.

**Operator checklist:**
1. Ensure `BOT_TOKEN` + `DISCORD_BOT_CLIENT_ID` are set in Railway bot service.
2. Run `/match list` in the test guild — verify "Page 1 of N" appears with Next button (if N > 1).
3. Click Next — verify the same message updates to "Page 2 of N" with Prev+Next (or Prev-only on last page).
4. Run `/clan list` — same verification.
5. Confirm clicking a disabled button does not trigger an interaction error.

## Phase 12 Summary

All 5 plans complete. Both SCs verified by automated gate suites:
- **NOTF-01**: account-settings prefs page discoverable + dispatcher honors stored preferences (end-to-end Pest test green).
- **BOT-01**: in-message Prev/Next pagination fully implemented and tested (232 Vitest tests green).

No regressions in the full web suite (1370 tests, fresh schema). Phase 12 complete.
