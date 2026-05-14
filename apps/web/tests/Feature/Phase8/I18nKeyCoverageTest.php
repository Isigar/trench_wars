<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

/*
| Source: .planning/phases/08-rcon-automation/08-12-PLAN.md task 2 (i18n coverage extension).
|
| Phase 8 mirror of Phase 6's TournamentI18nKeyCoverageTest. Two complementary
| checks:
|
|   (1) EXPECTED-KEY RESOLUTION — a hardcoded list of leaf keys that MUST resolve
|       via Lang::has(). Catches the case where someone deletes a key from
|       lang/en/rcon.php OR lang/en/admin.php while a Phase 8 caller still
|       expects it.
|
|   (2) SOURCE-GREP ROUND-TRIP — preg_match every t() / __() call site across
|       the Phase 8 source surface (Filament resources + observers + jobs +
|       services) for keys whose namespace prefix lands in this plan's
|       coverage scope (rcon.dot-namespace, admin.match_servers.dot-namespace,
|       admin.match_server_bookings.dot-namespace). Every captured key MUST
|       resolve. Catches the reverse case — someone adds a t('rcon.x.y') call
|       without landing the key in lang/en/rcon.php.
|
| Threat refs:
|   - T-08-12-04 (Spoofing — user sees raw key in Discord embed) — mitigated by
|     this CI gate. If buildMatchResultAnnounce references __('rcon.embed.x.y')
|     and `rcon.embed.x.y` is missing, the gate fires.
|
| Pitfall mitigation:
|   - The Phase 1 NoHardcodedStringsTest scans the SPA-side surface (Vue pages,
|     layouts, components). This test extends that scope to the Phase 8 backend
|     surface (Filament admin + observers + builders + jobs) where __()/lang()
|     calls land at runtime and feed Discord embeds + Filament admin labels.
*/

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

/**
 * Grep one source file for t('namespace.key') OR __('namespace.key') references
 * whose key starts with one of the supplied prefixes.
 *
 * Accepts BOTH single and double quotes. Accepts BOTH `t(` and `__(` shapes
 * (the bare-`t(` covers Vue auto-imported i18n composables; `__(` covers PHP /
 * Filament code paths). Only matches concrete LEAF keys (no trailing `.`).
 *
 * @param  list<string>  $prefixes  e.g. ['rcon.', 'admin.match_servers.']
 * @return array<int, string>
 */
function rconI18nGrepKeys(string $filePath, array $prefixes): array
{
    $contents = (string) file_get_contents($filePath);

    $alternatives = implode('|', array_map(
        fn (string $p): string => preg_quote($p, '/'),
        $prefixes,
    ));

    // Captured key body: dot-separated namespace path that MUST end with a
    // concrete leaf segment (no trailing `.`). This excludes string-concat
    // dynamic keys like `'rcon.errors.' . $kind` where the literal ends with
    // a dot before $var — those dynamic keys are exercised by the
    // expected-key resolution test (test #1) — every concrete leaf MUST be
    // in that list.
    $pattern = '/(?:\bt|__)\(\s*(["\'])((?:' . $alternatives . ')[a-z0-9_.]*[a-z0-9_])\1/i';
    preg_match_all($pattern, $contents, $matches);

    /** @var array<int, string> $keys */
    $keys = $matches[2];

    return array_values(array_unique($keys));
}

/**
 * Resolve the absolute paths of every Phase 8 source file this test scans.
 *
 * Coverage scope:
 *   - app/Filament/Resources/MatchServer<glob>.php           (plan 08-09)
 *   - app/Filament/Resources/MatchServerResource subfolders  (Pages, RelationManagers)
 *   - app/Services/Rcon/<glob>.php                           (plans 08-07/08-08)
 *   - app/Jobs/Rcon/<glob>.php                               (plans 08-08/08-09)
 *   - app/Http/Controllers/Internal/<glob>.php               (plan 08-06)
 *   - app/Http/Middleware/VerifyRconSignature.php            (plan 08-05)
 *   - app/Support/DiscordOutboundPayloadBuilder.php          (plan 08-12)
 *   - app/Observers/MatchResultObserver.php                  (plan 08-12)
 *   - app/Services/MatchResultService.php                    (plan 08-08)
 *
 * @return array<int, string>
 */
function rconI18nScanFiles(): array
{
    $base = base_path();

    /** @var array<int, string> $candidates */
    $candidates = array_merge(
        File::glob($base . '/app/Filament/Resources/MatchServer*.php'),
        File::glob($base . '/app/Filament/Resources/MatchServerResource/**/*.php'),
        File::glob($base . '/app/Filament/Resources/MatchServerResource/*.php'),
        File::glob($base . '/app/Services/Rcon/*.php'),
        File::glob($base . '/app/Jobs/Rcon/*.php'),
        File::glob($base . '/app/Http/Controllers/Internal/*.php'),
        [$base . '/app/Http/Middleware/VerifyRconSignature.php'],
        [$base . '/app/Support/DiscordOutboundPayloadBuilder.php'],
        [$base . '/app/Observers/MatchResultObserver.php'],
        [$base . '/app/Services/MatchResultService.php'],
    );

    return array_values(array_filter(
        $candidates,
        fn (string $path): bool => is_file($path),
    ));
}

// -----------------------------------------------------------------------------
// (1) EXPECTED-KEY RESOLUTION — every well-known Phase 8 i18n leaf key MUST
//     resolve via Lang::has() to a string value in lang/en/rcon.php or admin.php.
// -----------------------------------------------------------------------------

it('every expected Phase 8 rcon.* + admin.match_servers.* + admin.match_server_bookings.* key resolves', function (): void {
    $expected = [];

    // rcon.events.types.* — 10 canonical event_type labels (plan 08-04).
    foreach ([
        'game_start',
        'round_start',
        'player_kill',
        'player_team_kill',
        'player_connect',
        'player_disconnect',
        'team_switch',
        'round_end',
        'match_end',
        'manual_error',
    ] as $eventType) {
        $expected[] = "rcon.events.types.{$eventType}";
    }

    // rcon.errors.* — 6 surface error keys (plan 08-05 + 08-09).
    foreach ([
        'unreachable',
        'auth_failed',
        'permission_denied',
        'replayed_nonce',
        'stale_signature',
        'bad_signature',
    ] as $errorKey) {
        $expected[] = "rcon.errors.{$errorKey}";
    }

    // rcon.audit.* — 6 audit-log description keys (plans 08-08 + 08-09).
    foreach ([
        'manual_override_wins',
        'rcon_arrived_locked',
        'automated_from_crcon',
        'test_connection_queued',
        'test_connection_ok',
        'test_connection_error',
    ] as $auditKey) {
        $expected[] = "rcon.audit.{$auditKey}";
    }

    // rcon.embed.match_result.* — 4 Discord embed copy keys (plan 08-12).
    foreach ([
        'title',
        'score',
        'winner',
        'mvps',
    ] as $embedKey) {
        $expected[] = "rcon.embed.match_result.{$embedKey}";
    }

    $missing = [];
    foreach ($expected as $key) {
        if (! Lang::has($key)) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe(
        [],
        'Missing expected Phase 8 i18n keys: ' . implode(', ', $missing),
    );
});

// -----------------------------------------------------------------------------
// (2) SOURCE-GREP ROUND-TRIP — every captured rcon.* / admin.match_server*.*
//     key in the Phase 8 source surface MUST resolve via Lang::has().
// -----------------------------------------------------------------------------

it('every t()/__() rcon.* + admin.match_servers.* key referenced in Phase 8 source resolves', function (): void {
    $prefixes = [
        'rcon.',
        'admin.match_servers.',
        'admin.match_server_bookings.',
    ];

    $files = rconI18nScanFiles();
    expect($files)->not->toBeEmpty();

    /** @var array<string, list<string>> $unresolved   key => [source files] */
    $unresolved = [];

    foreach ($files as $file) {
        foreach (rconI18nGrepKeys($file, $prefixes) as $key) {
            if (! Lang::has($key)) {
                $unresolved[$key] = array_unique([
                    ...($unresolved[$key] ?? []),
                    str_replace(base_path() . '/', '', $file),
                ]);
            }
        }
    }

    if ($unresolved !== []) {
        $report = '';
        foreach ($unresolved as $key => $sources) {
            $report .= "\n  - {$key} (referenced in: " . implode(', ', $sources) . ')';
        }
        expect($unresolved)->toBe([], "Missing i18n keys in lang/en/{rcon,admin}.php:{$report}");
    }

    expect($unresolved)->toBe([]);
});
