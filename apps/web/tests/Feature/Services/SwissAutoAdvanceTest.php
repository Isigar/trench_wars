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
use App\Services\BracketAdvancementService;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\Brackets\SwissGenerator;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 11-01-PLAN.md Task 2 — RED scaffold; turned GREEN by 11-02.
|
| Covers Swiss auto-advance (TOUR-01):
|   - When the final result of a Swiss round is recorded, the next swiss-round
|     stage generates automatically (stage count goes from 1 → 2).
|   - A second advance does NOT generate a third stage when rounds are exhausted
|     (idempotency — calling again does not regenerate what already exists).
|
| FAILS now because BracketAdvancementService does not yet call
| SwissGenerator::generateNextRound() automatically; the observer chain does not
| trigger auto-advance. Stage count stays at 1 after recording the final result.
*/

/**
 * Build a swiss Tournament backed by a minimal GameMatchType.
 * Status 'running' so BracketAdvancementService can advance brackets.
 */
function makeSwissTournament(int $rounds = 3): Tournament
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]);

    // 4 participants → ceil(log2(4)) = 2 total rounds.
    // The tournament_settings can encode expected_rounds; Swiss generator
    // uses the participant count to compute totalRounds dynamically.
    return Tournament::factory()
        ->ofFormat('swiss')
        ->inStatus('running')
        ->for($game)
        ->create(['default_game_match_type_id' => $matchType->id]);
}

/**
 * Spawn $n active, seeded participants with clans.
 */
function makeSwissAutoParticipants(Tournament $tournament, int $n): void
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

// ---------------------------------------------------------------------------
// Auto-advance: final result of Swiss round 1 generates round 2
// ---------------------------------------------------------------------------

it('recording the final bracket result of a Swiss round auto-generates the next round', function (): void {
    $tournament = makeSwissTournament();
    makeSwissAutoParticipants($tournament, 4);

    // Generate + materialise round 1.
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    // Before recording results: 1 swiss-round stage exists.
    expect($tournament->stages()->where('type', 'swiss-round')->count())->toBe(1);

    /** @var TournamentStage $stage1 */
    $stage1 = $tournament->stages()->where('type', 'swiss-round')->first();

    // Record ALL round-1 results (drives the BracketAdvancementService observer chain).
    $stage1->brackets()->whereNotNull('match_id')->each(function (TournamentBracket $bracket): void {
        $winnerPid = $bracket->participant_a_id;
        /** @var TournamentParticipant $winner */
        $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
        MatchResult::factory()->create([
            'match_id' => $bracket->match_id,
            'winner_clan_id' => $winner->clan_id,
            'allies_score' => 4,
            'axis_score' => 1,
        ]);
    });

    // After the last result: BracketAdvancementService SHOULD detect that all round-1
    // brackets are decided and automatically call SwissGenerator::generateNextRound.
    // Round 2 stage should now exist → count = 2.
    // FAILS now because auto-advance is not wired.
    expect($tournament->fresh()->stages()->where('type', 'swiss-round')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// No advance past final round — exhaustion guard
// ---------------------------------------------------------------------------

it('auto-advance does not generate a round past the final swiss round', function (): void {
    // 4 participants → totalRounds = ceil(log2(4)) = 2.
    // After round 2 is complete, no round 3 should be generated.
    $tournament = makeSwissTournament();
    makeSwissAutoParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    // Complete round 1 → auto-advance generates round 2.
    /** @var TournamentStage $stage1 */
    $stage1 = $tournament->stages()->where('type', 'swiss-round')->first();
    $stage1->brackets()->whereNotNull('match_id')->each(function (TournamentBracket $bracket): void {
        $winner = TournamentParticipant::query()->whereKey($bracket->participant_a_id)->firstOrFail();
        MatchResult::factory()->create([
            'match_id' => $bracket->match_id,
            'winner_clan_id' => $winner->clan_id,
            'allies_score' => 3,
            'axis_score' => 0,
        ]);
    });

    // Round 2 now exists.
    expect($tournament->fresh()->stages()->where('type', 'swiss-round')->count())->toBe(2);

    // Materialise and complete round 2.
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament->fresh());

    /** @var TournamentStage $stage2 */
    // reorder() clears the HasMany default 'orderBy ordinal ASC' before applying DESC.
    $stage2 = $tournament->fresh()->stages()->where('type', 'swiss-round')->reorder()->orderByDesc('ordinal')->first();
    $stage2->brackets()->whereNotNull('match_id')->each(function (TournamentBracket $bracket): void {
        $winner = TournamentParticipant::query()->whereKey($bracket->participant_a_id)->firstOrFail();
        MatchResult::factory()->create([
            'match_id' => $bracket->match_id,
            'winner_clan_id' => $winner->clan_id,
            'allies_score' => 3,
            'axis_score' => 0,
        ]);
    });

    // Still exactly 2 swiss-round stages — no round 3 generated past the final round.
    expect($tournament->fresh()->stages()->where('type', 'swiss-round')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Premature-completion guard (BLOCKER-class finding, plan 11-03)
// ---------------------------------------------------------------------------

it('tournament does NOT auto-complete after a non-final swiss round resolves (premature-completion guard)', function (): void {
    // 4 participants → 2 total rounds. After round 1 resolves, auto-advance
    // generates round 2 (new brackets with match_id=null). The tournament
    // must remain 'running' — NOT transition to 'completed'.
    $tournament = makeSwissTournament();
    makeSwissAutoParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    expect($tournament->fresh()->status)->toBe('running');

    /** @var TournamentStage $stage1 */
    $stage1 = $tournament->stages()->where('type', 'swiss-round')->first();

    // Record all round-1 results to trigger auto-advance.
    $stage1->brackets()->whereNotNull('match_id')->each(function (TournamentBracket $bracket): void {
        $winner = TournamentParticipant::query()->whereKey($bracket->participant_a_id)->firstOrFail();
        MatchResult::factory()->create([
            'match_id' => $bracket->match_id,
            'winner_clan_id' => $winner->clan_id,
            'allies_score' => 4,
            'axis_score' => 1,
        ]);
    });

    // Round 2 has been auto-generated (new brackets with match_id=null).
    expect($tournament->fresh()->stages()->where('type', 'swiss-round')->count())->toBe(2);

    // Tournament must still be 'running' — the unmaterialised round 2 brackets
    // mean the tournament is NOT terminal yet (premature-completion guard).
    expect($tournament->fresh()->status)->toBe('running');
});

// ---------------------------------------------------------------------------
// Idempotency: auto-advance does not create duplicate rounds
// ---------------------------------------------------------------------------

it('auto-advance does not generate a duplicate round when advance() is re-fired on an already-advanced bracket', function (): void {
    $tournament = makeSwissTournament();
    makeSwissAutoParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage1 */
    $stage1 = $tournament->stages()->where('type', 'swiss-round')->first();

    // Record all round-1 results (triggers auto-advance once per bracket result).
    // The last result fires advance() which detects the round is complete and
    // auto-generates round 2.
    $stage1->brackets()->whereNotNull('match_id')->each(function (TournamentBracket $bracket): void {
        $winnerPid = $bracket->participant_a_id;
        /** @var TournamentParticipant $winner */
        $winner = TournamentParticipant::query()->whereKey($winnerPid)->firstOrFail();
        MatchResult::factory()->create([
            'match_id' => $bracket->match_id,
            'winner_clan_id' => $winner->clan_id,
            'allies_score' => 4,
            'axis_score' => 1,
        ]);
    });

    $countAfterRound1 = $tournament->fresh()->stages()->where('type', 'swiss-round')->count();

    // Re-fire advance() on a bracket that has already been advanced.
    // The nextRoundExists guard in BracketAdvancementService must prevent
    // a duplicate stage from being created.
    /** @var TournamentBracket $firstBracket */
    $firstBracket = $stage1->brackets()->whereNotNull('match_id')->firstOrFail();
    /** @var TournamentParticipant $winner1 */
    $winner1 = TournamentParticipant::query()->whereKey($firstBracket->participant_a_id)->firstOrFail();
    $syntheticResult = new MatchResult([
        'match_id' => $firstBracket->match_id,
        'winner_clan_id' => $winner1->clan_id,
        'allies_score' => 4,
        'axis_score' => 1,
    ]);
    app(BracketAdvancementService::class)->advance($syntheticResult);

    // Count must not have increased — the existence-check guard prevented a duplicate.
    expect($tournament->fresh()->stages()->where('type', 'swiss-round')->count())->toBe($countAfterRound1);
});
