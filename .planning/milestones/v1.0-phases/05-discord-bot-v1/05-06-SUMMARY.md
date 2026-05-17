---
phase: 05-discord-bot-v1
plan: 06
subsystem: discord-bot
tags: [wave-4, observers, jobs, horizon, role-sync, sc-4]
dependency_graph:
  requires: [05-02-complete, 05-04-complete, phase-02-complete]
  provides:
    - App\Jobs\SyncDiscordRolesJob
    - App\Observers\ClanMembershipObserver
    - canonical_role_sync_payload_shape
    - ClanMembership_booted_observer_registration
  affects: [05-11]
tech_stack:
  added:
    - "App\\Jobs\\ namespace (PSR-4 already covered by `App\\` → app/ — no composer.json change; directory created by this plan)"
  patterns:
    - "Horizon ShouldQueue job with $tries + backoff() array — RESEARCH §Standard Stack canonical retry contract"
    - "Primitive-only constructor (string membershipId, string action, ?string causerUserId) — never serialise Eloquent models into queue payload (Pitfall 6: model-deleted-between-dispatch-and-handle race)"
    - "Two-layer retry — Horizon $tries/backoff retries JOB on handle() crash; outbound table's backoff_until retries DELIVERY on Discord 429/5xx (plan 05-04 + 05-11 territory)"
    - "Observer + booted() registration (D-04-08-B precedent) — static::observe inside the model's booted() method, no AppServiceProvider plumbing, fires reliably under test"
    - "wasChanged('left_at') transition gate on observer.updated() — T-05-06-04 mitigation; role/joined_at edits do NOT trigger role-sync dispatches"
    - "Bidirectional left_at transition support — null→NOT NULL = remove; NOT NULL→null = add (re-join scenario, rare but supported)"
    - "Queue::fake() + Queue::assertPushed/assertNotPushed for dispatch matrix tests; direct ->handle() invocation for body tests (no Queue::fake)"
key_files:
  created:
    - "apps/web/app/Jobs/SyncDiscordRolesJob.php"
    - "apps/web/app/Observers/ClanMembershipObserver.php"
  modified:
    - "apps/web/app/Models/ClanMembership.php"
    - "apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php"
    - "apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php"
decisions:
  - "D-05-06-A: Payload key convention is `discord_user_id` / `discord_role_id` (NOT `user_discord_id` / `role_discord_id` as the plan <interfaces> block specified). The codebase had ALREADY shipped this convention in plan 05-04 (BotApiDiscordEventController::roleChange queries `payload->discord_user_id` JSONB path for echo suppression) and plan 05-02 (DiscordOutboundMessageFactory::roleSync() seeds rows with these keys). Following the plan verbatim would have broken the Pitfall 10 echo-suppression contract — every web-dispatched role sync would no longer match its inbound guildMemberUpdate reflection. The SyncDiscordRolesJob class docblock records this deviation."
  - "D-05-06-B: channel_id is empty string ('') for role_sync rows. The discord_outbound_messages.channel_id column is `text NOT NULL` per plan 05-02 migration (kept that way to preserve match_announce semantics — Filament admin display reads the column). Role-sync rows use the Guilds API (`PUT/DELETE /guilds/{g}/members/{u}/roles/{r}`) and have no channel concept, so passing '' satisfies the constraint without polluting the schema. Bot worker (plan 05-11) keys off message_type='role_sync' and reads the payload, never the channel_id."
  - "D-05-06-C: ClanMembership::booted() registers observer (no parent::booted() call). Matches the Phase 4 GameMatch::booted() precedent (D-04-08-B) — Eloquent's base Model::booted() is a no-op stub, so calling parent::booted() is harmless but adds no value. The plan's <interfaces> block called it but the established codebase pattern omits it; choose consistency with the existing Phase 4 implementation."
  - "D-05-06-D: Both stubs flipped GREEN simultaneously in task 2. The plan presented them as separate test files but their setup (User+Clan factories with discord_id/discord_role_id, ClanMembership factory) and intent (job-side vs dispatch-side) make them sibling concerns. Single commit; full pest baseline movement +16 tests / +24 assertions / -2 incomplete."
  - "D-05-06-E: PHPStan flagged null-safe `?->` on left side of `??` as unnecessary (the chained `??` already handles null). Removed the null-safe operator and kept the `?? ''` fallback. PHPStan L8 is clean; runtime behavior preserved (Eloquent BelongsTo relations with NOT NULL FKs always resolve to non-null in production)."
  - "D-05-06-F: Hard-delete (ClanMembership::delete()) treats as remove dispatch. D-009 prescribes left_at-based history preservation (never hard-delete), so the deleted() hook is defensive — if an admin or seeder does hard-delete a row, we still emit the Discord role removal to keep the guild state consistent. Documented as the precedent for Phase 9 reactivation flows."
metrics:
  duration_seconds: 327
  completed_date: "2026-05-13"
  tasks_total: 2
  tasks_completed: 2
  commits: 2
  files_changed: 5
---

# Phase 5 Plan 06: Wave 4 — SyncDiscordRolesJob + ClanMembershipObserver

The web side of SC-4 is now in place. When a player joins or leaves a clan on
the website (via ClanInviteService, ClanApplicationService, Filament admin, or
the bot reconcile path from plan 05-04), Horizon dispatches a
`SyncDiscordRolesJob` that writes a pending `discord_outbound_messages` row of
`message_type='role_sync'`. Plan 05-11's bot worker (the outbound poller)
consumes these rows and calls Discord's REST `PUT/DELETE /guilds/{g}/members/{u}/roles/{r}`
endpoint. Two-layer retry: Horizon's `$tries=5 + backoff [1,5,15,60,300]`
retries the JOB on handle() crash; the outbound table's `backoff_until` column
retries DELIVERY on Discord 429s/5xxes. The 60s echo-suppression window from
plan 05-04 prevents the inbound guildMemberUpdate event (Discord's reflection
of our PUT) from feeding back through reconcile and producing a ping-pong loop.

## Acceptance Criteria

### Task 1 — `App\Jobs\SyncDiscordRolesJob` (commit `32279ee`)

- [x] `apps/web/app/Jobs/` directory created (PSR-4 auto-resolved via `App\` → `app/`)
- [x] `final class SyncDiscordRolesJob implements ShouldQueue` with `declare(strict_types=1)`
- [x] Use traits: `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`
- [x] `public int $tries = 5` — Horizon canonical retry count
- [x] `public function backoff(): array` returns `[1, 5, 15, 60, 300]` — matches outbound table schedule
- [x] Constructor takes primitive args: `public readonly string $membershipId, public readonly string $action, public readonly ?string $causerUserId = null`
- [x] `handle()` re-hydrates the membership via `ClanMembership::query()->with(['user','clan'])->find($this->membershipId)`
- [x] Returns early without writing in three defensive cases (membership deleted, empty discord_id, empty discord_role_id)
- [x] Writes a single `DiscordOutboundMessage::create` row with `message_type='role_sync'`, `status='pending'`, payload keys `discord_user_id` + `discord_role_id` + `action` + `membership_id` + `clan_id` + `user_id`, `channel_id=''`, `causer_user_id=$this->causerUserId`
- [x] PHPStan L8 clean on the file
- [x] Pint clean (auto-fixed once for `braces_position` cosmetic adjustment)

### Task 2 — Observer + booted() + GREEN tests (commit `a24fd03`)

- [x] `ClanMembershipObserver.php`:
  - `final class` with `declare(strict_types=1)` and namespace `App\Observers`
  - `created(ClanMembership $membership)` — early return when `left_at !== null`, else dispatch action=add
  - `updated(ClanMembership $membership)` — guarded by `wasChanged('left_at')`; null→NOT NULL = remove, NOT NULL→null = add
  - `deleted(ClanMembership $membership)` — dispatch action=remove (defensive — D-009 expects left_at, not delete)
  - `private dispatchSyncIfBindingComplete(ClanMembership $membership, string $action): void` — loadMissing relations, pre-flight skip on empty user.discord_id OR clan.discord_role_id, dispatches `SyncDiscordRolesJob::dispatch(...)` with `Auth::id()` as causer
- [x] `ClanMembership` model amendment:
  - New `use App\Observers\ClanMembershipObserver` import
  - New `protected static function booted(): void` method calling `static::observe(ClanMembershipObserver::class)`
- [x] `SyncDiscordRolesJobTest.php` — Wave 0 stub REPLACED with 8 it() blocks:
  1. writes a pending role_sync outbound row when handle() succeeds
  2. payload contains all 6 keys (discord_user_id, discord_role_id, action, membership_id, clan_id, user_id)
  3. returns early without writing when ClanMembership has been hard-deleted between dispatch and handle
  4. returns early without writing when user.discord_id is empty
  5. returns early without writing when clan.discord_role_id is empty
  6. declares $tries=5 + backoff [1,5,15,60,300]
  7. writes causer_user_id from the constructor parameter
  8. writes null causer_user_id when constructor receives null (CLI/seeder flow)
- [x] `SyncDiscordRolesJobDispatchTest.php` — Wave 0 stub REPLACED with 8 it() blocks:
  1. dispatches with action=add when ClanMembership is created with left_at=null
  2. does NOT dispatch when ClanMembership is created with left_at NOT NULL (historical seed)
  3. dispatches with action=remove when ClanMembership.left_at is updated null → NOT NULL
  4. dispatches with action=add when ClanMembership.left_at reverts NOT NULL → null (re-join)
  5. dispatches with action=remove on ClanMembership::delete (hard delete)
  6. does NOT dispatch when user.discord_id is empty
  7. does NOT dispatch when clan.discord_role_id is empty
  8. does NOT dispatch on a non-left_at update (e.g. role change)
- [x] `'placeholder'` literal removed from both test files
- [x] Full pest run: 585 passed / 1690 assertions (was 569 + 2 incomplete; movement: +16 tests / +24 assertions / -2 incomplete)
- [x] PHPStan L8 clean (full CI scope: `./vendor/bin/phpstan analyse` → No errors)
- [x] Pint clean: 330 files PASS (was 328 in plan 05-05; +2 for Job + Observer)

## SyncDiscordRolesJob Signature

```php
namespace App\Jobs;

final class SyncDiscordRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $membershipId,
        public readonly string $action,           // 'add' | 'remove'
        public readonly ?string $causerUserId = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 15, 60, 300];
    }

    public function handle(): void;
}
```

## ClanMembershipObserver Hook Matrix

| Hook         | Condition                                | Action dispatched | Notes                                           |
|--------------|------------------------------------------|-------------------|-------------------------------------------------|
| `created`    | `left_at === null` (active member)       | `add`             | Normal join path (ClanInviteService etc.)        |
| `created`    | `left_at !== null` (historical seed)     | (none)            | Seeders import history without firing Discord    |
| `updated`    | `wasChanged('left_at')` & now NOT NULL   | `remove`          | Member ended membership                          |
| `updated`    | `wasChanged('left_at')` & now `null`     | `add`             | Re-join (rare; supports D-009 reactivation flow) |
| `updated`    | other field changed (role, etc.)         | (none)            | No role-relevant change                          |
| `deleted`    | hard-delete (rare; defensive)            | `remove`          | D-009 expects left_at, not delete — guard rail   |

All dispatches gated by `dispatchSyncIfBindingComplete()` — skipped silently when
`user.discord_id` or `clan.discord_role_id` is empty.

## Canonical role_sync Payload Shape (JSONB contract for plan 05-11)

```jsonc
{
  "discord_user_id": "100000000000000001",   // string snowflake (JS Number overflow guard)
  "discord_role_id": "200000000000000001",
  "action": "add",                            // or "remove"
  "membership_id": "uuid-...",
  "clan_id": "uuid-...",
  "user_id": "uuid-..."
}
```

Plus the wrapping `DiscordOutboundMessage` columns:
- `channel_id = ''` (empty — Guilds API has no channel)
- `message_type = 'role_sync'`
- `status = 'pending'`
- `causer_user_id` = Auth::id() at dispatch time (null for CLI/seeder flows)

## ClanMembership::booted() Amendment

```php
// New import (line 8):
use App\Observers\ClanMembershipObserver;

// Appended after inviter() relation method:
protected static function booted(): void
{
    static::observe(ClanMembershipObserver::class);
}
```

There was no prior `booted()` method on ClanMembership — Phase 2 plan 02-03's
partial unique constraint is enforced at the database level (Postgres
`UNIQUE WHERE left_at IS NULL` partial index), not at the model level. So the
new `booted()` is a clean addition.

## Test Strategy: Queue::fake vs direct handle()

| Test file                             | Strategy                                  | Why                                                      |
|---------------------------------------|-------------------------------------------|----------------------------------------------------------|
| `SyncDiscordRolesJobTest.php`         | Direct `$job->handle()` invocation         | Test the job body — DB writes, defensive returns, payload shape |
| `SyncDiscordRolesJobDispatchTest.php` | `Queue::fake()` + `Queue::assertPushed`    | Test the observer wiring — does the dispatch fire with the right action? |

The job-body tests delete any rows the observer wrote during factory creation
(`DiscordOutboundMessage::query()->delete()` after the factory) so handle()
runs against a clean baseline. The dispatch tests use `Queue::fake()` in
`beforeEach` to swap the queue driver; each test re-`Queue::fake()`s after
the initial create() to clear the add-on-create assertion before triggering
the next transition.

## Two-Layer Retry Architecture

```
[Trigger]                          [Job layer]                                    [Outbound layer]
─────────                          ───────────                                    ────────────────
ClanMembership::create  →   SyncDiscordRolesJob::dispatch              →   (handle() writes pending row)
                            ▲                                              │
                            │ Horizon retries handle() on crash              │
                            │ Schedule: $tries=5, backoff [1,5,15,60,300]   │
                            │ Worst-case total: ~6.5 min                     │
                            │                                                │
ClanMembership::update  →   SyncDiscordRolesJob::dispatch              →   (handle() writes pending row)
                                                                            │
                                                                            ▼
                                                                   discord_outbound_messages
                                                                            │
                                                                            ▼
                                                                Bot worker (plan 05-11)
                                                                claims via Pattern 4
                                                                            │
                                                                            ▼
                                                                Discord PUT/DELETE
                                                                            │
                                                                ┌───────────┼───────────┐
                                                                ▼                       ▼
                                                              204 OK              429/5xx
                                                                │                       │
                                                                ▼                       ▼
                                                          status='sent'      backoff_until=now()+N
                                                                                        │
                                                                                  (retry inner layer)
```

The OUTER layer (Horizon) retries the dispatch+handle pipeline if the JOB
crashes. The INNER layer (outbound table) retries DELIVERY if Discord rebuffs
the request. They're independent — a successful handle() that writes a pending
row counts as a Horizon SUCCESS even if Discord later refuses delivery.

## Echo Suppression Compatibility

The plan 05-04 `BotApiDiscordEventController::roleChange` looks up the matching
outbound row via:

```php
DiscordOutboundMessage::query()
    ->where('message_type', 'role_sync')
    ->where('status', 'sent')
    ->where('updated_at', '>', now()->subSeconds(60))
    ->where('payload->discord_user_id', $data['user_discord_id'])
    ->where('payload->discord_role_id', $data['role_discord_id'])
    ->where('payload->action', $data['action'])
    ->exists();
```

The job writes rows with payload keys `discord_user_id` / `discord_role_id`
matching the JSONB path query. Therefore the echo-suppression test
(`DiscordEventRoleChangeEchoSuppressionTest`) still passes (6 / 6 GREEN —
verified post-merge). If we had followed the plan's <interfaces> block
verbatim (using `user_discord_id` / `role_discord_id`), every web-dispatched
role sync would have been ineligible for echo suppression — the inbound
event would have produced an extra ClanMembership reconciliation row each
cycle, and the system would have entered a feedback loop bounded only by
Discord's rate-limit response. The deviation (D-05-06-A) closes that gap.

## Threat Register Coverage

| Threat ID    | Disposition | Coverage in this plan |
|--------------|-------------|-----------------------|
| T-05-06-01 (Bot writes arbitrary role_sync rows) | mitigate | Architectural — bot has only `bot:write-outbound` ack endpoints, not arbitrary INSERT (plan 05-04 Sanctum scope) |
| T-05-06-02 (Stolen Sanctum marks rows sent w/o delivery) | accept | Filament admin retry path (plan 05-07); audit log captures bot causer |
| T-05-06-03 (Mass create floods Horizon queue) | mitigate | Horizon worker rate-limit + outbound poll limit=20/5s (plan 05-04); queue backpressure |
| T-05-06-04 (Observer double-fire) | mitigate | `wasChanged('left_at')` gate on updated(); Discord PUT is idempotent (204 either way) |
| T-05-06-05 (Dispatch without causer attribution) | accept | causerUserId is nullable; LogsActivity captures the event regardless |
| T-05-06-06 (Horizon retries after partial write) | mitigate | handle() is idempotent-safe — duplicate role_sync rows are harmless (Discord PUT idempotent) |
| T-05-06-07 (Empty discord_id User row) | mitigate | Double-check: observer pre-flight + job defensive guard both skip |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Payload key naming consistency (D-05-06-A)**

- **Found during:** Task 1 setup, while reading `BotApiDiscordEventController` and `DiscordOutboundMessageFactory`
- **Issue:** The plan's `<interfaces>` block prescribed payload keys `user_discord_id` and `role_discord_id`. The existing code (shipped in plans 05-02 and 05-04) uses `discord_user_id` and `discord_role_id`. Following the plan verbatim would have broken the Pitfall 10 echo-suppression contract — every web-dispatched role sync would have failed to match its own reflection (inbound guildMemberUpdate from Discord) within the 60s window, producing a reconcile-loop.
- **Fix:** Used the codebase convention (`discord_user_id` / `discord_role_id`) in both the job payload and the test assertions. Documented the deviation in the SyncDiscordRolesJob class docblock.
- **Files affected:** `apps/web/app/Jobs/SyncDiscordRolesJob.php` (payload keys + docblock); both test files (assertions match)
- **Commit:** `32279ee` (Task 1) + `a24fd03` (Task 2)

**2. [Rule 3 — Blocking] PHPStan null-safe / null-coalesce conflict**

- **Found during:** Task 2 first phpstan run on ClanMembershipObserver
- **Issue:** Wrote the binding-incomplete guard as `$membership->user?->discord_id ?? ''`. PHPStan L8 flagged the null-safe `?->` as unnecessary — the chained `??` already handles a null left-hand side, and Eloquent's BelongsTo generic infers the relation as non-null (the FK columns are NOT NULL in the Phase 2 schema, so the relation always resolves in production).
- **Fix:** Removed the null-safe operator (kept the `?? ''` fallback). The runtime behavior is preserved — if the discord_id column itself is null/empty, the `?? ''` collapses it to '' and the equality check below catches it.
- **Files affected:** `apps/web/app/Observers/ClanMembershipObserver.php`
- **Commit:** `a24fd03` (Task 2)

**3. [Rule 2 — Defensive add] Eighth test in dispatch matrix — non-left_at update**

- **Found during:** Task 2 — writing the dispatch matrix tests
- **Issue:** The plan's enumeration covered the 7 main transitions but did not explicitly assert that a non-left_at update (e.g., changing role from 'member' to 'officer') does NOT trigger a dispatch. This is the inverse of the wasChanged('left_at') gate and is exactly the kind of regression a future refactor might silently introduce.
- **Fix:** Added an 8th test (`it('does NOT dispatch on a non-left_at update (e.g. role change)')`) — total 8 vs plan minimum of 7.
- **Files affected:** `apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php`
- **Commit:** `a24fd03`

**4. [Rule 2 — Defensive add] Eighth test in handle-body suite — null causerUserId**

- **Found during:** Task 2 — writing the handle() body tests
- **Issue:** The plan covered the causer_user_id propagation from a non-null constructor argument but didn't explicitly test the CLI/seeder flow where causerUserId=null. The acceptance criteria mentioned "writes causer_user_id from the constructor parameter" but not the null case.
- **Fix:** Added an 8th test (`it('writes null causer_user_id when constructor receives null (CLI/seeder flow)')`) — total 8 vs plan minimum of 7.
- **Files affected:** `apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php`
- **Commit:** `a24fd03`

### Documentation Deviations

- **booted() shape (D-05-06-C):** The plan's `<interfaces>` block included `parent::booted()` as the first line of the new method. Phase 4's GameMatch::booted() (D-04-08-B) omits it because Eloquent's base `Model::booted()` is an empty stub. Followed the existing codebase pattern for consistency; the runtime behavior is identical.

### Authentication Gates

None — no external OAuth or third-party credential flows touched in this plan.

## Files Created/Modified

```
5 files changed (2 commits)
```

### Created (2)

```
apps/web/app/Jobs/SyncDiscordRolesJob.php              (145 lines)
apps/web/app/Observers/ClanMembershipObserver.php      (113 lines)
```

### Modified (3)

```
apps/web/app/Models/ClanMembership.php                                (+15 / -0)
apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php                (+138 / -8  — Wave 0 stub → 8 GREEN tests)
apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php        (+145 / -7  — Wave 0 stub → 8 GREEN tests)
```

## Wave 0 Baseline Movement

| Marker                       | Before 05-06            | After 05-06             | Δ                                              |
|------------------------------|-------------------------|-------------------------|------------------------------------------------|
| Pest full suite — incomplete | 2                       | **0**                   | **-2** (both stubs flipped GREEN)              |
| Pest full suite — passed     | 569 (1666 assertions)   | 585 (1690 assertions)   | +16 / +24                                      |
| `./vendor/bin/pint --test`   | PASS 328 files          | PASS 330 files          | +2 (Job + Observer)                            |
| `./vendor/bin/phpstan analyse` | No errors             | No errors               | unchanged                                      |
| ClanMembership booted()      | (none)                  | static::observe(ClanMembershipObserver::class) | +1 line + import |

## Open Question — Hard-delete vs Soft-delete Precedent for Phase 9

The ClanMembership model has no `SoftDeletes` trait (D-009 prescribes left_at
history-preservation). The plan asked us to document the precedent for
Phase 9: should a hard-delete fire role_sync?

**Resolution adopted:** YES — `deleted()` hook fires action=remove. Rationale:

1. D-009 forbids hard-delete IN NORMAL OPERATION but doesn't make it impossible (seeders, admin cleanup, factory rollback tests, etc.).
2. If a row IS hard-deleted, the Discord guild state must still be kept consistent — otherwise the user retains the clan role in Discord with no corresponding ClanMembership in the database, which is exactly the desync state Phase 4 reconciliation (plan 05-04) was designed to detect and fix.
3. Firing the role_sync on hard-delete is the cheaper preventive path: do it now, save the reconcile cycle later.

If Phase 9 introduces a reactivation flow that explicitly tombstones rows by
hard-delete + re-create, the precedent will need a revisit. For v1, hard-delete
= remove dispatch.

## Self-Check: PASSED

- [x] `apps/web/app/Jobs/SyncDiscordRolesJob.php` exists
- [x] `apps/web/app/Observers/ClanMembershipObserver.php` exists
- [x] `apps/web/app/Models/ClanMembership.php` contains `static::observe(ClanMembershipObserver::class)` (1 occurrence)
- [x] `apps/web/app/Jobs/SyncDiscordRolesJob.php` contains `public int $tries = 5` (1 occurrence)
- [x] `apps/web/app/Jobs/SyncDiscordRolesJob.php` contains `[1, 5, 15, 60, 300]` (1 occurrence — backoff method)
- [x] `apps/web/tests/Feature/Bot/SyncDiscordRolesJobTest.php` no longer contains `'placeholder'` (0 occurrences)
- [x] `apps/web/tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php` no longer contains `'placeholder'` (0 occurrences)
- [x] Commit `32279ee` exists in `git log` (Task 1 — SyncDiscordRolesJob)
- [x] Commit `a24fd03` exists in `git log` (Task 2 — Observer + booted + GREEN tests)
- [x] `./vendor/bin/pest tests/Feature/Bot/SyncDiscordRolesJobTest.php tests/Feature/Bot/SyncDiscordRolesJobDispatchTest.php` → 16 passed / 24 assertions
- [x] `./vendor/bin/pest tests/Feature/Bot/DiscordEventRoleChangeEchoSuppressionTest.php` → 6 passed (regressionless)
- [x] `./vendor/bin/pest tests/Feature/Observers/MatchEventSyncTest.php` → 8 passed (Phase 4 regressionless)
- [x] Full pest baseline: 585 passed / 1690 assertions (was 569 + 2 incomplete)
- [x] `./vendor/bin/phpstan analyse` → No errors (CI gate)
- [x] `./vendor/bin/pint --test` → PASS 330 files
