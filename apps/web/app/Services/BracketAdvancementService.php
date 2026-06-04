<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BracketWinnerNotParticipantException;
use App\Models\Clan;
use App\Models\DiscordOutboundMessage;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Services\Brackets\SwissGenerator;
use App\Support\DiscordOutboundPayloadBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 1 +
 *         06-RESEARCH.md § Pattern 7 (winner propagation through bracket tree).
 *
 * The SC-4 engine: when a MatchResult is recorded for a bracket-linked match,
 * walk the advances_to / loser_advances_to chain one hop, write the winner
 * onto the next bracket's slot a/b (parity rule), trigger standings recalc,
 * enqueue a Discord bracket_result_announce row, and — when no incomplete
 * materialised brackets remain — auto-transition the tournament to 'completed'.
 *
 * Pattern 7 Option A — invocation flows through MatchResultObserver::saved()
 * (plan 06-08 Task 2). The service itself is pure: caller-agnostic, idempotent
 * on null winner / non-tournament matches, transaction-bracketed.
 *
 * Pitfall 6 mitigation — DB::transaction wraps the work, and the FIRST
 * statement acquires Tournament::lockForUpdate on the owning tournament so
 * concurrent MatchResult writes (parallel admin clicks, two referees) serialise
 * on the parent row and standings recalc never races itself.
 *
 * Single-hop walk — advance() only walks ONE bracket forward (T-06-08-02
 * mitigation). The next bracket's resolution is gated by a future MatchResult
 * save, which re-enters this service via the observer; total recursion depth
 * equals the number of rounds in the tournament tree (finite by D-011 format
 * contracts).
 *
 * Circular-DI break (T-06-08-07) — StandingsCalculatorService is resolved via
 * app() lookup, NOT constructor injection. The plan 06-09 real implementation
 * of StandingsCalculatorService may need to read brackets; pulling it through
 * the container at the call site avoids the cycle.
 *
 * Circular-DI break (T-11-03) — EloRatingService and SwissGenerator are also
 * resolved via app() lookup, NOT constructor injection. EloRatingService uses
 * DB::transaction + lockForUpdate on clan rows and may transitively read
 * tournament data; SwissGenerator reads standings which may depend on brackets.
 * Both are resolved at call-site to preserve the same isolation guarantee.
 *
 * Threat refs:
 *   - T-06-08-01 (concurrent advance() race)            — mitigated by DB::transaction + lockForUpdate
 *   - T-06-08-02 (advances_to cycle)                    — mitigated by single-hop walk + DB CHECK no_self_advance (plan 06-02)
 *   - T-06-08-03 (premature completion)                 — mitigated by allBracketsComplete() requiring ≥1 materialised bracket AND zero un-decided
 *   - T-06-08-04 (lost activity trail)                  — accepted; TournamentBracket LogsActivity covers it
 *   - T-06-08-05 (premature grand-final reset)          — mitigated by `$winnerParticipant->id !== $wWinner->id` guard
 *   - T-06-08-06 (wrong parity slot)                    — Pattern 7 odd/even rule, asserted by Pest "slot a vs b" test
 *   - T-06-08-07 (circular DI)                          — mitigated by app() resolution for StandingsCalculatorService
 *   - T-11-03-01 (Elo applied more than once per bracket) — mitigated by rated_at lockForUpdate re-read + stamp inside transaction (CR-02 fix)
 *   - T-11-03-02 (Swiss round generated twice)            — mitigated by nextRoundExists check inside the locked transaction
 *   - T-11-03-03 (auto-advance past final round)          — mitigated by inline currentRound < totalRounds guard (no exception-as-flow)
 *   - T-11-03-04 (bye blocks Swiss auto-complete)         — mitigated by whereNull('winner_participant_id') in allBracketsComplete() guard (CR-01 fix)
 */
final class BracketAdvancementService
{
    public function __construct(
        private readonly TournamentStatusService $statusService,
    ) {}

    /**
     * Propagate a MatchResult through its bracket tree.
     *
     * No-ops:
     *   - $result->winner_clan_id is null (draw — no winner to advance)
     *   - No tournament_bracket has match_id = $result->match_id (non-tournament match)
     *
     * Throws:
     *   - BracketWinnerNotParticipantException when winner_clan_id has no matching
     *     tournament_participants row (DB integrity guard)
     *
     * @throws BracketWinnerNotParticipantException
     */
    public function advance(MatchResult $result): void
    {
        if ($result->winner_clan_id === null) {
            return;  // draw — no advancement
        }

        $bracket = TournamentBracket::query()
            ->where('match_id', $result->match_id)
            ->first();

        if ($bracket === null) {
            return;  // non-tournament match — no advancement
        }

        $bracket->loadMissing(['stage.tournament', 'participantA', 'participantB']);

        $stage = $bracket->stage;
        $tournament = $stage?->tournament;

        if ($tournament === null) {
            // Orphan bracket without a stage/tournament — impossible under the
            // migration FKs, but defensive for PHPStan.
            return;
        }

        /** @var TournamentParticipant|null $winnerParticipant */
        $winnerParticipant = TournamentParticipant::query()
            ->where('tournament_id', $tournament->id)
            ->where('clan_id', $result->winner_clan_id)
            ->first();

        if ($winnerParticipant === null) {
            throw new BracketWinnerNotParticipantException(
                (string) __('tournaments.errors.winner_not_participant')
            );
        }

        // Resolve loser participant — needed for double-elim loser_advances_to.
        $loserParticipant = null;
        if ($bracket->participant_a_id !== null && $bracket->participant_b_id !== null) {
            $loserParticipantId = $bracket->participant_a_id === $winnerParticipant->id
                ? $bracket->participant_b_id
                : $bracket->participant_a_id;
            $loserParticipant = TournamentParticipant::query()->find($loserParticipantId);
        }

        DB::transaction(function () use ($bracket, $winnerParticipant, $loserParticipant, $tournament): void {
            // Pitfall 6 — serialise standings recalc on tournament-row lock.
            Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();

            // 1. Write winner on this bracket.
            $bracket->update(['winner_participant_id' => $winnerParticipant->id]);

            // 2. Propagate winner to next bracket via advances_to_bracket_id.
            if ($bracket->advances_to_bracket_id !== null) {
                /** @var TournamentBracket|null $next */
                $next = TournamentBracket::query()
                    ->whereKey($bracket->advances_to_bracket_id)
                    ->lockForUpdate()
                    ->first();
                if ($next !== null) {
                    $slot = $this->resolveSlot($bracket->position);
                    $next->update(["participant_{$slot}_id" => $winnerParticipant->id]);
                }
            }

            // 3. Double-elim: propagate loser to loser_advances_to_bracket_id.
            if ($bracket->loser_advances_to_bracket_id !== null && $loserParticipant !== null) {
                /** @var TournamentBracket|null $lNext */
                $lNext = TournamentBracket::query()
                    ->whereKey($bracket->loser_advances_to_bracket_id)
                    ->lockForUpdate()
                    ->first();
                if ($lNext !== null) {
                    $lSlot = $this->resolveSlot($bracket->position);
                    $lNext->update(["participant_{$lSlot}_id" => $loserParticipant->id]);
                }
            }

            // 4. Grand final reset (double-elim only).
            $stage = $bracket->stage;
            if ($stage->type === 'grand-final') {
                /** @var array<string, mixed>|null $settings */
                $settings = $stage->settings;
                $resetEnabled = is_array($settings)
                    && array_key_exists('grand_final_reset', $settings)
                    && (bool) $settings['grand_final_reset'];

                if ($resetEnabled && $bracket->round_number === 1) {
                    $wWinner = $this->findStageWinner($tournament, 'winners-bracket');
                    if ($wWinner !== null && $winnerParticipant->id !== $wWinner->id) {
                        // W-winner LOST the GF → lazily create the reset match.
                        // Guard against duplicate reset creation (idempotency).
                        $existingReset = TournamentBracket::query()
                            ->where('tournament_stage_id', $stage->id)
                            ->where('round_number', 2)
                            ->where('position', 1)
                            ->first();

                        if ($existingReset === null) {
                            TournamentBracket::create([
                                'tournament_stage_id' => $stage->id,
                                'round_number' => 2,
                                'position' => 1,
                                'participant_a_id' => $wWinner->id,
                                'participant_b_id' => $winnerParticipant->id,
                            ]);
                        }
                    }
                }
            }

            // 5. Trigger standings recalc (lazy via app() to break circular DI).
            app(StandingsCalculatorService::class)->recalculate($tournament);

            // 5a. TOUR-02 Elo hook — applies exactly once per bracket (rated_at guard).
            //     Skip byes: $loserParticipant is null when only one slot is filled.
            //     Circular-DI break (T-11-03): EloRatingService resolved via app(),
            //     NOT constructor injection (same pattern as StandingsCalculatorService above).
            if ($loserParticipant !== null && $bracket->rated_at === null) {
                $winnerClan = Clan::find($winnerParticipant->clan_id);
                $loserClan = Clan::find($loserParticipant->clan_id);
                if ($winnerClan !== null && $loserClan !== null) {
                    app(EloRatingService::class)->applyResult($winnerClan, $loserClan);
                    // Stamp the idempotency marker inside the same transaction (T-11-03-01).
                    $bracket->update(['rated_at' => now()]);
                }
            }

            // 5b. TOUR-01 Swiss auto-advance — when every bracket in the current
            //     swiss-round stage has a winner, generate the next round automatically.
            //     Only fires for 'swiss-round' stages; skipped for all other stage types.
            //     Circular-DI break (T-11-03): SwissGenerator resolved via app(),
            //     NOT constructor injection.
            if ($stage->type === 'swiss-round') {
                $roundComplete = ! TournamentBracket::query()
                    ->where('tournament_stage_id', $stage->id)
                    ->whereNull('winner_participant_id')
                    ->exists();

                if ($roundComplete) {
                    // Exhaustion guard (mirrors SwissGenerator::generateNextRound lines 151-159).
                    // Prevents calling generateNextRound past the final round and avoids
                    // LogicException-as-control-flow (T-11-03-03).
                    $currentRound = $tournament->stages()
                        ->where('type', 'swiss-round')
                        ->max('ordinal');
                    $activeCount = $tournament->participants()
                        ->where('status', 'active')
                        ->count();
                    $totalRounds = max(1, (int) ceil(log(max($activeCount, 2), 2)));

                    // Existence guard — do not create a duplicate next round (T-11-03-02).
                    $nextRoundExists = $tournament->stages()
                        ->where('type', 'swiss-round')
                        ->where('ordinal', '>', $stage->ordinal)
                        ->exists();

                    if ($currentRound !== null && $currentRound < $totalRounds && ! $nextRoundExists) {
                        app(SwissGenerator::class)->generateNextRound($tournament);
                        // Touch the tournament so the 30s public poll picks up the new round
                        // (TournamentObserver fires tournament_announce_update on updated_at bump).
                        $tournament->touch();
                    }
                }
            }

            // 6. Enqueue Discord bracket_result_announce outbound row.
            DiscordOutboundMessage::create([
                'channel_id' => '',  // resolved at dispatch time by the bot renderer (plan 05-11)
                'message_type' => 'bracket_result_announce',
                'status' => 'pending',
                'payload' => DiscordOutboundPayloadBuilder::buildBracketResult($bracket->fresh() ?? $bracket),
                'causer_user_id' => auth()->id(),
            ]);

            // 7. Tournament completion detection.
            if ($this->allBracketsComplete($tournament)) {
                $this->assignFinalPlacements($tournament);
                $tournament->refresh();
                if ($tournament->status === 'running') {
                    $this->statusService->transition($tournament, 'completed');
                }
            }
        });
    }

    /**
     * Pattern 7 odd/even parity rule — odd from-position → slot 'a';
     * even from-position → slot 'b'. This is the canonical bracket fold:
     * (pos 1, pos 2) → next semifinal; (pos 3, pos 4) → next semifinal; etc.
     */
    private function resolveSlot(int $fromPosition): string
    {
        return $fromPosition % 2 === 1 ? 'a' : 'b';
    }

    /**
     * Find the winner of the highest-round bracket in a given stage type.
     * Used for double-elim grand-final reset detection (locates the
     * winners-bracket final's winner).
     */
    private function findStageWinner(Tournament $tournament, string $stageType): ?TournamentParticipant
    {
        $stage = $tournament->stages()->where('type', $stageType)->first();
        if ($stage === null) {
            return null;
        }

        /** @var TournamentBracket|null $finalBracket */
        $finalBracket = $stage->brackets()
            ->with('winnerParticipant')
            ->orderByDesc('round_number')
            ->orderBy('position')
            ->first();

        return $finalBracket?->winnerParticipant;
    }

    /**
     * True when every materialised bracket (match_id NOT NULL) of every stage
     * has winner_participant_id NOT NULL — AND at least one materialised
     * bracket exists (guards against premature completion of a tournament
     * that never started, T-06-08-03).
     *
     * Swiss premature-completion guard (plan 11-03 BLOCKER-class finding):
     * After auto-advance creates the next swiss-round stage, its brackets have
     * match_id=NULL (not yet materialised). The check above excludes those rows,
     * so allBracketsComplete() would spuriously return true after a NON-final
     * swiss round. Guard: if the tournament format is 'swiss' and any swiss-round
     * stage has brackets with BOTH match_id IS NULL AND winner_participant_id IS NULL,
     * the tournament is NOT complete — those brackets represent a pending round.
     * Byes (match_id=null, winner_participant_id≠null) are intentionally excluded
     * from this guard: they are already resolved and must not block completion (CR-01).
     */
    private function allBracketsComplete(Tournament $tournament): bool
    {
        $stageIds = $tournament->stages()->pluck('id');

        $hasIncomplete = TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->whereNotNull('match_id')
            ->whereNull('winner_participant_id')
            ->exists();

        if ($hasIncomplete) {
            return false;
        }

        $hasAnyMaterialised = TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->whereNotNull('match_id')
            ->exists();

        if (! $hasAnyMaterialised) {
            return false;
        }

        // Swiss premature-completion guard: if any swiss-round stage has brackets
        // with match_id=null, a next round has been auto-generated but not yet
        // materialised — the tournament is still in progress (not terminal).
        if ($tournament->format === 'swiss') {
            $swissStageIds = $tournament->stages()
                ->where('type', 'swiss-round')
                ->pluck('id');

            // Byes have match_id=null AND winner_participant_id≠null (pre-assigned by
            // SwissGenerator). Only a bracket that is BOTH unmaterialised AND undecided
            // represents a pending round. Excluding byes here prevents odd-N Swiss
            // tournaments from getting stuck in the "never complete" state (CR-01).
            $hasUnmaterialisedSwissBrackets = TournamentBracket::query()
                ->whereIn('tournament_stage_id', $swissStageIds)
                ->whereNull('match_id')
                ->whereNull('winner_participant_id')
                ->exists();

            if ($hasUnmaterialisedSwissBrackets) {
                return false;
            }
        }

        return true;
    }

    /**
     * Copy tournament_standings.rank → tournament_participants.placement.
     * v1 implementation — depends on plan 06-09's StandingsCalculatorService
     * having populated standings.rank. When called from within the same
     * advance() transaction (after the recalculate() call above), the stub
     * service no-ops and standings rows stay un-ranked → placement stays
     * null. Plan 06-09 fills in real ranks and this method's writes start
     * landing real placements.
     */
    private function assignFinalPlacements(Tournament $tournament): void
    {
        $tournament->participants()->update(['placement' => null]);

        // Eager-load the participant relation so plan 09-08's
        // Model::shouldBeStrict() does not raise LazyLoadingViolationException
        // when we read `$standing->participant` in the loop below.
        $standings = $tournament->standings()
            ->with('participant')
            ->whereNotNull('rank')
            ->orderBy('rank')
            ->get();

        foreach ($standings as $standing) {
            $participant = $standing->participant;
            if ($participant !== null && $standing->rank !== null) {
                $participant->update(['placement' => $standing->rank]);
            }
        }
    }
}
