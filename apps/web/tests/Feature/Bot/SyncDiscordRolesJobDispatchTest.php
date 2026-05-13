<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 05-06 (SyncDiscordRolesJob + observer wave).
| Source: 05-01-PLAN.md task 2 + 05-VALIDATION.md Per-Plan Coverage Map.
| SC-4 — ClanMembershipObserver dispatches SyncDiscordRolesJob on membership created
| (action=add) and on membership ended / soft-deleted (action=remove); asserts the
| job lands on the Horizon queue with proper payload (user_id, clan.discord_role_id).
*/

it('placeholder — replace in plan 05-06', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 05-06.');
});
