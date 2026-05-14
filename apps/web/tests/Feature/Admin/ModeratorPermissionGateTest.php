<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-07 (spatie/laravel-permission
| `moderate` permission gates BulkActions + new resources). Asserts intent
| of SC-3 (permission gates) — non-moderator user cannot access them.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1349): "Non-moderator user cannot access BulkActions or new resources".
*/

test('Wave 0 stub: non-moderator user is blocked from BulkActions and new moderation resources', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-07');
})->skip('Wave 0 stub — turned GREEN in plan 09-07');
