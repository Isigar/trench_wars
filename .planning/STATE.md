---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: verifying
stopped_at: "Plan 02-05 complete (Wave 2). PlayerPrivacyGate service + 6 DTOs. 23 gate tests + 11 DTO tests GREEN. Optional::create() strategy for absent-vs-null. api.d.ts regenerated. PHPStan + Pint clean. 23/32 plans complete."
last_updated: "2026-05-12T18:51:55.596Z"
last_activity: 2026-05-12
progress:
  total_phases: 9
  completed_phases: 1
  total_plans: 32
  completed_plans: 24
  percent: 11
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-03)

**Core value:** Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically.
**Current focus:** Phase 01 — Foundations

## Current Position

Phase: 01 (Foundations) — COMPLETE
Plan: 18 of 18 (all plans executed; 18/18 summaries on disk)
Status: Phase complete — ready for verification
Last activity: 2026-05-12

Progress: [██████████] 100%

## Performance Metrics

**Velocity:**

- Total plans completed: 10
- Average duration: ~5 min (01-16 was the fastest at ~2 min — pure file authoring; 01-07 took ~5 min including pnpm add + Vite build verification inside the web container)
- Total execution time: ~0.85 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-foundations | 8/18 | ~44 min | ~5.5 min |

**Recent Trend:**

- Last 8 plans: 01-01 (~3 min), 01-02 (~3 min), 01-03 (~3 min), 01-04 (~7 min), 01-05 (~7 min), 01-06 (~12 min), 01-10 (~2.8 min), 01-16 (~2 min)
- Trend: 01-16 was the cleanest execution yet — pure YAML + config-file authoring with no install, no runtime verification (CI is the verification surface), 4 deviations all easily caught at write-time (skeleton-test type swap, pnpm flag syntax, master branch trigger, expanded Pest env)

*Updated after each plan completion*
| Phase 01 P07 | 5min | 2 tasks | 12 files |
| Phase 01 P08 | 5min | 2 tasks | 19 files |
| Phase 01 P11 | 4min | 2 tasks | 10 files |
| Phase 01 P09 | 5min | 3 tasks | 12 files |
| Phase 01 P12 | 9 | 2 tasks | 17 files |
| Phase 01 P13 | 6min | 2 tasks tasks | 18 files files |
| Phase 01 P15 | 8min | 2 tasks tasks | 19 files files |
| Phase 01 P14 | 9m 9s | 2 tasks | 14 files |
| Phase 01 P18 | 6min | 2 tasks tasks | 4 files files |
| Phase 02-clans-tags P02 | 140 | 2 tasks | 7 files |
| Phase 02-clans-tags P04 | 126s | 2 tasks | 5 files |
| Phase 02-clans-tags P05 | 310s | 2 tasks | 10 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table (D-001 through D-021, all LOCKED).
Recent decisions affecting current work:

- D-001 Stack: Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3
- D-002 Auth: Discord OAuth only; Discord ID is canonical user identity
- D-013 i18n plumbed from day one; EN at launch
- D-014 Hosting on Railway (5 services + Postgres + Redis plugins)
- D-017 No Laravel starter kit; hand-roll Discord Socialite auth scaffolding
- D-021 Local dev via custom `docker-compose.yml` at repo root (all 5 services + postgres + redis containerized; host runs only Docker Desktop, Node 22, Composer-via-container)

Plan-level decisions logged during execution:

- 01-04 used /tmp/laravel scaffold-then-merge pattern to work around named-volume + entrypoint collision (Rule 3 deviation; method-only — final filesystem state matches plan intent)
- 01-04 ran migrate:fresh post-commit to clean Laravel-default schema that composer's auto-migrate had created in postgres (Rule 1 deviation; DB state only, no code change)
- 01-04 deleted Laravel default users/cache/jobs migrations to keep DB clean for plan 10's UUID-PK schema (per plan task 2 step 6)
- 01-05 removed phpunit/phpunit ^11.5 from composer.json before Pest 4 install — Pest 4 requires phpunit ^12.5 (Rule 3 deviation; canonical Pest upgrade path)
- 01-05 used dual <env force=true> + <server force=true> tags in phpunit.xml — Laravel reads APP_ENV from $_SERVER first; PHPUnit's <env force=true> doesn't write $_SERVER. Rather than modify docker-compose env (plan 01-02 territory), keep override at the test invocation layer
- 01-05 committed apps/web/.env.testing with a static base64 APP_KEY (test keys are NOT secrets; .gitignore excludes .env/.env.backup/.env.production but NOT .env.testing — committing is the canonical Laravel pattern)
- 01-05 dropped checkMissingIterableValueType + checkGenericClassInNonGenericObjectType from phpstan.neon — both options removed in PHPStan v2 (Rule 3 deviation; the plan's pasted neon was authored against PHPStan v1)
- 01-05 ran Pint to apply 10 auto-fixes against Laravel default files alongside the install (must_have requires `pint --test` green from day 1)
- 01-06 added repo-root tsconfig.base.json bind-mount in docker-compose.yml so apps/web/tsconfig.json's `extends "../../tsconfig.base.json"` resolves inside the web container (pnpm runs in container per D-021; bot/rcon-worker bake the base config in via Dockerfile COPY but web's bind-mount strategy needed an explicit volume entry — Rule 3 deviation; cross-cuts plan 01-02 territory but is one-line surgical)
- 01-06 removed APP_ENV/APP_DEBUG/APP_URL/APP_KEY env injection from docker-compose.yml's web service. The empty `${APP_KEY:-}` was shadowing apps/web/.env's real key via $_SERVER (same root cause as plan 01-05's phpunit fix, but for runtime nginx requests instead of test invocations). Production overrides via Railway env groups remain unaffected
- 01-06 bumped docker/web/entrypoint.sh chmod from 0775 to 0777 on storage + bootstrap/cache. php-fpm runs as www-data (uid 33) but bind-mount is host-uid-1000 (rtx) — without 0777 every nginx request 500s on tempnam() into storage/framework/views. Dev-only; gitignored content; production single-user containers keep 0775
- 01-06 customised config/inertia.php to lowercase page_paths (Pages -> pages) for both root + testing block (Inertia default disagreed with plan structure); flipped ssr.enabled default true -> false + ensure_bundle_exists default true -> false (CONTEXT.md "scaffolded but optional in dev")
- 01-06 added @vue/server-renderer to package.json devDependencies (Rule 3 — required by ssr.ts but absent from plan's pasted pnpm-add list)
- 01-06 reworded the Pitfall 3 reminder comment in app.blade.php from `<meta name="csrf-token">` (literal — false-matched the source-grep verify) to `CSRF-token meta tag` (descriptive prose). Same intent, no false grep match
- 01-10 added `rememberToken()` to the users migration (plan prose mentioned it but the pasted snippet omitted it; User::$hidden references remember_token; Authenticatable contract assumes it). Followed the prose, not the snippet (Rule 2 — missing critical functionality)
- 01-10 added `/** @use HasFactory<XFactory> */` PHPDoc tags to all 3 models so PHPStan level 8 doesn't flag HasFactory as a non-generic class usage. The plan's pasted snippets omitted these but the project's existing User model already had the pattern (Rule 2 — type-correctness for L8 gate)
- 01-10 ran `pint database/factories/PlayerFactory.php` to apply the auto concat_space correction (Rule 1 — Pint preset compliance is a CI gate); final source spaces around `.` operator
- 01-16 imported `TrenchwarsApiContract` (placeholder shipped in 01-01) instead of `UserData` in skeleton tests — `UserData` is shipped by plan 01-15 (wave 10) which has not run yet (Rule 3 — blocking; plan example referenced a not-yet-existing type). Plan 01-15 will swap the import as part of its task 2
- 01-16 used `pnpm install --no-frozen-lockfile` (not `--frozen-lockfile=false` from plan; that's invalid pnpm CLI syntax) (Rule 3 — blocking; canonical pnpm 9 flag)
- 01-16 added `master` to `on.push.branches` in all 4 workflows alongside `[main, develop]` — repo currently on `master` (Rule 2 — without it CI never runs on the active branch)
- 01-16 expanded Pest env in web.yml to include DB_CONNECTION/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD/REDIS_PORT instead of the plan's minimal DB_HOST/REDIS_HOST — guarantees Pest targets the postgres service container regardless of .env.example defaults (Rule 2 — RefreshDatabase correctness)
- [Phase ?]: Tailwind v4 CSS-first authoring (no tailwind.config.js); semantic tokens declared in @theme + :root + [data-theme=light]
- [Phase ?]: Dark default at both <html data-theme=dark> SSR root and useTheme ref initial value; localStorage('trenchwars.theme') persistence
- [Phase ?]: Components reference semantic tokens via var(--color-*); no raw hex outside app.css :root/[data-theme=*] blocks
- [Phase ?]: IconButton 44x44 mobile / 36x36 desktop touch target (UI-SPEC); aria-label required prop
- [Phase 01]: 01-08 chose in-house useT() composable over laravel-vue-i18n's Vite plugin path — reads directly from Inertia's shared translations prop, sidestepping RESEARCH Pitfall 8 SSR async-glob trap; the package stays installed (kept on dep graph for Phase 2+ client-side validation rendering needs) but unwired in P1
- [Phase 01]: 01-08 made Inertia translations prop a flat dot-keyed Record<string,string> rather than a nested tree — gives useT an O(1) lookup with no path parser; middleware's flatten() helper recursively walks the 5 namespaces (auth/common/admin/home/validation) into composite keys like auth.discord.button_label
- [Phase 01]: 01-08 localised ThemeToggle.vue aria-label via Rule 2 deviation — the NoHardcodedStringsTest scope is <template> only so <script setup> literals would have escaped, but D-013 ('every UI string flows through t() / __()') is unambiguous and aria-label is a UI string; added common.theme.switch_to_{light,dark} keys
- [Phase 01]: 01-08 copied Laravel 12 default lang/en/validation.php verbatim into our custody (apps/web/lang/en/validation.php) — ValidationMessagesLocalizedTest asserts validation.{required,unique,email} resolve from our copy not the framework default, so tomorrow's CS/SK locale drop has a complete set of keys to override
- [Phase 01]: Plan 01-11: pin spatie permission default_guard='web' (config + model) — Pitfall 4 mitigation for plan 12 Filament gate
- [Phase 01]: Plan 01-11: override Spatie published migration to use uuid('model_id') for both model_has_permissions + model_has_roles (D-002 alignment)
- [Phase 01]: Native <a href> rather than Inertia <Link> in LoginButton — Inertia XHR would never complete the cross-origin Discord redirect — Plan 01-09
- [Phase 01]: AbstractProvider type-narrow on Socialite::driver('discord') so PHPStan L8 can resolve scopes() (Contracts\Provider has no scopes()) — Plan 01-09 deviation Rule 3
- [Phase 01]: Slug derivation = Str::slug(username) + '-' + 4 random lowercase chars; collision-tolerant for P1 scale — Plan 01-09
- [Phase 01]: OAuth credentials configured in tests via config()->set rather than phpunit.xml — keeps committed config secret-shape-free — Plan 01-09
- [Phase ?]: [Phase 01]: Plan 01-12 dual-Tailwind workaround proven — Tailwind v4 main pipeline + Tailwind v3 ('tailwindcss-v3' alias) Filament theme pipeline build cleanly into separate manifests; main vite.config.ts pinned to css.postcss:{plugins:[]} to avoid auto-detected postcss.config.js bleed-over
- [Phase ?]: [Phase 01]: Plan 01-12 RedirectFilamentAuthToDiscord middleware subclasses Filament's Authenticate::redirectTo() to return route('auth.discord.redirect') — Filament's getLoginUrl() returns null when ->login() is dropped, which would otherwise resolve to the undefined 'login' named route
- [Phase ?]: [Phase 01]: Plan 01-12 dropped @import vendor/filament/.../theme.css — Vite CSS resolver follows the inner @import 'tailwindcss/base' chain through Tailwind v4 (no /base subpath) and fails. Filament preset's content array still scans Filament blade files; small base-layer overrides inlined directly
- [Phase ?]: [Phase 01]: Plan 01-12 User implements FilamentUser + HasName; getFilamentName returns username (Discord username — D-002 means schema has no 'name' field; getUserName fallback via HasName)
- [Phase ?]: 01-13: Used getModelLabel/getPluralModelLabel overrides on all 4 Filament resources to keep i18n end-to-end (D-013 — sidebar labels via __('admin.<r>.label')).
- [Phase ?]: 01-13: RoleResource pins guard_name='web' twice (Form Select disabled+dehydrated AND CreateRole::mutateFormDataBeforeCreate) — Pitfall 4 defence-in-depth.
- [Phase ?]: 01-13: PermissionResource is List+Edit only (no Create) — admin grants permissions via PermissionSeeder + trenchwars:make-admin (CONTEXT.md), so surfacing Create in Filament would let admins mint unreferenced permission strings.
- [Phase ?]: 01-13: UserResource locale Select sourced from config('i18n.available_locales') via private localeOptions() helper — typed @var array<int,string> annotation needed because raw config() return is mixed (PHPStan L8 fix, Rule 1).
- [Phase ?]: Plan 01-15: spatie/laravel-typescript-transformer v3.0 dropped config/typescript-transformer.php; configure via subclass of TypeScriptTransformerApplicationServiceProvider with configure(TypeScriptTransformerConfigFactory)
- [Phase ?]: Plan 01-15: composer require -W needed; phpdocumentor/reflection-docblock locked at 6.0.3 had to downgrade to 5.6.7 for phpdocumentor/reflection ^6.1 (Rule 3 deviation)
- [Phase ?]: Plan 01-15: GlobalNamespaceWriter path is relative to outputDirectory — use bare filename ('api.d.ts') + outputDirectory=resources/js/types so the writer doesn't double-prefix
- [Phase ?]: Plan 01-15: typescript:install register-check (Str::contains for fully-qualified namespace) misreads bootstrap/providers.php's use-imports format and reports 'already registered' without writing the entry — manual registration required
- [Phase ?]: Plan 01-15: cross-package shared-types sync via in-container artisan command + extra docker bind mount (./packages/shared-types -> /repo/packages/shared-types on web service); host-side packages/shared-types/scripts/sync-types.sh as fallback for environments without the bind mount
- [Phase ?]: Plan 01-15: packages/shared-types/src/index.ts uses '/// <reference path>' + type aliases ('export type UserData = App.Data.UserData') so consumers can import without spelling out the ambient App.Data.* namespace
- [Phase ?]: Plan 01-14: spatie/laravel-activitylog v5 single-migration consolidation; UUID subject_id/causer_id via follow-up ALTER; LogsActivity on User suppresses last_login_at-only changes
- [Phase ?]: Plan 01-14: Audit Filament Page gated on audit.view permission (web guard); read-only by design (CLAUDE.md \xc2\xa76 + D-012); per-resource Audit tab via Forms\\Components\\Tabs + Placeholder pattern
- [Phase ?]: [Phase 01]: Plan 01-18: phase verification report doubles as the canonical phase-close artifact (M1..M7 traceability + ROADMAP SC-1..SC-5 mapping); manual smokes deferred to operator per autonomous-mode handoff
- [Phase ?]: [Phase 01]: Plan 01-18: Rule 3 fix added @types/node to packages/shared-types/package.json — pre-existing config gap from plan 01-15 surfaced when running bot/rcon-worker/shared-types pipelines as part of phase close; strictly additive (no architectural change)
- [Phase ?]: [Phase 01]: Plan 01-18: pnpm-lock.yaml committed at repo root for the first time (4193 lines) — improves reproducibility; CI workflows already use --no-frozen-lockfile so optional but recommended
- [Phase 02]: Plan 02-04: DiscordGuildSeeder uses firstOrCreate([]) singleton trick — empty $attributes matches any existing row; D-003 operational enforcement; admin fills guild_id via Filament edit (plan 02-13)
- [Phase 02]: Plan 02-04: ClanTagSeeder uses firstOrCreate(['slug' => $slug]) — UNIQUE slug column is the idempotency key; 3 starter tags (EU/NA/Tier-1) with translatable JSONB labels (D-013)
- [Phase 02]: Plan 02-04: DiscordGuildSingleRowTest documents operational-only enforcement contract (RESEARCH.md Pattern 4 — DB layer accepts second row; gate is seeder+Filament no-Create page in plan 02-13)
- [Phase ?]: Plan 02-05: Optional|T|null union types on PublicPlayerData — required by PHP type system to store Optional in typed properties; VisibleDataFieldsResolver strips Optional from toArray()
- [Phase ?]: Plan 02-05: PlayerPrivacyGate is stateless — no constructor injection, auto-resolved by Laravel container; own-profile bypass always grants full access regardless of tier or section flags

### Pending Todos

None yet.

### Blockers/Concerns

No active blockers. Docker Desktop WSL integration is enabled (verified 2026-05-03: `docker --version` 29.3.0, daemon reachable). Phase 1 execution is in flight per D-021 (everything in containers).

Advisory (non-blocking): Open Questions in PROJECT.md (branding, editorial cadence, tournament tiebreakers, league-guild membership requirement) — worth resolving before phases that depend on them.

## Deferred Items

Items acknowledged and carried forward from previous milestone close:

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| Repository state | `.docs/` directory untracked in git (17 reference docs from out-of-band intel ingest) | open | plan 01-04 (logged in `.planning/phases/01-foundations/deferred-items.md`) |

## Session Continuity

Last session: 2026-05-12T18:51:55.593Z
Stopped at: Plan 02-05 complete (Wave 2). PlayerPrivacyGate service + 6 DTOs. 23 gate tests + 11 DTO tests GREEN. Optional::create() strategy for absent-vs-null. api.d.ts regenerated. PHPStan + Pint clean. 23/32 plans complete.
Resume file: None
