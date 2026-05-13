<?php

declare(strict_types=1);

use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentStage;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 06-01.
*/

it('creates a valid stage via factory', function (): void {
    $stage = TournamentStage::factory()->create();

    expect($stage->exists)->toBeTrue();
    expect($stage->type)->toBe('elim');
    expect($stage->ordinal)->toBe(1);
});

it('casts ordinal to int, settings to array', function (): void {
    $stage = TournamentStage::factory()->create([
        'ordinal' => 4,
        'settings' => ['grand_final_reset' => true],
    ]);

    $reloaded = $stage->fresh();
    expect($reloaded?->ordinal)->toBe(4);
    expect($reloaded?->settings)->toBe(['grand_final_reset' => true]);
});

it('enforces tournament_stages_type_check at the DB layer', function (): void {
    expect(fn () => TournamentStage::factory()->create(['type' => 'mystery_stage']))
        ->toThrow(QueryException::class);
});

it('accepts each valid stage type enum value', function (): void {
    $valid = ['group', 'elim', 'swiss-round', 'winners-bracket', 'losers-bracket', 'grand-final'];

    $tournament = Tournament::factory()->create();
    foreach ($valid as $i => $type) {
        $stage = TournamentStage::factory()->for($tournament)->create([
            'type' => $type,
            'ordinal' => $i + 1,
        ]);
        expect($stage->type)->toBe($type);
    }
});

it('enforces composite UNIQUE(tournament_id, ordinal) at the DB layer', function (): void {
    $tournament = Tournament::factory()->create();
    TournamentStage::factory()->for($tournament)->create(['ordinal' => 1]);

    expect(fn () => TournamentStage::factory()->for($tournament)->create(['ordinal' => 1]))
        ->toThrow(QueryException::class);
});

it('allows ordinal=1 in different tournaments', function (): void {
    $tournamentA = Tournament::factory()->create();
    $tournamentB = Tournament::factory()->create();

    TournamentStage::factory()->for($tournamentA)->create(['ordinal' => 1]);
    TournamentStage::factory()->for($tournamentB)->create(['ordinal' => 1]);

    expect(TournamentStage::where('ordinal', 1)->count())->toBe(2);
});

it('exposes tournament BelongsTo + brackets HasMany ordered by round_number, position', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create();

    $b22 = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 2, 'position' => 2]);
    $b11 = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 1]);
    $b21 = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 2, 'position' => 1]);
    $b12 = TournamentBracket::factory()->for($stage, 'stage')->create(['round_number' => 1, 'position' => 2]);

    expect($stage->tournament?->id)->toBe($tournament->id);
    expect($stage->fresh()?->brackets->pluck('id')->all())
        ->toBe([$b11->id, $b12->id, $b21->id, $b22->id]);
});

it('logs activity on create (D-012)', function (): void {
    $stage = TournamentStage::factory()->create();

    $exists = Activity::query()
        ->where('subject_type', TournamentStage::class)
        ->where('subject_id', $stage->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});
