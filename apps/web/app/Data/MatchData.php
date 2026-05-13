<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\GameMatch;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 9 (DTO: MatchData) +
 *         04-07-PLAN.md <interfaces> MatchData snippet.
 *
 * Admin-facing Match DTO. Carries the full field surface including admin-only
 * `organiser_user_id` + `server_address`. The public visitor projection lives in
 * `PublicMatchData` (this file's privacy-stripped sibling).
 *
 * Translatable JSONB fields (`title`, `description`) surface as
 * `Record<string, string> | null` in TypeScript — the `?: null` null-coalesce
 * pattern (Phase 3 Pitfall 4) collapses empty arrays to null so Vue's
 * `v-if="match.title !== undefined"` contract works.
 *
 * Naming binding D-04-03-A LOCKED: the model class is `App\Models\GameMatch`
 * (not `Match` — that token is a fully reserved PHP keyword since 8.0). Direct
 * `use App\Models\GameMatch;` per the canonical Phase 4 idiom established in
 * D-04-06-D — no Pitfall 5 alias needed.
 */
#[TypeScript]
final class MatchData extends Data
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
        public string $organiser_user_id,
        public ?string $host_clan_id,
        public ?string $server_address,
    ) {}

    /**
     * Build a MatchData from a GameMatch Eloquent model.
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
            organiser_user_id: $match->organiser_user_id,
            host_clan_id: $match->host_clan_id,
            server_address: $match->server_address,
        );
    }
}
