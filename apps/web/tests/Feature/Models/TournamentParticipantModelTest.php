<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 06-01.
*/

it('creates a valid participant via factory', function (): void {
    $participant = TournamentParticipant::factory()->create();

    expect($participant->exists)->toBeTrue();
    expect($participant->status)->toBe('registered');
    expect($participant->registered_at)->not->toBeNull();
});

it('casts seed + placement to int, registered_at to datetime', function (): void {
    $participant = TournamentParticipant::factory()->create([
        'seed' => 7,
        'placement' => 3,
        'registered_at' => '2026-06-01 12:00:00',
    ]);

    $reloaded = $participant->fresh();
    expect($reloaded?->seed)->toBe(7);
    expect($reloaded?->placement)->toBe(3);
    expect($reloaded?->registered_at)->toBeInstanceOf(Carbon::class);
});

it('enforces tournament_participants_status_check at the DB layer', function (): void {
    expect(fn () => TournamentParticipant::factory()->create(['status' => 'totally-fake']))
        ->toThrow(QueryException::class);
});

it('accepts each valid status enum value', function (): void {
    foreach (['registered', 'active', 'withdrawn', 'disqualified'] as $status) {
        $participant = TournamentParticipant::factory()->create(['status' => $status]);
        expect($participant->status)->toBe($status);
    }
});

it('enforces composite UNIQUE(tournament_id, clan_id) at the DB layer', function (): void {
    $tournament = Tournament::factory()->create();
    $clan = Clan::factory()->create();
    TournamentParticipant::factory()->for($tournament)->for($clan)->create();

    expect(fn () => TournamentParticipant::factory()->for($tournament)->for($clan)->create())
        ->toThrow(QueryException::class);
});

it('allows the same clan in different tournaments', function (): void {
    $clan = Clan::factory()->create();
    $tournamentA = Tournament::factory()->create();
    $tournamentB = Tournament::factory()->create();

    TournamentParticipant::factory()->for($tournamentA)->for($clan)->create();
    TournamentParticipant::factory()->for($tournamentB)->for($clan)->create();

    expect(TournamentParticipant::where('clan_id', $clan->id)->count())->toBe(2);
});

it('exposes tournament + clan BelongsTo relations', function (): void {
    $tournament = Tournament::factory()->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()->for($tournament)->for($clan)->create();

    expect($participant->tournament?->id)->toBe($tournament->id);
    expect($participant->clan?->id)->toBe($clan->id);
});

it('exposes bracketsAsA, bracketsAsB, bracketsAsWinner HasMany relations', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $participant = TournamentParticipant::factory()->for($tournament)->create();
    $other = TournamentParticipant::factory()->for($tournament)->create();

    $bracketA = TournamentBracket::factory()->for($stage, 'stage')->create([
        'participant_a_id' => $participant->id,
        'round_number' => 1,
        'position' => 1,
    ]);
    $bracketB = TournamentBracket::factory()->for($stage, 'stage')->create([
        'participant_b_id' => $participant->id,
        'round_number' => 1,
        'position' => 2,
    ]);
    $bracketW = TournamentBracket::factory()->for($stage, 'stage')->create([
        'winner_participant_id' => $participant->id,
        'participant_a_id' => $participant->id,
        'participant_b_id' => $other->id,
        'round_number' => 1,
        'position' => 3,
    ]);

    $reloaded = $participant->fresh();
    expect($reloaded?->bracketsAsA->pluck('id')->all())->toContain($bracketA->id);
    expect($reloaded?->bracketsAsB->pluck('id')->all())->toContain($bracketB->id);
    expect($reloaded?->bracketsAsWinner->pluck('id')->all())->toContain($bracketW->id);
});

it('logs activity on create (D-012)', function (): void {
    $participant = TournamentParticipant::factory()->create();

    $exists = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});
