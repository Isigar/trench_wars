<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-03 (DiscordChannel writes a
| discord_outbound_messages row with kind='user_dm' instead of making a direct
| HTTP call). Asserts intent of SC-1 (DiscordChannel) AND Pitfall 3
| (no direct Discord HTTP from web — outbox-only).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1340): "DiscordChannel writes a discord_outbound_messages row with correct payload".
*/

test('Wave 0 stub: DiscordChannel writes discord_outbound_messages row with user_dm kind (no direct HTTP)', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-03');
})->skip('Wave 0 stub — turned GREEN in plan 09-03');
