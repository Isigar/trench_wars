---
phase: 06-tournaments-brackets
plan: 01
subsystem: scaffold
tags:
  - wave-0
  - factory-stubs
  - red-stubs
  - i18n-skeleton
  - phase-6-tournaments
dependency-graph:
  requires:
    - .planning/phases/04-matches-manual/04-03-SUMMARY.md  # GameMatchFactory canonical idiom (commit 1d4d736) — string-FQN $model + per-line phpstan-ignore
    - .planning/phases/05-discord-bot/05-01-SUMMARY.md     # Bare functional Pest convention (D-05-01-C) — no namespace, no per-file uses()
  provides:
    - "5 factory stubs (Tournament/TournamentParticipant/TournamentStage/TournamentBracket/TournamentStanding) — replaced by plan 06-03"
    - "32 Pest RED stubs covering Models / Services / Observers / Admin / Tournaments / I18n / Unit Data — replaced by plans 06-03..06-13"
    - "apps/web/lang/en/tournaments.php — full Phase 6 i18n namespace skeleton (90+ leaf keys)"
    - "apps/web/lang/en/admin.php extended with 4 Phase 6 top-level keys (tournament, tournament_participant, tournament_bracket, tournament_standing)"
  affects:
    - apps/web/database/factories/         # 5 new factory files
    - apps/web/tests/Feature/Models/       # 5 new RED stubs
    - apps/web/tests/Feature/Services/     # 9 new RED stubs
    - apps/web/tests/Feature/Observers/    # 2 new RED stubs
    - apps/web/tests/Feature/Admin/        # 7 new RED stubs
    - apps/web/tests/Feature/Tournaments/  # 4 new RED stubs (new directory)
    - apps/web/tests/Feature/I18n/         # 1 new RED stub
    - apps/web/tests/Unit/Data/            # 4 new RED stubs
    - apps/web/lang/en/                    # tournaments.php + admin.php extended
tech-stack:
  added: []
  patterns:
    - "Factory stub with string FQN $model + per-line @phpstan-ignore (Phase 3 commit 1d4d736 idiom verbatim)"
    - "Bare functional Pest convention: no namespace, no per-file uses(), single it('placeholder for ...')"
    - "i18n namespace pre-shipped in full (formats × 4, status × 6, participant_status × 4, errors × 8, actions × 9, tabs × 5, empty × 3, stage_types × 6)"
key-files:
  created:
    # Factory stubs (5) — Wave 0 replaced by plan 06-03
    - apps/web/database/factories/TournamentFactory.php
    - apps/web/database/factories/TournamentParticipantFactory.php
    - apps/web/database/factories/TournamentStageFactory.php
    - apps/web/database/factories/TournamentBracketFactory.php
    - apps/web/database/factories/TournamentStandingFactory.php
    # i18n (1 new)
    - apps/web/lang/en/tournaments.php
    # Models RED stubs (5) — replaced by plan 06-03
    - apps/web/tests/Feature/Models/TournamentModelTest.php
    - apps/web/tests/Feature/Models/TournamentParticipantModelTest.php
    - apps/web/tests/Feature/Models/TournamentStageModelTest.php
    - apps/web/tests/Feature/Models/TournamentBracketModelTest.php
    - apps/web/tests/Feature/Models/TournamentStandingModelTest.php
    # Services RED stubs (9) — replaced by plans 06-04..06-09
    - apps/web/tests/Feature/Services/TournamentStatusServiceTest.php
    - apps/web/tests/Feature/Services/TournamentSeedingServiceTest.php
    - apps/web/tests/Feature/Services/BracketGeneratorSingleEliminationTest.php
    - apps/web/tests/Feature/Services/BracketGeneratorDoubleEliminationTest.php
    - apps/web/tests/Feature/Services/BracketGeneratorRoundRobinTest.php
    - apps/web/tests/Feature/Services/BracketGeneratorSwissTest.php
    - apps/web/tests/Feature/Services/BracketMatchMaterialiserServiceTest.php
    - apps/web/tests/Feature/Services/BracketAdvancementServiceTest.php
    - apps/web/tests/Feature/Services/StandingsCalculatorServiceTest.php
    # Observers RED stubs (2)
    - apps/web/tests/Feature/Observers/TournamentObserverTest.php
    - apps/web/tests/Feature/Observers/MatchResultObserverTest.php
    # Admin RED stubs (7) — replaced by plan 06-11
    - apps/web/tests/Feature/Admin/TournamentResourceTest.php
    - apps/web/tests/Feature/Admin/TournamentSeedActionTest.php
    - apps/web/tests/Feature/Admin/TournamentReseedActionTest.php
    - apps/web/tests/Feature/Admin/TournamentForfeitActionTest.php
    - apps/web/tests/Feature/Admin/TournamentWithdrawActionTest.php
    - apps/web/tests/Feature/Admin/TournamentRecalculateStandingsTest.php
    - apps/web/tests/Feature/Admin/TournamentAuditLogTest.php
    # Tournaments public RED stubs (4) — replaced by plan 06-12
    - apps/web/tests/Feature/Tournaments/TournamentShowPageTest.php
    - apps/web/tests/Feature/Tournaments/TournamentIndexPageTest.php
    - apps/web/tests/Feature/Tournaments/TournamentPublicJsonControllerTest.php
    - apps/web/tests/Feature/Tournaments/TournamentEndToEndTest.php
    # I18n RED stub (1) — replaced by plan 06-13
    - apps/web/tests/Feature/I18n/TournamentI18nKeyCoverageTest.php
    # Unit Data RED stubs (4) — replaced by plan 06-10
    - apps/web/tests/Unit/Data/TournamentDataTest.php
    - apps/web/tests/Unit/Data/PublicTournamentDataTest.php
    - apps/web/tests/Unit/Data/BracketNodeDataTest.php
    - apps/web/tests/Unit/Data/BracketEdgeDataTest.php
  modified:
    - apps/web/lang/en/admin.php  # Appended 4 Phase 6 top-level keys; existing keys untouched
decisions:
  - "Wave 0 scaffolding adopts Phase 3 commit 1d4d736 factory-stub idiom verbatim: string FQN $model (not ::class) + per-line @phpstan-ignore on missingType.generics + property.defaultValue. Plan 06-03 will replace these with typed-generic factories once models exist."
  - "Wave 0 Pest stubs use bare functional convention (Phase 5 D-05-01-C canonical) — no namespace, no per-file uses() call. Pest.php autowires TestCase + RefreshDatabase via uses(...)->in('Feature'); per-file uses() was found to trigger TestRepository fatals in Phase 5."
  - "Phase 6 i18n namespace pre-shipped in full (instead of incremental per-plan) to prevent NoHardcodedStringsTest CI failures mid-execution. 90+ leaf keys cover formats × 4, status × 6, participant_status × 4, errors × 8, actions × 9 × 4 = 36, tabs × 5, empty × 3, stage_types × 6."
  - "Factory $model uses literal string 'App\\\\Models\\\\Tournament' (etc.) — class does not exist yet, so ::class would fail. definition() returns [] (empty); plan 06-03 fills it from migration column shapes."
  - "definition() returns [] rather than throw RuntimeException (Phase 3 03-01 idiom): downstream Wave 0 stubs never instantiate these factories (they ->skip on class_exists/RED placeholder), and an empty array is closer to the eventual GREEN body."
metrics:
  duration: 4m 52s
  completed: 2026-05-13
  tasks: 2
  files_created: 38
  files_modified: 1
  commits: 2
---

# Phase 6 Plan 1: Wave 0 Scaffolding Summary

5 factory stubs + 32 Pest RED stub tests + complete `lang/en/tournaments.php` i18n namespace skeleton + 4 Phase 6 keys appended to `lang/en/admin.php`. Every subsequent Phase 6 plan (06-02..06-13) has an explicit RED landing spot for its GREEN assertions.

## What Landed

### 5 Factory Stubs

| File | Replaced by |
|------|-------------|
| `apps/web/database/factories/TournamentFactory.php` | plan 06-03 |
| `apps/web/database/factories/TournamentParticipantFactory.php` | plan 06-03 |
| `apps/web/database/factories/TournamentStageFactory.php` | plan 06-03 |
| `apps/web/database/factories/TournamentBracketFactory.php` | plan 06-03 |
| `apps/web/database/factories/TournamentStandingFactory.php` | plan 06-03 |

Each factory follows the verbatim Phase 3 commit 1d4d736 shape:
- `final class XxxFactory extends Factory` (Phase 4 idiom)
- `protected $model = 'App\\Models\\Xxx';` — STRING FQN (model class does not exist yet)
- `/** @phpstan-ignore-next-line missingType.generics */` above the class line (Factory has generic `<TModel>`)
- `/** @phpstan-ignore-next-line property.defaultValue */` above `$model`
- `public function definition(): array { return []; }` — empty body

### 32 Pest RED Stub Tests

| Suite | Count | Replaced by |
|-------|-------|-------------|
| `tests/Feature/Models/Tournament*ModelTest.php` | 5 | plan 06-03 |
| `tests/Feature/Services/TournamentStatusServiceTest.php` | 1 | plan 06-04 |
| `tests/Feature/Services/TournamentSeedingServiceTest.php` | 1 | plan 06-05 |
| `tests/Feature/Services/BracketGenerator{SingleElim,DoubleElim,RoundRobin,Swiss}Test.php` | 4 | plans 06-06, 06-07 |
| `tests/Feature/Services/Bracket{MatchMaterialiser,Advancement}ServiceTest.php` | 2 | plan 06-08 |
| `tests/Feature/Services/StandingsCalculatorServiceTest.php` | 1 | plan 06-09 |
| `tests/Feature/Observers/TournamentObserverTest.php` | 1 | plan 06-10 |
| `tests/Feature/Observers/MatchResultObserverTest.php` | 1 | plan 06-08 |
| `tests/Feature/Admin/Tournament*Test.php` | 7 | plan 06-11 |
| `tests/Feature/Tournaments/Tournament*PageTest.php + PublicJsonController + EndToEnd` | 4 | plan 06-12 |
| `tests/Feature/I18n/TournamentI18nKeyCoverageTest.php` | 1 | plan 06-13 |
| `tests/Unit/Data/{Tournament,PublicTournament,BracketNode,BracketEdge}DataTest.php` | 4 | plan 06-10 |
| **Total** | **32** | |

All 32 stubs follow the bare functional Pest convention (Phase 5 D-05-01-C):

```php
<?php
declare(strict_types=1);

/* Wave 0 RED stub — replaced by plan 06-XX. */

it('placeholder for <feature> — replace via plan 06-XX', function (): void {
    expect(true)->toBe(false);
});
```

`pest --filter='placeholder for'` reports **32 failed / 32 assertions** — the intended RED baseline. Subsequent plans flip these to GREEN one at a time.

### i18n Namespace Skeleton — `apps/web/lang/en/tournaments.php`

Leaf-key inventory (90+ total):

| Top-level key | Shape | Leaf keys |
|---------------|-------|-----------|
| `formats` | 4 formats × 4 keys (`label`, `description`, `badge_class`, `badge_label`) | 16 |
| `status` | 6 states × 2 keys (`label`, `badge_class`) | 12 |
| `participant_status` | 4 × 1 (`label`) | 4 |
| `errors` | flat strings | 8 |
| `actions` | 9 actions × 4 keys (`label`, `modal_heading`, `modal_description`, `success`) | 36 |
| `tabs` | 5 × 1 (`label`) | 5 |
| `empty` | flat strings | 3 |
| `stage_types` | 6 × 1 (`label`) | 6 |
| **Total** | | **90** |

Pitfall 10 (Phase 6 RESEARCH.md): all 4 tournament formats × 4 keys are pre-shipped so that subsequent Vue + PHP code that calls `__('tournaments.formats.swiss.badge_class')` etc. never raises `MissingTranslationException`. Plan 06-13 ships `TournamentI18nKeyCoverageTest` that asserts every key in this file resolves; the test stub for it is one of the 32 RED stubs created here.

### `apps/web/lang/en/admin.php` Extension

4 new top-level keys appended in-place (existing keys untouched):

- `admin.tournament.{label,plural_label,navigation_group,fields.*,actions.*}` — TournamentResource labels + 9 action labels (plan 06-11)
- `admin.tournament_participant.{label,plural_label,fields.*}` — ParticipantsRelationManager
- `admin.tournament_bracket.{label,plural_label,fields.*}` — BracketsRelationManager
- `admin.tournament_standing.{label,plural_label,fields.*}` — StandingsRelationManager

## Verification

| Gate | Result |
|------|--------|
| `pint --test` on all 39 touched files | PASS — 39 files clean, 0 fixes needed |
| `phpstan analyse` on 5 factory stubs | PASS — `[OK] No errors` |
| `php -l` on all 38 new files | PASS — no syntax errors |
| `pest --filter='placeholder for'` | 32 failed / 32 assertions — INTENDED RED BASELINE |
| `grep -c "'label'" lang/en/tournaments.php` (excluding `//` comment lines) | 34 (plan threshold >= 25) |
| `find apps/web/tests -name 'Tournament*.php' -o -name 'Bracket*.php' -o -name 'Standings*.php' -o -name 'MatchResultObserverTest.php' \| wc -l` | 31 (plan threshold >= 28) |
| Full Phase 6 stub count (via `grep -lr 'placeholder for' apps/web/tests/...`) | 32 |

## Deviations from Plan

**None.** Plan executed exactly as written. The plan's verification glob pattern (`Tournament*.php OR Bracket*.php OR Standings*.php OR MatchResultObserverTest.php`) returns 31 — one short of the 32 Phase 6 stubs because `PublicTournamentDataTest.php` doesn't begin with the literal prefix `Tournament`. Threshold is `>= 28`, so this is well above the plan-defined floor.

## Plan Linkages

Each Wave 0 RED stub is wired to its replacement plan via the `replace via plan 06-XX` literal in the `it()` description string + the file's docblock header. Phase-close grep audit (T-06-01-01) can confirm every stub has been flipped to GREEN by running:

```bash
grep -rl 'placeholder for' apps/web/tests/
```

Expected count at Phase 6 close: 0.

## Threat Mitigations Applied

- **T-06-01-02** (Repudiation — factory stub committed without phpstan-ignore comments would break master CI): MITIGATED. Every factory stub has both `@phpstan-ignore-next-line missingType.generics` on the class line and `@phpstan-ignore-next-line property.defaultValue` on `$model`. `phpstan analyse` against the 5 factories reports `[OK] No errors`.
- **T-06-01-03** (Tampering — stray `uses(TestCase::class, RefreshDatabase::class)` triggers Phase 5 D-05-01-C TestRepository fatal): MITIGATED. All 32 stubs are bare (no namespace, no top-level `uses()`); `Pest.php` autowires `TestCase` + `RefreshDatabase` via `uses(...)->in('Feature')` already.
- **T-06-01-01** (Tampering — i18n typos drift to production): ACCEPTED per plan. Phase 6 i18n audit (plan 06-13) is the gate; plan 06-01 ships the keys as-spec'd.

## Known Stubs

All 5 factories + 32 Pest tests are intentional Wave 0 stubs documented in this plan and replaced by plans 06-03..06-13. No undocumented stubs.

## Self-Check: PASSED

- All 38 created files exist on disk (verified via `Write` tool success + `ls` confirmation in directory listings).
- Both commits exist on master: `0b75b8d` (Task 1 — feat: factories + i18n) and `399886a` (Task 2 — test: 32 RED stubs).
- `pest --filter='placeholder for'` returns exactly 32 failures (RED baseline).
- `phpstan analyse` factories returns `[OK] No errors`.
- `pint --test` passes on all touched files.
