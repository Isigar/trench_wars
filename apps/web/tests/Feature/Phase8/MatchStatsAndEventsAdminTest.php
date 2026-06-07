<?php

declare(strict_types=1);

/*
| Admin surfaces for per-player match stats + the normalised RCON event stream
| (REACH-06 + REACH-07).
|
| MatchPlayerStat documented an "admin manually corrects a stat" flow with no
| Filament surface; MatchEvent (the RCON event stream) had no admin view at all.
| These tests assert both relation managers are registered on MatchResource, an
| admin can correct a stat (firing the LogsActivity audit), and the events view
| is read-only.
*/

use App\Filament\Resources\MatchResource;
use App\Filament\Resources\MatchResource\Pages\EditMatch;
use App\Filament\Resources\MatchResource\RelationManagers\EventsRelationManager;
use App\Filament\Resources\MatchResource\RelationManagers\PlayerStatsRelationManager;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers the PlayerStats + Events relation managers on MatchResource', function (): void {
    expect(MatchResource::getRelations())
        ->toContain(PlayerStatsRelationManager::class)
        ->toContain(EventsRelationManager::class);
});

it('lets an admin correct a player stat, firing the LogsActivity audit', function (): void {
    $match = GameMatch::factory()->create();
    $player = Player::factory()->create();
    $stat = MatchPlayerStat::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'kills' => 5,
        'deaths' => 3,
        'team_kills' => 0,
        'score' => 500,
    ]);

    Livewire::test(PlayerStatsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->callTableAction('edit', $stat, data: [
        'kills' => 12,
        'deaths' => 3,
        'team_kills' => 1,
        'score' => 1200,
        'role_played' => 'rifleman',
    ])->assertHasNoTableActionErrors();

    $stat->refresh();
    expect($stat->kills)->toBe(12)
        ->and($stat->score)->toBe(1200)
        ->and($stat->team_kills)->toBe(1);

    // The MatchPlayerStat LogsActivity trait audits the correction (D-012 non-repudiation).
    $audit = Activity::query()
        ->where('subject_type', $stat->getMorphClass())
        ->where('subject_id', $stat->id)
        ->where('event', 'updated')
        ->first();
    expect($audit)->not->toBeNull();
});

it('lists a match\'s normalised CRCON events in the read-only events relation manager', function (): void {
    $match = GameMatch::factory()->create();
    MatchEvent::factory()->count(3)->create(['match_id' => $match->id]);
    // An event on a different match must not appear.
    MatchEvent::factory()->create(['match_id' => GameMatch::factory()->create()->id]);

    Livewire::test(EventsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords($match->matchEvents()->get());
});
