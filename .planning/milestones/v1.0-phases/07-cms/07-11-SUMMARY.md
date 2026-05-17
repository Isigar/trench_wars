---
phase: 07-cms
plan: 11
subsystem: infra
tags: [inertia, ssr, docker-compose, node-sidecar, i18n, pitfall-8, locale, blade, pest]

# Dependency graph
requires:
  - phase: 01-foundations
    provides: "Inertia v2 SSR scaffold (config/inertia.php, ssr.ts, vite.config.ts ssr input, app.blade.php <html lang>); LocaleMiddleware-shaped resolver chain in config/i18n.php"
  - phase: 07-cms
    provides: "Wave 5 Vue pages + SSR build produces bootstrap/ssr/ssr.js (07-10-SUMMARY)"
provides:
  - "6th docker-compose service `ssr` running `php artisan inertia:start-ssr` (split-service prod-parity per Open Question 7 LOCKED inline)"
  - "config/inertia.php ssr.url default retargeted from 127.0.0.1:13714 → ssr:13714 for docker service-name DNS resolution"
  - "INERTIA_SSR_ENABLED + INERTIA_SSR_URL documented in apps/web/.env.example; .env.testing explicit override (T-07-11-06)"
  - "GREEN SsrBundleExistsTest (4 it blocks, replaces 07-01 RED stub) — config, bundle, compose service registration"
  - "GREEN SsrLocaleHonouredTest (4 it blocks) — Pitfall 8 mitigation: <html lang> reflects app()->getLocale() end-to-end"
affects: [phase-07-13-phase-verification, deploy-railway-d-014, future-locale-middleware-v2]

# Tech tracking
tech-stack:
  added:
    - "Symfony\\Component\\Yaml\\Yaml::parseFile() idiom for asserting docker-compose service registration from inside the container (Phase 5 plan 05-01 precedent reused)"
    - "Test-only bind-mount: ./docker-compose.yml → /repo/docker-compose.yml:ro (read-only, idiomatic with the existing ./packages/shared-types and ./tsconfig.base.json mounts)"
  patterns:
    - "Pattern 5 (RESEARCH §522): Inertia v2 SSR enabled in production only — split service for prod, off in dev by default"
    - "Pitfall 8 mitigation pattern: <html lang=\"{{ str_replace('_','-', app()->getLocale()) }}\"> in root Blade view + LocaleMiddleware (when added) runs BEFORE HandleInertiaRequests in the middleware chain"
    - "T-07-11-04 isolation pattern: ports: [] explicitly empty on internal services so docker-compose cannot accidentally expose them to the host network"

key-files:
  created:
    - "apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php — 4 it() blocks proving the locale resolution chain reaches <html lang> and the Inertia data-page locale prop"
  modified:
    - "docker-compose.yml — added 6th `ssr` service + bind-mounted docker-compose.yml read-only into web container for test consumption"
    - "apps/web/config/inertia.php — ssr.url default updated to http://ssr:13714"
    - "apps/web/.env.example — INERTIA_SSR_URL documented + INERTIA_SSR_ENABLED comment refreshed to reference plan 07-11"
    - "apps/web/.env.testing — explicit INERTIA_SSR_ENABLED=false override"
    - "apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php — replaced 07-01 RED stub with 4 GREEN it() blocks"

key-decisions:
  - "Open Question 7 LOCKED inline RESOLVED — split `ssr` service over worker-co-host per RESEARCH Pattern 5 Option B (cleaner failure isolation on Railway D-014)"
  - "Phase 1 ssr.ts scaffolding is intact and functional — no refresh needed; the createInertiaApp + createSSRApp + ZiggyVue + renderToString chain matches the current @inertiajs/vue3@^2 server entry shape"
  - "Phase 1 app.blade.php uses <html lang=\"{{ str_replace('_','-', app()->getLocale()) }}\"> — Pitfall 8 baseline mitigation already in place, locked down by SsrLocaleHonouredTest"
  - "config/inertia.php ssr.enabled was already env-driven from Phase 1 plan 01-06 (no change needed) — plan 07-11 only adjusts ssr.url default to docker service-name DNS"
  - "Bind-mount the repo docker-compose.yml read-only into the web container at /repo/docker-compose.yml so SsrBundleExistsTest can Yaml::parseFile() it from inside the test runner — keeps test self-contained, no external fixture needed"

patterns-established:
  - "Compose-service contract tests: parse docker-compose.yml from inside the test container via Symfony Yaml and assert services + commands + healthchecks + port-exposure. Reusable for any future internal-only service."
  - "Locale chain end-to-end test: drive app()->setLocale() + Config::set('i18n.available_locales', [...]) and assert both <html lang> AND the Inertia shared `locale` prop. Locks down SSR↔client locale drift."

requirements-completed:
  - REQ-success-public-browse

# Metrics
duration: 5min
completed: 2026-05-14
---

# Phase 07-cms Plan 11: Inertia v2 SSR enable + Node sidecar in docker-compose Summary

**6th `ssr` docker-compose service running `php artisan inertia:start-ssr` (split-service prod-parity per Pattern 5 Option B), with config/inertia.php ssr.url retargeted to http://ssr:13714 and 8 GREEN Pest blocks locking down both the compose-service contract and the Pitfall 8 locale→<html lang> chain.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-05-14T02:13:20Z
- **Completed:** 2026-05-14T02:18:44Z
- **Tasks:** 2 / 2
- **Files modified:** 5 (1 created, 4 modified) + 1 modified test stub

## Accomplishments

- Open Question 7 LOCKED inline RESOLVED: SSR ships as a 6th `ssr` service in docker-compose (split service over worker-co-host per RESEARCH Pattern 5 Option B + Assumption A5). ports: [] keeps it internal-only (T-07-11-04). Healthcheck on /health endpoint. Production turns it on via Railway env group; dev defaults to off.
- config/inertia.php ssr.url default retargeted from `http://127.0.0.1:13714` to `http://ssr:13714` so the web service finds the sidecar via docker service-name DNS (D-021). INERTIA_SSR_URL env override preserved for hybrid deployments.
- apps/web/.env.example documents both INERTIA_SSR_ENABLED + INERTIA_SSR_URL with plan-07-11 + Pitfall 8 + Pattern 5 cross-references. apps/web/.env.testing explicit override so CI/local pest runs never reach the ssr port (T-07-11-06).
- 4-block SsrBundleExistsTest replaces the 07-01 RED stub: env-driven enabled, ssr:13714 default, bundle present post-build (skipped pre-build with explicit reason), ssr service contract verified by parsing docker-compose.yml via Symfony Yaml from inside the container.
- 4-block SsrLocaleHonouredTest locks the Pitfall 8 chain: default → en, App::setLocale('cs') → `<html lang="cs">`, underscore locales (en_US) → BCP-47 hyphen form (en-US), and `<html lang>` ↔ Inertia `props.locale` lockstep so SSR↔client hydration cannot drift.

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend docker-compose.yml + tune config/inertia.php + .env.{example,testing}** — `5dc4602` (feat)
2. **Task 2: GREEN SsrBundleExistsTest + author SsrLocaleHonouredTest** — `29824a9` (test)

## Files Created/Modified

- `docker-compose.yml` — added 6th `ssr` service (build context = docker/web/Dockerfile; command = `php artisan inertia:start-ssr`; ports: []; healthcheck on /health; depends_on postgres+redis; APP_ENV defaults to production). Also bind-mounted `./docker-compose.yml:/repo/docker-compose.yml:ro` into the web service so Pest can parse it from inside the container.
- `apps/web/config/inertia.php` — ssr.url default `http://127.0.0.1:13714` → `http://ssr:13714` with plan-07-11 comment block. ssr.enabled was already env-driven from Phase 1 plan 01-06 (verified intact, no change).
- `apps/web/.env.example` — appended `INERTIA_SSR_URL=http://ssr:13714` next to the existing `INERTIA_SSR_ENABLED=false`; comment block refreshed to reference plan 07-11 + Pitfall 8 + Pattern 5.
- `apps/web/.env.testing` — appended `INERTIA_SSR_ENABLED=false` explicit override (T-07-11-06).
- `apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php` — replaced 07-01 RED stub (`expect(true)->toBe(false)`) with 4 GREEN it() blocks.
- `apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php` — NEW, 4 it() blocks for the Pitfall 8 locale chain.

## Decisions Made

- **Split `ssr` service over worker-co-host (Open Question 7 LOCKED inline RESOLVED).** RESEARCH Pattern 5 Option B + Assumption A5 — split service gives cleaner failure isolation on Railway (D-014) and matches the prod topology. Worker-co-host was viable for dev RAM savings but the operator is opting into prod-parity. Dev still defaults off via INERTIA_SSR_ENABLED=false so the Vite hot-reload loop stays fast.
- **Phase 1 ssr.ts scaffolding is intact — no refresh needed.** The current entry uses `createInertiaApp` + `createSSRApp` + `ZiggyVue` + `renderToString` from `@inertiajs/vue3/server` v2, which is the canonical v2 shape; the `import.meta.glob` page resolver with explicit throw on missing page matches the Vite SSR entry idiom.
- **Phase 1 app.blade.php is intact — Pitfall 8 baseline already in place.** Uses `<html lang="{{ str_replace('_','-', app()->getLocale()) }}">`, which gives both the BCP-47 hyphen form (a11y) and the canonical locale resolution chain. No change needed; SsrLocaleHonouredTest locks it down.
- **LocaleMiddleware does NOT yet exist in bootstrap/app.php.** Phase 1 plan 01-08 shipped only `config/i18n.php` (resolution order + available locales) — the actual middleware that walks the chain is deferred to Phase 2+. The Inertia `HandleInertiaRequests::share()` already exposes `locale` via `app()->getLocale()`, so the moment a future middleware lands and calls `App::setLocale()`, both `<html lang>` AND the Inertia shared prop pick it up in lockstep. This is exactly what SsrLocaleHonouredTest pulses via `App::setLocale('cs')` to prove the chain works regardless of HOW the locale was resolved.
- **Bind-mount docker-compose.yml read-only into web container for Pest consumption.** The repo-root file is not normally accessible from inside the web container (only `./apps/web` → `/app` and `./packages/shared-types` → `/repo/packages/shared-types` are mounted). Adding `./docker-compose.yml:/repo/docker-compose.yml:ro` is the minimal extra mount needed for SsrBundleExistsTest to parse the file via `Symfony\Component\Yaml\Yaml::parseFile('/repo/docker-compose.yml')` — keeps the test self-contained with no external fixture.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Bind-mount docker-compose.yml into web container for test access**
- **Found during:** Task 2 (SsrBundleExistsTest design — the plan calls for `Yaml::parseFile(base_path('../docker-compose.yml'))`)
- **Issue:** The repo-root `docker-compose.yml` is NOT bind-mounted into the web container. `base_path('../docker-compose.yml')` resolves to `/app/../docker-compose.yml` = `/docker-compose.yml`, which does not exist. The test would hard-fail under the plan as written.
- **Fix:** Added `./docker-compose.yml:/repo/docker-compose.yml:ro` to the `web` service `volumes:` list (alongside the existing `./packages/shared-types:/repo/packages/shared-types` and `./tsconfig.base.json:/tsconfig.base.json:ro` idiom). Test reads from `/repo/docker-compose.yml`.
- **Files modified:** `docker-compose.yml` (added one volume entry); `apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php` (uses `/repo/docker-compose.yml` not `base_path('../docker-compose.yml')`)
- **Verification:** `docker compose exec web ls /repo/docker-compose.yml` returns the file post-recreate; `Yaml::parseFile` returns the services map; SsrBundleExistsTest GREEN.
- **Committed in:** `5dc4602` (Task 1 — bind-mount addition) + `29824a9` (Task 2 — test reads from the mounted path)

**2. [Rule 2 - Missing Critical] SSR bundle filename is `.js` not `.mjs`**
- **Found during:** Task 2 (SsrBundleExistsTest — Vite emits `bootstrap/ssr/ssr.js` because `apps/web/package.json` has no `"type":"module"`)
- **Issue:** Plan repeatedly references `bootstrap/ssr/ssr.mjs` but the actual Vite SSR output is `bootstrap/ssr/ssr.js` (default behaviour without ES-module package.json). Hardcoding `.mjs` would skip a present, working bundle and silently mask real breakage.
- **Fix:** Test checks BOTH candidate filenames (`ssr.mjs` and `ssr.js`); accepts whichever Vite emitted. Forward-compatible with a future `package.json` ESM bump.
- **Files modified:** `apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php`
- **Verification:** `ls apps/web/bootstrap/ssr/ssr.js` → present (post-pnpm-build); test GREEN.
- **Committed in:** `29824a9` (Task 2)

**3. [Rule 2 - Missing Critical] LocaleMiddleware does not yet exist; Accept-Language has no resolver**
- **Found during:** Task 2 (SsrLocaleHonouredTest design — plan called for `Accept-Language: cs` header to resolve to `<html lang="cs">`)
- **Issue:** The plan assumes Phase 1 plan 01-08 shipped a LocaleMiddleware that resolves Accept-Language → `App::setLocale()`. Reality: only `config/i18n.php` exists (resolution order config); no middleware walks the chain yet. Without a middleware, `Accept-Language: cs` is ignored by Laravel and `<html lang>` stays at the APP_LOCALE default. Asserting on Accept-Language directly would either always fail or hide the missing middleware.
- **Fix:** Test the **mitigation surface** instead of the **resolver**. The Pitfall 8 mitigation is: "when locale IS resolved (by any mechanism — middleware, controller, console command), it MUST propagate into `<html lang>` AND `Inertia.props.locale` together." Tests pulse `App::setLocale('cs')` directly to prove the Blade resolution + Inertia shared-prop chain is intact. The day a LocaleMiddleware lands (Phase 2+), it'll plug into this chain without test changes. Also added a 4th block (1 over plan minimum) for the `<html lang>` ↔ `props.locale` lockstep assertion — catches drift if anyone changes one but not the other.
- **Files modified:** `apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php`
- **Verification:** All 4 blocks GREEN; the cookie-path block from the plan minimum (3 blocks) was REPLACED with a BCP-47-form block + a lockstep block — the cookie path is meaningless without a resolver that consumes the cookie.
- **Committed in:** `29824a9` (Task 2)

---

**Total deviations:** 3 auto-fixed (1 Rule 3 blocking, 2 Rule 2 missing-critical)
**Impact on plan:** All three were required to make the plan executable as designed. None changed the architectural intent (split ssr service, env-driven enable, Pitfall 8 mitigation via Blade + Inertia shared prop); all three improved test fidelity over what the plan strictly specified. No scope creep.

## Issues Encountered

- **Container recreation needed for new bind-mount.** Adding `./docker-compose.yml:/repo/docker-compose.yml:ro` to the web service required `docker compose up -d web` to recreate the container before the test could see the file. Pre-existing healthchecks made the recreate idempotent; no other services were touched.

## Self-Check: PASSED

Files verified to exist:
- `apps/web/tests/Feature/Ssr/SsrLocaleHonouredTest.php` — present, 4 it() blocks
- `apps/web/tests/Feature/Ssr/SsrBundleExistsTest.php` — present, 4 it() blocks (RED stub replaced)
- `docker-compose.yml` — `ssr:` service present at line ~166; `/repo/docker-compose.yml` bind mount present
- `apps/web/config/inertia.php` — `'url' => env('INERTIA_SSR_URL', 'http://ssr:13714')` present
- `apps/web/.env.example` — `INERTIA_SSR_URL=http://ssr:13714` present
- `apps/web/.env.testing` — `INERTIA_SSR_ENABLED=false` present

Commits verified to exist:
- `5dc4602` — feat(07-11): add ssr Node sidecar service…
- `29824a9` — test(07-11): GREEN SsrBundleExistsTest + author SsrLocaleHonouredTest

Verification commands re-run and passing:
- `docker compose config --quiet` → exit 0
- `docker compose config | grep -E "^  ssr:$"` → ssr present
- `docker compose exec web php artisan tinker --execute="echo config('inertia.ssr.url');"` → `http://ssr:13714`
- `docker compose exec web ./vendor/bin/pest --filter='SsrBundleExistsTest|SsrLocaleHonouredTest'` → 8 passed
- `docker compose exec web ./vendor/bin/pint --test` → 506 files PASS
- `docker compose exec web ./vendor/bin/phpstan analyse --no-progress` → No errors

## Next Phase Readiness

- SSR sidecar service ready for Railway D-014 deploy: operator sets `INERTIA_SSR_ENABLED=true` in the production env group and the service spins up alongside web/worker/bot/rcon-worker/postgres/redis.
- Phase 7 plan 07-12 (sitemap + meta tags) can depend on the SSR-emitted HTML for crawler verification.
- Phase 7 plan 07-13 (phase verification) inherits a working SSR contract — manual prod smoke can verify first-paint via `curl https://prod/blog | grep '<html lang'`.
- Future LocaleMiddleware (Phase 2+) plugs into the existing chain: SsrLocaleHonouredTest stays GREEN without modification because the test exercises the mitigation surface (App::setLocale → Blade → Inertia shared prop), not the resolver implementation.

---
*Phase: 07-cms*
*Completed: 2026-05-14*
