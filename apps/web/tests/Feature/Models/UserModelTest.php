<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;

it('creates a user with a UUID primary key', function (): void {
    $user = User::factory()->create();
    expect($user->id)->toBeString();
    expect(strlen($user->id))->toBe(36); // standard UUID v4 string length
});

it('enforces unique discord_id', function (): void {
    User::factory()->create(['discord_id' => '123456789012345678']);
    expect(fn () => User::factory()->create(['discord_id' => '123456789012345678']))
        ->toThrow(QueryException::class);
});

it('exposes a player relationship (initially null)', function (): void {
    $user = User::factory()->create();
    expect($user->player)->toBeNull();
});
