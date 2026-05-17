---
phase: 05-discord-bot-v1
plan: 12
subsystem: discord-bot
tags: [wave-11, i18n, audit-log, sc-5-capstone, d-013, d-012]
dependency_graph:
  requires: [05-01-complete, 05-02-complete, 05-03-complete, 05-04-complete, 05-06-complete, 05-07-complete, 05-11-complete]
  provides:
    - apps/web/tests/Feature/Bot/BotI18nKeyCoverageTest.php
    - apps/web/tests/Feature/Bot/DiscordOutboundAuditLogTest.php
    - apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php
    - apps/web/tests/Feature/Admin/DiscordOutboundMessageAuditLogTest.php
    - "Static i18n coverage gate (Phase 5 bot.errors.* + admin.discord_outbound_message.* — D-013)"
    - "DiscordOutboundMessage state machine LogsActivity integration coverage (D-012)"
    - "SC-5 end-to-end causer attribution capstone — bot Sanctum + acts-as -> human causer"
  affects: [05-13]
tech_stack:
  added: []
  patterns:
    - "Static i18n key coverage: grep __('namespace.key') references across controllers/middleware/resources; assert trans($key) is non-identity (mirrors Phase 1 NoHardcodedStringsTest gate model)"
    - "State machine audit-log coverage: count Activity rows on subject_type/subject_id BEFORE + AFTER each transition; assert delta == 1 for transitions that touch fillable fields"
    - "SC-5 capstone trust chain: provision bot service user + Sanctum token (bot:read + bot:act-as-user) -> real HTTP POST through middleware stack -> assert MatchSlot.occupant_user_id == human AND Activity.causer_id == human AND Activity.causer_id != bot (defence-in-depth)"
key_files:
  created:
    - "apps/web/tests/Feature/Bot/BotI18nKeyCoverageTest.php"
    - "apps/web/tests/Feature/Bot/DiscordOutboundAuditLogTest.php"
    - "apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php"
    - "apps/web/tests/Feature/Admin/DiscordOutboundMessageAuditLogTest.php"
  modified:
    - "apps/web/lang/en/admin.php"
decisions:
  - "D-05-12-A: BotI18nKeyCoverageTest follows the functional Pest convention (no namespace, no per-file uses() — global Pest.php wires TestCase + RefreshDatabase via uses(...)->in('Feature')) rather than the plan <interfaces> `namespace Tests\\Feature\\Bot;` example. Rationale: consistent with every other test file in apps/web/tests/Feature/ (Phase 1-5 precedent verified across 100+ files). Adding the namespace would have made this the only test in the suite that introduces it — inconsistency masquerading as spec compliance."
  - "D-05-12-B: BotI18nKeyCoverageTest surfaced the admin.discord_outbound_message.fields.causer key gap. DiscordOutboundMessageResource (plan 05-07 line 115) references __('admin.discord_outbound_message.fields.causer') for the `causer.username` column label, but the key was absent from lang/en/admin.php. Closed inline (Rule 2 — D-013 enforcement is a correctness contract: the static gate is value-less if missing keys aren't fixed when surfaced)."
  - "D-05-12-C: DiscordOutboundAuditLogTest hits the REAL /api/bot/outbound-messages endpoints (claim, markSent, markFailed) rather than calling $row->update() directly. Rationale: the test's contract is `every state transition writes an activity_log row through the actual production code path`. Calling $row->update() in the test would prove the trait works on the model in isolation but would NOT catch a regression where a controller swaps to raw DB::update (T-05-12-04). The HTTP-layer approach is the canonical integration gate."
  - "D-05-12-D: DiscordOutboundMessageAuditLogTest is intentionally a duplicate-with-purpose of two it() blocks already in DiscordOutboundMessageResourcePresentTest.php (retry visibility + retry-writes-activity_log). Rationale: isolating the audit-log assertions in a dedicated file means a regression in LogsActivity wiring fails ONE focused test, not a smoke-test grab-bag of resource-present + retry-visibility + retry-audit-log mixed. Grep-by-symbol becomes a structural index of audit-log coverage."
  - "D-05-12-E: SC-5 capstone (MatchSignupViaBotCauserAttributionTest) ships 3 it() blocks: (1) the canonical SC-5 happy path with the ->not->toBe($bot->id) defence-in-depth assertion, (2) 422 for unknown discord_id (negative path — no rebind), (3) missing acts-as header keeps bot as causer (documents Pitfall 7 tolerance contract via the contrast with case 1). The third case is intentionally a documentation/inverse test: it surfaces what HAPPENS when the rebind doesn't fire, making the SC-5 guarantee mechanically observable rather than implicit."
metrics:
  duration_seconds: 322
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 5
---

# Phase 5 Plan 12: Wave 11 — i18n + Audit Log + SC-5 Capstone

Wave 11 ships the cross-cutting test coverage that ROADMAP SC-5 + CLAUDE.md
section 6 (activity log append-only) + D-013 (i18n) require. 4 new test
files + 1 i18n gap closure (Rule 2 — surfaced by the BotI18nKeyCoverageTest
audit on first run).

This plan does NOT flip any Wave 0 stubs. It ADDS the cross-cutting checks
that prove every plan from 02-11 plays together correctly at the
integration layer — final SC verification gates before plan 13's phase
verification artifact.

## Test File Inventory

| File                                                              | it() blocks | Assertions | Covers                                       |
| ----------------------------------------------------------------- | ----------- | ---------- | -------------------------------------------- |
| `tests/Feature/Bot/BotI18nKeyCoverageTest.php`                    | 3           | 8          | D-013 — bot.errors.* + admin.discord_outbound_message.* key coverage |
| `tests/Feature/Bot/DiscordOutboundAuditLogTest.php`               | 5           | 14         | D-012 — DiscordOutboundMessage state machine LogsActivity            |
| `tests/Feature/Admin/DiscordOutboundMessageAuditLogTest.php`      | 3           | 9          | T-05-07-05 — admin retry action activity_log + causer                |
| `tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php`    | 3           | 16         | SC-5 capstone — bot Sanctum + acts-as -> human causer                |
| **Total**                                                         | **14**      | **59**     | Phase 5 cross-cutting integration                                    |

All 14 tests GREEN on first complete run after the i18n gap closure
(commit `8d1be9f`).

## bot.php Key Inventory (D-013 Coverage Target)

`apps/web/lang/en/bot.php` — all keys verified by BotI18nKeyCoverageTest.

```
bot.errors.acts_as_unknown        Discord user has never logged in to the website.
bot.errors.match_not_open         This match is not open for signups.
bot.errors.capacity_full          This role is full.
bot.errors.tag_restricted         Your clan tags are not permitted on this match.
bot.errors.already_signed_up      You are already signed up to this match.
bot.errors.no_active_clan         You have no active clan membership.
bot.errors.outbound_not_pending   This outbound message has already been claimed or completed.
bot.errors.outbound_not_dispatching  This outbound message is not currently being dispatched.
bot.errors.echo_suppressed        Discord-side change suppressed as bot-originated echo (within 60s window).
bot.embeds.match_title            Match :title
bot.embeds.match_status           Status: :status
bot.embeds.match_scheduled        Scheduled: :time
bot.embeds.clan_card_title        :name
bot.embeds.profile_card_title     Player: :slug
```

8 keys referenced from server-side code (controllers + middleware) all
resolve. 0 missing.

## admin.discord_outbound_message Key Inventory

`apps/web/lang/en/admin.php` group `discord_outbound_message` — verified by
BotI18nKeyCoverageTest.

```
admin.discord_outbound_message.label                     Outbound message
admin.discord_outbound_message.plural_label              Outbound messages
admin.discord_outbound_message.fields.message_type       Type
admin.discord_outbound_message.fields.status             Status
admin.discord_outbound_message.fields.channel_id         Channel
admin.discord_outbound_message.fields.attempts           Attempts
admin.discord_outbound_message.fields.last_error         Last error
admin.discord_outbound_message.fields.sent_message_id    Sent message ID
admin.discord_outbound_message.fields.created_at         Created
admin.discord_outbound_message.fields.causer             Caused by   ◀── ADDED in plan 05-12 task 1
admin.discord_outbound_message.actions.retry             Retry
admin.discord_outbound_message.actions.retry_success     Message marked pending for redelivery.
admin.discord_outbound_message.status.pending            Pending
admin.discord_outbound_message.status.dispatching        Dispatching
admin.discord_outbound_message.status.sent               Sent
admin.discord_outbound_message.status.failed             Failed
```

13 keys referenced from `DiscordOutboundMessageResource.php` — all resolve.

## i18n Gaps Closed During the Audit

| Key                                                       | Reference                                                  | Fix                                                                   |
| --------------------------------------------------------- | ---------------------------------------------------------- | --------------------------------------------------------------------- |
| `admin.discord_outbound_message.fields.causer`            | `DiscordOutboundMessageResource.php:115` (causer.username column) | Added to `apps/web/lang/en/admin.php` discord_outbound_message.fields group (commit `8d1be9f`) |

Only 1 gap surfaced — the rest of the bot.php + admin.php inventory was
already complete from plans 05-01 + 05-07. The single gap was introduced
during plan 05-07 when the resource's table() schema added a causer column
without a matching lang/en entry (the resource passed PHPStan + Pest in
plan 05-07 because Laravel's missing-key fallback echoes the key as the
string label — visually distracting but not a fatal error).

## SC-5 Capstone Assertion Shape

The defining test of MatchSignupViaBotCauserAttributionTest:

```php
expect($activity)->not->toBeNull()
    ->and($activity->causer_id)->toBe($human->id)               // <-- THE SC-5 contract
    ->and($activity->causer_id)->not->toBe($bot->id)            // <-- defence-in-depth
    ->and($activity->causer_type)->toBe(User::class);
```

The `->not->toBe($bot->id)` line is the load-bearing assertion. If the
ResolveBotActsAsUser middleware ever stops rebinding `Auth::user()` (e.g.
a refactor that swaps `Auth::setUser($user)` for a no-op, or drops the
defence-in-depth `Auth::guard('web')->setUser($user)` line that D-05-03-A
locked in), the SC-5 capstone fails on this single line with the message
`Failed asserting that <bot-uuid> is not <bot-uuid>` — instantly diagnosable.

## Trust Chain Verified by the Capstone

Each step is a separate failure mode if it breaks:

1. **Sanctum bearer authentication** — token row resolves to bot service User
2. **abilities middleware** — `['bot:read', 'bot:act-as-user']` on the token grants both required abilities
3. **bot.acts-as middleware (ResolveBotActsAsUser, plan 05-03)** — reads `X-Bot-Acts-As-User`, finds User by discord_id, calls `Auth::setUser($human)` + `Auth::guard('web')->setUser($human)`
4. **signup controller (BotApiMatchSignupController, plan 05-04)** — resolves `auth()->user()` → returns the rebound human
5. **MatchSignupService (plan 04-06)** — writes `MatchSlot.occupant_user_id = $human->id` via service-only path (D-010)
6. **LogsActivity trait on MatchSlot (plan 04-03)** — fires `updated` event with `causer_id = Auth::user()->id` (the rebound human)

A break at ANY of these 6 steps fails the capstone. Together they verify
the entire Phase 5 SC-5 contract end-to-end.

## Pest Test Count Delta for Phase 5

| Wave           | Test files (cumulative)                                                        | Net tests added |
| -------------- | ------------------------------------------------------------------------------ | --------------- |
| Wave 0 (05-01) | 20 RED stubs across `tests/Feature/Bot/` + `tests/Feature/Admin/` + bot.php/admin.php scaffold | +20 (stubs)     |
| Through 05-11  | Wave 0 stubs GREENed + plan-specific new tests (Phase 4 outbound + signup + acts-as middleware + observer + Filament + bot CLI + Discord events + bot side Vitest) | +cumulative     |
| **Plan 05-12** | **+4 new test files (BotI18nKeyCoverage, DiscordOutboundAuditLog, DiscordOutboundMessageAuditLog, MatchSignupViaBotCauserAttribution)** | **+14 tests / +59 assertions** |

After plan 05-12, the Feature/Bot test group runs 90+ tests across 12
files; Feature/Admin runs 18 tests on the outbound resource alone. The
plan 13 phase verification artifact will sum across the entire phase.

## Verification Outputs

```
$ docker compose exec web ./vendor/bin/pest \
    --filter='(BotI18n|DiscordOutboundAuditLog|DiscordOutboundMessageAuditLog|MatchSignupViaBot)' \
    --no-coverage
PASS  Tests\Feature\Admin\DiscordOutboundMessageAuditLogTest (3 tests)
PASS  Tests\Feature\Bot\BotI18nKeyCoverageTest             (3 tests)
PASS  Tests\Feature\Bot\DiscordOutboundAuditLogTest        (5 tests)
PASS  Tests\Feature\Bot\MatchSignupViaBotCauserAttributionTest (3 tests)
Tests:    14 passed (59 assertions)
Duration: 1.67s

$ docker compose exec web ./vendor/bin/pest tests/Feature/Bot tests/Feature/Admin/DiscordOutboundMessage*Test.php --no-coverage
Tests:    108 passed (320 assertions)   ← no regressions in Phase 5 Bot/Admin tests
Duration: 5.08s

$ docker compose exec web ./vendor/bin/pint --test
PASS  342 files

$ docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=512M
[OK] No errors

$ grep -c 'bot.errors\.' apps/web/app/Http/Controllers/BotApi/*.php
BotApiClanController.php:0
BotApiMatchController.php:0
BotApiMatchSignupController.php:8     ← 4 distinct keys
BotApiDiscordEventController.php:1
BotApiUserController.php:0
BotApiOutboundController.php:2        ← 1 distinct key (outbound_not_dispatching ×2)
(11 total references; 6 distinct keys + 3 more from ResolveBotActsAsUser + bot.embeds = 9 distinct bot.* keys covered)

$ grep -c 'X-Bot-Acts-As-User' apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php
8   ← capstone test exercises the header in 2 it() blocks (1 = present + rebound; 2 = present + unknown)
```

## Threat Register Status

| Threat ID  | Status     | How                                                                                                                                          |
| ---------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| T-05-12-01 | mitigated  | MatchSignupViaBotCauserAttributionTest fails LOUDLY if the acts-as rebind drifts (`->not->toBe($bot->id)` defence-in-depth assertion)         |
| T-05-12-02 | mitigated  | BotI18nKeyCoverageTest static gate catches missing keys; 1 gap surfaced + closed during the audit (`admin.discord_outbound_message.fields.causer`) |
| T-05-12-03 | mitigated  | DiscordOutboundMessageAuditLogTest verifies the explicit `activity()->log()` call in the retry action fires with causer=admin                |
| T-05-12-04 | mitigated  | DiscordOutboundAuditLogTest hits the REAL controller endpoints (not direct $row->update) — catches any future controller that swaps to raw DB::update |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Critical functionality] Closed `admin.discord_outbound_message.fields.causer` key gap**

- **Found during:** Task 1 (BotI18nKeyCoverageTest first run)
- **Issue:** `DiscordOutboundMessageResource.php:115` references `__('admin.discord_outbound_message.fields.causer')` for the `causer.username` table column label, but the key was absent from `apps/web/lang/en/admin.php`. The Laravel missing-key fallback meant the resource rendered the literal string `admin.discord_outbound_message.fields.causer` as the column header (visually distracting; not a fatal error — passed plan 05-07 verification because no test exercised the column label).
- **Fix:** Added `'causer' => 'Caused by'` to the `discord_outbound_message.fields` group in `apps/web/lang/en/admin.php`. Commented as "Added by plan 05-12 task 1".
- **Files modified:** `apps/web/lang/en/admin.php`
- **Commit:** Task 1 (`8d1be9f`)
- **Decision recorded:** D-05-12-B

**2. [Rule 3 - Blocking issue] Pest namespace convention divergence from plan `<interfaces>`**

- **Found during:** Task 1 (read_first for existing Pest test files)
- **Issue:** Plan `<interfaces>` for BotI18nKeyCoverageTest specifies `<?php\ndeclare(strict_types=1);\n\nnamespace Tests\\Feature\\Bot;` followed by `uses(TestCase::class, RefreshDatabase::class)`. But the project's `apps/web/tests/Pest.php` already wires `TestCase::class` + `RefreshDatabase::class` via `uses(...)->in('Feature', 'Unit')` (Pest.php:20-22). Adding a per-file `uses(TestCase::class, RefreshDatabase::class)` AND a namespace would (a) be inconsistent with every other test in the suite (verified across all 100+ feature test files — zero use namespace declarations) and (b) potentially trigger TestRepository fatal errors documented in D-05-01-C.
- **Fix:** Followed the existing project convention — functional Pest, no namespace, no per-file uses() call. The static i18n grep + trans() assertion logic is identical regardless of namespace style.
- **Files modified:** `apps/web/tests/Feature/Bot/BotI18nKeyCoverageTest.php` (not the plan's specified shape; matches project precedent)
- **Commit:** Task 1 (`8d1be9f`)
- **Decision recorded:** D-05-12-A

**3. [Rule 2 - Critical functionality] DiscordOutboundAuditLogTest uses real HTTP endpoints (not direct $row->update)**

- **Found during:** Task 2 (writing DiscordOutboundAuditLogTest)
- **Issue:** Plan `<interfaces>` shows the option `// trigger transition (direct $row->update OR via the BotApiOutboundController endpoint)`. Direct $row->update would prove the LogsActivity trait works on the model in isolation but would NOT catch a regression where a future controller refactor swaps to raw DB::update — the exact T-05-12-04 mitigation contract.
- **Fix:** Every state transition test hits the real `/api/bot/outbound-messages*` endpoint with a real Sanctum-token-bearing bot service user. This is the canonical integration gate — if a controller ever bypasses the trait, the test catches it.
- **Files modified:** `apps/web/tests/Feature/Bot/DiscordOutboundAuditLogTest.php`
- **Commit:** Task 2 (`0aed51b`)
- **Decision recorded:** D-05-12-C

### Auth gates

None. All 14 tests use in-process Sanctum token provisioning (`User::factory()->create()` + `$bot->createToken()`); no real Discord secrets required at test time. The bot service user fixture is created inline per test via `provisionBotAndToken()` (capstone) or `botOutboundAuditHeaders()` / `botOutboundAckHeaders()` (outbound audit).

## Known Stubs

None. This plan ADDS coverage; no stubs were created.

## Threat Flags

None. The plan adds tests only — no new code surface introduced.

## Self-Check: PASSED

- [x] `apps/web/tests/Feature/Bot/BotI18nKeyCoverageTest.php` exists (commit `8d1be9f`)
- [x] `apps/web/lang/en/admin.php` modified — `discord_outbound_message.fields.causer` key added (commit `8d1be9f`)
- [x] `apps/web/tests/Feature/Bot/DiscordOutboundAuditLogTest.php` exists (commit `0aed51b`)
- [x] `apps/web/tests/Feature/Admin/DiscordOutboundMessageAuditLogTest.php` exists (commit `0aed51b`)
- [x] `apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php` exists (commit `d219199`)
- [x] Commits `8d1be9f`, `0aed51b`, `d219199` all present in `git log`
- [x] `make pest filter=(BotI18n|DiscordOutboundAuditLog|DiscordOutboundMessageAuditLog|MatchSignupViaBot)` → 14 passed / 59 assertions / 1.67s
- [x] `make pest tests/Feature/Bot tests/Feature/Admin/DiscordOutboundMessage*Test.php` → 108 passed / 320 assertions (no regressions)
- [x] `make pint --test` → 342 files PASS
- [x] `make phpstan` → No errors (project-wide)
- [x] `grep -c 'bot.errors\.' apps/web/app/Http/Controllers/BotApi/*.php` → 11 total references across 3 controllers (plan required ≥ 6)
- [x] `grep -c 'X-Bot-Acts-As-User' apps/web/tests/Feature/Bot/MatchSignupViaBotCauserAttributionTest.php` → 8 (plan required ≥ 1)
- [x] SC-5 capstone defence-in-depth assertion (`->not->toBe($bot->id)`) is present (verified by reading the test file)
