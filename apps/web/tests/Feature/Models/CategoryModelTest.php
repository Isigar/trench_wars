<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/07-cms/07-03-PLAN.md task 2(d). New in this plan.
|
| Bare Pest functional convention (Phase 5 D-05-01-C canonical): no namespace,
| no per-file uses() — Pest.php autowires TestCase + RefreshDatabase via
| uses(...)->in('Feature').
*/

it('round-trips translatable name via HasTranslations', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Tournament Updates']]);
    $category->setTranslation('name', 'en', 'Tournament Recap');
    $category->save();

    $reloaded = $category->fresh();
    expect($reloaded?->getTranslation('name', 'en'))->toBe('Tournament Recap');

    $raw = DB::table('categories')->where('id', $category->id)->value('name');
    expect(json_decode((string) $raw, true))->toBe(['en' => 'Tournament Recap']);
});

it('exposes articles() HasMany relation', function (): void {
    $category = Category::factory()->create();

    $article1 = Article::factory()->create(['category_id' => $category->id]);
    $article2 = Article::factory()->create(['category_id' => $category->id]);

    $reloaded = $category->fresh();
    expect($reloaded?->articles)->toHaveCount(2);
    expect($reloaded?->articles->pluck('id')->all())->toContain($article1->id);
    expect($reloaded?->articles->pluck('id')->all())->toContain($article2->id);
});

it('enforces UNIQUE on slug at the DB layer', function (): void {
    Category::factory()->create(['slug' => 'news']);

    expect(fn () => Category::factory()->create(['slug' => 'news']))
        ->toThrow(QueryException::class);
});

it('uses slug as the route key', function (): void {
    $category = Category::factory()->create(['slug' => 'match-reports']);

    expect($category->getRouteKeyName())->toBe('slug');
    expect($category->getRouteKey())->toBe('match-reports');
});

it('writes an activity_log row on Category::create with log_name=category', function (): void {
    $category = Category::factory()->create();

    $exists = Activity::query()
        ->where('log_name', 'category')
        ->where('subject_type', Category::class)
        ->where('subject_id', $category->id)
        ->where('event', 'created')
        ->exists();

    expect($exists)->toBeTrue();
});

it('soft-deletes preserve the row (deleted_at set, withTrashed retrieves)', function (): void {
    $category = Category::factory()->create();
    $id = $category->id;

    $category->delete();

    expect(Category::query()->find($id))->toBeNull();
    expect(Category::withTrashed()->find($id))->not->toBeNull();
});
