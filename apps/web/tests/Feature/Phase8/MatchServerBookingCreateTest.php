<?php

declare(strict_types=1);

/*
| Booking creation path (MatchResource → BookingsRelationManager).
|
| Closes the HIGH reachability gap where nothing in the app ever created a
| match_server_bookings row, so the automatic CRCON capture pipeline
| (REQ-goal-rcon-history) was unreachable in production — the rcon-worker polls
| BookingScheduleController::dueNow, but no booking ever existed to poll.
|
| These tests prove: an admin can create a booking through the relation manager,
| the created row is what the worker's poll query (active + dueWithin) returns,
| and the no-overlap EXCLUDE constraint is surfaced gracefully (no 500).
*/

use App\Filament\Resources\MatchResource;
use App\Filament\Resources\MatchResource\Pages\EditMatch;
use App\Filament\Resources\MatchResource\RelationManagers\BookingsRelationManager;
use App\Models\GameMatch;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    // Booking management is an RCON-ops privilege — the actor needs manage-rcon
    // (the gate BookingsRelationManager enforces, mirroring MatchServerResource).
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->admin->givePermissionTo('manage-rcon');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers the Bookings relation manager on MatchResource', function (): void {
    expect(MatchResource::getRelations())
        ->toContain(BookingsRelationManager::class);
});

it('hides the Bookings relation manager from an admin WITHOUT manage-rcon (T-08-09-03)', function (): void {
    $match = GameMatch::factory()->create();

    // The manage-rcon admin (from beforeEach) CAN see it.
    expect(BookingsRelationManager::canViewForRecord($match, EditMatch::class))->toBeTrue();

    // A non-RCON admin (cms-editor scope: admin-access only) CANNOT — so the
    // create/delete actions on CRCON bookings are unreachable for them.
    $cmsAdmin = User::factory()->create();
    $cmsAdmin->givePermissionTo('admin-access');
    $this->actingAs($cmsAdmin);
    expect(BookingsRelationManager::canViewForRecord($match, EditMatch::class))->toBeFalse();
});

it('admin creates a booking through the relation manager and the worker poll picks it up', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played', 'scheduled_at' => now()]);
    $server = MatchServer::factory()->create();

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->callTableAction('create', data: [
        'server_id' => $server->id,
        'reserved_from' => now()->subMinute()->toDateTimeString(),
        'reserved_to' => now()->addHours(2)->toDateTimeString(),
        'status' => 'active',
    ])->assertHasNoTableActionErrors();

    $booking = MatchServerBooking::where('match_id', $match->id)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->server_id)->toBe($server->id)
        ->and($booking->status)->toBe('active');

    // End-to-end reachability: the worker's dueNow query (active + window covers
    // now) now returns the booking that previously could never exist.
    $due = MatchServerBooking::query()
        ->active()
        ->dueWithin(now()->subMinutes(5), now()->addMinutes(5))
        ->pluck('id');
    expect($due)->toContain($booking->id);
});

it('surfaces the no-overlap constraint instead of a 500 when double-booking a server', function (): void {
    $match = GameMatch::factory()->create(['status' => 'played']);
    $server = MatchServer::factory()->create();

    // First active booking occupies the window.
    MatchServerBooking::factory()->create([
        'match_id' => $match->id,
        'server_id' => $server->id,
        'reserved_from' => now(),
        'reserved_to' => now()->addHours(2),
        'status' => 'active',
    ]);

    // A second active booking of the SAME server with an overlapping window is
    // rejected by the EXCLUDE constraint; the action halts (no new row, no 500).
    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->callTableAction('create', data: [
        'server_id' => $server->id,
        'reserved_from' => now()->addMinutes(30)->toDateTimeString(),
        'reserved_to' => now()->addHours(3)->toDateTimeString(),
        'status' => 'active',
    ]);

    expect(MatchServerBooking::where('server_id', $server->id)->count())->toBe(1);
});
