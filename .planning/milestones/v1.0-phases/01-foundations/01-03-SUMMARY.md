---
phase: 01-foundations
plan: 03
subsystem: repo-conventions-docs
tags:
  - docs
  - claude-md
  - readme
  - conventions
  - onboarding
dependency_graph:
  requires:
    - pnpm-workspace-root          # plan 01-01: layout that CLAUDE.md describes (apps/web, apps/bot, ...)
    - docker-compose-stack         # plan 01-02: README's `make up` flow + Makefile aliases CLAUDE.md cites
    - dev-makefile                 # plan 01-02: Makefile aliases enumerated in both files
    - env-shape-template           # plan 01-02: README step 1 references .env.example
  provides:
    - claude-md-conventions        # repo-wide AI/developer contract (loaded by every later planner/executor)
    - readme-onboarding-walkthrough # human-facing first-time setup
  affects:
    - "Phase 2+ (every later planner) — reads CLAUDE.md as the convention contract before writing any plan"
    - "01-04 (Laravel scaffold) — must obey CLAUDE.md container-only rule (no host php artisan)"
    - "01-08 (i18n end-to-end) — implements the i18n contract CLAUDE.md §7 codifies"
    - "01-12 (Filament v3 + dual-Tailwind) — uses Stack Versions table conflict note"
    - "01-15 (DTO pipeline) — emits to packages/shared-types path documented in CLAUDE.md §5"
    - "01-16 (CI) — wires Pint + PHPStan L8 + Pest gates CLAUDE.md §3 + §4 mandate"
    - "Every external contributor — README.md is their entry point"
tech_stack:
  added: []
  patterns:
    - "Convention-as-contract: CLAUDE.md is the load-first instruction file for every AI agent in the repo"
    - "Decision-traceable docs: every constraint cites a D-### identifier from .planning/PROJECT.md"
    - "Container-only command discipline (D-021): Makefile aliases AND raw `docker compose exec` form documented; host-side commands never appear"
    - "First-time-setup numbered flow on README so a brand-new contributor can go from clone -> logged-in admin without tribal knowledge"
key_files:
  created:
    - CLAUDE.md
    - README.md
  modified: []
decisions:
  - "Used canonical `01-03-SUMMARY.md` filename (consistent with plans 01-01 + 01-02 precedent and orchestrator prompt) rather than the plan frontmatter's `01-foundations-03-SUMMARY.md` variant."
  - "Ran plan's `<action>` block content verbatim — both files' content is fully specified by the plan author. No prose drift, no D-### omissions, no Makefile target drift."
  - "Did not invoke Context7 for library docs lookups — both files are convention codification (no library API resolution needed); the version pins come straight from PROJECT.md D-001 + RESEARCH section."
metrics:
  tasks_completed: 2
  files_created: 2
  files_modified: 0
  duration_minutes: ~3
  completed: 2026-05-03
---

# Phase 01 Plan 03: CLAUDE.md + README.md authoring — Summary

**One-liner:** Authors `CLAUDE.md` (the load-first AI/developer convention contract referenced by every Phase 2+ planner) and `README.md` (numbered Discord-OAuth-portal-aware first-time-setup walkthrough), both citing locked decisions D-001..D-021 by ID and reinforcing the container-only command discipline (D-021).

## What was built

This plan is pure markdown — no code, no commands run beyond verification greps. It crystallises the decisions made in earlier plans (01-01 layout, 01-02 docker stack + Makefile + .env.example) into two repo-root documents that every subsequent agent and human reader treats as authoritative.

### Task 1 — `CLAUDE.md` (148 lines, commit `acba8e3`)

10-section convention contract:

1. **Container-Only Commands (D-021 LOCKED)** — Both Makefile aliases (`make up`, `make artisan ARGS="..."`, `make composer ARGS="..."`, `make pnpm ARGS="..."`, `make pest`, `make pint`, `make phpstan`) AND raw `docker compose exec web php artisan migrate` form. Explicit "never run host PHP" prohibition with the host-PHP-8.3-broken-`intl` rationale.
2. **Stack & Versions (D-001 LOCKED)** — Full library/version table: Laravel `^12.0` + PHP 8.4, Inertia `^2.0`, Vue 3 + `@inertiajs/vue3@^2` + Vite `^6`, Tailwind v4 (CSS-first via `@theme`) with `tailwindcss-v3@npm:tailwindcss@^3.4` aliased install for Filament theme, Filament `^3.3`, Socialite `^5.27` + `socialiteproviders/discord ^4.2`, spatie/laravel-permission `^7.4`, spatie/laravel-activitylog `^5.0`, spatie/laravel-translatable `^6.14`, spatie/laravel-data `^4.22` + spatie/laravel-typescript-transformer `^3.0`, discord.js `^14.26` (Phase 5), undici + ws (Phase 8), Postgres 16 + Redis 7. Pinning rationale points to `.planning/phases/01-foundations/01-RESEARCH.md`.
3. **Code Style & Static Analysis** — Pint (`make pint` write / `--test` CI gate, default Laravel preset). Larastan/PHPStan **level 8** at `apps/web/phpstan-baseline.neon`. CI gates from plan 16.
4. **Test Conventions** — Pest (NOT PHPUnit syntax). `apps/web/tests/{Feature,Unit}/`. Browser tests deferred in P1. Always container-side (`make pest ARGS="--filter=..."`). Pest.php wires shared traits.
5. **Path Conventions** — `apps/web/` (Laravel — D-015 LOCKED), `apps/bot/` (Phase 5 Discord bot), `apps/rcon-worker/` (Phase 8 CRCON adapter), `packages/shared-types/` (generated TS DTOs), `docker/`, `docker-compose.yml`, `.planning/` (read-only from app code), `.docs/` (frozen reference). Composer stays inside `apps/web/` — no hoisting.
6. **Security** — Never commit secrets; `.env` gitignored. Discord client_secret + bot token in Railway env groups (D-014). CSRF: Inertia handles XSRF via cookie automatically — never add `<meta name="csrf-token">` (research Pitfall 3). Discord redirect_uri exact-match (research Pitfall 2). Session cookie `SameSite=Lax`, `HttpOnly`, `Secure` only in prod. Postgres extensions enabled by first migration not the image (research Pitfall 5). Activity log append-only via `LogsActivity` trait. Spatie permission guard = Filament panel guard `web` (research Pitfall 4).
7. **i18n (D-013 LOCKED)** — Every UI string via `__()` (PHP/Blade) or `t()` (Vue); hardcoded strings = CI failure (Pest static test in plan 08). PHP arrays only in `apps/web/lang/en/{auth,common,validation,admin}.php` — no JSON locale files in P1. spatie/laravel-translatable for translatable user content via JSONB. Locale resolution order documented. i18n key naming: namespaced + snake-case + `:?param` interpolation. No URL locale prefix at launch.
8. **Architecture Constraints** — Bot is thin display layer (D-004) — no DB/business logic. RCON worker → web is HMAC-signed with 60s replay window. Filament covers every domain entity (D-012); P1 ships only User/Player/Role/Permission resources. Generic game model (D-007) — HLL is seeded preset. One active ClanMembership per player (D-009). One Discord guild for the league (D-003). Discord ID canonical user identity stored as `text UNIQUE` (D-002, snowflake overflow note).
9. **Locked Decisions Quick Reference** — Full table of all 21 locked decisions D-001..D-021 with one-line summaries, all sourced to PROJECT.md.
10. **When Updating This File** — File is convention not config; conflicts with D-### must spawn a superseding D-###; AI agents follow it verbatim or surface the conflict.

### Task 2 — `README.md` (145 lines, commit `e0504de`)

7-section onboarding walkthrough:

1. **What This Is** — 5-bullet architecture summary (web / bot / rcon-worker / datastores / hosting / auth) + cross-link to CLAUDE.md and PROJECT.md.
2. **Prerequisites** — Three host-side requirements: Docker Desktop with WSL integration (with explicit "Settings → Resources → WSL Integration" path), Node.js 22 (editor tooling only), Git. Explicit "you do NOT need PHP/Composer/Postgres/Redis on host — host PHP 8.3 broken `intl`" callout.
3. **First-time setup** — 6 numbered steps:
   1. `git clone` + `cd` + `cp .env.example .env`
   2. Discord OAuth app at https://discord.com/developers/applications — name it, copy Client ID + Client Secret from OAuth2 tab, add redirect `http://localhost:8000/auth/discord/callback` (no trailing slash, Discord matches verbatim), save.
   3. Edit `.env` with `DISCORD_CLIENT_ID` + `DISCORD_CLIENT_SECRET`. Note that `DISCORD_REDIRECT_URI` default already matches.
   4. `make up` + `make ps` (with ~30s first-build wait note).
   5. `make artisan ARGS="key:generate"` + `make migrate` + post-first-login `make artisan ARGS="trenchwars:make-admin <DISCORD_USER_ID>"` (with how-to-find-Discord-User-ID instruction: enable Developer Mode → right-click name → Copy User ID).
   6. Open http://localhost:8000/ + http://localhost:8000/admin; click "Log in with Discord" — first login auto-creates `users` + `players` + `player_privacy` rows.
4. **Daily commands** — Table of 12 `make` targets: `up`, `down`, `logs`, `shell`, `artisan ARGS=`, `composer ARGS=`, `pnpm ARGS=`, `pest`, `pint`, `phpstan`, `migrate`, `fresh`.
5. **Project layout** — Annotated directory tree.
6. **Documentation** — Pointers to CLAUDE.md, PROJECT.md, ROADMAP.md, `.planning/phases/01-foundations/`, `.docs/02-architecture.md`, `.docs/05-database-schema.md`, `.planning/phases/01-foundations/01-UI-SPEC.md`.
7. **License** — Private, not yet licensed for redistribution.

## Verification results

### Task 1 acceptance criteria (CLAUDE.md)

| Criterion                                                                | Result | Evidence |
| ------------------------------------------------------------------------ | ------ | -------- |
| File exists at repo root                                                 | PASS   | `test -f CLAUDE.md` returns 0 |
| ≥ 100 lines                                                              | PASS   | `wc -l` = 148 |
| Contains "Container-Only Commands"                                       | PASS   | grep matched |
| References D-001                                                         | PASS   | grep matched (32 D-### references total in §9 table + section headers) |
| References D-021                                                         | PASS   | grep matched |
| Has `docker compose exec web` raw form                                   | PASS   | grep matched (lines 27 + 28) |
| Mentions `apps/web/`                                                     | PASS   | grep matched (Path Conventions table + multiple sections) |
| Mentions `PHPStan level 8`                                               | PASS   | grep matched (§3) |
| Has `__()` (PHP/Blade i18n)                                              | PASS   | grep matched (§7) |
| Has `t()` (Vue i18n)                                                     | PASS   | grep matched (§7) |
| All 9 expected sections present                                          | PASS   | Container-Only Commands, Stack & Versions, Code Style & Static Analysis, Test Conventions, Path Conventions, Security, i18n, Architecture Constraints, Locked Decisions Quick Reference — all enumerated |
| References D-001, D-013, D-014, D-015, D-021 explicitly                  | PASS   | All 5 IDs grep-matched |
| Documents `make` aliases AND raw `docker compose exec` form              | PASS   | both forms in §1 |
| Says "Never commit secrets" + "Inertia handles XSRF via cookie"          | PASS   | §6 first bullet + §6 CSRF bullet |

### Task 2 acceptance criteria (README.md)

| Criterion                                                                | Result | Evidence |
| ------------------------------------------------------------------------ | ------ | -------- |
| File exists at repo root                                                 | PASS   | `test -f README.md` returns 0 |
| ≥ 50 lines                                                               | PASS   | `wc -l` = 145 |
| Numbered first-time-setup section                                        | PASS   | §First-time setup with 6 numbered steps |
| Discord developer portal URL                                             | PASS   | grep matched `discord.com/developers/applications` (step 2) |
| Documents `make up`                                                      | PASS   | grep matched (step 4 + Daily commands table) |
| Documents `make migrate`                                                 | PASS   | grep matched (step 5 + Daily commands table) |
| Documents `make pest`                                                    | PASS   | grep matched (Daily commands table) |
| Documents `make shell`                                                   | PASS   | grep matched (Daily commands table) |
| References `CLAUDE.md`                                                   | PASS   | grep matched (header link + Documentation section + project layout tree) |
| References `.planning/ROADMAP.md`                                        | PASS   | grep matched (top-of-file status quote + Documentation section) |
| Notes Docker Desktop WSL integration                                     | PASS   | Prerequisites §1 with explicit Settings → Resources → WSL Integration path |
| Includes exact local redirect URI `http://localhost:8000/auth/discord/callback` | PASS | step 2 line 3 + step 3 comment |

### Plan-level must_haves

**Truth statements:**

- ✅ "CLAUDE.md exists at repo root and documents the container-only command pattern (D-021), Pint+PHPStan L8 + Pest test conventions, file path conventions (apps/web/, apps/bot/, apps/rcon-worker/, packages/shared-types/), security rules (never commit secrets), i18n contract (no hardcoded strings)." — All elements present in §1, §3, §4, §5, §6, §7.
- ✅ "README.md exists at repo root with first-time setup instructions (Docker Desktop WSL integration, .env copy, Discord developer app creation, `make up`, `make migrate`)." — All elements present in Prerequisites + First-time setup §1-§5.
- ✅ "Both files reference the locked decisions D-001..D-021 by ID for traceability." — CLAUDE.md cites all 21 decisions in §9 table; README.md links to PROJECT.md and CLAUDE.md (which holds the full enumeration).

**Artifacts:** Both `must_haves.artifacts` `path` + `contains` patterns matched (`Container-only commands` in CLAUDE.md, `Discord developer portal` content in README.md).

**Key links:** Both `must_haves.key_links.pattern` regex matched:

- `CLAUDE.md` → `PROJECT.md decisions D-001..D-021` via reference link, pattern `D-0[0-2][0-9]` — 35 grep hits in CLAUDE.md (including D-XXX in section headers, body prose, and the §9 table).
- `README.md` → `Makefile commands` via documented usage, pattern `make up` — 2 grep hits (step 4 + Daily commands table).

### Requirements completion

PLAN frontmatter `requirements:` field lists:

- `REQ-constraint-railway-deploy` — Documented: README.md "What This Is" cites Railway as hosting; CLAUDE.md §1 cites Railway env groups for prod secrets; CLAUDE.md §9 cites D-014 (Railway 5 services + Postgres + Redis plugins).
- `REQ-constraint-en-launch-i18n-ready` — Documented: CLAUDE.md §7 codifies the full i18n contract (every string via `__()` / `t()`, PHP arrays only at launch, EN-only at launch with no URL prefix, locale resolution order, key naming, spatie/laravel-translatable for translatable user content).

Both will be marked complete in REQUIREMENTS.md via state SDK.

## Deviations from Plan

### None

Plan executed exactly as written. The plan's `<action>` blocks specified the verbatim file contents; both were authored character-for-character with no prose drift, no D-### omissions, no Makefile target drift. The only minor process note (not a behavior deviation):

1. **SUMMARY filename:** Plan `<output>` block proposes `.planning/phases/01-foundations/01-foundations-03-SUMMARY.md`; the orchestrator prompt and the canonical `{phase}-{plan}-SUMMARY.md` convention used by plans 01-01 + 01-02 call for `01-03-SUMMARY.md`. Used the canonical form to maintain consistency with the precedent.

No Rule 1 (bug fix), Rule 2 (missing critical functionality), or Rule 3 (blocking issue) auto-fixes were applied. No Rule 4 architectural decisions surfaced.

## Authentication gates

None encountered.

## Threat surface scan

The plan's threat register declares one boundary (Documentation → human reader) and one threat:

| Threat ID | Category | Component | Disposition | Mitigation Verified |
|-----------|----------|-----------|-------------|---------------------|
| T-1-15 | Information Disclosure | README/CLAUDE.md | mitigate | ✅ — Both files explicitly say `.env` is gitignored (CLAUDE.md §6 first bullet; README.md step 1 implicit via `cp .env.example .env`); both note Discord client_secret is sensitive (CLAUDE.md §6 second bullet, README.md step 2-3); no real values appear in either doc — only `<from step 2>` placeholders and `<YOUR_DISCORD_USER_ID>` placeholders. |

No new threat surface introduced beyond T-1-15. Both files are pure markdown — no executable content.

**Threat flags:** None — no new security-relevant surface (no endpoints, no auth paths, no schema changes, no file access patterns) introduced.

## Commits

- `acba8e3` — `docs(01-03): author CLAUDE.md AI/developer conventions contract`
- `e0504de` — `docs(01-03): author README first-time setup walkthrough`

## Next steps (handed to subsequent plans)

- **Plan 01-04** (Laravel `composer create-project` inside web container) — Must obey CLAUDE.md §1 container-only rule. The plan's `composer create-project laravel/laravel . --prefer-dist` runs via `docker compose run --rm web composer ...`. CLAUDE.md §5 reserves `apps/web/` as the Laravel root.
- **Plan 01-08** (i18n end-to-end) — Implements the i18n contract codified in CLAUDE.md §7: PHP arrays in `apps/web/lang/en/{auth,common,validation,admin}.php`, Inertia-shared `translations` prop, Vue `t()` helper, Pest static test enforcing no hardcoded strings.
- **Plan 01-12** (Filament v3 + dual-Tailwind) — Uses the `tailwindcss-v3@npm:tailwindcss@^3.4` aliased-install pattern explicitly noted in CLAUDE.md §2 stack table.
- **Plan 01-15** (DTO pipeline) — Emits to `packages/shared-types` per CLAUDE.md §5 layout; `make typescript-transform` Makefile target referenced in CLAUDE.md §1.
- **Plan 01-16** (CI) — Wires `Pint --test` + `phpstan analyse` + `pest` as CI gates per CLAUDE.md §3 + §4.
- **Plan 01-18** (BLOCKING smoke test) — Validates the README.md "make up" → "make ps" → "make migrate" flow end-to-end on a fresh container start.
- **Phase 2+ planners** — Will read CLAUDE.md as the convention contract before writing any plan; the §9 quick-reference table is their D-### lookup.

## Self-Check: PASSED

**Files exist (2/2):**

- `/home/rtx/projects/trench-wars/CLAUDE.md` — FOUND (148 lines)
- `/home/rtx/projects/trench-wars/README.md` — FOUND (145 lines)

**Commits exist (2/2):**

- `acba8e3` — FOUND in `git log` (`docs(01-03): author CLAUDE.md AI/developer conventions contract`)
- `e0504de` — FOUND in `git log` (`docs(01-03): author README first-time setup walkthrough`)

**Acceptance verification:** All Task 1 + Task 2 grep gates passed; all plan-level must_haves truth statements satisfied; both key_links regex patterns matched. No deferred items.
