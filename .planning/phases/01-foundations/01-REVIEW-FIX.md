---
phase: 01-foundations
fixed_at: 2026-05-04T21:30:00Z
review_path: .planning/phases/01-foundations/01-REVIEW.md
iteration: 1
findings_in_scope: 12
fixed: 12
skipped: 0
status: all_fixed
quality_gate:
  pint: pass
  phpstan: pass
  pest: pass
  pest_note: "65 tests / 219 assertions; required `apps/web/public/build/manifest.json` (gitignored) to be present — see IN-05 (out of scope) for the Vite-build CI gap that will need resolving in v2."
---

# Phase 1: Code Review Fix Report

**Fixed at:** 2026-05-04T21:30:00Z
**Source review:** `.planning/phases/01-foundations/01-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope (CR + WR): 12
- Fixed: 12
- Skipped: 0
- Info findings (IN-01 through IN-07): out of scope this run (`fix_scope=critical_warning`).

All 4 Critical and 8 Warning findings were fixed in atomic per-finding commits inside an isolated worktree (`/tmp/sv-01-reviewfix-WHTmv2`, branch `gsd-reviewfix/01-58655`). The cleanup tail fast-forwards `master` to that branch on exit.

## Quality Gate

Run via one-off containers that mount the worktree as `/app` while reusing the live web container's `vendor/` and `node_modules` volumes (`--volumes-from trenchwars-web -v "$wt/apps/web:/app"`):

| Gate | Result | Notes |
|---|---|---|
| `./vendor/bin/pint --test` | PASS (93 files) | 4 files were auto-fixed during the run; reformat committed as `7a3bab2`. |
| `./vendor/bin/phpstan analyse` | PASS | `[OK] No errors` at level 8. |
| `./vendor/bin/pest --testsuite=Feature` | PASS (65 tests / 219 assertions) | Requires `apps/web/public/build/manifest.json` to exist — gitignored, populated only by `pnpm run build`. This is exactly the IN-05 finding (out of scope). To reproduce green: `cd apps/web && pnpm run build` then run pest. |

## Fixed Issues

### CR-01: `MakeAdminCommand` violates D-012 — no audit-log entry on privilege grant

**Files modified:** `apps/web/app/Console/Commands/MakeAdminCommand.php`, `apps/web/tests/Feature/Auth/MakeAdminCommandTest.php`
**Commit:** `714a057` (combined with WR-04 since both touch the same handler in the same logical edit)
**Applied fix:** Added an explicit `activity()->performedOn($user)->withProperties([...])->log('Super-admin granted via CLI')` call right after the `assignRole` step. Test asserts the resulting `Activity` row has `subject_type=User`, `subject_id=$user->id`, `description='Super-admin granted via CLI'`, and the expected `properties` payload.

### CR-02: `PlayerResource::form` corrupts the `bio` JSONB field via `Textarea`

**Files modified:** `apps/web/app/Filament/Resources/PlayerResource.php`, `apps/web/lang/en/admin.php`, `apps/web/tests/Feature/Admin/PlayerResourceBioFieldTest.php`
**Commits:** `e842923` (initial), `6b85769` (Postgres JSONB key-order assertion fix)
**Applied fix:** Replaced `Textarea::make('bio')` with `KeyValue::make('bio')` so the locale-keyed array shape (`{en: "...", cs: "..."}`) round-trips correctly via the form. Added `admin.player.fields.bio_locale` and `admin.player.fields.bio_text` strings. Pinned the choice via two Pest assertions: a source-text scan for `KeyValue::make('bio')` (and that `Textarea::make('bio')` is gone), and a model save+reload that verifies the associative shape persists. The reload assertion is order-independent (Postgres jsonb does not preserve key insertion order).
**Status:** fixed: requires human verification in browser — the source-pinned test confirms the schema change, and the model round-trip confirms persistence, but the actual Filament KeyValue form UI behaviour should be smoke-tested manually before phase 1 is signed off.

### CR-03: `add_uuid_columns_to_activity_log` migration is irreversibly destructive

**Files modified:** `apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php`
**Commit:** `99749d8`
**Applied fix:** Added a `SELECT COUNT(*) FROM activity_log` guard at the top of `up()` that throws a `RuntimeException` if the table has any rows. Replaced `USING NULL` with the safe explicit cast `USING subject_id::text::uuid` (and the same for `causer_id`). Implemented a real `down()` that drops indexes, casts back to bigint (with `USING NULL` since uuid → bigint has no preserving cast), and rebuilds the indexes. The "destroy data on rollback" tradeoff is documented in the down() docblock.

### CR-04: `DiscordController::callback` persists empty username when Discord returns null

**Files modified:** `apps/web/app/Http/Controllers/Auth/DiscordController.php`, `apps/web/tests/Feature/Auth/DiscordOAuthTest.php`
**Commit:** `eb4067f`
**Applied fix:** Computed `$rawUsername = trim((string) ($discordUser->getNickname() ?: $discordUser->getName() ?: ''))` and short-circuit to `redirect()->route('home')->with('error', __('auth.discord.error.provider'))` when it is empty. The `User::updateOrCreate` call now uses the validated `$rawUsername`. Pest test added that mocks Socialite returning `nickname=null, name=null` and asserts the response is a redirect to home with an `error` flash and no User row written.

### WR-01: `ProvisionFirstLogin` doesn't catch race-condition `UniqueViolation` on Player insert

**Files modified:** `apps/web/app/Listeners/ProvisionFirstLogin.php`, `apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php`
**Commit:** `168a2ac`
**Applied fix:** Wrapped the `$user->player()->create([...])` + `PlayerPrivacy::create([...])` block in `try { ... } catch (\Illuminate\Database\UniqueConstraintViolationException) { /* idempotent */ }`. Added a Pest test that simulates the race deterministically by invoking the listener twice with `setRelation('player', null)` between calls; without the catch, the second call throws — with the catch, the second call is a no-op and exactly one player row exists.

### WR-02: `useTheme.ts` shared module-level state risks SSR pollution

**Files modified:** `apps/web/resources/js/composables/useTheme.ts`
**Commit:** `98d258d`
**Applied fix:** Detected the browser environment via `typeof window !== 'undefined' && typeof document !== 'undefined'`. Under SSR, `useTheme()` now returns a per-call (per-request) shadow ref that does not mutate any module-level state, eliminating cross-request leak. In the browser, a singleton `browserTheme` ref is created at module-load time, hydrated once from `localStorage`, and observed by a single `watchEffect` registered exactly once at module scope (no longer once per consumer). Verified by transpile (Tier 2).
**Status:** fixed: requires human verification — exercising the SSR path requires a real Node SSR run; the static analysis confirms the new shape but a quick `pnpm run build:ssr` smoke test is recommended.

### WR-03: `inertia.d.ts` `auth` prop shape mismatches `HandleInertiaRequests::share()` output

**Files modified:** `apps/web/resources/js/types/inertia.d.ts`, `apps/web/resources/js/pages/Home.vue`, `apps/web/tests/Feature/InertiaSmokeTest.php`
**Commit:** `d6cb202`
**Applied fix:** Replaced the incorrect `auth: { user: {...} | null }` envelope with the actual flat shape `auth: AuthUser | null` and exported the `AuthUser` interface from `inertia.d.ts`. Removed the now-unnecessary `as AuthUser` cast in `Home.vue` and pulled the type via `import type { AuthUser } from '@/types/inertia'`. Added two Pest assertions to lock the shape from the PHP side: (a) anonymous request → `where('auth', null)`, (b) authenticated request → `has('auth', fn ($auth) => $auth->where('id', ...)->where('discord_id', ...)...)` plus `missing('auth.user')` to fail loudly if anyone reintroduces the envelope.

### WR-04: `MakeAdminCommand` indiscriminately grants `Permission::all()` to super-admin

**Files modified:** `apps/web/app/Console/Commands/MakeAdminCommand.php` (in CR-01 commit), `apps/web/database/seeders/PermissionSeeder.php`
**Commits:** `714a057` (MakeAdminCommand half), `f8e1c7c` (PermissionSeeder mirror)
**Applied fix:** Replaced both `$role->givePermissionTo(Permission::all())` and `$superAdmin->syncPermissions(Permission::all())` with a whitelisted `whereIn('name', $superAdminPermissions)->get()` query. The whitelist (`['admin-access', 'audit.view']`) is duplicated in both files with a comment that they must stay in lockstep. Pest test added in `MakeAdminCommandTest.php` that creates a `'rogue.permission'` row before running the command and asserts `super-admin` does NOT inherit it (only `admin-access` + `audit.view`).

### WR-05: `PermissionResource` allows editing `name` — silently breaks code references

**Files modified:** `apps/web/app/Filament/Resources/PermissionResource.php`, `apps/web/tests/Feature/Admin/PermissionResourceLockedTest.php`
**Commit:** `3051869`
**Applied fix:** Marked both `TextInput::make('name')` and `Select::make('guard_name')` as `->disabled()->dehydrated(false)` in the form schema. The fields render but values dehydrate to nothing on submit, so a malicious or accidental rename is dropped before reaching the model. Pest test asserts the Create page is 404, the source contains `->disabled()` and `->dehydrated(false)`, and a no-op save round-trip preserves the original `name`.

### WR-06: `Audit` page exposes the database event values without translating them

**Files modified:** `apps/web/app/Filament/Pages/Audit.php`, `apps/web/lang/en/admin.php`
**Commit:** `ab8badc`
**Applied fix:** Replaced the hardcoded English values in the `event` SelectFilter with `__('admin.audit.event.created')` (and the rest), added `->label(__('admin.audit.filter.event'))` etc. on every filter, and routed the `subject_type` column's `class_basename($state)` output through `__('admin.audit.subject.' . $basename)` with a fallback to the raw basename when no translation is registered. Added the `admin.audit.event.*`, `admin.audit.filter.*`, and `admin.audit.subject.*` namespaces to `lang/en/admin.php` (User, Player, Role, Permission seeded).

### WR-07: `RedirectFilamentAuthToDiscord::redirectTo($request)` lacks parameter type — PHPStan level 8

**Files modified:** `apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php`
**Commit:** `67cd5d2`
**Applied fix:** Added `use Illuminate\Http\Request;` and the `@param Request $request` PHPDoc directly above `protected function redirectTo($request): string`. Kept the parameter untyped at the signature level to remain Liskov-compatible with `Filament\Http\Middleware\Authenticate::redirectTo($request): ?string` (which itself is untyped). The PHPDoc gives PHPStan level 8 the type information it needs without breaking the override contract.

### WR-08: `audit-tab.blade.php` allows N+1 on `$activity->causer`

**Files modified:** `apps/web/resources/views/filament/partials/audit-tab.blade.php`
**Commit:** `a404d5d`
**Applied fix:** Added `->with('causer')` to the `Activity::query()` chain so the 50-row loop emits one users-table query instead of 50.

## Skipped Issues

None — all 12 in-scope findings were fixed.

## Info findings (out of scope, recorded for visibility)

These were NOT fixed this run (`fix_scope=critical_warning`). They remain in `01-REVIEW.md` for the next iteration:

- **IN-01:** `useT` bare-export double-call inefficiency.
- **IN-02:** `validation.custom.*` placeholder leaks into Inertia `translations` shared prop.
- **IN-03:** `useT` parameter substitution iteration-order dependence.
- **IN-04:** Discord email column has no UNIQUE / `verified_at`.
- **IN-05:** `LayoutTokensTest` flakiness on fresh checkouts (no `pnpm run build` in CI). **Note: this caused the entire pest suite to fail in fresh worktrees during this run; documented in `quality_gate.pest_note` above.**
- **IN-06:** `TypescriptGenerateCommand` hardcoded `/repo/...` path.
- **IN-07:** `Audit` page `latest('id')` instead of `latest('created_at')`.

---

_Fixed: 2026-05-04T21:30:00Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
