---
phase: 01-foundations
plan: 12
subsystem: admin-panel
tags: [filament, tailwind, vite, postcss, rbac-gate, dual-tailwind-workaround]
dependency-graph:
  requires: [01-07, 01-11]
  provides:
    - "filament/filament v3.3.50 installed; AdminPanelProvider customised for Discord-OAuth-only auth"
    - "Dual-Tailwind workaround proven: Tailwind v4 (main) + Tailwind v3 (Filament theme via tailwindcss-v3 alias) build cleanly side-by-side"
    - "vite.filament.config.ts emits to public/build/filament/ (separate manifest)"
    - "AdminPanelProvider gates panel via canAccessPanel('admin-access') — admins only"
    - "Filament panel reachable at /admin (302→Discord for guests; 403 for non-admins; 200 for admins)"
    - "User model implements FilamentUser + HasName contracts"
    - "App\\Http\\Middleware\\RedirectFilamentAuthToDiscord — Filament auth redirects to /auth/discord/redirect (no built-in login form)"
  affects:
    - "plan 01-13 Filament resources (User/Player/Role/Permission) — register in panel resources() array"
    - "plan 01-14 audit log /admin/audit page — register in panel pages() array; canAccessPanel covers it"
    - "plan 01-15 spatie-laravel-data DTO TS export — Filament forms can later consume DTO classes"
tech-stack:
  added:
    - "filament/filament ^3.3 (resolved 3.3.50) + 23 transitive (livewire, blade-icons, eloquent-power-joins, etc.)"
    - "tailwindcss-v3@npm:tailwindcss@^3.4 (resolved 3.4.19) — alias install for Filament theme only"
    - "@tailwindcss/forms ^0.5 (0.5.11), @tailwindcss/typography ^0.5 (0.5.19)"
    - "autoprefixer ^10 (10.5.0), postcss ^8 (8.5.13)"
  patterns:
    - "Dual-Tailwind workaround: separate Vite config + separate PostCSS chain for Filament theme; main vite.config.ts pinned to css.postcss:{plugins:[]} so it ignores the auto-detected postcss.config.js"
    - "ESM-everywhere: postcss.config.js, tailwind.filament.config.js, vite.filament.config.ts all use ESM (matches package.json type:module); Filament preset is ESM-imported directly"
    - "Filament Authenticate subclass to redirect to Discord OAuth (preserves canAccessPanel check while bypassing Filament::getLoginUrl() null path)"
    - "Filament theme.css inlines vendor base-layer overrides instead of @import-ing vendor/filament/.../theme.css (Vite CSS resolver tries to follow the inner `@import 'tailwindcss/base'` chain through Tailwind v4 and fails)"
key-files:
  created:
    - "apps/web/app/Providers/Filament/AdminPanelProvider.php (customised — id=admin, accent #A4262C, dark mode, Inter, viteTheme to build/filament, no ->login())"
    - "apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php"
    - "apps/web/postcss.config.js (Filament-only PostCSS chain)"
    - "apps/web/tailwind.filament.config.js (Tailwind v3 config; Filament preset + primary palette #A4262C)"
    - "apps/web/vite.filament.config.ts"
    - "apps/web/resources/css/filament/admin/theme.css"
    - "apps/web/tests/Feature/Admin/FilamentBootTest.php"
    - "apps/web/tests/Feature/Admin/FilamentPanelAccessTest.php"
  modified:
    - "apps/web/composer.json (+filament/filament ^3.3)"
    - "apps/web/composer.lock"
    - "apps/web/package.json (+tailwindcss-v3 alias + 4 deps; build script chained)"
    - "apps/web/pnpm-lock.yaml"
    - "apps/web/vite.config.ts (+css.postcss:{plugins:[]} pin to ignore the Filament-only postcss.config.js)"
    - "apps/web/app/Models/User.php (+FilamentUser, HasName, canAccessPanel, getFilamentName)"
    - "apps/web/app/Providers/AppServiceProvider.php (+Authenticate::redirectUsing belt-and-braces)"
    - "apps/web/bootstrap/providers.php (auto-registered AdminPanelProvider + Pint cleanup)"
    - "apps/web/.gitignore (+ /public/css/filament + /public/js/filament — republished by composer post-autoload-dump)"
decisions:
  - "Filament v3.3 LOCKED (D-001); v4 rejected. Dual-Tailwind workaround is the documented Filament v3 + Tailwind v4 coexistence path."
  - "ESM everywhere — apps/web/package.json declares type:module; Filament's tailwind.config.preset.js is ESM (export default), so tailwind.filament.config.js is ESM (.js, not .cjs) and import-s the preset directly."
  - "Pin main vite.config.ts to css.postcss:{plugins:[]} — Vite auto-detects postcss.config.js from project root; without this, the Filament-only Tailwind v3 chain runs against app.css and fails on `@layer base used but no @tailwind base directive` (Tailwind v4 directives differ)."
  - "Drop @import 'vendor/filament/filament/resources/css/theme.css' from the Filament theme.css — Vite CSS resolver runs before PostCSS and chases the inner `@import 'tailwindcss/base'` through Tailwind v4 (no /base subpath). Inline the small `[data-field-wrapper]` and `:root.dark { color-scheme: dark }` overrides instead. Filament preset's content array still scans Filament's blade files so all classes are extracted."
  - "Dropped Filament built-in ->login() (Open Question #4); RedirectFilamentAuthToDiscord subclass returns route('auth.discord.redirect') from redirectTo() — preserves Filament's authenticate() override (which performs canAccessPanel check) while routing guests to Discord."
  - "Restored Pages\\Dashboard::class as the panel's default landing — without ANY pages, GET /admin's RedirectToHomeController returns 404 (no navigable home). Plan 14 will add /admin/audit alongside."
  - "Implement Filament\\Models\\Contracts\\HasName + getFilamentName() returning $this->username — Filament's getUserName() falls back to $user->name (which doesn't exist on our schema; D-002 makes Discord username canonical)."
  - "Gitignore /public/css/filament + /public/js/filament — the filament:install --panels command publishes ~2.3MB of vendor JS/CSS that's regenerated by `php artisan filament:upgrade` (auto-fired by the composer post-autoload-dump script the installer added). Standard Filament/Laravel practice."
metrics:
  duration_minutes: 9
  tasks_completed: 2
  files_created: 8
  files_modified: 9
  tests_added: 5
completed_date: 2026-05-04
---

# Phase 01 Plan 12: Filament v3 install + dual-Tailwind workaround + admin-access gating Summary

## One-liner

Filament v3.3.50 installed at `/admin`, gated by `admin-access` permission via `FilamentUser::canAccessPanel`, with the dual-Tailwind workaround (Tailwind v4 main + Tailwind v3 alias for Filament theme) building cleanly into separate manifests; unauthenticated panel hits redirect to Discord OAuth via a custom `RedirectFilamentAuthToDiscord` middleware (Filament's built-in login form is dropped).

## What was built

**Task 1 — Install + dual-Tailwind workaround (commit `a0bd71b`):**

- `composer require filament/filament:^3.3` (resolved 3.3.50) inside the web container per D-021. 24 packages added (livewire, blade-icons, eloquent-power-joins, danharrin/livewire-rate-limiting, etc.).
- `php artisan filament:install --panels` — scaffolded `app/Providers/Filament/AdminPanelProvider.php`, auto-registered in `bootstrap/providers.php`, and added `@php artisan filament:upgrade` to the composer `post-autoload-dump` hook (republishes vendor JS/CSS on every composer install).
- `pnpm add -D 'tailwindcss-v3@npm:tailwindcss@^3.4' '@tailwindcss/forms@^0.5' '@tailwindcss/typography@^0.5' 'autoprefixer@^10' 'postcss@^8'` — installs Tailwind v3 alongside the existing Tailwind v4 (no version conflict, the alias keeps them as separate packages on disk).
- Authored `vite.filament.config.ts`, `postcss.config.js`, `tailwind.filament.config.js`, `resources/css/filament/admin/theme.css` per RESEARCH Pitfall 1.
- Updated `package.json` build script: `"vite build && vite build --config vite.filament.config.ts"` so a single `pnpm run build` produces BOTH bundles.
- `pnpm run build` emits `public/build/manifest.json` (main, Tailwind v4) AND `public/build/filament/manifest.json` (Filament theme, Tailwind v3) — workaround proven.

**Task 2 — AdminPanelProvider customisation + tests (commit `9e93524`):**

- AdminPanelProvider customised: `id('admin')`, `path('admin')`, `brandName(__('admin.brand.name'))`, `colors(['primary' => Color::hex('#A4262C')])`, `darkMode()`, `font('Inter')`, `viteTheme('resources/css/filament/admin/theme.css', 'build/filament')`, NO `->login()` (Open Question #4), Pages\Dashboard kept as default landing.
- `App\Http\Middleware\RedirectFilamentAuthToDiscord` extends `Filament\Http\Middleware\Authenticate` and overrides `redirectTo()` to return `route('auth.discord.redirect')` — preserves Filament's `authenticate()` override (which performs the `canAccessPanel` 403 check, Pitfall 4 mitigation) while bypassing `Filament::getLoginUrl()`'s null-when-`->login()`-absent code path that would otherwise resolve to the undefined `login` named route.
- User model now implements `FilamentUser` + `HasName`: `canAccessPanel(Panel)` returns `$this->hasPermissionTo('admin-access', 'web')` (only for the admin panel id); `getFilamentName()` returns `$this->username` (avatar tooltip + header dropdown render correctly).
- AppServiceProvider also calls `Authenticate::redirectUsing(fn () => route('auth.discord.redirect'))` as defence-in-depth for any non-Filament `auth` middleware.
- 5 Pest tests in `tests/Feature/Admin/`: unauthenticated /admin → 302 to Discord; admin → 200; non-admin → 403; admin boots panel without errors; /admin/login is not 200 (no built-in form).

## Verification

- Pest: 40/40 green (5 new + 35 existing — no regressions).
- Pint: 63 files clean.
- PHPStan L8: no errors.
- Vite: `pnpm run build` emits both manifests (`public/build/manifest.json` 28 kB main app CSS + `public/build/filament/manifest.json` 110 kB Filament theme).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] postcss.config.js Vite auto-detection collision**
- **Found during:** Task 1 first build attempt
- **Issue:** Vite auto-detects `postcss.config.js` at the project root and applies it to BOTH builds. The main `vite build` (using `@tailwindcss/vite` for Tailwind v4) failed with `@layer base used but no matching @tailwind base directive` because Tailwind v3's PostCSS plugin was running against Tailwind-v4-style CSS.
- **Fix:** Pinned the main `vite.config.ts` to `css: { postcss: { plugins: [] } }` so it explicitly ignores the auto-detected PostCSS config. The Filament `vite.filament.config.ts` does NOT set this and therefore picks up `postcss.config.js` automatically (which is what we want).
- **Files modified:** `apps/web/vite.config.ts`
- **Commit:** `a0bd71b`

**2. [Rule 3 - Blocking] ESM vs CJS for Filament tailwind preset**
- **Found during:** Task 1 authoring
- **Issue:** The plan's snippet had `tailwind.filament.config.cjs` (CJS) using `require('./vendor/filament/support/tailwind.config.preset')`. The preset is ESM (`export default`), and `apps/web/package.json` declares `"type": "module"`. CJS `require()` of an ESM module fails under Node, and the preset has no CJS variant.
- **Fix:** Renamed `tailwind.filament.config.cjs` → `tailwind.filament.config.js` (ESM, with `import preset from './vendor/filament/support/tailwind.config.preset.js'`). Also wrote `postcss.config.js` as ESM (`export default { plugins: { ... } }`). The `postcss.config.js` plugin reference now points to `./tailwind.filament.config.js` (the new ESM filename).
- **Files modified:** `apps/web/postcss.config.js`, `apps/web/tailwind.filament.config.js` (new path)
- **Commit:** `a0bd71b`

**3. [Rule 3 - Blocking] Filament vendor theme.css @import chain breaks Vite CSS resolver**
- **Found during:** Task 1 second build attempt
- **Issue:** The plan's snippet had the Filament theme.css `@import "../../../../vendor/filament/filament/resources/css/theme.css"`. Vite's CSS resolver runs BEFORE PostCSS and follows the import chain — Filament's vendor theme.css imports `'tailwindcss/base'`/`'tailwindcss/components'`/`'tailwindcss/utilities'`/`'tailwindcss/variants'` as bare specifiers, which Vite resolves to the `tailwindcss` package (Tailwind v4) which has no `/base` subpath. Build fails with `Missing "./base" specifier in "tailwindcss" package`.
- **Fix:** Dropped the vendor theme.css `@import`. The Filament Tailwind preset's content array (`./vendor/filament/**/*.blade.php`) already scans Filament's blade templates, so all utility classes are still extracted. The handful of small base-layer overrides Filament's vendor theme.css emits (the `[data-field-wrapper]` scroll-margin and the `:root.dark { color-scheme: dark }` rule) were inlined directly into our theme.css under `@layer base { ... }`.
- **Files modified:** `apps/web/resources/css/filament/admin/theme.css`
- **Commit:** `a0bd71b`

**4. [Rule 1 - Bug] Filament's Authenticate middleware ignores Authenticate::redirectUsing**
- **Found during:** Task 2 first test run
- **Issue:** Plan said to set `Authenticate::redirectUsing(...)` in AppServiceProvider so unauthenticated /admin redirects to Discord. But Filament's `Filament\Http\Middleware\Authenticate` overrides Laravel's `redirectTo()` to return `Filament::getLoginUrl()` — which is null when `->login()` is dropped. The parent middleware then falls back to the static `route('login')` (undefined in this app), producing `Route [login] not defined`.
- **Fix:** Created `App\Http\Middleware\RedirectFilamentAuthToDiscord` extending Filament's `Authenticate`, overriding `redirectTo()` to return `route('auth.discord.redirect')`. Wired it into the panel's `authMiddleware([RedirectFilamentAuthToDiscord::class])` instead of `Authenticate::class`. The AppServiceProvider's `Authenticate::redirectUsing(...)` was kept as defence-in-depth for any non-Filament middleware that may exist later.
- **Files modified:** `apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php` (new), `apps/web/app/Providers/Filament/AdminPanelProvider.php`
- **Commit:** `9e93524`

**5. [Rule 2 - Missing critical functionality] Empty pages array breaks /admin landing**
- **Found during:** Task 2 second test run
- **Issue:** Plan said `pages([])` (empty stub, "plan 14 registers Audit page"). Without ANY page, Filament's `RedirectToHomeController` (the controller behind GET /admin) calls `$panel->getUrl()` which returns null, then `abort(404)`. Admin tests expecting `200` got `302→404`.
- **Fix:** Re-included the auto-scaffolded `Filament\Pages\Dashboard::class` in the `pages()` array. This is Filament's default landing widget host — it gives admins a visible home page until plan 13 adds resources. The plan's `# Plan 14 registers the Audit page.` comment is preserved alongside.
- **Files modified:** `apps/web/app/Providers/Filament/AdminPanelProvider.php`
- **Commit:** `9e93524`

**6. [Rule 2 - Missing critical functionality] Filament getUserName fails on missing `name` attribute**
- **Found during:** Task 2 third test run
- **Issue:** Filament's chrome (avatar tooltip, header dropdown) calls `Filament::getUserName($user)` which falls back to `$user->getAttributeValue('name')` when the user does not implement `Filament\Models\Contracts\HasName`. Our User model has `username` not `name` (D-002 — Discord username is canonical), so the call returns null and the `getUserName(): string` return-type contract throws.
- **Fix:** Added `implements FilamentUser, HasName` and `getFilamentName(): string { return $this->username; }` on the User model.
- **Files modified:** `apps/web/app/Models/User.php`
- **Commit:** `9e93524`

**7. [Rule 1 - Bug] Pint cleanup of bootstrap/providers.php after Filament installer wrote it**
- **Found during:** Task 1 pre-commit verification
- **Issue:** Filament's installer auto-added `App\Providers\Filament\AdminPanelProvider::class` to `bootstrap/providers.php` without `declare(strict_types=1)` and with concatenated formatting that tripped the project's Pint preset (`fully_qualified_strict_types`, `single_line_after_imports`).
- **Fix:** Ran `./vendor/bin/pint` to auto-format. Result: imports promoted to `use` statements, single blank line between import block and array. No behaviour change.
- **Files modified:** `apps/web/bootstrap/providers.php`
- **Commit:** `a0bd71b`

**8. [Rule 2 - Missing critical functionality] Filament-published vendor assets need gitignore**
- **Found during:** Task 1 pre-commit
- **Issue:** `php artisan filament:install --panels` published ~2.3MB of vendor CSS/JS into `apps/web/public/css/filament/` + `apps/web/public/js/filament/`. These are runtime-required by Filament's Livewire chrome but are also re-emitted on every `composer install` (the installer added `@php artisan filament:upgrade` to the `post-autoload-dump` hook). Committing them = perpetual lockfile-style churn; standard Filament/Laravel practice is to gitignore them.
- **Fix:** Added `/public/css/filament` and `/public/js/filament` to `apps/web/.gitignore` (alongside the existing `/public/build` entry).
- **Files modified:** `apps/web/.gitignore`
- **Commit:** `a0bd71b`

### Auth Gates

None. The plan executed without requiring any human-action checkpoints.

## Threat Surface Confirmation

All four threats from the plan's threat register are mitigated:

| Threat | Mitigation in code |
|--------|-------------------|
| T-1-04 EoP — panel access bypass | `User::canAccessPanel(Panel)` checks `hasPermissionTo('admin-access', 'web')` with explicit guard; `User::$guard_name='web'`; `config/permission.php` `default_guard_name='web'`; tests assert non-admin → 403 and admin → 200 |
| T-1-06 Tampering CSRF | `VerifyCsrfToken::class` in panel middleware stack |
| T-1-07 Spoofing session | `EncryptCookies::class` + `AuthenticateSession::class` in panel middleware stack |
| T-1-09 InfoDisclosure /admin/login form | `->login()` NOT called on the panel; test asserts `/admin/login` is not 200 |

No new threat surfaces introduced beyond the plan's register.

## Self-Check: PASSED

**Files verified:**
- FOUND: apps/web/app/Providers/Filament/AdminPanelProvider.php
- FOUND: apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php
- FOUND: apps/web/postcss.config.js
- FOUND: apps/web/tailwind.filament.config.js
- FOUND: apps/web/vite.filament.config.ts
- FOUND: apps/web/resources/css/filament/admin/theme.css
- FOUND: apps/web/tests/Feature/Admin/FilamentBootTest.php
- FOUND: apps/web/tests/Feature/Admin/FilamentPanelAccessTest.php
- FOUND: apps/web/public/build/manifest.json
- FOUND: apps/web/public/build/filament/manifest.json

**Commits verified:**
- FOUND: a0bd71b (feat(01-12): install Filament v3 + dual-Tailwind workaround)
- FOUND: 9e93524 (feat(01-12): customise AdminPanelProvider + gate panel via admin-access permission)
