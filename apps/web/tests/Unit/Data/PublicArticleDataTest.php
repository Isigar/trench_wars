<?php

declare(strict_types=1);

use App\Data\PublicArticleData;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Source: .planning/phases/07-cms/07-03-PLAN.md task 2(e). Replaces the Wave 0
| RED stub from plan 07-01.
|
| Partial GREEN: this plan ships the DTO shape + a fromModel() factory that
| emits $bodyHtml='' (TODO for plan 07-05 to wire tiptap_converter). Plan 07-05
| will flesh out the bodyHtml expectation as a separate GREEN case alongside
| the controller wiring. Plan 07-12 reuses the same DTO for the sitemap feed.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): Pest.php
| autowires TestCase + RefreshDatabase via uses(...)->in('Feature') — Unit
| does NOT have RefreshDatabase by default; we add it locally.
*/

uses(RefreshDatabase::class);

it('builds a PublicArticleData from an Article model', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'News']]);
    $author = User::factory()->create(['username' => 'commandant_rommel']);
    $article = Article::factory()->create([
        'slug' => 'rifleman-tactics-guide',
        'title' => ['en' => 'Rifleman Tactics Guide'],
        'excerpt' => ['en' => 'Quick summary.'],
        'category_id' => $category->id,
        'author_user_id' => $author->id,
        'published_at' => '2026-05-14 09:00:00',
        'allow_discord_announce' => true,
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->id)->toBe($article->id);
    expect($dto->slug)->toBe('rifleman-tactics-guide');
    expect($dto->title)->toBe('Rifleman Tactics Guide');
    expect($dto->excerpt)->toBe('Quick summary.');
    expect($dto->categoryName)->toBe('News');
    expect($dto->authorName)->toBe('commandant_rommel');
    expect($dto->allowDiscordAnnounce)->toBeTrue();
    expect($dto->url)->toBe('/news/rifleman-tactics-guide');
    expect($dto->publishedAt)->toContain('2026-05-14');
});

it('emits empty bodyHtml + null hero urls when media + tiptap_converter absent', function (): void {
    // Smoke: the DTO is constructible against an Article with no media + no
    // body conversion wired. Plan 07-05 will fill bodyHtml via tiptap_converter.
    $article = Article::factory()->create();

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->toBe('');
    expect($dto->heroThumbUrl)->toBeNull();
    expect($dto->heroOgImageUrl)->toBeNull();
});

it('emits null authorName when author_user_id is null', function (): void {
    $article = Article::factory()->create(['author_user_id' => null]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->authorName)->toBeNull();
});

it('emits null publishedAt for unpublished article', function (): void {
    $article = Article::factory()->create(['published_at' => null]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->publishedAt)->toBeNull();
});

it('TODO: bodyHtml integration with tiptap_converter (plan 07-05)', function (): void {
    // Plan 07-05 wires tiptap_converter()->asHTML for the bodyHtml field.
    // Until then this it()->skip stays as a marker for the verifier.
})->skip('Plan 07-05 wires tiptap_converter()->asHTML for the bodyHtml field');
