<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\PlayerPrivacy;
use Illuminate\Database\QueryException;

it('creates with defaults', function (): void {
    $privacy = PlayerPrivacy::factory()->create();
    expect($privacy->show_to)->toBe('community');
    expect($privacy->show_real_name)->toBeFalse();
    expect($privacy->show_discord_tag)->toBeTrue();
});

it('enforces show_to CHECK constraint', function (): void {
    expect(fn () => PlayerPrivacy::factory()->create(['show_to' => 'galactic']))
        ->toThrow(QueryException::class);
});

it('cascades on player delete', function (): void {
    $player = Player::factory()->create();
    PlayerPrivacy::factory()->create(['player_id' => $player->id]);
    expect(PlayerPrivacy::where('player_id', $player->id)->count())->toBe(1);

    $player->forceDelete();
    expect(PlayerPrivacy::where('player_id', $player->id)->count())->toBe(0);
});
