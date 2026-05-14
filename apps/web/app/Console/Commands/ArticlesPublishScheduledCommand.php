<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ArticlePublishService;
use Illuminate\Console\Command;

/**
 * Source: .planning/phases/07-cms/07-07-PLAN.md Task 1 + 07-RESEARCH.md Pattern 4.
 *
 * Artisan target for the every-minute scheduler entry in routes/console.php:
 *
 *     Schedule::command('articles:publish-scheduled')
 *         ->everyMinute()
 *         ->withoutOverlapping()   // single-host single-execution
 *         ->onOneServer();         // multi-host single-execution via cache lock
 *
 * BOTH guards are required for Railway multi-replica safety (Pitfall 12) — see
 * 07-RESEARCH.md § Pitfall 12 + § Pattern 4. Without ->onOneServer() the cron
 * fires on every worker replica; without ->withoutOverlapping() a slow run
 * overlaps with the next minute tick.
 *
 * The command delegates the entire publish-due batch to ArticlePublishService,
 * which in turn delegates each row's status flip to ArticleStatusService so
 * the observer chain (Event sync + article_announce outbound + activity_log)
 * fires uniformly across the admin and cron paths.
 *
 * Exit codes:
 *   0 (SUCCESS) — always, even when zero articles were due.
 *
 * Observability: $this->info echoes the published count. Railway scheduler
 * logs surface the line for operator visibility; if non-zero counts persist
 * for many ticks in a row, that signals an operator misconfiguration
 * (scheduled_at far in the past with no auto-publish for hours).
 *
 * Threat refs:
 *   - T-07-07-01 (Repudiation — duplicate publish across multi-replica worker):
 *     mitigated by the Schedule::command() dual-guard chain in routes/console.php.
 *   - T-07-07-05 (Tampering — cron impostor invoking artisan directly): the
 *     container-only docker compose exec is the dev path (D-021); production
 *     Railway runs the cron under the worker service's own permissions.
 */
class ArticlesPublishScheduledCommand extends Command
{
    protected $signature = 'articles:publish-scheduled';

    protected $description = 'Promote Article status=scheduled → published when scheduled_at has passed';

    public function handle(ArticlePublishService $service): int
    {
        $count = $service->publishDue();
        $this->info(sprintf('Published %d article(s).', $count));

        return self::SUCCESS;
    }
}
