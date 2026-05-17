---
phase: 1
slug: foundations
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-05-03
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 (PHP / Laravel feature + unit + browser) + vitest v2 (TS for `apps/bot` and `apps/rcon-worker` skeletons) |
| **Config file** | `apps/web/phpunit.xml` (Pest), `apps/bot/vitest.config.ts`, `apps/rcon-worker/vitest.config.ts` (all created Wave 0) |
| **Quick run command** | `docker compose exec web ./vendor/bin/pest --filter <pattern>` |
| **Full suite command** | `docker compose exec web ./vendor/bin/pest --parallel` |
| **Estimated runtime** | ~30s (P1 has ≤ 20 tests; full suite < 1 minute including browser smoke) |

Lint/static analysis (run alongside tests in CI):

| Tool | Command |
|------|---------|
| Pint (formatter) | `docker compose exec web ./vendor/bin/pint --test` |
| Larastan / PHPStan L8 | `docker compose exec web ./vendor/bin/phpstan analyse --level 8` |
| TypeScript (bot) | `pnpm --filter @trenchwars/bot exec tsc --noEmit` |
| TypeScript (rcon-worker) | `pnpm --filter @trenchwars/rcon-worker exec tsc --noEmit` |
| ESLint (bot/rcon-worker) | `pnpm --filter <pkg> exec eslint src/` |

---

## Sampling Rate

- **After every task commit:** Run `docker compose exec web ./vendor/bin/pest --filter <feature_or_unit>` (≤ 5s slice).
- **After every plan wave:** Run `docker compose exec web ./vendor/bin/pest` (full Pest suite + Pint + PHPStan).
- **Before `/gsd-verify-work`:** Full suite must be green; CI matrix must pass on at least one push.
- **Max feedback latency:** 30 seconds for the per-task slice; 90 seconds for the per-wave full suite.

---

## Per-Task Verification Map

> Plan IDs are **placeholders** — gsd-planner determines actual breakdown. This table will be replaced by the plan-checker once plans land. Below is the mapping derived from RESEARCH.md "Validation Architecture" + UI-SPEC.md.

| Test File (Wave 0) | Phase Goal Mapping | Requirement | Test Type | Automated Command | File Exists |
|---|---|---|---|---|---|
| `apps/web/tests/Feature/Auth/DiscordOAuthTest.php` | SC-1: Discord login + first-login provisioning (`users` + `players` + `player_privacy`) | REQ-constraint-railway-deploy | feature (Pest, mocked Socialite) | `pest tests/Feature/Auth/DiscordOAuthTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php` | SC-1: Idempotent re-login (no duplicate rows) | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Auth/FirstLoginProvisioningTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Admin/FilamentPanelAccessTest.php` | SC-2: `/admin` reachable for users with `admin-access` permission only | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Admin/FilamentPanelAccessTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php` | SC-2: User, Player, Role, Permission resources mounted | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Admin/FilamentResourcesPresentTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php` | SC-3: Every admin mutation creates `activity_log` row | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Audit/AuditPageTest.php` | SC-3: `/admin/audit` page lists activity with filters | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Audit/AuditPageTest.php` | ❌ W0 |
| `apps/web/tests/Feature/I18n/TranslationsSharedTest.php` | SC-4: Inertia shares `translations` prop; `t()` resolves | REQ-constraint-en-launch-i18n-ready | feature | `pest tests/Feature/I18n/TranslationsSharedTest.php` | ❌ W0 |
| `apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php` | SC-4: No hardcoded strings (grep over `resources/js/Pages/**/*.vue`) | REQ-constraint-en-launch-i18n-ready | feature (script-style) | `pest tests/Feature/I18n/NoHardcodedStringsTest.php` | ❌ W0 |
| `apps/web/tests/Feature/Health/BootHealthcheckTest.php` | App boots, `/` returns 200, `/admin` returns 302 logged-out, 200 logged-in admin | REQ-constraint-railway-deploy | feature | `pest tests/Feature/Health/BootHealthcheckTest.php` | ❌ W0 |
| `apps/web/tests/Feature/I18n/ValidationMessagesLocalizedTest.php` | SC-4: Validation messages render via `lang/en/validation.php` | REQ-constraint-en-launch-i18n-ready | feature | `pest tests/Feature/I18n/ValidationMessagesLocalizedTest.php` | ❌ W0 |
| `apps/bot/tests/skeleton.test.ts` | Skeleton boots & types compile (Phase 5 fills) | (none — skeleton-only in P1) | unit (vitest) | `pnpm --filter @trenchwars/bot test` | ❌ W0 |
| `apps/rcon-worker/tests/skeleton.test.ts` | Skeleton boots & types compile (Phase 8 fills) | (none — skeleton-only in P1) | unit (vitest) | `pnpm --filter @trenchwars/rcon-worker test` | ❌ W0 |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `docker-compose.yml` at repo root with services: `web` (php:8.4-fpm + intl + pdo_pgsql + redis), `web-nginx`, `bot` (node:22), `rcon-worker` (node:22), `postgres:16`, `redis:7-alpine`. Healthchecks on every service.
- [ ] `pnpm-workspace.yaml` at repo root listing `apps/*` and `packages/*`.
- [ ] `apps/web/phpunit.xml` configured for Pest with parallel + DB transactions.
- [ ] `apps/web/tests/Pest.php` with shared traits (`RefreshDatabase`, `Auth` helpers).
- [ ] `apps/web/tests/Feature/` and `apps/web/tests/Unit/` directories created with at least the test stubs listed above.
- [ ] `apps/bot/vitest.config.ts` + `apps/bot/tests/skeleton.test.ts`.
- [ ] `apps/rcon-worker/vitest.config.ts` + `apps/rcon-worker/tests/skeleton.test.ts`.
- [ ] `apps/web/composer.json` includes Pest + Pint + Larastan (PHPStan L8) as dev deps.
- [ ] `apps/web/phpstan.neon` at level 8 with Filament-related ignores baselined into `phpstan-baseline.neon`.
- [ ] `.github/workflows/ci.yml` running matrix: web (Pest + Pint + PHPStan) + bot (tsc + vitest + eslint) + rcon-worker (tsc + vitest + eslint) on every push.
- [ ] Postgres extensions migration (`uuid-ossp`, `citext`) lands BEFORE any table migration that uses them.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament theme renders correctly with dual-Tailwind (v4 site + v3 Filament) workaround | REQ-constraint-railway-deploy | Filament v3 + Tailwind v4 conflict (HIGH-risk pitfall from RESEARCH.md). Visual regression check needed in browser; the per-resource form CSS classes are most likely to break. | After Wave 0 lands and panel is reachable: `docker compose exec web npm run build`, then open `http://localhost:8000/admin` in a browser. Check: form labels, input borders, primary buttons (accent), table row hover, dark/light toggle. If any look unstyled or use raw HTML defaults, the dual-Tailwind config is broken. |
| Discord OAuth happy path (real Discord developer app, not mocked) | REQ-constraint-railway-deploy | OAuth requires live Discord redirect; can't be fully mocked in CI. Pest tests use mocked Socialite; manual verification is the end-to-end gate. | Create dev Discord app at https://discord.com/developers/applications, set redirect URI = `http://localhost:8000/auth/discord/callback`, fill `.env` with `DISCORD_CLIENT_ID` + `DISCORD_CLIENT_SECRET`. Open `/`, click "Log in with Discord", complete OAuth. Verify: redirect lands on `/` with logged-in user, DB has new `users` + `players` + `player_privacy` rows. Re-login → no duplicate rows. |
| Railway deploy of `web` + `worker` services from monorepo | REQ-constraint-railway-deploy | Railway-side configuration (Root Directory per service, env vars, plugin attachment); cannot be exercised in local CI. | After P1 lands: install Railway CLI, `railway init`, set Root Directory to `apps/web` for `web` service, `apps/web` (with worker start command) for `worker` service. Attach Postgres + Redis plugins. Set Discord OAuth env vars. Push branch → Railway deploys → manual smoke `curl https://<railway-url>/` returns 200. |
| First-time scaffolder onboarding doc renders correctly in `README.md` | (developer onboarding) | Markdown formatting + step ordering only verifiable by reading | Read README.md top-to-bottom; ensure Discord Developer Portal steps are numbered and reproducible by someone with no prior knowledge. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending — set after gsd-plan-checker validates against this strategy.
