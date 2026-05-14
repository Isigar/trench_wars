<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-09-PLAN.md task 2.
|
| Replaces the Wave 0 RED stub from plan 07-01.
|
| Covers SC-2 (CMS public surface): GET /blog reaches an Inertia 'Articles/Index'
| page for anonymous visitors; only published articles surface; pagination caps
| at 15 per page; ?category=slug filters via Eloquent whereHas; GET /blog/{slug}
| returns 404 for draft articles when the viewer cannot articles.update
| (T-07-09-02 non-disclosure idiom).
|
| Bare Pest functional style (Phase 4/5/6/7 precedent) — no namespace; Pest.php
| autowires TestCase + RefreshDatabase via uses(...)->in('Feature').
*/

use App\Models\Article;
use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;

it('renders Articles/Index Inertia component for an anonymous visitor', function (): void {
    $category = Category::factory()->create(['slug' => 'news', 'name' => ['en' => 'News']]);
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/blog')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Articles/Index', false)
                ->has('articles')
                ->has('categories')
                ->has('pagination')
        );
});

it('only includes published articles in the listing (drafts + scheduled hidden)', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Public release notes'],
    ]);
    Article::factory()->for($category, 'category')->create([
        'status' => 'draft',
        'title' => ['en' => 'Internal draft — do not show'],
    ]);
    Article::factory()->for($category, 'category')->create([
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(2),
        'title' => ['en' => 'Future scheduled — not yet public'],
    ]);

    $this->get('/blog')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page->has('articles', 1)
                ->where('articles.0.title', 'Public release notes')
        );
});

it('paginates with 15 articles per page', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->count(20)->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    // Page 1 — 15 entries.
    $this->get('/blog?page=1')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('articles', 15)
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 2)
                ->where('pagination.perPage', 15)
                ->where('pagination.total', 20)
        );

    // Page 2 — remaining 5 entries.
    $this->get('/blog?page=2')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('articles', 5)
                ->where('pagination.currentPage', 2)
        );
});

it('filters by category slug query param', function (): void {
    $news = Category::factory()->create(['slug' => 'news', 'name' => ['en' => 'News']]);
    $reports = Category::factory()->create(['slug' => 'match-reports', 'name' => ['en' => 'Match Reports']]);

    Article::factory()->for($news, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => ['en' => 'News article'],
    ]);
    Article::factory()->for($reports, 'category')->create([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Match report article'],
    ]);

    $this->get('/blog?category=news')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.title', 'News article')
                ->where('activeCategory', 'news')
        );
});

it('returns 404 for a draft article at /blog/{slug} for anonymous visitor (T-07-09-02)', function (): void {
    $category = Category::factory()->create();
    Article::factory()->for($category, 'category')->create([
        'status' => 'draft',
        'slug' => 'draft-foo',
        'title' => ['en' => 'Top secret draft'],
    ]);

    // Anonymous → 404. 404 (not 403) per T-07-09-02 — non-disclosure idiom.
    $this->get('/blog/draft-foo')->assertStatus(404);
});

it('returns 200 for a published article at /blog/{slug} for anonymous visitor', function (): void {
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
            fn (Assert $page) => $page->component('Articles/Show', false)->has('article')
        );
});
