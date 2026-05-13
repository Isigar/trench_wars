<?php

declare(strict_types=1);

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 06-01.
*/

it('creates a valid standing via factory', function (): void {
    $standing = TournamentStanding::factory()->create();

    expect($standing->exists)->toBeTrue();
    expect($standing->wins)->toBe(0);
    expect($standing->losses)->toBe(0);
    expect($standing->draws)->toBe(0);
    expect($standing->rank)->toBeNull();
});

it('casts wins/losses/draws/rank to int, points/tiebreak_score to decimal:2 string', function (): void {
    $standing = TournamentStanding::factory()->create([
        'wins' => 4,
        'losses' => 1,
        'draws' => 2,
        'points' => 9.5,
        'tiebreak_score' => 13.25,
        'rank' => 1,
    ]);

    $reloaded = $standing->fresh();
    expect($reloaded?->wins)->toBe(4);
    expect($reloaded?->losses)->toBe(1);
    expect($reloaded?->draws)->toBe(2);
    expect($reloaded?->rank)->toBe(1);
    // decimal:2 cast returns a string like "9.50".
    expect((float) $reloaded?->points)->toBe(9.5);
    expect((float) $reloaded?->tiebreak_score)->toBe(13.25);
});

it('exposes tournament, stage, participant BelongsTo relations', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $participant = TournamentParticipant::factory()->for($tournament)->create();

    $standing = TournamentStanding::factory()
        ->for($tournament)
        ->for($stage, 'stage')
        ->for($participant, 'participant')
        ->create();

    expect($standing->tournament?->id)->toBe($tournament->id);
    expect($standing->stage?->id)->toBe($stage->id);
    expect($standing->participant?->id)->toBe($participant->id);
});

it('enforces composite UNIQUE(tournament_stage_id, participant_id) at the DB layer', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $participant = TournamentParticipant::factory()->for($tournament)->create();

    TournamentStanding::factory()
        ->for($tournament)
        ->for($stage, 'stage')
        ->for($participant, 'participant')
        ->create();

    expect(
        fn () => TournamentStanding::factory()
            ->for($tournament)
            ->for($stage, 'stage')
            ->for($participant, 'participant')
            ->create()
    )->toThrow(QueryException::class);
});

it('allows the same participant in different stages of the same tournament', function (): void {
    $tournament = Tournament::factory()->create();
    $stageA = TournamentStage::factory()->for($tournament)->create(['ordinal' => 1, 'type' => 'group']);
    $stageB = TournamentStage::factory()->for($tournament)->create(['ordinal' => 2, 'type' => 'elim']);
    $participant = TournamentParticipant::factory()->for($tournament)->create();

    TournamentStanding::factory()
        ->for($tournament)->for($stageA, 'stage')->for($participant, 'participant')->create();
    TournamentStanding::factory()
        ->for($tournament)->for($stageB, 'stage')->for($participant, 'participant')->create();

    expect(TournamentStanding::where('participant_id', $participant->id)->count())->toBe(2);
});

it('logs activity on create (D-012)', function (): void {
    $standing = TournamentStanding::factory()->create();

    $exists = Activity::query()
        ->where('subject_type', TournamentStanding::class)
        ->where('subject_id', $standing->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});
