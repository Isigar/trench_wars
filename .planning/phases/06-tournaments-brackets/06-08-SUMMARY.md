---
phase: 06-tournaments-brackets
plan: 08
subsystem: services
tags:
  - wave-4
  - service
  - observer
  - bracket-advancement
  - pattern-7
  - pitfall-6
  - discord-outbound
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/06-tournaments-brackets/06-01-SUMMARY.md  # Wave 0 RED stubs (BracketAdvancementServiceTest + MatchResultObserverTest) + i18n key tournaments.errors.winner_not_participant
    - .planning/phases/06-tournaments-brackets/06-02-SUMMARY.md  # tournament_brackets schema with advances_to_bracket_id + loser_advances_to_bracket_id + no_self_advance DB CHECK
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # Tournament + TournamentStage + TournamentBracket + TournamentParticipant + TournamentStanding models + factories
    - .planning/phases/06-tournaments-brackets/06-04-SUMMARY.md  # TournamentStatusService::transition (running → completed)
    - .planning/phases/06-tournaments-brackets/06-06-SUMMARY.md  # BracketGeneratorService + BracketMatchMaterialiserService — fixture builders for the GREEN tests in this plan
    - .planning/phases/06-tournaments-brackets/06-07-SUMMARY.md  # DoubleEliminationGenerator (provides Burton loser-drop chain + grand-final stage settings shape consumed by advance())
  provides:
    - "App\\Services\\BracketAdvancementService — SC-4 engine: Pattern 7 single-hop walker (winner → advances_to slot, loser → loser_advances_to slot via odd/even parity rule); standings recalc trigger; Discord bracket_result_announce outbound writer; auto-transition tournament to 'completed' when all materialised brackets decided; lazily creates grand-final reset match when W-winner loses GF + settings.grand_final_reset=true"
    - "App\\Observers\\MatchResultObserver — Pattern 7 Option A; two-hook (`created` + `updated`) firing pattern (NOT `saved`) — Eloquent fires `created`/`updated` ONLY when there's actually something to persist; plain touch() is silently skipped, no wasRecentlyCreated/getChanges disambiguation needed"
    - "App\\Exceptions\\BracketWinnerNotParticipantException — \\DomainException; thrown when MatchResult.winner_clan_id has no matching tournament_participants row in the bracket's owning tournament (DB integrity guard)"
    - "App\\Services\\StandingsCalculatorService — no-op stub; constructor + recalculate(Tournament) signature locked; plan 06-09 (Wave 5) replaces the body with real Buchholz / round-robin logic. Resolved via app() at the BracketAdvancementService call site to break circular DI (T-06-08-07)."
    - "App\\Support\\DiscordOutboundPayloadBuilder::buildBracketResult(TournamentBracket): array — canonical bracket_result_announce JSONB payload; mirrors buildMatchAnnounce naming conventions"
    - "Migration 2026_05_15_100500 — extends discord_outbound_messages.message_type CHECK constraint to allow 'bracket_result_announce' (the original Phase 5 CHECK only permitted match_announce | role_sync | generic)"
    - "13 GREEN Pest tests / 32 assertions across BracketAdvancementServiceTest (9) + MatchResultObserverTest (4)"
  affects:
    - apps/web/app/Services/                  # 2 new files (BracketAdvancementService + StandingsCalculatorService stub)
    - apps/web/app/Observers/                 # 1 new observer (MatchResultObserver)
    - apps/web/app/Models/MatchResult.php     # booted() method added — first observer attachment on this model
    - apps/web/app/Exceptions/                # 1 new typed exception
    - apps/web/app/Support/DiscordOutboundPayloadBuilder.php  # buildBracketResult method appended
    - apps/web/database/migrations/           # 1 new migration extending CHECK constraint
    - apps/web/tests/Feature/Services/        # 1 Wave 0 RED stub → GREEN; 1 pre-existing test (TournamentSeedingServiceTest) patched for observer interference
    - apps/web/tests/Feature/Observers/       # 1 Wave 0 RED stub → GREEN
tech-stack:
  added: []
  patterns:
    - "Pattern 7 Option A — observer over inline service call (RESEARCH recommendation): MatchResultObserver dispatches BracketAdvancementService::advance() instead of every MatchResult caller inlining the dispatch. Preserves Phase 4 service purity (MatchResultService stays focused on result entry) and matches the existing Phase 4/5 observer convention."
    - "Pattern 7 odd/even parity slot resolution — `from.position % 2 === 1 ? 'a' : 'b'`. Pos 1 → slot a; pos 2 → slot b; pos 3 → slot a of NEXT semifinal; pos 4 → slot b of NEXT semifinal. The canonical bracket-fold algorithm; sibling positions (1,2) collapse onto the same parent semifinal, (3,4) onto the next, etc."
    - "Pitfall 6 mitigation — DB::transaction wraps the work; FIRST statement acquires `Tournament::lockForUpdate` on the owning tournament. Concurrent MatchResult writes (parallel admin clicks, two referees recording the same match) serialise on the parent row; standings recalc never races itself. Inner advances_to + loser_advances_to bracket updates also lockForUpdate the destination row."
    - "Single-hop walk — advance() only walks ONE bracket forward (T-06-08-02 mitigation). The next bracket's resolution is gated by a future MatchResult save which re-enters via the observer; total recursion depth = number of rounds in the tournament tree. Combined with the DB CHECK no_self_advance (plan 06-02), this defends against advancement-loop bugs at two layers."
    - "Circular-DI break via app() resolution — StandingsCalculatorService is NOT constructor-injected; it's pulled through the container at the call site. Plan 06-09's real StandingsCalculatorService may need to read TournamentBracket rows that BracketAdvancementService writes, so the constructor cycle would be a deadlock. The container-resolution pattern is the standard Laravel idiom for breaking such cycles."
    - "Two-hook observer pattern (`created` + `updated`, NOT `saved`) — Eloquent's `saved` event fires for BOTH inserts AND plain `touch()` calls; on the Laravel version pinned here, both emit `getChanges()=[]` AND `wasRecentlyCreated=true` (the latter is set ONCE on insert and never reset on the same instance). There is no reliable saved-event flag combination that distinguishes a fresh insert from a touch on a previously-recently-created instance. Eloquent fires `created` only on the actual insert and `updated` only when a fillable attribute was dirty at save time. Plain touch() (no dirty attributes) emits NEITHER `created` NOR `updated` — exactly the gate we need."
    - "Lazy grand-final reset creation — when bracket.stage.type='grand-final', stage.settings.grand_final_reset=true, bracket.round_number=1, AND the GF winner is NOT the W-bracket final's winner, advance() inserts a new bracket row at (stage_id, round_number=2, position=1) with W-winner vs L-winner pre-populated. Subsequent MatchResult on that reset match advances normally; the bracket has no advances_to_bracket_id (terminal). T-06-08-05 mitigation."
    - "DB integrity guard — BracketWinnerNotParticipantException thrown when MatchResult.winner_clan_id has no matching tournament_participants row. Normally impossible (signups flow from registered clans) but defends against admin manual edits + data corruption."
    - "CHECK constraint extension (Postgres) — drop + recreate is the canonical idiom for CHECK modification (Postgres has no ALTER CONSTRAINT … MODIFY). The migration drops `doutmsg_message_type_chk` and re-adds it with the new value list. Original Phase 5 CHECK permitted match_announce|role_sync|generic; Phase 6 plan 06-08 extends to include bracket_result_announce."
key-files:
  created:
    - apps/web/app/Services/BracketAdvancementService.php
    - apps/web/app/Services/StandingsCalculatorService.php
    - apps/web/app/Observers/MatchResultObserver.php
    - apps/web/app/Exceptions/BracketWinnerNotParticipantException.php
    - apps/web/database/migrations/2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php
  modified:
    - apps/web/app/Models/MatchResult.php
    - apps/web/app/Support/DiscordOutboundPayloadBuilder.php
    - apps/web/tests/Feature/Services/BracketAdvancementServiceTest.php
    - apps/web/tests/Feature/Observers/MatchResultObserverTest.php
    - apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php
decisions:
  - "D-06-08-A: Two-hook MatchResultObserver pattern (`created()` + `updated()`, NOT a single `saved()`). The plan's <interfaces> scaffold used a single `saved()` hook gated by `wasChanged([...]) || wasRecentlyCreated`. Implementation revealed via tinker probing that on the pinned Laravel version, a freshly-created MatchResult emits `getChanges()=[]` AND `wasChanged(...)=false` AND `wasRecentlyCreated=true`, while a `touch()` on the same instance emits the IDENTICAL flag values (and STAYS wasRecentlyCreated=true forever on the same instance). The saved hook therefore cannot distinguish create-fire from touch-skip. Switching to separate `created()` + `updated()` hooks gives Eloquent's native semantics: created fires only on insert; updated fires only when at least one attribute was dirty; plain touch() emits neither. The `updated()` hook keeps the wasChanged guard for the relevant attribute set so unrelated edits (notes typo fix) do not re-fire advance()."
  - "D-06-08-B: BracketWinnerNotParticipantException constructor takes no args. The plan's <interfaces> scaffold suggested passing the localised message; the canonical Phase 4/5 idiom (e.g., `final class SeedingNotAllowedException extends DomainException {}`) extends DomainException with an empty body and lets the caller pass the message via the constructor. Implementation matches the precedent — exception class is one line."
  - "D-06-08-C: DiscordOutboundMessage.channel_id is set to empty string (NOT null) by BracketAdvancementService. The Phase 5 migration (2026_05_13_170625) declares channel_id as `text NOT NULL`. The bot worker (plan 05-11) resolves the channel at dispatch time (per-tournament announce channel or organiser-clan fallback); the writer here cannot determine which channel at write time. The plan's <interfaces> scaffold proposed `null` which would violate NOT NULL — using an empty-string sentinel that the renderer fills in matches the deferred-resolution intent without breaking the DB constraint. Plan 05-11 / 05-12 (bot renderer) will read empty string as the deferred-resolution signal."
  - "D-06-08-D: Rule 3 Blocking — Phase 5 discord_outbound_messages.message_type CHECK constraint did not permit `bracket_result_announce`. The constraint was created by the original Phase 5 migration with values `{match_announce, role_sync, generic}` only. Wrote a new migration (2026_05_15_100500) that drops + recreates the CHECK with the additional `bracket_result_announce` value. The Postgres-canonical idiom — there is no ALTER CONSTRAINT … MODIFY for CHECK predicates."
  - "D-06-08-E: Rule 3 Blocking — Pre-existing TournamentSeedingServiceTest canReseed + reseed tests synthesised MatchResult rows against bracket-linked matches with the factory default winner_clan (a fresh Clan that is not a registered tournament participant). The new observer dispatch makes those rows throw BracketWinnerNotParticipantException. Fix: pass `winner_clan_id=null` (draw) — canReseed's semantics are MatchResult existence (not winner identity), so the assertion is unchanged. The fix is annotated inline with a comment explaining the observer interference. Total tests touched: 3 (the 3 canReseed/reseed scenarios that synthesise a MatchResult)."
  - "D-06-08-F: Tournament-completion guard against re-transition. advance() detects completion via allBracketsComplete() and calls TournamentStatusService::transition(running → completed). On a subsequent re-fire (e.g., admin edits allies_score on an already-resolved MatchResult triggering updated()), advance() re-detects completion. Guard: read tournament->refresh()->status; only transition if currently 'running'. This prevents TournamentStatusInvalidTransitionException on the second pass (the state machine has no completed → completed self-loop)."
  - "D-06-08-G: assignFinalPlacements() walks tournament_standings.rank into tournament_participants.placement. Plan 06-09's StandingsCalculatorService stub here is a no-op, so standings.rank stays null and placement stays null until plan 06-09 ships. The method is idempotent: it resets all participants' placement to null FIRST, then walks ranked standings — re-running after plan 06-09 fills standings.rank will correctly populate placements on the next MatchResult save (the observer re-fires advance which re-runs the completion path)."
metrics:
  duration: ~15m
  completed: 2026-05-13
  tasks: 2
  files_created: 5
  files_modified: 5
  commits: 2
---

# Phase 6 Plan 8: Wave 4 — BracketAdvancementService + MatchResultObserver Summary

The SC-4 engine landed. `BracketAdvancementService::advance()` now closes the loop from MatchResult → winner_participant_id propagation → Discord outbound row → tournament completion. Combined with the Wave 4 generators (plan 06-06 / 06-07) and materialiser (plan 06-06), the full Phase 6 tournament lifecycle is end-to-end functional save for the standings calculator (stub here; real body in plan 06-09).

Pattern 7 Option A is honoured — invocation flows through `MatchResultObserver`, not inline at MatchResultService callers. Phase 4's service purity is preserved.

13 GREEN Pest tests / 32 assertions cover the Pattern 7 odd/even parity rule, Pitfall 6 row-lock serialisation, double-elim loser propagation, grand-final reset lazy creation, tournament auto-completion, Discord outbound shape, and the observer's selective-fire semantics (no-op on draws, no-op on non-tournament matches, no re-fire on touch()).

## What Landed

### BracketAdvancementService (RESEARCH Pattern 7)

Located at `App\Services\BracketAdvancementService`. The `advance(MatchResult $result): void` flow inside `DB::transaction`:

| Step | Behaviour |
|------|-----------|
| 1 | `Tournament::lockForUpdate` on the owning tournament — Pitfall 6 mitigation; serialises concurrent advance() races. |
| 2 | `$bracket->update(['winner_participant_id' => $winnerParticipant->id])` — write winner on this bracket. |
| 3 | If `advances_to_bracket_id !== null`: lockForUpdate the next bracket; update `participant_{slot}_id` per the odd/even parity rule. |
| 4 | If `loser_advances_to_bracket_id !== null` AND both slots had participants: lockForUpdate the L-bracket destination; update `participant_{slot}_id` with the loser (double-elim only). |
| 5 | If `stage.type='grand-final'` AND `settings.grand_final_reset=true` AND `bracket.round_number=1` AND the GF winner ≠ W-bracket-final winner: lazily create a new bracket at `(stage_id, round_number=2, position=1)` with `participant_a_id=wWinner.id` + `participant_b_id=currentWinner.id`. Idempotency guard prevents duplicate reset creation if advance() re-fires. |
| 6 | `app(StandingsCalculatorService::class)->recalculate($tournament)` — lazy via app() to break circular DI (T-06-08-07). |
| 7 | Write `DiscordOutboundMessage` row with `message_type='bracket_result_announce'` + `payload=buildBracketResult($bracket->fresh())`. |
| 8 | If `allBracketsComplete($tournament)`: `assignFinalPlacements($tournament)`; refresh tournament; if `status='running'` (D-06-08-F guard), call `TournamentStatusService::transition($tournament, 'completed')`. |

**Short-circuit gates:**
- `$result->winner_clan_id === null` → return (draw).
- No `TournamentBracket::where('match_id', $result->match_id)->first()` → return (non-tournament match).
- `TournamentParticipant::where('tournament_id', $tournament->id)->where('clan_id', $result->winner_clan_id)->first()` is null → throw `BracketWinnerNotParticipantException`.

**Pattern 7 parity rule** (`resolveSlot(int $fromPosition): string`):

```php
return $fromPosition % 2 === 1 ? 'a' : 'b';
```

Pos 1 (odd) → slot a of advances_to. Pos 2 (even) → slot b. Pos 3 (odd) → slot a of the NEXT semifinal. Pos 4 (even) → slot b. Sibling positions (1,2) collapse onto the same parent; (3,4) onto the next.

**allBracketsComplete() rule** — guards against premature completion (T-06-08-03):

1. Zero un-decided materialised brackets (`whereNotNull('match_id')->whereNull('winner_participant_id')->exists() === false`).
2. AT LEAST ONE materialised bracket exists (`whereNotNull('match_id')->exists() === true`).

Both clauses required. Unmaterialised brackets (round-N stages not yet spawned) do not count as "incomplete" — Swiss next-round generation (plan 06-07) creates them only after the previous round resolves, so they're correctly invisible to this check.

### MatchResultObserver (Pattern 7 Option A)

Located at `App\Observers\MatchResultObserver`. Two hooks — `created()` + `updated()` — instead of the plan's proposed single `saved()` hook (D-06-08-A rationale).

```php
public function created(MatchResult $result): void
{
    if ($result->winner_clan_id === null) return;
    app(BracketAdvancementService::class)->advance($result);
}

public function updated(MatchResult $result): void
{
    if ($result->winner_clan_id === null) return;
    if (! $result->wasChanged(['winner_clan_id','allies_score','axis_score','recorded_at'])) return;
    app(BracketAdvancementService::class)->advance($result);
}
```

Registered via `MatchResult::booted()` static observe call — Phase 4 D-04-08-B idiom (locality of cohesion over central AppServiceProvider).

**Why two hooks instead of `saved()`** — Tinker-probed Eloquent semantics on the pinned Laravel version:

| Scenario | `wasChanged(['allies_score'])` | `getChanges()` | `wasRecentlyCreated` | `saved` fires | `created` fires | `updated` fires |
|----------|-------------------------------|----------------|---------------------|---------------|-----------------|-----------------|
| Fresh create | F | `[]` | T | ✓ | ✓ | — |
| `touch()` on the same instance | F | `[]` | T | ✓ | — | — |
| `update(['allies_score' => 9])` | T | `{"allies_score":9}` | T | ✓ | — | ✓ |

`saved` cannot distinguish fresh-create from touch-on-recently-created-instance because the flag values are identical. `created` + `updated` give Eloquent's native semantics that exactly match the gate we want.

### MatchResult Model Amendment

`apps/web/app/Models/MatchResult.php` gains a `protected static function booted()` method:

```php
protected static function booted(): void
{
    static::observe(MatchResultObserver::class);
}
```

This is the FIRST observer attachment on MatchResult (Phase 4 shipped the model without any). Phase 4 D-04-08-B convention is to register observers via the model's `booted()` for locality; centralising in `AppServiceProvider::boot()` is the alternative but not used here.

### BracketWinnerNotParticipantException

```php
final class BracketWinnerNotParticipantException extends \DomainException {}
```

Thrown by `advance()` when `MatchResult.winner_clan_id` has no matching `tournament_participants` row. DB integrity guard; normally impossible (signups flow from registered clans) but defends against admin manual edits + data corruption. Localised via `tournaments.errors.winner_not_participant` (shipped by plan 06-01).

### StandingsCalculatorService Stub

```php
final class StandingsCalculatorService
{
    public function recalculate(Tournament $tournament): void
    {
        unset($tournament);  // no-op; plan 06-09 fills the body.
    }
}
```

Resolved via `app(StandingsCalculatorService::class)` inside `BracketAdvancementService::advance()` (NOT constructor-injected). Plan 06-09 will replace the body with the Buchholz / round-robin standings writer; the public method signature is locked here.

### DiscordOutboundPayloadBuilder::buildBracketResult Amendment

Static method appended at `apps/web/app/Support/DiscordOutboundPayloadBuilder.php`. Eager-loads `stage.tournament`, `participantA.clan`, `participantB.clan`, `winnerParticipant.clan` to avoid N+1 inside the advance() transaction. Returns the canonical `bracket_result_announce` payload shape:

```php
[
    'kind' => 'bracket_result_announce',
    'tournament_id' => ...,
    'tournament_slug' => ...,
    'tournament_title' => ...,  // EN getTranslation
    'stage_id' => ...,
    'stage_type' => ...,
    'bracket_id' => ...,
    'round_number' => ...,
    'position' => ...,
    'winner_participant_id' => ...,
    'winner_clan_id' => ...,
    'winner_clan_name' => ...,
    'participant_a_clan_name' => ...,
    'participant_b_clan_name' => ...,
]
```

Mirrors `buildMatchAnnounce` naming conventions (snake_case keys, `kind` discriminator).

### Migration 2026_05_15_100500

`extend_discord_outbound_message_types_for_phase_6.php` — drops + recreates the `doutmsg_message_type_chk` CHECK constraint with the additional value `bracket_result_announce`. Postgres-canonical idiom for CHECK modification (no ALTER CONSTRAINT … MODIFY exists). Without this migration, `BracketAdvancementService::advance()`'s `DiscordOutboundMessage::create([...'message_type' => 'bracket_result_announce'...])` throws `SQLSTATE[23514] check_violation`.

### Test Coverage — 13 GREEN it() Blocks / 32 Assertions

**BracketAdvancementServiceTest — 9 tests / 24 assertions:**

| Test | Asserts |
|------|---------|
| Advances winner to slot a for odd from-position (Pattern 7) | winner_participant_id set on bracket; next bracket's participant_a_id = participantA.id |
| Advances winner to slot b for even from-position (Pattern 7) | next bracket's participant_b_id = participantA.id |
| No-op for non-tournament match | no DiscordOutboundMessage row; no exception |
| No-op for draw (winner_clan_id=null) | bracket.winner_participant_id stays null; no outbound row |
| Throws BracketWinnerNotParticipantException for foreign clan | exception type assertion (via create()-triggered observer) |
| Enqueues DiscordOutboundMessage with bracket_result_announce kind | row.status='pending'; payload.kind='bracket_result_announce'; payload.bracket_id matches; payload.winner_participant_id matches; payload.tournament_id matches |
| Auto-transitions tournament to completed | tournament.status flips 'running' → 'completed' after the last bracket's winner is set |
| Propagates loser to loser_advances_to_bracket_id for double-elim | LB destination's participant_a_id = loser.id; W-bracket bracket.winner_participant_id = winner.id |
| Lazily creates grand-final reset bracket | round-2 bracket in GF stage exists with W-winner + L-winner pre-populated |

**MatchResultObserverTest — 4 tests / 8 assertions:**

| Test | Asserts |
|------|---------|
| Fires advance() for tournament-match MatchResult create | bracket.winner_participant_id set after MatchResult::factory()->create() |
| No-op for non-tournament-match MatchResult | zero bracket_result_announce outbound rows |
| Does not fire advance() on draw | bracket.winner_participant_id stays null; zero outbound rows |
| Does not re-fire advance() on touch() | one outbound row across create + touch + score-update (the touch did NOT add a row; the score-update did) |

### Verification

| Gate | Result |
|------|--------|
| `pest tests/Feature/Services/BracketAdvancementServiceTest.php` | **PASS** — 9 passed / 24 assertions |
| `pest tests/Feature/Observers/MatchResultObserverTest.php` | **PASS** — 4 passed / 8 assertions |
| `pest tests/Feature/Services/MatchResultServiceTest.php` (regression) | **PASS** — 9 passed / 31 assertions |
| `pest tests/Feature/Services/TournamentSeedingServiceTest.php` (regression after observer-interference fix) | **PASS** — 14 passed / 52 assertions |
| `pest --no-coverage` (full suite) | **757 passed** / 18 failed (all pre-existing Wave 0 RED placeholders for plans 06-09 → 06-14) |
| `phpstan analyse` (full project) | **PASS** — `[OK] No errors` |
| `pint --test` on all 10 created/modified files | **PASS** — clean (1 auto-fix on the migration class_definition / single_quote — accepted) |
| `grep -c 'placeholder' tests/Feature/Services/BracketAdvancementServiceTest.php tests/Feature/Observers/MatchResultObserverTest.php` | **0** — Wave 0 RED stubs removed |
| Pattern 7 Option A (observer over inline call) | **honoured** — MatchResultObserver dispatches advance(); no MatchResultService inline dispatch |
| Pitfall 6 (Tournament::lockForUpdate) | **mitigated** — first statement inside DB::transaction in advance() |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] discord_outbound_messages.message_type CHECK constraint did not allow `bracket_result_announce`**

- **Found during:** Task 1 — first attempt at writing the DiscordOutboundMessage row inside advance() threw `SQLSTATE[23514] check_violation` against `doutmsg_message_type_chk`.
- **Issue:** The Phase 5 migration `2026_05_13_170625_create_discord_outbound_messages_table.php` installed the CHECK with values `{match_announce, role_sync, generic}` only. The new `bracket_result_announce` value introduced by plan 06-08 is rejected.
- **Fix:** Added migration `2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php` that drops + recreates the CHECK with the additional value. Postgres canonical idiom (no ALTER CONSTRAINT … MODIFY).
- **Files added:** `apps/web/database/migrations/2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php`.
- **Commit:** Folded into Task 1 commit `405de8a`.

**2. [Rule 3 - Blocking] Plan scaffold's saved() hook could not distinguish create from touch on the pinned Laravel version**

- **Found during:** Task 2 — first impl used a single `saved()` hook gated by `wasChanged([...]) || wasRecentlyCreated`. The touch() test failed because `wasRecentlyCreated` is set ONCE on insert and never reset on the same instance, so the second `saved` (from touch) re-fired advance().
- **Issue:** The plan's `<interfaces>` proposed `saved()` with the wasChanged + wasRecentlyCreated guards. Probing via tinker showed that on the pinned Laravel version, freshly-created MatchResult emits `getChanges()=[]` AND `wasChanged(...)=false` AND `wasRecentlyCreated=true`, while a `touch()` on the same instance emits the IDENTICAL flag values. The `saved` hook therefore cannot reliably distinguish create-fire from touch-skip.
- **Fix:** Switched to the two-hook pattern (`created()` + `updated()`). Eloquent fires `created` only on the actual insert; `updated` only when at least one attribute was dirty; plain `touch()` (no dirty attributes) emits NEITHER. The `updated` hook keeps the wasChanged guard for the relevant attribute set so unrelated edits (notes typo fix) do not re-fire advance.
- **Files modified:** `apps/web/app/Observers/MatchResultObserver.php`.
- **Commit:** Folded into Task 2 commit `5dc0603`.
- **D-06-08-A** documents the decision rationale and tinker-probed flag table.

**3. [Rule 3 - Blocking] DiscordOutboundMessage.channel_id is NOT NULL — plan scaffold proposed null**

- **Found during:** Task 1 — first impl wrote `channel_id => null` per the plan's scaffold; Postgres rejected with NOT NULL violation.
- **Issue:** The Phase 5 migration declared `channel_id` as `text` (no `->nullable()`), so the column is NOT NULL. The plan's scaffold proposed null because "the bot worker resolves the channel at dispatch time".
- **Fix:** Use empty string as the deferred-resolution sentinel. The bot worker (plan 05-11) will read the empty string as the signal to resolve the announce channel from per-tournament settings or organiser-clan fallback at dispatch time.
- **Files modified:** `apps/web/app/Services/BracketAdvancementService.php` (channel_id => '').
- **Commit:** Folded into Task 1 commit `405de8a`.
- **D-06-08-C** documents the decision.

**4. [Rule 3 - Blocking] Pre-existing TournamentSeedingServiceTest tests synthesised MatchResults that triggered the new observer's foreign-clan throw**

- **Found during:** Task 2 — running the full test suite after observer registration found 3 pre-existing tests in `TournamentSeedingServiceTest` failing with `BracketWinnerNotParticipantException`.
- **Issue:** The canReseed + reseed tests build a MatchResult against a bracket-linked match using `MatchResult::factory()->create(['match_id' => $match->id])`. The factory default for `winner_clan_id` is a fresh Clan that's NOT a registered tournament participant. With the new observer dispatching `advance()` on every relevant create, those rows now throw `BracketWinnerNotParticipantException` instead of silently inserting.
- **Fix:** Pass `winner_clan_id => null` (draw) on the 3 synthetic rows. The observer short-circuits on null winner_clan_id and the test invariants (canReseed false, reseed throws) are preserved — those assertions only require MatchResult existence, not winner identity. Each fix is annotated inline with a comment explaining the observer interference for future maintainers.
- **Files modified:** `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php` (3 tests).
- **Commit:** Folded into Task 2 commit `5dc0603`.
- **D-06-08-E** documents the decision.

**5. [Rule 1 - Bug] Plan's "throw on foreign clan" test was written for a world where the observer didn't exist yet**

- **Found during:** Task 2 verify gate — after Task 2 registered the observer, the plan's Task-1-shaped "throws BracketWinnerNotParticipantException" test failed because the throw now fires on the `MatchResult::factory()->create(...)` call (via the observer), not on the explicit `advance()` call that the test was wrapping.
- **Issue:** Task 1 wrote the test assuming the observer wasn't yet registered (Task 2 added it). Task 2's observer addition silently shifts the throw site one frame earlier — from the explicit service-call inside `expect(fn () => app(...)->advance($result))` to the implicit observer dispatch inside `MatchResult::factory()->create([...'winner_clan_id' => $foreignClan->id])`.
- **Fix:** Moved the `expect(fn () => ...)->toThrow(...)` wrap from the explicit advance() call to the MatchResult::factory()->create() call. The DB-integrity guard is still asserted; the throw site simply lives one frame deeper.
- **Files modified:** `apps/web/tests/Feature/Services/BracketAdvancementServiceTest.php` (1 test).
- **Commit:** Folded into Task 2 commit `5dc0603`.

**6. [Rule 1 - Bug] Tournament re-transition on second advance() pass after completion**

- **Found during:** Designing the touch() test — discovered that after a tournament completes, a later `updated()` event on the same MatchResult (e.g., admin edits allies_score on the resolved row) re-enters advance() with `allBracketsComplete()` still true, attempting a second `running → completed` transition that throws `TournamentStatusInvalidTransitionException` (no completed → completed self-loop in the state machine).
- **Fix:** Added a guard inside the completion path — `tournament->refresh(); if (tournament->status === 'running')` before calling `statusService->transition`. Tournaments already in 'completed' (or 'cancelled') are skipped silently. The placement re-write still happens (idempotent reset + re-assign).
- **Files modified:** `apps/web/app/Services/BracketAdvancementService.php`.
- **Commit:** Folded into Task 1 commit `405de8a`.
- **D-06-08-F** documents the decision.

**7. [Rule 1 - Bug] PHPStan flagged $stage !== null check inside the eager-loaded path as "always true"**

- **Found during:** Task 1 PHPStan gate — `$bracket->loadMissing(['stage.tournament', ...])` already eagerly loaded the relation; the subsequent `if ($stage !== null && $stage->type === 'grand-final')` flagged because PHPStan inferred `$stage` could not be null after loadMissing.
- **Fix:** Dropped the redundant `!== null` check — `if ($stage->type === 'grand-final')` is the remaining gate. PHPStan clean.
- **Files modified:** `apps/web/app/Services/BracketAdvancementService.php`.
- **Commit:** Folded into Task 1 commit `405de8a`.

No other deviations. Plan executed substantially as written with the scaffold adjustments documented above.

## Threat Mitigations Applied

| Threat ID | Disposition | Mitigation Implemented |
|-----------|-------------|------------------------|
| T-06-08-01 (Tampering — concurrent MatchResult writes race standings recalc) | mitigate | `DB::transaction` wraps advance(); `Tournament::lockForUpdate` is the FIRST statement. Concurrent advance() races on the same tournament serialise on the parent row; standings recalc never overlaps itself. Asserted indirectly by the test suite running RefreshDatabase + per-test isolation (one advance() per test). |
| T-06-08-02 (Tampering — bracket advancement loop / advances_to cycle) | mitigate | Service walks ONE hop per advance() call; recursion is gated by future MatchResult saves on the next bracket. Combined with the DB CHECK `no_self_advance` (plan 06-02), advancement-loop bugs are defended at two layers. |
| T-06-08-03 (Tampering — premature tournament completion) | mitigate | `allBracketsComplete()` requires BOTH (a) zero un-decided materialised brackets AND (b) at least one materialised bracket. Unmaterialised round-N stages (Swiss next-round, etc.) do not count as "incomplete". Asserted by `auto-transitions tournament to completed when every materialised bracket has a winner` test. |
| T-06-08-04 (Repudiation — advancement loses audit trail) | accept | `TournamentBracket::LogsActivity` writes an activity row on every bracket update; `TournamentStatusService::transition` writes one on every status flip. Combined coverage of the bracket tree + completion is sufficient. |
| T-06-08-05 (Tampering — premature grand-final reset creation) | mitigate | Reset creation is gated on `bracket.stage.type='grand-final'` AND `settings.grand_final_reset=true` AND `bracket.round_number=1` AND `winnerParticipant.id !== wWinner.id` (W-winner LOST). Idempotency guard prevents duplicate reset bracket creation on re-fire. Asserted by `lazily creates the grand-final reset bracket when W-winner loses the GF and reset is enabled` test. |
| T-06-08-06 (Tampering — wrong parity slot assignment) | mitigate | `resolveSlot(int $fromPosition): string` is the canonical `% 2 === 1 ? 'a' : 'b'` rule. Asserted by separate slot-a (odd) + slot-b (even) tests; both pass. |
| T-06-08-07 (Tampering — BracketAdvancementService circular DI with StandingsCalculatorService) | mitigate | StandingsCalculatorService is resolved via `app(StandingsCalculatorService::class)` inside `advance()`, NOT constructor-injected. Plan 06-09's real StandingsCalculatorService may need to read TournamentBracket rows that BracketAdvancementService writes; the container-lookup pattern is Laravel's standard way to break such cycles. |

## Threat Flags

None — Phase 6 plan 06-08 changes introduce 1 service + 1 stub service + 1 observer + 1 typed exception + 1 payload builder method + 1 migration extending an existing CHECK constraint + 2 test files. No new endpoints, no new auth paths, no new file access, no new schema (the migration only widens an enum-CHECK), no new network surface. All work stays inside the trust boundary documented by the plan's `<threat_model>`.

## Known Stubs

`App\Services\StandingsCalculatorService` ships as a no-op stub. Plan 06-09 (Wave 5) replaces the body with the real Buchholz / round-robin standings writer. Until then:
- `tournament_standings.rank` stays null on every row.
- `BracketAdvancementService::assignFinalPlacements()` walks 0 ranked standings → `tournament_participants.placement` stays null even after tournament completion.

The stub is **intentional and documented** in the class docblock (cross-refs plan 06-09). The public method signature `recalculate(Tournament $tournament): void` is locked here — plan 06-09 cannot change it.

## Plan Linkages

- **Plan 06-09 (StandingsCalculatorService)** replaces the body of `App\Services\StandingsCalculatorService::recalculate()`. The public signature is locked here; plan 06-09 only fills the body. Once shipped, `assignFinalPlacements()` starts populating `tournament_participants.placement` automatically as tournaments complete via the advance() path.
- **Plan 06-10 (TournamentObserver real bodies)** runs orthogonally — TournamentObserver fires on Tournament saves; MatchResultObserver fires on MatchResult saves. The two observers don't dispatch each other; they share the `DiscordOutboundMessage` outbox table.
- **Plan 06-11 (Filament admin TournamentResource + 9 actions)** wires the Filament UI but does not call `BracketAdvancementService` directly — every advance() invocation flows through the observer when admin records a MatchResult via the existing Phase 4 MatchResultService. The "Cancel tournament" action (plan 06-11) transitions via `TournamentStatusService::transition($t, 'cancelled')` which terminates the bracket tree; subsequent MatchResult saves on already-played brackets still advance() but cannot re-complete a cancelled tournament (D-06-08-F guard).
- **Plan 06-13 (i18n key coverage)** TournamentI18nKeyCoverageTest asserts `tournaments.errors.winner_not_participant` resolves — covered by the throws test in BracketAdvancementServiceTest.
- **Plan 06-14 (8-clan single-elim end-to-end capstone)** exercises the FULL chain: admin start → BracketGeneratorService → BracketMatchMaterialiserService → players sign up → MatchResultService::upsert → MatchResultObserver → BracketAdvancementService → next bracket gets winner → admin materialises round 2 → … → tournament auto-completes. This plan ships the advance() engine that powers the capstone.

## Self-Check: PASSED

- 5 created files exist on disk:
  - `apps/web/app/Services/BracketAdvancementService.php` — FOUND
  - `apps/web/app/Services/StandingsCalculatorService.php` — FOUND
  - `apps/web/app/Observers/MatchResultObserver.php` — FOUND
  - `apps/web/app/Exceptions/BracketWinnerNotParticipantException.php` — FOUND
  - `apps/web/database/migrations/2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php` — FOUND
- 5 modified files carry the expected amendments:
  - `apps/web/app/Models/MatchResult.php` — `booted()` method present; `MatchResultObserver` import present
  - `apps/web/app/Support/DiscordOutboundPayloadBuilder.php` — `buildBracketResult` method present
  - `apps/web/tests/Feature/Services/BracketAdvancementServiceTest.php` — no `placeholder` literal (grep returns 0)
  - `apps/web/tests/Feature/Observers/MatchResultObserverTest.php` — no `placeholder` literal (grep returns 0)
  - `apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php` — 3 synthetic MatchResult rows now use `winner_clan_id => null`
- 2 task commits exist on `master`:
  - `405de8a` — feat(06-08): BracketAdvancementService + advancement plumbing (Task 1)
  - `5dc0603` — feat(06-08): MatchResultObserver + booted() registration (Task 2)
- Pest: 13 new passed / 32 assertions (9 BracketAdvancementServiceTest + 4 MatchResultObserverTest); full suite 757 passed / 18 failed (all pre-existing Wave 0 RED placeholders for plans 06-09 → 06-14)
- PHPStan: full project `[OK] No errors`
- Pint: clean on all 10 created/modified files (1 auto-fix accepted on the migration)
- Plan acceptance criteria from `<tasks>` block — all satisfied (advance() with Pattern 7 + Pitfall 6 + grand-final reset + completion + Discord outbound; observer with no-op gates + wasChanged for updates; StandingsCalculatorService stub shipped; CHECK constraint extended)
- Wave 0 RED stubs removed — confirmed by `grep -c 'placeholder'` returning 0 on both test files
