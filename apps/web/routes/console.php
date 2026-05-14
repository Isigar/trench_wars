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
