<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Source: 01-REVIEW.md CR-02. The `bio` JSONB column has an Eloquent `array`
 * cast — binding it to a Textarea would coerce form input to a string scalar
 * and silently corrupt the locale-keyed shape. This test pins:
 *
 *   1. The PlayerResource form source uses KeyValue (not Textarea) for `bio`.
 *   2. Saving an array-shaped bio round-trips through the model unchanged.
 *   3. Round-tripping the JSON through Postgres preserves the associative shape.
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('declares the bio field as a KeyValue input (source-pinned)', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/PlayerResource.php'));

    expect($source)->toContain("KeyValue::make('bio')");
    expect($source)->not->toContain("Textarea::make('bio')");
});

it('preserves the locale-keyed bio shape across save + reload', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id]);
    PlayerPrivacy::create(['player_id' => $player->id]);

    // Simulate what KeyValue dehydrates: an associative array of locale → text.
    $player->bio = ['en' => 'Hello world', 'cs' => 'Ahoj svete'];
    $player->save();

    $reloaded = $player->fresh();

    expect($reloaded->bio)->toBeArray();
    expect($reloaded->bio)->toBe(['en' => 'Hello world', 'cs' => 'Ahoj svete']);
});
