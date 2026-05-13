<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Pattern 8
 *         (SVG bracket renderer) + 06-10-PLAN.md <interfaces> BracketNodeData.
 *
 * Render-shape DTO for one bracket node in the public SVG bracket viewer
 * (plan 06-12). One BracketNodeData per TournamentBracket row.
 *
 * Status state machine (computed in fromModel):
 *   - 'bye'         — participant_a set, participant_b null, winner == participant_a
 *   - 'completed'   — winner_participant_id set AND both participants present
 *   - 'in-progress' — match_id materialised but no winner yet
 *   - 'pending'     — match not yet materialised
 *
 * `stage_type` mirrors TournamentStage.type — one of
 * 'elim'|'winners-bracket'|'losers-bracket'|'grand-final'|'group'|'swiss-round'.
 * The Vue renderer uses it for stage-specific styling (e.g. losers-bracket
 * gets a different tint).
 */
#[TypeScript]
final class BracketNodeData extends Data
{
    public function __construct(
        public string $id,
        public int $round_number,
        public int $position,
        public string $stage_type,
        public ?ParticipantSummary $participant_a,
        public ?ParticipantSummary $participant_b,
        public ?string $winner_participant_id,
        public ?string $match_id,
        public string $status,
        public ?string $scheduled_at,
    ) {}

    /**
     * Build a BracketNodeData from a TournamentBracket model.
     *
     * Requires `stage` + `participantA.clan` + `participantB.clan` + `match`
     * to be loaded for an N+1-free render. PublicTournamentData::fromModel
     * eager-loads them via `stages.brackets.participantA.clan` etc.
     */
    public static function fromModel(TournamentBracket $bracket): self
    {
        $status = self::deriveStatus($bracket);

        /** @var Carbon|null $scheduledAt */
        $scheduledAt = $bracket->match?->scheduled_at;

        $stage = $bracket->stage;
        $stageType = $stage !== null ? $stage->type : 'elim';

        return new self(
            id: $bracket->id,
            round_number: $bracket->round_number,
            position: $bracket->position,
            stage_type: $stageType,
            participant_a: self::participantSummary($bracket->participantA),
            participant_b: self::participantSummary($bracket->participantB),
            winner_participant_id: $bracket->winner_participant_id,
            match_id: $bracket->match_id,
            status: $status,
            scheduled_at: $scheduledAt?->toIso8601String(),
        );
    }

    /**
     * Compact participant projection — preserves the clan name + seed for
     * label rendering. Returns null when the participant isn't assigned to
     * the bracket slot yet (un-materialised round 2+).
     */
    private static function participantSummary(mixed $participant): ?ParticipantSummary
    {
        if ($participant === null) {
            return null;
        }

        /** @var TournamentParticipant $participant */
        $clan = $participant->clan;

        return new ParticipantSummary(
            id: $participant->id,
            clan_name: $clan !== null ? $clan->name : '',
            seed: $participant->seed ?? 0,
        );
    }

    /**
     * 4-state status ladder. The bye case must be checked FIRST — single-elim
     * generators (plan 06-06) auto-fill winner_participant_id = participant_a
     * when participant_b is null, so the winner is set but the bracket never
     * materialises a match_id.
     */
    private static function deriveStatus(TournamentBracket $bracket): string
    {
        if ($bracket->participant_a_id !== null
            && $bracket->participant_b_id === null
            && $bracket->winner_participant_id !== null) {
            return 'bye';
        }

        if ($bracket->winner_participant_id !== null) {
            return 'completed';
        }

        if ($bracket->match_id !== null) {
            return 'in-progress';
        }

        return 'pending';
    }
}
