<?php

declare(strict_types=1);

use App\Models\ClanTag;
use App\Models\GameMatch;
use App\Models\MatchAccessRule;
use Illuminate\Database\QueryException;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
*/

it('creates a valid access rule via factory', function (): void {
    $rule = MatchAccessRule::factory()->create();
    expect($rule->exists)->toBeTrue();
});

it('enforces composite UNIQUE (match_id, clan_tag_id)', function (): void {
    $match = GameMatch::factory()->create();
    $tag = ClanTag::factory()->create();

    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    expect(fn () => MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]))->toThrow(QueryException::class);
});

it('allows multiple tags per match', function (): void {
    $match = GameMatch::factory()->create();
    $tagA = ClanTag::factory()->create();
    $tagB = ClanTag::factory()->create();

    MatchAccessRule::factory()->create(['match_id' => $match->id, 'clan_tag_id' => $tagA->id]);
    MatchAccessRule::factory()->create(['match_id' => $match->id, 'clan_tag_id' => $tagB->id]);

    expect(MatchAccessRule::where('match_id', $match->id)->count())->toBe(2);
});

it('exposes match and clanTag BelongsTo relations', function (): void {
    $match = GameMatch::factory()->create();
    $tag = ClanTag::factory()->create();
    $rule = MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    expect($rule->match?->id)->toBe($match->id);
    expect($rule->clanTag?->id)->toBe($tag->id);
});

it('cascades on parent match delete', function (): void {
    $match = GameMatch::factory()->create();
    $rule = MatchAccessRule::factory()->create(['match_id' => $match->id]);
    $ruleId = $rule->id;

    $match->delete();

    expect(MatchAccessRule::where('id', $ruleId)->exists())->toBeFalse();
});
