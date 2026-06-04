<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clan;
use Illuminate\Support\Facades\DB;

/*
| Source: 11-02-PLAN.md Task 1 — EloRatingService GREEN.
|
| Stateless service — auto-resolved by the Laravel container.
| No constructor injection needed (pure math + DB::transaction).
|
| Threat mitigations:
|   T-11-02-01 — ratings derived only from locked clan rows, never from caller-supplied values.
|   T-11-02-02 — DB::transaction + lockForUpdate serialises concurrent applyResult calls.
|   T-11-02-03 — activity log records delta + causer for audit trail.
*/

final class EloRatingService
{
    private const K = 32;

    /**
     * Apply a match result to both clans' Elo ratings.
     *
     * Both clan rows are re-fetched inside a DB::transaction with lockForUpdate
     * so the math operates on serialised, up-to-date ratings (T-11-02-02).
     *
     * The caller is responsible for idempotency (rated_at guard in
     * BracketAdvancementService, plan 11-03). applyResult itself is unconditional.
     *
     * Formula:
     *   Ea = 1 / (1 + 10 ** ((Rb - Ra) / 400))
     *   Sa = 1.0 on win, 0.5 on draw; Sb = 0.0 on win, 0.5 on draw.
     *   newRa = round(Ra + K * (Sa - Ea))  [same delta magnitude applies to Rb].
     */
    public function applyResult(Clan $winner, Clan $loser, bool $draw = false): void
    {
        DB::transaction(function () use ($winner, $loser, $draw): void {
            // Re-fetch both rows under lock so the math uses committed values.
            $w = Clan::query()->whereKey($winner->id)->lockForUpdate()->firstOrFail();
            $l = Clan::query()->whereKey($loser->id)->lockForUpdate()->firstOrFail();

            $Ra = $w->elo_rating;
            $Rb = $l->elo_rating;

            // Expected score for the winner side.
            $Ea = $this->expectedScore($Ra, $Rb);

            // Actual scores: 1/0 for decisive; 0.5/0.5 for draw.
            $Sa = $draw ? 0.5 : 1.0;
            $Sb = $draw ? 0.5 : 0.0;

            $newRa = (int) round($Ra + self::K * ($Sa - $Ea));
            $newRb = (int) round($Rb + self::K * ($Sb - (1.0 - $Ea)));

            $w->update([
                'elo_rating' => $newRa,
                'elo_matches_count' => $w->elo_matches_count + 1,
            ]);

            $l->update([
                'elo_rating' => $newRb,
                'elo_matches_count' => $l->elo_matches_count + 1,
            ]);

            // Audit trail — causer may be null for system-attributed bracket advances.
            activity()
                ->causedBy(auth()->user())
                ->performedOn($w)
                ->withProperties([
                    'winner_id' => $w->id,
                    'loser_id' => $l->id,
                    'draw' => $draw,
                    'winner_rating_before' => $Ra,
                    'loser_rating_before' => $Rb,
                    'winner_rating_after' => $newRa,
                    'loser_rating_after' => $newRb,
                    'delta' => $newRa - $Ra,
                ])
                ->log('Clan Elo updated');
        });
    }

    /**
     * Compute the expected score for player A against player B.
     *
     * E_a = 1 / (1 + 10 ^ ((Rb - Ra) / 400))
     */
    private function expectedScore(int $Ra, int $Rb): float
    {
        return 1.0 / (1.0 + 10.0 ** (($Rb - $Ra) / 400.0));
    }
}
