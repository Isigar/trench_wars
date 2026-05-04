<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Source: D-020 LOCKED — TS types generated from spatie/laravel-data + custom typescript:generate command.
 * 01-RESEARCH.md "Build-time pipeline" diagram: emits to BOTH
 *   - apps/web/resources/js/types/api.d.ts (frontend imports as @/types/api)
 *   - packages/shared-types/src/api.d.ts (apps/bot, apps/rcon-worker import via @trenchwars/shared-types)
 *
 * The shared-types target is reachable from inside the container at
 * /repo/packages/shared-types/src/api.d.ts because docker-compose.yml mounts
 * `./packages/shared-types` to `/repo/packages/shared-types` on the `web` service
 * (added in plan 01-15 — same cross-cut pattern as plan 01-06's tsconfig.base.json mount).
 *
 * If the mount is unavailable (e.g. running this command in a future Railway-deployed
 * container without the host repo bind), the command logs a warning and exits 0 — only
 * the local dev workflow needs the cross-package sync; CI runs the sync via
 * `packages/shared-types/scripts/sync-types.sh` invoked from the host.
 */
class TypescriptGenerateCommand extends Command
{
    /** @var string */
    protected $signature = 'trenchwars:typescript-generate';

    /** @var string */
    protected $description = 'Generate TS types from spatie/laravel-data DTOs and sync to packages/shared-types.';

    public function handle(): int
    {
        $this->info('Running php artisan typescript:transform...');
        $exitCode = $this->call('typescript:transform');

        if ($exitCode !== self::SUCCESS) {
            $this->error('typescript:transform failed.');

            return self::FAILURE;
        }

        $source = resource_path('js/types/api.d.ts');
        $target = '/repo/packages/shared-types/src/api.d.ts';

        if (! file_exists($source)) {
            $this->error("Source file not found: {$source}");

            return self::FAILURE;
        }

        $targetDir = dirname($target);
        if (! is_dir($targetDir)) {
            $this->warn("Target directory not mounted: {$targetDir}");
            $this->warn('Skipping cross-package sync — run packages/shared-types/scripts/sync-types.sh from the host.');

            return self::SUCCESS;
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            $this->error("Failed to read {$source}");

            return self::FAILURE;
        }

        $bytes = file_put_contents($target, $contents);
        if ($bytes === false) {
            $this->error("Failed to write {$target}");

            return self::FAILURE;
        }

        $this->info("Wrote {$bytes} bytes to {$target}");

        return self::SUCCESS;
    }
}
