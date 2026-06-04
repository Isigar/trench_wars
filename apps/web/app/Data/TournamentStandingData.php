<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\TournamentStanding;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         TournamentStandingData.
 *
 * Standings row projection. Public per D-018 — every viewer sees standings on
 * the public Standings tab (plan 06-12).
 *
 * `points` + `tiebreak_score` cast as decimal:2 at the model layer → PHP
 * stringifies them by default. We coerce to float here so the TS surface is
 * `number` (not `string`), matching the spreadsheet-style render the Vue
 * component performs.
 */
#[TypeScript]
final class TournamentStandingData extends Data
{
    public function __construct(
        public string $id,
        public string $tournament_id,
        public string $tournament_stage_id,
        public string $participant_id,
        public int $wins,
        public int $losses,
        public int $draws,
        public float $points,
        public float $tiebreak_score,
        public float $median_buchholz,
        public ?int $rank,
    ) {}

    public static function fromModel(TournamentStanding $standing): self
    {
        return new self(
            id: $standing->id,
            tournament_id: $standing->tournament_id,
            tournament_stage_id: $standing->tournament_stage_id,
            participant_id: $standing->participant_id,
            wins: (int) $standing->wins,
            losses: (int) $standing->losses,
            draws: (int) $standing->draws,
            points: (float) $standing->points,
            tiebreak_score: (float) $standing->tiebreak_score,
            median_buchholz: (float) $standing->median_buchholz,
            rank: $standing->rank,
        );
    }
}
