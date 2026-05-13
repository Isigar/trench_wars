<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\MatchMvp;
use App\Models\MatchResult;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
*/

it('creates a valid result via factory', function (): void {
    $result = MatchResult::factory()->create();
    expect($result->exists)->toBeTrue();
    expect($result->allies_score)->toBe(4);
    expect($result->axis_score)->toBe(1);
});

it('enforces 1:1 cardinality (match_id UNIQUE) at the DB layer', function (): void {
    $match = GameMatch::factory()->create();

    MatchResult::factory()->create(['match_id' => $match->id]);

    expect(fn () => MatchResult::factory()->create(['match_id' => $match->id]))
        ->toThrow(QueryException::class);
});

it('rejects a negative allies_score via match_results_scores_nonneg_check', function (): void {
    expect(fn () => MatchResult::factory()->create(['allies_score' => -1]))
        ->toThrow(QueryException::class);
});

it('rejects a negative axis_score via match_results_scores_nonneg_check', function (): void {
    expect(fn () => MatchResult::factory()->create(['axis_score' => -1]))
        ->toThrow(QueryException::class);
});

it('accepts NULL scores (column nullable)', function (): void {
    $result = MatchResult::factory()->create([
        'allies_score' => null,
        'axis_score' => null,
    ]);
    expect($result->allies_score)->toBeNull();
    expect($result->axis_score)->toBeNull();
});

it('exposes match, winnerClan, recordedBy BelongsTo relations + mvps HasMany', function (): void {
    $match = GameMatch::factory()->create();
    $clan = Clan::factory()->create();
    $recorder = User::factory()->create();
    $result = MatchResult::factory()->create([
        'match_id' => $match->id,
        'winner_clan_id' => $clan->id,
        'recorded_by_user_id' => $recorder->id,
    ]);
    $mvp = MatchMvp::factory()->create(['match_result_id' => $result->id]);

    $reloaded = $result->fresh();
    expect($reloaded->match?->id)->toBe($match->id);
    expect($reloaded->winnerClan?->id)->toBe($clan->id);
    expect($reloaded->recordedBy?->id)->toBe($recorder->id);
    expect($reloaded->mvps->pluck('id')->all())->toContain($mvp->id);
});

it('cascades from match → result → mvps on delete', function (): void {
    $match = GameMatch::factory()->create();
    $result = MatchResult::factory()->create(['match_id' => $match->id]);
    $mvp = MatchMvp::factory()->create(['match_result_id' => $result->id]);

    $resultId = $result->id;
    $mvpId = $mvp->id;

    $match->delete();

    expect(MatchResult::where('id', $resultId)->exists())->toBeFalse();
    expect(MatchMvp::where('id', $mvpId)->exists())->toBeFalse();
});

it('keeps the result row when the winner clan is force-deleted (FK nullOnDelete)', function (): void {
    // Clan uses SoftDeletes, so $clan->delete() only sets deleted_at — the DB-level
    // FK cascade fires only on a hard DELETE. forceDelete() bypasses SoftDeletes
    // and proves the migration's nullOnDelete contract on winner_clan_id.
    $clan = Clan::factory()->create();
    $result = MatchResult::factory()->create(['winner_clan_id' => $clan->id]);

    $clan->forceDelete();

    expect($result->fresh()->winner_clan_id)->toBeNull();
});
