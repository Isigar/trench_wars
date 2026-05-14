<?php

declare(strict_types=1);

use App\Filament\Resources\MatchServerResource;
use App\Filament\Resources\MatchServerResource\Pages\CreateMatchServer;
use App\Filament\Resources\MatchServerResource\Pages\EditMatchServer;
use App\Filament\Resources\MatchServerResource\Pages\ListMatchServers;
use App\Filament\Resources\MatchServerResource\RelationManagers\BookingsRelationManager;
use App\Jobs\Rcon\TestMatchServerConnectionJob;
use App\Models\GameMatch;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

/*
| Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 1.
|
| Verifies the Phase 8 admin surface for MatchServer:
|   1. MatchServerResource exists in Filament's resource list (Filament::getResource(...)).
|   2. ListMatchServers page mounts via Livewire (panel-context smoke).
|   3. CreateMatchServer page renders with the expected form fields
|      (name, host, port_rcon, region, credentials_encrypted.api_token, is_active).
|   4. EditMatchServer page mounts on an existing server.
|   5. BookingsRelationManager mounts cleanly on the edit page (Pitfall 3 typo guard).
|   6. Resource is HIDDEN from users WITHOUT manage-rcon permission (canViewAny→false).
|   7. Resource is VISIBLE for users WITH manage-rcon permission (canViewAny→true).
|   8. Test Connection table action dispatches TestMatchServerConnectionJob (Bus::fake).
|
| Analog: tests/Feature/Admin/MatchResourcePresentTest.php +
|         tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->admin->givePermissionTo('manage-rcon');
    $this->actingAs($this->admin);

    // Filament v3.3 panel context bootstrap — Livewire tests don't traverse panel
    // middleware so Filament::getCurrentPanel() is null unless set explicitly.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// Case 1 — Resource registered + reachable in panel
// -----------------------------------------------------------------------------

it('MatchServerResource is registered with the admin panel', function (): void {
    $panel = Filament::getCurrentPanel();
    expect($panel)->not->toBeNull();
    expect($panel->getResources())->toContain(MatchServerResource::class);
});

// -----------------------------------------------------------------------------
// Case 2 — List page mounts in panel context
// -----------------------------------------------------------------------------

it('ListMatchServers page mounts for manage-rcon admin', function (): void {
    Livewire::test(ListMatchServers::class)->assertOk();
});

// -----------------------------------------------------------------------------
// Case 3 — Create page form has the expected fields
// -----------------------------------------------------------------------------

it('CreateMatchServer page renders the expected form fields', function (): void {
    Livewire::test(CreateMatchServer::class)
        ->assertOk()
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('host')
        ->assertFormFieldExists('port_rcon')
        ->assertFormFieldExists('region')
        ->assertFormFieldExists('credentials_encrypted.api_token')
        ->assertFormFieldExists('is_active');
});

// -----------------------------------------------------------------------------
// Case 4 — Edit page mounts
// -----------------------------------------------------------------------------

it('EditMatchServer page mounts on an existing server', function (): void {
    $server = MatchServer::factory()->create();

    Livewire::test(EditMatchServer::class, ['record' => $server->id])->assertOk();
});

// -----------------------------------------------------------------------------
// Case 5 — BookingsRelationManager mounts + renders bookings
// -----------------------------------------------------------------------------

it('BookingsRelationManager mounts on the server edit page (Pitfall 3 guard)', function (): void {
    $server = MatchServer::factory()->create();

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $server,
        'pageClass' => EditMatchServer::class,
    ])->assertOk();
});

it('BookingsRelationManager renders bookings rows for a server', function (): void {
    $server = MatchServer::factory()->create();
    $match = GameMatch::factory()->create();

    $bookings = collect([
        MatchServerBooking::factory()
            ->forMatch($match)
            ->onServer($server)
            ->create([
                'reserved_from' => now()->addHour(),
                'reserved_to' => now()->addHours(3),
            ]),
    ]);

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $server,
        'pageClass' => EditMatchServer::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($bookings);
});

// -----------------------------------------------------------------------------
// Case 6 — Resource HIDDEN for users without manage-rcon
// -----------------------------------------------------------------------------

it('MatchServerResource canViewAny returns false for users without manage-rcon', function (): void {
    $nonRcon = User::factory()->create();
    $nonRcon->givePermissionTo('admin-access');
    $this->actingAs($nonRcon);

    expect(MatchServerResource::canViewAny())->toBeFalse();
});

// -----------------------------------------------------------------------------
// Case 7 — Resource VISIBLE for users with manage-rcon
// -----------------------------------------------------------------------------

it('MatchServerResource canViewAny returns true for users with manage-rcon', function (): void {
    expect(MatchServerResource::canViewAny())->toBeTrue();
});

// -----------------------------------------------------------------------------
// Case 8 — Test Connection table action exists and dispatches the job
// -----------------------------------------------------------------------------

it('Test Connection table action dispatches TestMatchServerConnectionJob', function (): void {
    Bus::fake([TestMatchServerConnectionJob::class]);

    $server = MatchServer::factory()->create();

    Livewire::test(ListMatchServers::class)
        ->callTableAction('test', $server)
        ->assertHasNoTableActionErrors();

    Bus::assertDispatched(
        TestMatchServerConnectionJob::class,
        fn (TestMatchServerConnectionJob $job): bool => $job->matchServerId === $server->id,
    );
});
