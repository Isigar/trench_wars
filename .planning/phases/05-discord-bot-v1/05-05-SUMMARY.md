---
phase: 05-discord-bot-v1
plan: 05
subsystem: discord-bot
tags: [wave-4, observers, outbound, match-announce, payload, sc-3]
dependency_graph:
  requires: [05-02-complete, phase-04-complete]
  provides:
    - App\Support\DiscordOutboundPayloadBuilder
    - MatchObserver::created (outbound writer)
    - MatchObserver::updated (status-gated outbound writer with prior_sent_message_id)
    - canonical_match_announce_payload_shape
  affects: [05-10, 05-11]
tech_stack:
  added:
    - "App\\Support\\ namespace (PSR-4 already covered by `App\\` → app/ — no composer.json change)"
  patterns:
    - "Two-method static helper (DiscordOutboundPayloadBuilder) — stateless payload-shape ownership; centralised JSONB contract between web (writer) and bot (reader via plan 05-11)"
    - "Observer additive-side-effect extension — created()/updated() hooks added without modifying the Phase 4 saved() Event-sync logic; Eloquent fires saving→creating→[persist]→created→saved for inserts (additive, no ordering coupling)"
    - "wasChanged('status') gate on updated() — T-05-05-04 DoS mitigation (mass title edits do NOT trigger outbound rows)"
    - "prior_sent_message_id propagation — Eloquent JSONB path query (`->where('payload->match_id', $match->id)`) finds the most recent sent announce row so plan 05-11 bot worker can EDIT the original message instead of POST a fresh one (idempotent UX)"
key_files:
  created:
    - "apps/web/app/Support/DiscordOutboundPayloadBuilder.php"
  modified:
    - "apps/web/app/Observers/MatchObserver.php"
    - "apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php"
decisions:
  - "D-05-05-A: cancelled match uses EDIT-not-DELETE pathway — observer.updated() fires match_announce_update with status='cancelled' (the existing prior_sent_message_id propagation pathway). Bot worker (plan 05-11) edits the original message in place (e.g., adds a [CANCELLED] banner). DELETE would drop the audit trail; EDIT preserves the message id + the embed content for screenshot reference. This matches the plan output's recommended resolution to the open question."
  - "D-05-05-B: MatchObserver retains the Phase 4 saved() hook (Event sync) AND adds created() + updated() hooks (outbound writer) — both fire independently per Eloquent's event sequence. No conflict: saved() returns void; created()/updated() return void; each owns its own side-effect with no shared state. The Phase 4 MatchEventSyncTest (8 tests) is regressionless — proven by running the test suite after the extension landed."
  - "D-05-05-C: causer_user_id captured via auth()->id() — null when called from CLI / seeder context (acceptable per T-05-05-03). The model factory's DB::transaction wrap does NOT clear the auth state, so Filament admin edits propagate the user id correctly. Tested explicitly with both actingAs($admin) (returns admin.id) and no-acting-as (returns null)."
  - "D-05-05-D: phpstan paths in phpstan.neon are `app/`, `bootstrap/app.php`, `database/`, `routes/` — `tests/` is NOT covered by the L8 CI gate. The new test file has phpstan-detectable issues (payload['key'] access on string-typed property) but these are out of CI scope. The full `./vendor/bin/phpstan analyse` (the CI gate) reports zero errors. If a future plan adds `tests/` to phpstan paths, the existing pattern (DiscordOutboundMessageModelTest.php already accesses `->payload['embed']['title']`) will need a global remediation — out of scope here."
  - "D-05-05-E: Eager-loading inside the payload builder (loadMissing(['gameMatchType','hostClan','slots.role'])) — the observer fires inside the model's save() context where slots may or may not be present. The eager-load is a no-op when relations are already loaded; cheap when they're not. The factory tests confirm slot_summary aggregates correctly even when slots are created AFTER the match (the updated() pathway re-builds the payload from a fresh hydrate)."
metrics:
  duration_seconds: 383
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 3
---

# Phase 5 Plan 05: Wave 4 — MatchObserver outbound writer + match-announce payload builder

The web side of SC-3 is now in place. Every public match created (or status-transitioned) on a clan with `discord_announce_channel_id` configured produces a pending `discord_outbound_messages` row that plan 05-11's bot poller will pick up via the Pattern 4 atomic claim shipped in plan 05-04. The canonical JSONB payload shape lives in `App\Support\DiscordOutboundPayloadBuilder` — one place owns the contract that plan 05-11's embed renderer reads. Prior `sent_message_id` propagation supports idempotent UX: when the bot has already POSTed an announce for a match, the next status flip routes through an EDIT instead of a fresh POST.

## Acceptance Criteria

### Task 1 — `App\Support\DiscordOutboundPayloadBuilder` (commit `cd79836`)

- [x] `apps/web/app/Support/` directory created (PSR-4 auto-resolved via `App\` → `app/`; no composer.json change)
- [x] `final class DiscordOutboundPayloadBuilder` with `declare(strict_types=1)` and namespace `App\Support`
- [x] Three methods per plan `<interfaces>`:
  - `public static buildMatchAnnounce(GameMatch $match): array` — `match_announce_new` payload kind
  - `public static buildMatchUpdate(GameMatch $match, ?string $priorSentMessageId): array` — `match_announce_update` payload kind with `prior_sent_message_id` propagated
  - `private static buildSlotSummary(GameMatch $match): array` — group-by `game_role_id` with `{role_id, role_key, role_display, total, filled}` per group
- [x] Eager-loads `['gameMatchType','hostClan','slots.role']` to avoid N+1 inside observer save() transaction
- [x] `Carbon|null` PHPStan-friendly null-safe `?->toIso8601String()` for `scheduled_at`
- [x] `getTranslation('title', 'en')` matches Phase 4 idiom (HasTranslations trait)
- [x] PHPStan L8 clean (full CI gate: `./vendor/bin/phpstan analyse` → No errors)
- [x] Pint clean (`./vendor/bin/pint --test` → PASS 328 files; +1 from 327)

### Task 2 — MatchObserver extension (commit `3b4235a`)

- [x] Phase 4 `saved()` hook preserved verbatim (Event sync regressionless)
- [x] Phase 4 `deleted()` hook preserved verbatim (cascade cleanup)
- [x] New `created(GameMatch $match)` hook — calls `writeMatchAnnounceIfEligible($match, isUpdate: false, priorSentMessageId: null)`
- [x] New `updated(GameMatch $match)` hook — guarded by `if (! $match->wasChanged('status')) { return; }` then looks up the most recent sent `match_announce` row via `->where('payload->match_id', $match->id)` (Postgres JSONB path) and propagates its `sent_message_id` forward
- [x] New `private writeMatchAnnounceIfEligible(GameMatch $match, bool $isUpdate, ?string $priorSentMessageId): void` — the single eligibility gate (`is_public` + `discord_announce_channel_id` guards) + payload build + `DiscordOutboundMessage::create`
- [x] `causer_user_id = auth()->id()` — captures Filament admin user; null in CLI flows (D-05-05-C)
- [x] Imports: `DiscordOutboundMessage`, `Event`, `GameMatch` (direct — no `Match` alias per D-04-03-A), `DiscordOutboundPayloadBuilder`
- [x] GameMatch model `booted()` registers observer (Phase 4 D-04-08-B already in place — `grep -c 'static::observe(MatchObserver' apps/web/app/Models/GameMatch.php` returns 1; no model change needed)
- [x] PHPStan L8 clean
- [x] Pint clean
- [x] Phase 4 `MatchEventSyncTest` 8/8 GREEN (regressionless)
- [x] `grep -c 'writeMatchAnnounceIfEligible' apps/web/app/Observers/MatchObserver.php` returns 4 (declaration + 2 callsites + 1 docblock; plan minimum: 3)

### Task 3 — `DiscordOutboundOnMatchCreateTest` GREEN (commit `974d905`)

- [x] Wave 0 RED stub replaced — `'placeholder'` literal removed
- [x] 12 it() blocks / 33 assertions covering all enumerated branches:
  1. ✅ public match + non-null channel → 1 pending `match_announce` row created
  2. ✅ private match (is_public=false) → 0 outbound rows (T-05-05-01)
  3. ✅ null `hostClan.discord_announce_channel_id` → 0 outbound rows
  4. ✅ null `host_clan_id` → 0 outbound rows (defensive add — no `hostClan` to read)
  5. ✅ payload shape covers `match_id`, `status`, `scheduled_at` ISO, `host_clan_id`, `host_clan_name`, `title`, `slot_summary[role_id, total=3, filled=1]`
  6. ✅ status `open → locked` transition fires 2nd outbound row with `kind=match_announce_update, status=locked`
  7. ✅ status `* → cancelled` transition fires 2nd outbound row with `kind=match_announce_update, status=cancelled`
  8. ✅ title-only edit (non-status update) → still 1 row (`wasChanged('status')` gate; T-05-05-04)
  9. ✅ `prior_sent_message_id` propagation when prior row marked sent (uses Eloquent JSONB path query)
 10. ✅ `prior_sent_message_id` is null when no prior sent row exists
 11. ✅ `causer_user_id = $admin->id` under `$this->actingAs($admin)` (Filament-attributed flow)
 12. ✅ `causer_user_id = null` without request context (CLI seeder flow; T-05-05-03 accept)
- [x] Status transitions use `MatchStatusService::transition` (canonical path; Pitfall 12 — never `GameMatch::query()->update` which bypasses observers)
- [x] Full pest baseline: 2 incomplete / 569 passed / 1666 assertions (was 3 / 557 / 1633 — `-1` stub flipped, `+12` tests, `+33` assertions)

## DiscordOutboundPayloadBuilder Method Signatures

```php
namespace App\Support;

final class DiscordOutboundPayloadBuilder
{
    /** @return array<string, mixed> */
    public static function buildMatchAnnounce(GameMatch $match): array;

    /** @return array<string, mixed> */
    public static function buildMatchUpdate(GameMatch $match, ?string $priorSentMessageId): array;

    /** @return array<int, array<string, mixed>> */
    private static function buildSlotSummary(GameMatch $match): array;
}
```

## Canonical Payload Shape (JSONB contract for plan 05-11)

```jsonc
{
  "kind": "match_announce_new",           // or "match_announce_update"
  "match_id": "uuid-...",
  "status": "open|locked|played|cancelled",
  "is_public": true,
  "scheduled_at": "2026-06-15T20:00:00+00:00",  // ISO 8601 UTC
  "host_clan_id": "uuid-...",
  "host_clan_name": "Acme Clan",
  "game_match_type_id": "uuid-...",
  "game_match_type_key": "5v5_ranked",
  "title": "Friday Night Skirmish",              // EN translation
  "slot_summary": [
    {
      "role_id": "uuid-...",
      "role_key": "officer",
      "role_display": "Officer",
      "total": 3,
      "filled": 1
    }
    // ... grouped by game_role_id
  ],
  // ONLY on match_announce_update:
  "prior_sent_message_id": "987654321098765432" // or null when no prior sent row
}
```

## MatchObserver Extension Diff Summary

```
apps/web/app/Observers/MatchObserver.php:
  + 4 new imports (DiscordOutboundMessage, DiscordOutboundPayloadBuilder)
  + 14-line `created()` hook + docblock
  + 27-line `updated()` hook + docblock (wasChanged gate + prior_sent_message_id lookup)
  + 32-line `writeMatchAnnounceIfEligible()` private method + docblock
  + Threat-ref additions in class docblock (T-05-05-01, T-05-05-04, T-05-05-06)
  Phase 4 saved() body: unchanged (Event row sync intact)
  Phase 4 deleted() body: unchanged (cascade cleanup intact)
```

## prior_sent_message_id Propagation Flow

1. Match is created public on a clan with a configured channel → observer.created() writes outbound row 1 with `kind=match_announce_new`, `status=pending`.
2. Bot worker (plan 05-11) claims row 1 via Pattern 4 atomic claim → POSTs embed + RSVP buttons to Discord → marks row 1 `status=sent, sent_message_id='999...'`.
3. Admin transitions match status `open → locked` via Filament → `MatchStatusService::transition` triggers `$match->update(['status' => 'locked'])` → observer.updated() fires with `wasChanged('status') === true`.
4. observer.updated() queries `DiscordOutboundMessage` where `message_type=match_announce`, `status=sent`, `payload->match_id` matches → finds row 1 → reads its `sent_message_id='999...'`.
5. observer.updated() writes outbound row 2 with `kind=match_announce_update`, `prior_sent_message_id='999...'`, `status=pending`.
6. Bot worker claims row 2 → reads `prior_sent_message_id` → calls Discord REST `PATCH /channels/{c}/messages/999...` (EDIT) instead of `POST /channels/{c}/messages` — UX preserves the original message location + thread context.
7. When no prior sent row exists (e.g., bot is offline, row 1 still pending), step 4 returns null → `prior_sent_message_id` is null → bot worker falls back to POST (degraded but functional).

## Threat Register Coverage

| Threat ID    | Disposition | Coverage in this plan |
|--------------|-------------|-----------------------|
| T-05-05-01 (private match leak)    | mitigate | Tested: `is_public=false` → 0 outbound rows; guard at top of `writeMatchAnnounceIfEligible` |
| T-05-05-02 (match title with sensitive content) | accept   | Out-of-band moderation (admin/officer responsibility); LogsActivity on Match captures the title edit |
| T-05-05-03 (causer_user_id absent for CLI flow) | accept   | Tested: no-actingAs flow writes `causer_user_id = null`; LogsActivity on DiscordOutboundMessage captures the event regardless |
| T-05-05-04 (mass UPDATE triggers many outbound rows) | mitigate | Tested: title-only update keeps outbound count at 1 (the `wasChanged('status')` gate is the rate-limit guard) |
| T-05-05-05 (bot edits wrong message via stale id)    | mitigate | Architectural: `sent_message_id` is scoped to bot's own past output; Discord rejects edit of non-existent message gracefully (404) — bot worker (plan 05-11) treats 404 as "POST fresh" fallback |
| T-05-05-06 (observer double-fire → duplicate announce) | mitigate | Architectural: GameMatch::booted() registers observer once (Phase 4 D-04-08-B); Eloquent's static::observe is idempotent on class name |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] PHPStan `scheduled_at` cast inference** 

- **Found during:** Task 1 first `./vendor/bin/phpstan analyse app/Support/...` run (`Cannot call method toIso8601String() on string.`)
- **Issue:** GameMatch has no `@property Carbon $scheduled_at` IDE-helper annotation, so phpstan resolves `$match->scheduled_at` as `string` (the cast definition `'datetime'` is runtime-only). Calling `?->toIso8601String()` on a string fails PHPStan L8.
- **Root cause:** Phase 4 `PublicMatchData::fromModel` already hit the same issue and resolved it with an inline `/** @var Carbon $scheduledAt */` PHPDoc cast.
- **Fix:** Adopted the same pattern — assigned `$match->scheduled_at` to a local `/** @var Carbon|null $scheduledAt */` and called `?->toIso8601String()` on that.
- **Files affected:** `apps/web/app/Support/DiscordOutboundPayloadBuilder.php`
- **Commit:** `cd79836` (squashed in task 1)

**2. [Rule 1 — Bug] PHPStan `list<...>` vs `array<int, ...>` return-type inference**

- **Found during:** Task 1 first phpstan run (`Method ::buildSlotSummary should return list<array<string, mixed>> but returns array<int, array{...}>.`)
- **Issue:** Collection's `->values()->all()` returns `array<int, X>` per phpstan's stubs — not `list<X>`. The semantic of `->values()` is "sequential int keys 0..n-1" but the static type expressed is the broader `array<int, ...>`.
- **Fix:** Changed the docblock return-type from `list<array<string, mixed>>` to `array<int, array<string, mixed>>` — matches phpstan's inferred type without any code change.
- **Files affected:** `apps/web/app/Support/DiscordOutboundPayloadBuilder.php`
- **Commit:** `cd79836` (squashed in task 1)

**3. [Rule 2 — Defensive add] Added `host_clan_id = null` suppression test**

- **Found during:** Task 3 — writing the negative-case branches.
- **Issue:** The plan enumeration covers "is_public=false" and "channel_id=null" but not "host_clan_id=null entirely" (no hostClan relation). `$match->hostClan?->discord_announce_channel_id` returns null via the null-safe operator — same suppression path, but a separate code path worth a regression guard.
- **Fix:** Added a 4th suppression test ("does NOT create an outbound row when host_clan_id is null") — total 12 tests vs plan minimum of 10.
- **Files affected:** `apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php`
- **Commit:** `974d905`

### Documentation Deviations

None. The plan's `<interfaces>` pseudocode was implemented verbatim except for the inline-Carbon PHPDoc adjustment above. The "open question" in `<output>` (cancelled match → DELETE vs EDIT) is resolved as recommended: EDIT pathway via the existing `match_announce_update` flow (see D-05-05-A).

### Authentication Gates

None — no external OAuth or third-party credential flows touched.

## Files Created/Modified

```
3 files changed (3 commits)
```

### Created (1)

```
apps/web/app/Support/DiscordOutboundPayloadBuilder.php   (114 lines)
```

### Modified (2)

```
apps/web/app/Observers/MatchObserver.php                              (+103 / -5)
apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php       (+315 / -7  — Wave 0 stub → 12 GREEN tests)
```

## Wave 0 Baseline Movement

| Marker                       | Before 05-05            | After 05-05             | Δ                                              |
|------------------------------|-------------------------|-------------------------|------------------------------------------------|
| Pest full suite — incomplete | 3                       | **2**                   | **-1** (DiscordOutboundOnMatchCreateTest flipped) |
| Pest full suite — passed     | 557 (1633 assertions)   | 569 (1666 assertions)   | +12 / +33                                      |
| `./vendor/bin/pint --test`   | PASS 327 files          | PASS 328 files          | +1 (`app/Support/DiscordOutboundPayloadBuilder.php`) |
| `./vendor/bin/phpstan analyse` | No errors             | No errors               | unchanged                                      |
| MatchObserver hooks          | 2 (`saved`, `deleted`)  | 4 (`saved`, `created`, `updated`, `deleted`) | +2 hooks (additive — Phase 4 saved + deleted preserved) |

## Open Question Resolved

**Cancelled match → DELETE or EDIT the Discord message?** → **EDIT** (D-05-05-A).

The observer.updated() fires `match_announce_update` with `status='cancelled'` and the prior `sent_message_id`. The bot worker (plan 05-11) routes through Discord's PATCH-message endpoint, replacing the embed body (e.g., with a `~~strikethrough~~ [CANCELLED]` banner) but preserving the original message id + thread context + audit trail. DELETE would drop the screenshot reference and break Discord notification semantics (Discord deletes do not generate a "this message was cancelled" indicator).

## Self-Check: PASSED

- [x] `apps/web/app/Support/DiscordOutboundPayloadBuilder.php` exists
- [x] `apps/web/app/Observers/MatchObserver.php` contains `writeMatchAnnounceIfEligible` (4 occurrences)
- [x] `apps/web/tests/Feature/Bot/DiscordOutboundOnMatchCreateTest.php` no longer contains `'placeholder'`
- [x] Commit `cd79836` exists in `git log` (Task 1 — DiscordOutboundPayloadBuilder)
- [x] Commit `3b4235a` exists in `git log` (Task 2 — MatchObserver extension)
- [x] Commit `974d905` exists in `git log` (Task 3 — DiscordOutboundOnMatchCreateTest GREEN)
- [x] `./vendor/bin/pest --filter='(DiscordOutboundOnMatchCreate|MatchEventSync)'` → 20 passed / 50 assertions (8 Phase 4 + 12 Phase 5)
- [x] Full pest baseline: 2 incomplete / 569 passed / 1666 assertions
- [x] `./vendor/bin/phpstan analyse` → No errors (CI gate scope: app/, bootstrap/, database/, routes/)
- [x] `./vendor/bin/pint --test` → PASS 328 files
- [x] `grep -c 'static::observe(MatchObserver' apps/web/app/Models/GameMatch.php` → 1 (D-04-08-B booted registration intact)
