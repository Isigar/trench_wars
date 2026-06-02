<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-06-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers BracketMatchMaterialiserService:
|   - materialiseFirstRound(Tournament) iterates non-bye round-1 brackets and
|     spawns one GameMatch per bracket + slot grid (Phase 4 reuse).
|   - materialiseFor(TournamentBracket) is the per-bracket entry; row-locked +
|     idempotent (Pitfall 4 mitigation).
|   - Byes (participant_b_id IS NULL) are skipped — no GameMatch needed.
|   - host_clan_id is NULL (A10 LOCKED) — bracket matches have no host clan.
|   - game_match_type_id matches tournament.default_game_match_type_id.
|   - status='open' (signups open automatically).
|   - Slot grid is spawned via Phase 4 MatchSlotMaterialiserService.
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. No `match($x)`
| expressions appear here so the alias-on-import pattern is not needed.
*/

/**
 * Helper: build a Tournament with a default GameMatchType backed by a single
 * RoleLimit (capacity=2) so the materialiser produces 2 MatchSlot rows per bracket.
 */
function makeTournamentWithMatchType(int $capacity = 2): Tournament
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
        ->ofFormat('single_elimination')
        ->inStatus('seeded')
        ->for($game)
        ->create(['default_game_match_type_id' => $matchType->id]);
}

/**
 * Helper: spawn $n active, 1..N-seeded participants for $tournament.
 */
function makeMaterialiserParticipants(Tournament $tournament, int $n): void
{
    TournamentParticipant::factory()
        ->for($tournament)
        ->count($n)
        ->state(new Sequence(...array_map(
            fn (int $i): array => ['seed' => $i + 1, 'status' => 'active'],
            range(0, $n - 1)
        )))
        ->create();
}

// ---------------------------------------------------------------------------
// Happy path — 8 participants, 4 round-1 GameMatches
// ---------------------------------------------------------------------------

it('materialises a GameMatch for every non-bye round-1 bracket of an 8-participant single-elim', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    $round1 = $stage->brackets()->where('round_number', 1)->get();

    expect($round1->count())->toBe(4);
    foreach ($round1 as $bracket) {
        expect($bracket->match_id)->not()->toBeNull();
    }

    // Each round-1 bracket got a distinct GameMatch row.
    expect(GameMatch::query()->whereIn('id', $round1->pluck('match_id'))->count())->toBe(4);
});

// ---------------------------------------------------------------------------
// Bye-skip behaviour — N=7 (1 bye) → 3 materialised matches
// ---------------------------------------------------------------------------

it('skips byes — round-1 brackets with participant_b_id NULL do not get a GameMatch', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 7);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    $round1 = $stage->brackets()->where('round_number', 1)->get();

    $materialised = $round1->whereNotNull('match_id');
    $byes = $round1->whereNull('participant_b_id');

    expect($materialised->count())->toBe(3); // 4 round-1 brackets - 1 bye = 3 matches
    expect($byes->count())->toBe(1);

    // Confirm the bye bracket has match_id=null (no GameMatch spawned for byes).
    /** @var TournamentBracket $byeBracket */
    $byeBracket = $byes->first();
    expect($byeBracket->match_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Idempotency (Pitfall 4) — second call returns same match_ids
// ---------------------------------------------------------------------------

it('is idempotent — calling materialiseFirstRound twice yields the same match_ids', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);
    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    $firstMatchIds = $stage->brackets()->where('round_number', 1)->orderBy('position')->pluck('match_id')->all();

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);
    /** @var TournamentStage $stage2 */
    $stage2 = $tournament->stages()->first();
    $secondMatchIds = $stage2->brackets()->where('round_number', 1)->orderBy('position')->pluck('match_id')->all();

    expect($secondMatchIds)->toBe($firstMatchIds);
    // The match table should only have 2 rows (4 participants → 2 round-1 brackets).
    expect(GameMatch::query()->count())->toBe(2);
});

it('per-bracket materialiseFor() is idempotent — second call returns the same GameMatch instance', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    /** @var TournamentBracket $bracket */
    $bracket = $stage->brackets()->where('round_number', 1)->where('position', 1)->firstOrFail();

    $first = app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament);
    $second = app(BracketMatchMaterialiserService::class)->materialiseFor($bracket->fresh(), $tournament);

    expect($second->id)->toBe($first->id);
    expect(GameMatch::query()->count())->toBe(1); // exactly one match for this single bracket
});

// ---------------------------------------------------------------------------
// A10 LOCKED — host_clan_id is NULL on every bracket-spawned GameMatch
// ---------------------------------------------------------------------------

it('sets host_clan_id to NULL on every bracket-spawned GameMatch (A10 LOCKED)', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 8);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    GameMatch::query()->get()->each(function (GameMatch $match): void {
        expect($match->host_clan_id)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Field inheritance from Tournament
// ---------------------------------------------------------------------------

it('inherits organiser_user_id, game_match_type_id, is_public from the Tournament', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    GameMatch::query()->get()->each(function (GameMatch $match) use ($tournament): void {
        expect($match->organiser_user_id)->toBe($tournament->organiser_user_id);
        expect($match->game_match_type_id)->toBe($tournament->default_game_match_type_id);
        expect($match->is_public)->toBe($tournament->is_public);
    });
});

it('spawns bracket GameMatch in status=open (signups open automatically)', function (): void {
    $tournament = makeTournamentWithMatchType();
    makeMaterialiserParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    GameMatch::query()->get()->each(function (GameMatch $match): void {
        expect($match->status)->toBe('open');
    });
});

// ---------------------------------------------------------------------------
// Slot grid spawn (Phase 4 MatchSlotMaterialiserService integration)
// ---------------------------------------------------------------------------

it('spawns the slot grid via Phase 4 MatchSlotMaterialiserService (slot rows present per match)', function (): void {
    // capacity=2 → each spawned GameMatch has 2 MatchSlot rows.
    $tournament = makeTournamentWithMatchType(capacity: 2);
    makeMaterialiserParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    /** @var TournamentStage $stage */
    $stage = $tournament->stages()->first();
    $round1 = $stage->brackets()->where('round_number', 1)->get();
    foreach ($round1 as $bracket) {
        expect(MatchSlot::query()->where('match_id', $bracket->match_id)->count())->toBe(2);
    }
});

// ---------------------------------------------------------------------------
// Negative path — tournament without default_game_match_type_id
// ---------------------------------------------------------------------------

it('throws RuntimeException when materialising a bracket whose tournament has no default_game_match_type_id', function (): void {
    $tournament = Tournament::factory()
        ->ofFormat('single_elimination')
        ->inStatus('seeded')
        ->create(['default_game_match_type_id' => null]);
    $stage = TournamentStage::factory()->for($tournament)->create();
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

    expect(fn () => app(BracketMatchMaterialiserService::class)->materialiseFor($bracket, $tournament))
        ->toThrow(RuntimeException::class);
});
