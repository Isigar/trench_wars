<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-12-PLAN.md Task 2 — replaces the 07-01 RED
| stub (expect(true)->toBe(false)) with the full sitemap:generate command
| integration contract.
|
| Asserts:
|   1. The command writes public_path('sitemap.xml').
|   2. The output is well-formed XML with a urlset root element.
|   3. Static routes (/, /clans, /players, /blog, /events) are emitted.
|   4. Published Article URLs are present (status='published' filter).
|   5. Draft Article URLs are absent (T-07-12-02 mitigation).
|   6. Public Tournament URLs are present (is_public=true filter).
|   7. Private Tournament URLs are absent (T-07-12-03 mitigation).
|   8. Individual Player URLs are NEVER emitted (T-07-12-01 — only /players
|      INDEX entry from the static route block; no per-player profile rows).
|   9. The sitemap contains < 50000 URLs (Pitfall 7 horizon — single sitemap.xml
|      suffices for round-1 round per RESEARCH A8 ~1000 URLs total).
|
| Each test re-runs sitemap:generate from a clean slate (RefreshDatabase trait
| in Pest.php) and reads the resulting file with File::get(). The DOMDocument
| pass uses libxml_use_internal_errors so a malformed sitemap surfaces as a
| readable assertion failure rather than a PHP warning.
*/

use App\Models\Article;
use App\Models\Clan;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Ensure we start with a clean file even if a previous test left one behind.
    if (File::exists(public_path('sitemap.xml'))) {
        File::delete(public_path('sitemap.xml'));
    }
});

it('writes public/sitemap.xml when run', function (): void {
    Artisan::call('sitemap:generate');

    expect(File::exists(public_path('sitemap.xml')))->toBeTrue();
});

it('produces well-formed XML with a urlset root element', function (): void {
    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($xml);
    libxml_clear_errors();

    expect($loaded)->toBeTrue('sitemap.xml is not well-formed XML');
    expect($doc->documentElement)->not->toBeNull();
    expect($doc->documentElement?->localName)->toBe('urlset');
});

it('includes static routes (/, /clans, /players, /blog, /events)', function (): void {
    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    foreach (['/clans', '/players', '/matches', '/tournaments', '/blog', '/events'] as $path) {
        // Pest's ->toContain($needle) signature is single-string; the assertion
        // failure message surfaces the actual XML so the missing path is obvious.
        expect(str_contains($xml, $path))->toBeTrue("Sitemap is missing static route {$path}");
    }
    // Root '/' renders as the bare host URL (no trailing path); assert the
    // base URL is present at least once instead of trying to match '<loc>/</loc>'.
    expect($xml)->toContain((string) config('app.url'));
});

it('includes URLs of published articles', function (): void {
    Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'slug' => 'foo-published-article',
    ]);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->toContain('/blog/foo-published-article');
});

it('excludes URLs of draft articles (T-07-12-02 privacy guard)', function (): void {
    Article::factory()->create([
        'status' => 'draft',
        'slug' => 'foo-draft-article',
    ]);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->not->toContain('/blog/foo-draft-article');
});

it('includes URLs of is_public=true tournaments', function (): void {
    Tournament::factory()->create([
        'is_public' => true,
        'slug' => 'public-tournament-x',
    ]);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->toContain('/tournaments/public-tournament-x');
});

it('excludes URLs of is_public=false tournaments (T-07-12-03 privacy guard)', function (): void {
    Tournament::factory()->create([
        'is_public' => false,
        'slug' => 'private-tournament-y',
    ]);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->not->toContain('/tournaments/private-tournament-y');
});

it('does NOT include individual Player URLs (T-07-12-01 privacy guard)', function (): void {
    // Two players exist; the sitemap must list the INDEX page only (`/players`)
    // and NEVER drop a /players/{slug} row that would let crawlers enumerate
    // gated profiles.
    $a = Player::factory()->create(['slug' => 'player-alpha']);
    $b = Player::factory()->create(['slug' => 'player-bravo']);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->not->toContain("/players/{$a->slug}");
    expect($xml)->not->toContain("/players/{$b->slug}");
    // But the index page IS present.
    expect($xml)->toContain('/players');
});

it('includes URLs of clans', function (): void {
    $clan = Clan::factory()->create(['slug' => 'foo-clan']);

    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->toContain('/clans/foo-clan');
    expect($clan->slug)->toBe('foo-clan'); // anchor — silence factory-only assertion warning
});

it('produces fewer than 50000 URLs (Pitfall 7 horizon)', function (): void {
    Artisan::call('sitemap:generate');

    $xml = File::get(public_path('sitemap.xml'));

    $count = substr_count($xml, '<url>');

    // Round-1 horizon: ~1000 URLs (RESEARCH A8); the < 50000 cap is the Spatie
    // single-sitemap.xml limit before SitemapIndex split is required (v2 work).
    expect($count)->toBeLessThan(50000);
    expect($count)->toBeGreaterThanOrEqual(7); // at minimum, the 7 static routes
});
