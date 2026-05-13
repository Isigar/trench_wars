<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MatchResult;
use App\Services\BracketAdvancementService;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 2 +
 *         06-RESEARCH.md § Pattern 7 (Option A — observer over inline call).
 *
 * Fires BracketAdvancementService::advance() on every relevant MatchResult save.
 * The service itself short-circuits for non-tournament matches (no bracket
 * links to the result's match_id), so this observer can fire unconditionally
 * for tournament-match results AND non-tournament-match results — the cost
 * is one extra SELECT against tournament_brackets per save.
 *
 * NAMING (D-04-03-A): MatchResult is the canonical class name (it's a
 * separate model from GameMatch — GameMatch holds the schedule + status;
 * MatchResult holds the final score + winner_clan_id + recorded_at).
 *
 * Threat refs:
 *   - T-06-08-01 (concurrent advance() race) — mitigated inside the service
 *     by DB::transaction + Tournament::lockForUpdate.
 *   - Pitfall 12 caveat: bulk updates (`MatchResult::query()->update(...)`)
 *     bypass model events and therefore this observer. Filament's EditAction
 *     uses `$model->save()` which fires the appropriate created/updated
 *     event. Do not add bulk score-edit actions; iterate models if a batch
 *     operation is needed.
 *
 * Two-hook pattern — `created()` + `updated()` instead of `saved()`:
 *   - `saved()` fires for both inserts AND plain `touch()` calls. On the
 *     Laravel version pinned here, fresh inserts emit getChanges()=[] AND
 *     touch() emits getChanges()=[] AND wasRecentlyCreated stays true on
 *     the same instance forever. There is no reliable flag combination on
 *     `saved` that distinguishes a fresh insert from a touch on a
 *     previously-recently-created instance.
 *   - Eloquent fires `created` only on the actual insert and `updated` only
 *     when at least one attribute was dirty at save time. Plain touch()
 *     (no dirty attributes) emits neither `created` nor `updated` —
 *     exactly the gate we need.
 *   - `updated()` additionally re-checks wasChanged() for the relevant
 *     attribute set so unrelated edits (e.g., recorded_by_user_id swap)
 *     do not re-fire advance().
 *
 * Side-effect: Filament inline editing of `notes` or `recorded_by_user_id`
 * does NOT re-trigger advance() — only changes to score/winner/recorded_at
 * cause re-propagation. This matches the user's expectation that fixing a
 * typo in the notes field should not reset the bracket tree.
 */
class MatchResultObserver
{
    /**
     * Fresh-insert hook. Fires once for newly-created MatchResult rows.
     *
     * Eloquent guarantees `created` does NOT fire for plain timestamp-only
     * touch() calls (touch() updates updated_at on an existing row → fires
     * `saved` only, NOT `created`/`updated`). Using the dedicated `created`
     * hook means no wasRecentlyCreated/getChanges disambiguation is needed
     * for the insert path.
     */
    public function created(MatchResult $result): void
    {
        if ($result->winner_clan_id === null) {
            return;
        }

        app(BracketAdvancementService::class)->advance($result);
    }

    /**
     * Update hook. Fires only when at least one dirty attribute was persisted
     * (Eloquent does NOT fire `updated` for touch() with no dirty fields).
     *
     * Even so, we gate on `wasChanged()` for the relevant attributes so an
     * unrelated edit (e.g., recorded_by_user_id reassignment) does not trigger
     * a redundant advance() — the bracket tree already settled when the score
     * was first recorded.
     */
    public function updated(MatchResult $result): void
    {
        if ($result->winner_clan_id === null) {
            return;
        }

        $relevantChange = $result->wasChanged([
            'winner_clan_id',
            'allies_score',
            'axis_score',
            'recorded_at',
        ]);

        if (! $relevantChange) {
            return;
        }

        app(BracketAdvancementService::class)->advance($result);
    }
}
