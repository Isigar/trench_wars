<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\DiscordOutboundPayloadBuilder;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/08-rcon-automation/08-12-PLAN.md must_haves.truths #3 +
 *         <interfaces> buildMatchResultAnnounce block.
 *
 * Canonical OUTPUT shape for the Discord `match_result_announce` outbound payload
 * produced by {@see DiscordOutboundPayloadBuilder::buildMatchResultAnnounce()}
 * after an RCON-sourced MatchResult lands.
 *
 * Per-field rationale:
 *  - `match_id` — surfaced so the bot renderer can resolve thread/channel context
 *                 deterministically; mirrors the buildBracketResult /
 *                 buildTournamentAnnounce shape (Phase 6).
 *  - `allies_score` / `axis_score` — direct CRCON match_end payload values; both
 *                 nullable because some failure paths still announce a partial
 *                 result (manual_entry_required=true) without complete scores.
 *  - `winner_clan_name` — pre-resolved server-side (eager-loaded winnerClan
 *                 relation); null when winner_clan_id is null (round 1 always —
 *                 CRCON's allies/axis labels don't map to clan IDs deterministically).
 *  - `mvps` — top-3 by (kills - deaths) DESC, each entry `{username, kills, deaths}`.
 *             Stable JSON shape lets the bot renderer pick a column or fall back to a
 *             text dump if a list embed-field would overflow Discord's 1024-char limit.
 *
 * **T-08-12-01 mitigation:** the DTO surfaces `username` only — `steam_id_64` is
 * NEVER part of this payload, so a public Discord announce can't accidentally leak
 * a player's Steam ID. Mirrors the contractual privacy boundary enforced by
 * RconBotResultAnnounceTest case 1.
 *
 * `#[TypeScript]` (D-020) — packages/shared-types regen exposes this to the bot's
 * renderer for compile-time parity.
 *
 * @phpstan-type Mvp array{username: string, kills: int, deaths: int}
 */
#[TypeScript]
final class MatchResultAnnounceData extends Data
{
    /**
     * @param  array<int, array{username: string, kills: int, deaths: int}>  $mvps
     */
    public function __construct(
        public string $match_id,
        public ?int $allies_score,
        public ?int $axis_score,
        public ?string $winner_clan_name,
        public array $mvps,
    ) {}
}
