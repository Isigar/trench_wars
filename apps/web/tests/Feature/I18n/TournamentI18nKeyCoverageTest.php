<?php

declare(strict_types=1);

/*
| Source: 06-13-PLAN.md task 1 — i18n key coverage gate for Phase 6 tournaments.*
| + admin.tournament*.* namespaces (D-013 + Pitfall 10 mitigation).
|
| Two complementary checks:
|
|   (1) Expected-key resolution — a hardcoded list of 100+ leaf keys that
|       MUST resolve to a non-empty string via __() / trans(). Catches the
|       case where someone deletes a key from lang/en/tournaments.php (or
|       lang/en/admin.php) but a Vue / Filament reference still expects it.
|
|   (2) Source-grep round-trip — preg_match every t() / __() call in the
|       Phase 6 Vue + Filament source surface for keys in the tournaments.*
|       and admin.tournament*.* namespaces, then assert every captured key
|       resolves. Catches the reverse case — someone adds a t() call for a
|       key that never landed in lang/en/*.php.
|
| Analog: tests/Feature/Bot/BotI18nKeyCoverageTest.php (Phase 5 plan 05-12
| canonical idiom).
|
| Pitfall 10 (i18n key explosion for 4 formats × 6 statuses × 9 actions ×
| 4 RelationManagers) is mitigated by THIS test — CI will fail on key drift.
*/

use Illuminate\Support\Facades\File;

/**
 * Grep one source file for t('namespace.key') OR __('namespace.key') references
 * whose key starts with one of the supplied prefixes.
 *
 * Accepts BOTH single and double quotes. Accepts BOTH t( and __( call shapes
 * (the bare-`t(` covers Vue's auto-imported useT composable; `__(` covers
 * PHP / Filament code paths).
 *
 * @param  list<string>  $prefixes  e.g. ['tournaments.', 'admin.tournament']
 * @return array<int, string>
 */
function grepTournamentI18nKeys(string $filePath, array $prefixes): array
{
    $contents = (string) file_get_contents($filePath);

    $alternatives = implode('|', array_map(
        fn (string $p): string => preg_quote($p, '/'),
        $prefixes,
    ));

    // Captured key body: dot-separated namespace path that MUST end with a
    // concrete leaf segment (no trailing `.`). This excludes string-concat
    // dynamic keys like `'tournaments.formats.' . $record->format . '.label'`
    // (where the literal ends with a dot before $var). Those dynamic keys are
    // exercised by the expected-key resolution test (test #1) — every concrete
    // leaf MUST be in that list.
    $pattern = '/(?:\bt|__)\(\s*(["\'])((?:' . $alternatives . ')[a-z0-9_.]*[a-z0-9_])\1/i';
    preg_match_all($pattern, $contents, $matches);

    /** @var array<int, string> $keys */
    $keys = $matches[2];

    return array_values(array_unique($keys));
}

/**
 * Resolve the absolute paths of all files this test scans.
 *
 * Vue surface (plan 06-12):
 *   - resources/js/pages/Tournaments/*.vue   (Index, Show)
 *   - resources/js/components/tournaments/*.vue (BracketCanvas, BracketNode,
 *     ParticipantsList, StandingsTable, TournamentScheduleList)
 *
 * Filament surface (plan 06-11):
 *   - app/Filament/Resources/TournamentResource.php
 *   - app/Filament/Resources/TournamentResource/Pages/*.php
 *   - app/Filament/Resources/TournamentResource/RelationManagers/*.php
 *
 * @return array<int, string>
 */
function tournamentI18nScanFiles(): array
{
    return array_values(array_filter(array_merge(
        File::glob(base_path('resources/js/pages/Tournaments/*.vue')),
        File::glob(base_path('resources/js/components/tournaments/*.vue')),
        [base_path('app/Filament/Resources/TournamentResource.php')],
        File::glob(base_path('app/Filament/Resources/TournamentResource/Pages/*.php')),
        File::glob(base_path('app/Filament/Resources/TournamentResource/RelationManagers/*.php')),
    ), fn (string $path): bool => is_file($path)));
}

// -----------------------------------------------------------------------------
// 1. EXPECTED-KEY RESOLUTION — every key the Phase 6 surfaces are known to
//    consume MUST resolve to a non-empty string. Deleting any key from
//    lang/en/tournaments.php (or lang/en/admin.php) without adjusting consumers
//    is a CI failure HERE.
// -----------------------------------------------------------------------------

it('every expected Phase 6 tournaments.* + admin.tournament*.* key resolves to a non-empty string', function (): void {
    // === tournaments.* namespace ===
    $expected = [];

    // 4 formats × 4 leaf keys = 16
    foreach (['single_elimination', 'double_elimination', 'round_robin', 'swiss'] as $format) {
        foreach (['label', 'description', 'badge_class', 'badge_label'] as $leaf) {
            $expected[] = "tournaments.formats.{$format}.{$leaf}";
        }
    }

    // 6 statuses × 2 leaf keys = 12
    foreach (['draft', 'registering', 'seeded', 'running', 'completed', 'cancelled'] as $status) {
        foreach (['label', 'badge_class'] as $leaf) {
            $expected[] = "tournaments.status.{$status}.{$leaf}";
        }
    }

    // 4 participant statuses × 1 leaf key = 4
    foreach (['registered', 'active', 'withdrawn', 'disqualified'] as $status) {
        $expected[] = "tournaments.participant_status.{$status}.label";
    }

    // 10 service-error strings (plan 06-04/05/06/07/08/09)
    foreach ([
        'invalid_transition',
        'brackets_already_generated',
        'swiss_too_few_participants',
        'winner_not_participant',
        'no_self_advance',
        'reseed_not_allowed',
        'insufficient_participants',
        'cannot_forfeit_completed',
        'cannot_withdraw_completed',
        'swiss_rounds_exhausted',
    ] as $err) {
        $expected[] = "tournaments.errors.{$err}";
    }

    // 9 admin actions × 4 leaf keys = 36
    foreach ([
        'open_registration',
        'seed',
        'reseed',
        'start',
        'forfeit',
        'withdraw',
        'recalculate_standings',
        'cancel',
        'generate_next_swiss_round',
    ] as $action) {
        foreach (['label', 'modal_heading', 'modal_description', 'success'] as $leaf) {
            $expected[] = "tournaments.actions.{$action}.{$leaf}";
        }
    }

    // 5 public tabs × 1 = 5
    foreach (['overview', 'bracket', 'schedule', 'standings', 'participants'] as $tab) {
        $expected[] = "tournaments.tabs.{$tab}.label";
    }

    // 3 empty-state copy
    foreach (['participants', 'brackets', 'standings'] as $emp) {
        $expected[] = "tournaments.empty.{$emp}";
    }

    // 6 stage-type labels
    foreach (['group', 'elim', 'winners-bracket', 'losers-bracket', 'grand-final', 'swiss-round'] as $stage) {
        $expected[] = "tournaments.stage_types.{$stage}.label";
    }

    // Public navigation + directory + show + standings + participants (plan 06-12)
    $expected[] = 'tournaments.nav.label';
    foreach ([
        'title',
        'subtitle',
        'empty_default',
        'card_format_prefix',
        'card_status_prefix',
        'card_starts_label',
        'card_ends_label',
        'card_view_button',
    ] as $leaf) {
        $expected[] = "tournaments.directory.{$leaf}";
    }
    foreach ([
        'title_fallback',
        'starts_label',
        'ends_label',
        'organiser_label',
        'participants_label',
        'format_label',
        'status_label',
        'bracket_empty',
        'schedule_empty',
        'schedule_view_match',
        'bracket_winner_pending',
        'bracket_loser_pending',
    ] as $leaf) {
        $expected[] = "tournaments.show.{$leaf}";
    }
    foreach ([
        'rank',
        'clan',
        'wins',
        'losses',
        'draws',
        'points',
        'tiebreak_buchholz',
        'tiebreak_point_diff',
        'tiebreak_default',
    ] as $leaf) {
        $expected[] = "tournaments.standings.{$leaf}";
    }
    foreach (['seed_label', 'no_seed'] as $leaf) {
        $expected[] = "tournaments.participants.{$leaf}";
    }

    // === admin.tournament*.* namespace ===

    // Tournament resource shell
    foreach (['label', 'plural_label', 'navigation_group'] as $leaf) {
        $expected[] = "admin.tournament.{$leaf}";
    }
    foreach (['profile', 'audit'] as $sect) {
        $expected[] = "admin.tournament.section.{$sect}";
    }
    foreach ([
        'slug',
        'game_id',
        'title',
        'description',
        'format',
        'status',
        'starts_at',
        'ends_at',
        'max_participants',
        'organiser_user_id',
        'default_game_match_type_id',
        'is_public',
        'settings',
        'participants_count',
    ] as $field) {
        $expected[] = "admin.tournament.fields.{$field}";
    }

    // Filament action labels (10 — includes materialise_next_round which lives
    // in admin.* rather than tournaments.* for parity with Phase 4 idiom).
    foreach ([
        'open_registration',
        'seed',
        'reseed',
        'start',
        'forfeit',
        'withdraw',
        'recalculate_standings',
        'cancel',
        'generate_next_swiss_round',
        'materialise_next_round',
    ] as $action) {
        $expected[] = "admin.tournament.actions.{$action}.label";
    }
    // materialise_next_round ships full modal copy under admin.* (plan 06-11).
    foreach (['modal_heading', 'modal_description', 'success'] as $leaf) {
        $expected[] = "admin.tournament.actions.materialise_next_round.{$leaf}";
    }
    // reseed in admin.* carries an action-specific modal_description + success
    // (a Phase 6 plan 06-11 idiom that overlaps the tournaments.* copy).
    foreach (['modal_description', 'success'] as $leaf) {
        $expected[] = "admin.tournament.actions.reseed.{$leaf}";
    }

    // 4 child resources × their leaves
    foreach (['label', 'plural_label'] as $leaf) {
        $expected[] = "admin.tournament_participant.{$leaf}";
        $expected[] = "admin.tournament_stage.{$leaf}";
        $expected[] = "admin.tournament_bracket.{$leaf}";
        $expected[] = "admin.tournament_standing.{$leaf}";
    }
    foreach (['clan_id', 'seed', 'status', 'placement'] as $field) {
        $expected[] = "admin.tournament_participant.fields.{$field}";
    }
    foreach (['type', 'ordinal', 'name', 'brackets_count'] as $field) {
        $expected[] = "admin.tournament_stage.fields.{$field}";
    }
    foreach (['stage', 'round_number', 'position', 'participant_a_id', 'participant_b_id', 'winner_participant_id', 'match_id'] as $field) {
        $expected[] = "admin.tournament_bracket.fields.{$field}";
    }
    foreach (['participant_id', 'wins', 'losses', 'draws', 'points', 'tiebreak_score', 'rank'] as $field) {
        $expected[] = "admin.tournament_standing.fields.{$field}";
    }

    // Sanity: the test itself should be auditing AT LEAST 100 keys.
    expect(count($expected))->toBeGreaterThanOrEqual(100);

    $missing = [];
    foreach ($expected as $key) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key || trim($resolved) === '') {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing or empty in lang/en/{tournaments,admin}.php:\n  - " . implode("\n  - ", $missing),
    );
});

// -----------------------------------------------------------------------------
// 2. SOURCE-GREP ROUND-TRIP — every concrete tournaments.* / admin.tournament*.*
//    key that Vue + Filament actually reference MUST resolve. Catches t() calls
//    against keys that never landed in lang/en/*.php.
// -----------------------------------------------------------------------------

it('every concrete tournaments.* / admin.tournament*.* key used in Phase 6 Vue + Filament source resolves to a real string', function (): void {
    $files = tournamentI18nScanFiles();

    expect($files)->not->toBeEmpty(
        'Tournament Vue/Filament surface scan returned zero files — globs are broken.'
    );

    $prefixes = [
        'tournaments.',
        'admin.tournament.',
        'admin.tournament_participant.',
        'admin.tournament_stage.',
        'admin.tournament_bracket.',
        'admin.tournament_standing.',
    ];

    $referenced = [];
    foreach ($files as $file) {
        foreach (grepTournamentI18nKeys($file, $prefixes) as $key) {
            $referenced[$key] = $file;
        }
    }

    expect($referenced)->not->toBeEmpty(
        'No tournaments.* / admin.tournament*.* keys discovered in Phase 6 source — regex broken or files moved.'
    );

    $missing = [];
    foreach ($referenced as $key => $file) {
        $resolved = trans($key);
        if (! is_string($resolved) || $resolved === $key || trim($resolved) === '') {
            $missing[] = sprintf('%s (referenced by %s)', $key, str_replace(base_path() . '/', '', $file));
        }
    }

    expect($missing)->toBe(
        [],
        "Translation keys missing from lang/en/{tournaments,admin}.php:\n  - " . implode("\n  - ", $missing),
    );
});
