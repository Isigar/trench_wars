---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: executing
stopped_at: Completed 06-09-PLAN.md
last_updated: "2026-05-13T22:44:48.186Z"
last_activity: 2026-05-13
progress:
  total_phases: 9
  completed_phases: 5
  total_plans: 82
  completed_plans: 80
  percent: 56
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-05-03)

**Core value:** Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically.
**Current focus:** Phase 06 — Tournaments & brackets (executing; Wave 0 scaffolding complete)

## Current Position

Phase: 06 (Tournaments & brackets) — IN PROGRESS
Plan: 12 of 14 complete (06-03 Wave 2 — 5 tournament models + TournamentObserver stub + 5 real factories + 5 GREEN model tests covering Pitfall 4 + Pitfall 11 + composite UNIQUEs + LogsActivity)
Status: Ready to execute
Last activity: 2026-05-13

Progress: [█████████░] 87% (5/9 phases; 71/82 plans complete through Phase 6 plan 3)

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

Last session: 2026-05-13T22:44:48.182Z
Stopped at: Completed 06-09-PLAN.md
Resume file: None
