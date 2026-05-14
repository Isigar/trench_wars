<?php

declare(strict_types=1);

use App\Exceptions\InvalidArticleStatusTransitionException;
use App\Models\Article;
use App\Services\ArticleStatusService;

/*
| Source: 07-06-PLAN.md Task 1.
|
| Covers the ArticleStatusService state machine:
|   draft     -> scheduled | published
|   scheduled -> published | draft
|   published -> draft
|
| Transition to published stamps published_at + clears scheduled_at; all other
| transitions are status-only.
|
| Threat refs:
|   - T-07-06-01 (re-publish loop): the observer enforces the once-only gate,
|     but the service refusing an "already published" → "published" (not in
|     ALLOWED — only published → draft is permitted) is the lower defence layer.
*/

// ---------------------------------------------------------------------------
// Happy paths — every permitted transition (5 paths)
// ---------------------------------------------------------------------------

it('transitions draft to scheduled', function (): void {
    $a = Article::factory()->create(['status' => 'draft', 'scheduled_at' => null, 'published_at' => null]);

    $result = app(ArticleStatusService::class)->transition($a, 'scheduled');

    expect($result)->toBeInstanceOf(Article::class)
        ->and($a->fresh()->status)->toBe('scheduled')
        ->and($a->fresh()->published_at)->toBeNull();
});

it('transitions draft to published and sets published_at', function (): void {
    $a = Article::factory()->create(['status' => 'draft', 'published_at' => null]);

    app(ArticleStatusService::class)->transition($a, 'published');

    $fresh = $a->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->published_at)->not->toBeNull();
});

it('transitions scheduled to published and clears scheduled_at', function (): void {
    $a = Article::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'published_at' => null,
    ]);

    app(ArticleStatusService::class)->transition($a, 'published');

    $fresh = $a->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->scheduled_at)->toBeNull()
        ->and($fresh->published_at)->not->toBeNull();
});

it('transitions scheduled to draft (unschedule path)', function (): void {
    $a = Article::factory()->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
    ]);

    app(ArticleStatusService::class)->transition($a, 'draft');

    expect($a->fresh()->status)->toBe('draft');
});

it('allows published to draft for unpublish path', function (): void {
    // Admin unpublish keeps the published_at audit trail — service does not
    // clear it. Re-publishing later will set a fresh published_at via the
    // 'draft|scheduled -> published' branch.
    $publishedAt = now()->subDay();
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => $publishedAt,
    ]);

    app(ArticleStatusService::class)->transition($a, 'draft');

    $fresh = $a->fresh();
    expect($fresh->status)->toBe('draft')
        ->and($fresh->published_at?->toIso8601String())->toBe($publishedAt->toIso8601String());
});

// ---------------------------------------------------------------------------
// Rejected transitions — illegal pairs throw InvalidArticleStatusTransitionException
// ---------------------------------------------------------------------------

it('throws on illegal transition published to scheduled', function (): void {
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect(fn () => app(ArticleStatusService::class)->transition($a, 'scheduled'))
        ->toThrow(InvalidArticleStatusTransitionException::class);

    expect($a->fresh()->status)->toBe('published');
});

it('throws on illegal transition draft to draft (no-op)', function (): void {
    $a = Article::factory()->create(['status' => 'draft']);

    expect(fn () => app(ArticleStatusService::class)->transition($a, 'draft'))
        ->toThrow(InvalidArticleStatusTransitionException::class);
});

it('throws on transition to unknown status string', function (): void {
    $a = Article::factory()->create(['status' => 'draft']);

    expect(fn () => app(ArticleStatusService::class)->transition($a, 'archived'))
        ->toThrow(InvalidArticleStatusTransitionException::class);
});

it('throws on transition from unknown current status (synthetic)', function (): void {
    $a = Article::factory()->create(['status' => 'draft']);
    $a->status = 'archived';  // transient — never persisted (CHECK constraint would reject)

    expect(fn () => app(ArticleStatusService::class)->transition($a, 'published'))
        ->toThrow(InvalidArticleStatusTransitionException::class);
});

it('throws the typed exception subclass (not bare DomainException)', function (): void {
    $a = Article::factory()->create(['status' => 'published', 'published_at' => now()]);

    try {
        app(ArticleStatusService::class)->transition($a, 'scheduled');
        $this->fail('Expected InvalidArticleStatusTransitionException was not thrown.');
    } catch (InvalidArticleStatusTransitionException $e) {
        expect($e)->toBeInstanceOf(InvalidArticleStatusTransitionException::class)
            ->and($e)->toBeInstanceOf(DomainException::class);
    }
});
