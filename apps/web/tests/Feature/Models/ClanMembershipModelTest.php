<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

it('enforces one active membership per user (D-009)', function (): void {
    $user = User::factory()->create();
    $clan1 = Clan::factory()->create();
    $clan2 = Clan::factory()->create();

    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan1->id,
        'left_at' => null,
    ]);

    expect(fn () => ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan2->id,
        'left_at' => null,
    ]))->toThrow(QueryException::class);
});

it('allows a second active membership after first is left (history preserved)', function (): void {
    $user = User::factory()->create();
    $clan1 = Clan::factory()->create();
    $clan2 = Clan::factory()->create();

    $first = ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan1->id,
        'left_at' => null,
    ]);

    // User leaves clan1 — sets left_at, preserving history (D-009)
    $first->update(['left_at' => now()]);

    // Now a second active membership is allowed
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan2->id,
        'left_at' => null,
    ]);

    expect(ClanMembership::where('user_id', $user->id)->count())->toBe(2);
});

it('enforces role CHECK constraint', function (): void {
    expect(fn () => ClanMembership::factory()->create(['role' => 'galactic']))
        ->toThrow(QueryException::class);
});

it('logs activity on create (D-012)', function (): void {
    $membership = ClanMembership::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', ClanMembership::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
