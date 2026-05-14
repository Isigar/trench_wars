<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-08 (/leaderboards page hits a query
| budget of ≤4 queries via eager loading + cached aggregates). Asserts intent
| of SC-4 (query budgets).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1352): "/leaderboards runs ≤4 queries".
*/

test('Wave 0 stub: /leaderboards page runs at most 4 queries (Model::shouldBeStrict + cache)', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-08');
})->skip('Wave 0 stub — turned GREEN in plan 09-08');
