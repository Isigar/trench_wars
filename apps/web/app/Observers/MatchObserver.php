<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Event;
use App\Models\GameMatch;

/**
 * Source: .planning/phases/04-matches-manual/04-08-PLAN.md Task 1 +
 *         04-RESEARCH.md § Pattern 8 (polymorphic Event sync).
 *
 * Keeps the polymorphic `events` row coherent with `matches.is_public` and
 * `matches.status` so the public `/matches` calendar (plan 04-10/11) and the
 * future Phase 7 unified `/events` calendar (Tournaments + Matches) read from
 * a single denormalised Event source without polymorphic JOINs.
 *
 * NAMING (D-04-03-A LOCKED + D-04-07-C): the owner model is `App\Models\GameMatch`,
 * NOT `App\Models\Match` — `match` is a reserved PHP 8 keyword. Pattern 8 in
 * 04-RESEARCH.md predates the rename and aliases `App\Models\Match as MatchModel`;
 * Phase 4 implementations use `GameMatch` directly (matches Match*Service idiom).
 *
 * Threat refs:
 *   - T-04-08-01 Cancelled match retains Event row → saved() deletes when status=cancelled
 *   - T-04-08-02 events_one_per_owner UNIQUE violation → updateOrCreate is idempotent
 *   - T-04-08-03 events.title cache drift from Match.title edit → saved() overwrites every save
 *
 * Pitfall 12 caveat: bulk updates (`GameMatch::query()->update(...)`) bypass model events
 * and therefore this observer. Filament's standard EditAction uses `$model->save()` which
 * fires the saved event correctly. Do not add bulk publish/cancel actions; iterate models.
 */
class MatchObserver
{
    /**
     * Upsert/delete the Event row to mirror the match's public+non-cancelled state.
     *
     * Runs inside the same DB::transaction as the model save (Eloquent default), so an
     * outer rollback discards this write too.
     */
    public function saved(GameMatch $match): void
    {
        $shouldHaveEvent = $match->is_public && $match->status !== 'cancelled';

        if ($shouldHaveEvent) {
            Event::updateOrCreate(
                ['eventable_type' => GameMatch::class, 'eventable_id' => $match->id],
                [
                    'starts_at' => $match->scheduled_at,
                    'ends_at' => null,
                    'title' => $match->getTranslations('title'),
                    'is_public' => $match->is_public,
                ],
            );

            return;
        }

        Event::where('eventable_type', GameMatch::class)
            ->where('eventable_id', $match->id)
            ->delete();
    }

    /**
     * Hard-delete cascade: events table has no FK on the polymorphic owner, so the
     * observer is the only cleanup path. RefreshDatabase resets between tests.
     */
    public function deleted(GameMatch $match): void
    {
        Event::where('eventable_type', GameMatch::class)
            ->where('eventable_id', $match->id)
            ->delete();
    }
}
