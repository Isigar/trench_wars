<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-08 (MatchPlayerStatAggregator + MatchResult
| auto-populate on match_end with source='rcon'). Asserts intent of
| REQ-goal-rcon-history (per-match history + per-player stats from RCON) and
| SC-3 (booked match runs → events streamed → match_end auto-populates MatchResult).
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('match_end CRCON event auto-populates MatchResult with source=rcon', function (): void {
    expect(true)->toBeFalse();
});
