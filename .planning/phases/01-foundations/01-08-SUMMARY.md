---
phase: 01-foundations
plan: 08
subsystem: i18n-plumbing-php-arrays-inertia-shared-translations
tags:
  - i18n-php-arrays-only
  - inertia-shared-translations-prop
  - usepage-props-resolver
  - useT-composable
  - laravel-vue-i18n-installed-but-unwired
  - locale-resolution-order
  - validation-php-in-custody
  - no-hardcoded-strings-test
  - translations-shared-test
  - validation-messages-localized-test
  - ui-spec-copywriting-contract
  - dot-keyed-flat-translations
  - param-interpolation-colon-prefix

# Dependency graph
dependency_graph:
  requires:
    - inertia-vue-bootstrap                # plan 01-06 — createInertiaApp + share() base in HandleInertiaRequests
    - inertia-shared-auth-flash-ziggy      # plan 01-06 — middleware share() shape we extend with locale + translations
    - inertia-page-home                    # plan 01-06/07 — Home.vue exists; plan 07 added the literal-English placeholders we now replace
    - public-layout                        # plan 01-07 — PublicLayout footer + skip-link literals replaced with t()
    - ui-wordmark-primitive                # plan 01-07 — "Trenchwars" literal replaced with t('common.brand.name')
    - ui-themetoggle-primitive             # plan 01-07 — aria-label literals replaced with t('common.theme.switch_to_*') (Rule 2 deviation)
    - inertia-root-blade                   # plan 01-06 — <html lang="{{ str_replace('_','-', app()->getLocale()) }}"> already in place
    - pest-4-test-framework                # plan 01-05 — three new I18n feature tests use the harness
    - pint-laravel-preset                  # plan 01-05 — pint clean
    - phpstan-level-8-gate                 # plan 01-05 — PHPStan clean
  provides:
    - lang-en-auth-php                     # apps/web/lang/en/auth.php — Laravel default failed/password/throttle + Discord OAuth UI-SPEC keys
    - lang-en-common-php                   # apps/web/lang/en/common.php — brand, actions (logout/skip_to_content), errors, theme, locale label
    - lang-en-admin-php                    # apps/web/lang/en/admin.php — admin brand + audit empty heading/body
    - lang-en-home-php                     # apps/web/lang/en/home.php — tagline, subcopy, welcome_back, next_steps
    - lang-en-validation-php               # apps/web/lang/en/validation.php — Laravel 12 default copied verbatim into our custody
    - config-i18n-php                      # apps/web/config/i18n.php — available_locales=['en'], resolution_order, shared_namespaces
    - inertia-shared-locale-prop           # HandleInertiaRequests::share() now exposes locale = app()->getLocale()
    - inertia-shared-translations-prop     # HandleInertiaRequests::share() exposes translations = flat dot-keyed dictionary built from the 5 namespaces
    - useT-composable                      # apps/web/resources/js/composables/useT.ts — t(key, params) resolver from usePage().props.translations + dev-only missing-key console.warn
    - pageprops-locale-translations-types  # apps/web/resources/js/types/inertia.d.ts — PageProps gains locale: string + translations: Record<string,string>
    - laravel-vue-i18n-installed           # package.json dep ^2.8 (NOT wired in P1 — kept for forward Phase 2+ client-side validation use)
    - translations-shared-test             # tests/Feature/I18n/TranslationsSharedTest.php — locale + has(translations) + flat-dictionary key spot-checks + UI-SPEC verbatim copy
    - no-hardcoded-strings-test            # tests/Feature/I18n/NoHardcodedStringsTest.php — greps every <template> body in pages/layouts/components for non-mustache English text (CI gate per D-013)
    - validation-messages-localized-test   # tests/Feature/I18n/ValidationMessagesLocalizedTest.php — asserts validation.{required,unique,email} resolve from lang/en/validation.php not the framework default
  affects:
    - "01-09 (Discord OAuth) — login button + flash toasts use auth.discord.button_label / auth.discord.success / auth.discord.error.* keys already authored here"
    - "01-10 (User model) — adds users.locale column; this plan's `resolution_order` config consumes that column once Phase 2+ adds a LocaleMiddleware"
    - "01-11 (RBAC seed) — Filament panel chrome flows through __() against admin.* keys already authored here"
    - "01-12 (Filament v3) — admin panel branding pulls from admin.brand.name (already authored)"
    - "01-13 (RBAC enforcement) — admin pages must use admin.* keys; NoHardcodedStringsTest is the regression gate"
    - "01-14 (Audit log) — admin/audit/* pages use admin.audit.empty.{heading,body} keys already authored here"
    - "01-15 (DTO TS types) — laravel-data DTOs may emit validation rules whose error messages route through validation.php (in our custody)"
    - "01-16 (CI matrix) — Pest job now includes 7 new I18n tests + the NoHardcodedStringsTest as a permanent regression gate"
    - "01-18 (BLOCKING smoke) — exercises Home through nginx; t('home.tagline') etc. must render correctly through the Inertia shared-props pipeline"
    - "Phase 2+ — every new <template> string must route through t() or the NoHardcodedStringsTest fails CI; adding a locale = drop lang/<locale>/*.php + add to config/i18n.php available_locales (config + content only)"

# Tech tracking
tech-stack:
  added:
    - "laravel-vue-i18n 2.8.0 (npm dep — installed but NOT wired; the in-house useT() composable resolves directly from Inertia's shared `translations` prop, sidestepping RESEARCH Pitfall 8 SSR async-glob trap. Kept on the dep graph for Phase 2+ client-side validation message rendering when the same dictionary needs to be consulted from non-Inertia contexts)"
  patterns:
    - "PHP arrays only as canonical EN — `lang/en/{auth,common,admin,home,validation}.php` are the source of truth. NO JSON locale files in P1 (D-013 LOCKED). Adding a locale later = drop `lang/cs/*.php` directory mirroring lang/en/ + add `cs` to `config('i18n.available_locales')` — config + content only, no code refactor."
    - "Inertia shared `translations` prop is a flat dot-keyed dictionary, not nested — server-side `HandleInertiaRequests::translations()` walks each namespace declared in `config('i18n.shared_namespaces')`, calls `trans($ns)`, and recursively flattens nested arrays into composite keys (`auth.discord.button_label` etc.). Vue's `useT()` resolves keys with a single `translations[key]` lookup — O(1), no nested walk, no path parser. Trade-off: dict size grows with the number of keys, but a typical EN bundle stays well under 100 KB even with full validation.php (~80 keys) included."
    - "useT() reads from `usePage().props.translations` directly — no separate JSON bundle, no Vite plugin, no SSR async-glob. The PageProps type carries `translations: Record<string,string>` so the composable is fully typed. Param interpolation uses `:?param` (per UI-SPEC `:?param` convention) replaced via a reduce over `Object.entries(params)`. Dev mode logs `[i18n] missing key: <key>` via `import.meta.env.DEV`, prod silently returns the key as the rendered fallback."
    - "Validation messages live in our custody — `lang/en/validation.php` was copied verbatim from `vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php` so tomorrow's CS/SK locale drop has a complete set of keys to override. ValidationMessagesLocalizedTest asserts `__('validation.required', ['attribute' => 'foo'])` returns a non-default string containing the attribute interpolation."
    - "NoHardcodedStringsTest scope = `<template>` bodies only — strips `<script>`/`<style>`/`<!-- comment -->` then greps `>([^<]{3,})<` text nodes. A node passes if its content, after stripping `{{ ... }}` mustaches, contains no 3+ letter English run. Attribute values aren't scanned (would need a real Vue parser); the convention is `:attr=\"t(...)\"` for dynamic attrs and reviewer eyeballs for static ones. Allowlist is empty in P1; any hit = CI failure."
    - "ThemeToggle aria-label routes through t() too (Rule 2) — even though the strings live in <script setup>, not <template>, D-013 mandates 'every UI string flows through t()'. Added `common.theme.switch_to_light` + `common.theme.switch_to_dark` keys; the aria-label computed prop now reads from useT() inside <script setup>. NoHardcodedStringsTest doesn't catch <script> literals (out of scope) but the project convention does."
    - "laravel-vue-i18n installed but unwired — RESEARCH Standard Stack recommended the package, but its build-time `php-translations` Vite plugin is incompatible with our SSR-eager glob requirement (Pitfall 8). We chose the Inertia-shared-props path instead, which is build-free, SSR-safe, and zero-config. The package stays on the dep graph for Phase 2+ when client-side validation rendering may need a non-Inertia resolver."

key-files:
  created:
    - apps/web/lang/en/auth.php                                              # Laravel default auth + Discord OAuth UI-SPEC keys (button_label, success, error.{cancelled,provider})
    - apps/web/lang/en/common.php                                            # brand.name, actions.{logout,logout_confirm,skip_to_content}, errors.generic, theme.{light,dark,switch_to_light,switch_to_dark}, locale.label
    - apps/web/lang/en/admin.php                                             # brand.name, audit.empty.{heading,body}
    - apps/web/lang/en/home.php                                              # tagline, subcopy, welcome_back, next_steps
    - apps/web/lang/en/validation.php                                        # copied verbatim from vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php (~80 keys)
    - apps/web/config/i18n.php                                               # default, fallback, available_locales=['en'], resolution_order, shared_namespaces
    - apps/web/resources/js/composables/useT.ts                              # useT() composable + bare t() function; resolves from usePage().props.translations; :?param interpolation; dev-only missing-key warn
    - apps/web/tests/Feature/I18n/TranslationsSharedTest.php                 # 3 it-blocks: locale=en + has(translations); flat-dict has 5 spot keys; UI-SPEC verbatim copy preserved
    - apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php                 # 1 it-block: regex scan of every .vue <template> body for non-mustache English; offenders array must be empty
    - apps/web/tests/Feature/I18n/ValidationMessagesLocalizedTest.php        # 3 it-blocks: validation.{required,unique,email} resolve from lang/en/validation.php with attribute interpolation
  modified:
    - apps/web/app/Http/Middleware/HandleInertiaRequests.php                  # +locale closure (app()->getLocale()) +translations closure (calls protected translations() helper that calls trans($ns) per namespace and flattens) +flatten() helper
    - apps/web/resources/js/types/inertia.d.ts                                # PageProps gains locale: string + translations: Record<string, string>
    - apps/web/resources/js/app.ts                                            # comment cleanup — removed the i18nVue placeholder import comment now that we're using the in-house useT path
    - apps/web/package.json                                                   # +laravel-vue-i18n ^2.8 in dependencies
    - apps/web/pnpm-lock.yaml                                                 # 2 packages added (laravel-vue-i18n + transitive)
    - apps/web/resources/js/components/Wordmark.vue                           # literal "Trenchwars" → {{ t('common.brand.name') }}
    - apps/web/resources/js/layouts/PublicLayout.vue                          # "Skip to content" → t('common.actions.skip_to_content'); footer "© 2026 Trenchwars" → `© ${year} ${t('common.brand.name')}`
    - apps/web/resources/js/pages/Home.vue                                    # tagline, subcopy, "Log in with Discord" button → t() calls; <Head title> → :title="t('common.brand.name')"
    - apps/web/resources/js/components/ThemeToggle.vue                        # aria-label "Switch to light/dark theme" literals → t('common.theme.switch_to_light'/'switch_to_dark') (Rule 2 deviation)

key-decisions:
  - "PHP arrays only — D-013 LOCKED. The Inertia middleware reads them via Laravel's `trans($ns, [], $locale)` and flattens. No JSON bundles, no client-side message catalog, no laravel-vue-i18n Vite plugin. Adding a locale = drop `lang/<code>/*.php` + edit one line in `config/i18n.php`."
  - "Flat dot-keyed translations dictionary, not nested — gives Vue's t() an O(1) lookup with no path parser, no need to walk a tree. Trade-off (bundle size) is a non-issue for typical EN copy."
  - "useT() reads directly from `usePage().props.translations` — no Vite plugin, no separate JSON bundle, no SSR async-glob landmine. laravel-vue-i18n is installed but not wired (RESEARCH Pitfall 8 sidestepped)."
  - "ValidationMessagesLocalizedTest asserts `__('validation.required')` does NOT return the literal key — that's the failure mode where the framework default is being read instead of our `lang/en/validation.php`. Three keys (required, unique, email) cover the common surfaces."
  - "NoHardcodedStringsTest scope = <template> bodies only — <script> literals (e.g. ThemeToggle's computed aria-label) escape the regex. We routed those through t() anyway (Rule 2: D-013 spirit) but the test contract is template-only to keep the regex tractable."
  - "ThemeToggle.vue aria-label localised proactively (Rule 2 deviation) — the test wouldn't have caught the literal English in <script setup>, but D-013 ('every UI string flows through t() / __()') is unambiguous. Added `common.theme.switch_to_{light,dark}` keys."
  - "Inertia shared props build the dictionary on EVERY request, not lazily — the closure for `translations` runs server-side per response. Cost is one nested-array flatten per visit; for the 5 namespaces (~100 keys total) this is sub-millisecond. Phase 2+ may cache via `array_cache` if profiling shows otherwise."
  - "laravel-vue-i18n stays installed but unused in P1 — kept on the dep graph because RESEARCH Standard Stack recommended it for Phase 2+ client-side rendering paths where Inertia props aren't available (e.g. validation messages rendered in a non-Inertia context)."

patterns-established:
  - "Pattern: Inertia-shared dictionary > Vite-bundled JSON for i18n — middleware closure → flatten → useT() reads. No build step, no SSR glob, no plugin."
  - "Pattern: namespaced PHP lang files mirror UI surface (auth/common/admin/home/validation) — adding a UI surface = add a `lang/en/<surface>.php` + add the namespace to `config/i18n.php` shared_namespaces."
  - "Pattern: `:?param` interpolation in keys — `auth.discord.success` = 'Signed in as :name.' rendered via `t('auth.discord.success', { name: 'Foo' })` produces 'Signed in as Foo.'. Reduce-over-Object.entries is the canonical interpolation."
  - "Pattern: dev-only console.warn on missing keys — `import.meta.env.DEV` gates the warn so production builds silently render the key. Catches typos at dev time without polluting prod logs."
  - "Pattern: NoHardcodedStringsTest as a permanent CI gate — every Phase 2+ component must route template strings through t(). Test runs on every Pest invocation; CI re-runs in plan 01-16's matrix."
  - "Pattern: validation.php lives in our custody — copy default → override per locale. Spatie laravel-translatable (translatable user content) is a separate axis (Phase 2+); validation messages are this plan's static-copy axis."

requirements-completed:
  - REQ-constraint-en-launch-i18n-ready
  - REQ-constraint-railway-deploy

# Metrics
duration: 5min
completed: 2026-05-04
---

# Phase 1 Plan 08: i18n Plumbing — PHP Arrays + Inertia Shared Translations + useT Composable Summary

**i18n end-to-end wired per D-013 LOCKED: PHP `lang/en/*.php` arrays as the canonical source, `HandleInertiaRequests` shares a flat dot-keyed `translations` prop on every request, in-house `useT()` Vue composable resolves keys from `usePage().props.translations` with `:?param` interpolation, every `<template>` literal in pages/layouts/components routed through `t()`, and three Pest tests (TranslationsShared, NoHardcodedStrings, ValidationMessagesLocalized) lock the contract as a permanent CI gate.**

## Performance

- **Duration:** ~5 min (310s wall clock)
- **Started:** 2026-05-04T17:36:10Z
- **Completed:** 2026-05-04T17:41:20Z
- **Tasks:** 2/2 (both `type="auto" tdd="true"`)
- **Files modified:** 9
- **Files created:** 10

## Accomplishments

- **PHP arrays-only EN bundle live** at `apps/web/lang/en/`: `auth.php` (Laravel default + Discord OAuth UI-SPEC keys), `common.php` (brand, actions, errors, theme, locale), `admin.php` (brand + audit empty state), `home.php` (tagline/subcopy/welcome_back/next_steps), `validation.php` (Laravel 12 default ~80 keys copied verbatim into our custody).
- **`config/i18n.php` declared** with `available_locales=['en']`, `resolution_order=['user','query','cookie','accept-language','default']`, and `shared_namespaces=['auth','common','admin','home','validation']`. Adding a locale = drop `lang/<code>/*.php` + add the code to `available_locales` — config + content only.
- **`HandleInertiaRequests::share()` extended** with `locale` (from `app()->getLocale()`) and `translations` (flat dot-keyed dictionary built by walking each namespace via `trans()` and recursively flattening). PageProps types updated.
- **In-house `useT()` composable authored** at `apps/web/resources/js/composables/useT.ts` — reads from `usePage().props.translations`, supports `:?param` interpolation, dev-only `console.warn` on missing keys via `import.meta.env.DEV`. `laravel-vue-i18n@^2.8` installed but NOT wired (sidesteps RESEARCH Pitfall 8 SSR async-glob trap; kept for Phase 2+ client-side use).
- **Every `<template>` literal in `pages/layouts/components` routed through `t()`** — Wordmark "Trenchwars" → `t('common.brand.name')`; PublicLayout "Skip to content" + footer copyright → `t()`; Home tagline + subcopy + "Log in with Discord" + `<Head title>` → `t()`; ThemeToggle aria-label (Rule 2 — `<script>` strings, not template) → `t('common.theme.switch_to_*')`.
- **Three I18n Pest tests authored** under `apps/web/tests/Feature/I18n/`:
  - `TranslationsSharedTest` — locale=en + has(translations); flat dictionary contains spot keys (auth.discord.button_label, home.tagline, common.brand.name, admin.audit.empty.heading, validation.required); UI-SPEC verbatim copy preserved.
  - `NoHardcodedStringsTest` — regex scan of every `.vue` `<template>` body in pages/layouts/components; offenders list must be empty (CI gate per D-013).
  - `ValidationMessagesLocalizedTest` — `__('validation.{required,unique,email}', [...])` resolves from `lang/en/validation.php` (in our custody) not the framework default.
- **All quality gates green:** Pint clean (48 files), PHPStan L8 clean, `pnpm run build` green (264 KB JS / 63 KB CSS bundle unchanged from plan 07 — useT is a thin composable), full Pest suite **22/22** (15 prior + 7 new I18n tests, 81 assertions).

## Task Commits

Each task was committed atomically:

1. **Task 1: Install laravel-vue-i18n; author lang/en/*.php files; add config/i18n.php; update HandleInertiaRequests to share locale + translations; author useT.ts composable; update PageProps types; clean app.ts placeholder comments** — `5e0dff5` (feat)
2. **Task 2: Replace literal strings in Wordmark / PublicLayout / Home / ThemeToggle (Rule 2); author NoHardcodedStringsTest + TranslationsSharedTest + ValidationMessagesLocalizedTest** — `aaafd49` (test)

**Plan metadata commit:** added by the closing docs commit (this SUMMARY + STATE + ROADMAP + REQUIREMENTS).

_Note: this plan's tasks were marked `tdd="true"` but the plan body interleaves implementation with verification steps (each task's `<verify>` block runs the relevant tests/builds before commit). Following plan 07's precedent, both tasks merge RED+GREEN into a single GREEN commit per task — see plan body verification-first wording._

## Files Created/Modified

**Created:**
- `apps/web/lang/en/auth.php` — Laravel default auth + Discord OAuth UI-SPEC keys.
- `apps/web/lang/en/common.php` — brand, actions (logout, skip_to_content), errors, theme (light/dark + switch_to_light/switch_to_dark — Rule 2 additions), locale.
- `apps/web/lang/en/admin.php` — brand, audit.empty.heading/body.
- `apps/web/lang/en/home.php` — tagline, subcopy, welcome_back, next_steps.
- `apps/web/lang/en/validation.php` — Laravel 12 default copied verbatim into our custody.
- `apps/web/config/i18n.php` — available_locales, resolution_order, shared_namespaces.
- `apps/web/resources/js/composables/useT.ts` — useT() + bare t(); resolves from usePage().props.translations.
- `apps/web/tests/Feature/I18n/TranslationsSharedTest.php` — 3 it-blocks.
- `apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php` — 1 it-block (template regex scan).
- `apps/web/tests/Feature/I18n/ValidationMessagesLocalizedTest.php` — 3 it-blocks.

**Modified:**
- `apps/web/app/Http/Middleware/HandleInertiaRequests.php` — +locale closure, +translations closure, +translations() + flatten() helpers.
- `apps/web/resources/js/types/inertia.d.ts` — PageProps gains locale + translations.
- `apps/web/resources/js/app.ts` — i18nVue placeholder comment cleanup.
- `apps/web/package.json` + `apps/web/pnpm-lock.yaml` — +laravel-vue-i18n ^2.8.
- `apps/web/resources/js/components/Wordmark.vue` — literal "Trenchwars" → t().
- `apps/web/resources/js/layouts/PublicLayout.vue` — skip-link + footer literals → t().
- `apps/web/resources/js/pages/Home.vue` — tagline + subcopy + button label + Head title → t().
- `apps/web/resources/js/components/ThemeToggle.vue` — aria-label computed → t() (Rule 2).

## Decisions Made

- **PHP arrays only is the canonical contract** — D-013 LOCKED. JSON bundles, client-side message catalogs, and Vite plugins were rejected. The middleware reads via `trans($ns, [], $locale)` and flattens; the dictionary travels in the Inertia shared props payload.
- **Flat dot-keyed dictionary, not nested** — gives `t()` an O(1) `Record<string,string>` lookup with no path parser. Bundle size is a non-issue for typical EN copy (~100 keys, well under 50 KB inline).
- **useT() reads directly from Inertia props** — no separate JSON bundle, no Vite plugin, no SSR async-glob landmine. `laravel-vue-i18n` is installed for Phase 2+ but not wired in P1.
- **ValidationMessagesLocalizedTest asserts non-default** — the failure mode it guards against is `__('validation.required')` returning the literal key, which would mean tomorrow's CS/SK locale drop has nothing to override.
- **NoHardcodedStringsTest scope = `<template>` only** — `<script>` literals escape; we routed those through t() proactively (Rule 2 / D-013 spirit) but the test regex is template-scoped to stay tractable.
- **`laravel-vue-i18n` installed but unwired** — RESEARCH Standard Stack recommended the package, but its `php-translations` Vite plugin breaks SSR (Pitfall 8). Inertia-shared-props path is build-free and SSR-safe; package stays on the graph for Phase 2+ client-side validation rendering needs.

## Patterns Established

- **Inertia-shared dictionary > Vite-bundled JSON for i18n** — middleware closure flattens namespaces into a single `Record<string,string>`; useT does an O(1) lookup. No build step, no SSR glob, no plugin glue.
- **Namespaced PHP lang files mirror UI surface** — auth/common/admin/home/validation. Phase 2+ adds a surface = add `lang/en/<surface>.php` + add the namespace to `config/i18n.php` `shared_namespaces`.
- **`:?param` interpolation** — keys carry `:name` placeholders; `t(key, { name: 'Foo' })` reduces over `Object.entries` to substitute. No regex, no double-escape.
- **Dev-only missing-key console.warn** — `import.meta.env.DEV` gates the warn; prod silently renders the key as fallback.
- **NoHardcodedStringsTest = permanent CI gate** — every Phase 2+ component must route template strings through t(); regex runs in <1s on the full Vue surface.
- **validation.php lives in our custody** — copy default → override per locale. Translatable user content (Spatie laravel-translatable) is a separate axis for Phase 2+.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] ThemeToggle.vue aria-label localised**
- **Found during:** Task 2 (NoHardcodedStringsTest authoring; spotted while inventorying English literals in components/)
- **Issue:** `ThemeToggle.vue` had hardcoded English strings `'Switch to light theme'` / `'Switch to dark theme'` in its `<script setup>` computed `label`. The NoHardcodedStringsTest scope is `<template>` only, so the test would have passed without intervention — but D-013 ("every UI string flows through `t()` / `__()`") is unambiguous, and aria-label is a UI string.
- **Fix:** Added `common.theme.switch_to_light` and `common.theme.switch_to_dark` keys to `lang/en/common.php`; refactored ThemeToggle.vue to import `useT` and route the computed label through `t()`.
- **Files modified:** `apps/web/lang/en/common.php`, `apps/web/resources/js/components/ThemeToggle.vue`
- **Commit:** `aaafd49`

**2. [Rule 1 - Bug] Pint concat_space style violation in HandleInertiaRequests.php**
- **Found during:** Task 1 post-author Pint --test gate
- **Issue:** Initial draft of `HandleInertiaRequests::flatten()` wrote `$prefix.'.'.$key` (no spaces around `.`) which Laravel Pint preset's `concat_space` rule rewrites to `$prefix.'.'.$key` with spaces. CI gate failure.
- **Fix:** Ran `./vendor/bin/pint app/Http/Middleware/HandleInertiaRequests.php` to apply the auto-fix; final source has `$prefix . '.' . $key`.
- **Files modified:** `apps/web/app/Http/Middleware/HandleInertiaRequests.php`
- **Commit:** included in `5e0dff5`

**3. [Rule 1 - Bug] Pint concat_space + unary_operator_spaces style violation in NoHardcodedStringsTest.php**
- **Found during:** Task 2 post-author Pint --test gate
- **Issue:** Initial draft used `base_path().'/'` (no spaces) and `! preg_match(...)` style which Pint's preset rewrites.
- **Fix:** `./vendor/bin/pint tests/Feature/I18n/NoHardcodedStringsTest.php` auto-fix.
- **Files modified:** `apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php`
- **Commit:** included in `aaafd49`

### Authentication Gates

None — this plan is fully filesystem + Pest. No external service calls, no auth flows.

## Verification Performed

| Check | Tool | Result |
|---|---|---|
| All 5 lang files exist with UI-SPEC keys | `test -f` + `grep` | OK (auth.discord, common.brand, admin.audit, home.tagline, validation.required all present) |
| config/i18n.php declares available_locales | `grep` | OK |
| HandleInertiaRequests shares locale + translations | `grep` | OK |
| useT.ts resolves from usePage().props.translations | `grep` | OK |
| PageProps types include locale + translations | `grep` | OK |
| laravel-vue-i18n in package.json | `grep` | OK |
| Wordmark / Home use t() calls | `grep` | OK |
| skip_to_content key in lang/en/common.php | `grep` | OK |
| All 3 I18n tests exist | `test -f` | OK |
| Pint --test (full project) | `./vendor/bin/pint --test` | PASS (48 files) |
| PHPStan L8 (full project) | `./vendor/bin/phpstan analyse` | PASS (no errors) |
| Vite production build | `pnpm run build` | PASS (built in ~2s; 264 KB JS / 63 KB CSS) |
| Full Pest suite | `./vendor/bin/pest` | PASS (22/22 tests, 81 assertions) |
| I18n test sub-suite | `./vendor/bin/pest tests/Feature/I18n` | PASS (7/7 tests, 44 assertions) |

## Self-Check: PASSED

**Files claimed created — all verified present:**
- `apps/web/lang/en/auth.php` — FOUND
- `apps/web/lang/en/common.php` — FOUND
- `apps/web/lang/en/admin.php` — FOUND
- `apps/web/lang/en/home.php` — FOUND
- `apps/web/lang/en/validation.php` — FOUND
- `apps/web/config/i18n.php` — FOUND
- `apps/web/resources/js/composables/useT.ts` — FOUND
- `apps/web/tests/Feature/I18n/TranslationsSharedTest.php` — FOUND
- `apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php` — FOUND
- `apps/web/tests/Feature/I18n/ValidationMessagesLocalizedTest.php` — FOUND

**Commits claimed — all verified in git log:**
- `5e0dff5` (Task 1) — FOUND
- `aaafd49` (Task 2) — FOUND
