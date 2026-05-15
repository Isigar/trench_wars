<?php

declare(strict_types=1);

use App\Models\Clan;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;

/*
| Source: .planning/phases/09-polish/09-09-PLAN.md task 1 (Wave 6 — WebP image
| variants via spatie/laravel-medialibrary).
|
| GREEN replacement of the Wave 0 stub:
|   "Wave 0 stub: clan logo upload generates avatar-thumb.webp, avatar-card.webp,
|    avatar-hero.webp" — Validation Architecture row (09-RESEARCH.md L1355).
|
| Asserts SC-4 intent: Clan model registers 3 WebP conversions (avatar-thumb
| 48x48, avatar-card 200x200, avatar-hero 800x800) via Pattern 5; spatie/image
| Imagick/GD driver actually emits .webp files on disk; conversions are queued.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): no namespace,
| no per-file uses() — Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature').
|
| Queue config note: phpunit.xml forces QUEUE_CONNECTION=sync so ->queued()
| conversions run inline with the addMedia() call. In production Horizon picks
| them up async (upload returns immediately).
*/

it('registers 3 WebP conversions on the Clan model (avatar-thumb/card/hero, all ->format("webp")->queued())', function (): void {
    $clan = Clan::factory()->create();

    // Drive the InteractsWithMedia trait's collector to inspect conversion shape.
    $clan->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $clan->mediaConversions;

    $names = collect($conversions)->map(fn ($c): string => $c->getName())->all();
    expect($names)->toContain('avatar-thumb');
    expect($names)->toContain('avatar-card');
    expect($names)->toContain('avatar-hero');

    // Every conversion targets the 'webp' format. getManipulationArgument
    // returns the raw arg array exactly as ->format('webp') passed it — for
    // a single-arg call this is `['webp']` (medialibrary stores arguments
    // verbatim).
    foreach ($conversions as $conversion) {
        $manipulations = $conversion->getManipulations()->getManipulationArgument('format');
        expect($manipulations)
            ->toBe(['webp'], "Conversion '{$conversion->getName()}' must format to webp");
    }
});

it('generates avatar-thumb.webp + avatar-card.webp + avatar-hero.webp files on logo upload', function (): void {
    Storage::fake('public');
    $clan = Clan::factory()->create();

    // 256x256 PNG large enough to exercise all three conversion widths (48/200/800).
    // Note: 800-wide target on a 256-wide source will upscale, which medialibrary
    // accepts; the WebP output is still emitted (just at the source dimensions or
    // upscaled per Imagick/GD).
    //
    // Store the UploadedFile in a $file variable so its temp file stays readable
    // across the addMedia() call — File::image() returns an UploadedFile whose
    // temp file would otherwise get cleaned up before medialibrary reads it.
    $file = File::image('logo.png', 256, 256);
    $clan->addMedia($file->getPathname())
        ->preservingOriginal()
        ->toMediaCollection('logos');

    $media = $clan->fresh()?->getFirstMedia('logos');
    expect($media)->not->toBeNull();

    foreach (['avatar-thumb', 'avatar-card', 'avatar-hero'] as $conversion) {
        $path = $media?->getPath($conversion);
        expect($path)
            ->toEndWith('.webp', "Conversion '{$conversion}' must produce a .webp file");
        expect(file_exists($path))
            ->toBeTrue("Conversion '{$conversion}' file must exist on disk at {$path}");
    }
});

it('emits avatar-card output smaller than the original (spatie/image-optimizer pipeline ran)', function (): void {
    Storage::fake('public');
    $clan = Clan::factory()->create();

    // Use a 400x400 PNG so the 200x200 avatar-card conversion downscales.
    // After downscale + WebP encode + image-optimizer pass, the result MUST be
    // smaller than the original (else either the conversion or the optimizer
    // pipeline silently failed). Keep UploadedFile alive in a variable so its
    // temp file isn't GC'd before medialibrary reads it.
    $file = File::image('logo-large.png', 400, 400);
    $sourcePath = $file->getPathname();
    $sourceBytes = filesize($sourcePath);
    expect($sourceBytes)->toBeGreaterThan(0);

    $clan->addMedia($sourcePath)
        ->preservingOriginal()
        ->toMediaCollection('logos');

    $media = $clan->fresh()?->getFirstMedia('logos');
    $cardPath = $media?->getPath('avatar-card');
    expect(file_exists($cardPath))->toBeTrue();

    $cardBytes = filesize($cardPath);
    expect($cardBytes)->toBeLessThan(
        $sourceBytes,
        "WebP output ({$cardBytes}B) must be smaller than original PNG ({$sourceBytes}B) — optimizer + format conversion both ran"
    );
});

it('marks all 3 conversions as queued (Horizon-async in production, sync in tests)', function (): void {
    $clan = Clan::factory()->create();
    $clan->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $clan->mediaConversions;

    $queued = collect($conversions)
        ->filter(fn (Conversion $c): bool => in_array($c->getName(), ['avatar-thumb', 'avatar-card', 'avatar-hero'], true));

    foreach ($queued as $conversion) {
        expect($conversion->shouldBeQueued())
            ->toBeTrue("Conversion '{$conversion->getName()}' must be ->queued()");
    }
});
