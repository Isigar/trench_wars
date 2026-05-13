<?php

declare(strict_types=1);

/*
| Source: 05-12-PLAN.md task 2 — admin-side audit-log coverage for the Filament
| Retry action on DiscordOutboundMessageResource (plan 05-07, T-05-07-05).
|
| The retry action is the only mutation the Filament admin exposes on the
| outbound resource (no Create / Edit pages — T-05-07-01). This test verifies:
|   1. Clicking Retry writes an activity_log row with event='retry'
|   2. The activity_log row's causer_id is the admin who clicked the button
|   3. The action is only callable on rows with status='failed' (visibility
|      contract — visible() callback on the action; covered structurally here
|      by asserting non-failed rows raise an action error when forcibly invoked)
|
| Analog: tests/Feature/Admin/MatchAuditLogTest.php (Phase 4 plan 04-12) for
| the admin actingAs + Filament Livewire pattern, and
| tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php for the
| same retry-action sanity (this file isolates the audit-log assertions so a
| regression in LogsActivity wiring fails a single dedicated test).
*/

use App\Filament\Resources\DiscordOutboundMessageResource\Pages\ListDiscordOutboundMessages;
use App\Models\DiscordOutboundMessage;
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

    // Filament v3.3 panel context bootstrap — Livewire component tests don't go
    // through the panel middleware so Filament::getCurrentPanel() is null without
    // this hint (same idiom as DiscordOutboundMessageResourcePresentTest).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('retry action writes activity_log row with event=retry', function (): void {
    $failedRow = DiscordOutboundMessage::factory()->failed('Discord 500 — Internal Server Error')->create();

    Livewire::test(ListDiscordOutboundMessages::class)
        ->callTableAction('retry', $failedRow)
        ->assertHasNoTableActionErrors();

    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $failedRow->id)
        ->where('event', 'retry')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('admin re-queued failed outbound message');
});

it('retry action causer_id is the admin who clicked the button (T-05-07-05)', function (): void {
    $failedRow = DiscordOutboundMessage::factory()->failed()->create();

    Livewire::test(ListDiscordOutboundMessages::class)
        ->callTableAction('retry', $failedRow);

    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('subject_id', $failedRow->id)
        ->where('event', 'retry')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class);
});

it('retry action only fires on failed rows — non-failed rows hide the action', function (): void {
    $pending = DiscordOutboundMessage::factory()->pending()->create();
    $dispatching = DiscordOutboundMessage::factory()->dispatching()->create();
    $sent = DiscordOutboundMessage::factory()->sent()->create();

    // visibility check — the action ->visible() callback in the resource gates
    // on $record->status === 'failed'. The Filament Livewire harness exposes
    // assertTableActionHidden() which mirrors the visible() callback resolution.
    Livewire::test(ListDiscordOutboundMessages::class)
        ->assertTableActionHidden('retry', $pending)
        ->assertTableActionHidden('retry', $dispatching)
        ->assertTableActionHidden('retry', $sent);

    // No retry activity row was written (only the underlying LogsActivity
    // create rows from the factories).
    $retryActivityCount = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('event', 'retry')
        ->count();

    expect($retryActivityCount)->toBe(0);
});
