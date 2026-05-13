<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Pattern 8
 *         (SVG bracket renderer) + 06-10-PLAN.md <interfaces> BracketEdgeData.
 *
 * Render-shape DTO consumed by the SVG bracket renderer (plan 06-12 Vue
 * component). One edge per non-null advances_to_bracket_id /
 * loser_advances_to_bracket_id pointer on a TournamentBracket row.
 *
 * Two edge kinds, encoded by `type`:
 *   - 'winner' — advances_to_bracket_id (single-elim final feed; W-bracket advance)
 *   - 'loser'  — loser_advances_to_bracket_id (double-elim drop chain only)
 *
 * `to_slot` ('a' | 'b') drives which side of the target bracket-node the edge
 * terminates on. PublicTournamentData::fromModel computes this from the source
 * bracket's position parity (odd → 'a', even → 'b') so two siblings advancing
 * to the same parent fill its two slots deterministically.
 */
#[TypeScript]
final class BracketEdgeData extends Data
{
    public function __construct(
        public string $from_bracket_id,
        public string $to_bracket_id,
        public string $to_slot,
        public string $type,
    ) {}
}
