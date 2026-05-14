<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\Conversions\Conversion;

/*
| Source: .planning/phases/07-cms/07-03-PLAN.md task 2(c). Replaces the Wave 0
| ArticleModelTest absence — plan 07-01 only stubbed PublicArticleDataTest +
| ArticleObserverTest, the model test is new in this plan.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): no namespace,
| no per-file uses() — Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature').
*/

it('round-trips translatable title via HasTranslations', function (): void {
    $article = Article::factory()->create();

    $article->setTranslation('title', 'en', 'Rifleman Tactics Guide');
    $article->save();

    $reloaded = $article->fresh();
    expect($reloaded?->getTranslation('title', 'en'))->toBe('Rifleman Tactics Guide');

    // DB-side jsonb verified directly — confirms HasTranslations writes JSONB
    // shape `{"en": "..."}` rather than a flat string.
    $raw = DB::table('articles')->where('id', $article->id)->value('title');
    expect(json_decode((string) $raw, true))->toBe(['en' => 'Rifleman Tactics Guide']);
});

it('round-trips translatable excerpt + body via HasTranslations', function (): void {
    $article = Article::factory()->create([
        'excerpt' => ['en' => 'Quick summary.'],
        'body' => ['en' => ['type' => 'doc', 'content' => []]],
    ]);

    $reloaded = $article->fresh();
    expect($reloaded?->getTranslation('excerpt', 'en'))->toBe('Quick summary.');
    expect($reloaded?->getTranslation('body', 'en'))->toBe(['type' => 'doc', 'content' => []]);
});

it('attaches an image to the hero media collection', function (): void {
    Storage::fake('public');
    $article = Article::factory()->create();

    $file = File::image('hero.jpg', 1600, 900);
    $article->addMedia($file->getPathname())
        ->preservingOriginal()
        ->toMediaCollection('hero');

    $first = $article->fresh()?->getFirstMedia('hero');
    expect($first)->not->toBeNull();
    expect($first?->collection_name)->toBe('hero');
});

it('relates to a category via BelongsTo', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'News']]);
    $article = Article::factory()->create(['category_id' => $category->id]);

    expect($article->category)->toBeInstanceOf(Category::class);
    expect($article->category?->id)->toBe($category->id);
    expect($article->category?->getTranslation('name', 'en'))->toBe('News');
});

it('relates to an author via BelongsTo nullable', function (): void {
    $noAuthor = Article::factory()->create();
    expect($noAuthor->author)->toBeNull();

    $user = User::factory()->create();
    $withAuthor = Article::factory()->create(['author_user_id' => $user->id]);

    expect($withAuthor->author)->toBeInstanceOf(User::class);
    expect($withAuthor->author?->id)->toBe($user->id);
});

it('exposes events() MorphMany pointing at the polymorphic events table', function (): void {
    $article = Article::factory()->create();

    expect($article->events())->toBeInstanceOf(MorphMany::class);
    expect($article->events()->getMorphClass())->toBe(Article::class);

    // Insert a polymorphic Event row directly (observer wiring lands in plan
    // 07-06; this plan only verifies the relation resolves the row).
    Event::create([
        'eventable_type' => Article::class,
        'eventable_id' => $article->id,
        'starts_at' => now(),
        'title' => ['en' => 'Article event smoke'],
        'is_public' => true,
    ]);

    expect($article->fresh()?->events->count())->toBe(1);
});

it('writes an activity_log row on Article::create with log_name=article', function (): void {
    $article = Article::factory()->create();

    $exists = Activity::query()
        ->where('log_name', 'article')
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});

it('writes an activity_log row on Article::update (logOnlyDirty)', function (): void {
    $article = Article::factory()->create(['status' => 'draft']);
    $article->update(['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);

    $exists = Activity::query()
        ->where('log_name', 'article')
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->exists();

    expect($exists)->toBeTrue();
});

it('uses slug as the route key', function (): void {
    $article = Article::factory()->create(['slug' => 'rifleman-tactics-guide']);

    expect($article->getRouteKeyName())->toBe('slug');
    expect($article->getRouteKey())->toBe('rifleman-tactics-guide');
});

it('casts allow_discord_announce to bool and scheduled_at / published_at to datetime', function (): void {
    $article = Article::factory()->create([
        'allow_discord_announce' => true,
        'scheduled_at' => '2026-06-01 12:00:00',
        'published_at' => '2026-06-02 09:30:00',
    ]);

    $reloaded = $article->fresh();
    expect($reloaded?->allow_discord_announce)->toBeTrue();
    expect($reloaded?->scheduled_at)->toBeInstanceOf(Carbon::class);
    expect($reloaded?->published_at)->toBeInstanceOf(Carbon::class);
});

it('registers 3 media conversions all on the hero collection', function (): void {
    $article = Article::factory()->create();

    // Drive the InteractsWithMedia trait's collector: registerAllMediaConversions
    // populates $article->mediaConversions with the Conversion objects authored
    // by registerMediaConversions(). Reading the public array sidesteps the
    // ConversionCollection morphMap lookup (which is a runtime media-resolution
    // concern, not a test concern).
    $article->registerAllMediaConversions();

    /** @var array<int, Conversion> $conversions */
    $conversions = $article->mediaConversions;

    $names = collect($conversions)->map(fn ($c): string => $c->getName())->all();
    expect($names)->toContain('thumb');
    expect($names)->toContain('hero');
    expect($names)->toContain('og-image');

    // Every conversion is bound to the 'hero' collection.
    foreach ($conversions as $conversion) {
        expect($conversion->shouldBePerformedOn('hero'))->toBeTrue();
    }
});

it('throws LogicException from toSitemapTag (deferred to plan 07-12)', function (): void {
    $article = Article::factory()->create();

    expect(fn () => $article->toSitemapTag())
        ->toThrow(LogicException::class, 'Sitemapable implementation lands in plan 07-12');
});
