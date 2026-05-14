<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Source: .planning/phases/07-cms/07-07-PLAN.md Task 1 + 07-RESEARCH.md Pattern 4
|         + § Pitfall 12 (everyMinute + horizontal scaling = duplicate publishes).
|
| Auto-publish path for Article rows whose scheduled_at has elapsed. Promotes
| status='scheduled' → 'published' via the ArticlePublishService → ArticleStatusService
| chain, which keeps the observer side-effects (Event sync + article_announce
| outbound + activity_log row) uniform across admin- and cron-driven publishes.
|
| ->withoutOverlapping()  — single-host single-execution. A slow run that crosses
|                            a minute boundary does NOT spawn a second concurrent
|                            invocation on the same host.
| ->onOneServer()         — multi-host single-execution via cache lock. Required
|                            for Railway multi-replica deployments (D-014) — without
|                            this guard, every worker replica that runs schedule:run
|                            would publish the same scheduled rows, duplicating
|                            Discord announces and activity_log rows.
|
| BOTH guards are required. See 07-RESEARCH.md Pitfall 12 for the canonical
| documentation; this is the verbatim mitigation pattern.
*/
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()    // single-host single-execution
    ->onOneServer();          // multi-host single-execution via cache lock — Railway multi-replica

/*
| Source: .planning/phases/07-cms/07-12-PLAN.md task 1 + 07-RESEARCH.md Pitfall 12.
|
| Daily sitemap.xml regeneration. dailyAt('03:00') chosen for low-traffic window
| (UTC); ->onOneServer() prevents multi-replica duplicate writes on Railway D-014.
| No ->withoutOverlapping() needed because the daily cadence makes overlap
| effectively impossible — Pitfall 12 only requires the multi-replica guard.
|
| SitemapGenerateCommand is a single-pass write (Article::where + Clan::all +
| Tournament::where, ~1000 URLs total per RESEARCH A8) — well under any
| meaningful runtime threshold and bounded above by the 50K Spatie URL limit
| (T-07-12-06 mitigation + Pitfall 7 horizon).
*/
Schedule::command('sitemap:generate')
    ->dailyAt('03:00')
    ->onOneServer();          // Railway multi-replica safety — Pitfall 12

/*
| Source: .planning/phases/09-polish/09-04-PLAN.md task 1 +
|         09-RESEARCH.md § Pattern 2 (NotificationDispatcher cron sweep).
|
| SC-1 timer-based dispatch surface — fires MatchStartingSoon to signed-up
| players + active host-clan members at T-60min and T-15min before the match's
| scheduled_at. The service's alreadyDispatched() guard makes the sweep
| idempotent against the (type, data->match_id, data->minutes) tuple (Pitfall 5
| LOCKED), so a slow tick that crosses minute boundaries does NOT re-fire the
| same notification.
|
| Both guards required (Pitfall 12):
|   ->withoutOverlapping() — single-host single-execution.
|   ->onOneServer()        — multi-host single-execution via cache lock for
|                            Railway multi-replica deploys (D-014).
*/
Schedule::command('notifications:dispatch-upcoming')
    ->everyMinute()
    ->withoutOverlapping()    // single-host single-execution
    ->onOneServer();          // Railway multi-replica safety — Pitfall 12

/*
| Source: .planning/phases/09-polish/09-04-PLAN.md task 1 +
|         09-RESEARCH.md Open Question 7 LOCKED (90-day retention).
|
| Daily 90-day prune of the notifications table. Notifications are NOT the
| audit log (activity_log is — D-012); this prune is operational hygiene to
| keep the bell list responsive + the table physically small. Threat-register
| entry T-09-04-05 accepts the audit-trail trade-off.
|
| dailyAt('03:30') is chosen to interleave with sitemap:generate (03:00) —
| both land in the low-traffic UTC window. onOneServer() prevents multi-replica
| double-delete (the delete itself is idempotent but the activity_log audit row
| would otherwise double).
*/
Schedule::command('notifications:prune')
    ->dailyAt('03:30')
    ->onOneServer();          // Railway multi-replica safety — Pitfall 12
