<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-05 (LeaderboardService::topClans
| aggregates via clan_memberships JOIN). Asserts intent of SC-2 (leaderboard clans).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1342): "LeaderboardService::topClans('30d', 1, 25) aggregates via clan_memberships JOIN".
*/

test('Wave 0 stub: LeaderboardService::topClans aggregates via clan_memberships JOIN for 30d window', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-05');
})->skip('Wave 0 stub — turned GREEN in plan 09-05');
