<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Wave 3 GREEN — replaces Wave 0 RED stub from plan 07-01.
| Source: .planning/phases/07-cms/07-06-PLAN.md Task 2 + 07-RESEARCH.md
|         (DiscordOutboundPayloadBuilder::buildArticleAnnounce contract).
|
| Covers the ArticleObserver → discord_outbound_messages outbox chain at the
| DB-row level + the CHECK constraint extension landed by plan 07-02
| (2026_05_15_120400_extend_discord_outbound_message_types_for_article_announce):
|
|   1. payload shape — embeds[0].title, embeds[0].color=#10B981 (Open Question 6),
|      embeds[0].url=/news/{slug} (canonical permalink — route('blog.show') lands in 07-09)
|   2. CHECK constraint permits article_announce (validates 07-02 migration)
|   3. CHECK constraint REJECTS unknown message_type (regression guard)
|   4. causer_user_id null when no auth user (T-07-06-04 scheduler-driven publish)
|
| Threat refs:
|   - T-07-06-06 (Tampering — CHECK bypass on raw insert): asserted by the
|     "rejects unknown message_type" test below.
*/

beforeEach(function (): void {
    config(['discord.league_announce_channel_id' => '0123456789012345']); // mock snowflake
});

// ---------------------------------------------------------------------------
// Payload shape — Open Question 6 LOCKED (CMS green #10B981)
// ---------------------------------------------------------------------------

it('inserts article_announce row with correct payload shape', function (): void {
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
        'title' => ['en' => 'Tournament Bracket Drama'],
        'excerpt' => ['en' => 'A blow-by-blow of the upper-bracket final.'],
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    /** @var DiscordOutboundMessage $outbound */
    $outbound = DiscordOutboundMessage::where('message_type', 'article_announce')->firstOrFail();
    /** @var array<string, mixed> $payload */
    $payload = $outbound->payload;

    expect($payload)->toHaveKeys(['kind', 'article_id', 'article_slug', 'embeds'])
        ->and($payload['kind'])->toBe('article_announce')
        ->and($payload['article_id'])->toBe($a->id)
        ->and($payload['article_slug'])->toBe($a->slug);

    /** @var array<int, array<string, mixed>> $embeds */
    $embeds = $payload['embeds'];
    expect($embeds)->toHaveCount(1);

    /** @var array<string, mixed> $embed */
    $embed = $embeds[0];
    expect($embed['title'])->toBe('Tournament Bracket Drama')
        ->and($embed['color'])->toBe('#10B981')        // Open Question 6 LOCKED — CMS green
        ->and($embed['url'])->toBeString()
        ->and(str_ends_with((string) $embed['url'], '/news/' . $a->slug))->toBeTrue();
});

it('truncates excerpt to 300 chars in the embed description', function (): void {
    $longExcerpt = str_repeat('A blow-by-blow of the upper-bracket final, ', 20);  // ~860 chars
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
        'excerpt' => ['en' => $longExcerpt],
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    /** @var DiscordOutboundMessage $outbound */
    $outbound = DiscordOutboundMessage::where('message_type', 'article_announce')->firstOrFail();
    /** @var array<string, mixed> $payload */
    $payload = $outbound->payload;
    /** @var array<int, array<string, mixed>> $embeds */
    $embeds = $payload['embeds'];
    /** @var array<string, mixed> $embed */
    $embed = $embeds[0];

    // Str::limit appends an ellipsis '…' as the 3rd arg — embedded description ≤ 301 chars total.
    expect(mb_strlen((string) $embed['description']))->toBeLessThanOrEqual(301);
});

// ---------------------------------------------------------------------------
// CHECK constraint validation (plan 07-02 migration regression guard)
// ---------------------------------------------------------------------------

it('CHECK constraint permits article_announce after plan 07-02 migration', function (): void {
    // Direct DB insert bypasses Eloquent and the observer — the only barrier
    // is the doutmsg_message_type_chk CHECK constraint. The row must land.
    $id = (string) Str::uuid();
    $rowsAffected = DB::table('discord_outbound_messages')->insert([
        'id' => $id,
        'channel_id' => '',
        'message_type' => 'article_announce',
        'status' => 'pending',
        'payload' => json_encode(['kind' => 'article_announce']),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($rowsAffected)->toBeTrue();
    expect(DB::table('discord_outbound_messages')->where('id', $id)->count())->toBe(1);
});

it('CHECK constraint REJECTS unknown message_type', function (): void {
    expect(fn () => DB::table('discord_outbound_messages')->insert([
        'id' => (string) Str::uuid(),
        'channel_id' => '',
        'message_type' => 'article_xyz',  // not in the enum
        'status' => 'pending',
        'payload' => json_encode(['kind' => 'article_xyz']),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// T-07-06-04 — scheduler-driven publish: causer_user_id may be null
// ---------------------------------------------------------------------------

it('causer_user_id nullable when no auth user (scheduler-driven publish)', function (): void {
    Auth::logout();
    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    /** @var DiscordOutboundMessage $outbound */
    $outbound = DiscordOutboundMessage::where('message_type', 'article_announce')->firstOrFail();
    expect($outbound->causer_user_id)->toBeNull();
});

it('causer_user_id populated when a user is authenticated', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $a = Article::factory()->create([
        'status' => 'draft',
        'allow_discord_announce' => true,
    ]);

    $a->update(['status' => 'published', 'published_at' => now()]);

    /** @var DiscordOutboundMessage $outbound */
    $outbound = DiscordOutboundMessage::where('message_type', 'article_announce')->firstOrFail();
    expect($outbound->causer_user_id)->toBe($user->id);
});
