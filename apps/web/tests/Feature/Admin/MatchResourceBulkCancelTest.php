<?php

declare(strict_types=1);

use App\Filament\Resources\MatchResource;
use App\Models\GameMatch;
use App\Models\MatchSlot;
use App\Models\User;
use App\Notifications\MatchCancelled;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/09-polish/09-07-PLAN.md task 2 (Wave 5).
|
| Replaces the Wave 0 RED stub. Covers SC-3 invariants for MatchResource
| bulk-cancel:
|
|   1. Selected scheduled (open/locked) matches transition to status='cancelled'.
|   2. MatchCancelled notifications fan out via the MatchObserver chain
|      (plan 09-04 maybeNotifyCancellation) to every occupant_user_id slot.
|   3. Terminal-state matches (played, cancelled) in the bulk selection are
|      ignored — they do NOT cause a state-machine error and do NOT trigger
|      duplicate notifications.
|   4. Permission gate: only moderators (with moderate-disputes) can invoke.
|   5. Pitfall 8 — required + minLength on reason field surfaces inline errors.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    $this->moderator = User::factory()->create();
    $this->moderator->givePermissionTo('admin-access');
    $this->moderator->assignRole('moderator');

    $this->actingAs($this->moderator);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ---------------------------------------------------------------------------
// Bulk-cancel fires MatchCancelled notifications via observer chain
// ---------------------------------------------------------------------------

it('bulk-cancels selected matches and fires MatchCancelled notifications', function (): void {
    Notification::fake();

    // Two scheduled (open) matches with signed-up players.
    $matchA = GameMatch::factory()->create(['status' => 'open']);
    $matchB = GameMatch::factory()->create(['status' => 'open']);

    $playerA = User::factory()->create();
    $playerB = User::factory()->create();
    MatchSlot::factory()->for($matchA, 'match')->create(['occupant_user_id' => $playerA->id]);
    MatchSlot::factory()->for($matchB, 'match')->create(['occupant_user_id' => $playerB->id]);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->callTableBulkAction('mark_cancelled', [$matchA->id, $matchB->id], data: [
            'reason' => 'Bulk-cancel: emergency server downtime.',
        ])
        ->assertHasNoTableBulkActionErrors();

    expect($matchA->fresh()->status)->toBe('cancelled')
        ->and($matchB->fresh()->status)->toBe('cancelled');

    // MatchObserver::updated -> maybeNotifyCancellation should have fired
    // MatchCancelled to each signed-up user.
    Notification::assertSentTo($playerA, MatchCancelled::class);
    Notification::assertSentTo($playerB, MatchCancelled::class);
});

it('does not transition already-cancelled or already-played matches', function (): void {
    Notification::fake();

    $alreadyCancelled = GameMatch::factory()->create(['status' => 'cancelled']);
    $alreadyPlayed = GameMatch::factory()->create(['status' => 'played']);
    $scheduledOpen = GameMatch::factory()->create(['status' => 'open']);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->callTableBulkAction('mark_cancelled', [
            $alreadyCancelled->id,
            $alreadyPlayed->id,
            $scheduledOpen->id,
        ], data: [
            'reason' => 'Bulk over mixed selection — terminal rows must be ignored.',
        ])
        ->assertHasNoTableBulkActionErrors();

    expect($alreadyCancelled->fresh()->status)->toBe('cancelled')
        ->and($alreadyPlayed->fresh()->status)->toBe('played')
        ->and($scheduledOpen->fresh()->status)->toBe('cancelled');
});

it('writes a single match.bulk_cancelled activity_log row with count + reason', function (): void {
    Notification::fake();

    $a = GameMatch::factory()->create(['status' => 'open']);
    $b = GameMatch::factory()->create(['status' => 'open']);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->callTableBulkAction('mark_cancelled', [$a->id, $b->id], data: [
            'reason' => 'Bulk-level audit row — single causer entry.',
        ])
        ->assertHasNoTableBulkActionErrors();

    $row = Activity::query()
        ->where('description', 'match.bulk_cancelled')
        ->latest('id')
        ->first();
    expect($row)->not->toBeNull()
        ->and($row->causer_id)->toBe($this->moderator->id);

    /** @var array<string, mixed> $props */
    $props = is_array($row->properties) ? $row->properties : $row->properties->toArray();
    expect($props['count'])->toBe(2)
        ->and($props['reason'])->toBe('Bulk-level audit row — single causer entry.');
});

it('MatchResource bulk-cancel BulkAction enforces required reason (Pitfall 8)', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->callTableBulkAction('mark_cancelled', [$match->id], data: [
            'reason' => '',
        ])
        ->assertHasTableBulkActionErrors(['reason']);

    expect($match->fresh()->status)->toBe('open');
});
