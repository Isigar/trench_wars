<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest config — Trenchwars
|--------------------------------------------------------------------------
| Source: pestphp.com/docs/configuration + 01-VALIDATION.md.
|
| Wires the base TestCase + RefreshDatabase to the Feature group so every
| feature test starts from a clean Postgres state. Unit tests intentionally
| skip RefreshDatabase — they run against in-memory fixtures.
*/

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
| `actingAsAdmin()` references App\Models\User factory + spatie/laravel-permission
| helpers that land in plans 01-10 + 01-11. The helper is autoload-tolerant
| (only invoked from tests authored after those plans land).
*/

function actingAsAdmin(): User
{
    /** @var User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('admin-access');

    return tap($user, fn ($u) => test()->actingAs($u));
}
