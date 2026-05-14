<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-05 (LeaderboardService cache invalidation
| via Cache::tags(['leaderboards'])->flush() from model observers). Asserts intent
| of SC-4 (cache strategy).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1354): "Cache::tags(['leaderboards'])->flush() invalidates leaderboard caches".
*/

test('Wave 0 stub: Cache::tags([leaderboards])->flush() invalidates all leaderboard caches', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-05');
})->skip('Wave 0 stub — turned GREEN in plan 09-05');
