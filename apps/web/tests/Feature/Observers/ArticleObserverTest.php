<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\DiscordOutboundMessage;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/*
| Wave 3 GREEN — replaces Wave 0 RED stub from plan 07-01.
| Source: .planning/phases/07-cms/07-06-PLAN.md Task 2 + 07-RESEARCH.md.
|
| Covers the two ArticleObserver side-effects:
|   1. syncEvent() — Event MorphOne upsert on created/updated (draft + published)
|   2. onPublish() — article_announce outbound on FIRST transition to published
|
| Pitfall 10 in observer mitigation (T-07-06-01): updated() three-gate fire-once
| guard — wasChanged('status') AND status==='published' AND getOriginal('status')
| !== 'published'. Republish (published → draft → published) does NOT duplicate
| the outbound row.
|
| D-06-08-A two-hook pattern (created + updated, NOT saved) is the Phase 6
| precedent — used verbatim here to prevent double-fire on touch().
*/

beforeEach(function (): void {
    config(['discord.league_announce_channel_id' => '0123456789012345']); // mock snowflake
});

// ---------------------------------------------------------------------------
// syncEvent() — Event MorphOne sync invariants
// ---------------------------------------------------------------------------

it('auto-creates Event MorphOne row when article is created with status=published', function (): void {
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'allow_discord_announce' => false, // suppress announce for this assertion
    ]);

    $event = Event::where('eventable_type', $a->getMorphClass())
        ->where('eventable_id', $a->id)
        ->first();

    expect($event)->not->toBeNull();
    assert($event !== null);
    expect($event->is_public)->toBeTrue()
        ->and($event->eventable_type)->toBe($a->getMorphClass())
        ->and($event->ends_at)->toBeNull();
});

it('auto-creates Event MorphOne row (is_public=false) for a draft article', function (): void {
    // Phase 4 D-04-08-C precedent — drafts keep the Event row with is_public=false
    // so /events calendar feed filters them out without row churn.
    $a = Article::factory()->create(['status' => 'draft']);

    $event = Event::where('eventable_type', $a->getMorphClass())
        ->where('eventable_id', $a->id)
        ->first();

    expect($event)->not->toBeNull();
    assert($event !== null);
    expect($event->is_public)->toBeFalse();
});

it('updates the existing Event row when article title changes', function (): void {
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'allow_discord_announce' => false,
        'title' => ['en' => 'Original Title'],
    ]);

    $a->update(['title' => ['en' => 'Updated Title']]);

    $event = Event::where('eventable_type', $a->getMorphClass())
        ->where('eventable_id', $a->id)
        ->firstOrFail();

    expect($event->getTranslation('title', 'en'))->toBe('Updated Title');
});

it('flips Event.is_public to false when status reverts to draft', function (): void {
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'allow_discord_announce' => false,
    ]);
    expect(Event::where('eventable_id', $a->id)->value('is_public'))->toBeTrue();

    $a->update(['status' => 'draft']);

    expect(Event::where('eventable_id', $a->id)->value('is_public'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// onPublish() — article_announce outbound enqueue
// ---------------------------------------------------------------------------

it('fires article_announce outbound row on first transition to published', function (): void {
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);
    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);

    $a->update(['status' => 'published', 'published_at' => now()]);

    $outbound = DiscordOutboundMessage::where('message_type', 'article_announce')->first();
    expect($outbound)->not->toBeNull();
    assert($outbound !== null);
    expect($outbound->status)->toBe('pending');
});

it('does NOT re-fire announce on republish (Pitfall 10 in observer)', function (): void {
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);
    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(1);

    $a->update(['status' => 'draft']);
    $a->update(['status' => 'published', 'published_at' => now()]);

    // Pitfall 10 guard — second published transition uses the SAME row count.
    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(1);
});

it('does NOT fire announce on title-only change without status flip', function (): void {
    $a = Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'allow_discord_announce' => true,
    ]);
    // Wipe the created-as-published outbound so the assertion is unambiguous.
    DiscordOutboundMessage::query()->delete();

    $a->update(['title' => ['en' => 'Edited Title']]);

    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);
});

it('suppresses announce when allow_discord_announce=false', function (): void {
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => false,
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);
});

it('suppresses announce when league_announce_channel_id is empty', function (): void {
    config(['discord.league_announce_channel_id' => '']);
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// LogsActivity audit trail (D-012)
// ---------------------------------------------------------------------------

it('writes activity_log row on status transition', function (): void {
    // The Article model uses Spatie LogsActivity with log_name='article' (07-03).
    // logFillable() + logOnlyDirty() drives the row write — the properties shape
    // varies across Spatie versions, so this test only asserts row presence under
    // the partition (subject_type + subject_id + log_name + description='updated').
    // The properties.attributes detail is exercised separately by the audit-log
    // page test in plan 07-11.
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => false,
    ]);

    $countBefore = Activity::query()
        ->where('log_name', 'article')
        ->where('subject_type', $a->getMorphClass())
        ->where('subject_id', $a->id)
        ->count();

    $a->update(['status' => 'published', 'published_at' => now()]);

    $countAfter = Activity::query()
        ->where('log_name', 'article')
        ->where('subject_type', $a->getMorphClass())
        ->where('subject_id', $a->id)
        ->count();

    expect($countAfter)->toBeGreaterThan($countBefore);

    $latest = Activity::query()
        ->where('log_name', 'article')
        ->where('subject_type', $a->getMorphClass())
        ->where('subject_id', $a->id)
        ->latest('id')
        ->first();

    expect($latest)->not->toBeNull();
    assert($latest !== null);
    expect($latest->description)->toBe('updated');
});

// ---------------------------------------------------------------------------
// Insert-as-published path (created hook)
// ---------------------------------------------------------------------------

it('fires announce on insert when article is created with status=published', function (): void {
    // Direct admin path or seeder writing status='published' at insert time.
    // The created() hook calls onPublish() directly (no wasChanged() — there is
    // no prior state on insert).
    Article::factory()->create([
        'status' => 'published',
        'published_at' => now(),
        'allow_discord_announce' => true,
    ]);

    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(1);
});

it('does NOT fire announce on insert when article is created with status=draft', function (): void {
    Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Bulk-update bypass (Pitfall 12 — documented; not auto-fixed by observer)
// ---------------------------------------------------------------------------

it('bulk Article::query()->update bypasses the observer (documented limitation)', function (): void {
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    // Bypass model events deliberately — raw query update.
    DB::table('articles')->where('id', $a->id)->update(['status' => 'published']);

    // Observer did NOT fire — Eloquent model events are bypassed by raw queries.
    expect(DiscordOutboundMessage::where('message_type', 'article_announce')->count())->toBe(0);
});
