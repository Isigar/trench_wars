<?php

declare(strict_types=1);

use App\Data\SearchResultData;
use App\Models\Article;
use App\Models\Category;
use App\Models\Clan;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
| Source: .planning/phases/07-cms/07-08-PLAN.md task 2.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): Pest.php
| autowires TestCase + RefreshDatabase via uses(...)->in('Feature') — Unit
| does NOT have RefreshDatabase by default; we add it locally for the model-
| factory rows.
*/

uses(RefreshDatabase::class);

it('fromArticle populates type, slug, url, and title from translatable fields', function (): void {
    $category = Category::factory()->create();
    $article = Article::factory()->for($category, 'category')->create([
        'slug' => 'rifleman-tactics',
        'title' => ['en' => 'Rifleman Tactics'],
        'excerpt' => ['en' => 'Frontline basics.'],
        'status' => 'published',
    ]);

    $row = SearchResultData::fromArticle($article, 3.5);

    expect($row->type)->toBe('article');
    expect($row->id)->toBe((string) $article->id);
    expect($row->slug)->toBe('rifleman-tactics');
    expect($row->title)->toBe('Rifleman Tactics');
    expect($row->excerpt)->toBe('Frontline basics.');
    expect($row->url)->toBe('/news/rifleman-tactics');  // route('blog.show') lands in plan 07-09
    expect($row->rank)->toBe(3.5);
});

it('fromClan populates type, slug, url, title, and truncated excerpt', function (): void {
    $clan = Clan::factory()->create([
        'slug' => 'phantom-brigade',
        'name' => 'The Phantom Brigade',
        'description' => ['en' => str_repeat('A very elite squad. ', 30)],  // > 200 chars
    ]);

    $row = SearchResultData::fromClan($clan, 1.0);

    expect($row->type)->toBe('clan');
    expect($row->id)->toBe((string) $clan->id);
    expect($row->slug)->toBe('phantom-brigade');
    expect($row->title)->toBe('The Phantom Brigade');
    expect(mb_strlen($row->excerpt))->toBeLessThanOrEqual(200);
    expect($row->url)->toBe(route('clans.show', 'phantom-brigade'));
    expect($row->rank)->toBe(1.0);
});

it('fromPlayer falls back from display_name → username → slug', function (): void {
    $user = User::factory()->create(['username' => 'cmdr_user']);
    $playerWithDisplay = Player::factory()->create([
        'user_id' => $user->id,
        'display_name' => 'Commander',
        'slug' => 'cmdr-slug',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $playerWithDisplay->id,
        'show_to' => 'public',
    ]);

    $gate = app(PlayerPrivacyGate::class);

    // case 1: display_name present — title = display_name
    $row = SearchResultData::fromPlayer($playerWithDisplay, $gate, null, 0.0);
    expect($row->type)->toBe('player');
    expect($row->title)->toBe('Commander');
    expect($row->url)->toBe(route('players.show', 'cmdr-slug'));
    expect($row->thumbnailUrl)->toBeNull();

    // case 2: display_name null → falls back to user.username
    $anotherUser = User::factory()->create(['username' => 'plain_user']);
    $playerNoDisplay = Player::factory()->create([
        'user_id' => $anotherUser->id,
        'display_name' => null,
        'slug' => 'plain-slug',
    ]);
    PlayerPrivacy::factory()->create([
        'player_id' => $playerNoDisplay->id,
        'show_to' => 'public',
    ]);

    $row2 = SearchResultData::fromPlayer($playerNoDisplay, $gate, null, 0.0);
    expect($row2->title)->toBe('plain_user');
});

it('declares the #[TypeScript] attribute (D-020 — auto-emitted in api.d.ts)', function (): void {
    $reflection = new ReflectionClass(SearchResultData::class);
    $attributes = $reflection->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
