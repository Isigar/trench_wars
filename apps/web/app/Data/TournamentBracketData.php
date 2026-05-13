<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\TournamentBracket;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         TournamentBracketData (admin shape — distinct from BracketNodeData
 *         which is the SVG render projection).
 *
 * Admin-side bracket row. Carries the raw FK columns + advance pointers verbatim
 * for Filament resources / admin JSON inspectors. The SVG renderer reads
 * BracketNodeData instead (compact + status-derived).
 */
#[TypeScript]
final class TournamentBracketData extends Data
{
    public function __construct(
        public string $id,
        public string $tournament_stage_id,
        public int $round_number,
        public int $position,
        public ?string $participant_a_id,
        public ?string $participant_b_id,
        public ?string $winner_participant_id,
        public ?string $match_id,
        public ?string $advances_to_bracket_id,
        public ?string $loser_advances_to_bracket_id,
    ) {}

    public static function fromModel(TournamentBracket $bracket): self
    {
        return new self(
            id: $bracket->id,
            tournament_stage_id: $bracket->tournament_stage_id,
            round_number: $bracket->round_number,
            position: $bracket->position,
            participant_a_id: $bracket->participant_a_id,
            participant_b_id: $bracket->participant_b_id,
            winner_participant_id: $bracket->winner_participant_id,
            match_id: $bracket->match_id,
            advances_to_bracket_id: $bracket->advances_to_bracket_id,
            loser_advances_to_bracket_id: $bracket->loser_advances_to_bracket_id,
        );
    }
}
