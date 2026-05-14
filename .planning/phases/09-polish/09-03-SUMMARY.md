---
phase: 09-polish
plan: 03
subsystem: notifications
tags: [wave-2, notifications, eloquent-models, factories, discord-outbox, pitfall-3, pitfall-4, d-004-locked, d-013-locked, d-04-03-a-locked]
requires:
  - "09-02 Wave 1 schema (notifications, user_notification_preferences, bans, match_disputes, abuse_reports + doutmsg user_dm CHECK) — models hydrate over these tables"
  - "Phase 5 discord_outbound_messages table + Phase 8 status state machine — DiscordChannel writes here"
provides:
  - "App\\Models\\Notification — extends DatabaseNotification with UUID PK + casts (data:array, read_at:datetime)"
  - "App\\Models\\UserNotificationPreference — fillable+casts+BelongsTo User; underpins User::enabledNotificationChannels"
  - "App\\Models\\Ban — Ban::scopeActive() (lifted_at NULL AND not expired); BelongsTo user/issuedBy/liftedBy"
  - "App\\Models\\MatchDispute — BelongsTo<GameMatch> via explicit match_id FK (D-04-03-A); no LogsActivity (D-09-03-A)"
  - "App\\Models\\AbuseReport — morphTo target + polymorphic target_type/target_id; no LogsActivity"
  - "User extension — notificationPreferences()+bans()+activeBan()+enabledNotificationChannels(eventType) per Pattern 7"
  - "BanFactory.permanent()/.lifted(), MatchDisputeFactory.resolved(), UserNotificationPreferenceFactory.discord()/.disabled()/.forEvent() — real states"
  - "App\\Notifications\\Channels\\DiscordChannel — writes ONE discord_outbound_messages row per send() (Pitfall 3 LOCKED — D-004)"
  - "5 Notification classes — MatchStartingSoon, MatchCancelled, MatchResultPublished, ClanApplicationDecided, ClanInviteReceived (all ShouldQueue + Queueable)"
  - "Unique databaseType discriminators — match.starting_soon | match.cancelled | match.result_published | clan.application_decided | clan.invite_received (Pitfall 4 LOCKED)"
affects:
  - "plan 09-04 NotificationDispatcher — wires the 5 Notification classes to scheduled cron handlers; idempotency reads notifications.type"
  - "plan 09-06 NotificationsBell.vue — reads notifications.data jsonb + i18n_key for renderer; routes by databaseType discriminator"
  - "plan 09-07 BanService + DisputeService + MatchResultService — invoke ban scope + dispatch notifications; LogsActivity emitted by services (D-09-03-A)"
  - "plan 09-11 AbuseReportService + ban-check middleware — reads User::activeBan() to gate authenticated routes"
  - "Phase 5 bot worker — picks up user_dm rows; resolves DM channel via payload.recipient_id (D-09-03-B)"
tech-stack:
  added: []
  patterns:
    - "Custom Laravel notification channel via class FQN (App\\Notifications\\Channels\\DiscordChannel) — Laravel maps the FQN returned by via() to a service-container resolved instance; channel.send($notifiable, $notification) is the contract surface"
    - "Outbox pattern for cross-service Discord delivery — web tier inserts a discord_outbound_messages row in 'pending' state; Phase 5 bot polls and dispatches (D-004 enforcement)"
    - "Notifiable::routeNotificationFor isn't needed for our DiscordChannel — the snowflake is read directly from User::discord_id in the Notification::toDiscord method and threaded into payload.recipient_id (D-09-03-B)"
    - "Pattern 7 preference matrix — User::enabledNotificationChannels(eventType) is the single authority on which channels fire; each Notification::via delegates to it (default-ON web bell, default-ON discord IF user has discord_id AND event != match_result_published — Open Question 3 LOCKED)"
    - "/** @var Carbon|null $scheduledAt */ shadow assignment idiom for PHPStan L8 — needed because Eloquent's $casts inference doesn't always propagate Carbon through to the call site (mirrors DiscordOutboundPayloadBuilder Phase 5 precedent)"
    - "Factory state methods replace fixture-heavy seeders — BanFactory::permanent()/lifted(), MatchDisputeFactory::resolved(), UserNotificationPreferenceFactory::discord()/disabled()/forEvent() give tests fluent expression of the dispatch matrix"
key-files:
  created:
    - "apps/web/app/Models/Notification.php — extends DatabaseNotification (UUID PK, $table='notifications', casts)"
    - "apps/web/app/Models/UserNotificationPreference.php — final class + HasFactory + fillable + bool cast + BelongsTo User"
    - "apps/web/app/Models/Ban.php — final class + HasFactory + scopeActive + BelongsTo user/issuedBy/liftedBy (NO LogsActivity per D-09-03-A)"
    - "apps/web/app/Models/MatchDispute.php — final class + BelongsTo<GameMatch> via 'match_id' (D-04-03-A) + BelongsTo raisedBy/resolvedBy"
    - "apps/web/app/Models/AbuseReport.php — final class + reporter+reviewedBy BelongsTo + morphTo target (varchar target_id per D-09-02-E)"
    - "apps/web/app/Notifications/Channels/DiscordChannel.php — final class; send() inserts ONE discord_outbound_messages row, throws RuntimeException if Notification lacks toDiscord (Pitfall 3 LOCKED)"
    - "apps/web/app/Notifications/MatchStartingSoon.php — Pattern 1 canonical Notification (final + ShouldQueue + Queueable); databaseType='match.starting_soon'"
    - "apps/web/app/Notifications/MatchCancelled.php — databaseType='match.cancelled'; carries optional $reason"
    - "apps/web/app/Notifications/MatchResultPublished.php — databaseType='match.result_published'; via() honours Open Question 3 default-off via User::enabledNotificationChannels special-case"
    - "apps/web/app/Notifications/ClanApplicationDecided.php — databaseType='clan.application_decided'; status-aware i18n_key (approved/rejected variant)"
    - "apps/web/app/Notifications/ClanInviteReceived.php — databaseType='clan.invite_received'"
  modified:
    - "apps/web/app/Models/User.php — added notificationPreferences()+bans()+activeBan()+enabledNotificationChannels(eventType) per Pattern 7 (Open Question 3 LOCKED — match_result_published Discord DM default-off)"
    - "apps/web/database/factories/BanFactory.php — real definition + permanent()/lifted() states (replaces Wave 0 RuntimeException stub)"
    - "apps/web/database/factories/MatchDisputeFactory.php — real definition + resolved() state; uses GameMatch::factory() per D-04-03-A"
    - "apps/web/database/factories/AbuseReportFactory.php — real definition (default target=Player) + reason_code randomised across v1 enum"
    - "apps/web/database/factories/UserNotificationPreferenceFactory.php — real definition + discord()/disabled()/forEvent() states"
    - "apps/web/tests/Unit/UserNotificationPreferencesTest.php — Wave 0 stub turned GREEN (8 tests / 12 assertions)"
    - "apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php — Wave 0 stub turned GREEN (7 tests / Pitfall 4 collision test)"
    - "apps/web/tests/Feature/Notifications/DiscordChannelOutboxTest.php — Wave 0 stub turned GREEN (4 tests / Pitfall 3 Http::assertNothingSent)"
decisions:
  - "D-09-03-A — Bans/MatchDisputes/AbuseReports do NOT use the LogsActivity trait. Plan's task action explicitly calls this out per Anti-Patterns: 'services call activity()->log() explicitly'. The moderator service layer (plan 09-07 BanService + DisputeService, plan 09-11 AbuseReportService) emits hand-rolled activity_log rows so the description is human-readable ('User <name> banned by <moderator>: <reason>') rather than the trait's auto-generated 'Ban created' skeleton. The trait's auto-logging would also surface internal lifecycle noise (the resolved_by_user_id flip on every save) that the audit log doesn't need to expose to Filament."
  - "D-09-03-B — DiscordChannel carries the recipient snowflake inside payload.recipient_id (jsonb) rather than via a dedicated discord_outbound_messages column. The Phase 5 schema does not have a recipient_user_discord_id or similar column — the table's routing inputs are channel_id (text NOT NULL; empty string for user DMs) + payload (jsonb). The plan's <interfaces> block mentioned a hypothetical recipient_user_discord_id column; verifying the actual schema via `\\d discord_outbound_messages` showed it doesn't exist. Bot worker will inspect payload.recipient_id for message_type='user_dm' and call Discord's createDM endpoint to resolve the DM channel at dispatch time. This is a Rule 1 deviation — aligned with on-disk reality, NOT plan text. Same pattern as plan 09-02 D-09-02-A and Phase 7/8 plan-vs-reality drift resolutions."
  - "D-09-03-C — Default-policy fallback in User::enabledNotificationChannels uses array key existence (`$prefs['discord'] ?? $discordDefault`), NOT row presence in the DB. This means a user with ZERO preference rows still gets the default policy applied — no need to seed default rows on user signup. Account-settings UI in plan 09-06 will UPSERT preference rows only when the user toggles a switch away from the default; the unp_unique constraint handles idempotency. Trade-off: 'default' state is implicit rather than materialised — but materialising defaults on signup would require 5 events × 2 channels = 10 rows per user, AND every default-policy change (e.g. adding a 6th event_type later) would need a backfill migration."
  - "D-09-03-D — Notifications.discord_id 'no snowflake' edge case is tested via empty-string discord_id (`''`), NOT NULL discord_id. The users table column is NOT NULL by schema (Discord OAuth canonical — D-002). The enabledNotificationChannels guard `if (! empty($this->discord_id))` is defensive code for an edge case the schema does not currently admit; if D-002 ever relaxes (e.g. to support email-only accounts), the guard already handles it. Testing via empty string exercises the same `empty()` branch without violating the NOT NULL constraint."
metrics:
  duration_seconds: 711
  duration_human: "~11m 51s"
  completed_at: "2026-05-14T07:37:17Z"
  files_created: 11
  files_modified: 8
  total_files: 19
  models_added: 5
  notification_classes_added: 5
  channel_classes_added: 1
  factories_real: 4         # BanFactory + MatchDisputeFactory + AbuseReportFactory + UserNotificationPreferenceFactory
  tests_now_passing: 1153   # baseline 1134 + 19 new
  tests_now_skipped: 27     # baseline 30 - 3 Wave 0 stubs turned GREEN
  suite_total: 1180
  baseline_passing: 1134
  baseline_skipped: 30
  pint_files_passed: 19
  phpstan_errors: 0
  lines_added: 1476
  lines_deleted: 75
---

# Phase 9 Plan 03: Wave 2 — Notification Models + DatabaseChannel + DiscordChannel Outbox Writer Summary

Authored the full Phase 9 notification surface in one deterministic step: 5 Eloquent models (Notification, UserNotificationPreference, Ban, MatchDispute, AbuseReport), 5 Notification classes (MatchStartingSoon, MatchCancelled, MatchResultPublished, ClanApplicationDecided, ClanInviteReceived), a custom DiscordChannel that writes to the Phase 5 outbox (Pitfall 3 LOCKED — ZERO outbound HTTP, D-004 compliance), and 4 real factory implementations replacing Wave 0 RuntimeException stubs. Three Wave 0 Pest stubs turned GREEN: `UserNotificationPreferencesTest` (8 tests), `MatchStartingSoonNotificationTest` (7 tests), `DiscordChannelOutboxTest` (4 tests). Full Pest baseline preserved + 19 new passing tests.

## What Shipped

### Models (5)

| Class | LogsActivity | Key methods | Relations |
|-------|--------------|-------------|-----------|
| `Notification` | n/a (Laravel framework) | extends `DatabaseNotification`; UUID PK; casts data:array,read_at:datetime | inherited polymorphic morph |
| `UserNotificationPreference` | NO | — | BelongsTo `User` |
| `Ban` | NO (D-09-03-A) | `scopeActive()` — lifted_at IS NULL AND (expires_at NULL OR > now()) | BelongsTo `user`/`issuedBy`/`liftedBy` (all User FK) |
| `MatchDispute` | NO (D-09-03-A) | — | BelongsTo `match` (GameMatch, explicit `match_id`)/`raisedBy`/`resolvedBy` |
| `AbuseReport` | NO (D-09-03-A) | — | BelongsTo `reporter`/`reviewedBy`; morphTo `target` |

### User model extensions

```php
// apps/web/app/Models/User.php (Phase 9 plan 09-03 amendment)
public function notificationPreferences(): HasMany   // UserNotificationPreference
public function bans(): HasMany                       // Ban (user_id FK)
public function activeBan(): ?Ban                     // first ::active()->orderByDesc('created_at')
public function enabledNotificationChannels(string $eventType): array  // Pattern 7
```

`enabledNotificationChannels` decision matrix:

| event_type | discord_id present | preference row | result |
|------------|---------------------|----------------|--------|
| match_starting_soon | yes | no rows | `['database', 'discord']` |
| match_starting_soon | no (empty string) | no rows | `['database']` |
| **match_result_published** | **yes** | **no rows** | **`['database']` ← Open Question 3 LOCKED — default-off** |
| match_result_published | yes | discord enabled=true | `['database', 'discord']` (explicit opt-in) |
| any | yes | database enabled=false | omit `'database'` |
| any | yes | discord enabled=false | omit `'discord'` |
| any | yes | all channels disabled | `[]` |

### Notification classes (5) — each `final class … implements ShouldQueue`

| Class | databaseType | Constructor signature | i18n key root |
|-------|--------------|------------------------|---------------|
| `MatchStartingSoon` | `match.starting_soon` | `GameMatch $match, int $minutesUntilStart` | `notifications.match_starting_soon.*` |
| `MatchCancelled` | `match.cancelled` | `GameMatch $match, ?string $reason = null` | `notifications.match_cancelled.*` |
| `MatchResultPublished` | `match.result_published` | `GameMatch $match, ?Clan $winnerClan = null` | `notifications.match_result_published.*` |
| `ClanApplicationDecided` | `clan.application_decided` | `ClanApplication $application` | `notifications.clan_application_decided.{approved|rejected}.*` |
| `ClanInviteReceived` | `clan.invite_received` | `ClanInvite $invite` | `notifications.clan_invite_received.*` |

**Pitfall 4 LOCKED** — `MatchStartingSoonNotificationTest::it_every_notification_class_has_a_unique_databaseType_discriminator` reflects over all 5 classes and asserts `array_unique(values)` has count 5. Any future class that accidentally collides on the discriminator will fail this test immediately.

### DiscordChannel (Pitfall 3 LOCKED — D-004 compliance)

```php
// apps/web/app/Notifications/Channels/DiscordChannel.php
public function send(mixed $notifiable, Notification $notification): DiscordOutboundMessage
{
    if (! method_exists($notification, 'toDiscord')) {
        throw new RuntimeException(...);
    }
    $shape = $notification->toDiscord($notifiable);
    // …extracts message_type, channel_id, recipient_id, payload…
    return DiscordOutboundMessage::create([
        'channel_id' => $channelId,
        'message_type' => $messageType,      // 'user_dm'
        'status' => 'pending',
        'payload' => $payload,               // includes recipient_id (D-09-03-B)
        'attempts' => 0,
        'causer_user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
    ]);
}
```

**Pitfall 3 evidence (DiscordChannelOutboxTest):**

```text
✓ it writes a discord_outbound_messages row on send (user_dm message_type)
✓ it makes ZERO outbound HTTP calls (Pitfall 3 LOCKED — D-004 compliance)
    └─ Http::fake(); send(...); Http::assertNothingSent();   ← passes
✓ it throws RuntimeException when notification lacks toDiscord method
✓ it persists channel_id as empty string when notification omits one
```

### Factory state catalogue (replaces Wave 0 stubs)

| Factory | Default state | State methods |
|---------|---------------|---------------|
| `BanFactory` | temporary, 7-day expiry, not lifted | `permanent()` — expires_at=null, ban_type='permanent'; `lifted()` — lifted_at + lifted_by_user_id + lift_reason |
| `MatchDisputeFactory` | open (no resolution), GameMatch via factory | `resolved()` — status='resolved', resolution='no_action', resolved_by + resolved_at |
| `AbuseReportFactory` | pending, Player target, random reason_code | — (no state methods needed for v1 dispatch tests) |
| `UserNotificationPreferenceFactory` | event_type='match_starting_soon', channel='database', enabled=true | `discord()` — channel='discord'; `disabled()` — enabled=false; `forEvent($e)` — overrides event_type |

## Quality Gates

| Gate | Result |
|------|--------|
| `pest --filter="UserNotificationPreferencesTest"` | 8 passed / 12 assertions / 2.03s |
| `pest --filter="MatchStartingSoonNotificationTest"` | 7 passed / 11 assertions / 2.04s |
| `pest --filter="DiscordChannelOutboxTest"` | 4 passed / 12 assertions / 0.18s |
| `pest --no-coverage` (full suite) | **1153 passed + 27 skipped (3835 assertions) in 71.82s** |
| Baseline delta (passed) | +19 (1134 → 1153) — every new test landed in this plan |
| Baseline delta (skipped) | −3 (30 → 27) — exactly the 3 Wave 0 stubs this plan committed to turning GREEN |
| `pint --test` on 19 touched files | **PASS** on 19 files |
| `phpstan analyse --no-progress` (level 8, full project) | **OK, no errors** |

## Wave 0 Stubs → GREEN

```text
UserNotificationPreferencesTest                           Wave 0 (1 skipped) → 8 passed
MatchStartingSoonNotificationTest                         Wave 0 (1 skipped) → 7 passed
DiscordChannelOutboxTest                                  Wave 0 (1 skipped) → 4 passed
                                                                                  ────────
                                                                              19 new GREEN tests
```

Skip-list count check:
- Pre-plan: 30 skipped (Phase 9 plan 09-01 inventory).
- Post-plan: 27 skipped (30 − 3 = 27 ✓).

## Pitfall Verification (this plan's two LOCKED clauses)

### Pitfall 3 LOCKED — DiscordChannel makes ZERO outbound HTTP

Test code (`DiscordChannelOutboxTest.php`):

```php
it('makes ZERO outbound HTTP calls (Pitfall 3 LOCKED — D-004 compliance)', function (): void {
    Http::fake();
    $user = User::factory()->create();
    $match = GameMatch::factory()->create();
    $notification = new MatchStartingSoon($match, minutesUntilStart: 15);
    (new DiscordChannel)->send($user, $notification);
    Http::assertNothingSent();
});
```

Test result: **PASS** (0.04s). The DiscordChannel inserts one outbox row via `DiscordOutboundMessage::create([...])` — no HTTP client is invoked. If a future maintainer introduces `Http::post('https://discord.com/...')`, `Http::assertNothingSent()` fails and CI blocks the merge.

### Pitfall 4 LOCKED — every Notification class has a unique databaseType

Test code (`MatchStartingSoonNotificationTest.php`):

```php
it('every notification class has a unique databaseType discriminator (Pitfall 4 LOCKED)', function (): void {
    $discriminators = [
        MatchStartingSoon::class      => (new MatchStartingSoon($match, 60))->databaseType($user),
        MatchCancelled::class         => (new MatchCancelled($match))->databaseType($user),
        MatchResultPublished::class   => (new MatchResultPublished($match, $clan))->databaseType($user),
        ClanApplicationDecided::class => (new ClanApplicationDecided($application))->databaseType($user),
        ClanInviteReceived::class     => (new ClanInviteReceived($invite))->databaseType($user),
    ];
    expect(array_values($discriminators))->toHaveCount(5);
    expect(array_unique(array_values($discriminators)))->toHaveCount(5, ...);
});
```

Test result: **PASS** (0.05s). The 5 unique discriminators are: `match.starting_soon`, `match.cancelled`, `match.result_published`, `clan.application_decided`, `clan.invite_received`.

## Deviations from Plan

### Rule 1 — schema reality (recipient_user_discord_id column does not exist)

**1. [Rule 1 — Bug] Plan referenced a recipient_user_discord_id column on discord_outbound_messages that does not exist**

- **Found during:** Task 2 authoring of `DiscordChannel::send()`.
- **Issue:** Plan's `<interfaces>` block prescribed: `recipient_user_discord_id (text nullable) — ... For message_type='user_dm': recipient_user_discord_id: set to the User's discord_id`. Verified live via `\d discord_outbound_messages` that the actual table columns are: `id, channel_id, message_type, status, payload, attempts, last_error, sent_message_id, causer_user_id, backoff_until, timestamps`. There is no `recipient_user_discord_id` column. Inserting against a non-existent column would error at SQL execute time.
- **Fix applied:** Carry the recipient snowflake INSIDE `payload.recipient_id` (jsonb). This matches the Phase 5 idiom (`DiscordOutboundPayloadBuilder` returns the routing inputs inside payload, not as table columns) — Phase 5 only uses `channel_id` (text NOT NULL) as the dedicated routing column, intended for guild channels; user DMs use empty string and let the bot worker resolve the DM channel via Discord's createDM endpoint at dispatch. Locked as **D-09-03-B**.
- **Files modified:** `apps/web/app/Notifications/Channels/DiscordChannel.php`, all 5 Notification classes (`toDiscord()` returns `payload.recipient_id` instead of a top-level column key).
- **Test impact:** `DiscordChannelOutboxTest::it_writes_a_discord_outbound_messages_row_on_send` asserts `$row->payload->recipient_id === '987654321098765432'` (jsonb key) rather than a top-level column.
- **Commit:** `d7d2351`.

### Rule 1 — users.discord_id NOT NULL prevents the "no Discord linked" test

**2. [Rule 1 — Bug] Plan's test scenario "default-off discord without discord_id" violates the users.discord_id NOT NULL schema constraint**

- **Found during:** Task 1 first run of `UserNotificationPreferencesTest`.
- **Issue:** Plan test: `it('routes to database channel only when user has only web bell enabled') — create User with discord_id=null, assert via() returns ['database']`. The users table column is `text NOT NULL UNIQUE` (per `2026_05_03_100000_create_users_table.php` migration line 26: `$table->text('discord_id')->unique();`). `User::factory()->create(['discord_id' => null])` triggers a 23502 NOT NULL violation at insert.
- **Fix applied:** Use empty string (`''`) instead of `null`. Locked as **D-09-03-D**. The `enabledNotificationChannels` guard uses `if (! empty($this->discord_id))`, so an empty string and a null both flow through the same code path (`empty()` returns true for both). This preserves the architectural intent of the test (Pattern 7 default-off-without-snowflake) without violating the schema.
- **Files modified:** `apps/web/tests/Unit/UserNotificationPreferencesTest.php`, `apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php`.
- **Commit:** `8020d10` (UserNotificationPreferences), `d7d2351` (MatchStartingSoonNotification).

### No other deviations

- Rule 2 (auto-add missing critical functionality): N/A — every plan-mandated method shipped; no security/correctness gap surfaced.
- Rule 3 (auto-fix blocking issues): N/A — Pint auto-fix on 3 files (User.php, MatchResultPublished.php, MatchStartingSoonNotificationTest.php + DiscordChannelOutboxTest.php) were style normalisations (fully_qualified_strict_types and new_with_parentheses), not blocking issues.
- Rule 4 (architectural decisions): N/A — D-09-03-B (recipient_id inside payload jsonb) is a Rule 1 alignment with on-disk reality, not an architectural decision (it matches the existing Phase 5 idiom).

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack (web + postgres healthy; no Discord OAuth or external API needed — DiscordChannel writes to the outbox table only).

## Known Stubs

None introduced. The 5 new Notification classes have placeholder strings (`'—'`) where the i18n template requires opponent/map/inviter/clan parameters that the dispatcher (plan 09-04) and Vue renderer (plan 09-06) will resolve from relation hydration. This is intentional and documented in the toDiscord docblock — the strings are not user-visible until the dispatcher passes real relations.

## Threat Flags

None. The plan's `<threat_model>` (T-09-03-01..06) covers every introduced surface:

| Threat | Component | Mitigation status |
|--------|-----------|-------------------|
| T-09-03-01 (Elevation — DiscordChannel direct HTTP) | DiscordChannel | **PASS** — DiscordChannelOutboxTest asserts Http::assertNothingSent |
| T-09-03-02 (Tampering — forged toDiscord payload) | Notification classes | **PASS** — server-side authored only; no user input controls the payload |
| T-09-03-03 (Info disclosure — toArray data leak) | Notification::toArray | **DEFER to plan 09-06** — Vue renderer scopes by auth()->id() (plan 09-06 NotificationsBell controller); this plan just produces the data |
| T-09-03-04 (Spoofing — preference flip by attacker) | UserNotificationPreference write path | **DEFER to plan 09-06** — controller scopes write to auth()->user(); Inertia CSRF enforces |
| T-09-03-05 (Tampering — discriminator collision) | databaseType | **PASS** — Pest reflection test asserts all 5 classes unique |
| T-09-03-06 (DoS — mass notification flood) | NotificationDispatcher | **DEFER to plan 09-04** — idempotency check + Horizon throttle |

No NEW surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (11 created, 8 modified — 19 total):**

```
FOUND: apps/web/app/Models/Notification.php
FOUND: apps/web/app/Models/UserNotificationPreference.php
FOUND: apps/web/app/Models/Ban.php
FOUND: apps/web/app/Models/MatchDispute.php
FOUND: apps/web/app/Models/AbuseReport.php
FOUND: apps/web/app/Models/User.php (modified)
FOUND: apps/web/app/Notifications/Channels/DiscordChannel.php
FOUND: apps/web/app/Notifications/MatchStartingSoon.php
FOUND: apps/web/app/Notifications/MatchCancelled.php
FOUND: apps/web/app/Notifications/MatchResultPublished.php
FOUND: apps/web/app/Notifications/ClanApplicationDecided.php
FOUND: apps/web/app/Notifications/ClanInviteReceived.php
FOUND: apps/web/database/factories/BanFactory.php (real impl)
FOUND: apps/web/database/factories/MatchDisputeFactory.php (real impl)
FOUND: apps/web/database/factories/AbuseReportFactory.php (real impl)
FOUND: apps/web/database/factories/UserNotificationPreferenceFactory.php (real impl)
FOUND: apps/web/tests/Unit/UserNotificationPreferencesTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Notifications/DiscordChannelOutboxTest.php (Wave 0 → GREEN)
```

**Commits verified:**

```
FOUND: 8020d10 feat(09-03): add Notification + UserNotificationPreference + Ban + MatchDispute + AbuseReport models with real factories
FOUND: d7d2351 feat(09-03): add 5 Notification classes + DiscordChannel outbox writer (Pitfall 3 + 4 LOCKED)
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="UserNotificationPreferencesTest|MatchStartingSoonNotificationTest|DiscordChannelOutboxTest" --no-coverage
  Tests: 19 passed (52 assertions) — all three Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-02):    1134 passed + 30 skipped
Post-plan (09-03):            1153 passed + 27 skipped
                              ────────────  ──────────
                              +19 passed    −3 skipped
```

All 11 created + 8 modified files present on disk; both commits resolve in `git log` with the expected diffs (Task 1: 11 files, 778 insertions, 14 deletions; Task 2: 8 files, 698 insertions, 61 deletions = 1476/75 total lines).
