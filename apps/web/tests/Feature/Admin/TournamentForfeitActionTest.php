<?php

declare(strict_types=1);

use App\Filament\Resources\TournamentResource\Pages\EditTournament;
use App\Filament\Resources\TournamentResource\RelationManagers\ParticipantsRelationManager;
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
| Covers SC-5 forfeit row action on ParticipantsRelationManager:
|   - Visible when participant.status is registered|active
|   - On call: participant.status → 'disqualified'
|   - On call: activity_log row written with properties.reason='forfeit' AND
|     properties.previous_status=<original status>
|
| A5 LOCKED inline (consistent with plan 06-05 + 06-09 + 06-11): forfeit + withdraw
| have IDENTICAL forward semantics; only the status string differs (disqualified
| vs withdrawn). Both write activity_log rows with their distinct `reason`.
|
| Pattern: callTableAction('forfeit', $participant) — Filament v3.3 table
| action helper (vendor/filament/tables/src/Testing/TestsActions.php).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('forfeit row action flips participant.status to disqualified', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'active', 'seed' => 1]);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->callTableAction('forfeit', $participant);

    expect($participant->fresh()->status)->toBe('disqualified');
});

it('forfeit row action writes an activity_log entry with reason=forfeit + previous_status', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'active', 'seed' => 1]);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->callTableAction('forfeit', $participant);

    $log = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('description', 'Participant forfeited')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('reason'))->toBe('forfeit');
    expect($log->properties->get('previous_status'))->toBe('active');
});

it('forfeit row action is hidden for already-withdrawn or already-disqualified participants', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'withdrawn']);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertTableActionHidden('forfeit', $participant);
});
