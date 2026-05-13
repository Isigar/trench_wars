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
| Covers SC-5 withdraw row action on ParticipantsRelationManager:
|   - Visible when participant.status is registered|active
|   - On call: participant.status → 'withdrawn'
|   - On call: activity_log row written with properties.reason='withdraw' AND
|     properties.previous_status=<original status>
|
| A5 LOCKED inline: identical forward semantics to forfeit — only the status
| string + audit `reason` differ.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('withdraw row action flips participant.status to withdrawn', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'active', 'seed' => 1]);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->callTableAction('withdraw', $participant);

    expect($participant->fresh()->status)->toBe('withdrawn');
});

it('withdraw row action writes an activity_log entry with reason=withdraw + previous_status', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'registered']);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->callTableAction('withdraw', $participant);

    $log = Activity::query()
        ->where('subject_type', TournamentParticipant::class)
        ->where('subject_id', $participant->id)
        ->where('description', 'Participant withdrew')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('reason'))->toBe('withdraw');
    expect($log->properties->get('previous_status'))->toBe('registered');
});

it('withdraw row action is hidden for already-disqualified participants', function (): void {
    $tournament = Tournament::factory()->inStatus('running')->create();
    $clan = Clan::factory()->create();
    $participant = TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['status' => 'disqualified']);

    Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $tournament,
        'pageClass' => EditTournament::class,
    ])->assertTableActionHidden('withdraw', $participant);
});
