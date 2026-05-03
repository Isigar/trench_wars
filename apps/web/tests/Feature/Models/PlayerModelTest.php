<?php

declare(strict_types=1);

use App\Models\Player;

it('creates a Player linked to a User', function (): void {
    $player = Player::factory()->create();
    expect($player->user)->not->toBeNull();
    expect($player->user_id)->toBe($player->user->id);
});

it('soft-deletes', function (): void {
    $player = Player::factory()->create();
    $id = $player->id;
    $player->delete();
    expect(Player::find($id))->toBeNull();
    expect(Player::withTrashed()->find($id))->not->toBeNull();
});

it('casts bio to array', function (): void {
    $player = Player::factory()->create(['bio' => ['en' => 'hello']]);
    $player->refresh();
    expect($player->bio)->toBe(['en' => 'hello']);
});
