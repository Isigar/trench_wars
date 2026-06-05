<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md <interfaces> PublicArticleData block
 * + .planning/phases/07-cms/07-05-PLAN.md task 2 (tiptap_converter wiring).
 *
 * Visitor-safe Article projection consumed by the public /news + /news/{slug}
 * Vue pages (plan 07-09) AND the article-announce Discord outbound (plan 07-10).
 *
 * Plan 07-05 update: fromModel() now FULLY WIRES tiptap_converter()->asHTML for
 * $bodyHtml. The previous bodyHtml='' marker is removed. The tiptap-php parser
 * registers the same extension set as the editor's 'default' profile (no iframe-
 * bearing nodes — Pitfall 10 mitigation continues to hold end-to-end).
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
     * Plan 07-05: bodyHtml is now wired via tiptap_converter()->asHTML, which
     * accepts the JSONB Tiptap document (string|array) and renders it to safe
     * HTML. The converter's parser registers the same node/mark set as the
     * editor's 'default' profile — no iframe/script extensions are loaded, so
     * any author-inserted iframe node would be silently dropped at parse time
     * (Pitfall 10 mitigation chain — defence-in-depth alongside the editor
     * toolbar allowlist and the TiptapOutput::Json storage format).
     *
     * Locale resolution: title + excerpt + categoryName fall back to 'en' when
     * the active locale lacks a translation (D-013). body is resolved by index
     * lookup on the translations array (HasTranslations stores per-locale arrays).
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

        // Body JSONB → HTML via tiptap_converter (Pitfall 10 end-to-end mitigation).
        // getTranslations('body') returns ['en' => ..., 'cs' => ..., ...]; we pick
        // the active locale with 'en' fallback, then feed the raw value (string or
        // array) into asHTML which handles both.
        $bodyTranslations = $article->getTranslations('body');
        $bodyValue = $bodyTranslations[$locale] ?? $bodyTranslations['en'] ?? [];
        /** @var string|array<mixed>|null $bodyValue */
        $bodyHtml = tiptap_converter()->asHTML($bodyValue);

        return new self(
            id: $article->id,
            slug: $article->slug,
            title: $article->getTranslation('title', $locale, useFallbackLocale: true),
            excerpt: $article->getTranslation('excerpt', $locale, useFallbackLocale: true) ?: null,
            bodyHtml: $bodyHtml,
            categoryName: $article->category?->getTranslation('name', $locale, useFallbackLocale: true) ?? '',
            authorName: $article->author?->username,
            heroThumbUrl: $thumbUrl !== '' ? $thumbUrl : null,
            heroOgImageUrl: $ogImageUrl !== '' ? $ogImageUrl : null,
            publishedAt: $publishedAt?->toIso8601String(),
            allowDiscordAnnounce: $article->allow_discord_announce,
            url: '/blog/' . $article->slug,
        );
    }
}
