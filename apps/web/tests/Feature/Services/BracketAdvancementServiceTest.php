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

    // Creating the MatchResult triggers the observer's created() hook which
    // dispatches advance() → the exception fires on the create() call itself.
    expect(fn () => MatchResult::factory()->create([
        'match_id' => $bracket->match_id,
        'winner_clan_id' => $foreignClan->id,
    ]))->toThrow(BracketWinnerNotParticipantException::class);
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

// ---------------------------------------------------------------------------
// Double-elim N≥8 — W-round-k (k≥2) loser drops into LB major-round slot B,
// never overwriting the LB-internal winner already in slot A (REACH-02).
// Regression for the resolveSlot collision: resolveSlot(W-r2-p1.position=1)='a'
// previously overwrote the LB winner sitting in slot a.
// ---------------------------------------------------------------------------

it('drops a W-round-2 loser into LB major-round slot B without overwriting the LB winner in slot A (N=8)', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);

    $participants = $tournament->participants()->orderBy('seed')->get();

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    /** @var TournamentBracket $wR2P1 */
    $wR2P1 = $wStage->brackets()->where('round_number', 2)->where('position', 1)->firstOrFail();

    // W-r2-p1 drops its loser into an LB major round (LB-r2-p1 for N=8).
    expect($wR2P1->loser_advances_to_bracket_id)->not->toBeNull();
    /** @var TournamentBracket $lbMajor */
    $lbMajor = TournamentBracket::query()->whereKey($wR2P1->loser_advances_to_bracket_id)->firstOrFail();

    // Slot A of that LB major round is reserved for the LB-internal winner that
    // advanced there. Simulate it already being filled with a sentinel participant.
    /** @var TournamentParticipant $lbWinner */
    $lbWinner = $participants[7];
    $lbMajor->update(['participant_a_id' => $lbWinner->id]);

    // Wire W-r2-p1 with two semifinalists + a materialised match.
    /** @var TournamentParticipant $wWinner */
    $wWinner = $participants[0];
    /** @var TournamentParticipant $wLoser */
    $wLoser = $participants[1];
    $matchType = GameMatchType::query()->firstOrFail();
    $wMatch = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $tournament->organiser_user_id,
    ]);
    $wR2P1->update([
        'participant_a_id' => $wWinner->id,
        'participant_b_id' => $wLoser->id,
        'match_id' => $wMatch->id,
    ]);

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $wMatch->id,
        'winner_clan_id' => $wWinner->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    $lbMajor->refresh();
    // Slot A is UNTOUCHED (the LB winner is preserved) and the W-r2 loser landed in slot B.
    expect($lbMajor->participant_a_id)->toBe($lbWinner->id);
    expect($lbMajor->participant_b_id)->toBe($wLoser->id);
});

it('drops the W-final loser into the L-final slot B (N=8, odd source position)', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);

    $participants = $tournament->participants()->orderBy('seed')->get();

    /** @var TournamentStage $wStage */
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    // W-final is the highest W round (3 for N=8), position 1. NOTE: the brackets()
    // relation carries a default ascending order, so a plain orderByDesc would be
    // appended after it and ignored — select the max round explicitly.
    $maxRound = (int) $wStage->brackets()->max('round_number');
    expect($maxRound)->toBe(3); // sanity: N=8 winners bracket has 3 rounds
    /** @var TournamentBracket $wFinal */
    $wFinal = $wStage->brackets()->where('round_number', $maxRound)->where('position', 1)->firstOrFail();
    expect($wFinal->loser_advances_to_bracket_id)->not->toBeNull();

    /** @var TournamentBracket $lFinal */
    $lFinal = TournamentBracket::query()->whereKey($wFinal->loser_advances_to_bracket_id)->firstOrFail();

    // L-final slot A reserved for the LB winner.
    /** @var TournamentParticipant $lbWinner */
    $lbWinner = $participants[7];
    $lFinal->update(['participant_a_id' => $lbWinner->id]);

    /** @var TournamentParticipant $wWinner */
    $wWinner = $participants[0];
    /** @var TournamentParticipant $wLoser */
    $wLoser = $participants[1];
    $matchType = GameMatchType::query()->firstOrFail();
    $wMatch = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $tournament->organiser_user_id,
    ]);
    $wFinal->update([
        'participant_a_id' => $wWinner->id,
        'participant_b_id' => $wLoser->id,
        'match_id' => $wMatch->id,
    ]);

    /** @var MatchResult $result */
    $result = MatchResult::factory()->create([
        'match_id' => $wMatch->id,
        'winner_clan_id' => $wWinner->clan_id,
    ]);

    app(BracketAdvancementService::class)->advance($result);

    $lFinal->refresh();
    expect($lFinal->participant_a_id)->toBe($lbWinner->id);
    expect($lFinal->participant_b_id)->toBe($wLoser->id);
});

// ---------------------------------------------------------------------------
// Double-elim — EVEN-positioned feeder collision (REACH-02 completeness).
// resolveLoserSlot alone was insufficient: the LB-internal winner-advance for an
// EVEN-positioned minor feeder (e.g. LB-r1-p2, resolveSlot(2)='b') collided with
// the W-loser-drop's hardcoded 'b'. resolveWinnerSlot routes the LB minor winner
// to slot 'a' instead. Adversarial-review finding (N=8 LB-r2-p2 collision).
// ---------------------------------------------------------------------------

/**
 * Wire a bracket with two participants + a materialised match, then advance it
 * with $winner winning. Returns nothing — caller asserts on the destination(s).
 */
function wireAndAdvance(
    TournamentBracket $bracket,
    TournamentParticipant $winner,
    TournamentParticipant $loser,
    Tournament $tournament,
): void {
    $matchType = GameMatchType::query()->firstOrFail();
    $match = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $tournament->organiser_user_id,
    ]);
    $bracket->update([
        'participant_a_id' => $winner->id,
        'participant_b_id' => $loser->id,
        'match_id' => $match->id,
    ]);
    $result = MatchResult::factory()->create([
        'match_id' => $match->id,
        'winner_clan_id' => $winner->clan_id,
    ]);
    app(BracketAdvancementService::class)->advance($result);
}

it('routes an even-positioned LB minor winner to slot A so the W-loser drop (slot B) does not collide (N=8)', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);
    $p = $tournament->participants()->orderBy('seed')->get();

    $lStage = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail();
    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();

    // LB-r1-p2 (minor, EVEN position) winner must advance to LB-r2-p2 slot A.
    $lbR1P2 = $lStage->brackets()->where('round_number', 1)->where('position', 2)->firstOrFail();
    $lbR2P2 = TournamentBracket::query()->whereKey($lbR1P2->advances_to_bracket_id)->firstOrFail();
    expect($lbR2P2->round_number)->toBe(2)->and($lbR2P2->position)->toBe(2);

    // W-r2-p2 (EVEN position) loser drops into that SAME LB-r2-p2.
    $wR2P2 = $wStage->brackets()->where('round_number', 2)->where('position', 2)->firstOrFail();
    expect($wR2P2->loser_advances_to_bracket_id)->toBe($lbR2P2->id);

    // Advance LB-r1-p2 → winner into LB-r2-p2 slot A.
    wireAndAdvance($lbR1P2, $p[0], $p[1], $tournament);
    $lbR2P2->refresh();
    expect($lbR2P2->participant_a_id)->toBe($p[0]->id); // LB winner in slot A

    // Advance W-r2-p2 → loser into LB-r2-p2 slot B, WITHOUT overwriting slot A.
    wireAndAdvance($wR2P2, $p[2], $p[3], $tournament);
    $lbR2P2->refresh();
    expect($lbR2P2->participant_a_id)->toBe($p[0]->id);  // LB winner preserved
    expect($lbR2P2->participant_b_id)->toBe($p[3]->id);  // W-r2-p2 loser
});

it('sends W-final winner to grand-final slot A and L-final winner to slot B (no GF collision, N=8)', function (): void {
    $tournament = makeAdvancementTournament('double_elimination', 'running');
    makeAdvancementParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);
    $p = $tournament->participants()->orderBy('seed')->get();

    $wStage = $tournament->stages()->where('type', 'winners-bracket')->firstOrFail();
    $lStage = $tournament->stages()->where('type', 'losers-bracket')->firstOrFail();
    $gfStage = $tournament->stages()->where('type', 'grand-final')->firstOrFail();
    $gf = $gfStage->brackets()->firstOrFail();

    $wMax = (int) $wStage->brackets()->max('round_number');
    $wFinal = $wStage->brackets()->where('round_number', $wMax)->where('position', 1)->firstOrFail();
    $lMax = (int) $lStage->brackets()->max('round_number');
    $lFinal = $lStage->brackets()->where('round_number', $lMax)->where('position', 1)->firstOrFail();
    expect($wFinal->advances_to_bracket_id)->toBe($gf->id);
    expect($lFinal->advances_to_bracket_id)->toBe($gf->id);

    wireAndAdvance($wFinal, $p[0], $p[1], $tournament);
    wireAndAdvance($lFinal, $p[2], $p[3], $tournament);

    $gf->refresh();
    expect($gf->participant_a_id)->toBe($p[0]->id); // W-final champion → slot A
    expect($gf->participant_b_id)->toBe($p[2]->id); // L-final champion → slot B
});
