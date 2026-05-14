<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md <interfaces> PublicArticleData block.
 *
 * Visitor-safe Article projection consumed by the public /news + /news/{slug}
 * Vue pages (plan 07-09) AND the article-announce Discord outbound (plan 07-10).
 *
 * SHAPE-ONLY in this plan — Plan 07-05 fills the fromModel() body with the
 * tiptap_converter integration that resolves the JSONB body array into HTML.
 * For now fromModel() emits $bodyHtml = '' and the test marker covers the
 * TODO. Plan 07-12 uses the same DTO for the sitemap feed.
 *
 * Translatable resolution: title + excerpt fields are resolved to the active
 * locale via getTranslation('field', app()->getLocale()). Locale fallback
 * follows the spatie/laravel-translatable config (D-013 — EN is the canonical
 * fallback for v1).
 *
 * Threat refs: T-07-03-02 (author soft-delete) — authorName is null-safe.
 *
 * @phpstan-consistent-constructor — Spatie\LaravelData\Data accepts the
 * unified constructor signature; fromModel() is a named-args factory.
 */
#[TypeScript]
final class PublicArticleData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public string $bodyHtml,
        public string $categoryName,
        public ?string $authorName,
        public ?string $heroThumbUrl,
        public ?string $heroOgImageUrl,
        public ?string $publishedAt,
        public bool $allowDiscordAnnounce,
        public string $url,
    ) {}

    /**
     * Build a PublicArticleData from an Article Eloquent model.
     *
     * Partial implementation (plan 07-03): emits $bodyHtml='' — plan 07-05
     * swaps in the tiptap_converter()->asHTML() call. Plan 07-12 sitemap
     * feed reuses this factory verbatim once 07-05 lands.
     *
     * Caller must eager-load `category` + `author` for N+1-free hydration.
     */
    public static function fromModel(Article $article): self
    {
        $locale = app()->getLocale();

        /** @var Carbon|null $publishedAt */
        $publishedAt = $article->published_at;

        // Hero conversions — null-safe; getFirstMediaUrl returns '' (not null)
        // when no media exists, so we explicit-null on empty for the JSON shape.
        $thumbUrl = $article->getFirstMediaUrl('hero', 'thumb');
        $ogImageUrl = $article->getFirstMediaUrl('hero', 'og-image');

        return new self(
            id: $article->id,
            slug: $article->slug,
            title: $article->getTranslation('title', $locale, useFallbackLocale: true),
            excerpt: $article->getTranslation('excerpt', $locale, useFallbackLocale: true) ?: null,
            bodyHtml: '', // TODO plan 07-05 — tiptap_converter()->asHTML($article->getTranslation('body', $locale))
            categoryName: $article->category?->getTranslation('name', $locale, useFallbackLocale: true) ?? '',
            authorName: $article->author?->username,
            heroThumbUrl: $thumbUrl !== '' ? $thumbUrl : null,
            heroOgImageUrl: $ogImageUrl !== '' ? $ogImageUrl : null,
            publishedAt: $publishedAt?->toIso8601String(),
            allowDiscordAnnounce: $article->allow_discord_announce,
            url: '/news/' . $article->slug,
        );
    }
}
