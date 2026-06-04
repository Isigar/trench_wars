<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-06-PLAN.md <interfaces>
 *         + 06-RESEARCH.md Pitfall 4 (row-locked materialiser).
 *
 * Bridges Phase 6 (tournament_brackets) and Phase 4 (matches + match_slots).
 *
 * For each round-1 bracket with BOTH participants assigned (skipping byes),
 * this service:
 *   1. Spawns a GameMatch row (host_clan_id=NULL per A10 LOCKED; status='open';
 *      organiser + game_match_type + title inherited from the parent Tournament).
 *   2. Calls Phase 4 MatchSlotMaterialiserService::materialise($match) to spawn
 *      the slot grid from the GameMatchType.roleLimits — exact same code path
 *      that SC-1 Phase 4 plan 04-09 uses; no behaviour duplication.
 *   3. Atomically writes bracket.match_id = $match->id inside the same
 *      DB::transaction.
 *
 *  Concurrency (Pitfall 4 + T-06-06-02):
 *    materialiseFor() row-locks the TournamentBracket inside DB::transaction.
 *    Concurrent admin clicks on "Materialise round" or future round-2 triggers
 *    serialise on the lock; the early return on `if (locked.match_id !== null)`
 *    makes the call idempotent — second call returns the existing GameMatch
 *    instead of creating a duplicate. The DB partial UNIQUE on
 *    tournament_brackets.match_id WHERE NOT NULL (plan 06-02 migration) is the
 *    second line of defence.
 *
 *  Idempotency for materialiseFirstRound():
 *    The whereNull('match_id') filter excludes brackets already materialised.
 *    Re-running on the same tournament is safe — only un-materialised brackets
 *    get processed (also covered by the per-bracket lockForUpdate gate).
 *
 *  Title inheritance (D-013 + Phase 4 translatable):
 *    bracket-spawned GameMatch.title is seeded with tournament->getTranslations('title')
 *    — every locale present on the tournament is carried forward. Admin can
 *    override per-bracket via Filament inline edit later (out of scope for plan 06-06).
 *
 *  scheduled_at handling:
 *    The Phase 4 matches table requires a non-null scheduled_at. We default to
 *    tournament.starts_at if present; otherwise we use now()->addDay() as a
 *    placeholder (admin must edit before publishing). This is a v1 simplification
 *    — Phase 7+ wires per-bracket scheduling. The plan's <interfaces> scaffold
 *    referenced scheduled_start_at + scheduled_end_at but the Phase 4 schema
 *    ships only the single `scheduled_at` column (Rule 3 alignment with actual
 *    migration 2026_05_14_100000_create_matches_table.php).
 *
 *  is_public inheritance:
 *    bracket-spawned GameMatch inherits tournament.is_public.
 *
 * Threat refs:
 *   - T-06-06-02 (bracket → GameMatch race) — mitigated by lockForUpdate + idempotent return
 *   - T-06-06-06 (host_clan_id=null on bracket GameMatch) — accept (A10 LOCKED; bracket matches have no host clan)
 *
 * Stateless — auto-resolved by the Laravel container; constructor-injects
 * MatchSlotMaterialiserService (Phase 4 plan 04-05).
 */
final class BracketMatchMaterialiserService
{
    public function __construct(
        private readonly MatchSlotMaterialiserService $slotMaterialiser,
    ) {}

    /**
     * Materialise GameMatch + slot grid for every round-1 bracket of $tournament
     * that has BOTH participants assigned and no match yet.
     *
     * Byes (participant_b_id IS NULL) are skipped — the bracket already has
     * winner_participant_id pre-assigned by SingleEliminationGenerator; no
     * GameMatch is needed because no actual play happens.
     *
     * Idempotent: brackets with non-null match_id are filtered out at the query
     * level + locked-and-checked at the per-bracket level (defence-in-depth).
     */
    public function materialiseFirstRound(Tournament $tournament): void
    {
        $stageIds = $tournament->stages()->pluck('id');

        $brackets = TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->where('round_number', 1)
            ->whereNotNull('participant_a_id')
            ->whereNotNull('participant_b_id')
            ->whereNull('match_id')
            ->get();

        foreach ($brackets as $bracket) {
            $this->materialiseFor($bracket, $tournament);
        }
    }

    /**
     * Materialise a single bracket. Idempotent — returns the existing GameMatch
     * if bracket.match_id is already set. Row-locks the TournamentBracket inside
     * DB::transaction for Pitfall 4 race mitigation.
     *
     * Pre-conditions:
     *   - $bracket has both participant_a_id + participant_b_id assigned (a bye
     *     has no GameMatch — caller filters them out via materialiseFirstRound).
     *   - Either the bracket's stage has game_match_type_id set OR the owning
     *     tournament has a non-null default_game_match_type_id (TOUR-04).
     *
     * @throws RuntimeException if both stage.game_match_type_id and tournament.default_game_match_type_id are null
     */
    public function materialiseFor(TournamentBracket $bracket, ?Tournament $tournament = null): GameMatch
    {
        return DB::transaction(function () use ($bracket, $tournament): GameMatch {
            /** @var TournamentBracket $locked */
            $locked = TournamentBracket::query()
                ->whereKey($bracket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->match_id !== null) {
                /** @var GameMatch $existing */
                $existing = $locked->match()->firstOrFail();

                return $existing;
            }

            // $tournament arg is optional for the SC-1 happy path
            // (materialiseFirstRound always passes it). For ad-hoc callers we
            // resolve via the stage's tournament; firstOrFail guards against
            // an orphaned bracket (impossible under the migration FKs but
            // PHPStan-correct).
            $t = $tournament;
            if ($t === null) {
                /** @var Tournament $tFromStage */
                $tFromStage = $locked->stage()->firstOrFail()->tournament()->firstOrFail();
                $t = $tFromStage;
            }

            // TOUR-04: stage-level GameMatchType override.
            // Prefer the bracket's stage game_match_type_id over the tournament default.
            // If both are null, throw with an extended message covering both sources.
            $stage = $locked->stage()->first();
            $stageOverrideId = ($stage !== null) ? $stage->game_match_type_id : null;
            $effectiveMatchTypeId = $stageOverrideId ?? $t->default_game_match_type_id;

            if ($effectiveMatchTypeId === null) {
                throw new RuntimeException(
                    "Tournament {$t->id} has no default_game_match_type_id and stage has no override — cannot materialise bracket GameMatch."
                );
            }

            $match = GameMatch::create([
                'organiser_user_id' => $t->organiser_user_id,
                'host_clan_id' => null,                    // A10 LOCKED — bracket matches have no host clan
                'game_match_type_id' => $effectiveMatchTypeId,
                'status' => 'open',                        // signups open automatically
                'scheduled_at' => $t->starts_at ?? now()->addDay(),
                'title' => $t->getTranslations('title'),    // D-013 — inherit tournament title (JSONB locales)
                'is_public' => $t->is_public,
            ]);

            $this->slotMaterialiser->materialise($match);

            $locked->update(['match_id' => $match->id]);

            return $match;
        });
    }
}
