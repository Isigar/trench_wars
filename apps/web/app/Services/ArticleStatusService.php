<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidArticleStatusTransitionException;
use App\Models\Article;
use App\Models\User;

/**
 * Source: 07-06-PLAN.md Task 1 + 07-RESEARCH.md (CMS state machine).
 *
 * Encodes the Phase 7 article-lifecycle state machine:
 *
 *     draft ──► scheduled ──► published
 *       │           │             │
 *       │           └─► draft     └─► draft   (unpublish — super-admin only,
 *       │                                       policy-enforced; service does
 *       │                                       NOT check the actor)
 *       └─► published
 *
 * Allowed transitions:
 *   draft     -> scheduled | published
 *   scheduled -> published | draft         (unschedule = back to draft)
 *   published -> draft                     (unpublish path)
 *
 * On transition TO published: published_at = now(), scheduled_at = null.
 * On all other transitions: status only.
 *
 * Causer captured via $from = $a->status BEFORE update — Phase 4 D-04-04-A
 * precedent that prevents getOriginal() drift. The $causer parameter is
 * accepted for signature parity with TournamentStatusService (D-06-04-A) but
 * service-layer logging via activity_log is owned by the model's LogsActivity
 * trait (07-03): the trait fires on update() automatically so this service
 * stays focused on state-machine validity.
 *
 * Threat refs:
 *   - T-07-06-01 (re-publish loop) — observer enforces "first transition only";
 *     this service is the lower defence layer (illegal pair rejection).
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class ArticleStatusService
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'draft' => ['scheduled', 'published'],
        'scheduled' => ['published', 'draft'],
        'published' => ['draft'],
    ];

    /**
     * Transition $a from its current status to $to.
     *
     * @throws InvalidArticleStatusTransitionException When (from, to) is not in ALLOWED.
     */
    public function transition(Article $a, string $to, ?User $causer = null): Article
    {
        unset($causer); // accepted for signature parity; LogsActivity owns the audit row

        $from = $a->status;
        $allowed = self::ALLOWED[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArticleStatusTransitionException($from, $to);
        }

        $updates = ['status' => $to];
        if ($to === 'published') {
            $updates['published_at'] = now();
            $updates['scheduled_at'] = null;
        }

        $a->update($updates);

        /** @var Article $fresh */
        $fresh = $a->fresh();

        return $fresh;
    }
}
