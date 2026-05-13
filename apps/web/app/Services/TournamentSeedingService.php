<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SeedingNotAllowedException;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Source: 06-05-PLAN.md Task 1 + 06-RESEARCH.md Pattern (seeding strategies) +
 *         RESOLVED Open Question A4 (reseed only when no MatchResult rows exist).
 *
 * Implements 3 seeding strategies for Phase 6 tournaments:
 *
 *   - 'by_rank'  — deterministic order; v1 uses tournament_participants.created_at
 *                  desc as a proxy for "skill rank" (RESEARCH Assumption A11 — Phase 9
 *                  polish will swap this for ELO-based ranking).
 *   - 'random'   — Faker shuffle (collection->shuffle()).
 *   - 'manual'   — no-op on seed values; admin set them via Filament inline edit
 *                  before calling. Only the participant status flip to 'active' fires.
 *
 * Both seed() and reseed() are wrapped in DB::transaction. The participants query
 * uses lockForUpdate() to serialise concurrent seed() calls on the same tournament
 * (T-06-05-02 mitigation).
 *
 * reseed() flow (5 steps inside one DB::transaction):
 *   1. canReseed() guard — throws SeedingNotAllowedException if false
 *   2. capture previous seeds (clan_id => seed map) for audit
 *   3. statusService->transition($t, 'registering') — seeded → registering back-transition
 *   4. reset participant status to 'registered' + seed to null
 *   5. call seed() inside the same transaction (re-assigns 1..N + flips back to 'active')
 *   6. statusService->transition($t, 'seeded') — re-forward transition
 *   7. emit dedicated "Tournament reseeded" activity_log row with previous + new seed maps
 *
 * The dual status transition routes through TournamentStatusService so both
 * transitions get audited individually (D-04-04-A pattern reused). A separate
 * "Tournament reseeded" activity row captures the seed delta for repudiation
 * mitigation (T-06-05-03).
 *
 * Threat refs:
 *   - T-06-05-01 (reseed after results recorded → standings invalid) — mitigated by canReseed() guard
 *   - T-06-05-02 (concurrent seed() races assigning duplicate seeds)  — mitigated by lockForUpdate() inside DB::transaction
 *   - T-06-05-03 (audit trail loses previous seeds across reseed)     — mitigated by previous_seeds + new_seeds maps in activity_log
 *   - T-06-05-04 (by_rank leaks registration order as "skill")        — accepted (A11; Phase 9 ELO upgrade tracked)
 *   - T-06-05-05 (seed() called outside 'registering' status)         — accepted (caller responsibility)
 *
 * Stateless — auto-resolved by the Laravel container; constructor-injects
 * TournamentStatusService for the reseed back-transition.
 */
final class TournamentSeedingService
{
    public function __construct(
        private readonly TournamentStatusService $statusService,
    ) {}

    /**
     * Seed every 'registered' participant of $tournament with a 1..N integer in
     * the order dictated by $strategy, and flip them to 'active' atomically.
     *
     * Allowed $strategy values: 'by_rank' | 'random' | 'manual'.
     *
     * The caller (Filament admin action in plan 06-11) is responsible for verifying
     * tournament.status === 'registering' before calling — this service does not
     * enforce status (single-responsibility separation; the status service owns the
     * lifecycle).
     */
    public function seed(Tournament $tournament, string $strategy, ?User $causer = null): void
    {
        DB::transaction(function () use ($tournament, $strategy, $causer): void {
            /** @var EloquentCollection<int, TournamentParticipant> $participants */
            $participants = $tournament->participants()
                ->where('status', 'registered')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $ordered = match ($strategy) {
                'by_rank' => $this->orderByRank($participants),
                'random' => $participants->shuffle()->values(),
                'manual' => $participants, // seeds already set by admin; just flip status
                default => throw new \InvalidArgumentException(
                    "Unknown seeding strategy: {$strategy}. Allowed: by_rank | random | manual."
                ),
            };

            $count = 0;
            foreach ($ordered as $i => $participant) {
                if ($strategy === 'manual') {
                    $participant->update(['status' => 'active']);
                } else {
                    $participant->update([
                        'seed' => $i + 1,
                        'status' => 'active',
                    ]);
                }
                $count++;
            }

            activity()
                ->causedBy($causer ?? auth()->user())
                ->performedOn($tournament)
                ->withProperties([
                    'strategy' => $strategy,
                    'participant_count' => $count,
                ])
                ->log('Tournament seeded');
        });
    }

    /**
     * Re-seed an already-seeded tournament. Enforces canReseed() guard
     * (Open Question A4 RESOLVED) and routes through TournamentStatusService for
     * the seeded → registering back-transition.
     *
     * @throws SeedingNotAllowedException When Tournament::canReseed() returns false.
     */
    public function reseed(Tournament $tournament, string $strategy, ?User $causer = null): void
    {
        if (! $tournament->canReseed()) {
            throw new SeedingNotAllowedException(__('tournaments.errors.reseed_not_allowed'));
        }

        DB::transaction(function () use ($tournament, $strategy, $causer): void {
            // 1. Capture previous seeds (by clan_id for stable identity across re-seed).
            $previousSeeds = $tournament->participants()
                ->whereNotNull('seed')
                ->get()
                ->mapWithKeys(fn (TournamentParticipant $p): array => [$p->clan_id => $p->seed])
                ->toArray();

            // 2. Status: seeded → registering (back-transition via TournamentStatusService).
            $this->statusService->transition($tournament, 'registering', $causer);

            // 3. Reset participants back to 'registered' so seed() picks them up.
            $tournament->participants()
                ->whereIn('status', ['active'])
                ->update(['status' => 'registered', 'seed' => null]);

            // 4. Re-seed (transitions participants back to 'active' and assigns new seeds).
            $this->seed($tournament->refresh(), $strategy, $causer);

            // 5. Status: registering → seeded (re-forward transition).
            $this->statusService->transition($tournament->refresh(), 'seeded', $causer);

            // 6. Audit: dedicated activity row capturing the seed delta.
            $newSeeds = $tournament->participants()
                ->whereNotNull('seed')
                ->get()
                ->mapWithKeys(fn (TournamentParticipant $p): array => [$p->clan_id => $p->seed])
                ->toArray();

            activity()
                ->causedBy($causer ?? auth()->user())
                ->performedOn($tournament)
                ->withProperties([
                    'strategy' => $strategy,
                    'previous_seeds' => $previousSeeds,
                    'new_seeds' => $newSeeds,
                ])
                ->log('Tournament reseeded');
        });
    }

    /**
     * v1 by_rank: order participants by created_at desc as a deterministic proxy for skill rank.
     * RESEARCH Assumption A11 — future Phase 9 polish swaps this for ELO-based ranking.
     *
     * @param  EloquentCollection<int, TournamentParticipant>  $participants
     * @return Collection<int, TournamentParticipant>
     */
    private function orderByRank(EloquentCollection $participants): Collection
    {
        return $participants->sortByDesc('created_at')->values();
    }
}
