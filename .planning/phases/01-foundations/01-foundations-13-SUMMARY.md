---
phase: 01-foundations
plan: 13
subsystem: ui
tags: [filament, admin-panel, spatie-permission, i18n]

requires:
  - phase: 01-foundations
    provides: "Filament v3 panel mounted + gated by admin-access (plan 12); Spatie Role/Permission models + admin-access permission seeded (plan 11); User/Player/PlayerPrivacy Eloquent models (plan 10)."

provides:
  - "Four P1 Filament resources: User, Player, Role, Permission — each with list/view/edit pages."
  - "PlayerResource form with inline player_privacy Section bound via ->relationship('privacy') (single save persists Player + 1:1 PlayerPrivacy)."
  - "RoleResource Create page pins guard_name='web' via mutateFormDataBeforeCreate (Pitfall 4 mitigation)."
  - "Admin-namespace lang keys (admin.user.*, admin.player.*, admin.role.*, admin.permission.*) — D-013 i18n discipline."
  - "FilamentResourcesPresentTest (5 tests) — 4 resources reachable + Users/Players Create routes 404 (D-002)."

affects: [phase-01-14-audit-tab, phase-02-clans, phase-03-games, phase-04-tournaments, phase-08-rcon]

tech-stack:
  added: []  # No new composer/pnpm packages — Filament + Spatie installed in plans 11+12.
  patterns:
    - "Filament Resource w/ ->relationship('section') for inline 1:1 child editing"
    - "i18n labels via __('admin.<resource>.fields.<col>') — no hardcoded English in Filament Resources"
    - "guard_name='web' pinned in form (disabled Select default + dehydrated(true)) AND in CreateRecord::mutateFormDataBeforeCreate — defence-in-depth"
    - "Resource w/ no 'create' page route when records are minted upstream (User: OAuth callback; Player: first-login)"

key-files:
  created:
    - "apps/web/app/Filament/Resources/UserResource.php"
    - "apps/web/app/Filament/Resources/UserResource/Pages/{ListUsers,EditUser,ViewUser}.php"
    - "apps/web/app/Filament/Resources/PlayerResource.php"
    - "apps/web/app/Filament/Resources/PlayerResource/Pages/{ListPlayers,EditPlayer,ViewPlayer}.php"
    - "apps/web/app/Filament/Resources/RoleResource.php"
    - "apps/web/app/Filament/Resources/RoleResource/Pages/{ListRoles,CreateRole,EditRole}.php"
    - "apps/web/app/Filament/Resources/PermissionResource.php"
    - "apps/web/app/Filament/Resources/PermissionResource/Pages/{ListPermissions,EditPermission}.php"
    - "apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php"
  modified:
    - "apps/web/app/Providers/Filament/AdminPanelProvider.php (registered 4 resources)"
    - "apps/web/lang/en/admin.php (added user/player/role/permission keys)"

key-decisions:
  - "UserResource omits Create route — D-002 makes Discord OAuth the only identity-mint path."
  - "PlayerResource omits Create route — Players are minted at first Discord login (plan 09)."
  - "PermissionResource is List+Edit only (no Create) — permissions are seeded via PermissionSeeder + trenchwars:make-admin (CONTEXT.md)."
  - "RoleResource pins guard_name='web' twice (form Select disabled + CreateRole::mutateFormDataBeforeCreate) — Pitfall 4 mitigation, defence-in-depth."
  - "Resource model labels go through __('admin.<resource>.label') / .plural_label so the sidebar respects i18n (D-013) — added getModelLabel/getPluralModelLabel overrides."
  - "UserResource locale Select sources options from config('i18n.available_locales') — adding a locale stays config-only (matches the i18n contract authored in plan 08)."

patterns-established:
  - "Filament inline 1:1 child editing: Section::make(...)->relationship('child') wraps a schema that targets columns on the related model. PlayerResource uses this for player_privacy."
  - "i18n discipline in Filament: every visible Form/Table label call goes through __() and lang/en/admin.php holds the canonical EN. Future phases (clan/match/tournament resources) follow this convention."
  - "Resource with no 'create' route: omit Pages\Create*::route() AND omit any header CreateAction. Filament hides the New button automatically when no route is registered."

requirements-completed:
  - REQ-constraint-railway-deploy

duration: ~6min
completed: 2026-05-04
---

# Phase 01 Plan 13: Filament Resources (User/Player/Role/Permission) Summary

**Four P1 Filament resources wired into the admin panel — User + Player (no-Create, OAuth/first-login mint) and Role + Permission (Spatie-backed, guard pinned to 'web')**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-05-04T18:18:03Z
- **Completed:** 2026-05-04T18:24:00Z (approx)
- **Tasks:** 2
- **Files modified:** 18 (16 created + 2 edited)

## Accomplishments

- UserResource with read-only `discord_id` (T-1-28 mitigation: form never writes the canonical identity column).
- PlayerResource with inline player_privacy Section bound via `->relationship('privacy')` — one form save persists both rows.
- RoleResource + PermissionResource on Spatie models with `guard_name='web'` pinned defence-in-depth (Form Select disabled default + CreateRole::mutateFormDataBeforeCreate — Pitfall 4 mitigation).
- AdminPanelProvider->resources() populated with all four classes.
- 5 new Pest tests in FilamentResourcesPresentTest — all four resources reachable for an admin (200), Users/Players Create routes return 404 (no-Create contract).
- lang/en/admin.php extended with user/player/role/permission namespaces — D-013 i18n discipline preserved (no hardcoded EN in Filament resources).

## Task Commits

1. **Task 1: UserResource + PlayerResource (with inline player_privacy)** — `ae4bf20` (feat)
2. **Task 2: RoleResource + PermissionResource + AdminPanelProvider registration + FilamentResourcesPresentTest** — `5b5b81d` (feat)

**Plan metadata:** _to be added by final docs commit_

## Files Created/Modified

- `apps/web/app/Filament/Resources/UserResource.php` — User CRUD; no Create page; locale Select sourced from `config('i18n.available_locales')`.
- `apps/web/app/Filament/Resources/UserResource/Pages/{ListUsers,EditUser,ViewUser}.php` — minimal page subclasses.
- `apps/web/app/Filament/Resources/PlayerResource.php` — Player CRUD; inline Privacy Section via `->relationship('privacy')`; no Create page.
- `apps/web/app/Filament/Resources/PlayerResource/Pages/{ListPlayers,EditPlayer,ViewPlayer}.php` — minimal page subclasses.
- `apps/web/app/Filament/Resources/RoleResource.php` — Role CRUD on Spatie\Permission\Models\Role; permissions multi-Select via relationship.
- `apps/web/app/Filament/Resources/RoleResource/Pages/{ListRoles,CreateRole,EditRole}.php` — ListRoles registers CreateAction header button; CreateRole pins guard_name='web' via mutateFormDataBeforeCreate.
- `apps/web/app/Filament/Resources/PermissionResource.php` — Permission List+Edit only (no Create — admin grants via tinker/artisan per CONTEXT.md).
- `apps/web/app/Filament/Resources/PermissionResource/Pages/{ListPermissions,EditPermission}.php` — minimal page subclasses.
- `apps/web/app/Providers/Filament/AdminPanelProvider.php` — replaced empty `->resources([])` with the 4-resource list.
- `apps/web/lang/en/admin.php` — extended with `user.*`, `player.*`, `role.*`, `permission.*` keys (label, plural_label, fields, sections, help text).
- `apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php` — 5 Pest tests covering panel route reachability + the no-Create contract.

## Decisions Made

- **`getModelLabel` / `getPluralModelLabel` overrides on every resource.** Filament's default sidebar label hardcodes the English class name. Override returning `__('admin.<resource>.label')` / `.plural_label` keeps i18n end-to-end. Added `label` and `plural_label` keys to the lang file.
- **`guard_name='web'` pinned twice on Role.** The Form Select is `disabled()->dehydrated(true)` (so the disabled control still POSTs the value) AND `CreateRole::mutateFormDataBeforeCreate` re-asserts `'web'`. Either alone would suffice today; the pair survives a future migration that adds a second guard option.
- **Locale options sourced from `config('i18n.available_locales')` (not hardcoded `['en']`).** Plan 08 authored the config; adding a locale at launch stays config-only. PHPStan-clean via a private `localeOptions()` helper with a typed `@var array<int,string>` annotation (the raw `config()` return is mixed; helper centralises the cast).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] PHPStan L8 `argument.templateType` on `collect(config(...))`**
- **Found during:** Task 1 (UserResource locale Select)
- **Issue:** `collect(config('i18n.available_locales', ['en']))->mapWithKeys(...)` — PHPStan can't resolve `TKey`/`TValue` because `config()` returns `mixed`. Two errors at L8.
- **Fix:** Replaced the inline collect with a typed private `localeOptions(): array<string,string>` helper using a `@var array<int,string>` annotation on the `config()` call.
- **Files modified:** `apps/web/app/Filament/Resources/UserResource.php`
- **Verification:** `phpstan analyse app/Filament/Resources` → 0 errors; full-config `phpstan analyse` → 0 errors.
- **Committed in:** `ae4bf20` (Task 1 commit)

**2. [Rule 2 — Missing Critical] i18n labels for resource sidebar names**
- **Found during:** Task 1 + Task 2 (resource authoring)
- **Issue:** Plan's pasted resource snippets used `protected static ?string $navigationLabel = ...` only via Filament defaults (which hardcode EN class names). D-013 requires every UI string flow through `__()`. Without `getModelLabel` / `getPluralModelLabel` the sidebar would display hardcoded "User"/"Player"/"Role"/"Permission".
- **Fix:** Added `getModelLabel` and `getPluralModelLabel` overrides on all four resources returning `__('admin.<resource>.label')` / `.plural_label`. Added matching keys to `lang/en/admin.php`.
- **Files modified:** All four `*Resource.php` + `apps/web/lang/en/admin.php`.
- **Verification:** Pest 10/10 admin tests green; Pint clean; PHPStan clean.
- **Committed in:** `ae4bf20` + `5b5b81d`.

**3. [Method-only] Pint auto-fixes**
- **Found during:** post-write Pint pass on both tasks.
- **Issue:** Two `fully_qualified_strict_types` violations — Pint promoted FQCN type-hints (`\Filament\Resources\Pages\PageRegistration`, `\Filament\Actions\Action`) to `use` imports. Same intent, no logic change.
- **Fix:** Pint applied; no manual change needed.
- **Files modified:** UserResource.php, PlayerResource.php, AdminPanelProvider.php, RoleResource/Pages/ListRoles.php.
- **Verification:** `pint --test` clean across all 84 files.
- **Committed in:** `ae4bf20` + `5b5b81d`.

---

**Total deviations:** 2 auto-fixed (1 type-correctness bug, 1 missing-critical i18n) + 1 method-only (Pint).
**Impact on plan:** Both auto-fixes essential for the L8 + D-013 CI gates. No scope creep — all changes confined to plan's `files_modified` list.

## Issues Encountered

- **Concurrent execution with plan 01-15** — orchestrator warned that 01-15 runs in another worktree and that `composer.json`, `composer.lock`, `app/Data/*`, `bootstrap/providers.php`, `app/Providers/TypeScriptTransformerServiceProvider.php`, `resources/js/types/*`, `packages/shared-types/*`, `apps/{bot,rcon-worker}/src/index.ts`, `docker-compose.yml`, `apps/web/app/Console/Commands/TypescriptGenerateCommand.php` would appear modified/untracked during my run.
  - **Resolution:** Confirmed via `git worktree list` only one worktree (this repo) exists, so 01-15 was committing directly to master in the meantime. I staged only my plan's files individually (`git add apps/web/app/Filament/...` per file) and never used `git add .` or `-A`. No cross-plan contamination in either of my two task commits.
- **`grep` is aliased to `ugrep` in this shell** — the plan's `<verify>` command used `grep -q "->relationship(...)"` and ugrep treats `->` as flag syntax. Used `/bin/grep` directly to bypass the alias for verification.

## User Setup Required

None — no external service configuration. The four resources are immediately usable by any user with the `admin-access` permission (granted via the `trenchwars:make-admin <discord_id>` artisan command shipped in plan 11).

## Next Phase Readiness

- **SC-2 deliverable:** `/admin` sidebar lists User, Player, Role, Permission. ✅
- Plan 01-14 (Audit tab + global Audit page) can now consume Filament's sidebar pattern + lang namespace.
- No blockers for the remaining wave-7+ plans (01-14, 01-18) or for Phase 2 (clans).

## Self-Check: PASSED

- [x] `apps/web/app/Filament/Resources/UserResource.php` exists.
- [x] `apps/web/app/Filament/Resources/PlayerResource.php` exists.
- [x] `apps/web/app/Filament/Resources/RoleResource.php` exists.
- [x] `apps/web/app/Filament/Resources/PermissionResource.php` exists.
- [x] `apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php` exists.
- [x] `apps/web/app/Providers/Filament/AdminPanelProvider.php` registers all four resources (verified via `git show`).
- [x] Commit `ae4bf20` (Task 1) on branch.
- [x] Commit `5b5b81d` (Task 2) on branch.
- [x] `pint --test` → 84 files clean.
- [x] `phpstan analyse` (config paths) → 0 errors.
- [x] `pest` → 45/45 passed (10 admin tests including 5 new).

---

*Phase: 01-foundations*
*Completed: 2026-05-04*
