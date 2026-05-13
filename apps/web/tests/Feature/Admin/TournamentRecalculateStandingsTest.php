<?php

declare(strict_types=1);

use App\Filament\Resources\TournamentResource\Pages\EditTournament;
use App\Filament\Resources\TournamentResource\RelationManagers\StandingsRelationManager;
use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Source: 06-11-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers SC-5 recalculate_standings admin action:
|   - Visible when tournament.status is running|completed
|   - Hidden when tournament.status is draft|registering|seeded|cancelled
|   - On call: standings table for this tournament is wiped + recomputed
|     (StandingsCalculatorService::recalculate())
|
| Two surfaces exposed:
|   1. EditTournament HeaderActions ::recalculate_standings
|   2. StandingsRelationManager headerAction ::recalculate
| Both call StandingsCalculatorService::recalculate($tournament).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Build a no-bracket tournament with N active participants. The calculator
 * short-circuits for single_elimination when no 'elim' stage exists; running
 * recalculate on this fixture is safe — standings remain empty (and any
 * pre-existing stale rows get wiped).
 */
function makeRunningTournament(int $count = 4): Tournament
{
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('running')->create();
    $clans = Clan::factory()->count($count)->create();
    foreach ($clans as $i => $clan) {
        TournamentParticipant::factory()
            ->for($tournament)
            ->for($clan)
            ->create(['status' => 'active', 'seed' => $i + 1]);
    }

    return $tournament;
}

// -----------------------------------------------------------------------------
// recalculate_standings on EditTournament — wipes existing standings rows
// -----------------------------------------------------------------------------

it('recalculate_standings header action wipes pre-existing standings rows', function (): void {
    $tournament = makeRunningTournament(4);

    // Pre-create an elim stage so the SingleEliminationStandingsCalculator
    // walks the participant set instead of short-circuiting.
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);
    $participant = $tournament->participants()->first();

    // Seed a stale standings row with a sentinel `wins=99` that the wipe-and-
    // recompute strategy must overwrite.
    TournamentStanding::factory()->create([
        'tournament_id' => $tournament->id,
        'tournament_stage_id' => $stage->id,
        'participant_id' => $participant->id,
        'wins' => 99,           // sentinel stale value
        'rank' => 7,
    ]);

    expect(TournamentStanding::where('tournament_id', $tournament->id)->count())->toBe(1);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('recalculate_standings');

    // The wipe-and-recompute strategy deletes the stale row and writes one
    // fresh row per active participant (count=4 here, all with wins=0 since no
    // brackets/match-results exist yet).
    $fresh = TournamentStanding::where('tournament_id', $tournament->id)->get();
    expect($fresh)->toHaveCount(4);
    expect($fresh->pluck('wins')->unique()->all())->toBe([0]);  // sentinel wiped
});

it('recalculate_standings header action is visible for running tournaments', function (): void {
    $tournament = makeRunningTournament(4);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionVisible('recalculate_standings');
});

it('recalculate_standings header action is hidden for draft / seeded tournaments', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionHidden('recalculate_standings');

    $tournament2 = Tournament::factory()->inStatus('seeded')->create();
    Livewire::test(EditTournament::class, ['record' => $tournament2->getRouteKey()])
        ->assertActionHidden('recalculate_standings');
});

// -----------------------------------------------------------------------------
// StandingsRelationManager recalculate header action (same underlying service)
// -----------------------------------------------------------------------------

it('StandingsRelationManager recalculate header action wipes stale standings', function (): void {
    $tournament = makeRunningTournament(4);

    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);
    $participant = $tournament->participants()->first();
    TournamentStanding::factory()->create([
        'tournament_id' => $tournament->id,
        'tournament_stage_id' => $stage->id,
        'participant_id' => $participant->id,
        'wins' => 42,           // sentinel stale value
    ]);

    Livewire::test(StandingsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->callTableAction('recalculate');

    // Stale row wiped; calculator re-emits 4 fresh rows (one per active participant).
    $fresh = TournamentStanding::where('tournament_id', $tournament->id)->get();
    expect($fresh)->toHaveCount(4);
    expect($fresh->pluck('wins')->unique()->all())->toBe([0]);  // stale 42 wiped
});
