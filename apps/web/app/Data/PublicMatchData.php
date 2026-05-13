<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\GameMatch;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 7 (privacy projection) +
 *         04-07-PLAN.md <interfaces> PublicMatchData block.
 *
 * Visitor-safe Match projection consumed by /matches/{id} (Matches/Show.vue). Strips
 * admin-only fields from MatchData:
 *   - organiser_user_id (admin-internal — exposed by Filament only)
 *   - server_address (only visible to confirmed signups in a future polish phase)
 *
 * The controller (plan 04-10) is the construction site; Vue receives this DTO verbatim
 * and renders without re-deriving privacy.
 *
 * Threat refs: T-04-07-03 (admin-field leak), T-04-07-05 (cancelled-match filter is a
 * query-layer responsibility — this DTO is a shape, not a filter).
 *
 * Naming binding D-04-03-A LOCKED: the model class is `App\Models\GameMatch` (direct
 * import per Phase 4 canonical idiom D-04-06-D).
 */
#[TypeScript]
final class PublicMatchData extends Data
{
    /**
     * @param  array<string, string>|null  $title
     * @param  array<string, string>|null  $description
     */
    public function __construct(
        public string $id,
        public string $game_match_type_id,
        public ?array $title,
        public ?array $description,
        public string $scheduled_at,
        public string $status,
        public bool $is_public,
        public ?string $host_clan_id,
    ) {}

    /**
     * Build a PublicMatchData from a GameMatch Eloquent model.
     *
     * NOTE: This factory does NOT filter `status` or `is_public` — that is the
     * controller's query-layer responsibility (T-04-07-05). The DTO is a shape;
     * if it's constructed it's already passed the visibility query.
     */
    public static function fromModel(GameMatch $match): self
    {
        /** @var Carbon $scheduledAt */
        $scheduledAt = $match->scheduled_at;

        return new self(
            id: $match->id,
            game_match_type_id: $match->game_match_type_id,
            title: $match->getTranslations('title') ?: null,
            description: $match->getTranslations('description') ?: null,
            scheduled_at: $scheduledAt->toIso8601String(),
            status: $match->status,
            is_public: $match->is_public,
            host_clan_id: $match->host_clan_id,
        );
    }
}
