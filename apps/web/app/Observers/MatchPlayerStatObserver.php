<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MatchPlayerStat;
use Illuminate\Support\Facades\Cache;

/**
 * Source: .planning/phases/09-polish/09-05-PLAN.md task 2 +
 *         09-RESEARCH.md § Pattern 3 (cache invalidation).
 *
 * Flushes the `leaderboards` cache tag whenever a MatchPlayerStat row is
 * inserted OR updated. Both code paths legitimately invalidate:
 *   - created: Phase 8 MatchPlayerStatAggregator writes a new row on
 *     match_end (RCON ingest) — adds a new (player, match) tuple that
 *     could enter top-N.
 *   - updated: Phase 4 admin override via Filament inline edit can
 *     re-write kills/deaths — top-N ordering may shift.
 *
 * Using `saved` (rather than separate created+updated) is intentional and
 * matches RESEARCH § Pattern 3 verbatim: both lifecycle paths invalidate,
 * so the consolidation is correct. RESEARCH's anti-pattern note ("don't
 * put Cache::tags->flush in saved unless both created+updated need it")
 * is the exception clause we satisfy here.
 *
 * Idempotency: Cache::tags(...)->flush() is O(1) (deletes the tag epoch
 * marker; existing keys are abandoned, not iterated). Repeated flushes
 * from a batch upsert (Phase 8 aggregator) are harmless beyond the cost
 * of one Redis call each. The next read repopulates lazily.
 *
 * Pitfall 9 — failure in the *next read*'s compute callback surfaces via
 * LeaderboardService::safeCompute(); the observer itself does no compute.
 */
final class MatchPlayerStatObserver
{
    public function saved(MatchPlayerStat $stat): void
    {
        Cache::tags(['leaderboards'])->flush();
    }
}
