---
phase: 06-tournaments-brackets
plan: 05
subsystem: services
tags:
  - wave-3
  - service
  - seeding
  - reseed-gate
  - activity-log
  - exception-class
  - open-question-a4-resolved
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stub for TournamentSeedingServiceTest + i18n skeleton (tournaments.errors.reseed_not_allowed)
    - .planning/phases/06-tournaments-brackets/06-02-SUMMARY.md  # tournament_brackets / tournament_stages / match_results schemas (canReseed subquery target)
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # Tournament model + TournamentParticipant + TournamentStage + TournamentBracket models/factories
    - .planning/phases/06-tournaments-brackets/06-04-SUMMARY.md  # TournamentStatusService + seeded → registering back-transition (consumed by reseed())
    - .planning/phases/04-matches-manual/04-03-SUMMARY.md         # MatchResult model + factory (canReseed subquery target)
    - .planning/phases/04-matches-manual/04-04-SUMMARY.md         # Phase 4 canonical state-machine service idiom (mirrored verbatim)
    - .planning/phases/04-matches-manual/04-06-SUMMARY.md         # Phase 4 lockForUpdate inside DB::transaction pattern (mirrored verbatim)
  provides:
    - "App\\Services\\TournamentSeedingService::seed(Tournament, string, ?User) — 3 strategies (by_rank/random/manual) with lockForUpdate + activity_log emission"
    - "App\\Services\\TournamentSeedingService::reseed(Tournament, string, ?User) — canReseed() gate + dual TournamentStatusService transition (seeded → registering → seeded) + dedicated audit row with previous_seeds + new_seeds maps"
    - "App\\Exceptions\\SeedingNotAllowedException — typed DomainException thrown by reseed() when canReseed() returns false"
    - "App\\Models\\Tournament::canReseed(): bool — A4 LOCKED eligibility gate (status='seeded' AND no MatchResult rows for any bracket-linked match)"
    - "14 GREEN Pest tests / 52 assertions covering 3 strategies × happy paths + 5 canReseed() cases + 3 reseed() paths + 2 activity_log shape checks"
  affects:
    - apps/web/app/Services/        # 1 new service file
    - apps/web/app/Exceptions/      # 1 new exception file
    - apps/web/app/Models/          # Tournament model gains canReseed() method
    - apps/web/tests/Feature/Services/  # 1 RED stub flipped to GREEN
tech-stack:
  added: []
  patterns:
    - "Phase 4 canonical service idiom — final class, constructor-injected dependencies (TournamentStatusService), DB::transaction wrap, lockForUpdate() on the participants subquery, activity()->causedBy()->performedOn()->withProperties()->log() audit emission"
    - "PHP 8 match expression with default arm — strategy dispatch with explicit `default => throw new \\InvalidArgumentException(...)` for runtime safety + PHPStan L8 exhaustivity (since the parameter is `string`, not an enum)"
    - "Dual-transition reseed flow — reseed() routes through TournamentStatusService for both seeded → registering AND registering → seeded transitions, so each step gets its own activity_log row; then emits a separate `Tournament reseeded` row capturing the seed delta"
    - "Clan-id-keyed seed maps — previous_seeds + new_seeds in the reseed audit row are keyed by clan_id (not participant_id) for stable identity across re-seed; the TournamentParticipant rows get their seeds reassigned but the same clan reappears at a potentially different number"
key-files:
  created:
    - apps/web/app/Services/TournamentSeedingService.php
    - apps/web/app/Exceptions/SeedingNotAllowedException.php
  modified:
    - apps/web/app/Models/Tournament.php
    - apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php
decisions:
  - "D-06-05-A: Open Question A4 RESOLVED inline at the strictest reasonable threshold — `Tournament::canReseed()` returns true ONLY when status='seeded' AND no MatchResult rows exist for any bracket-linked match in the tournament. Once a single result is recorded, reseeding would invalidate played work; admin must `cancel` and create a new tournament instead. The check uses a 2-level nested subquery (tournament_stages.tournament_id → tournament_brackets.match_id → match_results) with `->exists()` short-circuit. RESEARCH §Deferred Ideas tracks a Phase 9 polish to add a materialised view if the query surfaces in slow-query logs."
  - "D-06-05-B: by_rank v1 uses `tournament_participants.created_at` desc as a deterministic proxy for skill rank (RESEARCH Assumption A11). The implementation calls `$participants->sortByDesc('created_at')->values()` in the private `orderByRank()` helper. Future Phase 9 polish swaps this for ELO-based ranking (RESEARCH §Deferred Ideas). T-06-05-04 is accepted under this assumption — leaking registration order as `skill` is the cost of shipping seeding in v1; the i18n description copy will surface the caveat in plan 06-11."
  - "D-06-05-C: The match expression dispatch on `$strategy` adds a `default => throw new \\InvalidArgumentException(...)` arm. This was a Rule 2 auto-fix in response to PHPStan L8 `match.unhandled` (PHPStan cannot prove exhaustivity over `string`). The throw doubles as a useful runtime safety net — admin/Filament code that passes an unknown strategy gets a clear error message instead of falling silently through the match."
  - "D-06-05-D: reseed() captures previous_seeds + new_seeds maps KEYED BY clan_id (not participant_id) — clan_id is the stable cross-reseed identity since TournamentParticipant rows survive reseeding (only their `seed` column is rewritten). This guarantees audit log readers can diff `previous_seeds[clanX] vs new_seeds[clanX]` for any clan that was present at both timepoints. Mitigates T-06-05-03 (repudiation — audit trail loses previous seeds)."
metrics:
  duration: ~3m
  completed: 2026-05-13
  tasks: 1
  files_created: 2
  files_modified: 2
  commits: 1
---

# Phase 6 Plan 5: Wave 3 — TournamentSeedingService Summary

The TournamentSeedingService lands implementing the 3 SC-1 / SC-5 seeding strategies (by_rank / random / manual) + a strict reseed() flow gated by `Tournament::canReseed()`. The service is the wire target for plan 06-11's Filament admin actions (`Seed participants` + `Re-seed`). Open Question A4 is RESOLVED inline at the strictest threshold — no MatchResult rows allowed for any bracket-linked match. The Wave 0 RED stub is replaced with 14 GREEN Pest tests / 52 assertions.

## What Landed

### TournamentSeedingService — 3 Strategies

| Strategy   | Behaviour |
|------------|-----------|
| `by_rank`  | Deterministic — sorts the locked `registered` participants by `created_at` desc (newest = seed 1). v1 uses created_at as a proxy for skill rank (RESEARCH Assumption A11; Phase 9 ELO upgrade tracked). |
| `random`   | Faker shuffle (`$participants->shuffle()->values()`) — non-deterministic per run; 5-run probabilistic test asserts at least one of 5 shuffles differs from ascending order (flake budget ≤ 1e-7). |
| `manual`   | No-op on seed values — admin set them via Filament inline edit before calling. Only the status flip to `active` fires. |

All 3 strategies wrap in `DB::transaction` with a `lockForUpdate()` on the participants subquery (T-06-05-02 concurrent-seed mitigation; mirrors Phase 4 plan 04-06 idiom verbatim).

### reseed() Flow (5 Steps Inside One DB::transaction)

1. **canReseed() guard** — throws `SeedingNotAllowedException` with the localised `tournaments.errors.reseed_not_allowed` message if `Tournament::canReseed()` returns false.
2. **Capture previous_seeds** — `clan_id => seed` map (clan-keyed for stable cross-reseed identity).
3. **statusService->transition($t, 'registering')** — seeded → registering back-transition; writes its own `Tournament status: seeded -> registering` activity row (D-04-04-A pattern reused).
4. **Reset participants** — flip status back to `registered` + null out `seed`.
5. **Re-call seed()** — assigns new 1..N seeds per the chosen strategy + flips status back to `active`. Writes its own `Tournament seeded` activity row.
6. **statusService->transition($t, 'seeded')** — registering → seeded re-forward transition; writes its own `Tournament status: registering -> seeded` activity row.
7. **Capture new_seeds + emit `Tournament reseeded` audit row** — dedicated activity log entry with `previous_seeds`, `new_seeds`, and `strategy` properties (T-06-05-03 mitigation).

The dual-transition flow routes through `TournamentStatusService` so both halves get audited individually; a SEPARATE `Tournament reseeded` row captures the seed delta. End-state: tournament.status='seeded' + every participant has a fresh seed in 1..N + 4 activity_log rows emitted in total (status seeded → registering, Tournament seeded, status registering → seeded, Tournament reseeded).

### Tournament::canReseed() — Open Question A4 RESOLVED Inline

```php
public function canReseed(): bool
{
    if ($this->status !== 'seeded') {
        return false;
    }

    $hasResult = MatchResult::query()
        ->whereIn('match_id', function ($q): void {
            $q->from('tournament_brackets')
                ->select('match_id')
                ->whereNotNull('match_id')
                ->whereIn('tournament_stage_id', function ($q2): void {
                    $q2->from('tournament_stages')
                        ->select('id')
                        ->where('tournament_id', $this->id);
                });
        })
        ->exists();

    return ! $hasResult;
}
```

**Subquery shape + performance note:** 2-level nested IN clauses against existing indexes — `tournament_stages.tournament_id` (FK index), `tournament_brackets.tournament_stage_id` (FK index), `tournament_brackets.match_id` (partial UNIQUE WHERE match_id IS NOT NULL → indexed). `->exists()` short-circuits on the first hit. For tournaments with ≤ 64 participants × ~6 rounds = ~63 brackets, O(63 + |MatchResult|) — well under the slow-query threshold (RESEARCH §Performance Budget). Phase 9 polish may add a materialised view if it surfaces in slow-query logs.

**Lifecycle false-positives intentionally rejected:**

| Status         | canReseed() | Why                                              |
|----------------|-------------|--------------------------------------------------|
| `draft`        | false       | Pre-seeding — re-seeding is meaningless         |
| `registering`  | false       | Pre-seeding — re-seeding is meaningless         |
| `seeded`       | depends     | Gated on no-MatchResult subquery (A4 LOCKED)    |
| `running`      | false       | At least one match must be played to reach running |
| `completed`    | false       | Terminal lifecycle slot                          |
| `cancelled`    | false       | Terminal lifecycle slot                          |

### SeedingNotAllowedException

Extends `\DomainException` — matches the Phase 4 `MatchNotOpenException` + Phase 6 `TournamentStatusInvalidTransitionException` precedents verbatim. Thrown by `reseed()` with the localised `tournaments.errors.reseed_not_allowed` message (plan 06-01 shipped the i18n key).

### Test Coverage — 14 it() Blocks / 52 Assertions

| Test | Asserts |
|------|---------|
| `by_rank strategy assigns 1..N seeds in created_at desc order` | newest-created participant gets seed 1; oldest gets seed N (deterministic order) |
| `by_rank flips every registered participant to active status` | participant.status === 'active' for all N |
| `random strategy assigns 1..N seeds and is non-deterministic across runs` | 5-run loop; every run produces a valid 1..N permutation; at least one differs from ascending order (flake budget ≤ 1e-7) |
| `manual strategy preserves admin-set seed values and flips status to active` | seeds untouched; status === 'active' |
| `writes an activity log row on seed() with strategy + participant_count` | activity has subject + description='Tournament seeded' + properties.strategy='by_rank' + properties.participant_count=3 + causer_id |
| `canReseed() returns false when a MatchResult exists for any bracket-linked match (A4 LOCKED)` | builds bracket + MatchResult; asserts canReseed() === false |
| `canReseed() returns true when no MatchResult exists for any bracket-linked match` | builds bracket without MatchResult; asserts canReseed() === true |
| `canReseed() returns false on a completed tournament (terminal lifecycle)` | status='completed' rejects |
| `canReseed() returns false on a cancelled tournament (terminal lifecycle)` | status='cancelled' rejects |
| `canReseed() returns false on a draft tournament (pre-seeding)` | status='draft' rejects |
| `rejects reseed when a MatchResult exists for a bracket-linked match (A4 LOCKED)` | service throws SeedingNotAllowedException |
| `rejects reseed with the localised tournaments.errors.reseed_not_allowed message` | message matches `__('tournaments.errors.reseed_not_allowed')` literal |
| `reseed succeeds when no MatchResult exists; tournament returns to status=seeded` | end status='seeded' + all participants have seeds 1..N + all active |
| `reseed emits a dedicated activity log row with previous_seeds + new_seeds maps` | activity has description='Tournament reseeded' + previous_seeds + new_seeds keyed by clan_id with N entries each |

(Pest reports `Tests: 14 passed (52 assertions)`.)

### Verification

| Gate | Result |
|------|--------|
| `pest tests/Feature/Services/TournamentSeedingServiceTest.php` | PASS — 14 passed / 52 assertions / 2.03s |
| `phpstan analyse app/Services/TournamentSeedingService.php app/Exceptions/SeedingNotAllowedException.php app/Models/Tournament.php` | PASS — `[OK] No errors` |
| Full-project `phpstan analyse` (regression) | PASS — `[OK] No errors` |
| `pint --test` on all 4 changed files | PASS — 4 files clean |
| Regression: `pest tests/Feature/Services/TournamentStatusServiceTest.php tests/Feature/Models/TournamentModelTest.php` | PASS — 39 passed / 71 assertions (no regression from Tournament::canReseed() amendment) |
| `grep -c 'placeholder' tests/Feature/Services/TournamentSeedingServiceTest.php` | 0 — Wave 0 RED stub removed |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] PHP 8 `match` expression lacked exhaustivity arm**

- **Found during:** Task 1 PHPStan verification — L8 reports `match.unhandled — Match expression does not handle remaining value: string` on `match ($strategy) { ... }` because the parameter type is `string`, not an enum.
- **Issue:** A caller passing an unknown strategy (e.g., `'shuffle'` typo) would fall through to PHP's silent match failure, raising a fatal `UnhandledMatchError` only at runtime — and PHPStan L8 flagged it as a hard error.
- **Fix:** Added `default => throw new \InvalidArgumentException("Unknown seeding strategy: {$strategy}. Allowed: by_rank | random | manual.")` arm. This satisfies PHPStan AND doubles as a clear runtime error for admin/Filament callers that pass a typo.
- **Files modified:** `apps/web/app/Services/TournamentSeedingService.php`.
- **Commit:** Folded into Task 1's commit `aec1e83`.
- **Recorded as:** D-06-05-C in this SUMMARY's frontmatter.

No other deviations. Plan executed as written.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-05-01 (Tampering — admin reseeds after results recorded → standings retroactively invalid) | mitigate | `Tournament::canReseed()` guard returns false if ANY MatchResult row exists for ANY bracket-linked match; `reseed()` throws `SeedingNotAllowedException` with localised message. Asserted by 2 it() blocks (`rejects reseed when a MatchResult exists` + `rejects reseed with the localised ... message`). |
| T-06-05-02 (Tampering — concurrent seed() calls assign duplicate seeds) | mitigate | participants query uses `lockForUpdate()` inside `DB::transaction`. Concurrent callers serialize on the row locks — mirrors Phase 4 plan 04-06 D-010 idiom verbatim. (Pcntl_fork concurrency test deferred to plan 06-13 cross-cut audit; the same lock pattern is proven correct by Phase 4's MatchSignupConcurrencyTest.) |
| T-06-05-03 (Repudiation — admin reseeds and the audit trail loses the previous seeds) | mitigate | `reseed()` emits a SEPARATE `Tournament reseeded` activity_log row (in addition to the two status-transition rows) with `previous_seeds` + `new_seeds` keyed by `clan_id` for stable cross-reseed identity. Asserted by `reseed emits a dedicated activity log row with previous_seeds + new_seeds maps`. |
| T-06-05-04 (Information Disclosure — by_rank uses created_at as skill proxy, leaks registration order as "skill") | accept | RESEARCH Assumption A11; v1 placeholder; Phase 9 polish swaps for ELO-based ranking. Surfaced inline in the service docblock + this SUMMARY's D-06-05-B. The i18n description copy in plan 06-11 will mention the caveat ("Seeding by registration order — pending skill-rank engine"). |
| T-06-05-05 (Tampering — seed() called outside `registering` status) | accept | Caller (Filament admin action in plan 06-11) gates visibility on status; service itself does not enforce — single-responsibility separation (status flow is the status service's job). Service-layer status check is a Phase 9 polish if it surfaces as a real bug. |

## Threat Flags

None — Phase 6 plan 06-05 changes introduce a service + 1 exception class + 1 model amendment + a test file, all inside the trust boundary documented by the plan's `<threat_model>`. No new endpoints, no new auth paths, no new file access, no new schema, no new network surface.

## Known Stubs

None. The 1 service + 1 exception + 1 model amendment + 1 test file are fully implemented. The `by_rank` v1 strategy uses `created_at` desc as a documented proxy for skill rank (D-06-05-B; not a stub — the implementation is complete and behaves deterministically; a Phase 9 polish item replaces it with an ELO-based ranker but the v1 behaviour ships fully usable for tournaments). No data wired to a hardcoded empty/placeholder UI component.

## Plan Linkages

- **Plan 06-11 (Filament admin TournamentResource + 9 actions)** will wire `Seed participants` + `Re-seed` actions onto `app(TournamentSeedingService::class)->seed(...)` and `->reseed(...)`. The `?User $causer = null` signature enables omitting the causer arg in those callbacks (auth()->user() fallback).
- **Plan 06-06 (BracketGeneratorService)** is the "start tournament" target — it operates on `status='seeded'` tournaments. Reseeding before plan 06-06's brackets exist works trivially (canReseed subquery returns 0 brackets and 0 results); reseeding after plan 06-06 has materialised brackets BUT before any result is filed still works (canReseed checks for MatchResult, not for brackets).
- **Plan 06-12 (public Show.vue / Index.vue)** consumes the same `tournaments.errors.reseed_not_allowed` lang key for flash-message rendering when admin actions surface the exception's `getMessage()`.
- **Plan 06-13 (i18n key coverage + cross-cut audit)** TournamentI18nKeyCoverageTest asserts `tournaments.errors.reseed_not_allowed` resolves — already covered by `rejects reseed with the localised ... message` here.

## Self-Check: PASSED

- All 2 created files exist on disk:
  - `apps/web/app/Services/TournamentSeedingService.php` — FOUND
  - `apps/web/app/Exceptions/SeedingNotAllowedException.php` — FOUND
- The 2 modified files exist + carry the expected amendments:
  - `apps/web/app/Models/Tournament.php` — `canReseed()` method present (grep `canReseed`)
  - `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php` — no longer contains `placeholder` literal (grep returns 0 hits)
- Task 1 commit exists on `master`: `aec1e83` — feat(06-05): TournamentSeedingService + canReseed gate + GREEN tests
- Pest: 14 passed / 52 assertions; PHPStan: `[OK] No errors` on changed files + full-project regression; Pint: 4 files clean
- Tournament-related regression check: 39 passed / 71 assertions across TournamentStatusServiceTest + TournamentModelTest (no impact from the canReseed() amendment)
- Open Question A4 LOCKED at the strictest threshold (no MatchResult rows for any bracket-linked match)
- Wave 0 RED stub removed — confirmed by `grep -c 'placeholder' tests/Feature/Services/TournamentSeedingServiceTest.php` returning 0
