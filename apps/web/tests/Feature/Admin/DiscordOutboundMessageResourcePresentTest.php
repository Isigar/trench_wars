<?php

declare(strict_types=1);

use App\Filament\Resources\DiscordOutboundMessageResource;
use App\Filament\Resources\DiscordOutboundMessageResource\Pages\ListDiscordOutboundMessages;
use App\Filament\Resources\DiscordOutboundMessageResource\Pages\ViewDiscordOutboundMessage;
use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 05-07-PLAN.md task 3.
|
| Verifies the Phase 5 admin surface:
|   1. DiscordOutboundMessageResource registered + reachable at /admin/discord-outbound-messages
|   2. ListDiscordOutboundMessages page mounts (Livewire panel context)
|   3. ViewDiscordOutboundMessage page mounts on a specific record
|   4. Create page is NOT registered (T-05-07-01 read-only contract)
|   5. Edit page is NOT registered (T-05-07-01)
|   6. Retry action is visible on failed rows, hidden on others
|   7. Retry action flips status=failed → pending + zeros attempts +
|      clears last_error/backoff_until + writes activity_log retry event
|   8. Non-admin user gets 403 (Phase 1 admin-access gate inheritance)
|
| Analog: tests/Feature/Admin/MatchResourcePresentTest.php (Phase 4 plan 04-09)
| and tests/Feature/Admin/GameResourcesPresentTest.php (Phase 3 plan 03-08).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    // Filament v3.3 panel context bootstrap — Livewire component tests don't go
    // through the panel middleware so Filament::getCurrentPanel() is null without
    // this hint. Accepts the resolved Panel object (NOT a string ID; v4-only).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// Resource registered + reachable
// -----------------------------------------------------------------------------

it('registers DiscordOutboundMessageResource at /admin/discord-outbound-messages for admin user', function (): void {
    $this->get('/admin/discord-outbound-messages')->assertStatus(200);
});

it('list page mounts for admin (Livewire panel context)', function (): void {
    Livewire::test(ListDiscordOutboundMessages::class)->assertOk();
});

it('view page mounts on /admin/discord-outbound-messages/{record}', function (): void {
    $row = DiscordOutboundMessage::factory()->create();

    Livewire::test(ViewDiscordOutboundMessage::class, ['record' => $row->id])
        ->assertOk();
});

it('view page retry header action is visible on failed rows and re-queues + audits', function (): void {
    $failed = DiscordOutboundMessage::factory()->create([
        'status' => 'failed',
        'attempts' => 3,
        'last_error' => 'Cannot send messages to this user',
        'backoff_until' => now()->addMinutes(5),
    ]);

    Livewire::test(ViewDiscordOutboundMessage::class, ['record' => $failed->id])
        ->assertActionVisible('retry')
        ->callAction('retry')
        ->assertHasNoActionErrors();

    $fresh = $failed->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe('pending');
    expect($fresh->attempts)->toBe(0);
    expect($fresh->last_error)->toBeNull();
    expect($fresh->backoff_until)->toBeNull();

    $retry = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $failed->id)
        ->where('event', 'retry')
        ->first();
    expect($retry)->not->toBeNull();
    expect($retry->causer_id)->toBe($this->admin->id);
});

it('view page retry header action is hidden on non-failed rows', function (): void {
    $sent = DiscordOutboundMessage::factory()->sent()->create();

    Livewire::test(ViewDiscordOutboundMessage::class, ['record' => $sent->id])
        ->assertActionHidden('retry');
});

// -----------------------------------------------------------------------------
// READ-ONLY contract — T-05-07-01 mitigation
// -----------------------------------------------------------------------------

it('DiscordOutboundMessageResource getPages omits create and edit (read-only)', function (): void {
    $pages = DiscordOutboundMessageResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('view')
        ->and($pages)->not->toHaveKey('create')
        ->and($pages)->not->toHaveKey('edit');
});

it('Create page returns 404 (no Create route registered)', function (): void {
    // DiscordOutboundMessageResource::getPages() omits 'create' — Filament never
    // registered a route for /admin/discord-outbound-messages/create.
    $this->get('/admin/discord-outbound-messages/create')->assertStatus(404);
});

it('Edit page returns 404 (no Edit route registered)', function (): void {
    $row = DiscordOutboundMessage::factory()->create();

    // No 'edit' page in getPages() => no route registered. Laravel returns 404.
    $this->get("/admin/discord-outbound-messages/{$row->id}/edit")->assertStatus(404);
});

// -----------------------------------------------------------------------------
// Retry action visibility (RESEARCH Q3 — visible only on status=failed)
// -----------------------------------------------------------------------------

it('Retry action is visible on failed rows', function (): void {
    $failed = DiscordOutboundMessage::factory()->failed('Discord 500')->create();

    Livewire::test(ListDiscordOutboundMessages::class)
        ->assertTableActionVisible('retry', $failed);
});

it('Retry action is hidden on pending/dispatching/sent rows', function (): void {
    $pending = DiscordOutboundMessage::factory()->pending()->create();
    $dispatching = DiscordOutboundMessage::factory()->dispatching()->create();
    $sent = DiscordOutboundMessage::factory()->sent()->create();

    Livewire::test(ListDiscordOutboundMessages::class)
        ->assertTableActionHidden('retry', $pending)
        ->assertTableActionHidden('retry', $dispatching)
        ->assertTableActionHidden('retry', $sent);
});

// -----------------------------------------------------------------------------
// Retry action behaviour — RESEARCH Q3 state flip + T-05-07-05 audit
// -----------------------------------------------------------------------------

it('Retry action flips status=failed → pending + clears attempts/last_error/backoff_until', function (): void {
    $failed = DiscordOutboundMessage::factory()->create([
        'status' => 'failed',
        'attempts' => 3,
        'last_error' => 'Discord 429 — rate limited',
        'backoff_until' => now()->addMinutes(5),
    ]);

    Livewire::test(ListDiscordOutboundMessages::class)
        ->callTableAction('retry', $failed)
        ->assertHasNoTableActionErrors();

    $fresh = $failed->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe('pending');
    expect($fresh->attempts)->toBe(0);
    expect($fresh->last_error)->toBeNull();
    expect($fresh->backoff_until)->toBeNull();
});

it('Retry action writes activity_log retry event with admin causer (T-05-07-05)', function (): void {
    $failed = DiscordOutboundMessage::factory()->failed()->create();

    Livewire::test(ListDiscordOutboundMessages::class)
        ->callTableAction('retry', $failed);

    $retryEvent = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $failed->id)
        ->where('event', 'retry')
        ->first();

    expect($retryEvent)->not->toBeNull();
    expect($retryEvent->causer_id)->toBe($this->admin->id);
    expect($retryEvent->description)->toBe('admin re-queued failed outbound message');
});

// -----------------------------------------------------------------------------
// Phase 1 admin-access gate inheritance — non-admin → 403
// -----------------------------------------------------------------------------

it('non-admin user gets 403 on /admin/discord-outbound-messages', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/discord-outbound-messages')->assertStatus(403);
});
