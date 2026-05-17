---
phase: 01-foundations
plan: 06
subsystem: inertia-vue-vite-frontend-pipeline
tags:
  - inertia-laravel-2
  - inertia-vue3-2
  - vue-3.5
  - vite-7
  - vitejs-plugin-vue-6
  - laravel-vite-plugin-2
  - ziggy-2.6
  - ziggy-js-2.6
  - typescript-5.9
  - vue-server-renderer-3.5
  - ssr-scaffold
  - inertia-csrf-pitfall-3
  - tsconfig-extends-base
  - phpunit-server-tag-runtime-parallel
dependency_graph:
  requires:
    - laravel-12-skeleton                  # plan 01-04
    - pest-4-test-framework                # plan 01-05 — InertiaSmokeTest extends Pest harness
    - phpstan-level-8-gate                 # plan 01-05 — added HandleInertiaRequests + bootstrap/app.php to analysis paths
    - pint-laravel-preset                  # plan 01-05 — pint applied to new + modified PHP files
    - boot-healthcheck-test                # plan 01-05 — Wave 0 anchor still green after Inertia wiring
    - dual-tailwind-v4-already-installed   # plan 01-04 (Laravel 12 default) — Tailwind plugin commented out in vite.config.ts so plan 07 enables cleanly
    - tsconfig-base-at-repo-root           # plan 01-02 — workspace base config; bind-mounted into web container by this plan
  provides:
    - inertia-v2-server-adapter            # apps/web vendor/inertiajs/inertia-laravel ^2.0 (v2.0.24)
    - ziggy-server                         # apps/web vendor/tightenco/ziggy ^2.6 (v2.6.2) — `@routes` blade directive available
    - handle-inertia-requests-middleware   # app/Http/Middleware/HandleInertiaRequests sharing auth + flash + ziggy props (registered on `web` group)
    - inertia-root-blade                   # resources/views/app.blade.php with @inertia + @inertiaHead + @vite + @routes (no <meta csrf-token> per Pitfall 3)
    - inertia-config-published             # config/inertia.php published; SSR enabled=false default for dev; page_paths -> resources/js/pages
    - vue3-client                          # node_modules vue@^3.5.33 + @inertiajs/vue3@^2.3.21 (legacy v2 dist-tag) + @vitejs/plugin-vue@^6.0.6 + ziggy-js@^2.6.2
    - vite-config-ts                       # vite.config.ts with vue + laravel-vite-plugin (Tailwind plugin commented; plan 07 enables)
    - inertia-vue-bootstrap                # resources/js/app.ts createInertiaApp + ZiggyVue + glob page resolver
    - inertia-ssr-scaffold                 # resources/js/ssr.ts createServer + renderToString (SSR off in dev; production opt-in via INERTIA_SSR_ENABLED)
    - inertia-page-home                    # resources/js/pages/Home.vue placeholder — UI-SPEC tokens in plan 07; t() copy in plan 08
    - inertia-typed-page-props             # resources/js/types/inertia.d.ts declaring auth/flash/ziggy on @inertiajs/core PageProps
    - inertia-smoke-test                   # tests/Feature/InertiaSmokeTest.php — assertInertia component=Home + has(auth/flash/ziggy.routes) + Pitfall 3 mitigation
    - tsconfig-web                         # apps/web/tsconfig.json extends ../../tsconfig.base.json with Vite/Vue/Inertia type contract
    - tsconfig-base-bind-mount             # docker-compose.yml mounts ./tsconfig.base.json:/tsconfig.base.json:ro so the web container's `extends "../../"` resolves
    - dev-runtime-storage-perms            # docker/web/entrypoint.sh chmod 0775 -> 0777 on storage + bootstrap/cache (php-fpm www-data write to host-uid-1000 bind mount)
    - apps-web-env-from-dotenv             # docker-compose.yml stops injecting empty APP_KEY/APP_ENV/APP_DEBUG/APP_URL so Laravel reads apps/web/.env
  affects:
    - "01-07 (Tailwind v4) — uncomments tailwindcss() in vite.config.ts; replaces resources/css/app.css with full @theme block; PublicLayout wraps Home.vue"
    - "01-08 (i18n) — uncomments i18nVue plugin in app.ts; adds locale + translations props to HandleInertiaRequests::share() + types/inertia.d.ts"
    - "01-09 (Discord OAuth) — Home.vue gains the LoginButton CTA wired to /auth/discord/redirect; HandleInertiaRequests starts seeing real auth.user shape"
    - "01-12 (Filament v3) — Filament's own Vite/CSS pipeline lands; the dual-Tailwind workaround references the same vite.config.ts authored here"
    - "01-15 (DTO TypeScript) — emits to resources/js/types/api.d.ts alongside the inertia.d.ts authored here"
    - "01-16 (CI matrix) — adds vue-tsc + pnpm run build to web's CI lane (vue-tsc installed here as a dev-dep)"
    - "01-18 (BLOCKING smoke) — exercises GET / through nginx + browser; Pitfall 3 + ssr.config + vite manifest all gated by this plan"
tech_stack:
  added:
    - "inertiajs/inertia-laravel v2.0.24 (Inertia v2 protocol per D-001 LOCKED — NOT v3 even though `latest` dist-tag points there)"
    - "tightenco/ziggy v2.6.2 (server-side `@routes` blade directive + Ziggy::toArray() in shared props)"
    - "vue 3.5.33 (LOCKED ^3.5)"
    - "@inertiajs/vue3 2.3.21 (latest in v2 line — `legacy` dist-tag — D-001 LOCKED to v2)"
    - "@vitejs/plugin-vue 6.0.6"
    - "ziggy-js 2.6.2 (client-side route() + ZiggyVue plugin)"
    - "@vue/server-renderer 3.5.33 (Rule 3 — required by ssr.ts; not in plan's pasted pnpm add list)"
    - "typescript 5.9.3 (devDep — IDE + vue-tsc support; runtime uses Vite's esbuild transpile)"
    - "vue-tsc 2.2.12 (devDep — type-checking Vue SFCs; CI integration in plan 01-16)"
    - "@types/node 22.19.17 (devDep — node API types for tooling files like vite.config.ts)"
  patterns:
    - "Inertia CSRF Pitfall 3 mitigation — Inertia handles XSRF via the `XSRF-TOKEN` cookie issued by Laravel on first GET. NO `<meta name=\"csrf-token\">` in app.blade.php. Test asserts the absence. The blade comment uses descriptive prose (`do NOT add a CSRF-token meta tag here`) rather than a literal `name=\"csrf-token\"` string so the source-grep verifier passes alongside the rendered-content test."
    - "Inertia config page_paths customisation — defaults to resources/js/Pages (capital P, React-typical). Lowercased to `resources/js/pages` (Vue-style + matches plan's structure + plan author's `import.meta.glob('./pages/**/*.vue')`). Both root and `testing.page_paths` updated."
    - "SSR scaffolded but disabled by default — config/inertia.php `ssr.enabled` default flipped from true to false (env-overridable via INERTIA_SSR_ENABLED). ensure_bundle_exists default flipped from true to false. Production turns SSR on via INERTIA_SSR_ENABLED=true + `php artisan inertia:start-ssr`. Aligns with CONTEXT.md \"SSR config scaffolded but optional in dev\"."
    - "tsconfig.base.json bind-mount — repo-root tsconfig is bind-mounted at `/tsconfig.base.json` inside the web container so `apps/web/tsconfig.json`'s `extends \"../../tsconfig.base.json\"` resolves to a real file in both contexts (host tsc dev workflow + container pnpm run build / vue-tsc CI). Single line addition under `volumes:`."
    - "Removed APP_* env injection from docker-compose web service — compose was defaulting `APP_ENV/APP_DEBUG/APP_URL/APP_KEY` to host shell values (which were empty since no root .env exists). Laravel's Env::get() reads $_SERVER first, so the empty container vars shadowed apps/web/.env's real values, producing MissingAppKeyException at every nginx-served request. Plan 01-05's phpunit.xml dual-tag fix only solved the Pest path; runtime needed compose-level removal so Laravel reads apps/web/.env directly. Production still injects via Railway env groups."
    - "entrypoint.sh chmod 0775 -> 0777 on storage + bootstrap/cache — bind mount is owned by host uid 1000 (rtx) but php-fpm runs as www-data (uid 33). Without 0777 every Laravel request 500s on tempnam() into storage/framework/views (compiled blade cache). Dev-only — production single-user containers keep 0775. .gitignore excludes all generated content under these dirs so the perm bump never leaks via git."
    - "pnpm via the web container — the web Dockerfile (plan 01-02) shipped with `corepack enable && corepack prepare pnpm@9.15.0 --activate` so `docker compose exec web pnpm ...` Just Works without an extra container. node@22.22.2 + pnpm@9.15.0 inside the same image as PHP 8.4 simplifies the local dev story (single `docker compose exec web` for everything)."
    - "@inertiajs/vue3 distribution duality — npm registry has both `latest` (3.0.3 — Inertia v3 protocol) and `legacy` (2.3.21 — v2 protocol). D-001 LOCKED to v2; we install `@inertiajs/vue3@^2.0` so pnpm picks the v2 line regardless of dist-tag movement. The hint shown by pnpm (`3.0.3 is available`) is informational — D-001 is intentional, not stale."
    - "Inertia testing requires assertInertia + AssertableInertia — `Inertia\\Testing\\AssertableInertia` is auto-loaded; no extra setup needed in tests/Pest.php. The chain `->assertInertia(fn (Assert $page) => $page->component('Home')->has('auth')->has('flash')->has('ziggy.routes'))` covers component identity + each shared prop's presence. Deeper prop-shape assertions land in plan 08 (i18n keys) + plan 09 (auth.user shape)."
key_files:
  created:
    - apps/web/app/Http/Middleware/HandleInertiaRequests.php   # auth + flash + ziggy share() — locale/translations land in plan 08
    - apps/web/config/inertia.php                              # published; SSR off in dev; page_paths = resources/js/pages
    - apps/web/resources/views/app.blade.php                   # Inertia root blade (replaces welcome.blade.php)
    - apps/web/resources/js/app.ts                             # createInertiaApp + ZiggyVue + glob page resolver
    - apps/web/resources/js/bootstrap.ts                       # axios global (TS port of bootstrap.js)
    - apps/web/resources/js/ssr.ts                             # createServer + renderToString scaffold
    - apps/web/resources/js/pages/Home.vue                     # placeholder — UI-SPEC + t() in later plans
    - apps/web/resources/js/types/inertia.d.ts                 # typed PageProps (auth + flash + ziggy)
    - apps/web/tests/Feature/InertiaSmokeTest.php              # 2 it-blocks: Inertia render + Pitfall 3
    - apps/web/tsconfig.json                                   # extends ../../tsconfig.base.json + Vite/Vue/Inertia type contract
    - apps/web/vite.config.ts                                  # Vue plugin + laravel-vite-plugin (Tailwind commented for plan 07)
    - apps/web/pnpm-lock.yaml                                  # pnpm lockfile for vue + inertia + ziggy + vite client deps
  modified:
    - apps/web/.gitignore                                       # +/storage/debugbar +/.pnpm-store
    - apps/web/app/Models/User.php                              # Pint auto-fix (class_attributes_separation) on re-run
    - apps/web/bootstrap/app.php                                # +HandleInertiaRequests::class to web group append
    - apps/web/composer.json                                    # +inertiajs/inertia-laravel ^2.0 +tightenco/ziggy ^2.6
    - apps/web/composer.lock                                    # 2 packages locked + autoload regen
    - apps/web/package.json                                     # +vue +@inertiajs/vue3 +@vitejs/plugin-vue +ziggy-js (deps); +typescript +vue-tsc +@types/node +@vue/server-renderer (devDeps)
    - apps/web/resources/css/app.css                            # placeholder body{} CSS (Tailwind @import block returns in plan 07)
    - apps/web/routes/web.php                                   # GET / -> Inertia::render('Home') named `home`
    - docker-compose.yml                                        # +tsconfig.base.json bind-mount; -APP_* env injection (let Laravel read .env)
    - docker/web/entrypoint.sh                                  # chmod 0775 -> 0777 on storage + bootstrap/cache (www-data write to host-uid-1000 bind)
  deleted:
    - apps/web/resources/views/welcome.blade.php                # replaced by app.blade.php (Inertia root)
    - apps/web/resources/js/app.js                              # replaced by app.ts
    - apps/web/resources/js/bootstrap.js                        # replaced by bootstrap.ts (rename in git)
    - apps/web/vite.config.js                                   # replaced by vite.config.ts
decisions:
  - "Did NOT add a root .env at repo root to satisfy docker-compose's ${APP_KEY:-} substitution. Removing the APP_* env keys from docker-compose entirely is cleaner — Laravel reads apps/web/.env directly via its standard dotenv loader, which is the canonical pattern. Plan 01-05's phpunit.xml dual-tag pattern still works for the test path (it sets APP_ENV=testing + APP_KEY=base64:cVdSpHd... explicitly via <server> tags); production sets via Railway env groups. Local dev has a single source of truth (apps/web/.env)."
  - "Did NOT change the entrypoint.sh chmod from 0777 back to 0775 + chown www-data. chowning to www-data would break host-side editing (rtx@host can't write app/Models/* etc.); 0777 is dev-only and the bind-mounted dirs are entirely gitignored content (storage/framework/* + bootstrap/cache/services.php). Production runs in single-user containers where 0775 is sufficient — entrypoint detection of dev vs prod is a plan 01-17 concern (Railway nixpacks)."
  - "Did NOT install `@tailwindcss/vite` import in vite.config.ts even though Laravel 12 default ships with `@tailwindcss/vite` already in package.json + the plugin in vite.config.js. Plan author's intent was for plan 07 to enable Tailwind explicitly via the dual-Tailwind workaround (Pitfall 1 — Tailwind v4 vs Filament v3). Following the plan's explicit comment-out preserves the gated rollout: between this plan and plan 07, no Tailwind utilities are emitted, but the package is still installed (so plan 07 only authors config + tokens, not new installs)."
  - "Did NOT publish inertia-config via `--tag=inertia-config` — that tag doesn't exist in inertiajs/inertia-laravel v2.0.24 (the package publishes the entire config without a tag filter). Used `--provider=\"Inertia\\\\ServiceProvider\"` (no tag) which is what the package actually supports. The plan's `--tag=inertia-config` invocation reported `INFO No publishable resources for tag [inertia-config]` and was a no-op before the un-tagged retry."
  - "Set Inertia testing.page_paths AND root page_paths both to `resources/js/pages` (lowercase). The plan's `import.meta.glob('./pages/**/*.vue')` and Home.vue path use lowercase `pages/`. Plan author's intent is unambiguous; only the published default config disagreed (capital P)."
  - "Used `@vue/server-renderer` (Rule 3 — added critical missing dep) for ssr.ts. The plan's pasted ssr.ts imports `renderToString` from `@vue/server-renderer` but the plan's pnpm-add list did not include it. Without it, vue-tsc + the SSR build would fail to resolve the import. Installed at devDep level since SSR ships in production via a separate build (`pnpm run build:ssr`-equivalent — wired in a later phase)."
  - "Wrote the CSRF-token avoidance reminder in app.blade.php as descriptive prose (`do NOT add a CSRF-token meta tag here`) rather than a literal `<meta name=\"csrf-token\">` in a comment. The plan's source-level grep verify regex was `! grep -q 'meta name=\"csrf-token\"'` — a literal in a Blade comment would have failed the verify even though Blade strips comments before render (and the runtime test in InertiaSmokeTest already asserts the rendered content). Avoided the conflict by rewording."
  - "Restarted the web container after docker-compose.yml + entrypoint.sh edits. The bind-mount addition (tsconfig.base.json) requires a container recreate (`docker compose up -d web`); the entrypoint chmod runs only at container start. Both changes are persistent — once committed, every future `make up` works for any developer cloning the repo."
metrics:
  tasks_completed: 2
  files_created: 12       # middleware + inertia.php + app.blade.php + app.ts + bootstrap.ts + ssr.ts + Home.vue + inertia.d.ts + InertiaSmokeTest + tsconfig.json + vite.config.ts + pnpm-lock.yaml
  files_modified: 9       # .gitignore + User.php (Pint) + bootstrap/app.php + composer.json/lock + package.json + app.css + web.php + docker-compose.yml + entrypoint.sh
  files_deleted: 4        # welcome.blade.php + app.js + bootstrap.js + vite.config.js
  duration_minutes: 12    # composer require + pnpm add + entrypoint perm fix + curl 500 diagnosis + tsconfig bind-mount + page_paths fix
  completed: 2026-05-03
---

# Phase 01 Plan 06: Inertia v2 + Vue 3 + Vite + Ziggy — Summary

**One-liner:** Wires the Inertia v2 + Vue 3 + Vite + Ziggy frontend pipeline end-to-end — server-side adapter (`inertiajs/inertia-laravel:^2.0`), client packages (`@inertiajs/vue3@^2`, `vue@^3.5`, `@vitejs/plugin-vue@^6`, `ziggy-js@^2.6`), Vite TypeScript config, Inertia root blade with Pitfall 3 CSRF mitigation, the Home placeholder page, SSR scaffold (disabled in dev), and an InertiaSmokeTest that asserts both the Inertia component identity and the absence of the CSRF meta tag.

## What was built

This plan turns `apps/web/` from a Laravel skeleton serving the default welcome blade into a fully-wired Inertia + Vue 3 SPA-via-server-rendered-page application. Every public route from Phase 2 onward will land on top of this pipeline; the Filament admin panel (plan 12) shares the same Vite output dir but uses its own Tailwind v3 sub-config (Pitfall 1, deferred).

The plan is the first one to genuinely exercise the runtime serve path — plans 01-04 and 01-05 only verified Laravel via Pest's in-process kernel, never via nginx → php-fpm. That exposed two pre-existing latent bugs (storage perms, APP_KEY shadowing) that this plan fixed permanently.

### Task 1 — Server-side: composer + middleware + bootstrap registration + root blade + routes (commit `68fd97f`)

- `docker compose exec web composer require inertiajs/inertia-laravel:^2.0 tightenco/ziggy:^2.6` installed v2.0.24 + v2.6.2 with full transitive resolution. composer.json adds the two new entries; composer.lock writes the resolved tree (laravel/framework stays at v12.58.0 as locked by Plan 01-04).
- `php artisan inertia:middleware HandleInertiaRequests` scaffolded the middleware stub; rewrote it to share `auth` (resolves `$request->user()?->only(['id', 'discord_id', 'username', 'avatar_url'])` — closure form so unauthenticated requests get null without 401), `flash` (`success` and `error` from session — used by plan 09's post-OAuth toast), and `ziggy` (`(new Ziggy)->toArray() + ['location' => $request->url()]` — what ZiggyVue consumes). Locale and translations props are intentionally absent — plan 08 adds them.
- `bootstrap/app.php` updated: `->withMiddleware(fn ($m) => $m->web(append: [HandleInertiaRequests::class]))`. Append rather than prepend so the Inertia middleware sees the request after VerifyCsrfToken (correct ordering — Inertia's CSRF handling is downstream of Laravel's verifier).
- `resources/views/app.blade.php` authored as the Inertia root: `@routes`, `@vite(['resources/css/app.css', 'resources/js/app.ts'])`, `@inertiaHead`, `@inertia`. Pitfall 3: NO `<meta name="csrf-token">` — Inertia handles XSRF via the `XSRF-TOKEN` cookie. The blade comment uses descriptive prose (`do NOT add a CSRF-token meta tag here`) rather than the literal string so source-grep verify and rendered-content tests both pass.
- `routes/web.php` simplified to `Route::get('/', fn () => Inertia::render('Home'))->name('home')`. The route name `home` flows into Ziggy's routes map and is what Vue consumes via `route('home')`.
- `php artisan vendor:publish --provider="Inertia\\ServiceProvider"` published `config/inertia.php`. Then customised: SSR `enabled` default true → false (CONTEXT.md "scaffolded but optional in dev"); `ensure_bundle_exists` default true → false (no SSR bundle in dev); `page_paths` and `testing.page_paths` defaults `resources/js/Pages` → `resources/js/pages` (lowercase per plan's structure).
- Deleted `resources/views/welcome.blade.php` (replaced by app.blade.php).
- Verified `php artisan route:list --path=/` shows the home route bound. PHPStan + Pest stay green at this point (the new middleware + bootstrap are clean L8).

### Task 2 — Client-side: pnpm install + vite.config.ts + tsconfig.json + app.ts + ssr.ts + Home.vue + types/inertia.d.ts + InertiaSmokeTest + critical fixes (commit `3354372`)

- `docker compose exec web pnpm add vue@^3.5 @inertiajs/vue3@^2.0 @vitejs/plugin-vue@^6.0 ziggy-js@^2.6` installed 119 packages (vue 3.5.33 + @inertiajs/vue3 2.3.21 + @vitejs/plugin-vue 6.0.6 + ziggy-js 2.6.2 + transitives).
- `pnpm add -D typescript@^5.6 vue-tsc@^2.0 @types/node@^22.0` added 25 more (typescript 5.9.3, vue-tsc 2.2.12, @types/node 22.19.17).
- `pnpm add -D @vue/server-renderer@^3.5` (Rule 3 — see Deviation 1) — required by ssr.ts but absent from plan's pnpm-add list.
- `apps/web/vite.config.ts` authored with `laravel-vite-plugin` (input: app.css + app.ts, ssr: ssr.ts, refresh: true) + `@vitejs/plugin-vue` (transformAssetUrls config). `@tailwindcss/vite` import COMMENTED OUT — plan 07 will uncomment as part of the dual-Tailwind workaround.
- `apps/web/tsconfig.json` extends `../../tsconfig.base.json` (the workspace base config) + Vite/Vue specifics (Bundler module resolution, ESNext module, JSX preserve, isolatedModules, noEmit, types: ['vite/client', 'node'], baseUrl: '.', paths: `@/*` -> resources/js + ziggy-js -> vendor/tightenco/ziggy).
- `apps/web/resources/js/bootstrap.ts` ports the Laravel default axios bootstrap to TS with `declare global { interface Window { axios: ... } }`.
- `apps/web/resources/js/app.ts` is the Inertia client entry: `createInertiaApp({ title, resolve, setup, progress })` with `import.meta.glob<DefineComponent>('./pages/**/*.vue')` page resolver, `app.use(plugin).use(ZiggyVue)`. The `i18nVue` plugin import is COMMENTED OUT — plan 08 enables it once the translations prop ships.
- `apps/web/resources/js/ssr.ts` is the SSR scaffold per RESEARCH Pattern 2: `createServer((page) => createInertiaApp({ page, render: renderToString, ... }))` with eager glob (`{ eager: true }` — required for SSR's synchronous render path). Disabled at runtime via `inertia.config.php` ssr.enabled=false.
- `apps/web/resources/js/pages/Home.vue` is a minimal placeholder (`<h1>Trenchwars</h1>` + `<p>The league for clan-organised matches.</p>`) plus `<Head title="Trenchwars" />` from `@inertiajs/vue3`. Three TODO comments mark the upcoming layered work in plans 07, 08, 09.
- `apps/web/resources/js/types/inertia.d.ts` declares `module '@inertiajs/core' { interface PageProps { auth, flash, ziggy } }` — typed shared props matching HandleInertiaRequests::share()'s output. Plan 08 adds `locale` + `translations`.
- `apps/web/tests/Feature/InertiaSmokeTest.php` ships two `it()` blocks: `it('renders the Home page via Inertia')` chains `assertStatus(200)` + `assertInertia(fn (Assert $page) => $page->component('Home')->has('auth')->has('flash')->has('ziggy.routes'))`; `it('does not include a CSRF meta tag in the root view')` asserts `$response->getContent()` does not contain `name="csrf-token"` (Pitfall 3 mitigation, runtime verification).
- `pnpm run build` produces `public/build/manifest.json` + `public/build/assets/*` (763 modules transformed; app-CNinMZ84.js 259.52 kB / 92.16 kB gzip; Home-ChxcWA2S.js 0.35 kB; app-071_jiHx.css 0.06 kB).
- All four Pest tests pass (BootHealthcheckTest 2 + InertiaSmokeTest 2 = 4 passed, 17 assertions, 0.19s); Pint clean (27 files); PHPStan level 8 [OK] No errors.
- Final runtime verification: `curl -s -o /tmp/home.html -w "HTTP %{http_code}\\n" http://localhost:8000/` → `HTTP 200`. The HTML contains `<title inertia>Trenchwars</title>` + `<div id="app" data-page="...">` with `component:"Home"`, `auth:null`, `flash:{success:null,error:null}`, full ziggy.routes including `home` → `{uri:"/",methods:["GET","HEAD"]}`, and NO `name="csrf-token"`.

## Verification results

### Plan-level must_haves

**Truth statements:**

- ✅ **"`GET /` returns an Inertia response rendering `pages/Home.vue` (not the Laravel welcome blade)."** — Verified: `curl http://localhost:8000/` → HTTP 200; `<div id="app" data-page="..."` contains `"component":"Home"`. welcome.blade.php deleted in commit `68fd97f`.
- ✅ **"Inertia v2 server adapter (`inertiajs/inertia-laravel:^2.0`) registered; `HandleInertiaRequests` middleware in the `web` group."** — Verified: `composer show inertiajs/inertia-laravel` → v2.0.24; `bootstrap/app.php` contains `$middleware->web(append: [HandleInertiaRequests::class])`; `php artisan route:list` shows the home route bound; InertiaSmokeTest's `assertInertia` calls all pass (Inertia is actively rendering).
- ⚠️ **"Vite dev server runs `pnpm dev` from the web container and HMR-updates `pages/Home.vue` edits."** — Partially verified: `pnpm run build` succeeds and produces a valid manifest; `pnpm dev` (`vite`) is not exercised in this plan because HMR requires the dev server to be reachable from the host browser (port 5173 forwarding) — that wire-up is implicit when a developer runs `make up` + `make pnpm ARGS=run dev`. End-to-end HMR verification is a plan 01-18 (BLOCKING smoke test) gate, not a per-plan acceptance.
- ✅ **"Ziggy `route()` helper available via `ZiggyVue` plugin in the Vue app."** — Verified: `app.ts` calls `.use(ZiggyVue)`; `app.blade.php` emits `@routes` (Ziggy server directive that injects the routes map into a `<script>` tag); the rendered HTML's `data-page` attr contains the full `ziggy.routes` object including the `home` named route.
- ✅ **"`<meta name=\"csrf-token\">` is NOT present in `app.blade.php` (Pitfall 3 — Inertia handles XSRF via cookie)."** — Verified: source-level `! grep -q 'meta name="csrf-token"' apps/web/resources/views/app.blade.php` → satisfied; runtime InertiaSmokeTest assertion `expect($response->getContent())->not->toContain('name="csrf-token"')` → passes.

**Artifacts:**

- ✅ `apps/web/vite.config.ts` provides `Vite config with Vue plugin + Laravel plugin (Tailwind v4 plugin lands in plan 07)` and contains `@vitejs/plugin-vue`.
- ✅ `apps/web/resources/js/app.ts` provides `Inertia v2 + Vue 3 + ZiggyVue bootstrap (i18nVue lands in plan 08)` and contains `createInertiaApp`.
- ✅ `apps/web/resources/js/pages/Home.vue` provides `Initial Inertia page (UI-SPEC tokens land in plan 07; final copy in plan 08)` and contains `<template>`.
- ✅ `apps/web/app/Http/Middleware/HandleInertiaRequests.php` provides `Inertia middleware sharing auth + ziggy props (translations prop lands in plan 08)` and contains `extends Middleware`.
- ✅ `apps/web/resources/views/app.blade.php` provides `Inertia root blade with @inertiaHead + @inertia + @vite` and contains `@inertia`.

**Key links:**

- ✅ `apps/web/routes/web.php` → `Inertia::render('Home')` via render call: `Route::get('/', fn () => Inertia::render('Home'))->name('home');` matches the `Inertia::render` pattern.
- ✅ `apps/web/resources/js/app.ts` → `ZiggyVue` via `app.use`: `.use(ZiggyVue)` matches the `ZiggyVue` pattern.
- ✅ `apps/web/bootstrap/app.php` → `HandleInertiaRequests` via `middleware->web append`: `$middleware->web(append: [HandleInertiaRequests::class])` matches the `HandleInertiaRequests::class` pattern.

### Task acceptance criteria

| Criterion | Result | Evidence |
| --------- | ------ | -------- |
| inertiajs/inertia-laravel + tightenco/ziggy in composer.json | PASS | `grep '"inertiajs/inertia-laravel"\|"tightenco/ziggy"' apps/web/composer.json` matches both |
| HandleInertiaRequests authored and registered in `web` group | PASS | `grep 'class HandleInertiaRequests' apps/web/app/Http/Middleware/HandleInertiaRequests.php` matches; `grep 'HandleInertiaRequests::class' apps/web/bootstrap/app.php` matches |
| `apps/web/resources/views/welcome.blade.php` removed; `app.blade.php` exists | PASS | `! test -f apps/web/resources/views/welcome.blade.php` + `test -f apps/web/resources/views/app.blade.php` |
| `app.blade.php` does NOT contain `<meta name="csrf-token">` (Pitfall 3) | PASS | `! grep -q 'meta name="csrf-token"' apps/web/resources/views/app.blade.php` |
| `app.blade.php` contains `@inertia`, `@inertiaHead`, `@vite`, `@routes` | PASS | grep matches all four directives |
| `routes/web.php` `/` route uses `Inertia::render('Home')` | PASS | `grep "Inertia::render('Home')" apps/web/routes/web.php` matches |
| `apps/web/config/inertia.php` published | PASS | `test -f apps/web/config/inertia.php` |
| package.json lists vue@^3.5, @inertiajs/vue3@^2, @vitejs/plugin-vue@^6, ziggy-js@^2.6 | PASS | grep finds each entry in apps/web/package.json |
| vite.config.ts replaces vite.config.js | PASS | `! test -f apps/web/vite.config.js` + `test -f apps/web/vite.config.ts` |
| tsconfig.json extends `../../tsconfig.base.json` | PASS | `grep 'tsconfig.base.json' apps/web/tsconfig.json` matches the extends line |
| resources/js/app.ts wires createInertiaApp + ZiggyVue (i18nVue commented) | PASS | grep matches both; `i18nVue` import line is `// import { i18nVue } ...` |
| resources/js/ssr.ts scaffolded (SSR disabled in dev per inertia.config) | PASS | `grep 'createServer' apps/web/resources/js/ssr.ts` matches; `config/inertia.php` `'ssr' => ['enabled' => (bool) env('INERTIA_SSR_ENABLED', false), ...]` |
| pages/Home.vue exists and renders the placeholder | PASS | `grep 'Trenchwars' apps/web/resources/js/pages/Home.vue` matches; runtime curl shows `<h1>Trenchwars</h1>` in the rendered Inertia page |
| types/inertia.d.ts declares typed PageProps | PASS | `test -f apps/web/resources/js/types/inertia.d.ts`; file contains `interface PageProps { auth, flash, ziggy }` |
| InertiaSmokeTest passes (Home component is rendered, csrf meta absent, ziggy routes shared) | PASS | `pest tests/Feature/InertiaSmokeTest.php` → 2 passed |
| BootHealthcheckTest still passes | PASS | `pest tests/Feature/Health/BootHealthcheckTest.php` → 2 passed |
| `pnpm run build` produces `public/build/manifest.json` | PASS | `test -f apps/web/public/build/manifest.json`; manifest content includes `resources/js/app.ts` + `resources/css/app.css` entries |

### Requirements progress

PLAN frontmatter `requirements:`:

- **REQ-constraint-railway-deploy** — Foundation extended: the Vite + Inertia pipeline is now part of the Railway-deployed `web` service surface. Railway nixpacks (plan 17) will run `pnpm install + pnpm run build` against the apps/web image. Still listed as Pending in REQUIREMENTS.md — closes in plan 17 (Railway service configs) + plan 18 (BLOCKING smoke).
- **REQ-constraint-en-launch-i18n-ready** — Foundation reinforced: the Inertia shared-props pipeline (HandleInertiaRequests::share()) is the canonical hook for plan 08 to add the `translations` + `locale` props. The `useT()` composable plan 08 ships will read from `usePage().props.translations`. Still Pending — closes in plan 08 (i18n plumbing).

Both requirements remain tracked. No requirement is marked complete by this plan alone.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Plan's pasted ssr.ts imports `@vue/server-renderer` but the plan's `pnpm add -D` list omits it**

- **Found during:** Task 2 — after authoring ssr.ts, vue-tsc and any subsequent SSR build would fail to resolve the `import { renderToString } from '@vue/server-renderer'` line.
- **Issue:** `@vue/server-renderer` is the canonical package providing Vue 3's SSR `renderToString` function. It's not bundled with `vue` — it's a separate package that must be installed at the same major.minor as the runtime `vue`. The plan's pnpm-add script instructed `vue@^3.5 @inertiajs/vue3@^2.0 @vitejs/plugin-vue@^6.0 ziggy-js@^2.6` and `-D typescript@^5.6 vue-tsc@^2.0 @types/node@^22.0` — neither block included `@vue/server-renderer`.
- **Fix:** `docker compose exec web pnpm add -D @vue/server-renderer@^3.5`. Installed 3.5.33 (matches the runtime `vue` version pnpm resolved). devDep classification because SSR builds happen at production deploy time, not at runtime.
- **Files modified:** `apps/web/package.json` (devDependencies +@vue/server-renderer), `apps/web/pnpm-lock.yaml`.
- **Commit:** `3354372` (Task 2 client install commit).
- **Verification:** `pnpm run build` succeeds (763 modules, no resolution errors). ssr.ts compiles cleanly.

**2. [Rule 3 - Blocking] `apps/web/tsconfig.json` extends `../../tsconfig.base.json` but the file is unreachable from inside the web container**

- **Found during:** Task 2 — first `pnpm run build` invocation reported `[vite:esbuild] failed to resolve "extends":"../../tsconfig.base.json" in /app/tsconfig.json`.
- **Issue:** The web container's bind mount is `./apps/web:/app` (only). The relative path `../../tsconfig.base.json` from `/app/tsconfig.json` resolves to `/tsconfig.base.json` (one level above /app), which doesn't exist inside the container — it lives at `/home/rtx/projects/trench-wars/tsconfig.base.json` on the host. Plan 01-02 set up the workspace pattern (bot/rcon-worker tsconfigs use the same extends — they bake the base config into their image at build time via `COPY pnpm-workspace.yaml package.json tsconfig.base.json ./`), but the web Dockerfile didn't include that COPY because plan 01-02 didn't anticipate web needing a tsconfig.
- **Fix:** Added a single bind-mount line to `docker-compose.yml` web service `volumes:`: `- ./tsconfig.base.json:/tsconfig.base.json:ro`. Read-only mount of the host's tsconfig.base.json at the path the relative `extends` resolves to. Single-file binds are first-class in docker-compose; no Dockerfile rebuild required. After `docker compose up -d web` (recreates the container with the new mount), the extends resolves cleanly. Also added `baseUrl: "."` to apps/web/tsconfig.json's compilerOptions so the `paths` aliases (which require baseUrl per TS spec) are valid.
- **Files modified:** `docker-compose.yml` (+1 volume entry + comment block), `apps/web/tsconfig.json` (+baseUrl + lib).
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** `docker compose exec web ls /tsconfig.base.json` succeeds with read-only perms; `pnpm run build` resolves the extends without warning. The bot/rcon-worker tsconfigs continue to work via their existing Dockerfile COPY pattern (no cross-impact).

**3. [Rule 3 - Blocking] docker-compose.yml injecting empty APP_KEY/APP_ENV/APP_DEBUG/APP_URL into the web container shadowed apps/web/.env at runtime**

- **Found during:** Task 2 — first `curl http://localhost:8000/` returned HTTP 500 with `tempnam(): file created in the system's temporary directory` (an exception in Laravel's exception renderer trying to compile a blade view — symptomatic of an upstream issue).
- **Issue:** Compounded by Deviation 4 (storage perms) but the root cause was pre-existing from plan 01-02: `docker-compose.yml` declared `APP_ENV: ${APP_ENV:-local}, APP_DEBUG: ${APP_DEBUG:-true}, APP_URL: ${APP_URL:-http://localhost:8000}, APP_KEY: ${APP_KEY:-}`. Since no root `.env` exists and the host shell doesn't export these variables, all four substitute to their defaults — including `APP_KEY=""` (empty). The empty string flows into the container's process env as `$_SERVER['APP_KEY']=''`, and Laravel's `Illuminate\\Support\\Env::get()` reads `$_SERVER` first (before `$_ENV` or the dotenv-loaded `apps/web/.env` content) — so the empty container value shadows the real `APP_KEY=base64:jxid8sQLkLqf7mXQWiq73miEE0wcA5ZW8VIjWJdUgdA=` that plan 01-04 wrote to apps/web/.env. Plan 01-05 already debugged this exact mechanism for the test path (Deviation 3 in plan 01-05's SUMMARY) and fixed it via phpunit.xml dual `<env force=true>` + `<server force=true>` tags — but those tags only apply to PHPUnit/Pest invocations. Runtime nginx → php-fpm requests go through Laravel's normal bootstrap and hit the same shadowing bug at every request.
- **Fix:** Removed the four `APP_*` env entries from `docker-compose.yml`'s `environment:` block. With those gone, the container's process env doesn't have APP_* keys at all (other than APP_LOCALE which isn't shadowed because compose doesn't set it), so `$_SERVER['APP_KEY']` is unset and Laravel's Env::get() falls through to `$_ENV` / dotenv-loaded values from `apps/web/.env`. Production overrides via Railway env groups (set explicitly per service) — that path is unaffected.
- **Files modified:** `docker-compose.yml` (-4 env entries + comment block explaining the rationale).
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** After `docker compose up -d web` recreates with the new env config, `docker compose exec web printenv APP_KEY` returns empty (env var unset, not empty); `docker compose exec web php artisan tinker --execute="echo config('app.key');"` returns the base64-encoded key from apps/web/.env. `curl http://localhost:8000/` → HTTP 200 with valid Inertia HTML (no MissingAppKeyException).

**4. [Rule 3 - Blocking] storage + bootstrap/cache perms 0775 prevent php-fpm (www-data, uid 33) from writing to host-uid-1000 bind mount**

- **Found during:** Task 2 — the curl 500 from Deviation 3 had a secondary cause: even after fixing APP_KEY, the request would have failed at `tempnam()` into `/app/storage/framework/views/` because Laravel needs to write compiled blade views, sessions, debugbar history, etc. on every request.
- **Issue:** docker/web/entrypoint.sh from plan 01-02 ran `chmod -R 0775 /app/storage /app/bootstrap/cache` — group-writable permissions. But php-fpm in the web image runs as `www-data` (uid 33, group 33) per the default `/usr/local/etc/php-fpm.d/www.conf`. The bind mount `./apps/web:/app` exposes files owned by host uid 1000 (rtx) — no overlap with www-data's groups. Result: php-fpm gets `Permission denied` on every write. Pest tests didn't catch this because Pest runs as root (uid 0) inside the container, which bypasses POSIX perms entirely.
- **Fix:** Bumped `chmod -R 0775` → `chmod -R 0777` on `/app/storage /app/bootstrap/cache` in `docker/web/entrypoint.sh`. World-writable is acceptable for these specific dev-only directories — the entire content is gitignored (storage/framework/cache, sessions, views, testing, logs; bootstrap/cache/services.php; storage/debugbar). Production runs in single-user containers (the entrypoint detects `APP_ENV=production` in a future plan to keep 0775) — this widening is a local-dev affordance for the host-bind / fpm-www-data uid mismatch.
- **Files modified:** `docker/web/entrypoint.sh` (chmod 0775 → 0777 + comment block).
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** `docker compose exec -u www-data web touch /app/storage/framework/views/test.txt` succeeds (was failing before the chmod). `curl http://localhost:8000/` → HTTP 200. `pest` continues to pass (Pest still runs as root, unaffected by the perm change).

**5. [Rule 1 - Bug] config/inertia.php's published default `page_paths` points at `resources/js/Pages` (capital P) but plan structure uses `resources/js/pages` (lowercase)**

- **Found during:** Task 2 — initial run of InertiaSmokeTest (`it('renders the Home page via Inertia')`) failed with `Inertia page component file [Home] does not exist.` from `AssertableInertia::component()`'s view-finder.
- **Issue:** Inertia's published config defaults `page_paths` and `testing.page_paths` to `resource_path('js/Pages')`. Plan author's structure uses lowercase `pages/` (`apps/web/resources/js/pages/Home.vue`, `import.meta.glob('./pages/**/*.vue')`). The mismatch only affects Pest's `assertInertia` view-finder (which validates the page file exists on disk) — runtime Inertia rendering works fine because it uses the JS-side resolver.
- **Fix:** Edited `apps/web/config/inertia.php`: replaced both occurrences of `resource_path('js/Pages')` with `resource_path('js/pages')` (lowercase). Both root `page_paths` and `testing.page_paths` updated for consistency (and to future-proof — the plan author's note in the published file says the testing block will be removed in a future Inertia version, with the root block taking over for both contexts).
- **Files modified:** `apps/web/config/inertia.php`.
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** InertiaSmokeTest's `it('renders the Home page via Inertia')` now passes; `assertInertia(... ->component('Home') ...)` finds `apps/web/resources/js/pages/Home.vue` correctly.

**6. [Rule 2 - Missing Critical] config/inertia.php's published default `ssr.enabled` is true with `ensure_bundle_exists: true` — would 500 in dev when no SSR bundle exists**

- **Found during:** Task 2 — anticipated based on CONTEXT.md "SSR config scaffolded but optional in dev" + the plan's explicit must_have "SSR scaffolded but NOT enabled in dev".
- **Issue:** Inertia v2's published config sets `ssr.enabled = (bool) env('INERTIA_SSR_ENABLED', true)` and `ensure_bundle_exists = (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true)`. Both default to true when the env var is unset. Without an SSR bundle (Vite SSR build hasn't been run; `bootstrap/ssr/ssr.mjs` doesn't exist), Inertia's `ensure_bundle_exists` check would throw at the first request.
- **Fix:** Edited `apps/web/config/inertia.php`: changed `'enabled' => (bool) env('INERTIA_SSR_ENABLED', true)` → `false`, and `'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true)` → `false`. Production opts in via `INERTIA_SSR_ENABLED=true` in Railway env groups (and the bundle exists because `pnpm run build:ssr` runs as part of the deploy script).
- **Files modified:** `apps/web/config/inertia.php`.
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** `curl http://localhost:8000/` → HTTP 200 (no SSR-bundle-missing exception). The ssr.ts file is still committed and parsed by vite.config.ts's `ssr: 'resources/js/ssr.ts'` directive — production builds will produce the SSR bundle when needed.

**7. [Rule 1 - Bug] Plan's blade comment `{{-- IMPORTANT: NO <meta name="csrf-token"> ... --}}` matches the source-grep verify regex (false positive)**

- **Found during:** Task 1 — running the plan's automated verify chain `! grep -q 'meta name="csrf-token"' apps/web/resources/views/app.blade.php` after authoring app.blade.php.
- **Issue:** The plan's prescribed comment text contains the literal string `<meta name="csrf-token">` (inside `{{-- ... --}}`). Blade strips comments before render, so the rendered output (what the InertiaSmokeTest checks) is correctly free of csrf meta tags. But the source-level verify grep is a false positive — it finds the literal in the comment.
- **Fix:** Reworded the comment from `IMPORTANT: NO <meta name="csrf-token"> — Inertia handles XSRF via cookie (Pitfall 3 from RESEARCH).` to `IMPORTANT: do NOT add a CSRF-token meta tag here — Inertia handles XSRF via cookie (Pitfall 3 from RESEARCH).` Same intent, no literal match. Both source-grep and runtime test now pass.
- **Files modified:** `apps/web/resources/views/app.blade.php`.
- **Commit:** `68fd97f` (Task 1 commit).
- **Verification:** `! grep -q 'meta name="csrf-token"' apps/web/resources/views/app.blade.php` → satisfied (silent, exit 1 from grep, exit 0 from `!`). InertiaSmokeTest's `not->toContain('name="csrf-token"')` continues to pass.

**8. [Rule 1 - Bug] Pint reports 3 style issues after authoring new PHP files**

- **Found during:** Task 2 — running `./vendor/bin/pint --test` after committing Task 1 + authoring InertiaSmokeTest.
- **Issue:** Pint flagged 3 issues: `User.php` (`class_attributes_separation` — pre-existing from a previous plan; surfaced when the User model was loaded by composer's class discovery during the require), `bootstrap/app.php` (`concat_space` on the `__DIR__ . '/../routes/web.php'` line I edited — Pint expects no space around concat in this context), and `tests/Feature/InertiaSmokeTest.php` (`method_argument_space, method_chaining_indentation` — multi-line `assertInertia(fn ... ->...)` indentation didn't match Laravel preset).
- **Fix:** Ran `pint app/Models/User.php bootstrap/app.php tests/Feature/InertiaSmokeTest.php` (auto-fix mode). All 3 issues fixed automatically — class attributes separation in User.php, concat space in bootstrap/app.php, and method chaining indentation in InertiaSmokeTest. Committed as part of the Task 2 commit (must_have-style: `pint --test` should be green at every commit boundary).
- **Files modified:** `apps/web/app/Models/User.php`, `apps/web/bootstrap/app.php`, `apps/web/tests/Feature/InertiaSmokeTest.php`.
- **Commit:** `3354372` (Task 2 commit).
- **Verification:** `pint --test` → `PASS 27 files`.

### Process notes (not behavior deviations)

- **Initial composer require ran with the wrong `-w`** flag pointing at `/var/www/html` (a stale path from a different docker convention). The container's actual working_dir is `/app` (per docker-compose.yml). The phantom install populated `/var/www/html` (a path inside the image but outside the bind mount) and was effectively a no-op as far as the host filesystem and the project's composer.json were concerned. Re-ran without the `-w` flag, which defaults to `/app` (correct).
- **`php artisan vendor:publish --tag=inertia-config` is a no-op** for inertiajs/inertia-laravel v2.0.24. Used `--provider="Inertia\\ServiceProvider"` (no tag) which is the actual published-resources path. Plan's pasted command was speculative.
- **gsd-sdk version mismatch carries over** from plan 01-05: the agent's instructions reference `gsd-sdk query state.* / commit-to-subrepo / requirements.mark-complete` subcommands, but the gsd-sdk binary on PATH (v0.x at `/home/rtx/.nvm/versions/node/v22.22.2/bin/gsd-sdk`) only exposes `run | auto | init`. STATE.md / ROADMAP.md / REQUIREMENTS.md updates done by direct file edits in the state-update step.

No Rule 4 (architectural) decisions surfaced. The docker-compose and entrypoint changes (Deviations 2, 3, 4) are infrastructure adjustments, not architectural redesigns — they don't introduce new abstractions or change layering.

## Authentication gates

None encountered. The plan is autonomous (`autonomous: true` in frontmatter).

## Threat surface scan

Plan threat register declares two boundaries (browser → web Inertia POSTs; Vite dev server → browser HMR) and two threats:

| Threat ID | Disposition | Mitigation Verified |
| --------- | ----------- | ------------------- |
| T-1-06 (Tampering / CSRF: Inertia POSTs from Vue) | mitigate | ✅ — Inertia auto-handles XSRF via the `XSRF-TOKEN` cookie issued by Laravel on first GET. `apps/web/resources/views/app.blade.php` contains NO `<meta name="csrf-token">` — verified at the source level (grep `! ... ` passes) AND at the runtime level (InertiaSmokeTest assertion `not->toContain('name="csrf-token"')`). |
| T-1-19 (XSS: Vue template rendering of user-controlled data) | mitigate | ✅ — Vue auto-escapes `{{ }}` interpolations by default. Home.vue has no `v-html` directive (search `grep -r 'v-html' apps/web/resources/js/` → empty). Plan's threat register notes that `v-html` is forbidden in P1; future phases gate it behind code review. No user-authored content lands until Phase 2 (clan descriptions) anyway. |

**Threat flags:** None. The plan's surface is entirely on the trusted server tier (Inertia rendering, route registration, middleware) — no new endpoints, no new auth paths, no new file access patterns. The XSRF cookie boundary is unchanged from Laravel's default; we just stopped redundantly emitting the meta tag.

## Commits

- `68fd97f` — `feat(01-06): wire Inertia v2 server adapter + Ziggy + HandleInertiaRequests middleware`
- `3354372` — `feat(01-06): wire Vue 3 + Inertia v2 client + Vite + Ziggy + SSR scaffold + InertiaSmokeTest`

## Next steps (handed to subsequent plans)

- **Plan 01-07 (Tailwind v4 CSS-first + dual-Tailwind workaround for Filament v3)** — uncomments `tailwindcss()` plugin in `vite.config.ts`; replaces the placeholder `resources/css/app.css` with the full `@import "tailwindcss"` + `@theme` block from UI-SPEC.md; adds the `tailwindcss-v3@npm:tailwindcss@^3.4` aliased install for Filament's theme compilation. Wraps Home.vue with PublicLayout (LayoutBase from UI-SPEC).
- **Plan 01-08 (i18n end-to-end)** — adds `locale` + `translations` props to `HandleInertiaRequests::share()` and `resources/js/types/inertia.d.ts`. Uncomments the `i18nVue` plugin import + `.use(i18nVue, { ... })` block in `app.ts`. Authors `lang/en/{auth,common,validation,admin}.php`. Replaces literal strings in Home.vue with `t('home.tagline')` etc. NoHardcodedStringsTest pest test.
- **Plan 01-09 (Discord Socialite OAuth)** — adds `LoginButton` Vue component to Home.vue (wired to `route('discord.redirect')`); adds `DiscordController` + `ProvisionFirstLogin` listener; HandleInertiaRequests::share()'s `auth` prop will start emitting real user data after first login.
- **Plan 01-12 (Filament v3)** — uses the same `vite.config.ts` (the dual-Tailwind workaround references the existing config rather than authoring a new one). Filament's own admin-theme.css ships through a separate Vite input.
- **Plan 01-15 (DTO TypeScript pipeline)** — emits to `apps/web/resources/js/types/api.d.ts` alongside the `inertia.d.ts` authored here. Both share the `@/types/*` alias from `tsconfig.json`'s paths.
- **Plan 01-16 (CI matrix)** — adds `pnpm install + pnpm run build` and `pnpm run vue-tsc` (or `vue-tsc --noEmit`) to web's CI lane. The vue-tsc devDep installed here is the binary that lane runs.
- **Plan 01-17 (Railway service configs)** — nixpacks/Dockerfile for the `web` service runs `pnpm install + pnpm run build` (and optionally `pnpm run build:ssr` if SSR is enabled in production). The deferred-items entry for SSR-enabled-in-prod was logged in the phase 01 deferred file by an earlier plan.
- **Plan 01-18 (BLOCKING smoke test)** — exercises the full stack-up path including HMR via `pnpm dev` from the host browser; will catch any HMR or SSR regression introduced by intervening plans.

## Self-Check: PASSED

**Files exist:**

- `/home/rtx/projects/trench-wars/apps/web/app/Http/Middleware/HandleInertiaRequests.php` — FOUND (HandleInertiaRequests class; auth + flash + ziggy share)
- `/home/rtx/projects/trench-wars/apps/web/config/inertia.php` — FOUND (SSR off in dev; page_paths lowercase)
- `/home/rtx/projects/trench-wars/apps/web/resources/views/app.blade.php` — FOUND (@inertia + @inertiaHead + @vite + @routes; no csrf meta)
- `/home/rtx/projects/trench-wars/apps/web/resources/js/app.ts` — FOUND (createInertiaApp + ZiggyVue)
- `/home/rtx/projects/trench-wars/apps/web/resources/js/bootstrap.ts` — FOUND (axios global)
- `/home/rtx/projects/trench-wars/apps/web/resources/js/ssr.ts` — FOUND (createServer + renderToString scaffold)
- `/home/rtx/projects/trench-wars/apps/web/resources/js/pages/Home.vue` — FOUND (placeholder with TODO comments for plans 07/08/09)
- `/home/rtx/projects/trench-wars/apps/web/resources/js/types/inertia.d.ts` — FOUND (typed PageProps for auth + flash + ziggy)
- `/home/rtx/projects/trench-wars/apps/web/tests/Feature/InertiaSmokeTest.php` — FOUND (Inertia render + Pitfall 3 mitigation)
- `/home/rtx/projects/trench-wars/apps/web/tsconfig.json` — FOUND (extends ../../tsconfig.base.json)
- `/home/rtx/projects/trench-wars/apps/web/vite.config.ts` — FOUND (Vue + Laravel plugins; Tailwind commented for plan 07)
- `/home/rtx/projects/trench-wars/apps/web/pnpm-lock.yaml` — FOUND (vue + inertia + ziggy + vite client deps)
- `/home/rtx/projects/trench-wars/apps/web/composer.json` — FOUND (inertiajs/inertia-laravel + tightenco/ziggy)
- `/home/rtx/projects/trench-wars/apps/web/composer.lock` — FOUND (Inertia v2.0.24 + Ziggy v2.6.2 locked)
- `/home/rtx/projects/trench-wars/apps/web/bootstrap/app.php` — FOUND (HandleInertiaRequests::class registered on web group)
- `/home/rtx/projects/trench-wars/apps/web/routes/web.php` — FOUND (GET / -> Inertia::render('Home') named home)
- `/home/rtx/projects/trench-wars/apps/web/public/build/manifest.json` — FOUND (Vite build output)
- `/home/rtx/projects/trench-wars/docker-compose.yml` — FOUND (tsconfig.base.json bind-mount; APP_* env removed)
- `/home/rtx/projects/trench-wars/docker/web/entrypoint.sh` — FOUND (chmod 0777 on storage + bootstrap/cache)

Files NOT present (intentional deletions):

- `apps/web/resources/views/welcome.blade.php` — DELETED (replaced by app.blade.php)
- `apps/web/resources/js/app.js` — DELETED (replaced by app.ts)
- `apps/web/resources/js/bootstrap.js` — DELETED (renamed to bootstrap.ts)
- `apps/web/vite.config.js` — DELETED (replaced by vite.config.ts)

**Commits exist:**

- `68fd97f` — FOUND in `git log --oneline` (`feat(01-06): wire Inertia v2 server adapter + Ziggy + HandleInertiaRequests middleware`)
- `3354372` — FOUND in `git log --oneline` (`feat(01-06): wire Vue 3 + Inertia v2 client + Vite + Ziggy + SSR scaffold + InertiaSmokeTest`)

**Runtime verification:**

- `docker compose exec web ./vendor/bin/pest --colors=never` → `Tests: 4 passed (17 assertions) Duration: 0.19s`
- `docker compose exec web ./vendor/bin/pint --test` → `PASS 27 files`
- `docker compose exec web ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress` → `[OK] No errors`
- `docker compose exec web pnpm run build` → `vite v7.3.2 ... ✓ 763 modules transformed. ✓ built in 1.05s` with valid `public/build/manifest.json`
- `curl -s -o /tmp/home.html -w "HTTP %{http_code}\\n" http://localhost:8000/` → `HTTP 200`; HTML contains `<title inertia>Trenchwars</title>`, `<div id="app" data-page="..."` with `component:"Home"`, `auth:null`, `flash:{success:null,error:null}`, full `ziggy.routes` including `home`, and NO `name="csrf-token"`.
- `docker compose exec web php artisan route:list --path=/` → `GET|HEAD / ... home › routes/web.php:8` (route bound; named `home`)
- All 4 stack services healthy: web, web-nginx, postgres, redis
