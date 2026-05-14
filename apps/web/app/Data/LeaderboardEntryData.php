<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/09-polish/09-05-PLAN.md task 1 +
 *         09-RESEARCH.md § Pattern 3 + § Leaderboards (privacy gating).
 *
 * DTO for a single row of the `/leaderboards` top-players view (SC-2).
 *
 * D-018 LOCKED — privacy gating happens at DTO factory time, not at the
 * service layer. `LeaderboardService::topPlayers()` returns raw aggregate
 * rows; the controller (plan 09-06) hydrates each row through
 * `LeaderboardEntryData::fromQueryResult($row, $viewer)`. When the viewer
 * cannot see this player's stats, `is_anonymous=true`, the display_name
 * is replaced with `__('leaderboards.anonymous_player')`, and the
 * player_id is replaced with the empty string (Vue layer treats empty
 * player_id as "render label, no link" — plan 09-06).
 *
 * Plan 09-05 deviation note (D-09-05-A): The plan text said the DTO
 * blanks player_id when anonymous. We keep the field shape (always a
 * string) so the Vue v-for :key binding remains stable. Plan 09-06's
 * <PlayerLink> renderer decides whether to wrap the row in an <a>.
 */
#[TypeScript]
final class LeaderboardEntryData extends Data
{
    public function __construct(
        public string $player_id,
        public string $player_name,
        public ?string $clan_name,
        public int $kills,
        public int $deaths,
        public ?float $kdr,
        public int $matches_played,
        public bool $is_anonymous,
    ) {}

    /**
     * Hydrate a DTO from a raw aggregate row + the eager-loaded Player
     * model. The `$row` shape comes from
     * `LeaderboardService::computePlayerLeaderboard()` selectRaw:
     *   { player_id, kills, deaths, kdr, matches_played }
     *
     * The `$player` model MUST be loaded with `player.user.privacy` and
     * (optionally) `player.activeClanMembership.clan` so this factory
     * does not lazy-load (Pattern 6 strict-mode protection).
     */
    public static function fromQueryResult(
        object $row,
        Player $player,
        ?User $viewer,
        ?string $clanName,
    ): self {
        $gate = app(PlayerPrivacyGate::class);

        $canSeeStats = $gate->allowsSection($player, $viewer, 'show_stats');
        $isAnonymous = ! $canSeeStats;

        $playerName = $isAnonymous
            ? __('leaderboards.anonymous_player')
            : (string) ($player->display_name ?? $player->slug);

        // Raw aggregate row from LeaderboardService — dynamic columns.
        // PHPStan-safe property access for L8.
        /** @var array{kills?: int|string|null, deaths?: int|string|null, kdr?: float|string|null, matches_played?: int|string|null} $columns */
        $columns = (array) $row;

        return new self(
            player_id: $isAnonymous ? '' : (string) $player->id,
            player_name: $playerName,
            clan_name: $isAnonymous ? null : $clanName,
            kills: (int) ($columns['kills'] ?? 0),
            deaths: (int) ($columns['deaths'] ?? 0),
            kdr: isset($columns['kdr']) ? (float) $columns['kdr'] : null,
            matches_played: (int) ($columns['matches_played'] ?? 0),
            is_anonymous: $isAnonymous,
        );
    }
}
