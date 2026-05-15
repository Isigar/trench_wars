<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Source: .planning/phases/09-polish/09-09-PLAN.md task 1.
 *
 * Open Question 6 LOCKED — yes, regenerate WebP conversions for ALL existing
 * Phase 7 article media (and any pre-existing Clan/Player media) as a one-time
 * post-deploy step. Conversions are ->queued() so the regenerate call returns
 * immediately; Horizon workers process the conversions async.
 *
 * Operator workflow:
 *   make artisan ARGS="trenchwars:media:regenerate-webp"
 *   # watch Horizon dashboard for completion (each Media row → 3+ jobs)
 *
 * This command is intentionally NOT scheduled — running it on every cron tick
 * would re-enqueue every media row's conversions repeatedly, burning Horizon
 * cycles for no behavior change. Operator-triggered post-deploy is the right
 * cadence (mediated by .planning/phases/09-polish/CACHE-STRATEGY.md cross-ref).
 *
 * Implementation: delegates to spatie/laravel-medialibrary's built-in
 * `media-library:regenerate` artisan command (vendored in the package). Wrapping
 * it under the `trenchwars:` namespace gives us a project-local entry point for
 * operator documentation (Open Question 6 LOCKED).
 */
class MediaRegenerateWebpCommand extends Command
{
    protected $signature = 'trenchwars:media:regenerate-webp';

    protected $description = 'Backfill WebP conversions for all existing media (Phase 9 one-time deployment step).';

    public function handle(): int
    {
        $this->info('Dispatching media-library:regenerate (queued via Horizon)...');

        $this->call('media-library:regenerate');

        $this->info('Done. Watch Horizon dashboard for conversion job completion.');

        return self::SUCCESS;
    }
}
