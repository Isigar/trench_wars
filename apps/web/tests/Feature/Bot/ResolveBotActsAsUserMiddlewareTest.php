<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 05-03 (middleware + Sanctum abilities wave).
| Source: 05-01-PLAN.md task 2 + 05-VALIDATION.md Per-Plan Coverage Map.
| SC-5 — middleware reads X-Bot-Acts-As-User: <discord_id>, resolves User row, calls
| Auth::onceUsingId so LogsActivity attributes the human causer (not the bot service
| account). Unknown discord_id → 422 bot.errors.acts_as_unknown.
*/

it('placeholder — replace in plan 05-03', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 05-03.');
});
