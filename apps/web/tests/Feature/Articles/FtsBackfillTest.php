<?php

declare(strict_types=1);

/*
| Plan 07-02 task 2 — replaces the Wave 0 RED stub.
|
| Asserts that the Postgres BEFORE INSERT OR UPDATE triggers on articles,
| clans, and players keep their `search_vector` tsvector column in sync
| regardless of the writer (here we use DB::table() raw inserts because
| Eloquent models for articles + categories don't land until plan 07-03).
|
| Trigger source: 2026_05_15_120300_add_fts_to_articles_clans_players.php.
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function fts_make_category(): string
{
    $id = (string) Str::uuid();
    DB::table('categories')->insert([
        'id' => $id,
        'slug' => 'news-' . Str::random(8),
        'name' => json_encode(['en' => 'News']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('populates articles.search_vector via trigger on raw insert', function (): void {
    $categoryId = fts_make_category();
    $articleId = (string) Str::uuid();

    DB::table('articles')->insert([
        'id' => $articleId,
        'slug' => 'rifleman-tactics-guide',
        'category_id' => $categoryId,
        'title' => json_encode(['en' => 'Rifleman Tactics Guide']),
        'excerpt' => json_encode(['en' => 'How to dominate the trench as a rifleman.']),
        'body' => json_encode(['en' => 'Long body content goes here.']),
        'status' => 'draft',
        'allow_discord_announce' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vector = DB::table('articles')->where('id', $articleId)->value('search_vector');
    expect($vector)->not->toBeNull();

    $matchCount = DB::table('articles')
        ->where('id', $articleId)
        ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['rifleman'])
        ->count();
    expect($matchCount)->toBe(1);
});

it('refires trigger on update to articles.title', function (): void {
    $categoryId = fts_make_category();
    $articleId = (string) Str::uuid();

    DB::table('articles')->insert([
        'id' => $articleId,
        'slug' => 'fts-update-test',
        'category_id' => $categoryId,
        'title' => json_encode(['en' => 'One alpha']),
        'body' => json_encode(['en' => 'body']),
        'status' => 'draft',
        'allow_discord_announce' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(
        DB::table('articles')->where('id', $articleId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['alpha'])
            ->count()
    )->toBe(1);

    DB::table('articles')->where('id', $articleId)->update([
        'title' => json_encode(['en' => 'Two bravo']),
        'updated_at' => now(),
    ]);

    expect(
        DB::table('articles')->where('id', $articleId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['alpha'])
            ->count()
    )->toBe(0);

    expect(
        DB::table('articles')->where('id', $articleId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['bravo'])
            ->count()
    )->toBe(1);
});

it('populates clans.search_vector for raw-inserted clan', function (): void {
    // Phase 2 seeders don't auto-create clan rows (only ClanTags), so we
    // exercise the trigger via raw insert. We create a fresh owner user
    // inline (RefreshDatabase rolls seeded rows back between tests).
    $ownerUserId = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $ownerUserId,
        'discord_id' => 'fts-owner-' . Str::random(10),
        'username' => 'clan-owner',
        'locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $clanId = (string) Str::uuid();
    DB::table('clans')->insert([
        'id' => $clanId,
        'slug' => 'fts-test-clan-' . Str::random(6),
        'tag' => 'FTS' . Str::random(4),
        'name' => 'Phoenix Battalion',
        'description' => json_encode(['en' => 'An elite rifleman company.']),
        'owner_user_id' => $ownerUserId,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vector = DB::table('clans')->where('id', $clanId)->value('search_vector');
    expect($vector)->not->toBeNull();

    expect(
        DB::table('clans')->where('id', $clanId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['phoenix'])
            ->count()
    )->toBe(1);
});

it('populates players.search_vector via trigger on raw insert', function (): void {
    // Mitigates Pitfall 3 — confirm bypass-Eloquent path still maintains
    // the tsvector. A fresh user is created first because the bot user is
    // already linked to a player via the BotServiceUserSeeder.
    $userId = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $userId,
        'discord_id' => 'fts-test-' . Str::random(10),
        'username' => 'fts-test-username',
        'locale' => 'en',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playerId = (string) Str::uuid();
    DB::table('players')->insert([
        'id' => $playerId,
        'user_id' => $userId,
        'slug' => 'fts-player-' . Str::random(6),
        'display_name' => 'Sergeant Tango',
        'avatar_source' => 'discord',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $vector = DB::table('players')->where('id', $playerId)->value('search_vector');
    expect($vector)->not->toBeNull();

    expect(
        DB::table('players')->where('id', $playerId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['tango'])
            ->count()
    )->toBe(1);
});
