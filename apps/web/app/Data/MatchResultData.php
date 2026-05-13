<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\MatchResult;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_results) +
 *         04-07-PLAN.md <interfaces> MatchResultData block.
 *
 * 1:1 with GameMatch (match_id UNIQUE at DB layer). Scores are nullable —
 * results may be filed before scores are known — but DB CHECK
 * `match_results_scores_nonneg_check` rejects negative integers (T-04-02-04).
 *
 * `recorded_at` is emitted as ISO-8601 string; `notes` is plain text (NOT
 * translatable in P4 — translatable would be added by a future Phase if needed).
 */
#[TypeScript]
final class MatchResultData extends Data
{
    public function __construct(
        public string $id,
        public string $match_id,
        public ?string $winner_clan_id,
        public ?int $allies_score,
        public ?int $axis_score,
        public ?string $notes,
        public string $recorded_by_user_id,
        public string $recorded_at,
    ) {}

    /**
     * Build a MatchResultData from a MatchResult Eloquent model.
     */
    public static function fromModel(MatchResult $result): self
    {
        /** @var Carbon $recordedAt */
        $recordedAt = $result->recorded_at;

        return new self(
            id: $result->id,
            match_id: $result->match_id,
            winner_clan_id: $result->winner_clan_id,
            allies_score: $result->allies_score,
            axis_score: $result->axis_score,
            notes: $result->notes,
            recorded_by_user_id: $result->recorded_by_user_id,
            recorded_at: $recordedAt->toIso8601String(),
        );
    }
}
