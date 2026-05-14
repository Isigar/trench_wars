<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-11 (worker failure handling) + plan 08-08
| (manual_entry_required flag on GameMatch). SC-4 — CRCON failure modes (unreachable
| on session open, mid-match log gap, key rotated) degrade gracefully: match is
| flagged for manual entry, admin sees `audit.rcon_unreachable` event, manual
| override path opens. RESEARCH § Failure Handling matches D-019.
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('rcon unreachable on session open flags match manual_entry_required=true and emits audit event', function (): void {
    expect(true)->toBeFalse();
});
