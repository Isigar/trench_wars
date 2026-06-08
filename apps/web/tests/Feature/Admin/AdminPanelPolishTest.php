<?php

declare(strict_types=1);

/*
| Admin-panel polish pass (2026-06-07).
|
| Covers the user-facing fixes that the existing *PresentTest files do not assert:
|   1. Resource pages render the auto-derived descriptive subheading under the
|      title (App\Filament\Base\* + HasResourceSubheading wiring).
|   2. EventResource View page renders its infolist (was blank — no infolist()).
|   3. DiscordOutboundMessageResource View page renders its infolist (was blank).
|
| The pre-existing DiscordOutboundMessageResourcePresentTest only asserts the View
| page mounts (assertOk) — which passed even while the body was blank. These tests
| assert the page actually SHOWS record data, so a regression to the empty-form
| fallback fails here.
|
| Panel/permission bootstrap mirrors DiscordOutboundMessageResourcePresentTest.
*/

use App\Filament\Base\EditRecord;
use App\Filament\Base\ListRecords;
use App\Filament\Resources\DiscordOutboundMessageResource\Pages\ViewDiscordOutboundMessage;
use App\Filament\Resources\EventResource\Pages\ViewEvent;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\DiscordOutboundMessage;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// Subheading wiring — every resource page inherits the project base classes which
// render admin.subheadings.<slug> under the title.
// -----------------------------------------------------------------------------

it('renders the resource subheading under the page title', function (): void {
    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertSee('Discord-authenticated accounts');
});

it('resource pages extend the project base page classes (subheading trait source)', function (): void {
    expect(is_subclass_of(ListUsers::class, ListRecords::class))->toBeTrue()
        ->and(is_subclass_of(EditUser::class, EditRecord::class))->toBeTrue();
});

it('the user subheading key resolves for the snake_case-derived slug', function (): void {
    // UserResource -> "user" — the derivation HasResourceSubheading relies on.
    expect(Lang::has('admin.subheadings.user'))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Item D — EventResource View page renders an infolist (was blank).
// -----------------------------------------------------------------------------

it('Event view page renders the infolist with record data', function (): void {
    // MatchObserver auto-creates exactly one Event per GameMatch (events_one_per_owner),
    // so we view that observer-managed row rather than Event::factory() — which would
    // collide on the unique index.
    $match = GameMatch::factory()->create();
    $event = Event::query()->where('eventable_id', $match->getKey())->firstOrFail();

    Livewire::test(ViewEvent::class, ['record' => $event->getKey()])
        ->assertOk()
        ->assertSee('Starts at')   // field label — proves the infolist rendered (was blank)
        ->assertSee('Public');     // is_public IconEntry label
});

// -----------------------------------------------------------------------------
// Item E — DiscordOutboundMessage View page renders an infolist (was blank).
// -----------------------------------------------------------------------------

it('DiscordOutboundMessage view page renders the infolist with record data', function (): void {
    $row = DiscordOutboundMessage::factory()->create([
        'channel_id' => '123456789012345678',
        'message_type' => 'match_announce',
    ]);

    Livewire::test(ViewDiscordOutboundMessage::class, ['record' => $row->getKey()])
        ->assertOk()
        ->assertSee('123456789012345678')   // channel_id TextEntry
        ->assertSee('Payload');             // a field label proves the infolist rendered
});
