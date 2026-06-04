<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use RuntimeException;

/*
| Source: 11-01-PLAN.md Task 2 — RED scaffold; turned GREEN by 11-04.
|
| Covers BracketMatchMaterialiserService stage.game_match_type_id override (TOUR-04):
|   - A stage with game_match_type_id set materialises a GameMatch with that type
|     (not the tournament default).
|   - A stage with game_match_type_id = null falls back to tournament.default_game_match_type_id.
|
| FAILS now because BracketMatchMaterialiserService::materialiseFor() always uses
| $tournament->default_game_match_type_id and ignores the stage-level override.
*/

/**
 * Build a tournament with a default GameMatchType (matchTypeA).
 * Returns [tournament, matchTypeA, matchTypeB] so callers can set stage overrides.
 *
 * @return array{Tournament, GameMatchType, GameMatchType}
 */
function makeOverrideTournament(): array
{
    $game = Game::factory()->create();

    // Two match types for the same game.
    $matchTypeA = GameMatchType::factory()->for($game)->create();
    $matchTypeB = GameMatchType::factory()->for($game)->create();

    $role = GameRole::factory()->for($game)->create();
    // Both match types need a RoleLimit so materialisation can create MatchSlots.
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchTypeA->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]);
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchTypeB->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]);

    $tournament = Tournament::factory()
        ->ofFormat('single_elimination')
        ->inStatus('running')
        ->for($game)
        ->create(['default_game_match_type_id' => $matchTypeA->id]);

    return [$tournament, $matchTypeA, $matchTypeB];
}

/**
 * Spawn $n active, seeded participants with clans.
 */
function makeOverrideParticipants(Tournament $tournament, int $n): void
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
// Stage override: materialised GameMatch uses stage.game_match_type_id
// ---------------------------------------------------------------------------

it('materialises a GameMatch with the stage-level game_match_type_id override when set', function (): void {
    [$tournament, $matchTypeA, $matchTypeB] = makeOverrideTournament();
    makeOverrideParticipants($tournament, 2);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Set the stage override to matchTypeB (NOT the tournament default matchTypeA).
    $stage->update(['game_match_type_id' => $matchTypeB->id]);

    // Materialise the first-round bracket.
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->whereNotNull('match_id')->first();
    /** @var GameMatch $match */
    $match = $bracket->match;

    // The spawned GameMatch must use matchTypeB (the stage override), NOT matchTypeA.
    // FAILS now because materialiseFor uses tournament.default_game_match_type_id always.
    expect($match->game_match_type_id)->toBe($matchTypeB->id);
});

// ---------------------------------------------------------------------------
// No override: materialised GameMatch falls back to tournament default
// ---------------------------------------------------------------------------

it('materialises a GameMatch with the tournament default when stage override is null', function (): void {
    [$tournament, $matchTypeA] = makeOverrideTournament();
    makeOverrideParticipants($tournament, 2);

    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();

    // Explicitly ensure no override (should be null by default, but be explicit).
    $stage->update(['game_match_type_id' => null]);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->whereNotNull('match_id')->first();
    /** @var GameMatch $match */
    $match = $bracket->match;

    // No override → falls back to tournament default matchTypeA.
    // This path already works today (plan 11-04 must not break it).
    expect($match->game_match_type_id)->toBe($matchTypeA->id);
});

// ---------------------------------------------------------------------------
// Both null: RuntimeException with extended message (TOUR-04 guard)
// ---------------------------------------------------------------------------

it('throws RuntimeException when both stage override and tournament default are null', function (): void {
    // Tournament with NO default_game_match_type_id.
    $game = Game::factory()->create();
    $tournament = Tournament::factory()
        ->ofFormat('single_elimination')
        ->inStatus('running')
        ->for($game)
        ->create(['default_game_match_type_id' => null]);

    $stage = TournamentStage::factory()->for($tournament)->create([
        'game_match_type_id' => null, // no stage override either
    ]);

    /** @var TournamentParticipant $a */
    $a = TournamentParticipant::factory()->for($tournament)->create();
    /** @var TournamentParticipant $b */
    $b = TournamentParticipant::factory()->for($tournament)->create();
    /** @var TournamentBracket $bracket */
    $bracket = TournamentBracket::factory()->create([
        'tournament_stage_id' => $stage->id,
        'participant_a_id' => $a->id,
        'participant_b_id' => $b->id,
    ]);

    // TOUR-04: the extended RuntimeException message must mention both the missing
    // tournament default AND the missing stage override.
    expect(fn () => app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament))
        ->toThrow(RuntimeException::class, 'stage has no override');
});
