<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;

/*
| Source: .planning/phases/09-polish/09-09-PLAN.md task 1.
|
| GREEN replacement of the Wave 0 stub:
|   "Wave 0 stub: article cover upload generates WebP variants (cover-thumb,
|    cover-hero)" — Validation Architecture row (09-RESEARCH.md L1356).
|
| Article keeps the Phase 7 trio (thumb/hero/og-image) for backward-compat with
| ArticleSummaryData::heroThumbUrl + PublicArticleData::heroOgImageUrl, AND adds
| the Phase 9 plan 09-09 trio (cover-thumb 200x120, cover-card 600x400, cover-hero
| 1200x630) — all WebP, all queued. og-image stays original-format (non-webp)
| for social-scraper compatibility (T-09-09-04 accept).
|
| Bare Pest functional convention — Pest.php autowires TestCase + RefreshDatabase.
*/

it('registers 6 hero-collection conversions (Phase 7 thumb/hero/og-image + Phase 9 cover-*)', function (): void {
    $article = Article::factory()->create();
    $article->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $article->mediaConversions;

    $names = collect($conversions)->map(fn ($c): string => $c->getName())->all();
    // Phase 9 plan 09-09 cover-* trio.
    expect($names)->toContain('cover-thumb');
    expect($names)->toContain('cover-card');
    expect($names)->toContain('cover-hero');
});

it('cover-* conversions emit WebP format', function (): void {
    $article = Article::factory()->create();
    $article->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $article->mediaConversions;

    $coverConversions = collect($conversions)->filter(
        fn (Conversion $c): bool => str_starts_with($c->getName(), 'cover-')
    );

    expect($coverConversions)->toHaveCount(3);

    // getManipulationArgument returns the raw arg array `['webp']` (medialibrary
    // stores ->format('webp')'s argument list verbatim).
    foreach ($coverConversions as $conversion) {
        $format = $conversion->getManipulations()->getManipulationArgument('format');
        expect($format)
            ->toBe(['webp'], "cover-* conversion '{$conversion->getName()}' must format to webp");
    }
});

it('cover-hero dimensions are 1200x630 (OpenGraph optimal)', function (): void {
    $article = Article::factory()->create();
    $article->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $article->mediaConversions;

    $coverHero = collect($conversions)->first(
        fn (Conversion $c): bool => $c->getName() === 'cover-hero'
    );

    expect($coverHero)->not->toBeNull();

    // getManipulationArgument returns the raw arg list — ->width(1200) stores
    // [1200] (int), ->format('webp') stores ['webp'] (string).
    $manipulations = $coverHero->getManipulations();
    expect($manipulations->getManipulationArgument('width'))->toBe([1200]);
    expect($manipulations->getManipulationArgument('height'))->toBe([630]);
});

it('generates cover-thumb.webp + cover-card.webp + cover-hero.webp on article cover upload', function (): void {
    Storage::fake('public');
    $article = Article::factory()->create();

    // Source banner: 1600x900 PNG (wide enough to downscale to all three target widths).
    // Keep UploadedFile in a variable so its temp file isn't GC'd before
    // medialibrary reads it.
    $file = File::image('banner.png', 1600, 900);
    $article->addMedia($file->getPathname())
        ->preservingOriginal()
        ->toMediaCollection('hero');

    $media = $article->fresh()?->getFirstMedia('hero');
    expect($media)->not->toBeNull();

    foreach (['cover-thumb', 'cover-card', 'cover-hero'] as $conversion) {
        $path = $media?->getPath($conversion);
        expect($path)
            ->toEndWith('.webp', "Conversion '{$conversion}' must produce a .webp file");
        expect(file_exists($path))
            ->toBeTrue("Conversion '{$conversion}' file must exist on disk at {$path}");
    }
});

it('cover-* conversions are queued (Horizon-async in production, sync in tests)', function (): void {
    $article = Article::factory()->create();
    $article->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $article->mediaConversions;

    $coverConversions = collect($conversions)->filter(
        fn (Conversion $c): bool => str_starts_with($c->getName(), 'cover-')
    );

    foreach ($coverConversions as $conversion) {
        expect($conversion->shouldBeQueued())
            ->toBeTrue("Conversion '{$conversion->getName()}' must be ->queued()");
    }
});
