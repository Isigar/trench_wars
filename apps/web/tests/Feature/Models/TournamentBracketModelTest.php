<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 06-01.
|
| Covers two DB-layer defences from plan 06-02 in addition to standard model assertions:
|   - Pitfall 4  : tournament_brackets_match_id_unique  (partial UNIQUE WHERE NOT NULL)
|   - Pitfall 11 : tournament_brackets_no_self_advance  (CHECK constraint, both pointers)
|
| Self-advance CHECKs are exercised via raw DB::table()->update() because the factory's
| default state always starts with advances_to_bracket_id=NULL — no application-layer
| save() path can trigger the CHECK on a freshly-created bracket.
*/

it('creates a valid bracket via factory', function (): void {
    $bracket = TournamentBracket::factory()->create();

    expect($bracket->exists)->toBeTrue();
    expect($bracket->round_number)->toBe(1);
    expect($bracket->position)->toBe(1);
    expect($bracket->match_id)->toBeNull();
    expect($bracket->advances_to_bracket_id)->toBeNull();
});

it('casts round_number + position to int', function (): void {
    $bracket = TournamentBracket::factory()->create([
        'round_number' => 5,
        'position' => 9,
    ]);

    $reloaded = $bracket->fresh();
    expect($reloaded?->round_number)->toBe(5);
    expect($reloaded?->position)->toBe(9);
});

it('exposes stage, participantA/B/winner, match, advancesTo, loserAdvancesTo relations', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $a = TournamentParticipant::factory()->for($tournament)->create();
    $b = TournamentParticipant::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();
    $next = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 2, 'position' => 1]);
    $consolation = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 2, 'position' => 2]);

    $bracket = TournamentBracket::factory()->for($stage, 'stage')->create([
        'round_number' => 1,
        'position' => 1,
        'participant_a_id' => $a->id,
        'participant_b_id' => $b->id,
        'winner_participant_id' => $a->id,
        'match_id' => $match->id,
        'advances_to_bracket_id' => $next->id,
        'loser_advances_to_bracket_id' => $consolation->id,
    ]);

    expect($bracket->stage?->id)->toBe($stage->id);
    expect($bracket->participantA?->id)->toBe($a->id);
    expect($bracket->participantB?->id)->toBe($b->id);
    expect($bracket->winnerParticipant?->id)->toBe($a->id);
    expect($bracket->match?->id)->toBe($match->id);
    expect($bracket->advancesTo?->id)->toBe($next->id);
    expect($bracket->loserAdvancesTo?->id)->toBe($consolation->id);
});

it('D-04-03-B: match() relation uses match_id FK column', function (): void {
    $bracket = new TournamentBracket;
    $relation = $bracket->match();

    expect($relation->getForeignKeyName())->toBe('match_id');
    expect($relation->getRelated()::class)->toBe(GameMatch::class);
});

it('D-04-03-B: advancesTo + loserAdvancesTo use the right self-FK columns', function (): void {
    $bracket = new TournamentBracket;

    expect($bracket->advancesTo()->getForeignKeyName())->toBe('advances_to_bracket_id');
    expect($bracket->loserAdvancesTo()->getForeignKeyName())->toBe('loser_advances_to_bracket_id');
});

it('rejects advances_to_bracket_id = self via DB CHECK (Pitfall 11)', function (): void {
    $bracket = TournamentBracket::factory()->create();

    expect(
        fn () => DB::table('tournament_brackets')
            ->where('id', $bracket->id)
            ->update(['advances_to_bracket_id' => $bracket->id])
    )->toThrow(QueryException::class);
});

it('rejects loser_advances_to_bracket_id = self via DB CHECK (Pitfall 11)', function (): void {
    $bracket = TournamentBracket::factory()->create();

    expect(
        fn () => DB::table('tournament_brackets')
            ->where('id', $bracket->id)
            ->update(['loser_advances_to_bracket_id' => $bracket->id])
    )->toThrow(QueryException::class);
});

it('rejects duplicate match_id via partial UNIQUE (Pitfall 4)', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();

    TournamentBracket::factory()->for($stage, 'stage')->create([
        'round_number' => 1,
        'position' => 1,
        'match_id' => $match->id,
    ]);

    expect(fn () => TournamentBracket::factory()->for($stage, 'stage')->create([
        'round_number' => 1,
        'position' => 2,
        'match_id' => $match->id,
    ]))->toThrow(QueryException::class);
});

it('allows multiple brackets with match_id IS NULL (partial UNIQUE permits NULLs)', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();

    TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 1]);
    TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 2]);
    TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 3]);

    expect(TournamentBracket::whereNull('match_id')->where('tournament_stage_id', $stage->id)->count())
        ->toBe(3);
});

it('enforces composite UNIQUE(tournament_stage_id, round_number, position)', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();

    TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 1]);

    expect(fn () => TournamentBracket::factory()->for($stage, 'stage')->create([
        'round_number' => 1,
        'position' => 1,
    ]))->toThrow(QueryException::class);
});

it('logs activity on create (D-012)', function (): void {
    $bracket = TournamentBracket::factory()->create();

    $exists = Activity::query()
        ->where('subject_type', TournamentBracket::class)
        ->where('subject_id', $bracket->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});
