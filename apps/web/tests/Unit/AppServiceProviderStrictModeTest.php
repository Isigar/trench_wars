<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-08 (AppServiceProvider::boot enables
| Model::shouldBeStrict in non-production environments). Asserts intent of
| SC-4 (N+1 strict).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1351): "App boot enables Model::shouldBeStrict() in non-production".
*/

test('Wave 0 stub: AppServiceProvider enables Model::shouldBeStrict in non-production environments', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-08');
})->skip('Wave 0 stub — turned GREEN in plan 09-08');
