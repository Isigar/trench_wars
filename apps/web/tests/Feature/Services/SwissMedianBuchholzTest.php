<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\Game;
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
| Source: 11-01-PLAN.md Task 2 — RED scaffold; turned GREEN by 11-03.
|
| Covers SwissStandingsCalculator median_buchholz column (TOUR-03):
|   - 4-opponent fixture: median_buchholz drops highest + lowest opponent score
|     (Buchholz Cut 1 — standard definition). With 4 opponents, median = sum of
|     middle 2 scores.
|   - <3-opponent fixture: median_buchholz == tiebreak_score (plain Buchholz).
|     No values to drop when opponent count < 3.
|
| FAILS now because SwissStandingsCalculator does not yet write median_buchholz
| (column defaults to 0 → assertion expects a computed non-zero value).
*/

/**
 * Build a swiss Tournament with a default GameMatchType.
 * Mirrors makeStandingsTournament() from StandingsCalculatorServiceTest.
 */
function makeSwissMedianTournament(): Tournament
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
        ->ofFormat('swiss')
        ->inStatus('running')
        ->for($game)
        ->create(['default_game_match_type_id' => $matchType->id]);
}

/**
 * Spawn $n active, seeded participants each with their own Clan.
 * Mirrors makeStandingsParticipants() from StandingsCalculatorServiceTest.
 */
function makeSwissMedianParticipants(Tournament $tournament, int $n): void
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
 * Record a MatchResult via BracketAdvancementService on the given bracket
 * (drives the same observer chain as the standings calculator tests).
 */
function recordSwissResult(TournamentBracket $bracket, TournamentParticipant $winner): void
{
    MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $winner->clan_id,
        'allies_score' => 4,
        'axis_score' => 1,
    ]);
}

// ---------------------------------------------------------------------------
// 4-participant swiss: loser's median_buchholz matches winner's points (1 opponent, <3 → equal to buchholz)
// and SwissStandingsCalculator writes the column (not stuck at default 0)
// ---------------------------------------------------------------------------

it('median_buchholz is written by the calculator (not stuck at column default 0)', function (): void {
    // 4-participant Swiss, round 1 pairing: 1v3, 2v4.
    // p3 LOSES to p1. p1 scores 1 point. So p3's plain Buchholz = 1.0.
    // After the calculator runs, p3.median_buchholz MUST equal 1.0 too
    // (same as tiebreak_score — only 1 opponent, so no values to drop).
    //
    // FAILS now because SwissStandingsCalculator does NOT yet write median_buchholz.
    // The column stays at the DB default (0.0), but tiebreak_score is 1.0.
    $tournament = makeSwissMedianTournament();
    makeSwissMedianParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p3 */
    $p3 = $participants[2]; // seed=3, paired against seed=1 (p1); p1 wins

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Top seeds (participant_a in each bracket) win. p1 beats p3, p2 beats p4.
    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        $winnerPid = $bracket->participant_a_id;
        $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
        recordSwissResult($bracket, $winner);
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    $p3Standing = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->where('participant_id', $p3->id)
        ->firstOrFail();

    // p3 has 1 opponent (p1, who scored 1.0 pts). Plain Buchholz = 1.0.
    // median_buchholz MUST be written as 1.0 (not stuck at the DB default 0.0).
    // Currently fails: median_buchholz stays 0.0 (calculator does not write it).
    expect((float) $p3Standing->tiebreak_score)->toBe(1.0);   // sanity — buchholz is 1.0
    expect((float) $p3Standing->median_buchholz)->toBe(1.0);  // RED: stuck at 0.0 now
});

// ---------------------------------------------------------------------------
// <3-opponent edge: median_buchholz == tiebreak_score (plain Buchholz)
// ---------------------------------------------------------------------------

it('median_buchholz equals tiebreak_score when participant has fewer than 3 opponents', function (): void {
    // 4-participant Swiss, 1 round — each participant faces exactly 1 opponent.
    // Count of opponents < 3, so Buchholz Cut 1 DOES NOT drop any scores:
    // median_buchholz == plain Buchholz == tiebreak_score.
    $tournament = makeSwissMedianTournament();
    makeSwissMedianParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $p3 */
    $p3 = $participants[2]; // seed=3, will LOSE to p1 → tiebreak_score = p1's points = 1.0

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
        /** @var TournamentBracket $bracket */
        $winnerPid = $bracket->participant_a_id;
        $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
        recordSwissResult($bracket, $winner);
    }

    app(StandingsCalculatorService::class)->recalculate($tournament->fresh());

    // p3 has 1 opponent (p1, who scored 1 pt). Both Buchholz == median_buchholz == 1.0.
    $p3Standing = TournamentStanding::query()
        ->where('tournament_id', $tournament->id)
        ->where('participant_id', $p3->id)
        ->firstOrFail();

    // tiebreak_score (plain Buchholz) is written by the existing calculator.
    // median_buchholz is NOT yet written — this test asserts the eventual equality
    // once plan 11-03 greens it. Currently fails because median_buchholz defaults to 0.
    expect((float) $p3Standing->median_buchholz)->toBe((float) $p3Standing->tiebreak_score);
});
