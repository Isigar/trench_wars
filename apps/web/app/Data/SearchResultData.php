<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Article;
use App\Models\Clan;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-08-PLAN.md task 1.
 *
 * Polymorphic per-result DTO for the Postgres FTS SearchService UNION pipeline.
 * Three named constructors (fromArticle / fromClan / fromPlayer) build the row
 * from each of the three indexed Eloquent models; the controller (plan 07-09)
 * + the Results.vue page (plan 07-10) consume a unified shape via Spatie Data.
 *
 * The `type` discriminator carries 'article' | 'clan' | 'player' so the Vue
 * consumer can branch on layout (article cards vs clan tags vs player avatars)
 * without re-querying.
 *
 * `rank` is a PHP-side ordinal (0-based descending rank) rather than the raw
 * Postgres ts_rank() float (D-07-08-A — recorded in SUMMARY). The DB-returned
 * ordering already encodes ts_rank DESC via orderByRaw, so the ordinal preserves
 * the same total order without requiring a second SELECT to re-fetch the float.
 * Vue consumers only need the ordering, not the absolute magnitude.
 *
 * Threat refs:
 *   - T-07-08-04 (Information Disclosure — real_name leak): the Player schema
 *     does not currently expose a real_name column (Phase 2 only has
 *     display_name + slug); fromPlayer uses display_name ?? username (via the
 *     User relation), with no real_name path. If a future migration adds
 *     real_name + a show_real_name privacy flag, gate the title interpolation
 *     via $gate->allowsSection($player, $viewer, 'show_real_name').
 */
#[TypeScript]
final class SearchResultData extends Data
{
    public function __construct(
        public string $type,
        public string $id,
        public string $slug,
        public string $title,
        public string $excerpt,
        public string $url,
        public ?string $thumbnailUrl,
        public float $rank,
    ) {}

    /**
     * Build a result row for an Article.
     *
     * `excerpt` resolves via HasTranslations with explicit `useFallbackLocale=true`
     * — articles authored only in English still surface to any locale.
     *
     * `url` uses the literal /news/{slug} path rather than route('blog.show', ...)
     * because the route binding lands in plan 07-09; calling a non-existent
     * named route here would throw RouteNotFoundException at FTS-build time.
     * Plan 07-09 lifts this to route('blog.show', $a->slug) once registered.
     */
    public static function fromArticle(Article $a, float $rank = 0.0): self
    {
        $locale = app()->getLocale();

        $title = $a->getTranslation('title', $locale, useFallbackLocale: true)
            ?: $a->getTranslation('title', 'en', useFallbackLocale: false)
            ?: '';

        $excerpt = $a->getTranslation('excerpt', $locale, useFallbackLocale: true)
            ?: $a->getTranslation('excerpt', 'en', useFallbackLocale: false)
            ?: '';

        $thumbnailUrl = $a->getFirstMediaUrl('hero', 'thumb');

        return new self(
            type: 'article',
            id: (string) $a->id,
            slug: (string) $a->slug,
            title: (string) $title,
            excerpt: (string) $excerpt,
            url: '/news/' . $a->slug,
            thumbnailUrl: $thumbnailUrl !== '' ? $thumbnailUrl : null,
            rank: $rank,
        );
    }

    /**
     * Build a result row for a Clan.
     *
     * `name` is a plain text column (not translatable) per the Phase 2 schema;
     * `description` is the translatable JSONB field. Title is the clan name
     * verbatim; excerpt is the truncated translatable description (200 chars).
     */
    public static function fromClan(Clan $c, float $rank = 0.0): self
    {
        $locale = app()->getLocale();

        $description = $c->getTranslation('description', $locale, useFallbackLocale: true)
            ?: $c->getTranslation('description', 'en', useFallbackLocale: false)
            ?: '';

        $excerpt = mb_substr((string) $description, 0, 200);

        return new self(
            type: 'clan',
            id: (string) $c->id,
            slug: (string) $c->slug,
            title: (string) $c->name,
            excerpt: $excerpt,
            url: route('clans.show', $c->slug),
            thumbnailUrl: null,
            rank: $rank,
        );
    }

    /**
     * Build a result row for a Player.
     *
     * `title` resolves to display_name ?? user.username ?? slug — the same
     * resolution chain PublicPlayerData::fromPlayer uses. There is no real_name
     * column on Player in Phase 2, so the canShowField('show_real_name') gate
     * cited in the plan's <interfaces> code block is forward-compat only —
     * we'd interpolate "display_name (real_name)" once the column lands and
     * the privacy flag is wired. For now title is just the display name.
     *
     * `url` binds to the existing /players/{slug} route (Phase 2 plan 02-07).
     * `thumbnailUrl` is null because players have no medialibrary attachment
     * in Phase 2 — avatar_url is a User-level discord-CDN string and is not
     * surfaced here (the Results.vue page can re-resolve avatar via the
     * separate per-player endpoint if needed).
     */
    public static function fromPlayer(
        Player $p,
        PlayerPrivacyGate $gate,
        ?User $viewer,
        float $rank = 0.0,
    ): self {
        $user = $p->user;
        $title = $p->display_name
            ?? ($user !== null ? $user->username : null)
            ?? $p->slug;

        return new self(
            type: 'player',
            id: (string) $p->id,
            slug: (string) $p->slug,
            title: (string) $title,
            excerpt: '',
            url: route('players.show', $p->slug),
            thumbnailUrl: null,
            rank: $rank,
        );
    }
}
