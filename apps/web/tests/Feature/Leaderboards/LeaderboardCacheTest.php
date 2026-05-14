<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-05 (Cache::tags(['leaderboards'])->remember()
| wraps service calls). Asserts intent of SC-2 (cache) — second call within
| window returns cached result; flush via tag invalidates.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1343): "Second call within window returns cached result; flush via tag invalidates".
*/

test('Wave 0 stub: leaderboard second call within window returns cached result; flush via tag invalidates', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-05');
})->skip('Wave 0 stub — turned GREEN in plan 09-05');
