---
phase: 07-cms
plan: 04
subsystem: cms-authz
tags:
  - wave-2
  - permissions
  - spatie-permission
  - role
  - policy
  - artisan-command
  - cms-editor
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/01-foundations/01-11-SUMMARY.md  # trenchwars:make-admin idiom precedent
    - .planning/phases/02-clans/02-09-SUMMARY.md        # AuthServiceProvider $policies array + before() admin-bypass idiom
    - .planning/phases/07-cms/07-03-SUMMARY.md          # Article + Category models exist for policy + factory test fixtures
  provides:
    - "PermissionSeeder extended with 6 new permissions: articles.view, articles.create, articles.update, articles.publish, articles.delete, categories.manage (super-admin syncs all 8; cms-editor syncs 6, EXPLICITLY omits articles.delete per T-07-04-01)"
    - "App\\Console\\Commands\\MakeCmsEditorCommand — trenchwars:make-cms-editor {discord_id} artisan; idempotent (hasRole gate); writes activity_log row on first grant only"
    - "App\\Policies\\ArticlePolicy — viewAny/view (public), create (articles.create), update (articles.update + own-author OR articles.publish override), publish (articles.publish), delete (super-admin role only); before() admin-bypass for everything but delete"
    - "App\\Policies\\CategoryPolicy — viewAny/view (public), create/update/delete (categories.manage); before() admin-bypass"
    - "AuthServiceProvider registers Article + Category policies alongside Clan + ClanMembership"
    - "tests/Feature/Permissions/CmsEditorRoleTest.php — 8 GREEN it() blocks (exceeded plan's 5+ target)"
    - "tests/Feature/Console/MakeCmsEditorCommandTest.php — 4 GREEN it() blocks (exceeded plan's 3+ target)"
  affects:
    - apps/web/database/seeders/PermissionSeeder.php
    - apps/web/app/Console/Commands/MakeCmsEditorCommand.php
    - apps/web/app/Policies/ArticlePolicy.php
    - apps/web/app/Policies/CategoryPolicy.php
    - apps/web/app/Providers/AuthServiceProvider.php
    - apps/web/tests/Feature/Permissions/CmsEditorRoleTest.php
    - apps/web/tests/Feature/Console/MakeCmsEditorCommandTest.php
tech-stack:
  added: []
  patterns:
    - "Phase 1 plan 01-11 artisan-command idiom verbatim (trenchwars:make-admin →
      trenchwars:make-cms-editor): {discord_id} arg, User lookup by discord_id,
      defence-in-depth Permission::findOrCreate + Role::findOrCreate, idempotent
      via hasRole() gate, activity_log row on first grant only (D-012 audit)."
    - "Phase 2 plan 02-09 AuthServiceProvider $policies array idiom: model class
      → policy class mapping; registerPolicies() called in boot(). Preserved
      existing Clan + ClanMembership entries verbatim."
    - "Phase 2 plan 02-09 Policy::before() admin-bypass — admin-access permission
      grants all abilities except delete (which routes back to the explicit
      delete() method so super-admin-only enforcement holds for articles)."
    - "PermissionSeeder additive extension — preserve existing super-admin sync
      + cms-editor placeholder line, append new permissions to the $permissions
      array, then swap the cms-editor placeholder for a full syncPermissions block.
      Idempotent via Permission::findOrCreate + Role::findOrCreate + syncPermissions."
key-files:
  created:
    - apps/web/app/Console/Commands/MakeCmsEditorCommand.php
    - apps/web/app/Policies/ArticlePolicy.php
    - apps/web/app/Policies/CategoryPolicy.php
    - apps/web/tests/Feature/Permissions/CmsEditorRoleTest.php
    - apps/web/tests/Feature/Console/MakeCmsEditorCommandTest.php
  modified:
    - apps/web/database/seeders/PermissionSeeder.php       # +6 permissions, +cms-editor full sync
    - apps/web/app/Providers/AuthServiceProvider.php       # +2 policy entries (Article, Category)
decisions:
  - "D-07-04-A — ArticlePolicy::before() admin-bypass forwards delete back to the
    explicit method (returns null for $ability === 'delete'). This is the Phase 2
    ClanPolicy idiom adapted for the cms-editor / super-admin split: admins via
    admin-access get free updates/views, but delete still requires explicit
    super-admin role membership (defence-in-depth complement to PermissionSeeder's
    explicit omission of articles.delete from cms-editor). This double-gate means
    a future operator who accidentally grants articles.delete to cms-editor as a
    stray permission STILL cannot delete via the policy because the role-membership
    check is independent of the permission grant."
  - "D-07-04-B — Open Question 2 (initial editorial team bootstrap) LOCKED inline
    via MakeCmsEditorCommand. The artisan-command pattern (trenchwars:make-X
    {discord_id}) is now an established Trenchwars idiom: Phase 1 plan 01-11
    (super-admin), Phase 7 plan 07-04 (cms-editor). Any future role that requires
    operator-controlled bootstrap should follow this same shape — discord_id arg,
    User lookup by canonical D-002 identifier, defence-in-depth permission+role
    findOrCreate, idempotent hasRole gate, activity_log row on first grant."
  - "D-07-04-C — articles.delete is intentionally a super-admin-only permission
    AND ArticlePolicy::delete enforces hasRole('super-admin'). The two gates are
    independent on purpose (T-07-04-01 mitigation). Plan 07-12 (article retention
    + sitemap.xml) will inherit this constraint: a deleted article can re-emerge
    in the sitemap.xml feed via undeletion only by a super-admin."
  - "D-07-04-D — ArticlePolicy::update implements (own-author OR articles.publish)
    branch verbatim per plan <interfaces>. cms-editor role grants articles.publish
    by default, so the editorial cohort can edit each other's drafts without ceremony
    (matches the small-team realistic editorial workflow). Stricter author isolation
    (own-only) is reserved for plan 07-12 if it ever ships a 'guest author' permission
    that grants articles.update WITHOUT articles.publish."
  - "D-07-04-E — Pest test files live in tests/Feature/Permissions/ and tests/Feature/Console/
    (new directories — created here). The plan specified these paths verbatim;
    no existing convention to overrule. Future role+command test plans should
    continue to use these dirs."
metrics:
  duration: 3m 49s
  completed: 2026-05-14
  tasks: 2
  files_created: 5
  files_modified: 2
  commits: 2
---

# Phase 7 Plan 4: cms-editor Role + Article/Category Policies + Bootstrap Artisan

Phase 7 Wave 2 — wire the authorization surface for the editorial team.
PermissionSeeder grows from 2 to 8 permissions; cms-editor role transitions
from empty placeholder to fully-wired 6-permission grant; ArticlePolicy +
CategoryPolicy register with Laravel's Gate; the trenchwars:make-cms-editor
artisan bootstraps the initial editorial roster via the same Phase 1
trenchwars:make-admin idiom.

## Surface Delivered

### PermissionSeeder (apps/web/database/seeders/PermissionSeeder.php)

```text
8 permissions on 'web' guard:
  admin-access       (existing) → Filament panel gate (plan 01-12)
  audit.view         (existing) → /admin/audit (plan 01-14)
  articles.view      (NEW)       → Read drafts in Filament (plan 07-05)
  articles.create    (NEW)       → Author new articles (plan 07-05)
  articles.update    (NEW)       → Edit own drafts; editorial override via articles.publish
  articles.publish   (NEW)       → Move status draft → scheduled/published (plan 07-06)
  articles.delete    (NEW)       → Soft-delete articles (super-admin only)
  categories.manage  (NEW)       → Full CRUD on categories (plan 07-05)

2 roles on 'web' guard:
  super-admin → ALL 8 permissions (including articles.delete)
  cms-editor  → 6 permissions: admin-access + articles.view/create/update/publish
                + categories.manage (EXPLICITLY OMITS articles.delete)
```

### MakeCmsEditorCommand (apps/web/app/Console/Commands/MakeCmsEditorCommand.php)

```php
Signature: trenchwars:make-cms-editor {discord_id}

handle(PermissionRegistrar):
  1. forgetCachedPermissions
  2. find User by discord_id; FAILURE+"not found" if missing
  3. defence-in-depth findOrCreate of all 6 cms-editor permissions + cms-editor role
  4. role->syncPermissions to the canonical 6
  5. if !user->hasRole('cms-editor'): assignRole + activity_log row
  6. SUCCESS + "cms-editor granted to {username} (discord_id=...)"

Idempotent via hasRole() gate; second call is a no-op (no duplicate role row,
no duplicate activity_log entry).
```

### ArticlePolicy authorization matrix

| Ability  | Anonymous (?User null) | cms-editor                                    | super-admin |
|----------|------------------------|-----------------------------------------------|-------------|
| viewAny  | true                   | true                                          | true        |
| view     | true if published      | true (drafts via articles.update)             | true        |
| create   | false                  | true (articles.create)                        | true        |
| update   | false                  | own-author OR articles.publish (both granted) | true        |
| publish  | false                  | true (articles.publish)                       | true        |
| delete   | false                  | **false** (no articles.delete + no role)      | true        |

`before()` short-circuits everything except `delete` for users with `admin-access`,
so Filament admin without explicit articles.* grants can still author/publish.

### CategoryPolicy authorization matrix

| Ability         | Anonymous | cms-editor | super-admin |
|-----------------|-----------|------------|-------------|
| viewAny / view  | true      | true       | true        |
| create / update / delete | false | true (categories.manage) | true |

### AuthServiceProvider $policies (apps/web/app/Providers/AuthServiceProvider.php)

```php
protected $policies = [
    Clan::class           => ClanPolicy::class,
    ClanMembership::class => ClanMembershipPolicy::class,
    Article::class        => ArticlePolicy::class,           // NEW (07-04)
    Category::class       => CategoryPolicy::class,          // NEW (07-04)
];
```

## Test Surface (2 GREEN files, 12 it() blocks total)

| File | Pass count | Coverage |
|------|------------|----------|
| `tests/Feature/Permissions/CmsEditorRoleTest.php` | **8 GREEN** (target 5+) | admin-access + all 5 article perms + categories.manage via role inheritance; /admin reach via canAccessPanel; articles.delete denial (perm + policy gate); super-admin override; own-draft + senior-editor cross-author override; non-cms-editor /admin denial; categories.manage CRUD |
| `tests/Feature/Console/MakeCmsEditorCommandTest.php` | **4 GREEN** (target 3+) | happy path (role + permissions); missing-user error code; idempotency (single roles row); D-012 activity_log row on first grant only |

```text
Tests: 12 passed (35 assertions), 2.01s
```

No regressions in adjacent tests (PermissionSeederTest, FilamentPanelAccessTest,
MakeAdminCommandTest, ClanPolicy) — all 11 cross-suite tests still GREEN.

## Verification Line-by-Line (plan <verification>)

| Plan verification line | Result |
|------------------------|--------|
| `make pest --filter='CmsEditorRoleTest|MakeCmsEditorCommandTest'` GREEN | **PASS** — 12 passed / 35 assertions / 2.01s |
| `SELECT name FROM permissions WHERE name LIKE 'articles.%';` returns 5 rows | **PASS** — 5 rows: articles.{view,create,update,publish,delete} |
| cms-editor role joined to 6 permissions, `articles.delete` NOT among them | **PASS** — verified via psql query (6 permissions; articles.delete absent) |
| `php artisan list | grep trenchwars:make-cms-editor` finds the command | **PASS** — `trenchwars:make-cms-editor` registered |
| PHPStan L8 + Pint clean on new + edited files | **PASS** — phpstan [OK] on `app/Policies/`, `app/Providers/AuthServiceProvider.php`, `app/Console/Commands/MakeCmsEditorCommand.php`, `database/seeders/PermissionSeeder.php`. Pint --test PASS on 9 files (4 prod + 2 tests + 3 already-passing). |

## Pint + PHPStan Gates

| Gate | Files | Result |
|------|-------|--------|
| `pint --test` | All 7 task files (4 prod + 2 tests + AuthServiceProvider edit) | **PASS** (9 files, 0 fixes needed) |
| `phpstan analyse app/Policies/ app/Providers/AuthServiceProvider.php app/Console/Commands/MakeCmsEditorCommand.php database/seeders/PermissionSeeder.php` | 4 production-code files | **[OK] No errors** |
| `phpstan analyse` (full project, scoped to app/+database/+routes/+bootstrap/) | All paths | **[OK] No errors** |

Test files (`tests/Feature/Permissions/`, `tests/Feature/Console/`) are intentionally
NOT in PHPStan paths per `apps/web/phpstan.neon` (project convention — tests
exercised at runtime via Pest only). Matches Phase 1 plan 01-11 + Phase 2 plan
02-09 precedent.

## Deviations from Plan

None. The plan executed exactly as written. Two judgment calls were made
inside the spirit of the plan:

1. **ArticlePolicy::before() admin-bypass scope** — the plan `<interfaces>`
   did not explicitly call for a `before()` hook. I added one that bypasses
   everything EXCEPT `delete` (D-07-04-A), mirroring the Phase 2 ClanPolicy
   idiom and the project-wide convention. This is documented as a decision,
   not a deviation, because the plan's spec is honoured verbatim for every
   method signature; the `before()` is an additive defence-in-depth that
   the spec does not preclude. The `delete` carve-out keeps the plan's
   "super-admin role required" constraint intact even for admin-access
   users without super-admin role.

2. **Test count exceeded plan targets** — 8 CmsEditor it() blocks vs target
   5+; 4 MakeCmsEditor blocks vs target 3+. Added coverage for the senior-
   editor override path (T-07-04-02) and the D-012 activity_log audit
   side-effect to make the threat model machine-checkable.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-04-01 (cms-editor → articles.delete elevation) | **mitigated, double-gated** — PermissionSeeder omits articles.delete from cms-editor sync (verified via psql); ArticlePolicy::delete additionally requires hasRole('super-admin'). The `it denies cms-editor user the articles.delete permission` test asserts both layers (perm absent AND Gate::denies). |
| T-07-04-02 (cross-author overwrite within cms-editor cohort) | **mitigated** — ArticlePolicy::update branches on (actor.id === author_user_id OR actor->can('articles.publish')). Verified via two it() blocks: own-draft happy path + editorial-override path. |
| T-07-04-03 (anonymous user viewing drafts) | **mitigated (policy-side)** — ArticlePolicy::view enforces status='published' for non-actor + non-editor cases. Plan 07-09 BlogShowController will call `Gate::authorize('view', $article)` for the controller-side enforcement. |
| T-07-04-04 (artisan grants wrong user) | **accepted** — Operator-driven; discord_id is canonical D-002 identity. MakeCmsEditorCommand echoes `{username} (discord_id=...)` before exit so operator can visually verify before re-prompt. |
| T-07-04-05 (spatie guard mismatch cms-editor) | **mitigated** — Role::findOrCreate('cms-editor', 'web') pins the 'web' guard explicitly (D-018 Pitfall 4). canAccessPanel test passing for cms-editor user proves the guard resolution end-to-end (Filament's auth guard → User::canAccessPanel → hasPermissionTo('admin-access', 'web')). |
| T-07-04-06 (role assignment lacks audit trail) | **mitigated** — MakeCmsEditorCommand explicitly writes an activity_log row on first grant (`'cms-editor role granted via CLI'` with `command` + `discord_id` properties). The `it writes an activity_log row on first grant only` test asserts the row is written ONCE across two invocations (idempotency + audit are jointly preserved). |

## Open Question Resolutions

| OQ | Resolution |
|----|------------|
| OQ-2 (initial editorial team bootstrap) | **LOCKED inline via MakeCmsEditorCommand.** The artisan-command pattern (mirror of trenchwars:make-admin) is the canonical bootstrap path. Operator runs `make artisan ARGS="trenchwars:make-cms-editor <discord_id>"` for each initial editor after first deploy. |
| OQ-3 (starter category set) | **LOCKED in plan 07-03's CategorySeeder.** 4 starter categories: News, Match Reports, Tournament Updates, Community. Re-referenced in PermissionSeeder docblock so the plumbing is discoverable from the authz seeder. |

## Pre-existing AuthServiceProvider $policies entries

From Phase 2 plan 02-09:
- `Clan::class => ClanPolicy::class`
- `ClanMembership::class => ClanMembershipPolicy::class`

Plan 07-04 added (additive, non-breaking):
- `Article::class => ArticlePolicy::class`
- `Category::class => CategoryPolicy::class`

The `boot()` method still calls `registerPolicies()` only (no additional Gate::define calls were needed).

## Known Stubs

None. Every method in both new policies is fully implemented. The artisan
command has no TODOs. PermissionSeeder is final.

## Threat Flags

None. The plan's `<threat_model>` already covered every surface introduced
here (cms-editor role grants, ArticlePolicy method authorization, artisan
command bootstrap, audit log writes). No new endpoints, no new file access
patterns, no new schema changes at trust boundaries.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: Extend PermissionSeeder + ship MakeCmsEditorCommand | `1031292` | 2 (1 created + 1 modified) |
| 2: ArticlePolicy + CategoryPolicy + AuthServiceProvider + 2 GREEN test files | `36fef91` | 5 (4 created + 1 modified) |

## Self-Check

- [x] `apps/web/database/seeders/PermissionSeeder.php` — FOUND (modified)
- [x] `apps/web/app/Console/Commands/MakeCmsEditorCommand.php` — FOUND
- [x] `apps/web/app/Policies/ArticlePolicy.php` — FOUND
- [x] `apps/web/app/Policies/CategoryPolicy.php` — FOUND
- [x] `apps/web/app/Providers/AuthServiceProvider.php` — FOUND (modified)
- [x] `apps/web/tests/Feature/Permissions/CmsEditorRoleTest.php` — FOUND
- [x] `apps/web/tests/Feature/Console/MakeCmsEditorCommandTest.php` — FOUND
- [x] commit `1031292` — FOUND in git log
- [x] commit `36fef91` — FOUND in git log

## Self-Check: PASSED
