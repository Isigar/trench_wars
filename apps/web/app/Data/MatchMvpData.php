<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\MatchMvp;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_mvps) +
 *         04-07-PLAN.md <interfaces> MatchMvpData block.
 *
 * Per-category MVP entry within a MatchResult. Categories are one of
 * {'kills','defense','objective','mvp'} — DB CHECK `match_mvps_category_check`
 * enforces the enum at the storage layer.
 *
 * `value` is nullable (category 'mvp' carries no scalar value).
 */
#[TypeScript]
final class MatchMvpData extends Data
{
    public function __construct(
        public string $id,
        public string $match_result_id,
        public string $player_id,
        public string $category,
        public ?int $value,
    ) {}

    /**
     * Build a MatchMvpData from a MatchMvp Eloquent model.
     */
    public static function fromModel(MatchMvp $mvp): self
    {
        return new self(
            id: $mvp->id,
            match_result_id: $mvp->match_result_id,
            player_id: $mvp->player_id,
            category: $mvp->category,
            value: $mvp->value,
        );
    }
}
