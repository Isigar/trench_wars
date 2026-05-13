<?php

declare(strict_types=1);

use App\Exceptions\BracketWinnerNotParticipantException;
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
use App\Services\BracketAdvancementService;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-08-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers BracketAdvancementService::advance():
|   - Happy path: winner propagates into next bracket via Pattern 7 parity rule.
|   - No-op: non-tournament match (no bracket row links to match_id).
|   - No-op: draw (winner_clan_id is null).
|   - Throws: BracketWinnerNotParticipantException for foreign clan.
|   - Pattern 7 slot a (odd from-position) + slot b (even from-position).
|   - Double-elim: loser propagates to loser_advances_to_bracket_id slot.
|   - Grand-final reset: lazy creation of round-2 reset match when W-winner loses.
|   - Tournament completion: auto-transition to 'completed' when all materialised brackets are decided.
|   - Discord outbound: bracket_result_announce row created with correct payload kind.
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. No `match($x)` exprs
| appear here so the alias-on-import pattern is not needed.
*/

/**
 * Build a Tournament with a default GameMatchType backed by a single
 * RoleLimit (capacity=2) so the materialiser produces 2 MatchSlot rows per bracket.
 */
function makeAdvancementTournament(string $format = 'single_elimination', string $status = 'running', int $capacity = 2): Tournament
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => $capacity,
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
function makeAdvancementParticipants(Tournament $tournament, int $n): void
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
// Happy path — winner propagates into next bracket slot a (odd from-position)
// ---------------------------------------------------------------------------

it('advances winner to next bracket slot a for odd from-position (Pattern 7)', function (): void {
    // 4-participant single-elim — round-1 position 1 → round-2 slot a.
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;
    expect($participantA)->not->toBeNull();

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    $bracket->refresh();
    expect($bracket->winner_participant_id)->toBe($participantA->id);

    /** @var TournamentBracket $next */
    $next = $stage->brackets()->where('round_number', 2)->where('position', 1)->firstOrFail();
    // Position 1 is odd → slot a.
    expect($next->participant_a_id)->toBe($participantA->id);
});

// ---------------------------------------------------------------------------
// Pattern 7 — slot b for even from-position
// ---------------------------------------------------------------------------

it('advances winner to slot b for even from-position (Pattern 7)', function (): void {
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 2)->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;
    expect($participantA)->not->toBeNull();

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    /** @var TournamentBracket $next */
    $next = $stage->brackets()->where('round_number', 2)->where('position', 1)->firstOrFail();
    // Position 2 is even → slot b.
    expect($next->participant_b_id)->toBe($participantA->id);
});

// ---------------------------------------------------------------------------
// No-op — non-tournament match (no bracket links to match_id)
// ---------------------------------------------------------------------------

it('is a no-op when no tournament_bracket links to the result match_id', function (): void {
    $match = GameMatch::factory()->create();
    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $match->id,
        'winner_clan_id' => Clan::factory()->create()->id,
    ]);

    // Should not throw — non-tournament match.
    app(BracketAdvancementService::class)->advance($result);

    // No DiscordOutboundMessage was written.
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// No-op — draw (winner_clan_id is null)
// ---------------------------------------------------------------------------

it('is a no-op when winner_clan_id is null (draw)', function (): void {
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => null,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    $bracket->refresh();
    expect($bracket->winner_participant_id)->toBeNull();
    expect(DiscordOutboundMessage::query()->where('message_type', 'bracket_result_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Throws — winner clan is not a registered participant
// ---------------------------------------------------------------------------

it('throws BracketWinnerNotParticipantException when winner_clan_id is foreign', function (): void {
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();

    $foreignClan = Clan::factory()->create();
    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $foreignClan->id,
    ]);

    expect(fn () => app(BracketAdvancementService::class)->advance($result))
        ->toThrow(BracketWinnerNotParticipantException::class);
});

// ---------------------------------------------------------------------------
// Discord outbound row written with bracket_result_announce kind
// ---------------------------------------------------------------------------

it('enqueues a DiscordOutboundMessage row with bracket_result_announce on advance', function (): void {
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    /** @var DiscordOutboundMessage $row */
    $row = DiscordOutboundMessage::query()
        ->where('message_type', 'bracket_result_announce')
        ->latest('created_at')
        ->firstOrFail();

    expect($row->status)->toBe('pending');
    /** @var array<string, mixed> $payload */
    $payload = $row->payload;
    expect($payload['kind'])->toBe('bracket_result_announce');
    expect($payload['bracket_id'])->toBe($bracket->id);
    expect($payload['winner_participant_id'])->toBe($participantA->id);
    expect($payload['tournament_id'])->toBe($tournament->id);
});

// ---------------------------------------------------------------------------
// Tournament completion auto-transition
// ---------------------------------------------------------------------------

it('auto-transitions tournament to completed when every materialised bracket has a winner', function (): void {
    // 2-participant single-elim — 1 stage, 1 bracket.
    $tournament = makeAdvancementTournament('single_elimination', 'running');
    makeAdvancementParticipants($tournament, 2);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->firstOrFail();
    /** @var TournamentParticipant $participantA */
    $participantA = $bracket->participantA;

    expect($tournament->status)->toBe('running');

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $participantA->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    $tournament->refresh();
    expect($tournament->status)->toBe('completed');
});

// ---------------------------------------------------------------------------
// Double-elim — loser propagates to loser_advances_to_bracket_id
// ---------------------------------------------------------------------------

it('propagates loser to loser_advances_to_bracket_id slot for double-elim W-bracket', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentBracket $wR1P1 */
    $wR1P1 = $wStage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();
    expect($wR1P1->loser_advances_to_bracket_id)->not->toBeNull();

    /** @var TournamentParticipant $winner */
    $winner = $wR1P1->participantA;
    /** @var TournamentParticipant $loser */
    $loser = $wR1P1->participantB;
    expect($winner)->not->toBeNull();
    expect($loser)->not->toBeNull();

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $wR1P1->match_id,
        'winner_clan_id' => $winner->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    /** @var TournamentBracket $lDrop */
    $lDrop = TournamentBracket::query()->whereKey($wR1P1->loser_advances_to_bracket_id)->firstOrFail();
    // Position 1 odd → slot a in the L-bracket destination.
    expect($lDrop->participant_a_id)->toBe($loser->id);
    // And winner went forward in W-bracket too.
    $wR1P1->refresh();
    expect($wR1P1->winner_participant_id)->toBe($winner->id);
});

// ---------------------------------------------------------------------------
// Grand-final reset lazy creation (double-elim + grand_final_reset=true)
// ---------------------------------------------------------------------------

it('lazily creates the grand-final reset bracket when W-winner loses the GF and reset is enabled', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    $tournament->update(['settings' => ['grand_final_reset' => true]]);
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentStage $gfStage */
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();
    /** @var TournamentBracket $gfBracket */
    $gfBracket = $gfStage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();

    // Simulate the W-bracket final having already resolved with W-winner = $wWinner.
    $participants = $tournament->participants()->orderBy('seed')->get();
    /** @var TournamentParticipant $wWinner */
    $wWinner = $participants[0];
    /** @var TournamentParticipant $lWinner */
    $lWinner = $participants[1];

    // Wire the GF bracket with both finalists + a materialised GameMatch.
    $matchType = GameMatchType::query()->firstOrFail();
    $gfMatch = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $tournament->organiser_user_id,
    ]);
    $gfBracket->update([
        'participant_a_id' => $wWinner->id,
        'participant_b_id' => $lWinner->id,
        'match_id' => $gfMatch->id,
    ]);

    // Set the W-bracket final winner manually so findStageWinner() returns wWinner.
    /** @var TournamentBracket $wFinal */
    $wFinal = $wStage->brackets()->orderByDesc('round_number')->orderBy('position')->firstOrFail();
    $wFinal->update(['winner_participant_id' => $wWinner->id]);

    // GF result: L-winner wins → reset match should be created.
    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $gfMatch->id,
        'winner_clan_id' => $lWinner->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    /** @var TournamentBracket $reset */
    $reset = $gfStage->brackets()->where('round_number', 2)->where('position', 1)->firstOrFail();
    expect($reset)->not->toBeNull();
    expect($reset->participant_a_id)->toBe($wWinner->id);
    expect($reset->participant_b_id)->toBe($lWinner->id);
});
