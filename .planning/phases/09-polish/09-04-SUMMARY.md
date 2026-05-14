---
phase: 09-polish
plan: 04
subsystem: notifications
tags: [wave-3, notifications, dispatcher, cron, observers, pitfall-5, pitfall-12, d-04-03-a-locked, d-014-locked, d-09-04-a-locked]
requires:
  - "09-03 Wave 2 — 5 Notification classes (MatchStartingSoon, MatchCancelled, MatchResultPublished, ClanApplicationDecided, ClanInviteReceived) + DiscordChannel outbox writer + User::enabledNotificationChannels()"
  - "Phase 4 GameMatch model + MatchSlot relations (slots.occupantUser)"
  - "Phase 2 Clan::activeMembers() + ClanMembership.user + ClanApplication + ClanInvite models"
  - "Phase 7 routes/console.php Schedule infrastructure (Pitfall 12 cron pattern)"
provides:
  - "App\\Services\\NotificationDispatcher — sweepUpcoming() scans T-60min/T-15min windows; alreadyDispatched() idempotency guard (Pitfall 5)"
  - "App\\Console\\Commands\\NotificationsDispatchUpcomingCommand — notifications:dispatch-upcoming Artisan command"
  - "App\\Console\\Commands\\NotificationsPruneCommand — notifications:prune Artisan command (90-day retention; Open Question 7 LOCKED)"
  - "Schedule::command('notifications:dispatch-upcoming')->everyMinute()->withoutOverlapping()->onOneServer() — cron registration"
  - "Schedule::command('notifications:prune')->dailyAt('03:30')->onOneServer() — daily prune registration"
  - "MatchObserver::updated() extension — fires MatchCancelled on transition into 'cancelled' from any non-cancelled state"
  - "MatchResultObserver::created() extension — fires MatchResultPublished on first result create (preserves match_result_announce + advance branches)"
  - "App\\Observers\\ClanApplicationObserver — fires ClanApplicationDecided on pending→accepted|declined"
  - "App\\Observers\\ClanInviteObserver — fires ClanInviteReceived on row creation"
affects:
  - "plan 09-05 LeaderboardService — will add Cache::tags('leaderboards')->flush() inside MatchResultObserver::created() alongside the dispatch added here"
  - "plan 09-06 NotificationsBell.vue — reads notifications written by sweepUpcoming + the 4 observer branches"
  - "plan 09-07 BanService/DisputeService/MatchResultService — call the dispatch path from service layer when cancelling matches en bulk"
  - "plan 09-08 strict-mode Eloquent flip — the dispatcher + observers eager-load relations to satisfy preventLazyLoading()"
tech-stack:
  added: []
  patterns:
    - "Pitfall 5 idempotency guard — read-then-write race between cron replicas (Railway D-014). NotificationDispatcher::alreadyDispatched() queries notifications WHERE notifiable + type + data->match_id + data->minutes BEFORE notify(); ->onOneServer() guard in routes/console.php is the multi-replica defence; ->withoutOverlapping() is the single-host slow-tick defence."
    - "Pitfall 12 cron pattern — every Schedule::command() must have both ->withoutOverlapping() (single-host) AND ->onOneServer() (Railway multi-replica D-014). The 2 new entries in routes/console.php mirror ArticlesPublishScheduledCommand (07-07) + SitemapGenerateCommand (07-12) precedents."
    - "Observer + model::booted() registration — D-04-08-B precedent. Every new observer registered via static::observe() in the model's booted() hook (NOT EventServiceProvider's $observers array). Idempotent — Eloquent dedupes by class name across multiple registrations."
    - "Eager-load before iterate idiom — match->loadMissing(['slots.occupantUser','hostClan.activeMembers.user']) before the foreach. Pre-empts plan 09-08's strict-mode flip + keeps N+1 cost flat regardless of recipient set size."
    - "MatchResultObserver::created()-only branching for notification dispatch — the result is 'published' exactly once; updates are score-correction edits and do NOT re-notify. Distinct from the announce branch (which has its own alreadyAnnounced guard) and the bracket-advance branch (which intentionally re-fires on relevant attribute changes)."
key-files:
  created:
    - "apps/web/app/Services/NotificationDispatcher.php — sweepUpcoming() + dispatchWindow() + participantsFor() + alreadyDispatched() (138 lines)"
    - "apps/web/app/Console/Commands/NotificationsDispatchUpcomingCommand.php — Artisan wrapper bound to schedule (41 lines)"
    - "apps/web/app/Console/Commands/NotificationsPruneCommand.php — 90-day prune command (49 lines)"
    - "apps/web/app/Observers/ClanApplicationObserver.php — pending→accepted|declined notify trigger (60 lines)"
    - "apps/web/app/Observers/ClanInviteObserver.php — created() notify trigger (38 lines)"
  modified:
    - "apps/web/routes/console.php — added Schedule entries for notifications:dispatch-upcoming + notifications:prune"
    - "apps/web/app/Observers/MatchObserver.php — added maybeNotifyCancellation() helper + invocation from updated()"
    - "apps/web/app/Observers/MatchResultObserver.php — added notifyResultPublished() helper + invocation from created()"
    - "apps/web/app/Models/ClanApplication.php — register ClanApplicationObserver via booted()"
    - "apps/web/app/Models/ClanInvite.php — register ClanInviteObserver via booted()"
    - "apps/web/tests/Feature/Notifications/NotificationDispatcherTest.php — Wave 0 stub turned GREEN (7 tests / 12 assertions)"
    - "apps/web/tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php — Wave 0 stub turned GREEN (3 tests / 8 assertions)"
decisions:
  - "D-09-04-A — Plan-vs-reality relation drift resolved. Plan + RESEARCH Pattern 2 reference GameMatch::signups and Clan::activeMemberships; on-disk those relations DO NOT exist. The real relations are GameMatch::slots() (HasMany<MatchSlot> with occupant_user_id columns) and Clan::activeMembers() (HasMany<ClanMembership> filtered WHERE left_at IS NULL). The dispatcher + both observers use the real relations and dereference occupant_user_id + user accordingly. The merged participant set is the union of signed-up players + active host-clan members, deduped by user id, with anonymous/empty slots filtered. Locked here so plan 09-06+ author against the same idiom."
  - "D-09-04-B — Match status filter for dispatcher is whereIn('status', ['open','locked']), NOT status='scheduled'. The matches.status enum is draft|open|locked|played|cancelled (CHECK constraint in 2026_05_14_100000_create_matches_table.php). The plan/RESEARCH text referenced 'scheduled' which is the Article enum value, not GameMatch's. The semantic equivalent for 'upcoming bookable match' is `open` (signups still accepted) ∪ `locked` (signups closed, awaiting play). Filtering on those two values is correct — draft/played/cancelled matches MUST NOT trigger T-60/T-15 notifications."
  - "D-09-04-C — MatchObserver cancellation trigger fires on transition INTO 'cancelled' from ANY non-cancelled state, not specifically scheduled→cancelled. Reasoning: the matches state machine routes are draft→open→locked→played|cancelled (plus open→cancelled, locked→cancelled). Each transition into cancelled is an organiser/moderator action that warrants notifying participants of the cancellation regardless of which prior state the match was in. The implementation guards on (status==='cancelled' && getOriginal('status') !== 'cancelled') — cancelled→cancelled (e.g., from a touch()) is a noop."
  - "D-09-04-D — ClanApplicationObserver maps plan text 'approved/rejected' to the actual schema enum 'accepted/declined'. The clan_applications_status_check constraint is `CHECK (status IN ('pending','accepted','declined','cancelled'))`. ClanApplicationDecided Notification class (plan 09-03) already encodes 'accepted' as the 'approved' i18n variant, so the observer's pending→{accepted,declined} guard matches both the schema and the Notification class's behaviour. The 'cancelled' transition (applicant-initiated) is intentionally NOT notified — the applicant IS the actor, no DM/bell required."
  - "D-09-04-E — MatchResultPublished dispatch fires from MatchResultObserver::created() only, NOT updated(). The notification semantics are 'result has been published' — score-correction edits via Filament inline editing do NOT re-notify (would be spammy + the edit audit is already in activity_log). Distinct from the existing match_result_announce branch (which fires on both created+updated and uses its own alreadyAnnounced guard) and the bracket-advance branch (which re-fires when relevant attributes change). Three branches, three semantics, intentional."
  - "D-09-04-F — Plan referenced 'guest clan active members' as a recipient set for MatchResultPublished. The matches table has no away_clan_id / guest_clan_id column (only host_clan_id) — v1 GameMatch is host-clan only. Adding guest-clan recipients would be a schema change and is deferred. Documented in MatchResultObserver::notifyResultPublished() docblock so future maintainers know it's a known-future-extension, not an oversight."
metrics:
  duration_seconds: 573
  duration_human: "~9m 33s"
  completed_at: "2026-05-14T07:51:24Z"
  files_created: 5
  files_modified: 7
  total_files: 12
  service_classes_added: 1
  artisan_commands_added: 2
  schedule_entries_added: 2
  observers_added: 2
  observers_extended: 2
  tests_now_passing: 1163
  tests_now_skipped: 25
  suite_total: 1188
  baseline_passing: 1153
  baseline_skipped: 27
  tests_added_this_plan: 10
  wave_0_stubs_turned_green: 2
  pint_files_passed: 12
  phpstan_errors: 0
  lines_added: 832
  lines_deleted: 16
---

# Phase 9 Plan 04: Wave 3 — NotificationDispatcher + Schedule cron + observers + prune Summary

Operationalised the SC-1 dispatch surface end-to-end at the service + observer layer. NotificationDispatcher::sweepUpcoming() sweeps T-60min and T-15min windows on every cron tick with a (type, data->match_id, data->minutes) idempotency guard (Pitfall 5 LOCKED). Four observers (MatchObserver + MatchResultObserver extended, ClanApplicationObserver + ClanInviteObserver newly authored) fire the four state-transition Notification classes from Phase 9 plan 03 on the correct trigger events. Two Schedule entries (everyMinute + dailyAt('03:30')) wire the dispatcher + 90-day prune (Open Question 7 LOCKED). Two Wave 0 Pest stubs turned GREEN: NotificationDispatcherTest (7 tests) + NotificationDispatcherIdempotencyTest (3 tests).

## What Shipped

### NotificationDispatcher service

```php
// app/Services/NotificationDispatcher.php
final class NotificationDispatcher
{
    public function sweepUpcoming(): void { /* T-60 then T-15 */ }
    private function dispatchWindow(int $minutes): void { /* ±3min slack + iterate */ }
    private function participantsFor(GameMatch $match): Collection { /* slots.occupantUser ∪ hostClan.activeMembers.user */ }
    private function alreadyDispatched(User $user, GameMatch $match, int $minutes): bool { /* Pitfall 5 dedupe */ }
}
```

| Aspect | Behaviour |
|--------|-----------|
| Sweep windows | T-60min and T-15min (two `dispatchWindow()` invocations per `sweepUpcoming()`) |
| Window slack | ±3 minutes — a 1-minute cron tick never misses a target |
| Match status filter | `whereIn('status', ['open', 'locked'])` — bookable matches only |
| Participant set | union of (slots.occupant_user_id non-null) + (hostClan.activeMembers.user) deduped by user id |
| Idempotency key | `(notifiable_type, notifiable_id, type='match.starting_soon', data->match_id, data->minutes)` |
| Eager-loads | `slots.occupantUser` + `hostClan.activeMembers.user` (anticipates plan 09-08 strict mode) |

### Artisan commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `notifications:dispatch-upcoming` | `->everyMinute()->withoutOverlapping()->onOneServer()` | Wraps `NotificationDispatcher::sweepUpcoming()`. Manual invocation is supported for incident-response. |
| `notifications:prune` | `->dailyAt('03:30')->onOneServer()` | Deletes notifications older than 90 days (Open Question 7 LOCKED). Idempotent — emits `Pruned N notifications older than 90 days.` to console. |

### Schedule:list verification

```
$ docker compose exec -T web php artisan schedule:list | grep -E "notifications:"
  *  * * * *  php artisan notifications:dispatch-upcoming  Next Due: 6 seconds from now
  30 3 * * *  php artisan notifications:prune .... Next Due: 19 hours from now
```

Both guards (`->withoutOverlapping()` + `->onOneServer()`) are required on the everyMinute entry per Pitfall 12 — the precedent is `articles:publish-scheduled` (07-07). The daily prune entry uses only `->onOneServer()` (Pitfall 12 explicitly notes ->withoutOverlapping is unnecessary for daily cadence).

### Observer registration map

| Observer | Model::booted() registration | Triggers | Fires |
|----------|------------------------------|----------|-------|
| `MatchObserver` (extended) | `GameMatch::booted()` (Phase 4) | `updated()` when `wasChanged('status') && status === 'cancelled' && original !== 'cancelled'` | `MatchCancelled` to signed-up players + active host-clan members |
| `MatchResultObserver` (extended) | `MatchResult::booted()` (Phase 6) | `created()` (first MatchResult row only) — runs ADDITIVELY after bracket-advance + match_result_announce branches | `MatchResultPublished` to signed-up players + active host-clan members |
| `ClanApplicationObserver` (new) | `ClanApplication::booted()` (this plan) | `updated()` when `wasChanged('status') && original === 'pending' && new ∈ {accepted, declined}` | `ClanApplicationDecided` to applicant only |
| `ClanInviteObserver` (new) | `ClanInvite::booted()` (this plan) | `created()` (every new invite — invites are always created in 'pending' state) | `ClanInviteReceived` to invitee only |

### Pitfall 5 idempotency proof

```php
// File: tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php
it('does not duplicate notifications when sweep runs twice in the same window', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open', 'scheduled_at' => now()->addMinutes(60)]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();
    app(NotificationDispatcher::class)->sweepUpcoming();   // second sweep — must NOT duplicate

    $count = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->where('type', 'match.starting_soon')
        ->whereJsonContains('data->match_id', $match->id)
        ->whereJsonContains('data->minutes', 60)
        ->count();
    expect($count)->toBe(1);     // ← exactly one row, not two
});
```

Test result: **PASS** (1.75s on cold start). Two consecutive sweeps in the same minute produce exactly 1 row per (user, match, minutes) tuple — the second sweep's `alreadyDispatched()` query returns true and the notify is skipped.

Additionally, `it('treats T-60 and T-15 as separate dispatch keys')` proves that the same match flowing through both windows (the cron crosses the T-60 mark at 12:00 and the T-15 mark at 12:45) produces 2 rows with distinct `data->minutes` values (15 and 60) — the dedupe key includes `minutes`, so the two windows do NOT collide.

## Quality Gates

| Gate | Result |
|------|--------|
| `pest --filter="NotificationDispatcherTest"` | **7 passed** / 12 assertions / 0.36s |
| `pest --filter="NotificationDispatcherIdempotencyTest"` | **3 passed** / 8 assertions / 1.92s |
| `pest --filter="MatchObserver\|MatchResultObserver\|ClanApplication\|ClanInvite"` | **37 passed** / 104 assertions / 3.99s (pre-existing tests remained GREEN) |
| `pest --no-coverage` (full suite) | **1163 passed + 25 skipped** (3855 assertions) in 72.54s |
| Baseline delta (passed) | +10 (1153 → 1163) — every new test landed in this plan |
| Baseline delta (skipped) | −2 (27 → 25) — exactly the 2 Wave 0 stubs this plan committed to turning GREEN |
| `pint --test` on 12 touched files | **PASS** (after one auto-fix pass — fully_qualified_strict_types in NotificationDispatcher.php + NotificationDispatcherTest.php) |
| `phpstan analyse` (level 8, full project) | **OK, no errors** |
| `php artisan schedule:list \| grep notifications:` | 2 entries registered (dispatch-upcoming + prune) |

## Wave 0 Stubs → GREEN

```text
NotificationDispatcherTest                                Wave 0 (1 skipped) → 7 passed
NotificationDispatcherIdempotencyTest                     Wave 0 (1 skipped) → 3 passed
                                                                                  ────────
                                                                              10 new GREEN tests
```

Skip-list count check:
- Pre-plan (09-03): 27 skipped.
- Post-plan (09-04): 25 skipped (27 − 2 = 25 ✓).

## Deviations from Plan

### Rule 1 — relation drift (plan vs on-disk reality)

**1. [Rule 1 — Bug] `GameMatch::signups` and `Clan::activeMemberships` relations do not exist on disk**

- **Found during:** Task 1 — writing NotificationDispatcher::dispatchWindow().
- **Issue:** Plan text + RESEARCH Pattern 2 (verbatim code) read `$match->signups->pluck('user')` and `$match->hostClan->activeMemberships->pluck('user')`. The real relations are `GameMatch::slots()` (HasMany<MatchSlot> — MatchSlot owns `occupant_user_id`) and `Clan::activeMembers()` (HasMany<ClanMembership> filtered WHERE left_at IS NULL). Calling `->signups` or `->activeMemberships` on the model returns null and Eloquent throws BadMethodCallException.
- **Fix:** Used the real relations — `slots.occupantUser` and `hostClan.activeMembers.user`. Anonymous/empty slots (occupant_user_id NULL) are filtered out. Locked as **D-09-04-A**.
- **Files modified:** `app/Services/NotificationDispatcher.php`, `app/Observers/MatchObserver.php`, `app/Observers/MatchResultObserver.php`, both test files.
- **Commit:** `0db9134` + `1b447e7`.

**2. [Rule 1 — Bug] `matches.status='scheduled'` does not exist; matches enum is `draft|open|locked|played|cancelled`**

- **Found during:** Task 1 — writing dispatchWindow() status filter.
- **Issue:** Plan + RESEARCH Pattern 2 referenced `->where('status', 'scheduled')`. The `matches_status_check` CHECK constraint enforces `IN ('draft','open','locked','played','cancelled')` — there is no `'scheduled'` value (that's the **Article** enum). Inserting against / filtering by `'scheduled'` on matches would return 0 rows under any cron tick — silently broken.
- **Fix:** Used `->whereIn('status', ['open', 'locked'])` — the semantic equivalent of "upcoming bookable match" (open: signups still accepted; locked: signups closed, awaiting play). Locked as **D-09-04-B**.
- **Files modified:** `app/Services/NotificationDispatcher.php`, `tests/Feature/Notifications/NotificationDispatcherTest.php`.
- **Test impact:** `it('skips matches whose status is not open or locked')` covers draft / cancelled / played and asserts dispatcher does NOT fire for any of them.
- **Commit:** `0db9134`.

**3. [Rule 1 — Bug] MatchObserver cancel trigger uses "any non-cancelled → cancelled", not "scheduled → cancelled"**

- **Found during:** Task 2 — extending MatchObserver::updated().
- **Issue:** Plan text said "fires MatchCancelled on status flip scheduled→cancelled". Same underlying bug — no 'scheduled' value. Multiple legal transitions land in cancelled (draft→cancelled, open→cancelled, locked→cancelled per `MatchStatusService::ALLOWED_TRANSITIONS`). All of them deserve the notification.
- **Fix:** Guard fires on `(status === 'cancelled' && getOriginal('status') !== 'cancelled')`. Locked as **D-09-04-C**.
- **Files modified:** `app/Observers/MatchObserver.php`.
- **Commit:** `1b447e7`.

**4. [Rule 1 — Bug] ClanApplication status enum is `accepted/declined`, not `approved/rejected`**

- **Found during:** Task 2 — writing ClanApplicationObserver guard.
- **Issue:** Plan text said `pending→{approved,rejected}`. The `clan_applications_status_check` CHECK constraint enforces `IN ('pending','accepted','declined','cancelled')`. The ClanApplicationDecided Notification class (plan 09-03) already encodes `'accepted'` as the "approved" i18n variant — it's purely a vocabulary mismatch in the plan text, not in the schema or the notification class.
- **Fix:** Observer guards on `in_array($status, ['accepted','declined'], true)`. Locked as **D-09-04-D**.
- **Files modified:** `app/Observers/ClanApplicationObserver.php`.
- **Commit:** `1b447e7`.

### Rule 2 / Rule 3 / Rule 4 — None

- Rule 2 (auto-add missing critical functionality): N/A — every plan-mandated method shipped; no security/correctness gap surfaced.
- Rule 3 (auto-fix blocking issues): N/A — one Pint auto-fix on NotificationDispatcher.php + NotificationDispatcherTest.php (fully_qualified_strict_types) was a style normalisation, not a blocker. One unused `ClanMembership` use statement removed from NotificationDispatcher.php after Pint passed.
- Rule 4 (architectural decisions): N/A — D-09-04-A..D-09-04-F are Rule 1 alignments with on-disk reality, not architectural changes.

### Plan-design clarifications (NOT deviations, but documented for verifier)

- **D-09-04-E** — `MatchResultPublished` fires from `MatchResultObserver::created()` only, never `updated()`. Plan text said "MatchResultObserver fires MatchResultPublished notification on first MatchResult create" — implementation matches that intent. Score-correction edits via Filament do NOT re-notify (intentional — would be spammy and the edit audit lives in activity_log).
- **D-09-04-F** — Plan referenced "guest clan active members" as a MatchResultPublished recipient set. The matches table has no `away_clan_id` / `guest_clan_id` column — v1 GameMatch is host-clan only. Documented in `MatchResultObserver::notifyResultPublished()` docblock as known-future-extension; verifier may want to track for Phase 10+.

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack (web + postgres + redis healthy throughout). No Discord OAuth / external API / human action required at any point.

## Known Stubs

None. Every code path is fully wired:
- Cron dispatcher writes notifications rows that the Phase 9 plan 09-06 Vue bell list will read.
- Observer dispatches write the same notifications rows + (per Pattern 7) optional discord_outbound_messages rows that the Phase 5 bot worker picks up.
- The dispatch test cases include a non-fake assertion (`it('writes notifications.match.starting_soon rows with the correct data shape')`) that proves the end-to-end write reaches the DB, not just the Notification::fake() facade.

The MatchResultPublished helper noted the "guest clan active members" deferred extension (D-09-04-F) inline in its docblock — that is a documented future-phase TODO, not a stub.

## Threat Flags

None. The plan's `<threat_model>` (T-09-04-01..05) covers every introduced surface:

| Threat | Component | Mitigation status |
|--------|-----------|-------------------|
| T-09-04-01 (DoS — dispatcher mass-fire) | NotificationDispatcher | **PASS** — `whereIn('status', ['open','locked'])` + ±3min window + `alreadyDispatched()` guard; `it('skips matches outside the ±3min boundary')` + `it('skips matches whose status is not open or locked')` + `it('does not duplicate notifications when sweep runs twice')` together cover all three mitigation legs |
| T-09-04-02 (Tampering — multi-host race) | Schedule registration | **PASS** — both `->withoutOverlapping()` and `->onOneServer()` present on the everyMinute entry; `schedule:list` shows the cron registered correctly |
| T-09-04-03 (Information Disclosure — cancel leaks) | MatchObserver::maybeNotifyCancellation() | **PASS** — recipient set is exactly signups + host-clan active members; never broadens. The plan 09-06 bell controller will additionally scope reads by auth()->user() |
| T-09-04-04 (Spoofing — wrong-user notify) | All 4 observers | **PASS** — every observer takes the Eloquent-dispatched model; no user-controlled input crosses the trust boundary |
| T-09-04-05 (Elevation — prune hides audit) | NotificationsPruneCommand | **ACCEPT** — notifications are NOT the audit log (activity_log is, per D-012). 90-day operational hygiene retention. Threat-register accepted disposition; no further mitigation needed |

No new surface beyond the threat register. No threat flags added.

## Self-Check: PASSED

**Files checked (5 created, 7 modified — 12 total):**

```
FOUND: apps/web/app/Services/NotificationDispatcher.php
FOUND: apps/web/app/Console/Commands/NotificationsDispatchUpcomingCommand.php
FOUND: apps/web/app/Console/Commands/NotificationsPruneCommand.php
FOUND: apps/web/app/Observers/ClanApplicationObserver.php
FOUND: apps/web/app/Observers/ClanInviteObserver.php
FOUND: apps/web/routes/console.php (modified — 2 new Schedule entries)
FOUND: apps/web/app/Observers/MatchObserver.php (modified — maybeNotifyCancellation)
FOUND: apps/web/app/Observers/MatchResultObserver.php (modified — notifyResultPublished)
FOUND: apps/web/app/Models/ClanApplication.php (modified — register observer)
FOUND: apps/web/app/Models/ClanInvite.php (modified — register observer)
FOUND: apps/web/tests/Feature/Notifications/NotificationDispatcherTest.php (Wave 0 → GREEN)
FOUND: apps/web/tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php (Wave 0 → GREEN)
```

**Commits verified:**

```
FOUND: 0db9134 feat(09-04): add NotificationDispatcher service + dispatch-upcoming + prune commands
FOUND: 1b447e7 feat(09-04): wire observer-driven notification dispatches (4 observers)
```

**Stub elimination verified:**

```
$ docker compose exec -T web ./vendor/bin/pest --filter="NotificationDispatcherTest|NotificationDispatcherIdempotencyTest" --no-coverage
  Tests: 10 passed (20 assertions) — both Wave 0 stubs turned GREEN
```

**Suite delta:**

```
Pre-plan baseline (09-03):    1153 passed + 27 skipped
Post-plan (09-04):            1163 passed + 25 skipped
                              ────────────  ──────────
                              +10 passed    −2 skipped
```

All 5 created + 7 modified files present on disk; both commits resolve in `git log` (Task 1: 6 files, 585 insertions, 16 deletions; Task 2: 6 files, 247 insertions, 0 deletions = 832/16 total lines).
