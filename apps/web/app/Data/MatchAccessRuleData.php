<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\MatchAccessRule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 5 (tag access) +
 *         04-07-PLAN.md <interfaces> MatchAccessRuleData block.
 *
 * Allowlist rule — when zero rules exist for a match the match is open to all
 * eligible players (D-04-06-C). When >=1 rules exist, the signup service compares
 * the user's active-clan tags against the rules' clan_tag_id (D-008 + Pattern 5).
 *
 * Eager-load aware: when the `clanTag` relation is loaded the nested DTO is
 * populated; otherwise null. Mirrors the Phase 3 GameData->roles eager-load
 * pattern.
 */
#[TypeScript]
final class MatchAccessRuleData extends Data
{
    public function __construct(
        public string $id,
        public string $match_id,
        public string $clan_tag_id,
        public ?ClanTagData $clan_tag,
    ) {}

    /**
     * Build a MatchAccessRuleData from a MatchAccessRule Eloquent model.
     *
     * Requires `clanTag` to be eager-loaded for the nested DTO to be populated —
     * an unloaded relation surfaces as null (no lazy-load N+1).
     */
    public static function fromModel(MatchAccessRule $rule): self
    {
        $clanTag = $rule->relationLoaded('clanTag') && $rule->clanTag !== null
            ? ClanTagData::fromModel($rule->clanTag)
            : null;

        return new self(
            id: $rule->id,
            match_id: $rule->match_id,
            clan_tag_id: $rule->clan_tag_id,
            clan_tag: $clanTag,
        );
    }
}
