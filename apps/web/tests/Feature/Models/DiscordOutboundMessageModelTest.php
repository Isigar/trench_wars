<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 05-02 (migrations + model wave).
| Source: 05-01-PLAN.md task 2 + 05-VALIDATION.md Per-Plan Coverage Map.
| SC-3 — DiscordOutboundMessage model contract: UUID PK, channel_id text,
| message_type enum (match_announce / role_sync / etc), payload JSONB, status enum
| (pending / dispatching / sent / failed), attempts int default 0, last_error nullable
| text, sent_message_id nullable Discord snowflake (text), causer_user_id nullable
| FK to users, timestamps. LogsActivity trait wired (append-only).
*/

it('placeholder — replace in plan 05-02', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 05-02.');
});
