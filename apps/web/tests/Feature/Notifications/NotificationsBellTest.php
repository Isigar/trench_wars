<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-06-PLAN.md task 1.
|
| GREEN replacement for the Wave 0 stub (plan 09-01). Asserts SC-1:
|   - unread_notifications_count is shared via HandleInertiaRequests (closure
|     lazily evaluated per-request — guests resolve to 0).
|   - GET /notifications renders Notifications/Index with the paginator.
|   - POST /notifications/{id}/read marks one notification read; cross-user
|     access yields 404 (T-09-06-02 mitigation — auth-scoped relation).
|   - POST /notifications/read-all flips every unread row to read_at = now().
|   - Guests on /notifications or /account/notification-preferences redirect
|     to the Discord OAuth entrypoint (auth middleware).
*/

use App\Models\Notification;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Helper — write a DatabaseNotification row directly. Bypasses the Notification
 * dispatch pipeline so tests don't need to spin up a concrete Notification class
 * and queue worker. Mirrors the row shape DatabaseChannel would insert.
 *
 * @param  array<string, mixed>  $data
 */
function makeDbNotification(User $user, string $type = 'match.starting_soon', array $data = [], ?Carbon $readAt = null): Notification
{
    /** @var Notification $row */
    $row = Notification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => $user::class,
        'notifiable_id' => $user->id,
        'data' => $data,
        'read_at' => $readAt,
    ]);

    return $row;
}

it('shares unread_notifications_count on Inertia props (3 unread rows for the auth user)', function (): void {
    $user = User::factory()->create();
    makeDbNotification($user);
    makeDbNotification($user);
    makeDbNotification($user);
    // A read notification — must NOT contribute to the unread count.
    makeDbNotification($user, readAt: now());

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->where('unread_notifications_count', 3)
        );
});

it('shares unread_notifications_count = 0 for guests', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->where('unread_notifications_count', 0)
        );
});

it('GET /notifications renders Notifications/Index for the auth user', function (): void {
    $user = User::factory()->create();
    makeDbNotification($user);

    $this->actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Notifications/Index', false)
                ->has('notifications')
                ->has('notifications.data', 1)
        );
});

it('POST /notifications/{id}/read marks a single notification read', function (): void {
    $user = User::factory()->create();
    $unread = makeDbNotification($user);

    expect($unread->fresh()?->read_at)->toBeNull();

    $this->actingAs($user)
        ->post("/notifications/{$unread->id}/read")
        ->assertRedirect();

    expect($unread->fresh()?->read_at)->not->toBeNull();
});

it('POST /notifications/read-all marks every unread notification read', function (): void {
    $user = User::factory()->create();
    makeDbNotification($user);
    makeDbNotification($user);
    makeDbNotification($user);

    expect($user->unreadNotifications()->count())->toBe(3);

    $this->actingAs($user)
        ->post('/notifications/read-all')
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns 404 when marking another user\'s notification read (T-09-06-02)', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $row = makeDbNotification($owner);

    $this->actingAs($attacker)
        ->post("/notifications/{$row->id}/read")
        ->assertStatus(404);

    // The notification must remain unread — owner-scoped.
    expect($row->fresh()?->read_at)->toBeNull();
});

it('returns 404 when marking a non-existent notification id', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/notifications/' . Str::uuid() . '/read')
        ->assertStatus(404);
});

it('guests are redirected away from /notifications by the auth middleware', function (): void {
    $this->get('/notifications')->assertRedirect();
});

it('guests are redirected away from /account/notification-preferences by the auth middleware', function (): void {
    $this->get('/account/notification-preferences')->assertRedirect();
});

it('GET /account/notification-preferences renders the matrix for the auth user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/account/notification-preferences')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Account/NotificationPreferences', false)
                ->has('preferences')
                ->has('event_types', 5)
                ->has('channels', 2)
        );
});

it('POST /account/notification-preferences persists toggles via updateOrCreate', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/account/notification-preferences', [
            'preferences' => [
                ['event_type' => 'match_starting_soon', 'channel' => 'database', 'enabled' => false],
                ['event_type' => 'match_starting_soon', 'channel' => 'discord', 'enabled' => true],
            ],
        ])
        ->assertRedirect();

    $rows = $user->notificationPreferences()->get()
        ->keyBy(fn ($r) => $r->event_type . ':' . $r->channel);

    expect($rows->get('match_starting_soon:database')?->enabled)->toBeFalse();
    expect($rows->get('match_starting_soon:discord')?->enabled)->toBeTrue();
});

it('POST /account/notification-preferences rejects an unknown event_type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/account/notification-preferences', [
            'preferences' => [
                ['event_type' => 'totally_invalid_event', 'channel' => 'database', 'enabled' => true],
            ],
        ])
        ->assertSessionHasErrors('preferences.0.event_type');
});

it('POST /account/notification-preferences scopes updateOrCreate to the auth user (T-09-06-03)', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->post('/account/notification-preferences', [
            'preferences' => [
                ['event_type' => 'match_starting_soon', 'channel' => 'database', 'enabled' => false],
            ],
        ])
        ->assertRedirect();

    // userA has one row, userB has none.
    expect($userA->notificationPreferences()->count())->toBe(1);
    expect($userB->notificationPreferences()->count())->toBe(0);
});

it('DatabaseNotification is the parent type used by Laravel\'s Notifiable trait', function (): void {
    // Sanity check that App\Models\Notification extends the Laravel parent —
    // protects against a future Phase 10 refactor that swaps the model out.
    expect(is_subclass_of(Notification::class, DatabaseNotification::class))->toBeTrue();
});
