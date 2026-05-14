<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-08 (MatchResult manual override lock).
| D-019 — manual override always wins: once an admin commits a manual MatchResult
| (source='manual'), any subsequent RCON match_end event MUST NOT overwrite it.
| The RCON path is the SAFETY NET, not the source of truth when the operator
| has committed otherwise.
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('manual MatchResult locks the row — subsequent rcon match_end event leaves source=manual untouched', function (): void {
    expect(true)->toBeFalse();
});
