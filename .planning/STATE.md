---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: Phase 9 in flight
stopped_at: Completed 09-10-PLAN.md (Wave 8 — SC-5 a11y: focus-visible + axe-core CI + 2 Pest tests GREEN; Task 2 PENDING_MANUAL_SMOKE)
last_updated: "2026-05-15T15:57:00Z"
last_activity: 2026-05-15
progress:
  total_phases: 9
  completed_phases: 8
  total_plans: 120
  completed_plans: 119
  percent: 91
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-03)

**Core value:** Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically.
**Current focus:** Phase 09 — Polish (next; Phase 8 closed PENDING_MANUAL_SMOKE for 4-item operator walkthrough A-D per 08-PHASE-VERIFICATION.md)

## Current Position

Phase: 09 (Polish) — IN FLIGHT (Wave 8 — SC-5 accessibility delivery underway)
Plan: 09-10 COMPLETE (Task 1 GREEN: global *:focus-visible CSS + button/a/[role=button] color-mix outer ring; .github/workflows/a11y.yml axe-core CI workflow @^4.11.3 with 7-URL public route matrix + Pitfall 11 admin-route exclusion + T-09-10-01 upgraded to mitigate via if:failure() artifact upload; PublicPagesHtmlLangTest Wave 0 → 7 GREEN tests; VueFormLabelsTest Wave 0 → 1 GREEN static-scan test, 0 violations; SkipToContent + main#main verified intact. Task 2 PENDING_MANUAL_SMOKE — 10-step keyboard-nav checklist deferred to operator out-of-band per autonomous workflow convention. Filter run 8 passed / 15 assertions / 2.08 s; Pint 0 dirty files; PHPStan L8 OK); next 09-11
Status: Phase 9 in flight
Last activity: 2026-05-15

Progress: [██████████] 99% (8/9 phases; 119/120 plans incl. Phase 9 09-01..09-10)

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
| Phase 02-clans-tags P07 | 12min | 2 tasks | 16 files |
| Phase 02-clans-tags P08 | 273s | 3 tasks | 8 files |
| Phase 02-clans-tags P09 | 468s | 3 tasks | 14 files |
| Phase 02-clans-tags P10 | 253s | 2 tasks | 9 files |
| Phase 02-clans-tags P11 | 720 | 3 tasks | 11 files |
| Phase 02-clans-tags P12 | 9 | 3 tasks | 14 files |
| Phase 02-clans-tags P13 | 270 | 3 tasks | 14 files |
| Phase 02-clans-tags P14 | ~25min | 3 tasks | 9 files |
| Phase 03 P02 | 12min | 2 tasks | 4 files |
| Phase 03 P03 | 263 | 3 tasks | 12 files |
| Phase 03-games-match-types P04 | 300 | 2 tasks | 8 files |
| Phase 03 P05 | 4 minutes | 2 tasks | 3 files |
| Phase 03-games-match-types P06 | 202s | 2 tasks tasks | 7 files files |
| Phase 03 P07 | 305 | 3 tasks | 6 files |
| Phase 03 P08 | 418 | 1 tasks | 1 files |
| Phase 03 P09 | 1200 | 2 tasks | 1 files |
| Phase 03 P10 | 304s | 2 tasks tasks | 2 files files |
| Phase 04 P01 | 320s | - tasks | - files |
| Phase 04 P02 | 3min | 2 tasks | 6 files |
| Phase 04 P03 | 12 | 3 tasks | 20 files |
| Phase 04 P04 | 5min | 1 task tasks | 3 files files |
| Phase 04 P05 | 4min | 1 task tasks | 2 files files |
| Phase 04 P06 | 9 | 3 tasks | 7 files |
| Phase 04 P07 | 18m | 3 tasks | 14 files |
| Phase 04 P08 | 11min | 2 tasks | 6 files |
| Phase 04 P09 | 12 | 3 tasks | 17 files |
| Phase 04-matches-manual P10 | 9 | 2 tasks | 10 files |
| Phase 04 P11 | 11 | 3 tasks | 12 files |
| Phase 04 P12 | 11 | 3 tasks | 3 files |
| Phase 04 P13 | 339s | 1 tasks | 5 files |
| Phase 05 P01 | ~15min | 2 tasks (+ 1 Rule 1 follow-up) | 39 files |
| Phase 05-discord-bot-v1 P02 | 405 | - tasks | - files |
| Phase 05 P03 | 428 | 2 tasks | 6 files |
| Phase 05 P04 | 828 | 3 tasks | 17 files |
| Phase 05 P05 | 383 | 3 tasks | 3 files |
| Phase 05 P06 | 327 | 2 tasks | 5 files |
| Phase 05 P07 | 407 | - tasks | - files |
| Phase 05 P08 | 366 | 3 tasks | 9 files |
| Phase 05 P09 | 353 | 3 tasks | 12 files |
| Phase 05 P10 | 472 | 3 tasks | 10 files |
| Phase 05 P11 | 321 | 3 tasks | 7 files |
| Phase 05 P12 | 322 | 3 tasks | 5 files |
| Phase 05-discord-bot-v1 P13 | 408s | 2 tasks | 5 files |
| Phase 06 P01 | 292s | 2 tasks | 38 files |
| Phase 06 P02 | 4m 06s | 2 tasks | 5 files |
| Phase 06 P03 | 30m | 2 tasks | 16 files |
| Phase 06 P04 | ~3min | 1 tasks | 3 files |
| Phase 06 P05 | ~3m | 1 tasks | 4 files |
| Phase 06 P06 | ~12m | 2 tasks | 10 files |
| Phase 06 P07 | ~9m | 3 tasks | 9 files |
| Phase 06 P08 | ~15m | 2 tasks | 10 files |
| Phase 06 P09 | ~7m | 2 tasks | 7 files |
| Phase 06 P10 | 16m | 2 tasks | 21 files |
| Phase 06 P11 | 20m | 2 tasks | 16 files |
| Phase 06 P12 | 40min | - tasks | - files |
| Phase 06 P13 | ~7min | 2 tasks | 6 files |
| Phase 06 P14 | 462s | 2 tasks | 5 files |
| Phase 07 P01 | 8m 35s | 2 tasks | 32 files |
| Phase 07 P02 | 7m 14s | 2 tasks | 6 files |
| Phase 07 P03 | 11m 58s | 2 tasks | 12 files |
| Phase 07 P04 | 3m 49s | 2 tasks | 7 files |
| Phase 07 P05 | 13m 37s | 2 tasks | 15 files |
| Phase 07 P06 | 9m 2s | 2 tasks | 10 files |
| Phase 07-cms P07 | 11m 19s | 2 tasks | 5 files |
| Phase 07 P08 | 9m 24s | 2 tasks | 9 files |
| Phase 07 P09 | 11m 22s | 2 tasks | 13 files |
| Phase 07 P10 | 18m 04s | 2 tasks | - files |
| Phase 07-cms P11 | 5min | 2 tasks | 5 files |
| Phase 07 P12 | 12min | 2 tasks | 14 files |
| Phase 07 P13 | ~8min | 2 tasks | 5 files |
| Phase 08 P01 | 11min | 2 tasks | 25 files |
| Phase 08-rcon-automation P02 | 10min | 2 tasks | 6 files |
| Phase 08 P03 | 8min | 2 tasks | 10 files |
| Phase 08-rcon-automation P04 | 7min | 2 tasks | 7 files |
| Phase 08 P05 | 13m05s | 2 tasks | 5 files |
| Phase 08 P06 | 6min | 2 tasks | 11 files |
| Phase 08 P07 | 9min | 2 tasks | 6 files |
| Phase 08 P09 | 12min | 2 tasks | 14 files |
| Phase 08-rcon-automation P10 | 9 | 2 tasks | 8 files |
| Phase 08 P11 | 10min | 2 tasks | 11 files |
| Phase 08 P12 | 18min | - tasks | - files |
| Phase 08 P13 | ~18min | 2 tasks | 4 files |
| Phase 09 P01 | ~8min | 1 task tasks | 39 files files |
| Phase 09 P02 | ~6min | 2 tasks | 7 files |
| Phase 09 P03 | ~12min | 2 tasks | 19 files |
| Phase 09 P04 | 573 | 2 tasks | 12 files |
| Phase 09 P05 | 757 | 2 tasks | 13 files |
| Phase 09-polish P06 | 1251 | 2 tasks | 19 files |
| Phase 09-polish P08 | 2395 | 2 tasks | 14 files |
| Phase 09-polish P09 | 1066 | 2 tasks | 12 files |
| Phase 09-polish P10 | 780 | 1 task (Task 2 PENDING_MANUAL_SMOKE) | 4 files |

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
- [Phase ?]: Plan 02-10: ClanInviteService stateless; accept() uses DB::transaction; Clan::invites() HasMany added (Rule 2)
- [Phase 02]: Plan 02-14: ClanMembershipUniqueTest covers D-009 at the integration layer (service guard + DB partial-unique defence-in-depth + history-preserved + migrate:fresh durability)
- [Phase 02]: Plan 02-14: vue-tsc errors fixed — ambient namespace imports (api.d.ts is not a module), index-signature cast on page.props.auth, unused imports
- [Phase 02]: Plan 02-14: shared-types/src/index.ts updated with Phase 2 export type aliases (ClanData, ClanTagData, ClanMembershipData, ClanInviteData, ClanApplicationData, PublicPlayerData)
- [Phase 02]: Plan 02-14: Phase 2 COMPLETE — 214 tests, all quality gates green, PHASE-VERIFICATION.md written, ROADMAP.md updated
- [Phase ?]: 03-02: Composite UNIQUE on game_match_type_role_limits uses short name gmtrl_match_type_role_unique to fit Postgres 63-byte identifier limit (Pitfall 1)
- [Phase ?]: 03-02: Cross-game invariant for RoleLimit (matchType.game_id === role.game_id) deferred to model saving() listener in plan 03-03 — DB CHECK cannot reference another table cheaply (Pitfall 10 / Assumption A6)
- [Phase ?]: 03-02: cascadeOnDelete on both game_match_type_role_limits FKs (matchType + role) — RoleLimits are configuration rows, not historical; revisit if Phase 4 wires signed-up slots to RoleLimit (Assumption A3 / Pitfall 7)
- [Phase ?]: Plan 03-03: GameMatchTypeRoleLimit::booted() saving() listener throws DomainException on cross-game pair — only programmatic guard for Pitfall 10 (Postgres cross-table CHECK ruled out by Assumption A6); pairs with Filament Select scoping in plan 03-07.
- [Phase ?]: Plan 03-03: factory key generation via regexify('[a-z0-9_]{4,12}') instead of Str::slug() — slug emits hyphens which fail the DB ^[a-z0-9_]+$ CHECK.
- [Phase ?]: Plan 03-03: HasTranslations mutator coerces $model->description = null into JSONB {"en": null}, not SQL NULL — GameMatchTypeModelTest NULL-description assertion uses DB::table()->insert() to bypass the mutator and prove the column's nullable contract.
- [Phase ?]: Phase 3 DTOs follow the Phase 2 ClanData/ClanTagData pattern verbatim: #[TypeScript] + fromModel factory using getTranslations() for translatable JSONB columns (Pitfall 4 mitigation). Nested DTO hydration is eager-load aware via relationLoaded() checks.
- [Phase ?]: shared-types local typecheck via apps/web tsc on /repo/packages/shared-types/src files because web container does not have the full pnpm workspace mounted (only /repo/packages/shared-types is mounted). CI runs pnpm --filter @trenchwars/shared-types typecheck (plan 01-16) as the canonical gate.
- [Phase ?]: 03-05: HLL preset seeded via GameSeeder using firstOrCreate idiom per UNIQUE index (Pattern 5); admin edits to translatable name/display_name/capacity survive re-seeds because [other_attrs] fires on create only — D-007 runtime contract
- [Phase ?]: 03-05: Friendly/Tournament/Clan War match types seeded with ZERO RoleLimit rows (admin fills via Filament per RESEARCH Q2 RESOLVED Recommendation B); only Scrim 50v50 (15 rows, 50 slots) and Skirmish 6v6 (5 rows, 6 slots) get pre-seeded capacity matrices
- [Phase ?]: 03-05: Canonical 100-tier HLL role key is heavy_machine_gunner / Heavy Machine Gunner (spawn-prompt explicit instruction supersedes the older machine_gunner spec from the plan acceptance criterion — Rule 3 blocking-issue resolution)
- [Phase ?]: Phase 3 Plan 03-06: GameResource ships Tabs(Profile+Audit) + 2 RelationManagers (Roles, MatchTypes). No DeleteAction on table (admin uses is_active toggle, Open Question Q4); key field disabledOn('edit') to protect seeder idempotency (T-03-06-03).
- [Phase ?]: Plan 03-06 Rule 1: Filament v3 RelationManager::getTitle() is STATIC — instance signature triggers fatal error at panel registration. Corrected during phpstan loop on task 2.
- [Phase ?]: Plan 03-06: Pattern 2 click-through (MatchTypesRelationManager EditAction -> GameMatchTypeResource::edit URL) DEFERRED to plan 03-07 task 3 Rule-2 amendment; ships default modal-based EditAction in this plan since GameMatchTypeResource lives in wave 5.
- [Phase ?]: GameMatchTypeResource ships List/Create/Edit only — NO View page (ClanTagResource precedent; keeps the second-tier resource tight)
- [Phase ?]: RoleLimitsRelationManager Select option labels read raw JSONB via getAttributes() + json_decode to bypass HasTranslations string accessor for PHPStan L8 compatibility (functionally identical)
- [Phase ?]: Pattern 3 cross-game UI Select scoping + model saving() listener (plan 03-03) together satisfy Pitfall 10 defense-in-depth; both gates required
- [Phase ?]: 03-08: Pitfall 3 detection switched from HTTP assertSee to Livewire::test + assertCanSeeTableRecords (Filament v3 RM tables are x-intersect lazy-loaded; HTTP-fallback does not detect RM typo on Phase 3)
- [Phase ?]: 03-08: Filament v3.3 setCurrentPanel() accepts Panel object not string — Filament::setCurrentPanel(Filament::getPanel('admin')) is the v3.3 idiom; v4 docs show string form
- [Phase ?]: Phase 3 closed cleanly: 278 tests / 822 assertions, all 5 quality gates green (Pest, Pint, PHPStan L8, vue-tsc, shared-types); 64 Phase 3 tests / 138 assertions cover REQ-platform-vision + SC-1..SC-4; status PENDING_MANUAL_SMOKE pending operator 6-item walkthrough A-F
- [Phase ?]: REQUIREMENTS.md REQ-platform-vision was already Complete (lines 14 + 116) from prior session; only ROADMAP.md required 3 surgical edits in plan 03-10
- [Phase ?]: shared-types typecheck runs host-side via corepack pnpm (canonical CI command per plan 01-16); web container does not have full pnpm workspace mounted
- [Phase ?]: [Phase 04]: 04-01 Wave 0 — reused Phase 3 commit 1d4d736 idiom (string FQN $model + @phpstan-ignore-next-line on missingType.generics + property.defaultValue) for the 6 factory stubs; CLAUDE.md §3 forbids regenerating phpstan-baseline.neon so per-line ignores are the only path. Plan 04-03 MUST remove the ignores when real model classes land.
- [Phase ?]: [Phase 04]: 04-01 — Match model FQN is the singular App\Models\Match (Pitfall 5 / Assumption A4 — legal PHP 8 class identifier despite the lowercase match keyword); factory file is MatchFactory.php.
- [Phase ?]: [Phase 04]: 04-01 — pcntl extension verified PRESENT in trenchwars-web container; plan 04-06 SC-2 concurrency test can use pcntl_fork() (Pitfall 4 primary path); the dual-DB-connection fallback is unnecessary on this image.
- [Phase ?]: [Phase 04]: 04-02 Wave 1 — events table polymorphic, no FK by design (Pattern 8); MatchObserver + events_one_per_owner composite UNIQUE are the integrity pair
- [Phase ?]: [Phase 04]: 04-02 — match_slots_one_occupancy_per_user partial UNIQUE follows verbatim Phase 2 D-009 idiom (raw DB::statement CREATE UNIQUE INDEX ... WHERE occupant_user_id IS NOT NULL); Schema::unique() cannot express WHERE (Pitfall 1)
- [Phase ?]: [Phase 04]: 04-02 — Phase 4 cascade matrix follows RESEARCH Pattern 1: matches cascade to slots/rules/results, results cascade to mvps; user/player/role/tag restrict; host_clan/winner_clan/occupant_user nullOnDelete
- [Phase ?]: D-04-03-A: GameMatch class name (not Match — PHP 8.4 reserved keyword); table stays matches via protected $table override. Binding for plans 04-04..04-13. Supersedes D-04-01-B.
- [Phase ?]: D-04-03-B: All BelongsTo<GameMatch, $this> pass match_id as explicit FK arg (Laravel cannot infer from match() method name when related class is GameMatch).
- [Phase ?]: D-04-03-C: SoftDelete-aware FK cascade tests use forceDelete() to fire DB-level cascade.
- [Phase ?]: [Phase 04]: D-04-04-A — MatchStatusService captures $from BEFORE update(); prevents getOriginal() drift
- [Phase ?]: [Phase 04]: D-04-04-B — MatchNotOpenException extends \DomainException; defined in plan 04-04 so plan 04-06 MatchSignupService imports without circular dependency
- [Phase ?]: [Phase 04]: D-04-04-C — MatchStatusService + tests do not use Pitfall 5 alias-on-import (no match() expressions); direct 'use App\Models\GameMatch;' is canonical Phase 4 idiom per D-04-03-A
- [Phase ?]: [Phase 04]: D-04-05-A — MatchSlot snapshot semantics locked; slot.game_role_id FKs to game_roles (not RoleLimit), slot.sort_order is value snapshot. RoleLimit edits do NOT retroactively rewrite open match_slots
- [Phase ?]: [Phase 04]: D-04-05-B — MatchSlotMaterialiserService uses direct 'use App\Models\GameMatch;' (no Pitfall 5 alias); canonical Phase 4 idiom per D-04-04-C
- [Phase ?]: [Phase 04]: D-04-05-C — PHPStan L8 null-guard on $match->gameMatchType (BelongsTo nullable in PHPStan view); pathological-null returns 0 (semantically equivalent to empty roleLimits)
- [Phase ?]: D-04-06: 5-guard order, parent-row lock, empty rules = open, GameMatch direct import, pcntl_fork concurrency test
- [Phase ?]: D-04-07-A: PublicMatchOccupantData::empty() renamed to forEmptySlot() — Spatie Data::empty() collision.
- [Phase ?]: D-04-07-B: Carbon @var PHPDoc narrowing for Eloquent datetime casts in DTO factories.
- [Phase ?]: D-04-07-C: Direct use App\Models\GameMatch (no Pitfall 5 alias) — D-04-03-A + D-04-06-D canonical idiom.
- [Phase ?]: D-04-07-D: camelCase for Public* DTOs (PublicMatchOccupantData), snake_case for internal (MatchData, EventData).
- [Phase ?]: D-04-07-E: PublicMatchOccupantData::fromMatchSlot uses direct queries (not eager-load) — self-contained factory.
- [Phase ?]: D-04-07-F: Translatable JSONB getTranslations() ?: null is canonical Phase 3 Pitfall 4 pattern reused verbatim.
- [Phase 04]: D-04-08-A: No `Match as MatchModel` alias in MatchObserver — use GameMatch directly (D-04-03-A LOCKED + D-04-07-C continuation).
- [Phase 04]: D-04-08-B: Model-level `booted()` registration ONLY — no AppServiceProvider fallback added; observer fires reliably under test.
- [Phase 04]: D-04-08-C: Segregate observer-driven Event from manual Event::factory() by setting is_public=false on the underlying GameMatch — canonical ripple-fix pattern for tests that touch the events table.
- [Phase ?]: D-04-09-A: MvpsRelationManager uses HasManyThrough on GameMatch::mvps() — chosen over getEloquentQuery override and standalone resource because Filament v3 natively supports HasManyThrough in RelationManagers (Context7 docs confirmed)
- [Phase ?]: D-04-09-B: EditMatch HeaderActions for status transitions (Lock signups, Cancel match, Open signups) — visible predicates hide actions outside the state machine; each calls MatchStatusService::transition
- [Phase ?]: D-04-09-C: MatchResultService::upsert terminal-state SKIP — wraps the MatchStatusService::transition call in 'if status !== played' to support re-edits to the result without re-firing the transition (Pattern 4 terminal rule)
- [Phase ?]: D-04-09-D: Container-bind stub for final services — replaces Mockery/anonymous-class-extension for testing final services; reusable for plan 04-10+ tests
- [Phase ?]: D-04-10-A: 4-exception catch order in MatchSignupController::store ends with CapacityExceededException routed to game_role_id; the other three route to general. PHP catches the FIRST exception thrown; order mirrors the service guard order (status > tag > idempotency > capacity).
- [Phase ?]: D-04-10-D: Inertia ->component(name, false) skips Vue page existence check in tests — Vue pages land in plan 04-11.
- [Phase 04]: D-04-11-A: Privacy branches use !== null (matching generated TS nullable contract for PublicMatchOccupantData.displayName)
- [Phase 04]: D-04-11-B: MatchStatusBadge is a standalone Phase 4 composite, not a StatusBadge wrap
- [Phase 04]: D-04-11-C: No dayjs runtime dep — native Intl.DateTimeFormat
- [Phase 04]: D-04-11-D: TextInput Rule 2 — type prop accepts 'date'
- [Phase 04]: D-04-11-E: Templates avoid > comparison literals — refactored into computed booleans (NoHardcodedStringsTest regex constraint)
- [Phase ?]: D-04-12-A: LogsActivity does not populate properties.attributes in this project — explicit activity()->withProperties() is the only path to a populated properties JSON
- [Phase ?]: D-04-12-B: MatchResourcePresentTest upgraded from 18 smoke (04-09) to 25 comprehensive blocks via assertCanSeeTableRecords on all 4 RelationManagers (Phase 3 Pitfall 3 idiom)
- [Phase ?]: D-04-12-C: Same-game fixtures mandatory for RelationManager tests that depend on materialiser invariant (MatchSlot factory default is cross-game)
- [Phase ?]: [Phase 04]: Plan 04-13 — Phase 4 COMPLETE; 493 tests / 1459 assertions / 0 incomplete; all 5 quality gates GREEN; 04-PHASE-VERIFICATION.md written mapping SC-1..SC-5 + REQ-goal-match-workflows; ROADMAP 13/13 Complete; REQUIREMENTS REQ-goal-match-workflows -> Complete; status PENDING_MANUAL_SMOKE pending operator 5-item walkthrough A-E
- [Phase ?]: [Phase 04]: D-04-03-A LOCKED canonical class binding for Phase 5+: App\\Models\\GameMatch (match is PHP 8.x reserved keyword); table stays 'matches' via $table override; no Match aliases anywhere; direct use App\\Models\\GameMatch; is canonical idiom
- [Phase 05]: D-05-01-A — laravel/sanctum v4.3.2 (latest 4.x line; Laravel 12 compatible); installed via container composer per D-021; PAT migration applied via install:api
- [Phase 05]: D-05-01-B — laravel/horizon v5.46.0 (latest 5.x line); HorizonServiceProvider auto-registered in bootstrap/providers.php; QUEUE_CONNECTION=redis (Phase 1 default) drives Horizon
- [Phase 05]: D-05-01-C — Wave 0 Pest stub idiom is the canonical Phase 4 commit 8435020 bare form (no namespace, no per-file uses() call). Plan <interfaces> sample with namespace + uses(TestCase::class, RefreshDatabase::class) triggered TestRepository fatal because apps/web/tests/Pest.php autowires both via uses(...)->in(...). Rule 3 deviation documented in 05-01-SUMMARY.md
- [Phase 05]: D-05-01-D — apps/bot/pnpm-lock.yaml does NOT exist (workspace lockfile is at repo root per D-015 pnpm-workspaces). Plan's files_modified entry was wrong; root pnpm-lock.yaml is the canonical commit target
- [Phase 05]: D-05-01-E — Worker docker healthcheck uses tr+grep on /proc/1/cmdline (busybox-portable) instead of pgrep (unavailable in php-fpm Alpine image); functional outcome identical
- [Phase 05]: D-05-01-F — SANCTUM_STATEFUL_DOMAINS explicitly empty in .env.example as defence-in-depth on top of framework default; Pitfall 4 verified clean (no bot/web-nginx hostnames in sanctum.stateful)
- [Phase ?]: D-05-02-A: clans.discord_announce_channel_id already shipped by Phase 2 — the second migration prescribed by 05-02 was omitted (would have duplicated existing column).
- [Phase ?]: D-05-02-B: spatie/laravel-activitylog LogOptions uses dontLogEmptyChanges() — plan's dontSubmitEmptyLogs() does not exist in installed version.
- [Phase ?]: D-05-02-C: spatie/laravel-activitylog v4+ stores attribute diffs in attribute_changes (collection) column, NOT in properties. Tests must read attribute_changes.
- [Phase ?]: D-05-03-A: Auth::onceUsingId not callable on Sanctum RequestGuard — middleware uses Auth::setUser + Auth::guard('web')->setUser instead (defence-in-depth for LogsActivity causer chain)
- [Phase ?]: D-05-03-B: Plan 05-01 personal_access_tokens migration used bigint morphs — incompatible with users.uuid PKs; migration in-place edited to uuidMorphs (safe pre-prod-deploy)
- [Phase ?]: D-05-03-C: Laravel/Sanctum/HasApiTokens trait added to App/Models/User as Rule 2 amendment — missing from plan 05-01 install:api scaffold
- [Phase ?]: D-05-03-D: Pitfall 7 wire contract — middleware tolerates missing X-Bot-Acts-As-User header (200 pass-through with token-owner identity); plan 05-04 controllers add the per-route 422 enforcement when a human causer is mandatory
- [Phase ?]: D-05-04-A: MatchSignupService is final — replaced container-bind stub with non-Mockery D-004 reuse proof (occupant_user_id + confirmed_at + activity_log post-conditions)
- [Phase ?]: D-05-04-B/C: Pitfall 7 pass-through contract documented in BotApiMatchSignupAbilitiesTest case 2 + BotApiUserMeTest case 3 — missing X-Bot-Acts-As-User leaves auth as bot service user (controller-side 422 tightening deferred)
- [Phase ?]: D-05-04-D: Concurrent outbound claim test uses sequential calls (not pcntl_fork) — second call sees status=dispatching via dispatchable scope filter; pcntl_fork already exercised at the service layer in Phase 4 plan 04-06
- [Phase ?]: D-05-05-A: cancelled match -> EDIT (match_announce_update with prior_sent_message_id) not DELETE; preserves audit trail
- [Phase ?]: D-05-05-B: MatchObserver keeps Phase 4 saved() hook + adds independent created()/updated() hooks; regressionless (MatchEventSyncTest 8/8 GREEN)
- [Phase ?]: D-05-05-C: causer_user_id via auth()->id() - null in CLI/seeder (T-05-05-03 accept); both flows tested explicitly
- [Phase ?]: D-05-05-D: phpstan.neon paths exclude tests/ - test-file payload['key'] phpstan issues are outside the CI gate; existing DiscordOutboundMessageModelTest.php follows same pattern
- [Phase ?]: D-05-05-E: DiscordOutboundPayloadBuilder eager-loads gameMatchType+hostClan+slots.role - no-op when already loaded; prevents N+1 inside observer save() transaction
- [Phase ?]: D-05-06-A: role_sync payload keys are discord_user_id/discord_role_id (matches plan 05-04 echo suppression JSONB path lookup); plan <interfaces> wording superseded.
- [Phase ?]: D-05-06-F: ClanMembership hard-delete fires SyncDiscordRolesJob with action=remove (defensive — D-009 expects left_at but seeders/admin may bypass).
- [Phase ?]: D-05-07-A: ClanResource discord_announce_channel_id was already shipped in Phase 2 plan 02-12; 05-07 was additive (helperText + maxLength) preserving the T-02-09-02 toggle gate
- [Phase ?]: D-05-07-B: Bot service user firstOrCreate must include locale='en' — users.locale is NOT NULL with no DB default (Rule 2 auto-add)
- [Phase ?]: D-05-07-C: PHPStan scope excludes tests/ per phpstan.neon paths (app, bootstrap/app.php, database, routes); test files emit findings on explicit analysis but stay out of CI scope
- [Phase ?]: D-05-08-A: ESM import extensions explicit (.js) on local imports — Node 22 + module=NodeNext
- [Phase ?]: D-05-08-B: customIds decodeButtonId enforces structural validity only; UUID validation is caller's job
- [Phase ?]: D-05-08-C: api.ts hard-codes /api/bot prefix in request() — cleaner than passing prefix every call
- [Phase ?]: D-05-09-A: /profile v1 redirect-to-web stub; viewer-aware endpoint deferred to plan 05-12
- [Phase ?]: D-05-09-B: /clan apply v1 redirect-to-web stub; live api.post deferred to Phase 6+
- [Phase ?]: D-05-09-C: Modal customId reuses encodeButtonId from plan 05-08 (m:o:<matchId>) — single-sourced round-trippable scheme
- [Phase ?]: D-05-09-D: registerCommands().catch() in ready.ts logs but does NOT process.exit(1); bot stays alive on registration failure (T-05-09-06)
- [Phase ?]: D-05-10-A: matchCard renders only scalar PublicMatchData fields (DTO has no nested relations)
- [Phase ?]: D-05-10-B: encodeButtonId('m:o:<matchId>') reused on slash command + button sides — single round-trippable customId
- [Phase ?]: D-05-10-C: buildSignupModal exported from components/signupModal.ts (submit handler module) — locality of cohesion
- [Phase ?]: D-05-10-D: translateError substring-matches err.message; structured JSON parse deferred to plan 05-12
- [Phase ?]: D-05-10-E: interactionCreate button branch peeks customId 'm:o:' prefix to skip pre-defer (Pitfall 1 corollary)
- [Phase ?]: D-05-10-F: matchCard defensive guard on m.game_match_type_id (typeof + non-empty) to handle partial mocks
- [Phase ?]: D-05-11-D: render.ts re-fetches PublicMatchData on every match_announce dispatch (vs reading row.payload snapshot)
- [Phase ?]: D-05-11-F: payload key naming asymmetry: outbound uses discord_user_id/discord_role_id; inbound /discord-events/role-change body uses user_discord_id/role_discord_id
- [Phase ?]: D-05-11-G: handleGuildMemberUpdate exported as a public helper from registerGuildMemberUpdateHandler for direct unit testing without mock Client
- [Phase ?]: [Phase 05]: D-05-12-A — BotI18nKeyCoverageTest follows functional Pest convention (no namespace); diverges from plan <interfaces> namespace example for consistency with 100+ existing test files
- [Phase ?]: [Phase 05]: D-05-12-B — BotI18nKeyCoverageTest surfaced 1 i18n gap (admin.discord_outbound_message.fields.causer); closed inline (Rule 2 — D-013 enforcement)
- [Phase ?]: [Phase 05]: D-05-12-C — DiscordOutboundAuditLogTest hits real /api/bot/outbound-messages endpoints (not direct $row->update) — catches future controllers that swap to raw DB::update (T-05-12-04)
- [Phase ?]: [Phase 05]: D-05-12-E — SC-5 capstone ships 3 it() blocks: happy path with ->not->toBe($bot->id) defence; 422 negative; missing acts-as header documents Pitfall 7 tolerance via contrast
- [Phase ?]: [Phase 05]: Plan 05-13 — Phase 5 COMPLETE; 618 web Pest tests / 1817 assertions + 117 bot Vitest tests; all 6 quality gates GREEN (Pest, Vitest, Pint, PHPStan L8, bot tsc strict, shared-types tsc, vue-tsc); 05-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-goal-discord-ux + 12 pitfalls + 5 open questions; ROADMAP 13/13 Complete; status PENDING_MANUAL_SMOKE pending operator 6-item walkthrough A-F
- [Phase ?]: [Phase 05]: D-04-03-A LOCKED continued — App\\Models\\GameMatch direct import everywhere in Phase 5 (controllers, observers, services, jobs, FormRequests, tests); zero alias-on-import; canonical binding for Phase 6+ tournaments — bracket-match materialisation MUST import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B
- [Phase ?]: [Phase 06-01]: Wave 0 factory stubs adopt Phase 3 commit 1d4d736 idiom verbatim — string FQN $model + per-line @phpstan-ignore (missingType.generics + property.defaultValue). Plan 06-03 will replace with typed-generic factories once Tournament/TournamentParticipant/TournamentStage/TournamentBracket/TournamentStanding models exist.
- [Phase ?]: [Phase 06-01]: Wave 0 Pest stubs use bare functional convention (Phase 5 D-05-01-C canonical idiom) — no namespace, no per-file uses() call. Pest.php autowires TestCase + RefreshDatabase via uses(...)->in('Feature'); a per-file uses(TestCase::class, RefreshDatabase::class) was found to trigger TestRepository fatals in Phase 5.
- [Phase ?]: [Phase 06-01]: Phase 6 i18n namespace pre-shipped in full (apps/web/lang/en/tournaments.php, 90+ leaf keys: formats × 4, status × 6, participant_status × 4, errors × 8, actions × 9 × 4 = 36, tabs × 5, empty × 3, stage_types × 6) rather than incremental per-plan. Prevents NoHardcodedStringsTest + MissingTranslationException mid-execution across plans 06-02..06-13.
- [Phase ?]: D-06-02-A: Self-FKs on tournament_brackets deferred to a separate Schema::table() block to avoid Laravel ADD PRIMARY KEY ordering quirk
- [Phase ?]: D-06-02-B: tournament_standings UNIQUE composite is (stage_id, participant_id) not (tournament_id, participant_id) — round-robin stages carry distinct standings per participant
- [Phase ?]: D-06-02-C: no-self-advance CHECK covers both advance pointers in a single CHECK; NULL != id allowed (NULL not FALSE in Postgres) so un-materialised brackets coexist
- [Phase ?]: D-06-03-A: TournamentBracket::match() uses explicit FK arg 'match_id' (D-04-03-B) — auto-inferred 'game_match_id' doesn't exist on tournament_brackets; same rule for advancesTo/loserAdvancesTo self-FK args
- [Phase ?]: D-06-03-B: Phase 6 models use Spatie\Activitylog\Models\Concerns\LogsActivity (canonical v5 path), not the older Traits path referenced in the plan's <interfaces> sample
- [Phase ?]: D-06-03-C: getActivitylogOptions() across all 5 Phase 6 models includes dontLogIfAttributesChangedOnly(['updated_at']) so timestamp-only touches don't pollute activity_log
- [Phase ?]: D-06-04-A: TournamentStatusService::transition() signature uses ?User $causer = null + Tournament return type — diverges from Phase 4 MatchStatusService (required User + void) to enable Filament admin actions to omit causer arg and fluent chaining.
- [Phase ?]: D-06-04-B: BracketsAlreadyGeneratedException ships in plan 06-04 (not 06-06) to break circular dependency — plan 06-06 BracketGeneratorService imports it from here.
- [Phase ?]: D-06-04-C: Activity log description uses format-string 'Tournament status: {from} -> {to}' (more descriptive than Phase 4 static 'Match status transition') for visual scan-ability in Filament audit log.
- [Phase ?]: D-06-05-A: Open Question A4 RESOLVED — Tournament::canReseed() returns true ONLY when status='seeded' AND no MatchResult rows exist for any bracket-linked match
- [Phase ?]: D-06-05-B: by_rank v1 uses tournament_participants.created_at desc as deterministic proxy for skill rank (RESEARCH Assumption A11; Phase 9 ELO upgrade tracked)
- [Phase ?]: D-06-05-C: PHP match dispatch on strategy gets explicit default => throw new InvalidArgumentException arm — satisfies PHPStan L8 match.unhandled + clear runtime error for typo callers
- [Phase ?]: D-06-05-D: reseed() audit-log previous_seeds + new_seeds maps keyed by clan_id (stable cross-reseed identity; only the seed column is rewritten)
- [Phase ?]: D-06-06-A: BracketGeneratorService ships all 4 strategies; 3 are stubs (DoubleElim/RoundRobin/Swiss) — plan 06-07 only replaces stub bodies, not constructor signature.
- [Phase ?]: D-06-06-B: INNER_OUTER_ORDERINGS hardcodes sizes 2/4/8/16/32; sizes > 32 use recursive computeInnerOuter() validated against the hardcoded 32-element case.
- [Phase ?]: D-06-06-C: GameMatch ships single scheduled_at column (not scheduled_start_at + scheduled_end_at as plan scaffold suggested); aligned with actual Phase 4 migration.
- [Phase ?]: D-06-06-D: A10 LOCKED — bracket-spawned GameMatch.host_clan_id = NULL; both participants are guests.
- [Phase ?]: D-06-06-E: BracketMatchMaterialiserService throws RuntimeException (not DomainException) when default_game_match_type_id is null.
- [Phase ?]: D-06-06-F: Bracket GameMatch.title inherits tournament.getTranslations('title') — JSONB locales map (D-013).
- [Phase ?]: D-06-06-G: Bye-winner round-2 slot rule — odd round-1 position p → participant_a_id; even p → participant_b_id.
- [Phase ?]: D-06-07-A: SingleEliminationGenerator refactor — extracted layoutInStage() public static helper for DoubleEliminationGenerator W-bracket reuse
- [Phase ?]: D-06-07-B: Burton L-bracket N=8 hardcoded loser-drop mapping verified vs brackets-manager.js
- [Phase ?]: D-06-07-C: Pitfall 5 narrows v1 swiss tournaments to powers of 2 (N must equal 2^ceil(log2(N)))
- [Phase ?]: Open Question A6 RESOLVED LOCKED inline — Swiss admin-click next-round via SwissGenerator::generateNextRound() (Filament wiring in plan 06-11)
- [Phase ?]: D-06-08-A: Two-hook MatchResultObserver pattern (created + updated, NOT saved) — saved cannot distinguish create from touch on the pinned Laravel version since wasRecentlyCreated stays true forever on the same instance
- [Phase ?]: D-06-08-D: Phase 5 discord_outbound_messages.message_type CHECK extended via migration 2026_05_15_100500 to allow bracket_result_announce (Postgres drop+recreate idiom)
- [Phase ?]: D-06-08-G: StandingsCalculatorService ships as no-op stub (replaced by plan 06-09); resolved via app() lookup at BracketAdvancementService::advance() call site to break circular DI cycle (T-06-08-07)
- [Phase ?]: D-06-09-A: TournamentStage::brackets() default ordering requires ->reorder() before ->orderByDesc('round_number') to escape the relationship's default ASC sort
- [Phase ?]: D-06-09-B: Wipe-and-recompute strategy for standings (small table) inside DB::transaction; rolls back atomically on failure
- [Phase ?]: D-06-09-F: Round-robin default points scheme is FIFA 3/1/0; admin override via tournament.settings.roundrobin_points_per_{win,draw,loss}
- [Phase ?]: D-06-09-H: Swiss tiebreaker is plain Buchholz only; median Buchholz variant deferred to Phase 9
- [Phase ?]: D-06-10-A: PublicTournamentData composes BracketNodeData + BracketEdgeData inline; Vue SVG renderer (plan 06-12) receives one DTO + renders entire bracket tree without further API calls; ETag short-circuits unchanged JSON polling responses.
- [Phase ?]: D-06-10-B: Etag = sha1(tournament.updated_at | sorted bracket id:updated_at). Deterministic across identical-state calls; standings excluded from v1 etag input (Phase 9 polish).
- [Phase ?]: D-06-10-C: BracketNodeData 4-state ladder bye check FIRST. Single-elim generators auto-set winner_participant_id = participantA for byes without materialising a match_id; naive completed-first ordering mis-classifies byes.
- [Phase ?]: D-06-10-E: TournamentObserver outbound channel_id = '' (empty string), not null. discord_outbound_messages.channel_id is text NOT NULL; bot worker resolves the channel at dispatch time. Matches BracketAdvancementService convention (plan 06-08).
- [Phase ?]: D-06-10-F: tournament_announce + tournament_announce_update added to doutmsg_message_type_chk via migration 2026_05_15_100600. Same drop+add pattern as plan 06-08's migration for bracket_result_announce.
- [Phase ?]: D-06-10-H: TournamentModelTest event MorphOne test updated in-place. Pre-existing test was written for plan 06-03 stub state; observer auto-creation now creates the Event row, so manual Event::create collides on UNIQUE. Updated to assert auto-created row resolves through MorphOne + private no-event invariant.
- [Phase ?]: D-06-11-A: A8 LOCKED inline — admin-only via existing Phase 1 admin-access permission (canAccessPanel), NOT a new tournament.manage permission
- [Phase ?]: D-06-11-B: A5 LOCKED inline — forfeit + withdraw row actions have identical forward semantics; only status string + audit reason differ
- [Phase ?]: D-06-11-E: Added Tournament::brackets() HasManyThrough relation for BracketsRelationManager + future PublicTournamentData consumers
- [Phase ?]: D-06-11-C: Open Question A6 LOCKED inline — Swiss next-round generation is admin-click via generate_next_swiss_round HeaderAction (auto-trigger queue deferred to Phase 9)
- [Phase ?]: 06-12: Vue ambient typing via App.Data.* namespace instead of @trenchwars/shared-types — matches Phase 2/4 in-app idiom
- [Phase ?]: 06-12: Routes for /tournaments/{slug}.json declared BEFORE /tournaments/{slug} so Laravel first-match-wins dispatcher captures .json suffix correctly
- [Phase ?]: 06-12: config/i18n.php shared_namespaces extended with matches + tournaments (matches was pre-existing gap)
- [Phase ?]: 06-12: SC-1 capstone test walks downstream brackets via iterative materialiseFor() loop (materialiseFirstRound only handles round 1)
- [Phase ?]: Phase 6 plan 13 — Open Question 5 LOCKED inline: 3 distinct outbound kinds (tournament_announce, tournament_announce_update, bracket_result_announce) for per-kind dispatch + admin filterability
- [Phase ?]: Phase 6 plan 13 — Bot embed builders ship in apps/bot/src/lib/embeds.ts (extending Phase 5 single-file convention)
- [Phase ?]: Phase 6 plan 13 — i18n coverage gate uses leaf-anchored regex so string-concat dynamic keys are excluded from source-grep
- [Phase 06]: D-06-13-A — Bot kinds: 3 distinct enums (tournament_announce, tournament_announce_update, bracket_result_announce); Open Question 5 LOCKED inline
- [Phase 06]: D-06-13-B — Bot embed builders ship in apps/bot/src/lib/embeds.ts extending Phase 5 single-file convention; tournamentEmbeds.test.ts adds 22 bot Vitest tests
- [Phase 06]: D-06-13-C — i18n coverage gate uses leaf-anchored regex (90+ key tournaments.php namespace) to exclude string-concat dynamic keys from source-grep
- [Phase 06]: Plan 06-14 — Phase 6 COMPLETE; 866 web Pest tests / 2719 assertions (+248 web / +902 assertions from Phase 5 close) + 139 bot Vitest tests (+22 from Phase 5 close); all 7 quality gates GREEN (Pest, Vitest, Pint, PHPStan L8, bot tsc strict, shared-types tsc, vue-tsc); 06-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-success-tournament-end-to-end + 12 Pitfalls + 5 Open Questions + 53 D-06-* canonical bindings; ROADMAP Phase 6 14/14 Complete (2026-05-14); REQUIREMENTS REQ-success-tournament-end-to-end -> Complete; STATE completed_phases 5 -> 6 + percent 56 -> 67; status PENDING_MANUAL_SMOKE pending operator 4-item walkthrough A-D
- [Phase 06]: D-04-03-A LOCKED continued — App\\Models\\GameMatch direct import everywhere in Phase 6 (services, observers, DTOs, Filament resource, tests); zero alias-on-import; canonical binding for Phase 7+ CMS plans — Tournament events that polymorphically link back to GameMatch via tournament_brackets.match_id MUST import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B / D-06-03-A
- [Phase ?]: D-07-01-A — Tiptap 'default' profile pinned to safe-node allowlist in config/filament-tiptap-editor.php at install time (Pitfall 10 mitigation). Excluded: oembed/youtube/video/source/grid-builder/details/blocks. Plan 07-05 references this profile by name.
- [Phase ?]: D-07-01-B — Open Question 8 LOCKED: markdown-it NOT installed in v1. Article body render path is tiptap_converter()->asHTML end-to-end via ueberdosis/tiptap-php.
- [Phase 07]: D-07-02-A clans FTS trigger indexes name+tag+description+slug (Phase 2 schema: clans.name is text, not jsonb; tag included for league-directory UX)
- [Phase 07]: D-07-02-B players FTS trigger indexes ONLY display_name+slug (D-018; users.username is on users table not players)
- [Phase 07]: D-07-02-C discord_outbound CHECK baseline was 6 values not 7 per plan; up() extends 6→7 (adds article_announce); down() restores Phase 6 baseline verbatim
- [Phase 07]: D-07-03-A Use Spatie\\Image\\Enums\\Fit::Crop (not Fit::Cover — does not exist in spatie/image v3) for Article media conversions; cover-crop semantics preserved
- [Phase 07]: D-07-03-B Conversion method-call order — Conversion-native methods (performOnCollections/nonQueued/withResponsiveImages) BEFORE ImageDriver-proxied ->fit() to satisfy PHPStan L8 (Conversion declares @mixin ImageDriver; ->fit() returns ImageDriver to PHPStan, hiding Conversion methods after). Project-wide rule for every HasMedia model.
- [Phase 07]: D-07-03-C Canonical activitylog paths in this codebase: Spatie\\Activitylog\\Models\\Concerns\\LogsActivity + Spatie\\Activitylog\\Support\\LogOptions (Phase 4/6 idiom precedent — plan 07-03 <interfaces> referenced older Spatie\\Activitylog\\Traits\\LogsActivity + Spatie\\Activitylog\\LogOptions paths which exist on older library versions)
- [Phase 07]: D-07-03-D Article::events() uses morphMany (collection-shaped return) even though events_one_per_owner UNIQUE makes it functionally one-to-one; Tournament + GameMatch use morphOne — Article diverges per plan must_haves to give plan 07-12 sitemap consumers flexibility for batched calendar projections
- [Phase 07]: D-07-03-E PublicArticleData::fromModel() emits bodyHtml='' as documented partial-impl marker; plan 07-05 wires tiptap_converter()->asHTML; DTO shape stabilises here so 4 downstream plans (07-05, 07-09, 07-10, 07-12) can typehint without further class-modification churn
- [Phase 07]: D-07-03-F Article media conversions ALL bound to 'hero' collection (the only collection articles use in v1); plan 07-05 SpatieMediaLibraryFileUpload field must use ->collection('hero') matching performOnCollections('hero') — zero re-configuration of disk / collection names downstream
- [Phase ?]: D-07-04-A: ArticlePolicy::before() admin-bypass excludes delete (super-admin-role double-gate)
- [Phase ?]: D-07-04-B: Open Question 2 LOCKED via trenchwars:make-cms-editor artisan (mirrors Phase 1 make-admin idiom)
- [Phase ?]: D-07-04-C: articles.delete is super-admin only (perm-omit + policy-role double-gate per T-07-04-01)
- [Phase ?]: D-07-05-A: Installed filament/spatie-laravel-media-library-plugin ^3.3 (Rule 3 blocker — SpatieMediaLibraryFileUpload class absent from base install).
- [Phase ?]: D-07-05-C: Article slug ->disabledOn('edit') + ->unique(ignoreRecord) form rule (Open Question 4 LOCKED — permalink integrity, no auto-suffix).
- [Phase ?]: D-07-05-F: CreateArticle::mutateFormDataBeforeCreate force-sets author_user_id = auth()->id() (T-07-05-07 mitigation; form does not expose author_user_id field).
- [Phase ?]: D-07-05-G: Filament tests use assertFormFieldIsHidden (NOT assertFormFieldHidden — that method does not exist in Filament v3.3).
- [Phase ?]: D-07-06-A: DiscordOutboundPayloadBuilder lives at app/Support/ not app/Services/ — plan path label was incorrect; extended in-place
- [Phase ?]: D-07-06-B: Pitfall 10 republish guard uses outbox-row existence query (payload->article_id JSONB lookup) — plan's wasChanged+getOriginal trio passes on republish second leg
- [Phase ?]: D-07-06-C: config/discord.php is new — Phase 5 placed Discord OAuth in services.php; non-OAuth runtime settings get a dedicated namespace
- [Phase ?]: D-07-06-F: buildArticleAnnounce uses url('/news/'.slug) — route('blog.show') ships in plan 07-09; one-line migration when route binds
- [Phase ?]: D-07-07-A — chunkById 250-row boundary test shares one Category to avoid Faker UniqueGenerator overflow
- [Phase ?]: D-07-07-B — container resolution test asserts indirectly via side effect because ArticlePublishService is final and cannot be subclassed/mocked
- [Phase ?]: D-07-07-C — Schedule entry appended to existing inspire Artisan::command in routes/console.php; no prior Schedule entries existed in Phase 1-6
- [Phase ?]: D-07-08-A: SearchResultData.rank is a PHP-side ordinal (0-based descending), NOT the raw Postgres ts_rank float — preserves DB ordering without a second SELECT
- [Phase ?]: D-07-08-B: ts_rank test asserts ordering via term-frequency (NOT title-position weight) — plan 07-02 unweighted vector + 'simple' config cannot differentiate title-vs-excerpt position; future setweight() migration deferred
- [Phase ?]: D-07-08-C: SearchResultsData factory renamed empty() to forEmptyQuery() to avoid Spatie LaravelData Data::empty() override collision (Rule 3 — framework method LSP)
- [Phase ?]: D-07-08-F: PlayerPrivacyGate::canShowInSearch added as Rule 2 amendment to Phase 2 gate — tier semantics mirror passesTier; separate entry point keeps SearchService decoupled from controller abort(404)
- [Phase ?]: D-07-09-A: Retain ArticleSummaryData (do not collapse to PublicArticleData) — listing cards drop bodyHtml + heroOgImageUrl to save tiptap_converter render cost per card
- [Phase ?]: D-07-09-B: /events/feed.json route declared BEFORE /events (Phase 6 D-06-12-C continuation) so first-match-wins captures the .json suffix
- [Phase ?]: D-07-09-C: EventsFeedRequest uses per-request endUpperBound() helper for 90-day range cap — Laravel grammar does not support before_or_equal:start+90 days
- [Phase ?]: D-07-09-D: Open Question 6 LOCKED color palette inline in CalendarEventData::colourFor — match=#3B82F6, tournament=#8B5CF6, article=#10B981
- [Phase ?]: D-07-09-E: Web routes return 302+session errors on validation failure (not JSON 422); /events/feed.json with getJson() returns 422
- [Phase ?]: D-07-09-F: Inertia data-page attribute is htmlspecialchars(ENT_QUOTES) double-encoded — apostrophes become &#039; (T-07-09-06 XSS mitigation proof)
- [Phase ?]: D-07-10-A Vue components in lowercase components/cms folder
- [Phase ?]: D-07-10-B Boolean view helpers (hasCategories/hasArticles/hasMultiplePages) refactored from inline v-if attribute > expressions to keep NoHardcodedStringsTest scanner happy
- [Phase ?]: D-07-10-C NODE_WIDTH/NODE_HEIGHT extracted to bracket-node-dimensions.ts (Rule 3 fix for pre-existing pnpm build failure — Vue 3.5+ refuses export const in script setup)
- [Phase ?]: D-07-10-D FullCalendar options typed as Record<string,unknown> to avoid FC internal type collisions across the SSR boundary
- [Phase ?]: D-07-10-E Header SearchBar in hidden md:flex wrapper alongside existing nav; mobile-search affordance deferred
- [Phase ?]: Plan 07-11: SSR ships as 6th docker-compose service (split-service over worker-co-host per RESEARCH Pattern 5 Option B + Open Question 7 LOCKED inline RESOLVED); ssr.url default retargeted to docker service-name DNS (http://ssr:13714); Pitfall 8 locale chain locked down by SsrLocaleHonouredTest
- [Phase ?]: Pitfall 4 mitigation tested at SFC source level (head-key occurrence-count == 1) — runtime DOM verification deferred to v2
- [Phase ?]: Category is NOT a Sitemapable in v1 (no public show route); deferred to v2 alongside CategoryShowController
- [Phase ?]: Individual Player URLs NEVER per-row in sitemap (T-07-12-01 hard rule); only /players index URL exposed
- [Phase ?]: Search/Results.vue ships head-key='robots' content='noindex' (T-07-12-08) — must not be indexed
- [Phase 07]: Plan 07-13 — Phase 7 COMPLETE PENDING_MANUAL_SMOKE; 1037 web Pest tests / 3471 assertions (+171 web / +752 assertions from Phase 6 close) + 139 bot Vitest tests (regressionless from Phase 6); all 7 quality gates GREEN (Pest, Vitest, Pint 507 files clean, PHPStan L8 [OK], bot tsc strict, shared-types tsc, vue-tsc); 07-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-goal-cms + REQ-success-public-browse + 12 Pitfalls + 8 Open Questions RESOLVED inline + ~50 D-07-* canonical bindings; ROADMAP Phase 7 13/13 Complete (2026-05-14); REQUIREMENTS REQ-goal-cms + REQ-success-public-browse confirmed Complete; STATE completed_phases 6 -> 7 + completed_plans 94 -> 95 + percent 67 -> 78; status PENDING_MANUAL_SMOKE pending operator 4-item walkthrough A-D (Filament editor flow / FullCalendar UX / search ranking / sitemap + Discord announce + SSR first-paint)
- [Phase 07]: D-04-03-A LOCKED continued — App\\Models\\GameMatch direct import everywhere in Phase 7 (CalendarFeedService projections, SearchService joins, Article observer chain, tests); zero alias-on-import; canonical binding for Phase 8+ RCON plans — RCON-driven MatchResult create + per-player MatchPlayerStat MUST import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B / D-06-03-A / D-07-* continuation
- [Phase ?]: Phase 8 Plan 01 (2026-05-14): undici pinned to ^7 NOT ^8 for Node 22 fetch-compat (nodejs/undici#3901); Pino redact paths baked in at Wave 0 (T-08-01-02 mitigation prep); admin.audit.match_servers.* nested INSIDE existing top-level audit array to avoid Phase 1-7 regression.
- [Phase ?]: Plan 08-02: aligned with on-disk reality for discord_outbound CHECK constraint name (doutmsg_message_type_chk, not plan-text-claimed discord_outbound_messages_message_type_check) and 7-value baseline (Rule 1, Phase 7 plan 07-02 precedent).
- [Phase ?]: Plan 08-02: added match_server_bookings_range_check (reserved_to > reserved_from) as defence-in-depth alongside EXCLUDE — Postgres treats empty/inverted ranges as never-overlapping, so the CHECK closes that escape (Rule 2).
- [Phase ?]: Plan 08-02: down() does NOT drop btree_gist extension — CREATE EXTENSION IF NOT EXISTS on up() is idempotent; mirrors 0001 enable_postgres_extensions posture for uuid-ossp/citext.
- [Phase ?]: Plan 08-03: ALTER credentials_encrypted from jsonb to text — Laravel encrypted:array cast writes a base64 envelope (not JSON); column type must align with cast output.
- [Phase ?]: Plan 08-03: MatchServerBookingFactory uses explicit forMatch/onServer state helpers (not Eloquent ->for()) — avoids reserved-keyword reflection ambiguity on match() relation.
- [Phase ?]: MatchEvent uses $timestamps=false (append-only stream — occurred_at + ingested_at handle timeline; migration deliberately omits created_at/updated_at)
- [Phase ?]: Unit tests opt into RefreshDatabase explicitly — Pest.php global binding only attaches to Feature/, Phase 8 Unit tests use real DB factory fixtures
- [Phase ?]: DB::transaction() savepoint pattern for UNIQUE-violation probes that need follow-on queries — outer RefreshDatabase transaction otherwise enters failed state on 23505
- [Phase ?]: Test text
- [Phase ?]: Carbon::now() instead of microtime(true) for freshness arithmetic so Carbon::setTestNow honoured in tests; production semantics identical (08-05 D1)
- [Phase ?]: Distinct 401 labels per failure mode for ops debuggability; secret material never logged (08-05 D2)
- [Phase ?]: Test uses call() + pre-converted HTTP_* server vars (not postJson) so raw body bytes match signed bytes byte-for-byte (08-05 D3)
- [Phase ?]: Empty WEB_HMAC_SECRET fails LOUD via InvalidArgumentException in HmacVerifier::sign; mitigates T-08-05-06 fail-open misconfig (08-05 D4)
- [Phase 08]: Plan 08-06 ships a labelled SHIM in MatchEventsController::store; plan 08-07 will replace the inline closure with MatchEventIngestService injection — the 8-case InternalApiRoutesPresentTest pins the wire contract that 08-07 must preserve
- [Phase 08]: BookingDueData pre-resolves server_host + server_port so the rcon-worker BookingScheduler (plan 08-11) does not need a second hop to /api/internal/match-servers/{id}/credentials just for connectivity info — credentials endpoint stays reserved for the api_token
- [Phase 08]: SignsRconRequests at tests/Support/ (Tests\Support namespace) as a reusable Pest trait — plans 08-07/08-08/08-12 will uses(SignsRconRequests::class) to inherit signedJsonPost / signedGet / rconServerVars ergonomics; trait reads secret from config('rcon.hmac_secret') so each test pins its scope-local secret via beforeEach
- [Phase ?]: Per-event ingest INSERT wrapped in DB::transaction() for Postgres SAVEPOINT semantics — defuses SQLSTATE 25P02 under RefreshDatabase outer transaction (08-07 auto-fix Rule 1)
- [Phase ?]: MatchEventsController response shape gains skipped_count (additive — InternalApiRoutesPresentTest case 6 toHaveKeys non-strict)
- [Phase ?]: CloseMatchJob constructor takes readonly string matchId (primitive-ID job idiom — matches Phase 5 SyncDiscordRolesJob)
- [Phase 08]: Filament v3 MatchServerResource gated behind manage-rcon permission via canViewAny() — T-08-09-03 mitigation; Bookings relation manager is read-only with view_match link to MatchResource.
- [Phase 08]: TestMatchServerConnectionJob is async via Horizon (T-08-09-02 PHP-FPM 30s timeout); CrconHealthProbe runs from web container directly (not worker) — D-021-consistent because web has outbound HTTPS; calls CRCON GET /api/get_map_rotation (no-side-effect probe per RESEARCH line 179).
- [Phase 08]: MatchResource surgical extension preserves Phase 4 wizard + 4 RelationManagers; adds IconColumn (danger triangle) + Filter + clear-flag Action for D-019 manual_entry_required admin surface.
- [Phase ?]: Plan 08-10: HmacSigner timestamp typed as string (not number) for byte-for-byte cross-tier wire compat with apps/web HmacVerifier (PHP hash_hmac over $timestamp . $body).
- [Phase ?]: Plan 08-10: CrconClient.heartbeatIntervalMs option (default 30s) for deterministic integration testing — fake timers compose poorly with real ws I/O.
- [Phase ?]: Plan 08-10: WebIngestClient is retry-stateless; returns {status, body}; caller (08-11 BookingScheduler) owns Redis-backed 5xx drainer.
- [Phase ?]: BookingScheduler.managerFactory injectable option — Rule 2 testability hook (08-11)
- [Phase ?]: MatchLifecycleManager flushIntervalMs/batchSize/completeGraceMs override options — Rule 2 testability hooks (08-11)
- [Phase ?]: fetchSignedJson<T>(path) throws on non-2xx — centralises transient failure handling (08-11)
- [Phase ?]: ioredis named-import shape (import { Redis } from 'ioredis') for TS 5.6 NodeNext compatibility (08-11)
- [Phase ?]: Plan 08-12 (match_id in payload, not column)
- [Phase ?]: Plan 08-12 (DiscordOutboundPayloadBuilder in App\Support, static methods)
- [Phase ?]: Plan 08-12 (MatchResultObserver uses created+updated hooks)
- [Phase ?]: Plan 08-12 (MatchResultAnnounceData lives in App\Data, not App\Data\Internal)
- [Phase 08]: Plan 08-13 — Phase 8 COMPLETE PENDING_MANUAL_SMOKE; 1134 web Pest tests / 3783 assertions (+97 web / +312 assertions from Phase 7 close) + 139 bot Vitest tests (regressionless from Phase 7) + 40 rcon-worker Vitest tests across 7 files (brand new Node service); all 7 quality gates GREEN (Pest, Vitest×2, Pint 566 files clean, PHPStan L8 [OK], vue-tsc, shared-types tsc, rcon-worker typecheck+lint+test+build, migrate:fresh --seed); 08-PHASE-VERIFICATION.md authored mapping SC-1..SC-5 + REQ-goal-rcon-history + REQ-constraint-league-owns-servers + REQ-success-end-to-end-scrim + 12 Pitfalls + 5 Open Questions RESOLVED inline + ~60 D-08-* canonical bindings; ROADMAP Phase 8 13/13 Complete (2026-05-14); REQUIREMENTS REQ-goal-rcon-history + REQ-constraint-league-owns-servers + REQ-success-end-to-end-scrim confirmed Complete; STATE completed_phases 7 -> 8 + completed_plans 107 -> 108 + percent 78 -> 89; status PENDING_MANUAL_SMOKE pending operator 4-item walkthrough A-D (live CRCON probe / two-clan SC-5 happy path / mid-match log gap / HMAC key rotation)
- [Phase 08]: D-04-03-A LOCKED continued — App\\Models\\GameMatch direct import everywhere in Phase 8 (MatchEventIngestService, MatchPlayerStatAggregator, MatchResultService::upsertFromRcon, MatchResultObserver match_result_announce branch, DiscordOutboundPayloadBuilder::buildMatchResultAnnounce, BookingsRelationManager, ScrimE2EHappyPathTest, all Feature + Unit tests); zero alias-on-import; canonical binding for Phase 9+ Polish plans — leaderboards/aggregates/MatchPlayerStat consumers MUST import App\\Models\\GameMatch directly; BelongsTo<GameMatch, $this> passes match_id as explicit FK arg per D-04-03-B / D-06-03-A / D-07-* / D-08-* continuation
- [Phase 09]: Plan 09-02 — D-09-02-A — plan referenced non-existent CHECK constraint name `discord_outbound_messages_message_type_check` (Laravel default); actual canonical name is `doutmsg_message_type_chk` (set by Phase 5 baseline). Aligned with on-disk reality; same Rule 1 deviation pattern as 07-02 and 08-02. Verified live via pg_get_constraintdef BEFORE authoring.
- [Phase 09]: Plan 09-02 — D-09-02-B — ban_type, match_disputes.status, abuse_reports.reason_code/status, user_notification_preferences.event_type/channel intentionally NOT enforced via DB CHECK; varchar+app-validation (Pest+FormRequest) keeps schema portable as enum values evolve in 09-03/07/11.
- [Phase 09]: Plan 09-02 — D-09-02-C — partial UNIQUE `one_open_dispute_per_user_per_match` via raw SQL `CREATE UNIQUE INDEX ... WHERE status='open'` (Pitfall 11); Blueprint cannot express WHERE clause; down() explicit DROP for self-documenting reversal.
- [Phase 09]: Plan 09-02 — D-09-02-D — mps_player_kills_idx as plain ASC composite (player_id, kills); Postgres B-tree planner walks backwards for DESC; explicit DESC reserved for fallback if 09-05 LeaderboardService profiling proves Bitmap Heap Scan + Sort.
- [Phase 09]: Plan 09-02 — D-09-02-E — abuse_reports.target_id as varchar (NOT uuid via uuidMorphs) to admit BOTH UUID PK and bigint PK targets; application code in 09-11 casts per target_type.
- [Phase 09]: Plan 09-02 — Wave 1 schema landed: 7 migrations on 2026_05_18_100[0-6]00; migrate:fresh + rollback --step=7 + re-migrate all GREEN; Pest 1134 passed + 30 skipped preserved; Pint 7/7 PASS; PHPStan L8 OK. doutmsg_message_type_chk now 9 values incl. user_dm. Schema ready for Wave 2+ model/service plans (09-03..09-12).
- [Phase 09]: Plan 09-03 — D-09-03-A — Ban/MatchDispute/AbuseReport models intentionally do NOT use the LogsActivity trait per plan Anti-Patterns; the moderator service layer (plan 09-07 BanService + DisputeService, plan 09-11 AbuseReportService) emits hand-rolled activity_log rows so descriptions are human-readable rather than the trait's auto-generated "Ban created" skeleton, and avoids logging internal lifecycle noise (resolved_by_user_id flips) the audit log doesn't need to expose to Filament.
- [Phase 09]: Plan 09-03 — D-09-03-B — DiscordChannel carries recipient snowflake INSIDE payload.recipient_id (jsonb) rather than a dedicated discord_outbound_messages column. Plan's <interfaces> mentioned a hypothetical recipient_user_discord_id column that does not exist on the actual Phase 5 schema (verified via `\d discord_outbound_messages`). The table's routing inputs are channel_id (text NOT NULL; empty string for user DMs) + payload (jsonb); bot worker will inspect payload.recipient_id for message_type='user_dm' and call createDM at dispatch. Rule 1 deviation — aligned with on-disk reality, same pattern as 09-02 D-09-02-A.
- [Phase 09]: Plan 09-03 — D-09-03-C — Default-policy fallback in User::enabledNotificationChannels uses array key existence (`$prefs['discord'] ?? $discordDefault`), NOT row presence in the DB. Users with ZERO preference rows still get default policy applied without seeding default rows on signup. Account-settings UI (plan 09-06) will UPSERT preference rows only when the user toggles away from the default; unp_unique constraint handles idempotency. Trade-off: 'default' state is implicit rather than materialised — but materialising defaults would require 5 events × 2 channels = 10 rows per user, AND every default-policy change (e.g. adding a 6th event_type) would need a backfill migration.
- [Phase 09]: Plan 09-03 — D-09-03-D — `discord_id` "no snowflake" edge case is tested via empty string (`''`), NOT NULL discord_id (users.discord_id is text NOT NULL UNIQUE per D-002 Discord OAuth canonical). The enabledNotificationChannels guard `if (! empty($this->discord_id))` is defensive code that handles both null and empty string identically via empty(); if D-002 ever relaxes (e.g. email-only accounts), the guard already covers it. Testing via empty string exercises the same empty() branch without violating NOT NULL.
- [Phase 09]: Plan 09-03 — Wave 2 landed: 5 Eloquent models (Notification, UserNotificationPreference, Ban, MatchDispute, AbuseReport) + 5 Notification classes (MatchStartingSoon, MatchCancelled, MatchResultPublished, ClanApplicationDecided, ClanInviteReceived) + DiscordChannel + 4 real factories. Pitfall 3 LOCKED (Http::assertNothingSent on DiscordChannel::send — D-004 compliance) + Pitfall 4 LOCKED (every Notification has unique databaseType discriminator via reflection test). Pest 1153 passed + 27 skipped (delta: +19 / −3 Wave 0 stubs turned GREEN). PHPStan L8 OK. Pint 19/19 PASS.
- [Phase ?]: D-09-04-A: NotificationDispatcher uses GameMatch::slots.occupantUser + Clan::activeMembers.user relations (plan-vs-reality drift)
- [Phase ?]: D-09-04-B: dispatcher match-status filter whereIn('status',['open','locked']) — matches enum has no 'scheduled' value
- [Phase ?]: D-09-04-C: MatchObserver cancel trigger fires on (status=cancelled AND original!=cancelled)
- [Phase ?]: D-09-04-D: ClanApplicationObserver guards pending->{accepted,declined} (schema enum vocabulary, not plan's approved/rejected)
- [Phase ?]: D-09-04-E: MatchResultPublished fires from MatchResultObserver::created() only — score-edits do NOT re-notify
- [Phase ?]: D-09-04-F: Guest-clan recipients for MatchResultPublished deferred — v1 GameMatch is host-clan only (no away_clan_id column)
- [Phase 09]: D-09-05-A: LeaderboardEntryData blanks player_id (empty string) when is_anonymous=true; field shape preserved for stable Vue :key binding
- [Phase 09]: D-09-05-B: clans.logo_url does NOT exist on v1 schema; DTO carries the field as forward-compat, always null until plan 09-09 medialibrary WebP
- [Phase 09]: D-09-05-C: Schema-vs-plan drift LOCKED — matches has no game_id (route via game_match_types.game_id); clan_memberships uses left_at IS NULL (no active boolean); clan_memberships keys on user_id (route through players.user_id)
- [Phase 09]: D-09-05-D: Clan attribution joins on CURRENT active membership at query time (not membership-at-match-time); v1 accepts re-attribution on clan switch; ClanMembershipSnapshot is future-extension
- [Phase 09]: D-09-05-E: games.id is UUID (not int); LeaderboardService signatures use ?string $gameId
- [Phase 09]: D-09-05-F: Observers registered via Model::booted() per D-04-08-B (Laravel 11 removed EventServiceProvider); MatchPlayerStat::booted() registers MatchPlayerStatObserver
- [Phase 09]: D-09-05-G: ClanMembershipObserver tag-flush extension is Rule 2 additive correctness for D-09-05-D current-snapshot semantics (join/leave/rejoin all invalidate the leaderboards tag)
- [Phase 09]: Plan 09-05 landed: LeaderboardService (topPlayers + topClans with Cache::tags->flexible([600,3600]) SWR) + 2 Spatie DTOs + 3 tag-flush observers + Pitfall 9 safeCompute(). Pest 1187 passed + 21 skipped (delta: +24 / −4 Wave 0 stubs turned GREEN). PHPStan L8 OK. Pint 11/11 PASS.
- [Phase ?]: D-09-06-A — Inertia shared closure prop unread_notifications_count carries the bell badge count; no polling, no WebSocket (SC-1)
- [Phase ?]: D-09-06-B — Named rate limiters notifications-read + public-api registered at AppServiceProvider boot (idempotent; plan 09-11 may refine)
- [Phase ?]: D-09-06-C — LeaderboardEntryData privacy guard tightened to AND ::allowsSection AND ::passesTier (D-018 trust-boundary alignment, leaderboard surface)
- [Phase 09]: D-09-08-A — Leaderboards cold-cache query budget raised from PLAN ≤4 → measured 6 queries. Canonical Pattern 6 hydration trio (players + privacy + memberships) requires 3 IN-lookups; collapsing into a hand-written JOIN would bypass Eloquent + privacy accessors. Warm-cache (4) + empty-state (4) remain within the original envelope. Documented inline in `LeaderboardsQueryBudgetTest` file header + `CACHE-STRATEGY.md` § 7.
- [Phase 09]: D-09-08-B — Games dropdown cache moves OUT of `leaderboards` tag namespace into its own `games:dropdown` tag. Plan 09-05 implicitly grouped both under `leaderboards`; in practice the games list mutates ~once per phase (admin seeds new title) while the leaderboards aggregate flush fires on every MatchResult INSERT. Decoupling preserves the dropdown across all match-result writes. New tag-flush observer (`GameObserver::saved/deleted` → `games:dropdown` flush) deferred to a future plan when Game first needs an observer.
- [Phase 09]: D-09-08-C — Strict-mode flag uses full `Model::shouldBeStrict(! isProduction())` (the three-flag trio: preventLazyLoading + preventAccessingMissingAttributes + preventSilentlyDiscardingAttributes), NOT the more conservative `Model::preventLazyLoading()` alone. Half-strict would catch lazy loads but silently accept reads of columns the SELECT excluded — exactly the User::password Pitfall 2 bug class this plan surfaced. Production stays at `false` — runtime exception on a public Inertia-SSR page strictly worse than the same exception in CI.
- [Phase 09]: D-09-08-D — User::getAuthPassword override returns empty string (NOT null, NOT synthetic hash). AuthenticateSession middleware short-circuits on `! getAuthPassword()`; empty string is falsy so password-rehash session-rotation skipped entirely. Discord-OAuth-only schema (D-017) has no canonical password value. Same shape pattern applied to `getRememberToken(): ?string` defensive read via `array_key_exists($name, getAttributes())`.
- [Phase 09]: Plan 09-10 — D-09-10-A — plan literal `--color-focus` token corrected to `--color-focus-ring` (Phase 1 plan 01-07 LOCKED canonical Tailwind v4 @theme token name, 50+ Vue references across codebase). Rule 1 deviation aligned with on-disk reality. Global *:focus-visible rule + button/a/[role=button] color-mix shadow both bind `var(--color-focus-ring)`.
- [Phase 09]: Plan 09-10 — D-09-10-B — plan literal `/articles` corrected to `/blog` in BOTH axe-core URL matrix (.github/workflows/a11y.yml) AND Pest route matrix (PublicPagesHtmlLangTest). Phase 7 plan 07-09 LOCKED `/blog` as public-facing slug (Inertia component name is `Articles/Index` but route is `/blog`).
- [Phase 09]: Plan 09-10 — D-09-10-C — VueFormLabelsTest scans ONLY lowercase native <input>/<textarea>/<select>. PascalCase Vue wrappers (Select/TextInput/Textarea under components/ui/) audited at wrapper definition (emit native <label :for="id"> internally). Case-sensitive regex enforces scope.
- [Phase 09]: Plan 09-10 — D-09-10-D — axe artifact upload gated on `if: failure()` ONLY (T-09-10-01 upgraded from accept→mitigate). Passing CI runs retain no DOM snapshots. 14-day retention cap on failure artifacts.
- [Phase 09]: Plan 09-10 — D-09-10-E — axe per-URL loop uses `set +e` + EXIT accumulator (NOT fail-fast). Collects every report on single CI run for triage UX.
- [Phase 09]: Plan 09-10 — D-09-10-F — Task 2 (checkpoint:human-verify — 10-step manual keyboard nav smoke) DEFERRED to PENDING_MANUAL_SMOKE operator handoff per autonomous workflow convention. Same close pattern as Phase 1/2/3/4/5/6/7/8. Checklist recorded in 09-10-SUMMARY.md "Operator Handoff" section verbatim; operator walks out-of-band and reports via standard Phase 9 channel. Task 1 (CSS + axe-core CI + 2 Pest tests GREEN) committed in 01abd1e.
- [Phase 09]: Plan 09-10 landed: SC-5 accessibility round-1 deliverable. Site-wide `*:focus-visible` rule + button/a/[role=button] color-mix outer ring on `var(--color-focus-ring)`; `.github/workflows/a11y.yml` axe-core@^4.11.3 CI workflow with 7-URL Pitfall-11-compliant public route matrix (/, /clans, /matches, /tournaments, /blog, /events, /leaderboards) — admin/auth routes explicitly excluded. PublicPagesHtmlLangTest Wave 0 → 7 GREEN; VueFormLabelsTest Wave 0 → 1 GREEN static-scan (0 violations). SkipToContent + main#main verified intact (no edits). Filter run: 8 passed / 15 assertions / 2.08 s. Pint 0 dirty / PHPStan L8 OK. Task 2 PENDING_MANUAL_SMOKE.

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

Last session: 2026-05-15T15:57:00Z
Stopped at: Completed 09-10-PLAN.md (Wave 8 — SC-5 a11y: focus-visible + axe-core CI + 2 Pest tests GREEN; Task 2 PENDING_MANUAL_SMOKE keyboard-nav handoff to operator)
Resume file: None
