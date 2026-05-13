<?php

declare(strict_types=1);

use App\Filament\Resources\TournamentResource\Pages\EditTournament;
use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 06-11-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers SC-2 (seed participants via Filament admin action): the seed
| HeaderAction on EditTournament:
|   - Visible only when status='registering' AND >=2 registered participants
|   - Form has Strategy Select (by_rank / random / manual)
|   - After ->callAction('seed', ['strategy' => ...]):
|       * participants seed=1..N (or unchanged for 'manual')
|       * participants status flipped 'registered' → 'active'
|       * tournament status flipped 'registering' → 'seeded'
|       * activity_log row written (Tournament seeded) + transition log row
|
| A8 LOCKED inline: non-admin user gets 403 on the resource entirely (no need to
| stub the action against a non-admin — admin-access gates at canAccessPanel).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Build a tournament in status='registering' with $count registered
 * participants (each with a fresh Clan).
 */
function makeRegisteringTournament(int $count = 4): Tournament
{
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $clans = Clan::factory()->count($count)->create();
    foreach ($clans as $clan) {
        TournamentParticipant::factory()
            ->for($tournament)
            ->for($clan)
            ->create(['status' => 'registered']);
    }

    return $tournament;
}

// -----------------------------------------------------------------------------
// Happy path — seed transitions to seeded + assigns seeds 1..N
// -----------------------------------------------------------------------------

it('seed action transitions tournament status from registering to seeded', function (): void {
    $tournament = makeRegisteringTournament(4);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('seed', ['strategy' => 'random']);

    expect($tournament->fresh()->status)->toBe('seeded');
});

it('seed action assigns 1..N seed values to all participants (random strategy)', function (): void {
    $tournament = makeRegisteringTournament(6);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('seed', ['strategy' => 'random']);

    $participants = $tournament->fresh()->participants;
    expect($participants->pluck('status')->unique()->all())->toBe(['active']);

    $seeds = $participants->pluck('seed')->sort()->values()->all();
    expect($seeds)->toBe([1, 2, 3, 4, 5, 6]);
});

it('seed action writes activity_log row with strategy + participant_count', function (): void {
    $tournament = makeRegisteringTournament(4);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('seed', ['strategy' => 'by_rank']);

    $log = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament seeded')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('strategy'))->toBe('by_rank');
    expect($log->properties->get('participant_count'))->toBe(4);
});

// -----------------------------------------------------------------------------
// Visibility — seed action hidden when prereqs not met
// -----------------------------------------------------------------------------

it('seed action is hidden when status is not registering (e.g. draft)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionHidden('seed');
});

it('seed action is hidden when fewer than 2 registered participants', function (): void {
    $tournament = makeRegisteringTournament(1);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionHidden('seed');
});
