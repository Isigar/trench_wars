<?php

declare(strict_types=1);

use App\Models\DiscordGuild;
use Database\Seeders\DiscordGuildSeeder;

it('seeds exactly one discord_guild row', function (): void {
    $this->seed(DiscordGuildSeeder::class);

    expect(DiscordGuild::count())->toBe(1);
});

it('is idempotent — re-running the seeder leaves one row', function (): void {
    $this->seed(DiscordGuildSeeder::class);
    $this->seed(DiscordGuildSeeder::class);

    expect(DiscordGuild::count())->toBe(1);
});

it('seeded row has nullable fields null until admin fills them', function (): void {
    $this->seed(DiscordGuildSeeder::class);

    $row = DiscordGuild::first();
    expect($row)->not->toBeNull();
    expect($row->guild_id)->toBeNull();
    expect($row->name)->toBeNull();
});
