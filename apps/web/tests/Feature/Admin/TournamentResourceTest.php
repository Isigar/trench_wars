<?php

declare(strict_types=1);

use App\Filament\Resources\TournamentResource;
use App\Filament\Resources\TournamentResource\Pages\CreateTournament;
use App\Filament\Resources\TournamentResource\Pages\EditTournament;
use App\Filament\Resources\TournamentResource\Pages\ListTournaments;
use App\Filament\Resources\TournamentResource\RelationManagers\BracketsRelationManager;
use App\Filament\Resources\TournamentResource\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\TournamentResource\RelationManagers\StagesRelationManager;
use App\Filament\Resources\TournamentResource\RelationManagers\StandingsRelationManager;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Source: 06-11-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers:
|   1. TournamentResource registered at /admin/tournaments (admin → 200)
|   2. ListTournaments mounts via Livewire and renders the records table
|   3. CreateTournament / EditTournament pages mount cleanly
|   4. 4 RelationManagers (Participants/Stages/Brackets/Standings) mount on Edit
|   5. Phase 1 admin-access gate inheritance (non-admin → 403) — A8 LOCKED inline
|   6. Resource navigation metadata: group='Tournaments', sort=30
|
| Pattern: Phase 4 plan 04-12 MatchResourcePresentTest verbatim.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// HTTP smoke — resource registered at /admin/tournaments
// -----------------------------------------------------------------------------

it('registers TournamentResource at /admin/tournaments for admin user', function (): void {
    $this->get('/admin/tournaments')->assertStatus(200);
});

it('CreateTournament page is reachable at /admin/tournaments/create', function (): void {
    $this->get('/admin/tournaments/create')->assertStatus(200);
});

// -----------------------------------------------------------------------------
// Livewire mount + table records
// -----------------------------------------------------------------------------

it('ListTournaments mounts and renders at least 3 tournament rows', function (): void {
    $tournaments = Tournament::factory()->count(3)->create();

    Livewire::test(ListTournaments::class)
        ->assertOk()
        ->assertCanSeeTableRecords($tournaments);
});

it('CreateTournament page mounts (Livewire panel context)', function (): void {
    Livewire::test(CreateTournament::class)->assertOk();
});

it('EditTournament page mounts for an existing tournament', function (): void {
    $tournament = Tournament::factory()->create();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertOk();
});

// -----------------------------------------------------------------------------
// 4 RelationManagers mount cleanly (Pitfall 3 $relationship typo guard)
// -----------------------------------------------------------------------------

it('ParticipantsRelationManager mounts on a TournamentResource edit page', function (): void {
    $tournament = Tournament::factory()->create();

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertOk();
});

it('StagesRelationManager mounts on a TournamentResource edit page', function (): void {
    $tournament = Tournament::factory()->create();

    Livewire::test(StagesRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertOk();
});

it('BracketsRelationManager mounts on a TournamentResource edit page', function (): void {
    $tournament = Tournament::factory()->create();

    Livewire::test(BracketsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertOk();
});

it('StandingsRelationManager mounts on a TournamentResource edit page', function (): void {
    $tournament = Tournament::factory()->create();

    Livewire::test(StandingsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertOk();
});

// -----------------------------------------------------------------------------
// Resource metadata — navigation group + sort + i18n labels
// -----------------------------------------------------------------------------

it('TournamentResource declares navigationGroup=Tournaments and navigationSort=30', function (): void {
    expect(TournamentResource::getNavigationSort())->toBe(30)
        ->and(TournamentResource::getNavigationGroup())->toBe('Tournaments')
        ->and(TournamentResource::getModelLabel())->toBe('Tournament')
        ->and(TournamentResource::getPluralModelLabel())->toBe('Tournaments');
});

it('TournamentResource getPages returns the 3 LOCKED page registrations', function (): void {
    $pages = TournamentResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('TournamentResource getRelations returns the 4 LOCKED RelationManagers', function (): void {
    $relations = TournamentResource::getRelations();

    expect($relations)->toContain(ParticipantsRelationManager::class)
        ->and($relations)->toContain(StagesRelationManager::class)
        ->and($relations)->toContain(BracketsRelationManager::class)
        ->and($relations)->toContain(StandingsRelationManager::class);
});

// -----------------------------------------------------------------------------
// Phase 1 admin-access gate inheritance — A8 LOCKED (admin-only)
// -----------------------------------------------------------------------------

it('non-admin user gets 403 on /admin/tournaments', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/tournaments')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/tournaments/create', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/tournaments/create')->assertStatus(403);
});
