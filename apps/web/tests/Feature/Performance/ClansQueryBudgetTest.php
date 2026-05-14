<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-08 (/clans page hits a query budget
| of ≤8 queries via eager loading sweep). Asserts intent of SC-4 (query budgets).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1353): "/clans runs ≤8 queries".
*/

test('Wave 0 stub: /clans page runs at most 8 queries (Model::shouldBeStrict + eager loading)', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-08');
})->skip('Wave 0 stub — turned GREEN in plan 09-08');
