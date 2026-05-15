<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\StandingsCalculatorService;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-09-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers StandingsCalculatorService::recalculate() with 4 format strategies:
|   - SingleEliminationStandingsCalculator: rank from bracket position
|     (winner=1, runner-up=2, semi loser=3, QF loser=5)
|   - RoundRobinStandingsCalculator: FIFA 3/1/0 points + h2h tiebreak
|   - SwissStandingsCalculator: 1/0.5/0 points + Buchholz tiebreak
|   - DoubleEliminationStandingsCalculator: GF winner=1, GF loser=2,
|     L-final loser=3
|
| Wipe-and-recompute idempotency, withdrawn-participant retention (A5 LOCKED),
| and admin override of round-robin points scheme are also asserted.
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch.
*/

/**
 * Build a Tournament with a default GameMatchType backed by a single
 * RoleLimit (capacity=2) so the materialiser produces 2 MatchSlot rows per
 * bracket. Mirrors the BracketAdvancementServiceTest helper.
 */
function makeStandingsTournament(string $format, string $status = 'running'): Tournament
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]);

    return Tournament::factory()
        ->ofFormat($format)
        ->inStatus($status)
        ->for($game)
        ->create(['default_game_match_type_id' => $matchType->id]);
}

/**
 * Spawn $n active, 1..N-seeded participants for $tournament (each with a fresh Clan).
 */
function makeStandingsParticipants(Tournament $tournament, int $n): void
{
    $clans = Clan::factory()->count($n)->create();
    TournamentParticipant::factory()
        ->for($tournament)
        ->count($n)
        ->state(new Sequence(...array_map(
            fn (int $i): array => [
                'seed' => $i + 1,
                'status' => 'active',
                'clan_id' => $clans[$i]->id,
            ],
            range(0, $n - 1)
        )))
        ->create();
}

/**
 * Record a MatchResult on a single materialised bracket with the named winner
 * (a TournamentParticipant). Drives the MatchResultObserver → advance() →
 * recalculate() chain end-to-end.
 */
function recordBracketResult(TournamentBracket $bracket, TournamentParticipant $winner, int $allies = 4, int $axis = 1): MatchResult
{
    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $winner->clan_id,
        'allies_score' => $allies,
        'axis_score' => $axis,
    ]);

    return $result;
}

// ---------------------------------------------------------------------------
// Single-elim: winner = rank 1, runner-up = rank 2
// ---------------------------------------------------------------------------

it('single-elim assigns rank 1 to tournament winner and rank 2 to runner-up', function (): void {
    $tournament = makeStandingsTournament('single_elimination');
    makeStandingsParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    $round1 = $stage->brackets()->with(['participantA', 'participantB'])->where('round_number', 1)->orderBy('position')->get();

    // Round 1: top-seeded participantA wins each semi.
    foreach ($round1 as $bracket) {
        /** @var TournamentBracket $bracket */
        recordBracketResult($bracket, $bracket->participantA);
    }

    // Round 2 (final): now both participant slots are filled by round-1 winners
    // (via observer-driven advance()). Materialise the final bracket.
    /** @var TournamentBracket $final */
    $final = $stage->brackets()->where('round_number', 2)->where('position', 1)->firstOrFail();
    app(BracketMatchMaterialiserService::class)->materialiseFor($final->fresh(), $tournament);
    $finalFresh = $final->fresh()?->load(['participantA', 'participantB']);
    /** @var TournamentParticipant $finalWinner */
    $finalWinner = $finalFresh->participantA;
    recordBracketResult($finalFresh, $finalWinner);

    // Tournament should auto-complete via the observer chain; standings
    // populated by the same chain.
    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('rank')
        ->get();

    expect($standings->count())->toBe(4);
    expect($standings->where('participant_id', $finalWinner->id)->first()->rank)->toBe(1);

    /** @var TournamentParticipant $runnerUp */
    $runnerUp = $finalFresh->participantB;
    expect($standings->where('participant_id', $runnerUp->id)->first()->rank)->toBe(2);

    // Two semi-final losers share rank 3 (Phase 9 polish: break by seed).
    $semiLosers = $standings->whereNotIn('participant_id', [$finalWinner->id, $runnerUp->id]);
    expect($semiLosers->count())->toBe(2);
    foreach ($semiLosers as $semiLoser) {
        expect($semiLoser->rank)->toBe(3);
    }
});

// ---------------------------------------------------------------------------
// Single-elim 8-clan: full placement spectrum (1/2/3/5)
// ---------------------------------------------------------------------------

it('single-elim 8-clan assigns ranks 1, 2, 3-tie, 5-tie by elimination round', function (): void {
    $tournament = makeStandingsTournament('single_elimination');
    makeStandingsParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Round 1: participantA wins each.
    foreach ($stage->brackets()->with(['participantA', 'participantB'])->where('round_number', 1)->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        recordBracketResult($bracket, $bracket->participantA);
    }

    // Round 2: materialise + record.
    foreach ($stage->brackets()->where('round_number', 2)->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        $fresh = $bracket->fresh();
        app(BracketMatchMaterialiserService::class)->materialiseFor($fresh, $tournament);
        $freshLoaded = $fresh->fresh()?->load(['participantA', 'participantB']);
        recordBracketResult($freshLoaded, $freshLoaded->participantA);
    }

    // Round 3 (final): materialise + record.
    /** @var TournamentBracket $final */
    $final = $stage->brackets()->where('round_number', 3)->where('position', 1)->firstOrFail();
    app(BracketMatchMaterialiserService::class)->materialiseFor($final->fresh(), $tournament);
    $finalFresh = $final->fresh()?->load(['participantA', 'participantB']);
    recordBracketResult($finalFresh, $finalFresh->participantA);

    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->get();

    expect($standings->count())->toBe(8);

    // Expected rank distribution:
    //   1 winner       → rank 1
    //   1 runner-up    → rank 2
    //   2 semi losers  → rank 3 (shared)
    //   4 QF losers    → rank 5 (shared)
    $ranks = $standings->pluck('rank')->sort()->values()->all();
    expect($ranks)->toBe([1, 2, 3, 3, 5, 5, 5, 5]);
});

// ---------------------------------------------------------------------------
// Round-robin: ranks by FIFA points (3/1/0)
// ---------------------------------------------------------------------------

it('round-robin ranks by FIFA points 3/1/0', function (): void {
    // 3-participant round-robin → 3 matches: (1v2), (1v3), (2v3).
    $tournament = makeStandingsTournament('round_robin');
    makeStandingsParticipants($tournament, 3);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Materialise every bracket (round-robin has no rounds in the same
    // single-elim sense — generator places all brackets up front; the
    // materialiser only does round_number=1, so we manually materialise
    // every bracket).
    foreach ($stage->brackets()->whereNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    }

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];
    /** @var TournamentParticipant $p2 */
    $p2 = $participants[1];
    /** @var TournamentParticipant $p3 */
    $p3 = $participants[2];

    // Pre-determined outcome: p1 beats p2 + p3; p2 beats p3.
    // Expected: p1=6pts (2W); p2=3pts (1W,1L); p3=0pts (2L).
    $brackets = $stage->brackets()->whereNotNull('match_id')->get();
    foreach ($brackets as $bracket) {
        /** @var TournamentBracket $bracket */
        $pa = $bracket->participant_a_id;
        $pb = $bracket->participant_b_id;
        $pair = [$pa, $pb];
        sort($pair);

        if ($pair === [$p1->id, $p2->id] || $pair === [$p2->id, $p1->id]) {
            recordBracketResult($bracket, $p1);
        } elseif ($pair === [$p1->id, $p3->id] || $pair === [$p3->id, $p1->id]) {
            recordBracketResult($bracket, $p1);
        } else {
            recordBracketResult($bracket, $p2);
        }
    }

    // Manually invoke recalculate (round-robin observer chain may not auto-fire
    // when no advances_to wiring exists; this is the SC-5 admin-action path).
    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('rank')
        ->get();

    expect($standings->count())->toBe(3);
    expect($standings->where('participant_id', $p1->id)->first()->rank)->toBe(1);
    expect((float) $standings->where('participant_id', $p1->id)->first()->points)->toBe(6.0);
    expect($standings->where('participant_id', $p2->id)->first()->rank)->toBe(2);
    expect((float) $standings->where('participant_id', $p2->id)->first()->points)->toBe(3.0);
    expect($standings->where('participant_id', $p3->id)->first()->rank)->toBe(3);
    expect((float) $standings->where('participant_id', $p3->id)->first()->points)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// Round-robin: head-to-head tiebreak among equal points
// ---------------------------------------------------------------------------

it('round-robin 4-clan: h2h breaks tie between participants on equal points', function (): void {
    $tournament = makeStandingsTournament('round_robin');
    makeStandingsParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    foreach ($stage->brackets()->whereNull('match_id')->get() as $bracket) {
        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    }

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];
    /** @var TournamentParticipant $p2 */
    $p2 = $participants[1];
    /** @var TournamentParticipant $p3 */
    $p3 = $participants[2];
    /** @var TournamentParticipant $p4 */
    $p4 = $participants[3];

    // Outcome design (6 matches in 4-clan RR):
    //   p1 beats p3, p1 beats p4 → p1 = 2W (6pts)
    //   p2 beats p3, p2 beats p4 → p2 = 2W (6pts)
    //   p1 beats p2 (head-to-head: p1 > p2)
    //   p3 beats p4 → p3 = 1W, p4 = 0W
    // Expected ranks: p1=1, p2=2, p3=3, p4=4.
    $byPair = function (TournamentParticipant $a, TournamentParticipant $b): array {
        return [$a->id, $b->id];
    };
    $expectedWinners = [
        // pair (sorted by id) → winner participant
    ];
    $pairs = [
        [$p1, $p2, $p1],
        [$p1, $p3, $p1],
        [$p1, $p4, $p1],
        [$p2, $p3, $p2],
        [$p2, $p4, $p2],
        [$p3, $p4, $p3],
    ];
    foreach ($pairs as [$a, $b, $winner]) {
        $key = $byPair($a, $b);
        sort($key);
        $expectedWinners[implode('-', $key)] = $winner;
    }

    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        $pair = [$bracket->participant_a_id, $bracket->participant_b_id];
        sort($pair);
        $winner = $expectedWinners[implode('-', $pair)] ?? null;
        if ($winner === null) {
            continue;
        }
        recordBracketResult($bracket, $winner);
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('rank')
        ->get();

    expect($standings->where('participant_id', $p1->id)->first()->rank)->toBe(1);
    expect($standings->where('participant_id', $p2->id)->first()->rank)->toBe(2);
    expect($standings->where('participant_id', $p3->id)->first()->rank)->toBe(3);
    expect($standings->where('participant_id', $p4->id)->first()->rank)->toBe(4);
});

// ---------------------------------------------------------------------------
// Round-robin: admin override of points scheme via settings
// ---------------------------------------------------------------------------

it('round-robin respects admin override of points-per-win via tournament settings', function (): void {
    $tournament = makeStandingsTournament('round_robin');
    $tournament->update(['settings' => ['roundrobin_points_per_win' => 5]]);
    makeStandingsParticipants($tournament, 3);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    foreach ($stage->brackets()->whereNull('match_id')->get() as $bracket) {
        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    }

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];

    // p1 wins everything.
    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        if ($bracket->participant_a_id === $p1->id || $bracket->participant_b_id === $p1->id) {
            recordBracketResult($bracket, $p1);
        } else {
            // Non-p1 match: arbitrary winner.
            $winnerPid = $bracket->participant_a_id;
            $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
            recordBracketResult($bracket, $winner);
        }
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $p1Standing = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->where('participant_id', $p1->id)
        ->firstOrFail();
    // 2 wins × 5 pts each = 10pts (not 6 with FIFA default).
    expect((float) $p1Standing->points)->toBe(10.0);
});

// ---------------------------------------------------------------------------
// Swiss: ranks by points then Buchholz
// ---------------------------------------------------------------------------

it('swiss ranks by points then Buchholz tiebreak', function (): void {
    // 4-participant swiss × 1 round (generator ships round 1 only).
    // Round 1 pairing (top half vs bottom half): (1v3), (2v4).
    $tournament = makeStandingsTournament('swiss');
    makeStandingsParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];
    /** @var TournamentParticipant $p3 */
    $p3 = $participants[2];

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Both top seeds win → p1=1pt, p2=1pt, p3=0, p4=0.
    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        $winnerPid = $bracket->participant_a_id;
        $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
        recordBracketResult($bracket, $winner);
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->orderBy('rank')
        ->get();

    expect($standings->count())->toBe(4);
    // p1 and p2 tied on 1pt; tiebreak by Buchholz then seed.
    // p1's opponent was p3 (0pt); p2's opponent was p4 (0pt). Equal Buchholz=0,
    // tiebreak falls to seed → p1 > p2.
    expect($standings->where('participant_id', $p1->id)->first()->rank)->toBe(1);
    expect((float) $standings->where('participant_id', $p1->id)->first()->points)->toBe(1.0);
    // p3 lost; Buchholz = p1's score = 1.
    expect((float) $standings->where('participant_id', $p3->id)->first()->tiebreak_score)->toBe(1.0);
});

// ---------------------------------------------------------------------------
// Double-elim: grand-final winner ranks 1
// ---------------------------------------------------------------------------

it('double-elim assigns rank 1 to grand-final winner and rank 2 to grand-final loser', function (): void {
    $tournament = makeStandingsTournament('double_elimination');
    makeStandingsParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentStage $gfStage */
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();

    // Manually populate the GF bracket with two participants + a materialised
    // match (skipping the W/L bracket play-through; we're testing the
    // standings calculator's placement logic, not the full advancement chain).
    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $winner */
    $winner = $participants[0];
    /** @var TournamentParticipant $loser */
    $loser = $participants[1];

    /** @var TournamentBracket $gfBracket */
    $gfBracket = $gfStage->brackets()->where('round_number', 1)->firstOrFail();
    $matchType = GameMatchType::query()->firstOrFail();
    $gfMatch = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $tournament->organiser_user_id,
    ]);
    $gfBracket->update([
        'participant_a_id' => $winner->id,
        'participant_b_id' => $loser->id,
        'match_id' => $gfMatch->id,
    ]);

    // Set W-bracket-final winner so allBracketsComplete() check is satisfied
    // (mark all W brackets complete).
    foreach ($wStage->brackets()->get() as $wb) {
        $wb->update(['winner_participant_id' => $wb->participant_a_id]);
    }

    // GF winner = participant_a → no reset triggered. Use a foreign-not-W
    // outcome that resolves the GF in one shot.
    recordBracketResult($gfBracket->fresh(), $winner);

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $standings = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->get();

    expect($standings->where('participant_id', $winner->id)->first()->rank)->toBe(1);
    expect($standings->where('participant_id', $loser->id)->first()->rank)->toBe(2);
});

// ---------------------------------------------------------------------------
// Recalculate wipes existing standings before recompute
// ---------------------------------------------------------------------------

it('recalculate wipes existing standings before recompute', function (): void {
    $tournament = makeStandingsTournament('round_robin');
    makeStandingsParticipants($tournament, 3);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    foreach ($stage->brackets()->whereNull('match_id')->get() as $bracket) {
        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    }

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];

    // Stuff some bogus standings rows in directly.
    foreach ($participants as $p) {
        TournamentStanding::factory()->create([
            'tournament_id' => $tournament->id,
            'tournament_stage_id' => $stage->id,
            'participant_id' => $p->id,
            'wins' => 99,
            'losses' => 99,
            'rank' => 99,
        ]);
    }

    expect(TournamentStanding::query()->where('tournament_id', $tournament->id)->count())->toBe(3);

    // Record real results (p1 wins everything) + recalculate.
    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        if ($bracket->participant_a_id === $p1->id || $bracket->participant_b_id === $p1->id) {
            recordBracketResult($bracket, $p1);
        } else {
            $winnerPid = $bracket->participant_a_id;
            $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
            recordBracketResult($bracket, $winner);
        }
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    // Still 3 rows (not 6) — old rows wiped.
    expect(TournamentStanding::query()->where('tournament_id', $tournament->id)->count())->toBe(3);

    // And the wins-99 bogus values are gone.
    $maxWins = (int) TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->max('wins');
    expect($maxWins)->toBeLessThan(99);
});

// ---------------------------------------------------------------------------
// Withdrawn participant retains rank from played matches (A5 LOCKED)
// ---------------------------------------------------------------------------

it('withdrawn participant retains W/L from played matches (A5 LOCKED)', function (): void {
    $tournament = makeStandingsTournament('round_robin');
    makeStandingsParticipants($tournament, 3);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    foreach ($stage->brackets()->whereNull('match_id')->get() as $bracket) {
        app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    }

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p1 */
    $p1 = $participants[0];

    // p1 wins everything…
    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        if ($bracket->participant_a_id === $p1->id || $bracket->participant_b_id === $p1->id) {
            recordBracketResult($bracket, $p1);
        } else {
            $winnerPid = $bracket->participant_a_id;
            $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
            recordBracketResult($bracket, $winner);
        }
    }

    // …then withdraws.
    $p1->update(['status' => 'withdrawn']);

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    /** @var TournamentStanding $p1Standing */
    $p1Standing = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->where('participant_id', $p1->id)
        ->firstOrFail();
    // Past wins retained — A5 LOCKED.
    expect($p1Standing->wins)->toBe(2);
    expect($p1Standing->losses)->toBe(0);
});
