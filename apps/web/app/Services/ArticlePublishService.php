<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Source: .planning/phases/07-cms/07-07-PLAN.md Task 1 + 07-RESEARCH.md Pattern 4
 *         (Laravel Scheduler for draft→scheduled→published).
 *
 * Promotes Article rows with status='scheduled' AND scheduled_at <= now() to
 * status='published'. Invoked by ArticlesPublishScheduledCommand which is
 * registered in routes/console.php as
 * Schedule::command('articles:publish-scheduled')->everyMinute()
 *     ->withoutOverlapping()->onOneServer()
 * — see Pitfall 12 (everyMinute + multi-replica = duplicate publishes without
 * both guards).
 *
 * Delegation to ArticleStatusService::transition is deliberate: the observer
 * chain (ArticleObserver — sync Event row + enqueue article_announce outbound)
 * fires identically whether the publish was admin-driven or scheduler-driven.
 * A raw $article->update(['status' => 'published']) would also fire the
 * observer, but bypasses the state-machine guard — if a row drifted to an
 * illegal state (e.g. status='archived' via some future hot-path), the
 * scheduler would silently flip it. transition() throws on illegal pairs,
 * which is the correct fail-loud behavior for a cron-driven background job.
 *
 * chunkById(100) is Laravel's default batch size for chunkById; it caps memory
 * usage at ~100 hydrated Article models per iteration. orderBy('scheduled_at')
 * enforces FIFO publish order when multiple articles are due simultaneously —
 * the oldest scheduled row publishes first, mirroring editorial intent.
 *
 * Threat refs:
 *   - T-07-07-02 (Tampering — race between cms-editor manual publish + scheduler tick):
 *     ArticleStatusService::transition throws InvalidArticleStatusTransitionException
 *     on the second leg (published → published is not in the ALLOWED map; only
 *     published → draft is). The service-layer guard is the upper defence; the
 *     observer's payload->article_id republish guard (Pitfall 10) is the lower
 *     defence. The command surfaces the exception via Laravel's default error
 *     channel — see ArticlesPublishScheduledCommand.
 *   - T-07-07-04 (DoS — chunkById memory): batch capped at 100; FIFO orderBy
 *     processes oldest-first; tested at 250 rows (2.5x default batch) for
 *     boundary coverage.
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class ArticlePublishService
{
    public function __construct(private ArticleStatusService $statusService) {}

    /**
     * Promote every scheduled article whose scheduled_at has passed to
     * status='published'.
     *
     * @return int Number of articles flipped this run (0 if none due).
     */
    public function publishDue(?Carbon $now = null): int
    {
        $now ??= now();
        $count = 0;

        Article::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')   // FIFO publish order on tie
            ->chunkById(100, function (EloquentCollection $articles) use (&$count): void {
                /** @var EloquentCollection<int, Article> $articles */
                foreach ($articles as $a) {
                    // Delegate to state service — observer chain fires identically to admin path.
                    $this->statusService->transition($a, 'published');
                    $count++;
                }
            });

        return $count;
    }
}
