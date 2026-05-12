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

it('stores bio as translatable JSONB and resolves current locale', function (): void {
    $player = Player::factory()->create(['bio' => ['en' => 'hello']]);
    $player->refresh();
    // HasTranslations resolves current locale — default locale is 'en'
    expect($player->bio)->toBe('hello');
    expect($player->getTranslation('bio', 'en'))->toBe('hello');
    expect($player->getTranslations('bio'))->toBe(['en' => 'hello']);
});
