<?php

declare(strict_types=1);

use App\Models\DiscordGuild;
use Database\Seeders\DiscordGuildSeeder;

it('enforces single-row invariant operationally via seeder idempotency (D-003)', function (): void {
    $this->seed(DiscordGuildSeeder::class);
    $this->seed(DiscordGuildSeeder::class);

    expect(DiscordGuild::count())->toBe(1);
});

it('a second manual DiscordGuild::create() succeeds at the DB layer (operational gate is the only enforcement)', function (): void {
    $this->seed(DiscordGuildSeeder::class);

    /*
     * RESEARCH.md Pattern 4 chose operational enforcement (seeder + Filament no-Create page)
     * over a DB-level CHECK constraint. The DB will accept a second row if asked directly —
     * the gate is the Filament resource refusing to expose Create (plan 02-13).
     * If policy changes to DB-level enforcement (D-### supersede), flip this test to
     * assert ->toThrow(QueryException::class).
     */
    DiscordGuild::create(['guild_id' => '123456789012345678']);

    expect(DiscordGuild::count())->toBe(2);
});
