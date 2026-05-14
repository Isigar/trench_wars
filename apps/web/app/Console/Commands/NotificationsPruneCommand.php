<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/09-polish/09-04-PLAN.md task 1 +
 *         09-RESEARCH.md Open Question 7 LOCKED — 90-day retention.
 *
 * Daily cron that deletes notifications older than 90 days. Notifications are
 * NOT the audit log (activity_log is — D-012). Pruning is operational hygiene
 * to keep the bell list responsive + the table physically small; the threat
 * register entry T-09-04-05 accepts the audit-trail trade-off (the bell is
 * UX surface, not security surface).
 *
 * Schedule contract (routes/console.php):
 *   Schedule::command('notifications:prune')
 *     ->dailyAt('03:30')
 *     ->onOneServer();
 *
 * dailyAt('03:30') chosen to interleave with sitemap:generate (03:00) — both
 * land in the low-traffic UTC window. onOneServer() prevents multi-replica
 * double-delete (the delete is idempotent but the activity_log audit row
 * would otherwise double).
 */
class NotificationsPruneCommand extends Command
{
    /** @var string */
    protected $signature = 'notifications:prune';

    /** @var string */
    protected $description = 'Delete notifications older than 90 days (Open Question 7 LOCKED).';

    public function handle(): int
    {
        $deleted = DB::table('notifications')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        $this->info("Pruned {$deleted} notifications older than 90 days.");

        return self::SUCCESS;
    }
}
