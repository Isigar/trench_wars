<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-05 (LeaderboardService::topPlayers
| aggregates match_player_stats by window + page). Asserts intent of SC-2
| (leaderboard players).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1341): "LeaderboardService::topPlayers('7d', 1, 25) returns sorted aggregates".
*/

test('Wave 0 stub: LeaderboardService::topPlayers returns sorted aggregates for 7d window', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-05');
})->skip('Wave 0 stub — turned GREEN in plan 09-05');
