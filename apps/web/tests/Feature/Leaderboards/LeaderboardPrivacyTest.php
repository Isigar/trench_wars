<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-06 (public leaderboard page applies
| PlayerPrivacy::show_stats=false filter). Asserts intent of SC-2 (privacy)
| AND D-018 (per-section + global tier player privacy).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1344): "Player with show_stats=false renders anonymously on /leaderboards".
*/

test('Wave 0 stub: player with show_stats=false renders anonymously on /leaderboards', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-06');
})->skip('Wave 0 stub — turned GREEN in plan 09-06');
