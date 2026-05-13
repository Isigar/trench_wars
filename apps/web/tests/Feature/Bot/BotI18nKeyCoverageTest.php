<?php

declare(strict_types=1);

/*
| Source: 05-12-PLAN.md task 1 — i18n key coverage gate for Phase 5 bot.* +
| admin.discord_outbound_message.* namespaces (D-013).
|
| Static gate: grep every __('bot.errors.*') and __('admin.discord_outbound_message.*')
| reference in the Phase 5 server-side code surface, then assert trans($key) returns
| a non-identity value (i.e. Laravel's "key missing → key echoed back" sentinel is
| not surfaced).
|
| Analog: tests/Feature/I18n/NoHardcodedStringsTest.php (Phase 1 plan 01-08).
|
| File globs scanned:
|   - apps/web/app/Http/Controllers/BotApi/*.php
|   - apps/web/app/Http/Middleware/ResolveBotActsAsUser.php
|   - apps/web/app/Filament/Resources/DiscordOutboundMessageResource.php
*/

use Illuminate\Support\Facades\File;

/**
 * Grep one source file for __('namespace.key') references whose key starts with $prefix.
 *
 * Captures everything inside the single-quoted argument to `__()`, e.g.
 *   __('bot.errors.match_not_open')          -> ['bot.errors.match_not_open']
 *   __('admin.discord_outbound_message.label') -> ['admin.discord_outbound_message.label']
 *
 * @return array<int, string>
 */
function grepI18nKeys(string $filePath, string $prefix): array
{
    $contents = (string) file_get_contents($filePath);

    // Match __('...') with the key argument starting with $prefix. We accept
    // single OR double quotes; the key body is [a-z0-9_.] so we don't pick up
    // dynamic-key interpolations or parameter arrays.
    $pattern = '/__\((["\'])(' . preg_quote($prefix, '/') . '[a-z0-9_.]+)\1/i';
    preg_match_all($pattern, $contents, $matches);

    /** @var array<int, string> $keys */
    $keys = $matches[2];

    return array_values(array_unique($keys));
}

/**
 * Resolve the absolute paths of all files this test scans. Centralised here
 * so individual it() blocks all read the same authoritative list.
 *
 * @return array<int, string>
 */
function botI18nScanFiles(): array
{
    $controllers = File::glob(base_path('app/Http/Controllers/BotApi/*.php'));

    return array_values(array_filter(array_merge(
        $controllers,
        [
            base_path('app/Http/Middleware/ResolveBotActsAsUser.php'),
            base_path('app/Filament/Resources/DiscordOutboundMessageResource.php'),
        ],
    ), fn (string $path): bool => is_file($path)));
}

it('every bot.* key referenced in BotApi controllers + ResolveBotActsAsUser resolves to a real string', function (): void {
    $files = botI18nScanFiles();

    $referenced = [];
    foreach ($files as $file) {
        foreach (grepI18nKeys($file, 'bot.') as $key) {
            $referenced[$key] = $file;
        }
    }

    // Sanity: the audit must find at least the canonical Phase 5 bot.errors.*
    // keys. Empty result almost certainly means the regex broke or the file
    // globs missed the directory — fail loudly.
    expect($referenced)->not->toBeEmpty(
        'No bot.* i18n keys discovered in Phase 5 controllers/middleware — regex or glob is broken.'
    );

    $missing = [];
    foreach ($referenced as $key => $file) {
        $resolved = trans($key);
        // Laravel returns the key itself when missing (and __() returns a
        // string, never an array, for our scalar keys).
        if (! is_string($resolved) || $resolved === $key) {
            $missing[] = sprintf('%s (referenced by %s)', $key, str_replace(base_path() . '/', '', $file));
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing from lang/en/bot.php:\n  - " . implode("\n  - ", $missing),
    );
});

it('every admin.discord_outbound_message.* key referenced in Filament resource resolves to a real string', function (): void {
    $files = botI18nScanFiles();

    $referenced = [];
    foreach ($files as $file) {
        foreach (grepI18nKeys($file, 'admin.discord_outbound_message.') as $key) {
            $referenced[$key] = $file;
        }
    }

    expect($referenced)->not->toBeEmpty(
        'No admin.discord_outbound_message.* i18n keys discovered in Phase 5 Filament resource — regex or glob is broken.'
    );

    $missing = [];
    foreach ($referenced as $key => $file) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key) {
            $missing[] = sprintf('%s (referenced by %s)', $key, str_replace(base_path() . '/', '', $file));
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing from lang/en/admin.php:\n  - " . implode("\n  - ", $missing),
    );
});

it('bot.errors.acts_as_unknown resolves to a localized string (D-013 sanity check)', function (): void {
    $resolved = trans('bot.errors.acts_as_unknown');

    expect($resolved)->toBeString()
        ->and($resolved)->not->toBe('bot.errors.acts_as_unknown')
        ->and($resolved)->not->toBe('')
        ->and(strlen($resolved))->toBeGreaterThan(5);
});
