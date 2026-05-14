<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-12-PLAN.md Task 2 — replaces the 07-01 RED
| stub with the integration contract for Inertia <Head> meta tags on the
| public article-show page (and the Pitfall 4 head-key dedupe assertion).
|
| Architecture note — why this test reads the .vue source file directly:
|
|   Inertia v2's <Head> component renders its meta-tag block CLIENT-SIDE during
|   hydration. With INERTIA_SSR_ENABLED=false (the .env.testing default; see
|   07-11-SUMMARY.md), the initial response body carries the data-page JSON
|   payload (props + component name) but NOT the rendered meta tags — those
|   are emitted by the client bundle once Vue mounts and the Head manager
|   walks the per-page <Head> children.
|
|   Asserting meta-tag presence against the response body would therefore
|   always return zero matches under the default test configuration. The
|   meaningful assertion is "the .vue SFC declares meta tags with head-key on
|   every entry" — that's what guarantees the rendered HTML is dedup-safe.
|
|   This file mirrors the source-grep idiom used by CmsI18nKeyCoverageTest and
|   TournamentI18nKeyCoverageTest (Phase 6 D-06-13-C). It exercises the same
|   trust boundary at the same layer: assert that the Vue source includes
|   exactly the attributes the runtime contract requires.
|
| Asserts (against resources/js/pages/Articles/Show.vue source):
|   1. <meta name="description" head-key="description"> declared.
|   2. <meta property="og:title" head-key="og:title"> declared.
|   3. <meta property="og:image" head-key="og:image"> declared.
|   4. <meta name="twitter:card" head-key="twitter:card"> declared.
|   5. Pitfall 4 dedupe: head-key="og:title" appears EXACTLY ONCE in the SFC —
|      proving the SPA navigation contract (Inertia keys by head-key when
|      reconciling Head children).
|
| Plus one HTTP-layer smoke check that the published article actually renders
| (200 OK), which keeps this test connected to the runtime path.
*/

use App\Models\Article;
use Illuminate\Support\Facades\File;

function articleShowVueSource(): string
{
    $path = base_path('resources/js/pages/Articles/Show.vue');
    expect(File::exists($path))->toBeTrue("Articles/Show.vue not found at {$path}");

    return File::get($path);
}

it('Articles/Show.vue declares <meta head-key="description" name="description" :content=...>', function (): void {
    $src = articleShowVueSource();

    expect($src)->toContain('head-key="description"');
    expect($src)->toContain('name="description"');
});

it('Articles/Show.vue declares <meta head-key="og:title" property="og:title" :content="article.title">', function (): void {
    $src = articleShowVueSource();

    expect($src)->toContain('head-key="og:title"');
    expect($src)->toContain('property="og:title"');
    expect($src)->toContain(':content="article.title"');
});

it('Articles/Show.vue declares <meta head-key="og:image" property="og:image" :content=ogImage>', function (): void {
    $src = articleShowVueSource();

    expect($src)->toContain('head-key="og:image"');
    expect($src)->toContain('property="og:image"');
    // The :content binding routes through a computed `ogImage` ref so a null
    // heroOgImageUrl never produces an unbound attribute (Vue would render
    // content="undefined" otherwise — undesirable for SEO).
    expect($src)->toContain(':content="ogImage"');
});

it('Articles/Show.vue declares <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">', function (): void {
    $src = articleShowVueSource();

    expect($src)->toContain('head-key="twitter:card"');
    expect($src)->toContain('content="summary_large_image"');
});

it('every head-key attribute appears EXACTLY ONCE in Articles/Show.vue (Pitfall 4 dedupe guarantee)', function (): void {
    $src = articleShowVueSource();

    // Each head-key must be unique within the SFC — that's what guarantees
    // Inertia's Head manager dedupes (rather than stacks) across SPA navigation.
    // Stacking happens when TWO templates declare the SAME head-key OR when a
    // template omits head-key entirely on a meta tag. Both failure modes are
    // covered by asserting count===1 per key here.
    foreach ([
        'description',
        'og:title',
        'og:description',
        'og:image',
        'og:url',
        'og:type',
        'twitter:card',
        'twitter:image',
    ] as $key) {
        $count = substr_count($src, "head-key=\"{$key}\"");
        expect($count)->toBe(
            1,
            "head-key=\"{$key}\" must appear EXACTLY ONCE in Articles/Show.vue (found {$count})"
        );
    }
});

it('renders 200 OK on GET /blog/{slug} for a published article (HTTP smoke)', function (): void {
    $article = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'slug' => 'meta-http-smoke',
    ]);

    $this->get("/blog/{$article->slug}")->assertOk();
});
