<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Clan;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/09-polish/09-05-PLAN.md task 1 +
 *         09-RESEARCH.md § Pattern 3 + § Leaderboards SQL block.
 *
 * DTO for a single row of the `/leaderboards` top-clans view (SC-2).
 *
 * Schema reality (D-09-05-B LOCKED): clans table has no `logo_url`
 * column in v1 — plan 09-09 will introduce medialibrary conversions
 * (Pattern 5). The field is kept on the DTO so the v1 surface is
 * forward-compatible with the WebP variant chain; v1 always emits
 * `logo_url=null`. The Vue layer renders a placeholder when null.
 */
#[TypeScript]
final class LeaderboardClanEntryData extends Data
{
    public function __construct(
        public string $clan_id,
        public string $clan_name,
        public string $clan_slug,
        public ?string $logo_url,
        public int $kills,
        public int $matches_played,
        public int $wins,
    ) {}

    /**
     * Hydrate a DTO from a raw aggregate row + the eager-loaded Clan
     * model. The `$row` shape comes from
     * `LeaderboardService::computeClanLeaderboard()` selectRaw:
     *   { clan_id, kills, matches_played, wins }
     *
     * The `$clan` model MUST be pre-hydrated (the service eager-loads
     * the union of clan_ids in one query via `Clan::whereIn(...)->get()`
     * — Pattern 6 strict-mode protection, no lazy fetch).
     */
    public static function fromQueryResult(object $row, Clan $clan): self
    {
        /** @var array{kills?: int|string|null, matches_played?: int|string|null, wins?: int|string|null} $columns */
        $columns = (array) $row;

        return new self(
            clan_id: (string) $clan->id,
            clan_name: (string) $clan->name,
            clan_slug: (string) $clan->slug,
            // D-09-05-B LOCKED: clans.logo_url does not exist in v1 schema;
            // plan 09-09 will introduce medialibrary WebP conversions. Always
            // null for v1.
            logo_url: null,
            kills: (int) ($columns['kills'] ?? 0),
            matches_played: (int) ($columns['matches_played'] ?? 0),
            wins: (int) ($columns['wins'] ?? 0),
        );
    }
}
