<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Clan;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;

/**
 * Source: .planning/phases/07-cms/07-12-PLAN.md task 1 + 07-RESEARCH.md Pattern 6.
 *
 * Daily sitemap regeneration for the public surface. Wired to the scheduler in
 * routes/console.php with ->dailyAt('03:00')->onOneServer() — Pitfall 12
 * (Railway multi-replica) mitigation, same idiom as ArticlesPublishScheduled.
 *
 * Privacy posture (T-07-12-01 + T-07-12-02 + T-07-12-03 + T-07-12-08):
 *   - /players is the INDEX page only; individual player URLs are NEVER added
 *     to the sitemap. The D-018 per-section privacy gate would refuse to render
 *     gated player profiles anyway, but the sitemap MUST NOT advertise them.
 *   - Articles: only status='published' rows enumerated.
 *   - Tournaments: only is_public=true rows enumerated.
 *   - Clans: all rows enumerated (no privacy tier in v1 — D-007 directory is
 *     fully public).
 *
 * Sitemapable contract: Article + Clan + Tournament all implement
 * Spatie\Sitemap\Contracts\Sitemapable — their toSitemapTag() methods own the
 * lastmod / changefreq / priority shape. Category is intentionally NOT a
 * Sitemapable in v1 (categories have no public show route — deferred to V2).
 *
 * Pitfall 7 horizon (50K URL limit per spatie/laravel-sitemap):
 *   For round-1 the league has ~1000 URLs total (RESEARCH A8) so a single
 *   sitemap.xml suffices. SitemapIndex split is a v2 concern — tested for
 *   here by the < 50000 count assertion in SitemapGenerateCommandTest.
 */
class SitemapGenerateCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate public_path("sitemap.xml") from published articles + public tournaments + clans';

    public function handle(): int
    {
        Sitemap::create()
            ->add('/')
            ->add('/clans')
            ->add('/players')       // INDEX only — T-07-12-01 privacy guard
            ->add('/matches')
            ->add('/tournaments')
            ->add('/blog')
            ->add('/events')
            ->add(Article::query()->where('status', 'published')->get())
            ->add(Clan::all())
            ->add(Tournament::query()->where('is_public', true)->get())
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml written.');

        return self::SUCCESS;
    }
}
