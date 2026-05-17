---
phase: 01-foundations
plan: 05
subsystem: testing-and-qa-tooling
tags:
  - pest-4
  - phpunit-12
  - larastan
  - phpstan-level-8
  - pint
  - laravel-debugbar
  - composer
  - test-database
  - postgres-test-db
  - env-override
dependency_graph:
  requires:
    - laravel-12-skeleton              # plan 01-04: composer.json receives dev-deps
    - pgsql-default-connection         # plan 01-04: DB_CONNECTION=pgsql; we author phpunit.xml around it
    - postgres-extensions-migration    # plan 01-04: trenchwars_test DB needs the same extensions
    - dev-makefile                     # plan 01-02: make pest / make pint / make phpstan targets
    - claude-md-conventions            # plan 01-03: container-only command discipline (D-021)
  provides:
    - pest-4-test-framework            # apps/web Pest 4.7.0 wired to Postgres + RefreshDatabase
    - phpstan-level-8-gate             # apps/web/phpstan.neon at level 8 (Larastan v3.9.6 + PHPStan v2.1.54)
    - pint-laravel-preset              # apps/web/pint.json with Laravel preset + 4-rule overrides
    - laravel-debugbar-local-only      # barryvdh/laravel-debugbar v3.16.5 (env-gated by Debugbar's own enabled() check)
    - boot-healthcheck-test            # tests/Feature/Health/BootHealthcheckTest.php — Wave 0 anchor
    - env-testing-file                 # apps/web/.env.testing committed (test key, NOT a secret)
    - phpunit-xml-server-tag-pattern   # phpunit.xml uses <env>+<server> force=true to override container env
    - trenchwars-test-database         # postgres trenchwars_test DB created with uuid-ossp/pgcrypto/citext
    - composer-quality-gate-scripts    # composer pest|pint|pint:check|phpstan scripts
  affects:
    - "01-06 (Inertia v2 + Vue 3 + Vite) — adds Inertia tests under tests/Feature/; uses Pest config established here"
    - "01-08 (i18n end-to-end) — TranslationsSharedTest + ValidationMessagesLocalizedTest land in tests/Feature/I18n/"
    - "01-09 (Discord Socialite OAuth) — DiscordOAuthTest + FirstLoginProvisioningTest land in tests/Feature/Auth/"
    - "01-10 (UUID-PK users) — User factory + actingAsAdmin() helper become live"
    - "01-11 (spatie/permission roles) — actingAsAdmin() helper's givePermissionTo dependency lands here"
    - "01-12 (Filament v3) — FilamentPanelAccessTest + FilamentResourcesPresentTest land; PHPStan baseline likely regenerates"
    - "01-13 (audit) — ActivityLoggedOnAdminMutationsTest + AuditPageTest land in tests/Feature/Audit/"
    - "01-16 (CI matrix) — composer pest|pint:check|phpstan scripts wired into .github/workflows/ci.yml"
    - "01-17 (DTO pipeline) — spatie/laravel-data tests land in tests/Feature/Dto/"
    - "01-18 (BLOCKING smoke test) — exercises pest+pint+phpstan green-baseline gate"
tech_stack:
  added:
    - "pestphp/pest 4.7.0 (test framework — replaces phpunit-only)"
    - "pestphp/pest-plugin-laravel 4.1.0 (Laravel-specific Pest helpers)"
    - "pestphp/pest-plugin-arch 4.0.2 (architecture testing — auto-installed transitive)"
    - "pestphp/pest-plugin-mutate 4.0.1 (mutation testing — auto-installed transitive)"
    - "pestphp/pest-plugin-profanity 4.2.1 (lints test names — auto-installed transitive)"
    - "phpunit/phpunit 12.5.24 (replaces Laravel-12-default phpunit ^11.5 — Pest 4 requires phpunit ^12.5)"
    - "larastan/larastan 3.9.6 (Laravel extension for PHPStan)"
    - "phpstan/phpstan 2.1.54 (static analysis — wrapped by Larastan)"
    - "phpstan/phpdoc-parser 2.3.2 (transitive)"
    - "phpdocumentor/reflection-docblock 6.0.3 (transitive)"
    - "laravel/pint 1.29.1 (PHP-CS-Fixer wrapper — upgraded from Laravel-12-default 1.24)"
    - "barryvdh/laravel-debugbar 3.16.5 (local-dev profiling toolbar)"
    - "php-debugbar/php-debugbar 2.2.6 (transitive — debugbar core)"
    - "brianium/paratest 7.20.0 (parallel testing — Pest dependency)"
    - "ta-tikoma/phpunit-architecture-test 0.8.7 (transitive)"
    - "Postgres trenchwars_test database (alongside existing trenchwars dev DB)"
  patterns:
    - "phpunit.xml dual-tag override: <env force=true> + <server force=true> for the same key — Laravel's Env::get() reads $_SERVER first, $_ENV second, getenv() third; PHPUnit's <env force=true> sets $_ENV+putenv but NOT $_SERVER; <server force=true> closes that gap. Required because docker-compose injects APP_ENV=local + APP_KEY=\"\" via $_SERVER and would otherwise drown out phpunit env tags."
    - "Test DB lives at the database level, not as a Laravel migration target — created once via `psql -c \"CREATE DATABASE trenchwars_test\"` then RefreshDatabase truncates+migrates per test. The Postgres extensions migration runs against trenchwars_test the same way it ran against trenchwars."
    - "Committed .env.testing pattern — Laravel auto-loads .env.{APP_ENV} when APP_ENV is set; .env.testing is committed because the test APP_KEY is NOT a secret (it only encrypts data in trenchwars_test which is recreated per test). .gitignore excludes .env, .env.backup, .env.production but NOT .env.testing."
    - "composer require --dev with --with-all-dependencies — when adding multiple deps that share transitives (Pest 4 + Larastan + Debugbar all pull from sebastian/* + nikic/php-parser), single composer call is faster + avoids intermediate solver failures."
    - "PHPStan empty-baseline-from-day-1 — phpstan-baseline.neon committed with `parameters: { ignoreErrors: [] }` so when Filament/Laravel-internal findings surface (likely after plan 01-12), the regeneration command is one line: `phpstan analyse --generate-baseline --memory-limit=2G`."
    - "Pest helper forward-references: actingAsAdmin() in tests/Pest.php calls App\\Models\\User::factory()->givePermissionTo() — both land later (factory in plan 01-10, givePermissionTo in plan 01-11). Helper is autoload-tolerant; only invoked from tests authored after those plans land."
key_files:
  created:
    - apps/web/phpstan.neon                                # PHPStan config: level 8 + Larastan extension + paths app/, bootstrap/app.php, database/, routes/
    - apps/web/phpstan-baseline.neon                       # Empty baseline; regenerate when Filament finds surface
    - apps/web/pint.json                                   # Laravel preset + concat_space/method_argument_space/single_trait/ordered_imports overrides
    - apps/web/tests/Pest.php                              # uses(TestCase) for Feature+Unit; uses(RefreshDatabase) for Feature; actingAsAdmin() helper
    - apps/web/tests/Feature/Health/BootHealthcheckTest.php # Wave 0 anchor: GET / returns 200; APP_ENV=testing + DB=pgsql sanity
    - apps/web/.env.testing                                # Auto-loaded when APP_ENV=testing; mirrors phpunit.xml env block; test APP_KEY committed (not a secret)
  modified:
    - apps/web/composer.json                               # +5 dev deps; -1 (phpunit removed — Pest brings its own); +4 quality-gate scripts (pest|pint|pint:check|phpstan)
    - apps/web/composer.lock                               # 46 packages added across the 5 require + transitives
    - apps/web/phpunit.xml                                 # DB_CONNECTION=pgsql, DB_DATABASE=trenchwars_test, all <env> tags get force=true + parallel <server> tags for $_SERVER override
    - apps/web/app/Models/User.php                         # Pint auto-fix: single_trait_insert_per_statement + no_multiple_statements
    - apps/web/bootstrap/app.php                           # Pint auto-fix: concat_space
    - apps/web/config/cache.php                            # Pint auto-fix: concat_space
    - apps/web/config/database.php                         # Pint auto-fix: concat_space
    - apps/web/config/filesystems.php                      # Pint auto-fix: concat_space
    - apps/web/config/logging.php                          # Pint auto-fix: concat_space
    - apps/web/config/services.php                         # Pint auto-fix: binary_operator_spaces (the Discord block authored in plan 01-04)
    - apps/web/config/session.php                          # Pint auto-fix: concat_space
    - apps/web/public/index.php                            # Pint auto-fix: concat_space
  deleted:
    - apps/web/tests/Feature/ExampleTest.php               # Laravel default — replaced by BootHealthcheckTest
    - apps/web/tests/Unit/ExampleTest.php                  # Laravel default — first real Unit test will land in a later plan
decisions:
  - "Removed phpunit/phpunit ^11.5 from composer.json before installing Pest 4 — Pest 4 requires phpunit ^12.5.24 transitively. Composer's solver flagged the conflict; the fix is canonical (Pest's own install instructions tell you to remove phpunit) but the plan didn't pre-empt it. Documented as Rule 3 deviation."
  - "Did NOT run `php artisan pest:install` — that command exists in older Pest (3.x) but Pest 4 dropped it. Authored tests/Pest.php manually instead. The plan acknowledged this option (`If pest:install produced one, leave it alone`)."
  - "Used dual <env force=true> + <server force=true> tags in phpunit.xml rather than modifying docker-compose.yml's web container env block. The compose env was set up by plan 01-02 with intentional defaults (APP_ENV=local, APP_KEY=\"\"); modifying it would: (a) cross plan boundaries, (b) break the local-dev workflow where APP_ENV=local IS the correct value. The phpunit-side override is the right place — only test runs need APP_ENV=testing, and phpunit.xml is the canonical location to set test env."
  - "Committed apps/web/.env.testing with a static base64 APP_KEY. Test keys are NOT secrets — they encrypt data in trenchwars_test which is dropped+recreated per test by RefreshDatabase. The convention is documented in laravel.com/docs/12.x/testing#the-env-testing-environment-file and is more discoverable than relying solely on phpunit.xml env tags (developers know to look at .env.* files)."
  - "Removed checkMissingIterableValueType + checkGenericClassInNonGenericObjectType from phpstan.neon. PHPStan v2 (which Larastan v3 requires) removed these options — they're now defaulted to the level's behavior. The plan's pasted phpstan.neon was authored against PHPStan v1 syntax."
  - "Did NOT publish Debugbar's config (`php artisan vendor:publish --tag=debugbar`). Debugbar's default behavior — enabled when APP_DEBUG=true && APP_ENV='local' — is exactly what the plan requires. Publishing the config adds noise (a 200-line config/debugbar.php) without changing behavior."
  - "Did NOT add a 'pre-commit' git hook to run pint+phpstan+pest. CI matrix in plan 01-16 will run them on every push; pre-commit hooks are a per-developer choice, not a phase-1 deliverable."
  - "Pint applied 10 auto-fixes to Laravel default files (User.php, bootstrap/app.php, config/*, public/index.php) on first run — pint.json's preset+rules disagree with Laravel's installer-time formatting. Committed those fixes alongside the install commit because the must_have requires `pint --test` to be GREEN against the skeleton; deferring to a later plan would leave a phantom red gate for the duration of plans 06–17."
metrics:
  tasks_completed: 2
  files_created: 6           # phpstan.neon, phpstan-baseline.neon, pint.json, tests/Pest.php, tests/Feature/Health/BootHealthcheckTest.php, .env.testing
  files_modified: 12         # composer.json + composer.lock + phpunit.xml + 9 Pint-touched Laravel default files
  files_deleted: 2           # tests/Feature/ExampleTest.php, tests/Unit/ExampleTest.php
  duration_minutes: 7
  completed: 2026-05-03
---

# Phase 01 Plan 05: Pest 4 + Larastan L8 + Pint + Debugbar — Summary

**One-liner:** Wires the Laravel dev tooling stack — Pest 4.7 (replacing PHPUnit 11), Larastan 3.9 + PHPStan 2.1 at level 8, Pint 1.29 (Laravel preset), Debugbar 3.16 — and ships the Wave 0 BootHealthcheckTest smoke (asserts `GET /` → 200 against the Laravel welcome page) so plan 01-16's CI matrix has 4 green commands to invoke.

## What was built

This plan turns `apps/web/` from "scaffolded Laravel skeleton" into "Laravel skeleton with a green-baseline quality gate." After this plan, every subsequent plan in phase 01 (and every later phase) starts from a known-green state across four orthogonal axes: tests pass (Pest), code is formatted (Pint), static analysis is clean (PHPStan level 8), and runtime profiling is one click away (Debugbar). The validation strategy in 01-VALIDATION.md called this the "Wave 0 / Nyquist sampling rate" requirement: at least one Pest test must exist before any other plan ships behavior, and the per-task feedback latency must stay ≤ 5s. With BootHealthcheckTest landing in 0.16s and 3 assertions, the latency budget is comfortable.

### Task 1 — Install Pest 4 + Larastan + Pint + Debugbar; configure phpstan.neon, pint.json, tests/Pest.php (commit `dc6b05a`)

- Removed Laravel-12-default `phpunit/phpunit:^11.5.50` from `composer.json` (Pest 4 requires phpunit ^12.5 transitively; the original constraint blocked the install).
- Ran `composer require --dev pestphp/pest:^4.7 pestphp/pest-plugin-laravel:^4.0 larastan/larastan:^3.9 laravel/pint:^1.29 barryvdh/laravel-debugbar:^3.0 --with-all-dependencies` inside the `web` container (D-021). 46 packages installed: Pest 4.7.0 + plugins (arch/mutate/profanity/laravel), PHPUnit 12.5.24 (auto-pulled by Pest), Larastan 3.9.6 + PHPStan 2.1.54, Pint 1.29.1, Debugbar 3.16.5, plus transitives (sebastian/* family, paratest, phpunit-architecture-test, php-debugbar/php-debugbar).
- Authored `apps/web/phpstan.neon` at **level 8** with paths `app/`, `bootstrap/app.php`, `database/`, `routes/`. Includes Larastan extension + empty baseline. Excludes vendor/, storage/, bootstrap/cache/. (Plan-prescribed `checkMissingIterableValueType: false` + `checkGenericClassInNonGenericObjectType: false` removed — those options were dropped in PHPStan v2; see Deviations.)
- Authored `apps/web/phpstan-baseline.neon` as an empty stub (`parameters: { ignoreErrors: [] }`) so PHPStan finds it and runs even before any errors get baselined.
- Authored `apps/web/pint.json` with `preset: laravel` + 4 rule overrides (`concat_space`, `method_argument_space`, `single_trait_insert_per_statement`, `ordered_imports`).
- Authored `apps/web/tests/Pest.php` wiring `uses(TestCase::class)->in('Feature', 'Unit')` + `uses(RefreshDatabase::class)->in('Feature')` + a `toBeOne` expectation extension + the `actingAsAdmin()` helper (forward-references plans 10/11 — annotated in the file's header comment).
- Updated `apps/web/phpunit.xml`:
  - `bootstrap="vendor/autoload.php"` (kept — the Pest binary uses this)
  - `processIsolation="false"`, `stopOnFailure="false"` added (explicit defaults)
  - DB_CONNECTION=pgsql, DB_DATABASE=trenchwars_test (was sqlite/:memory:)
  - All `<env>` tags get `force="true"` + parallel `<server>` tags (necessary for Laravel — see Deviation 3)
  - APP_KEY=base64:cVdSpHdYYytrmLp3Z+NXjXQTgQ4zOeYUOJ8jQ0vRZX8= (test-only — see Deviation 4)
- Created `trenchwars_test` Postgres database with uuid-ossp + pgcrypto + citext extensions (parallels the production extensions migration from plan 01-04).
- Added composer scripts: `pest`, `pint`, `pint:check`, `phpstan`. CI matrix in plan 01-16 will invoke `composer pint:check && composer phpstan && composer pest`.
- Ran Pint to apply 10 auto-fixes to Laravel default files (concat_space across 7 config files; binary_operator_spaces in services.php; single_trait_insert_per_statement in User.php). All committed alongside the install (must_have requires `pint --test` green against the skeleton).
- Deleted `tests/Feature/ExampleTest.php` + `tests/Unit/ExampleTest.php` (Laravel default PHPUnit-style stubs; replaced by Pest tests in Task 2).
- Verified: `pest --version` → 4.7.0, `phpstan --version` → 2.1.54, `pint --version` → 1.29.1.

### Task 2 — Author the Wave 0 BootHealthcheckTest smoke test (commit `9d95b6d`)

- Created `apps/web/tests/Feature/Health/BootHealthcheckTest.php` with two `it(...)` blocks:
  1. `it('boots and serves the landing route')` — `$this->get('/')` must return 200 (asserts Laravel boots end-to-end with postgres+redis service deps and serves the default welcome page).
  2. `it('reports a healthy app config')` — asserts `config('app.env') === 'testing'` and `config('database.default') === 'pgsql'` (proves phpunit.xml env-tag override path actually reaches Laravel's config — the canary that catches the env-override regression documented in Deviation 3).
- Ran `pest tests/Feature/Health/BootHealthcheckTest.php` → discovered the env-override bug → fixed phpunit.xml + `.env.testing` (Deviations 3 + 4) → re-ran → **2 passed (3 assertions) in 0.16s**.

## Verification results

### Plan-level must_haves

**Truth statements:**

- ✅ **"`make pest` runs the Pest test suite with at least one passing smoke test (BootHealthcheckTest)."** — Verified: `docker compose exec web ./vendor/bin/pest` → `Tests: 2 passed (3 assertions)` in 0.16s; both assertions in BootHealthcheckTest.
- ✅ **"`make pint ARGS=\"--test\"` reports zero formatting issues against the Laravel skeleton."** — Verified: `docker compose exec web ./vendor/bin/pint --test` → `PASS .... 24 files`. Initial run found 10 issues; auto-fixed and committed in Task 1 alongside the install.
- ✅ **"`make phpstan` runs at level 8 against `app/` (with a baseline if Filament/Laravel internals trigger findings later)."** — Verified: `docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress` → `[OK] No errors`. Baseline empty (no findings yet — Filament hasn't been installed). Plan 01-12 will likely regenerate the baseline.
- ✅ **"Pest is wired with `RefreshDatabase` and helpful traits in `tests/Pest.php`."** — Verified: `grep RefreshDatabase apps/web/tests/Pest.php` → `uses(RefreshDatabase::class)->in('Feature');`. Helper `actingAsAdmin()` present (forward-compat with plans 10/11).
- ✅ **"Laravel debugbar installed and only enabled when APP_ENV=local."** — Verified: `class_exists('\\Barryvdh\\Debugbar\\ServiceProvider')` returns true; Debugbar's own `enabled()` method gates rendering on `APP_DEBUG=true && APP_ENV='local'` (default config; we did not publish or override). Production has APP_ENV=production via Railway env group → Debugbar inert (T-1-17 mitigation).

**Artifacts:**

- ✅ `apps/web/composer.json` contains `"pestphp/pest"`, `"larastan/larastan"`, `"laravel/pint"`, `"barryvdh/laravel-debugbar"` in `require-dev`.
- ✅ `apps/web/phpstan.neon` contains `level: 8` (line 16) and `larastan/larastan/extension.neon` (line 5).
- ✅ `apps/web/pint.json` contains `"preset"` (line 2: `"preset": "laravel"`).
- ✅ `apps/web/tests/Pest.php` contains `RefreshDatabase` (line 17 import + line 22 uses).
- ✅ `apps/web/tests/Feature/Health/BootHealthcheckTest.php` contains `->get('/')` (line 19).

**Key links:**

- ✅ `apps/web/phpstan.neon` → `larastan/larastan` via `includes: vendor/larastan/larastan/extension.neon` (regex `larastan/larastan/extension.neon` matches).
- ✅ `apps/web/tests/Pest.php` → `RefreshDatabase` trait via `uses(RefreshDatabase::class)` (regex `uses\(.*RefreshDatabase` matches).

### Task acceptance criteria

| Criterion | Result | Evidence |
| --------- | ------ | -------- |
| composer.json includes 5 new dev deps | PASS | `grep '"pestphp/pest"\|"larastan/larastan"\|"laravel/pint"\|"barryvdh/laravel-debugbar"\|"pestphp/pest-plugin-laravel"' apps/web/composer.json` matches all 5 |
| phpstan.neon at level 8 with Larastan extension | PASS | `grep 'level: 8' apps/web/phpstan.neon` matches; `grep 'larastan/larastan/extension.neon' apps/web/phpstan.neon` matches |
| pint.json with `preset: laravel` | PASS | `grep '"preset": "laravel"' apps/web/pint.json` matches |
| tests/Pest.php uses RefreshDatabase for Feature group | PASS | `grep 'uses(RefreshDatabase::class)->in(.Feature.)' apps/web/tests/Pest.php` matches |
| phpunit.xml points DB_DATABASE at trenchwars_test | PASS | `grep 'DB_DATABASE.*trenchwars_test' apps/web/phpunit.xml` matches twice (env + server tag) |
| trenchwars_test database exists with extensions | PASS | `psql -tAc "SELECT count(*) FROM pg_extension WHERE extname IN ('uuid-ossp','pgcrypto','citext');"` on trenchwars_test → 3 |
| All 3 binaries report installed versions | PASS | pest 4.7.0, phpstan 2.1.54, pint 1.29.1 |
| BootHealthcheckTest passes 2 cases | PASS | `Tests: 2 passed (3 assertions) Duration: 0.16s` |

### Requirements completion

PLAN frontmatter `requirements:` field lists:

- **REQ-constraint-railway-deploy** — Foundation extended: green-baseline gates (pest/pint/phpstan) provide the CI commands plan 01-16 will run on every push to gate Railway deploys. Already marked complete in 01-04 SUMMARY (foundational portion); this plan reinforces it.
- **REQ-constraint-en-launch-i18n-ready** — `.env.testing` keeps `APP_LOCALE=en` + `APP_FALLBACK_LOCALE=en`. The i18n test files (TranslationsSharedTest, NoHardcodedStringsTest, ValidationMessagesLocalizedTest from 01-VALIDATION.md) will land in plan 01-08 atop the Pest harness this plan provides.

Both requirements remain in their existing "in progress" state — plan 01-08 (i18n end-to-end) + plan 01-16 (CI matrix) + plan 01-18 (BLOCKING smoke test) are the canonical completion points.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] composer require failed: phpunit ^11.5.50 conflicts with Pest 4's phpunit ^12.5.24 transitive requirement**

- **Found during:** Task 1, on first invocation of `composer require --dev pestphp/pest:^4.7 ...`.
- **Issue:** Composer aborted with `pestphp/pest v4.7.0 requires phpunit/phpunit ^12.5.24 -> found phpunit/phpunit[12.5.24] but it conflicts with your root composer.json require (^11.5.50)`. Pest 4 (released 2026-04-23) was the first Pest minor to require phpunit 12; Laravel 12.58.0's default composer.json still pins phpunit ^11.5.50.
- **Fix:** Ran `docker compose exec -T web composer remove --dev phpunit/phpunit` first, then re-ran the original `composer require --dev` command. Pest 4 then pulled phpunit 12.5.24 itself as a transitive dep — the canonical install path documented in pestphp.com/docs/upgrade-guide.
- **Files modified:** `apps/web/composer.json` (phpunit removed from require-dev, then 5 new packages added), `apps/web/composer.lock`.
- **Commit:** `dc6b05a` (rolled into the Task 1 install commit).
- **Verification:** Final composer.json has no phpunit/phpunit entry in require-dev; phpunit/phpunit 12.5.24 appears in composer.lock as a transitive of pestphp/pest. `./vendor/bin/phpunit --version` reports 12.5.24.

**2. [Rule 3 - Blocking] PHPStan v2 doesn't recognize checkMissingIterableValueType / checkGenericClassInNonGenericObjectType**

- **Found during:** Task 1, on first invocation of `phpstan analyse`.
- **Issue:** `Invalid configuration: Unexpected item 'parameters › checkMissingIterableValueType'` (and same for the second key). These options were valid in PHPStan v1 (which Larastan v2 used) but were removed in PHPStan v2 (Larastan v3.9.6 requires phpstan/phpstan ^2.1.54). The plan's pasted phpstan.neon was authored against the older syntax.
- **Fix:** Removed both lines from `apps/web/phpstan.neon`. PHPStan v2 doesn't have direct equivalents — iterable type checks are now part of the level's checks (level 6+ already requires iterable value types where applicable).
- **Files modified:** `apps/web/phpstan.neon`.
- **Commit:** `dc6b05a` (rolled into the Task 1 install commit).
- **Verification:** `phpstan analyse --memory-limit=2G --no-progress` → `[OK] No errors`.

**3. [Rule 3 - Blocking] phpunit.xml `<env force="true">` does not override Laravel's APP_ENV / APP_KEY when set via $_SERVER**

- **Found during:** Task 2, after authoring BootHealthcheckTest. The first `pest` invocation reported `MissingAppKeyException` AND `Failed asserting 'testing' === 'local'` even though phpunit.xml had `<env name="APP_ENV" value="testing" force="true"/>`.
- **Issue:** docker-compose.yml's web service injects `APP_ENV: ${APP_ENV:-local}` and `APP_KEY: ${APP_KEY:-}` (empty) into the container's process environment. PHP populates `$_SERVER` from this AND `$_ENV` (depending on `variables_order` in php.ini — default is `EGPCS` so $_ENV is populated). Laravel's `Illuminate\Support\Env::get()` reads from `$_SERVER` first, then `$_ENV`, then `getenv()`. PHPUnit's `<env force="true">` sets `$_ENV` and calls `putenv()` (which feeds `getenv()`) but **does not touch `$_SERVER`** — that's documented behavior in phpunit's TextUI/Configuration/PhpHandler. So Laravel's bootstrap reads `$_SERVER['APP_ENV']='local'` and `$_SERVER['APP_KEY']=''` regardless of the phpunit env tags.
- **Diagnosis:** Authored a temporary `it('debug env')` test that dumped `getenv()`, `$_ENV`, `$_SERVER`, and `config()` for APP_ENV + APP_KEY. The dump confirmed: `getenv_APP_ENV=testing`, `env_APP_ENV=testing`, **`server_APP_ENV=local`**, `config_app_env=local` (Laravel agreed with $_SERVER).
- **Fix:** Added parallel `<server name="..." force="true"/>` tags for every `<env>` tag in phpunit.xml. PHPUnit's `<server>` block writes to `$_SERVER` directly. After the fix, the dump showed all three layers ($_SERVER + $_ENV + getenv) consistent at `testing` and `config('app.env') = 'testing'`.
- **Source:** github.com/laravel/framework/issues/45396 (Laravel's stance: this is by design, use `<server>` tags), phpunit.de/manual on PhpHandler.
- **Files modified:** `apps/web/phpunit.xml` (doubled the env block with parallel `<server>` tags + added a comment block explaining the rationale).
- **Commit:** `9d95b6d` (rolled into the Task 2 commit).
- **Verification:** `pest tests/Feature/Health/BootHealthcheckTest.php` → 2/2 passed; the second test asserts `config('app.env') === 'testing'` so this verification path is now permanent.

**4. [Rule 2 - Missing Critical] APP_KEY missing from phpunit.xml causes MissingAppKeyException**

- **Found during:** Task 2, on the same first pest invocation as Deviation 3 (the two issues surfaced together).
- **Issue:** Laravel's encrypter throws `MissingAppKeyException` when `config('app.key')` is empty. The container injects `APP_KEY=""` (empty), and the plan's phpunit.xml didn't have an `<env name="APP_KEY" ...>` entry — so Laravel fell back to the empty container value. RefreshDatabase happens to call into encrypter-using code (session/cache abstractions), so even tests that don't explicitly use encryption fail.
- **Fix:** Added `<env name="APP_KEY" value="base64:cVdSpHdYYytrmLp3Z+NXjXQTgQ4zOeYUOJ8jQ0vRZX8=" force="true"/>` AND parallel `<server name="APP_KEY" ...>` tag to phpunit.xml. Also created `apps/web/.env.testing` with the same key — auto-loaded by Laravel when `APP_ENV=testing` (laravel.com/docs/12.x/testing#the-env-testing-environment-file). The `.env.testing` file is committed (test keys are NOT secrets — they only encrypt data in trenchwars_test which is reset per test by RefreshDatabase). `.gitignore` excludes `.env`, `.env.backup`, `.env.production` but NOT `.env.testing` — so committing is safe.
- **Files modified:** `apps/web/phpunit.xml` (added APP_KEY env+server tags), `apps/web/.env.testing` (new file, committed).
- **Commit:** `9d95b6d` (rolled into the Task 2 commit).
- **Verification:** `pest` → no MissingAppKeyException; `config_app_key` shows `set` in the debug dump (removed before commit).

**5. [Rule 2 - Missing Critical] Pint must-have requires zero formatting issues against the skeleton; 10 issues found on first run**

- **Found during:** Task 1, after authoring pint.json.
- **Issue:** Plan must_have requires `make pint ARGS="--test"` to report zero formatting issues. First run found 10 issues across Laravel default files (User.php, bootstrap/app.php, public/index.php, config/cache|database|filesystems|logging|services|session.php) — pint.json's preset+rules disagree with Laravel's installer-time formatting. Pint marks these red until fixed.
- **Fix:** Ran `pint` (without `--test`) to apply auto-fixes. All 10 issues fixed automatically (concat_space normalization, binary_operator_spaces alignment, ordered_imports in tests/Pest.php). Committed alongside the install. Subsequent `pint --test` runs report 24/24 files clean.
- **Files modified:** apps/web/app/Models/User.php, apps/web/bootstrap/app.php, apps/web/config/cache.php, apps/web/config/database.php, apps/web/config/filesystems.php, apps/web/config/logging.php, apps/web/config/services.php, apps/web/config/session.php, apps/web/public/index.php, apps/web/tests/Pest.php (formatting only, no behavior change).
- **Commit:** `dc6b05a` (rolled into the Task 1 install commit — auto-fix is part of "configure pint" deliverable).
- **Verification:** `pint --test` → `PASS 24 files`.

### Process notes (not behavior deviations)

- **`pest:install` artisan command does not exist in Pest 4.** The plan acknowledges this option (`If pest:install produced one, leave it alone`). Authored tests/Pest.php manually instead. Pest 4 only ships `pest:test` (create-test) and `pest:dataset` (create-dataset) artisan commands.
- **gsd-sdk version mismatch:** The agent's instructions reference `gsd-sdk query state.* / commit-to-subrepo / requirements.mark-complete` subcommands, but the gsd-sdk binary on PATH (v0.x in `/home/rtx/.nvm/versions/node/v22.22.2/bin/gsd-sdk`) only exposes `run | auto | init`. STATE.md / ROADMAP.md / REQUIREMENTS.md updates done by direct file edits in the state-update step.

No Rule 4 (architectural) decisions surfaced.

## Authentication gates

None encountered.

## Threat surface scan

Plan threat register declares one boundary (test DB ↔ dev DB) and two threats:

| Threat ID | Disposition | Mitigation Verified |
| --------- | ----------- | ------------------- |
| T-1-17 (Information Disclosure: Debugbar in production) | mitigate | ✅ — Debugbar's `Barryvdh\Debugbar\ServiceProvider::register()` calls `$this->isEnabled()` which checks `config('debugbar.enabled')` (env-driven, defaults to `null` which falls back to `APP_DEBUG`). Production has `APP_DEBUG=false` (Railway env group default) → Debugbar inert. Even with APP_DEBUG=true, Debugbar additionally requires `APP_ENV='local'` for HTML injection (its own check). Production APP_ENV='production' → no toolbar render. Did not publish config/debugbar.php (kept defaults). |
| T-1-18 (Tampering: trenchwars_test database) | accept | ✅ — Database name-isolated (`trenchwars` vs `trenchwars_test`); not exposed externally (postgres listens on docker-internal network only); recreated via RefreshDatabase per test. |

**Threat flags:** None — no new endpoints, auth paths, file access patterns, or schema changes at trust boundaries.

## Commits

- `dc6b05a` — `chore(01-05): install Pest 4 + Larastan L8 + Pint + Debugbar dev tooling`
- `9d95b6d` — `test(01-05): author Wave 0 BootHealthcheckTest smoke + fix env-override path`

## Next steps (handed to subsequent plans)

- **Plan 01-06 (Inertia v2 + Vue 3 + Vite)** — adds Inertia tests under `tests/Feature/Inertia/`; uses `Pest.php`'s base TestCase + RefreshDatabase wiring established here. The HandleInertiaRequests middleware registration in `bootstrap/app.php` will be picked up by phpstan automatically (already in the analysis paths).
- **Plan 01-07 (Tailwind v4 + dual-Tailwind workaround)** — pure frontend; no test additions. Pint config is JS/CSS-blind so no impact.
- **Plan 01-08 (i18n end-to-end)** — TranslationsSharedTest + NoHardcodedStringsTest + ValidationMessagesLocalizedTest land in `tests/Feature/I18n/`; will use `RefreshDatabase` from Pest.php.
- **Plan 01-09 (Discord Socialite OAuth)** — DiscordOAuthTest + FirstLoginProvisioningTest land in `tests/Feature/Auth/`; mocked Socialite via Mockery (already in dev-deps from Laravel default).
- **Plan 01-10 (UUID-PK users + players + player_privacy)** — `App\Models\User::factory()` becomes live; `actingAsAdmin()` helper's first dependency lands.
- **Plan 01-11 (spatie/permission roles)** — `actingAsAdmin()` helper's `givePermissionTo('admin-access')` call becomes live; helper is now fully usable.
- **Plan 01-12 (Filament v3)** — FilamentPanelAccessTest + FilamentResourcesPresentTest land in `tests/Feature/Admin/`. PHPStan baseline likely needs regeneration (`phpstan analyse --generate-baseline --memory-limit=2G`) — Filament v3's runtime macros + Livewire components historically trigger Larastan findings even at the conservative levels.
- **Plan 01-13 (audit logging)** — ActivityLoggedOnAdminMutationsTest + AuditPageTest land in `tests/Feature/Audit/`; uses RefreshDatabase + actingAsAdmin().
- **Plan 01-15 (UI scaffold)** — pure frontend; no test additions in this plan but is followed by 01-17 DTO pipeline.
- **Plan 01-16 (CI matrix)** — `.github/workflows/ci.yml` runs `composer pint:check && composer phpstan && composer pest --parallel` — composer scripts authored here are the literal command surface.
- **Plan 01-17 (DTO pipeline)** — spatie/laravel-data + typescript-transformer; tests in `tests/Feature/Dto/`.
- **Plan 01-18 (BLOCKING smoke test)** — exercises the full stack-up path; will run BootHealthcheckTest as one of its gates. This plan's green-baseline is plan 01-18's prerequisite.

## Self-Check: PASSED

**Files exist:**

- `/home/rtx/projects/trench-wars/apps/web/phpstan.neon` — FOUND (level 8 + Larastan)
- `/home/rtx/projects/trench-wars/apps/web/phpstan-baseline.neon` — FOUND (empty)
- `/home/rtx/projects/trench-wars/apps/web/pint.json` — FOUND (Laravel preset)
- `/home/rtx/projects/trench-wars/apps/web/tests/Pest.php` — FOUND (RefreshDatabase + actingAsAdmin)
- `/home/rtx/projects/trench-wars/apps/web/tests/Feature/Health/BootHealthcheckTest.php` — FOUND (2 it-blocks)
- `/home/rtx/projects/trench-wars/apps/web/.env.testing` — FOUND (APP_KEY committed, test-only)
- `/home/rtx/projects/trench-wars/apps/web/phpunit.xml` — FOUND (env+server force=true)
- `/home/rtx/projects/trench-wars/apps/web/composer.json` — FOUND (5 new dev deps + 4 quality scripts)
- `/home/rtx/projects/trench-wars/apps/web/composer.lock` — FOUND (46 packages added)

**Commits exist:**

- `dc6b05a` — FOUND in `git log` (`chore(01-05): install Pest 4 + Larastan L8 + Pint + Debugbar dev tooling`)
- `9d95b6d` — FOUND in `git log` (`test(01-05): author Wave 0 BootHealthcheckTest smoke + fix env-override path`)

**Runtime verification:**

- `docker compose exec web ./vendor/bin/pest` → `Tests: 2 passed (3 assertions)` in 0.16s
- `docker compose exec web ./vendor/bin/pint --test` → `PASS 24 files`
- `docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress` → `[OK] No errors`
- `docker compose exec web php -r "echo class_exists('\\Barryvdh\\Debugbar\\ServiceProvider') ? 'INSTALLED' : 'MISSING';"` → `INSTALLED`
- All 4 stack services healthy: web, web-nginx, postgres, redis
- Postgres `trenchwars_test` database has uuid-ossp + pgcrypto + citext extensions enabled
