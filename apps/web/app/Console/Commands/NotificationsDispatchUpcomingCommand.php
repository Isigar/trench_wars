<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Source: .planning/phases/09-polish/09-04-PLAN.md task 1.
 *
 * Thin wrapper around NotificationDispatcher::sweepUpcoming() so the schedule
 * binding in routes/console.php has a stable command signature. The service
 * itself is injectable + unit-testable; this command exists for the cron
 * registration surface + manual `php artisan notifications:dispatch-upcoming`
 * invocation from an admin terminal during incident response.
 *
 * Schedule contract (routes/console.php):
 *   Schedule::command('notifications:dispatch-upcoming')
 *     ->everyMinute()
 *     ->withoutOverlapping()   // single-host single-execution
 *     ->onOneServer();         // Railway multi-replica (D-014) safety
 *
 * Pitfall 12 — both guards required; ArticlesPublishScheduledCommand is the
 * precedent (Phase 7 plan 07-07 + 07-RESEARCH Pitfall 12).
 */
class NotificationsDispatchUpcomingCommand extends Command
{
    /** @var string */
    protected $signature = 'notifications:dispatch-upcoming';

    /** @var string */
    protected $description = 'Dispatch upcoming-match notifications (T-60min, T-15min) for bookable matches.';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $dispatcher->sweepUpcoming();

        return self::SUCCESS;
    }
}
