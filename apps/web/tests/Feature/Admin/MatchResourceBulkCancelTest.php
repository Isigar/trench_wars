<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-07 (MatchResource bulk-cancel
| transitions match status + fans out match_cancelled notifications via
| NotificationDispatcher). Asserts intent of SC-3 (BulkAction match cancel).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1346): "MatchResource bulk-cancel issues match_cancelled notifications".
*/

test('Wave 0 stub: MatchResource bulk-cancel issues match_cancelled notifications to signed-up players', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-07');
})->skip('Wave 0 stub — turned GREEN in plan 09-07');
