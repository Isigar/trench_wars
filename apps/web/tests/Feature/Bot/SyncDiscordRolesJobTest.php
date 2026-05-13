<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 05-06 (SyncDiscordRolesJob + observer wave).
| Source: 05-01-PLAN.md task 2 + 05-VALIDATION.md Per-Plan Coverage Map.
| SC-4 — SyncDiscordRolesJob writes a discord_outbound_messages row of
| message_type=role_sync (payload: user_id, role_id, action=add|remove) for the bot
| outbound poller to execute against the Discord REST API. Idempotent — Discord
| returns 204 whether role was added or already present.
*/

it('placeholder — replace in plan 05-06', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 05-06.');
});
