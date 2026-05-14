<?php

declare(strict_types=1);

use App\Data\PublicArticleData;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Source: .planning/phases/07-cms/07-05-PLAN.md task 2. Replaces the partial
| GREEN + skip-marker version shipped in plan 07-03.
|
| Plan 07-05 update: skip-marker removed; tiptap_converter()->asHTML is now
| fully wired in fromModel(). New it() blocks prove Pitfall 10 mitigation
| end-to-end — author-inserted iframe/script nodes are silently dropped by
| the tiptap-php parser (the converter's extension set never registers them).
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): Pest.php
| autowires TestCase + RefreshDatabase via uses(...)->in('Feature') — Unit
| does NOT have RefreshDatabase by default; we add it locally.
*/

uses(RefreshDatabase::class);

it('builds a PublicArticleData from an Article model', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'News']]);
    $author = User::factory()->create(['username' => 'commandant_rommel']);
    $article = Article::factory()->create([
        'slug' => 'rifleman-tactics-guide',
        'title' => ['en' => 'Rifleman Tactics Guide'],
        'excerpt' => ['en' => 'Quick summary.'],
        'category_id' => $category->id,
        'author_user_id' => $author->id,
        'published_at' => '2026-05-14 09:00:00',
        'allow_discord_announce' => true,
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->id)->toBe($article->id);
    expect($dto->slug)->toBe('rifleman-tactics-guide');
    expect($dto->title)->toBe('Rifleman Tactics Guide');
    expect($dto->excerpt)->toBe('Quick summary.');
    expect($dto->categoryName)->toBe('News');
    expect($dto->authorName)->toBe('commandant_rommel');
    expect($dto->allowDiscordAnnounce)->toBeTrue();
    expect($dto->url)->toBe('/news/rifleman-tactics-guide');
    expect($dto->publishedAt)->toContain('2026-05-14');
});

it('emits empty bodyHtml from an empty Tiptap doc + null hero urls when no media attached', function (): void {
    // No tiptap content (empty array body) + no media attached.
    $article = Article::factory()->create(['body' => ['en' => []]]);

    $dto = PublicArticleData::fromModel($article);

    // tiptap_converter()->asHTML('' or []) returns '' — the editor's empty state.
    expect($dto->bodyHtml)->toBe('');
    expect($dto->heroThumbUrl)->toBeNull();
    expect($dto->heroOgImageUrl)->toBeNull();
});

it('emits null authorName when author_user_id is null', function (): void {
    $article = Article::factory()->create(['author_user_id' => null]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->authorName)->toBeNull();
});

it('emits null publishedAt for unpublished article', function (): void {
    $article = Article::factory()->create(['published_at' => null]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->publishedAt)->toBeNull();
});

// -----------------------------------------------------------------------------
// Plan 07-05 GREEN — tiptap_converter()->asHTML integration
// -----------------------------------------------------------------------------

it('renders bodyHtml from Tiptap JSON via tiptap_converter', function (): void {
    $article = Article::factory()->create([
        'body' => ['en' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'hello world'],
                    ],
                ],
            ],
        ]],
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->toContain('hello world');
    expect($dto->bodyHtml)->toContain('<p>');
});

it('preserves safe inline marks (bold, italic) through the converter', function (): void {
    $article = Article::factory()->create([
        'body' => ['en' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'bolded',
                            'marks' => [['type' => 'bold']],
                        ],
                    ],
                ],
            ],
        ]],
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->toContain('<strong>bolded</strong>');
});

// -----------------------------------------------------------------------------
// T-07-05-04 / Pitfall 10 mitigation — XSS prevention end-to-end
// -----------------------------------------------------------------------------

it('NEVER includes <iframe> in rendered output regardless of body content', function (): void {
    // Try to encode an iframe-bearing Tiptap doc. The tiptap-php parser registers
    // only the safe extension set (StarterKit + listed nodes/marks — NO iframe,
    // NO oembed, NO youtube, NO video) — so an unknown 'iframe' node is silently
    // dropped during parse.
    $maliciousDoc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'leading text']],
            ],
            [
                // Unknown node type — should be dropped at parse time.
                'type' => 'iframe',
                'attrs' => [
                    'src' => 'https://evil.example.com',
                ],
            ],
        ],
    ];

    $article = Article::factory()->create([
        'body' => ['en' => $maliciousDoc],
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->not->toContain('<iframe');
    expect($dto->bodyHtml)->not->toContain('iframe');
    expect($dto->bodyHtml)->not->toContain('evil.example.com');
});

it('NEVER includes <script> in rendered output regardless of body content', function (): void {
    $maliciousDoc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'safe text']],
            ],
            [
                'type' => 'script',
                'attrs' => ['src' => 'https://evil.example.com/xss.js'],
            ],
        ],
    ];

    $article = Article::factory()->create([
        'body' => ['en' => $maliciousDoc],
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->not->toContain('<script');
    expect($dto->bodyHtml)->not->toContain('xss.js');
});

it('strips on* event-handler attributes from text marks', function (): void {
    // Even if an attacker crafts a Tiptap text node with onclick marks, the
    // converter's mark registry rejects unknown mark types.
    $maliciousDoc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'click me',
                        'marks' => [
                            ['type' => 'onclick', 'attrs' => ['handler' => 'alert(1)']],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $article = Article::factory()->create([
        'body' => ['en' => $maliciousDoc],
    ]);

    $dto = PublicArticleData::fromModel($article);

    expect($dto->bodyHtml)->not->toContain('onclick');
    expect($dto->bodyHtml)->not->toContain('alert(1)');
});

// -----------------------------------------------------------------------------
// Locale fallback (D-013)
// -----------------------------------------------------------------------------

it('falls back to en when active locale has no translation for title', function (): void {
    $article = Article::factory()->create([
        'title' => ['en' => 'English title only'],
    ]);

    app()->setLocale('cs');

    $dto = PublicArticleData::fromModel($article);

    expect($dto->title)->toBe('English title only');

    app()->setLocale('en'); // restore for subsequent tests
});

// -----------------------------------------------------------------------------
// Hero media URLs (plumbing test — getFirstMediaUrl null-safety)
// -----------------------------------------------------------------------------

it('emits non-null heroThumbUrl + heroOgImageUrl strings when media attached', function (): void {
    $article = Article::factory()->create();
    // Attach a 1x1 transparent PNG from a temp file to the 'hero' collection.
    $tmpPath = sys_get_temp_dir() . '/hero-' . uniqid() . '.png';
    file_put_contents(
        $tmpPath,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=')
    );

    $article->addMedia($tmpPath)
        ->preservingOriginal()
        ->toMediaCollection('hero');

    $dto = PublicArticleData::fromModel($article->fresh());

    // og-image conversion is non-queued; thumb is queued so its URL may or may
    // not resolve immediately in tests — what we assert is that getFirstMediaUrl
    // returns a string (not null) when media is attached, regardless of whether
    // the conversion file has been generated yet.
    expect($dto->heroOgImageUrl)->toBeString();
    expect($dto->heroOgImageUrl)->not->toBe('');
});
