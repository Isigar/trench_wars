<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Source: D-020 LOCKED — TypeScript types generated from spatie/laravel-data DTOs.
 *
 * spatie/laravel-typescript-transformer v3.0 dropped the `config/typescript-transformer.php`
 * file in favour of provider-based configuration (see vendor/spatie/laravel-typescript-transformer/
 * src/Commands/InstallTypeScriptTransformerCommand.php). The plan was authored against the v2
 * config-file API; we reconcile to v3's provider API here (Rule 3 deviation).
 *
 * Configuration:
 *   - transformDirectories(app_path('Data'))  — only DTOs under app/Data/ are scanned
 *   - GlobalNamespaceWriter(resource_path('js/types/api.d.ts')) — single .d.ts emit path
 *     (consumed by Vue via `@/types/api` and by apps/bot + apps/rcon-worker after the
 *     trenchwars:typescript-generate command syncs it to packages/shared-types/src/api.d.ts)
 *   - No formatter — PrettierFormatter requires `prettier` on PATH which the PHP container
 *     does not provide; the writer's default output is already valid `.d.ts`.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        // outputDirectory is the on-disk base; the writer's path is relative to it
        // (see vendor/spatie/typescript-transformer/src/Actions/WriteFilesAction.php
        // line 53: `$this->config->outputDirectory.DIRECTORY_SEPARATOR.$file->path`).
        // We aim outputDirectory at resources/js/types/ and the writer at the bare
        // filename so the final emit is resources/js/types/api.d.ts.
        $config
            ->outputDirectory(resource_path('js/types'))
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path('Data'))
            ->writer(new GlobalNamespaceWriter('api.d.ts'));
    }
}
