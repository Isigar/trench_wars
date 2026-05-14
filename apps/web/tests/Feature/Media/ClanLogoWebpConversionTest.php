<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-09 (Clan registers `avatar-thumb`,
| `avatar-card`, `avatar-hero` media conversions with ->format('webp')). Asserts
| intent of SC-4 (WebP) — clan logo upload generates WebP variants via
| spatie/laravel-medialibrary.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1355): "Clan logo upload generates avatar-thumb.webp, avatar-card.webp, avatar-hero.webp".
*/

test('Wave 0 stub: clan logo upload generates avatar-thumb.webp, avatar-card.webp, avatar-hero.webp', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-09');
})->skip('Wave 0 stub — turned GREEN in plan 09-09');
