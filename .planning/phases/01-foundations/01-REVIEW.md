---
phase: 01-foundations
reviewed: 2026-05-04T20:55:00Z
depth: standard
files_reviewed: 121
files_reviewed_list:
  - .github/workflows/bot.yml
  - .github/workflows/rcon-worker.yml
  - .github/workflows/shared-types.yml
  - .github/workflows/web.yml
  - apps/bot/eslint.config.mjs
  - apps/bot/nixpacks.toml
  - apps/bot/package.json
  - apps/bot/railway.json
  - apps/bot/src/index.ts
  - apps/bot/tests/skeleton.test.ts
  - apps/bot/vitest.config.ts
  - apps/rcon-worker/eslint.config.mjs
  - apps/rcon-worker/nixpacks.toml
  - apps/rcon-worker/package.json
  - apps/rcon-worker/railway.json
  - apps/rcon-worker/src/index.ts
  - apps/rcon-worker/tests/skeleton.test.ts
  - apps/rcon-worker/vitest.config.ts
  - apps/web/.gitignore
  - apps/web/app/Concerns/HasUuidPrimaryKey.php
  - apps/web/app/Console/Commands/MakeAdminCommand.php
  - apps/web/app/Console/Commands/TypescriptGenerateCommand.php
  - apps/web/app/Data/PlayerData.php
  - apps/web/app/Data/PlayerPrivacyData.php
  - apps/web/app/Data/UserData.php
  - apps/web/app/Filament/Pages/Audit.php
  - apps/web/app/Filament/Resources/PermissionResource.php
  - apps/web/app/Filament/Resources/PermissionResource/Pages/EditPermission.php
  - apps/web/app/Filament/Resources/PermissionResource/Pages/ListPermissions.php
  - apps/web/app/Filament/Resources/PlayerResource.php
  - apps/web/app/Filament/Resources/PlayerResource/Pages/EditPlayer.php
  - apps/web/app/Filament/Resources/PlayerResource/Pages/ListPlayers.php
  - apps/web/app/Filament/Resources/PlayerResource/Pages/ViewPlayer.php
  - apps/web/app/Filament/Resources/RoleResource.php
  - apps/web/app/Filament/Resources/RoleResource/Pages/CreateRole.php
  - apps/web/app/Filament/Resources/RoleResource/Pages/EditRole.php
  - apps/web/app/Filament/Resources/RoleResource/Pages/ListRoles.php
  - apps/web/app/Filament/Resources/UserResource.php
  - apps/web/app/Filament/Resources/UserResource/Pages/EditUser.php
  - apps/web/app/Filament/Resources/UserResource/Pages/ListUsers.php
  - apps/web/app/Filament/Resources/UserResource/Pages/ViewUser.php
  - apps/web/app/Http/Controllers/Auth/DiscordController.php
  - apps/web/app/Http/Controllers/Auth/LogoutController.php
  - apps/web/app/Http/Middleware/HandleInertiaRequests.php
  - apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php
  - apps/web/app/Listeners/ProvisionFirstLogin.php
  - apps/web/app/Models/Player.php
  - apps/web/app/Models/PlayerPrivacy.php
  - apps/web/app/Models/User.php
  - apps/web/app/Providers/AppServiceProvider.php
  - apps/web/app/Providers/Filament/AdminPanelProvider.php
  - apps/web/app/Providers/TypeScriptTransformerServiceProvider.php
  - apps/web/bootstrap/providers.php
  - apps/web/composer.json
  - apps/web/config/activitylog.php
  - apps/web/config/i18n.php
  - apps/web/config/permission.php
  - apps/web/database/factories/PlayerFactory.php
  - apps/web/database/factories/PlayerPrivacyFactory.php
  - apps/web/database/factories/UserFactory.php
  - apps/web/database/migrations/2026_05_03_100000_create_users_table.php
  - apps/web/database/migrations/2026_05_03_100100_create_players_table.php
  - apps/web/database/migrations/2026_05_03_100200_create_player_privacy_table.php
  - apps/web/database/migrations/2026_05_03_110000_create_permission_tables.php
  - apps/web/database/migrations/2026_05_03_140000_create_activity_log_table.php
  - apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php
  - apps/web/database/seeders/DatabaseSeeder.php
  - apps/web/database/seeders/PermissionSeeder.php
  - apps/web/lang/en/admin.php
  - apps/web/lang/en/auth.php
  - apps/web/lang/en/common.php
  - apps/web/lang/en/home.php
  - apps/web/lang/en/validation.php
  - apps/web/nixpacks.toml
  - apps/web/package.json
  - apps/web/postcss.config.js
  - apps/web/railway.json
  - apps/web/resources/css/app.css
  - apps/web/resources/css/filament/admin/theme.css
  - apps/web/resources/js/app.ts
  - apps/web/resources/js/components/LoginButton.vue
  - apps/web/resources/js/components/ThemeToggle.vue
  - apps/web/resources/js/components/Wordmark.vue
  - apps/web/resources/js/components/icons/DiscordIcon.vue
  - apps/web/resources/js/components/ui/Button.vue
  - apps/web/resources/js/components/ui/IconButton.vue
  - apps/web/resources/js/composables/useT.ts
  - apps/web/resources/js/composables/useTheme.ts
  - apps/web/resources/js/layouts/PublicLayout.vue
  - apps/web/resources/js/pages/Home.vue
  - apps/web/resources/js/types/api.d.ts
  - apps/web/resources/js/types/inertia.d.ts
  - apps/web/resources/views/filament/pages/audit.blade.php
  - apps/web/resources/views/filament/partials/audit-tab.blade.php
  - apps/web/routes/web.php
  - apps/web/tailwind.filament.config.js
  - apps/web/tests/Feature/Admin/FilamentBootTest.php
  - apps/web/tests/Feature/Admin/FilamentPanelAccessTest.php
  - apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php
  - apps/web/tests/Feature/Audit/ActivityLoggedOnAdminMutationsTest.php
  - apps/web/tests/Feature/Audit/AuditPageTest.php
  - apps/web/tests/Feature/Auth/DiscordOAuthTest.php
  - apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php
  - apps/web/tests/Feature/Auth/MakeAdminCommandTest.php
  - apps/web/tests/Feature/Auth/PermissionSeederTest.php
  - apps/web/tests/Feature/Data/TypescriptTransformTest.php
  - apps/web/tests/Feature/Home/LayoutTokensTest.php
  - apps/web/tests/Feature/I18n/NoHardcodedStringsTest.php
  - apps/web/tests/Feature/I18n/TranslationsSharedTest.php
  - apps/web/tests/Feature/I18n/ValidationMessagesLocalizedTest.php
  - apps/web/tests/Feature/Models/PlayerModelTest.php
  - apps/web/tests/Feature/Models/PlayerPrivacyModelTest.php
  - apps/web/tests/Feature/Models/UserModelTest.php
  - apps/web/vite.config.ts
  - apps/web/vite.filament.config.ts
  - docker-compose.yml
  - packages/shared-types/package.json
  - packages/shared-types/scripts/sync-types.sh
  - packages/shared-types/src/api.d.ts
  - packages/shared-types/src/index.ts
  - railway.toml
findings:
  critical: 4
  warning: 8
  info: 7
  total: 19
status: issues_found
---

# Phase 1: Code Review Report

**Reviewed:** 2026-05-04T20:55:00Z
**Depth:** standard
**Files Reviewed:** 121
**Status:** issues_found

## Summary

Phase 1 ships the Trenchwars foundation: Discord OAuth auth flow, identity models (User/Player/PlayerPrivacy) with UUID PKs, spatie/laravel-permission with explicit `web` guard pinning, four Filament v3 admin resources, an audit log + page, and the Vue/Inertia/Tailwind v4 public surface. Pitfalls 1–5, 8 are correctly mitigated (no `<meta csrf-token>`, citext+pgcrypto in first migration, dual-Tailwind workaround, `default_guard_name => 'web'`, `redirectTo()` override for missing `login` route, in-house `useT()` instead of laravel-vue-i18n's plugin).

The OAuth callback flow is generally well-shaped (Socialite state CSRF, session regeneration, transactional first-login provisioning) but several defects need attention before this ships:

- **One D-012 audit violation** (BLOCKER): the privileged `trenchwars:make-admin` command grants super-admin without writing an `activity_log` row.
- **One data-corruption bug** (BLOCKER): `PlayerResource` exposes the JSONB `bio` column as a plain Textarea, which silently strips data on save (cast incompatibility).
- **One destructive migration** (BLOCKER): the activity_log UUID column ALTER uses `USING NULL`, irreversibly nulling any pre-existing FK-like data.
- **One auth correctness bug** (BLOCKER): empty username persistence path when Discord returns no nickname/name.
- **Several Warnings** around concurrent first-login race handling, SSR state pollution in `useTheme`, type-shape mismatch between `inertia.d.ts` and `HandleInertiaRequests::share()`, and the `MakeAdminCommand`'s indiscriminate `Permission::all()` grant.

## Critical Issues

### CR-01: `MakeAdminCommand` violates D-012 — no audit-log entry on privilege grant

**File:** `apps/web/app/Console/Commands/MakeAdminCommand.php:42-50`
**Issue:** Granting the most powerful role in the system (`super-admin` with all permissions, including future ones) writes nothing to `activity_log`. This contradicts CLAUDE.md §6 ("Activity log writes are append-only via the LogsActivity trait — Filament admin UI never exposes edit/delete on `activity_log` rows") and D-012 ("Every domain entity gets a Filament resource; per-resource Audit tab + global `/admin/audit` page; spatie/laravel-activitylog as the engine").

`spatie/laravel-permission`'s `givePermissionTo()` and `assignRole()` are silent by default — `config/permission.php` has `'events_enabled' => false` (line 147), and even when enabled they don't auto-write to activity_log. Result: any operator (or compromised CLI shell) can mint a super-admin without leaving a trace anyone can find via the audit page.

**Fix:**
```php
public function handle(PermissionRegistrar $registrar): int
{
    // ... existing user lookup ...

    Permission::findOrCreate('admin-access', 'web');
    $role = Role::findOrCreate('super-admin', 'web');
    $role->givePermissionTo(Permission::all());

    $user->givePermissionTo('admin-access');
    $user->assignRole('super-admin');

    activity()
        ->performedOn($user)
        ->withProperties(['command' => 'trenchwars:make-admin', 'discord_id' => $discordId])
        ->log('Super-admin granted via CLI');

    $registrar->forgetCachedPermissions();

    $this->info("Admin granted to {$user->username} (discord_id={$discordId}).");

    return self::SUCCESS;
}
```

Add a corresponding test assertion to `MakeAdminCommandTest.php` that an `Activity` row is written.

---

### CR-02: `PlayerResource::form` corrupts the `bio` JSONB field via `Textarea`

**File:** `apps/web/app/Filament/Resources/PlayerResource.php:82-85`
**Issue:** The `bio` column is `jsonb` with an Eloquent `array` cast (Player.php:48). The Filament form binds it to `Forms\Components\Textarea::make('bio')`, which sends a raw string back on save. When Eloquent receives a non-array value for an `array`-cast attribute, the JSON serialiser stores it as a JSON string scalar (e.g., typing `hello world` produces stored value `"hello world"`), or — depending on the Postgres driver and value — fails entirely with a JSONB cast error. Either way the locale-keyed shape (`{"en": "..."}`) documented in `admin.player.help.bio_jsonb` is silently broken. Phase 2's `HasTranslations` trait will read this corrupted column and either throw or surface garbage.

**Fix:** Until the Phase 2+ structured translatable editor lands, either disable the field or use a KeyValue input that round-trips an array:
```php
Forms\Components\KeyValue::make('bio')
    ->label(__('admin.player.fields.bio'))
    ->keyLabel('Locale')
    ->valueLabel('Bio text')
    ->reorderable(false)
    ->helperText(__('admin.player.help.bio_jsonb')),
```
Or, defer entirely:
```php
Forms\Components\Placeholder::make('bio_placeholder')
    ->label(__('admin.player.fields.bio'))
    ->content(__('admin.player.help.bio_jsonb')),
// (no read/write — Phase 2 introduces the structured editor)
```

Add a Pest test that asserts editing `bio` via the Filament form preserves the `{locale: text}` shape.

---

### CR-03: `add_uuid_columns_to_activity_log` migration is irreversibly destructive

**File:** `apps/web/database/migrations/2026_05_03_140100_add_uuid_columns_to_activity_log.php:30-31`
**Issue:** The `up()` method runs:
```php
DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING NULL;');
DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE uuid USING NULL;');
```
The `USING NULL` clause **discards every existing `subject_id` and `causer_id` value** in the table during the cast. The header comment claims "table is empty at install time" — but nothing enforces that. If a developer runs the seeder, then `php artisan migrate` (e.g., new migration added later that triggers this re-run path on a non-trivial DB), or if migrations run on staging with seeded data, the entire audit-log linkage to subjects/causers is wiped silently. Worse, the `down()` method is empty (`// Down-migration not provided`), so `migrate:rollback` will leave the schema with uuid columns but no recovery path.

This is a P1 issue and not strictly under load yet, but the pattern is dangerous and gets enshrined in the migration history forever once shipped.

**Fix:** Replace `USING NULL` with a safe cast that preserves data when possible, and guard with a row-count check that fails loudly if data is present:

```php
public function up(): void
{
    $rowCount = (int) DB::scalar('SELECT COUNT(*) FROM activity_log');
    if ($rowCount > 0) {
        throw new \RuntimeException(
            "activity_log has {$rowCount} rows — UUID column conversion would destroy data. ".
            'Run a manual data migration first (see migration source comment).'
        );
    }

    DB::statement('DROP INDEX IF EXISTS subject;');
    DB::statement('DROP INDEX IF EXISTS causer;');

    DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING subject_id::text::uuid;');
    DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE uuid USING causer_id::text::uuid;');

    DB::statement('CREATE INDEX subject ON activity_log (subject_type, subject_id);');
    DB::statement('CREATE INDEX causer  ON activity_log (causer_type, causer_id);');
}

public function down(): void
{
    DB::statement('DROP INDEX IF EXISTS subject;');
    DB::statement('DROP INDEX IF EXISTS causer;');
    DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE bigint USING NULL;');
    DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id  TYPE bigint USING NULL;');
    DB::statement('CREATE INDEX subject ON activity_log (subject_type, subject_id);');
    DB::statement('CREATE INDEX causer  ON activity_log (causer_type, causer_id);');
}
```

The simpler — and arguably better — fix is to inline the UUID column types into the original `create_activity_log_table.php` migration (skipping the spatie published stub's bigint default), which removes this migration entirely.

---

### CR-04: `DiscordController::callback` persists empty username when Discord returns null

**File:** `apps/web/app/Http/Controllers/Auth/DiscordController.php:58`
**Issue:** Username is computed as `(string) ($discordUser->getNickname() ?: $discordUser->getName())`. When both `getNickname()` and `getName()` return null/empty (rare but possible for new Discord accounts before username migration completes, or for stripped OAuth scopes), the `(string) null` cast silently produces `''`. The `users.username` column is `text NOT NULL` but has no length/empty CHECK constraint, so the empty string passes the DB write. The downstream consequences are severe:

- `Player.slug = Str::slug('') . '-' . Str::lower(Str::random(4))` produces a slug starting with `-` (e.g., `-a3k9`), violating the slug uniqueness expectation and looking malformed.
- Filament's `getFilamentName(): string` returns `''` — the panel shows a blank avatar tooltip.
- The `Signed in as :name.` flash message reads `Signed in as .`.

**Fix:** Validate Discord identity returns a non-empty username before persisting; reject the OAuth flow with a clear error otherwise.

```php
$rawUsername = trim((string) ($discordUser->getNickname() ?: $discordUser->getName() ?: ''));
if ($rawUsername === '') {
    return redirect()->route('home')->with(
        'error',
        __('auth.discord.error.provider'),
    );
}

$user = User::updateOrCreate(
    ['discord_id' => (string) $discordUser->getId()],
    [
        'username' => $rawUsername,
        'email' => $discordUser->getEmail(),
        'avatar_url' => $discordUser->getAvatar(),
        'locale' => $discordUser->user['locale'] ?? config('app.locale', 'en'),
    ],
);
```

Add a Pest case to `DiscordOAuthTest.php` that asserts the callback redirects to home with an error flash when Discord returns `nickname=null, name=null`.

---

## Warnings

### WR-01: `ProvisionFirstLogin` doesn't catch race-condition `UniqueViolation` on Player insert

**File:** `apps/web/app/Listeners/ProvisionFirstLogin.php:42-50`
**Issue:** The "first-login is idempotent" claim assumes the `if ($user->player !== null) return;` check eliminates duplicate writes. Under concurrent first-login attempts for the same `discord_id` (two browser tabs, retried request, etc.), `User::updateOrCreate` is atomic but the listener's read-then-write on `players.user_id` is not. The DB-level `UNIQUE (user_id)` constraint on the `players` table (migration line 26) catches it, but the resulting `Illuminate\Database\QueryException` propagates out of the listener — bubbling through `Auth::login`'s event dispatch and producing a 500 to the second concurrent client, even though the first request succeeded.

**Fix:** Wrap the Player+PlayerPrivacy insert in a try/catch that swallows the unique-violation case (the Player already exists, so the listener has nothing to do):
```php
try {
    /** @var Player $player */
    $player = $user->player()->create([
        'slug' => Str::slug($user->username) . '-' . Str::lower(Str::random(4)),
        'display_name' => null,
    ]);

    PlayerPrivacy::create([
        'player_id' => $player->id,
        // ...
    ]);
} catch (\Illuminate\Database\UniqueConstraintViolationException) {
    // Concurrent first-login race — the parallel request already provisioned. Idempotent.
}
```

Add a Pest test that runs two concurrent `/auth/discord/callback` requests for the same Discord ID (via parallel `Http::async` or transaction-level simulation) and asserts the second receives a 302, not 500.

---

### WR-02: `useTheme.ts` shared module-level state risks SSR pollution

**File:** `apps/web/resources/js/composables/useTheme.ts:7-9, 31`
**Issue:** `theme` is declared at module scope and reused by every `useTheme()` call. Two issues:

1. **SSR state leak**: `vite.config.ts` declares `ssr: 'resources/js/ssr.ts'`. Under server-side rendering, module-level state is shared across concurrent requests in the same Node process, so request A's theme toggle would change the rendered theme of request B. The `applyTheme()` function guards `document` access (good), but the underlying `theme.value` ref leaks.
2. **Multiple `watchEffect` registrations**: every component that calls `useTheme()` registers a new `watchEffect(() => applyTheme(theme.value))`. After mounting `LoginButton`, `ThemeToggle`, etc., there are N effect handlers all writing the same theme to localStorage on every change. Cosmetic perf; the SSR concern is the real risk.

**Fix:** Move the side-effect registration out of the composable and into `app.ts` (client-only), and read theme inside `useTheme()` from a singleton. Or guard the watchEffect with `if (typeof window !== 'undefined')` and register it only once via a top-level setup script in `app.ts`. SSR best practice is to keep composables pure and lift mutable globals out of module scope.

---

### WR-03: `inertia.d.ts` `auth` prop shape mismatches `HandleInertiaRequests::share()` output

**File:** `apps/web/resources/js/types/inertia.d.ts:7-13` vs `apps/web/app/Http/Middleware/HandleInertiaRequests.php:43`
**Issue:** PHP shares `'auth' => fn () => $request->user()?->only(['id', 'discord_id', 'username', 'avatar_url'])` — i.e., the user fields directly under `auth`, OR `null`. The TypeScript declaration says:
```ts
auth: {
    user: {
        id: string;
        // ...
    } | null;
};
```
This wraps the user inside an `auth.user` envelope that the server never produces. `Home.vue:22` correctly treats `page.props.auth` as the user object directly with `(page.props.auth as AuthUser | null)` — bypassing the (incorrect) declared type via the `as` cast. Anyone trusting the declared type and writing `usePage().props.auth?.user.username` will get `undefined` at runtime.

**Fix:** Align the declaration with the actual shape:
```ts
auth: {
    id: string;
    discord_id: string;
    username: string;
    avatar_url: string | null;
} | null;
```
Or change the PHP shape to match the declaration. Either is fine; pick one and ensure both ends agree. Add an Inertia assertion test that pins the shape.

---

### WR-04: `MakeAdminCommand` indiscriminately grants `Permission::all()` to super-admin

**File:** `apps/web/app/Console/Commands/MakeAdminCommand.php:44`
**Issue:** `$role->givePermissionTo(Permission::all())` syncs whatever permissions exist in the DB at command-run time to `super-admin`. Today that's the two seeded permissions (`admin-access`, `audit.view`), which is fine. But if Phase 2+ adds permissions through migrations or admin actions BEFORE this command runs again, those get auto-attached. If a malicious or buggy admin creates an arbitrary permission row (currently mitigated by `PermissionResource` having no Create page, but `PermissionResource` does expose Edit), they could rename an existing permission and have `super-admin` silently inherit it.

More concretely: `PermissionResource::form` allows editing the `name` field of any permission. Renaming `audit.view` → `everything.allowed` doesn't create a new permission, but combined with future create paths (Phase 2+) it's a foot-gun.

**Fix:** Whitelist the permissions super-admin gets:
```php
$superAdminPermissions = [
    'admin-access',
    'audit.view',
];
$role->syncPermissions(
    Permission::whereIn('name', $superAdminPermissions)->get()
);
```
And mirror the same change in `PermissionSeeder.php:35` (currently `$superAdmin->syncPermissions(Permission::all())` — same vulnerability).

---

### WR-05: `PermissionResource` allows editing `name` — silently breaks code references

**File:** `apps/web/app/Filament/Resources/PermissionResource.php:46-50`
**Issue:** The Edit form lets an admin rename `admin-access` to anything. Codebase code paths (`User::canAccessPanel`, `MakeAdminCommand`, `FilamentPanelAccessTest`, etc.) hard-code the string `'admin-access'`. Renaming via Filament locks every existing admin out of the panel until someone hand-edits the DB or re-runs the seeder. There's no compelling reason to allow rename in P1 — permissions are a developer concern, not an admin concern (the resource header docblock says exactly this: "Read-mostly resource: List + Edit only. ... Surfacing Create in Filament would let admins mint permission strings the codebase doesn't reference.").

**Fix:** Disable the `name` field in edit mode. Keep the edit form for `guard_name` if needed, or drop the EditPermission page entirely so it's truly read-only:
```php
Forms\Components\TextInput::make('name')
    ->label(__('admin.permission.fields.name'))
    ->disabled()
    ->dehydrated(false),
```
Or remove `Pages\EditPermission::route('/{record}/edit')` from `getPages()` and update `PermissionResource::table()` to drop the EditAction — making the resource truly view-only.

---

### WR-06: `Audit` page exposes the database event values without translating them

**File:** `apps/web/app/Filament/Pages/Audit.php:79-83`
**Issue:** The `event` `SelectFilter` options are hardcoded English strings:
```php
->options([
    'created' => 'created',
    'updated' => 'updated',
    'deleted' => 'deleted',
]),
```
This violates D-013 ("Every UI string flows through `__()` (PHP/Blade) or `t()` (Vue). Hardcoded strings are a CI failure"). The `NoHardcodedStringsTest.php` only scans Vue `<template>` bodies, so this slips past CI but is still a project-rule violation. The same pattern affects the `subject_type` filter's `class_basename()` output and the `formatStateUsing` callback on line 68 (`'—'` em-dash is fine; `class_basename($state)` is the leak).

**Fix:**
```php
->options([
    'created' => __('admin.audit.event.created'),
    'updated' => __('admin.audit.event.updated'),
    'deleted' => __('admin.audit.event.deleted'),
]),
```
Add the keys to `lang/en/admin.php` under `audit.event.*`.

---

### WR-07: `RedirectFilamentAuthToDiscord::redirectTo($request)` lacks parameter type — PHPStan level 8

**File:** `apps/web/app/Http/Middleware/RedirectFilamentAuthToDiscord.php:24`
**Issue:** The method signature `protected function redirectTo($request): string` has no type for `$request`. Project requires PHPStan level 8 (`composer.json`, CLAUDE.md §3). The parent method in `Filament\Http\Middleware\Authenticate` may type it as `Request|null` or untyped, but level 8 still complains about untyped parameters in subclass overrides absent a `@param` PHPDoc.

**Fix:**
```php
/**
 * @param  \Illuminate\Http\Request  $request
 */
protected function redirectTo($request): string
{
    return route('auth.discord.redirect');
}
```
Or, if the parent method's signature has changed to a typed argument in Filament v3, match it. Verify against `vendor/filament/filament/src/Http/Middleware/Authenticate.php` to mirror exactly.

---

### WR-08: `audit-tab.blade.php` allows N+1 on `$activity->causer`

**File:** `apps/web/resources/views/filament/partials/audit-tab.blade.php:31-33`
**Issue:** The `@foreach` loop dereferences `$activity->causer` for every row without eager loading. With 50 rows this is 50 extra queries (one `users` lookup per row). The same partial is rendered inside the `Audit` per-resource tab whenever a User or Player is viewed.

Per the review scope this is technically a performance concern (out of v1), but it also causes a correctness risk: if causer's User row was hard-deleted, `$activity->causer` resolves to `null` and `$activity->causer->username ?? $activity->causer->id` short-circuits — but typed accessors on Eloquent are forgiving. More important: this pattern propagates as more callers copy it, so fixing it now is cheap.

**Fix:**
```php
$activities = $subject
    ? \Spatie\Activitylog\Models\Activity::query()
        ->with('causer')
        ->where('subject_type', $subject::class)
        ->where('subject_id', $subject->getKey())
        ->orderByDesc('id')
        ->limit(50)
        ->get()
    : collect();
```

---

## Info

### IN-01: Double-call inefficiency in bare `t()` export

**File:** `apps/web/resources/js/composables/useT.ts:43-45`
**Issue:** The bare `export function t(key, params)` calls `useT().t(key, params)`. Every invocation creates a new computed ref over the page props. Inside a Vue template, prefer `const { t } = useT()` once in `<script setup>` and call `t()` repeatedly. The bare export is a footgun for readers who think it's lighter weight — it's actually heavier.

**Fix:** Either inline the cheap path or document the trade-off:
```ts
// Prefer destructuring inside <script setup>: `const { t } = useT()` reuses one computed.
// This bare helper is fine for one-off use sites but creates a new computed per call.
export function t(key: string, params: Params = {}): string {
    return useT().t(key, params);
}
```

---

### IN-02: `validation.php` `'custom'` placeholder leaks into `translations` shared prop

**File:** `apps/web/lang/en/validation.php:181-185` + `apps/web/app/Http/Middleware/HandleInertiaRequests.php:67-99`
**Issue:** The placeholder block:
```php
'custom' => [
    'attribute-name' => [
        'rule-name' => 'custom-message',
    ],
],
```
gets flat-merged into the Inertia `translations` shared prop as `validation.custom.attribute-name.rule-name = "custom-message"`. Sent on every request. Cosmetic but wasteful and confusing to anyone inspecting the prop.

**Fix:** Remove the placeholder entirely (Laravel doesn't read it unless populated) or filter the `validation` namespace to skip the `custom` and `attributes` sub-trees in `HandleInertiaRequests::flatten()`.

---

### IN-03: `useT.ts` parameter substitution order is insertion-order dependent

**File:** `apps/web/resources/js/composables/useT.ts:33-36`
**Issue:** `Object.entries(params).reduce(...)` substitutes parameters in object-key insertion order. If a parameter value happens to contain another parameter's `:key` token, you get cascading substitution that depends on iteration order. Edge case but well-known footgun in template engines.

**Fix:** Either substitute all keys in a single pass via regex, or document that param values must not contain `:` followed by another param's name.
```ts
function t(key: string, params: Params = {}): string {
    const raw = translations.value[key];
    if (raw === undefined) {
        if (import.meta.env.DEV) {
            console.warn(`[i18n] missing key: ${key}`);
        }
        return key;
    }
    return raw.replace(/:(\w+)/g, (m, k) => (k in params ? String(params[k]) : m));
}
```

---

### IN-04: Discord OAuth callback persists email without uniqueness or verification check

**File:** `apps/web/app/Http/Controllers/Auth/DiscordController.php:60` + `apps/web/database/migrations/2026_05_03_100000_create_users_table.php:39`
**Issue:** `email` is `citext NULL` with no UNIQUE constraint and no `verified_at`. Two unrelated Discord accounts could share an email, and a Discord account whose email is unverified still has it stored verbatim. Discord OAuth's `email` scope returns the email regardless of verification state. For the Trenchwars threat model this is acceptable (Discord ID is canonical, not email), but worth tracking — Phase 2's CMS author profile feature may eventually depend on email uniqueness.

**Fix:** No code change required for P1, but add a CON-### or D-### in `.planning/PROJECT.md` documenting that emails are non-unique and unverified, so future contributors don't accidentally rely on uniqueness.

---

### IN-05: `LayoutTokensTest` asserts on built `manifest.json` — flaky in fresh checkouts

**File:** `apps/web/tests/Feature/Home/LayoutTokensTest.php:19-23`
**Issue:** The "serves the css bundle in the manifest" test reads `public/build/manifest.json`, which exists only after `pnpm run build` runs. A fresh `composer install && ./vendor/bin/pest` will fail this test with no obvious cause. The CI workflow (`.github/workflows/web.yml`) doesn't run `pnpm run build` before `./vendor/bin/pest --parallel`.

**Fix:** Either run a `pnpm run build` step in the workflow before Pest, or guard the test with a `skipUnless(file_exists(public_path('build/manifest.json')))` and add a CI step. The current setup will fail intermittently when the build directory hasn't been populated.

---

### IN-06: `TypescriptGenerateCommand` uses hardcoded `/repo/...` path

**File:** `apps/web/app/Console/Commands/TypescriptGenerateCommand.php:45`
**Issue:** `$target = '/repo/packages/shared-types/src/api.d.ts';` is hardcoded to the docker-compose mount path. If the volume mount path ever changes (or in a Railway runtime where this path doesn't exist), the command silently warns and exits 0 — masking sync failures. The test `TypescriptTransformTest.php:34-48` exercises this fallback (`if (is_dir(dirname($sharedTypesTarget)))`), so dev-without-docker silently passes the test.

**Fix:** Read the target path from config (`config('typescript.shared_types_path')`) and document the override. This way the test can pin a known path, and operators can change the mount without editing PHP.

---

### IN-07: `Audit` page sort uses `latest('id')` not `latest('created_at')`

**File:** `apps/web/app/Filament/Pages/Audit.php:55`
**Issue:** Sorting by `id` works because Postgres bigint serial is monotonically increasing per insertion, so `id desc` is equivalent to `created_at desc`. But it's an implicit dependency on the storage engine's sequence behaviour. If migrations later partition activity_log by date or move to a different sequence strategy, the visible sort order diverges from the displayed `created_at` timestamps.

**Fix:** `Activity::query()->latest('created_at')` — explicit and matches the column users actually see in the table.

---

_Reviewed: 2026-05-04T20:55:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
