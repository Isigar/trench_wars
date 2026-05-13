<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 05-04 (BotApi controllers wave).
| Source: 05-01-PLAN.md task 2 + 05-VALIDATION.md Per-Plan Coverage Map.
| SC-4 — bot reports guildMemberUpdate role deltas via /api/bot/discord-events/role-change;
| Laravel suppresses echoes within a 60s window from its own SyncDiscordRolesJob writes
| (do NOT reconcile drift if our job just wrote the role); see bot.errors.echo_suppressed.
*/

it('placeholder — replace in plan 05-04', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 05-04.');
});
