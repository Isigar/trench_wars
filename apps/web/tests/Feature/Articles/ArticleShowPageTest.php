<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-10-PLAN.md task 2.
|
| Replaces the Wave 0 RED stub from plan 07-01.
|
| Covers SC-2 (CMS public detail surface) Vue page contract: GET /blog/{slug}
| renders Inertia 'Articles/Show' for anonymous visitors on PUBLISHED articles;
| draft articles return 404 to anonymous (T-07-09-02 non-disclosure idiom
| already covered at controller-level in ArticleIndexPageTest — re-asserted
| here at HTTP/page-integration layer for defence-in-depth).
|
| Pitfall 10 mitigation chain ENDPOINT-level verification:
|   1. Tiptap editor profile pinned in 07-01 config.
|   2. ->profile('default') on form field in 07-05.
|   3. tiptap_converter()->asHTML server-side render in PublicArticleData (07-05).
|   4. <THIS TEST> asserts at HTTP layer that bodyHtml in the Inertia payload
|      contains no <iframe or <script substring even when the persisted Tiptap
|      JSON tries to encode those nodes — the converter drops them at parse time.
|
| cms-editor draft visibility (ArticlePolicy::view): an actor with the
| articles.update permission can see drafts at /blog/{slug} because the
| controller's abort_unless gates on
| `status==='published' || $user->can('articles.update')`.
|
| Bare Pest convention (Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature')).
*/

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders Articles/Show component for an anonymous visitor at /blog/{slug}', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'public-piece',
        'title' => ['en' => 'Public piece'],
    ]);

    $this->get('/blog/public-piece')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show', false)
                ->has('article')
                ->where('article.slug', 'public-piece')
                ->where('article.title', 'Public piece')
        );
});

it('exposes article.bodyHtml as a non-empty string on a published article with body content', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'with-body',
        'title' => ['en' => 'With body'],
        'body' => [
            'en' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'Visible paragraph copy.']],
                    ],
                ],
            ],
        ],
    ]);

    $this->get('/blog/with-body')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show', false)
                ->has('article.bodyHtml')
                ->where('article.bodyHtml', fn (string $html) => str_contains($html, 'Visible paragraph copy.'))
        );
});

it('NEVER returns <iframe> or <script> in bodyHtml even when the Tiptap doc attempts to encode them (Pitfall 10 HTTP-layer chain verification)', function (): void {
    /*
    | This is the END-TO-END mitigation proof. Plan 07-05's PublicArticleDataTest
    | asserts at the DTO layer; this test asserts at the HTTP/Inertia layer that
    | the full chain (editor profile → tiptap_converter → controller → Inertia
    | prop → response payload) drops iframe/script nodes.
    |
    | Strategy: persist an Article whose body is a Tiptap doc with an iframe
    | node + a script node + an evil URL in attrs. Hit GET /blog/{slug} and
    | assert the response body's article.bodyHtml prop does NOT contain any of
    | those substrings.
    */
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'xss-attempt',
        'title' => ['en' => 'XSS attempt'],
        'body' => [
            'en' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'leading safe text']],
                    ],
                    // Unknown node — tiptap-php parser drops it at parse time
                    // (Tiptap profile registers no iframe/script extensions).
                    [
                        'type' => 'iframe',
                        'attrs' => ['src' => 'https://evil.example.com'],
                    ],
                    [
                        'type' => 'script',
                        'attrs' => ['src' => 'https://evil.example.com/xss.js'],
                    ],
                ],
            ],
        ],
    ]);

    $this->get('/blog/xss-attempt')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show', false)
                ->where('article.bodyHtml', function (string $html): bool {
                    // Defence-in-depth: assert raw HTML tags + the evil URL are absent.
                    return ! str_contains($html, '<iframe')
                        && ! str_contains($html, '<script')
                        && ! str_contains($html, 'evil.example.com');
                })
        );
});

it('returns 404 for a draft article at /blog/{slug} for an anonymous visitor (T-07-09-02 non-disclosure)', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'draft',
        'slug' => 'top-secret-draft',
        'title' => ['en' => 'Top secret draft'],
    ]);

    // Anonymous → 404. NOT 403 — leaking "this slug exists" via 403 would let
    // an attacker enumerate draft slugs; 404 is indistinguishable from a
    // never-published slug (mirrors MatchShowController + TournamentShowController
    // precedent in T-04-10-02 / T-06-12-03).
    $this->get('/blog/top-secret-draft')->assertStatus(404);
});

it('allows a cms-editor with articles.update permission to view their own draft at /blog/{slug}', function (): void {
    // Seed permissions + create a cms-editor user (07-04 PermissionSeeder grants
    // cms-editor the articles.update permission needed by the abort_unless gate).
    $this->seed(PermissionSeeder::class);

    $editor = User::factory()->create();
    $editor->assignRole('cms-editor');
    $editor = $editor->fresh();

    $category = Category::factory()->create();
    Article::factory()
        ->for($category, 'category')
        ->for($editor, 'author')
        ->create([
            'status' => 'draft',
            'slug' => 'editor-draft',
            'title' => ['en' => 'Editor draft'],
        ]);

    $this->actingAs($editor)
        ->get('/blog/editor-draft')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Show', false)
                ->has('article')
                ->where('article.slug', 'editor-draft')
        );
});

it('emits a public permalink that resolves to a live route (regression: /news 404 → /blog)', function (): void {
    // Guard against the dead-link class where DTOs emitted /news/{slug} but only
    // /blog/{slug} was ever registered. Every producer of the public article URL
    // (search results, og:url meta, Discord announce) must point at a route that 200s.
    $category = Category::factory()->create();
    $article = Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'slug' => 'permalink-resolves',
        'title' => ['en' => 'Permalink resolves'],
    ]);

    // 1. SearchResultData (search results href) + 2. PublicArticleData (og:url meta)
    //    both produce a relative /blog/{slug} path that must resolve.
    $searchUrl = \App\Data\SearchResultData::fromArticle($article)->url;
    $ogUrl = \App\Data\PublicArticleData::fromModel($article)->url;
    expect($searchUrl)->toBe('/blog/permalink-resolves')
        ->and($ogUrl)->toBe('/blog/permalink-resolves');
    $this->get($searchUrl)->assertOk();
    $this->get($ogUrl)->assertOk();

    // 3. Discord announce embed url is absolute but must end in the same live path.
    $announceUrl = \App\Support\DiscordOutboundPayloadBuilder::buildArticleAnnounce($article)['embeds'][0]['url'];
    expect(str_ends_with((string) $announceUrl, '/blog/permalink-resolves'))->toBeTrue();
    $this->get((string) parse_url((string) $announceUrl, PHP_URL_PATH))->assertOk();
});
