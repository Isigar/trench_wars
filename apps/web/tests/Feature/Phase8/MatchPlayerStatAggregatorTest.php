<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-08 (MatchPlayerStatAggregator service).
| Asserts that per-player kills/deaths/assists/score/role roll-ups run ONCE on
| match_end (Pitfall 4 anti-pattern: never re-aggregate per event during the match).
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('MatchPlayerStatAggregator rolls up match_events ONCE on match_end into match_player_stats', function (): void {
    expect(true)->toBeFalse();
});
