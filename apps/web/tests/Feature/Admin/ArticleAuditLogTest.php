<?php

declare(strict_types=1);

/*
| Source: 07-12-PLAN.md Task 2 — replaces the 07-01 RED stub.
|
| Verifies D-012 (Filament + spatie/activitylog) end-to-end for the Phase 7
| Article + Category models. Closes the audit-log contract for the CMS surface:
|
|   - Article::create writes an activity_log row with log_name='article'
|   - Article status transitions to published write an updated row carrying
|     the status attribute diff (logFillable + logOnlyDirty)
|   - Causer captured when an admin transitions the article (acts under actingAs)
|   - dontLogIfAttributesChangedOnly(['updated_at']) is honoured — pure
|     touch() does NOT write an updated row
|
| Idiomatic alignment: this test mirrors TournamentAuditLogTest (06-13 plan)
| and ActivityLoggedOnAdminMutationsTest (01-14 plan) — both established the
| Activity::query()->where(subject_type/subject_id/event) pattern + the v5
| attribute_changes-column shape (not v4's properties.attributes path).
|
| LogsActivity options on Article (from plan 07-03):
|   ->logFillable() — captures full $fillable diff on every mutation
|   ->logOnlyDirty() — emits only changed attributes
|   ->dontLogIfAttributesChangedOnly(['updated_at']) — suppresses no-op touches
|   ->useLogName('article') — partitions activity_log rows so the audit page
|                              can filter to article events alone
*/

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleStatusService;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

// -----------------------------------------------------------------------------
// 1. Article::create — bare LogsActivity trip + log_name='article'
// -----------------------------------------------------------------------------

it('writes activity_log row on Article::create with log_name=article and event=created', function (): void {
    $article = Article::factory()->create(['status' => 'draft']);

    $activity = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('article')
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class);
});

// -----------------------------------------------------------------------------
// 2. Status flip to published via ArticleStatusService — attribute diff visible
// -----------------------------------------------------------------------------

it('writes activity_log row on status flip to published with attribute diff visible', function (): void {
    $article = Article::factory()->create(['status' => 'draft']);

    app(ArticleStatusService::class)->transition($article, 'published', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('article');

    // v5 attribute_changes shape: ['attributes' => [...], 'old' => [...]] —
    // logFillable() materialises the diff into both keys; logOnlyDirty()
    // suppresses unchanged keys.
    $changes = $activity->attribute_changes->toArray();
    expect($changes)->toHaveKey('attributes');
    expect($changes['attributes'])->toHaveKey('status')
        ->and($changes['attributes']['status'])->toBe('published');
});

// -----------------------------------------------------------------------------
// 3. Status transition causer captured when admin triggers publish
//     (causer_id = currently-authenticated admin via actingAs in beforeEach)
// -----------------------------------------------------------------------------

it('captures causer_user_id when admin triggers publish via ArticleStatusService', function (): void {
    $article = Article::factory()->create(['status' => 'draft']);

    app(ArticleStatusService::class)->transition($article, 'published', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class);
});

// -----------------------------------------------------------------------------
// 4. dontLogIfAttributesChangedOnly(['updated_at']) — pure touch() writes no row
// -----------------------------------------------------------------------------

it('does NOT write activity_log row when only updated_at changes (timestamp-only touch)', function (): void {
    $article = Article::factory()->create(['status' => 'draft']);

    $beforeCount = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->count();

    // ->touch() forces an updated_at bump without changing any other column;
    // logOnlyDirty + dontLogIfAttributesChangedOnly should suppress the row.
    $article->touch();

    $afterCount = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount)->toBe($beforeCount);
});

// -----------------------------------------------------------------------------
// 5. Single fillable change writes exactly one updated row (logOnlyDirty fidelity)
// -----------------------------------------------------------------------------

it('writes exactly one activity_log row per real fillable change (logOnlyDirty fidelity)', function (): void {
    $article = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    $beforeCount = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->count();

    $article->update(['allow_discord_announce' => false]);

    $afterCount = Activity::query()
        ->where('subject_type', Article::class)
        ->where('subject_id', $article->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1);
});
