<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\DiscordOutboundMessage;
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
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-08-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers MatchResultObserver::saved():
|   - tournament-match MatchResult fires advance() (bracket gets winner_participant_id).
|   - non-tournament MatchResult is a no-op (no DiscordOutboundMessage row created).
|   - draw (winner_clan_id=null) does not fire advance().
|   - touch() (no relevant attribute change) does not re-fire advance() — single
|     bracket_result_announce row across create + touch.
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. No `match($x)` exprs
| appear here so the alias-on-import pattern is not needed.
*/

/**
 * Build a Tournament with a default GameMatchType backed by a single
 * RoleLimit (capacity=2) so the materialiser produces 2 MatchSlot rows per bracket.
 */
function makeObserverTournament(): Tournament
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
        ->ofFormat('single_elimination')
        ->inStatus('running')
        ->for($game)
        ->create(['default_game_match_type_id' => $matchType->id]);
}

/**
 * Spawn $n active, 1..N-seeded participants for $tournament (each with a fresh Clan).
 */
function makeObserverParticipants(Tournament $tournament, int $n): void
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
// Tournament match — observer fires advance() on MatchResult creation
// ---------------------------------------------------------------------------

it('fires advance() when a MatchResult is created for a tournament match', function (): void {
    $tournament = makeObserverTournament();
    makeObserverParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;

    expect($bracket->winner_participant_id)->toBeNull();

    // Creating the MatchResult should trigger the observer → advance() → bracket update.
    MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    $bracket->refresh();
    expect($bracket->winner_participant_id)->toBe($participantA->id);
});

// ---------------------------------------------------------------------------
// Non-tournament match — observer fires but advance() is a no-op
// ---------------------------------------------------------------------------

it('is a no-op for a MatchResult on a non-tournament match', function (): void {
    $match = GameMatch::factory()->create();
    $winnerClan = Clan::factory()->create();

    // Observer fires; advance() short-circuits (no bracket links to match_id).
    MatchResult::factory()->create([
        'match_id' => $match->id,
        'winner_clan_id' => $winnerClan->id,
    ]);

    // No bracket-related side effects.
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Draw — observer short-circuits on null winner_clan_id
// ---------------------------------------------------------------------------

it('does not fire advance() when winner_clan_id is null (draw)', function (): void {
    $tournament = makeObserverTournament();
    makeObserverParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();

    MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => null,
    ]);

    $bracket->refresh();
    expect($bracket->winner_participant_id)->toBeNull();
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// wasChanged guard — touch() does not re-fire advance()
// ---------------------------------------------------------------------------

it('does not re-fire advance() on a save that touches no relevant attributes', function (): void {
    $tournament = makeObserverTournament();
    makeObserverParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;

    // First save — observer fires advance() → one bracket_result_announce row created.
    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(1);

    // Touch the row — no attribute changes, just timestamp update.
    $result->touch();

    // touch() should NOT re-fire advance() (no relevant attribute changed and not recently created).
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(1);

    // Now change a relevant attribute (allies_score) → advance() should fire again → new outbound row.
    $result->update(['allies_score' => 7]);
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(2);
});
