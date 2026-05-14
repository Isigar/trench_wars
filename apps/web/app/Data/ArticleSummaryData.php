<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + must_haves truths line 35.
 *
 * Listing-card projection consumed by the public /blog Vue page (plan 07-10).
 * Distinct from PublicArticleData (07-03/07-05) because the index page renders
 * dozens of cards per request — sending the rendered bodyHtml for each would be
 * wasteful (and re-runs tiptap_converter()->asHTML per article).
 *
 * Field shape:
 *   - id, slug                  — route key + UUID for keyed loops
 *   - title, excerpt            — locale-resolved via getTranslation (D-013)
 *   - categoryName, authorName  — eager-loaded BelongsTo names; null-safe
 *   - publishedAt               — ISO-8601 string; sortable on the client
 *   - heroThumbUrl              — 'thumb' conversion (600x400, Pattern 2);
 *                                 nullable when no media uploaded
 *   - url                       — pre-built /blog/{slug} string (matches the
 *                                 named route 'blog.show' shape so a future
 *                                 rename keeps a single source of truth)
 *
 * Caller (BlogIndexController) MUST eager-load `category` + `author` + `media`
 * for N+1-free hydration.
 */
#[TypeScript]
final class ArticleSummaryData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public string $categoryName,
        public ?string $authorName,
        public ?string $publishedAt,
        public ?string $heroThumbUrl,
        public string $url,
    ) {}

    public static function fromModel(Article $article): self
    {
        $locale = app()->getLocale();

        /** @var Carbon|null $publishedAt */
        $publishedAt = $article->published_at;

        $thumbUrl = $article->getFirstMediaUrl('hero', 'thumb');

        return new self(
            id: $article->id,
            slug: $article->slug,
            title: $article->getTranslation('title', $locale, useFallbackLocale: true),
            excerpt: $article->getTranslation('excerpt', $locale, useFallbackLocale: true) ?: null,
            categoryName: $article->category?->getTranslation('name', $locale, useFallbackLocale: true) ?? '',
            authorName: $article->author?->username,
            publishedAt: $publishedAt?->toIso8601String(),
            heroThumbUrl: $thumbUrl !== '' ? $thumbUrl : null,
            url: '/blog/' . $article->slug,
        );
    }
}
