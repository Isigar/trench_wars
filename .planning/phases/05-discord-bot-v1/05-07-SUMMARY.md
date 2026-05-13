---
phase: 05-discord-bot-v1
plan: 07
subsystem: discord-bot
tags: [wave-5, filament, sanctum, admin, bot-service-user, retry-action]
dependency_graph:
  requires: [05-02-complete, 05-05-complete, 05-06-complete, phase-02-complete]
  provides:
    - App\Filament\Resources\DiscordOutboundMessageResource (read-only)
    - App\Console\Commands\IssueBotTokenCommand
    - App\Console\Commands\RevokeBotTokenCommand
    - Database\Seeders\BotServiceUserSeeder
    - bot_service_user_sentinel (discord_id='SYSTEM_BOT')
    - admin_retry_action_state_flip_contract
  affects: [05-11, 05-12, 05-13]
tech_stack:
  added:
    - "trenchwars:bot:issue-token Artisan command — Sanctum personal access token rotation playbook (RESEARCH §Pitfall 3 + Q4)"
    - "trenchwars:bot:revoke-token Artisan command — companion delete-by-name"
  patterns:
    - "Filament v3 read-only Resource (getPages omits create+edit) — Phase 4 EventResource precedent confirmed"
    - "Filament v3 Tables\\Actions\\Action::make('retry')->visible(fn $record => $record->status === 'failed')->action(closure) — bespoke per-row action"
    - "Sentinel discord_id='SYSTEM_BOT' (non-numeric string) avoids collision with Discord-OAuth-provisioned users (Discord snowflakes are pure digits)"
    - "User->createToken(name, abilities, expires_at) → Laravel\\Sanctum\\NewAccessToken with ->plainTextToken (shown ONCE) and ->accessToken (PersonalAccessToken model with expires_at Carbon cast)"
    - "Rotation safety idiom: $bot->tokens()->where('name', $name)->delete() BEFORE createToken (T-05-07-04 mitigation — no duplicate valid tokens)"
    - "firstOrCreate idempotent singleton seeder pattern (Phase 2 plan 02-04 DiscordGuildSeeder analog)"
    - "Filament v3 callTableAction('actionName', $record) + assertTableActionVisible/Hidden — canonical Pest+Livewire idiom"
key_files:
  created:
    - "apps/web/app/Filament/Resources/DiscordOutboundMessageResource.php"
    - "apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ListDiscordOutboundMessages.php"
    - "apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ViewDiscordOutboundMessage.php"
    - "apps/web/app/Console/Commands/IssueBotTokenCommand.php"
    - "apps/web/app/Console/Commands/RevokeBotTokenCommand.php"
    - "apps/web/database/seeders/BotServiceUserSeeder.php"
    - "apps/web/tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php"
    - "apps/web/tests/Feature/Bot/IssueBotTokenCommandTest.php"
  modified:
    - "apps/web/app/Filament/Resources/ClanResource.php"
    - "apps/web/lang/en/admin.php"
    - "apps/web/database/seeders/DatabaseSeeder.php"
decisions:
  - "D-05-07-A: ClanResource already shipped the discord_announce_channel_id field in Phase 2 plan 02-12 (committed BEFORE 05-07 ran). The plan's task 1 amendment focused on adding helperText (via the new admin.clan.fields.discord_announce_channel_id_help i18n key) + maxLength(20) for the Discord snowflake shape constraint. The pre-existing 'Enable Discord field editing' toggle gate (Phase 2 plan 02-09 T-02-09-02 mitigation) was preserved — admin must still flip the toggle to enable editing the channel snowflake."
  - "D-05-07-B: Bot service user gets locale='en' in firstOrCreate defaults (plan <interfaces> omitted this column but the users table requires it per Phase 1 schema). Without locale, the create would fail on the NOT NULL constraint. Used 'en' (matches D-013 default + UserFactory default). Documented as a Rule 2 auto-add."
  - "D-05-07-C: PHPStan scope excludes tests/ (per phpstan.neon paths: app, bootstrap/app.php, database, routes). The test files DO emit PHPStan findings when individually analysed (artisan/withHeaders not resolved on Pest\\TestCall, null-safe on Eloquent first() results) but those are out-of-scope for CI. Pattern matches existing tests/Feature/Bot/* files (e.g. ResolveBotActsAsUserMiddlewareTest, SyncDiscordRolesJobTest) which have the same in-scope-clean / explicit-analysis-noisy posture. No baseline change."
  - "D-05-07-D: The plan's retry action enumeration listed 8 tests; the GREEN file ships 11 by splitting 'retry visible on failed + hidden on pending/dispatching/sent' into two it() blocks for crisp coverage and adding 'getPages omits create+edit' as a structural assertion separate from the HTTP 404 checks. Net coverage gain: +3 tests vs plan minimum."
  - "D-05-07-E: IssueBotTokenCommandTest ships 8 tests (plan called for 7+). All 8 enumerated by the plan are present. No deviation count-wise."
metrics:
  duration_seconds: 407
  completed_date: "2026-05-13"
  tasks_total: 3
  tasks_completed: 3
  commits: 3
  files_changed: 11
---

# Phase 5 Plan 07: Wave 5 — DiscordOutboundMessageResource + Bot Token Artisan

Wave 5 ships the admin-facing surface for Phase 5: a read-only Filament Resource
that lists every outbound row the observer/role-sync job writes, with a bespoke
"Retry" action for failed deliveries (RESEARCH Q3); a ClanResource amendment
exposing helper text on `discord_announce_channel_id` (RESEARCH Q1); and two
Artisan commands (`trenchwars:bot:issue-token` + `:revoke-token`) that
provision the bot service user and rotate its Sanctum personal access token
(RESEARCH Pitfall 3 + Q4).

## Acceptance Criteria

### Task 1 — DiscordOutboundMessageResource + 2 Pages + ClanResource amendment (commit `f9468b5`)

- [x] `DiscordOutboundMessageResource.php`:
  - `protected static ?string $model = DiscordOutboundMessage::class`
  - `protected static ?int $navigationSort = 22`
  - `protected static ?string $navigationGroup = 'Discord'`
  - `protected static ?string $navigationIcon = 'heroicon-o-paper-airplane'`
  - `getModelLabel()` + `getPluralModelLabel()` via `__('admin.discord_outbound_message.*')`
  - `form()` returns empty schema (view-only)
  - `table()`: 8 columns (created_at, message_type badge, status badge, channel_id mono, attempts numeric, last_error tooltip, sent_message_id mono, causer.username) + 2 filters (status + message_type) + 2 actions (ViewAction + retry) + no bulk actions
  - retry action: `visible(fn $record => $record->status === 'failed')`; closure flips status → pending, attempts → 0, last_error → null, backoff_until → null; writes `activity()->event('retry')` log; sends success notification
  - `getPages()`: `index` + `view` ONLY (no `create`, no `edit` — T-05-07-01)
- [x] `ListDiscordOutboundMessages.php` — thin `extends ListRecords` with `$resource = DiscordOutboundMessageResource::class`; no `getHeaderActions()`
- [x] `ViewDiscordOutboundMessage.php` — thin `extends ViewRecord`
- [x] `ClanResource.php` amendment: `discord_announce_channel_id` TextInput now has `->helperText(__('admin.clan.fields.discord_announce_channel_id_help'))` + `->maxLength(20)`; preserves the Phase 2 T-02-09-02 toggle gate
- [x] `admin.php` appended: `admin.clan.fields.discord_announce_channel_id_help` key (the field label itself was already present from Phase 2)
- [x] PHPStan L8 CI scope clean (No errors)
- [x] Pint clean (1 cosmetic concat_space auto-fix applied; all 338 files PASS)
- [x] `artisan optimize:clear` succeeds (Filament resource cache invalidated)
- [x] `route:list` shows exactly 2 routes for `discord-outbound-messages`: list + view (no create/edit)

### Task 2 — IssueBotTokenCommand + RevokeBotTokenCommand + BotServiceUserSeeder + DatabaseSeeder amendment (commit `d6a2e0f`)

- [x] `IssueBotTokenCommand.php`:
  - `signature = 'trenchwars:bot:issue-token {--name=bot-prod} {--ttl=90}'`
  - `firstOrCreate` bot user with `discord_id='SYSTEM_BOT'` + `username='Trenchwars Bot'` + `email='bot@trenchwars.local'` + `locale='en'` (D-05-07-B)
  - rotation safety: `$bot->tokens()->where('name', $name)->delete()` before issue
  - `createToken($name, ['bot:read','bot:act-as-user','bot:write-outbound','bot:reconcile'], now()->addDays($ttl))`
  - prints: bot user id, token name, expires_at ISO8601, plaintext token, warnings
- [x] `RevokeBotTokenCommand.php`:
  - `signature = 'trenchwars:bot:revoke-token {--name=bot-prod}'`
  - finds bot user by `discord_id='SYSTEM_BOT'`; emits error + FAILURE if absent
  - deletes tokens by name; reports count
- [x] `BotServiceUserSeeder.php` — idempotent `firstOrCreate` singleton mirroring DiscordGuildSeeder
- [x] `DatabaseSeeder.php` amendment: `BotServiceUserSeeder::class` added right after `DiscordGuildSeeder::class` (paired Discord-bound singletons)
- [x] `php artisan list | grep 'trenchwars:bot'` returns 2 entries (issue + revoke)
- [x] Smoke-tested locally: issue produces token with 4 abilities + ~90d expiry; revoke deletes the named token row
- [x] PHPStan L8 CI scope clean
- [x] Pint clean

### Task 3 — DiscordOutboundMessageResourcePresentTest + IssueBotTokenCommandTest GREEN (commit `0174710`)

- [x] `DiscordOutboundMessageResourcePresentTest.php` — 11 it() blocks (8 enumerated + 3 expanded per D-05-07-D):
  1. Resource registered at /admin/discord-outbound-messages
  2. List page mounts (Livewire panel context)
  3. View page mounts on /admin/discord-outbound-messages/{record}
  4. `getPages()` omits 'create' and 'edit' keys
  5. /admin/.../create returns 404
  6. /admin/.../{id}/edit returns 404
  7. Retry action visible on failed rows (`assertTableActionVisible('retry', $failed)`)
  8. Retry action hidden on pending/dispatching/sent rows
  9. Retry flips state — pending/0/null/null
  10. Retry writes activity_log retry event with admin causer (T-05-07-05)
  11. Non-admin user gets 403
- [x] `IssueBotTokenCommandTest.php` — 8 it() blocks (all 7 plan minima + 1):
  1. Creates bot service user when absent
  2. Reuses bot service user (idempotent)
  3. Token has 4 abilities (bot:read, bot:act-as-user, bot:write-outbound, bot:reconcile)
  4. expires_at = now + 90 days by default
  5. Respects --ttl=30
  6. Respects --name=staging
  7. Rotation safety — prior token with same --name deleted before reissue
  8. Output contains plaintext token + "shown ONCE" warning + bot user line
- [x] Both test files have **NO** `placeholder` literal (no Wave 0 stub artifacts)
- [x] `make pest ARGS="--filter='(DiscordOutboundMessageResourcePresent|IssueBotTokenCommand)'"` → 19 passed / 68 assertions
- [x] Full pest baseline: 604 passed / 1758 assertions (was 585 / 1690; +19 tests / +68 assertions)
- [x] PHPStan L8 CI scope clean
- [x] Pint clean (338 files PASS)

## DiscordOutboundMessageResource Page Inventory

| Page                            | Route                                       | Purpose                                |
|---------------------------------|---------------------------------------------|----------------------------------------|
| `ListDiscordOutboundMessages`   | `GET /admin/discord-outbound-messages`      | Table with status/type filters + retry |
| `ViewDiscordOutboundMessage`    | `GET /admin/discord-outbound-messages/{id}` | Read-only inspector for one row        |
| ~~`CreateDiscordOutboundMessage`~~ | ~~POST /admin/discord-outbound-messages/create~~ | **404 — intentionally omitted (T-05-07-01)** |
| ~~`EditDiscordOutboundMessage`~~   | ~~PATCH /admin/discord-outbound-messages/{id}~~ | **404 — intentionally omitted (T-05-07-01)** |

The observer + SyncDiscordRolesJob own the outbox. Admin only browses + retries
failures; they cannot inject arbitrary payloads or status transitions.

## Retry Action Signature + State-Flip Contract

```php
Tables\Actions\Action::make('retry')
    ->label(__('admin.discord_outbound_message.actions.retry'))
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->visible(fn (DiscordOutboundMessage $record): bool => $record->status === 'failed')
    ->action(function (DiscordOutboundMessage $record): void {
        $record->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'backoff_until' => null,
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($record)
            ->event('retry')
            ->log('admin re-queued failed outbound message');

        Notification::make()
            ->title(__('admin.discord_outbound_message.actions.retry_success'))
            ->success()
            ->send();
    }),
```

| Field           | Pre-retry           | Post-retry |
|-----------------|---------------------|------------|
| `status`        | `failed`            | `pending`  |
| `attempts`      | (whatever count)    | `0`        |
| `last_error`    | (Discord error msg) | `null`     |
| `backoff_until` | (future Carbon)     | `null`     |

The next bot poller cycle (plan 05-11) picks up the row via the `scopeDispatchable`
predicate and retries delivery. Two-layer retry (Horizon + outbound-table backoff)
remains intact from Phase 5 plan 05-06.

## ClanResource Amendment

Phase 2 plan 02-12 already shipped the `discord_announce_channel_id` TextInput
gated behind the "Enable Discord field editing" toggle (T-02-09-02 mitigation
for accidental snowflake edits). Plan 05-07 adds:

```diff
 Forms\Components\TextInput::make('discord_announce_channel_id')
     ->label(__('admin.clan.fields.discord_announce_channel_id'))
+    ->helperText(__('admin.clan.fields.discord_announce_channel_id_help'))
+    ->maxLength(20)
     ->disabled(fn (Forms\Get $get): bool => ! $get('discord_advanced_fields_enabled'))
     ->dehydrated(fn (Forms\Get $get): bool => $get('discord_advanced_fields_enabled') === true),
```

New i18n key (admin.php):

```php
'discord_announce_channel_id_help' => 'Discord channel snowflake — copy from Channel Settings → Edit Channel → Advanced → Channel ID. Bot needs send + embed perms.',
```

The placement after `discord_role_id` (preserving Phase 2 layout) groups all
Discord-bound fields in one visual block.

## `trenchwars:bot:issue-token` Output Sample (Smoke Test)

Plaintext token redacted; rest of output reproduced verbatim:

```
Bot service user: 82092f4e-c4df-4370-b00e-09b25d72a6e1
Token name: bot-prod
Expires at: 2026-08-11T18:04:23+00:00

Copy the token below — it is shown ONCE and cannot be recovered.
<REDACTED 48-char Sanctum plaintext token starting with "1|">

Paste into Railway bot service WEB_API_TOKEN env var (or apps/web/.env for local dev).
Run trenchwars:bot:revoke-token --name=bot-prod to revoke.
```

## `trenchwars:bot:revoke-token` Playbook (Rotation)

```bash
# 1. List active tokens (manual SQL — no Filament resource ships for personal_access_tokens):
docker compose exec web php artisan tinker --execute="App\\Models\\User::where('discord_id','SYSTEM_BOT')->first()->tokens()->get(['id','name','expires_at']);"

# 2. Revoke the soon-to-rotate token (idempotent — safe to re-run):
docker compose exec web php artisan trenchwars:bot:revoke-token --name=bot-prod
# Deleted 1 token(s) named 'bot-prod'.

# 3. Issue the replacement (prints new plaintext ONCE):
docker compose exec web php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90

# 4. Copy the printed plaintext into Railway bot service WEB_API_TOKEN env var.
# 5. Redeploy the bot service (Railway picks up the new env on next boot).
# 6. Verify the bot can still POST /api/bot/* — old token now 401s, new token 200s.
```

The IssueBotTokenCommand handles step 2+3 atomically if you skip step 2: the
command itself revokes any prior token with the same name before issuing the
new one (T-05-07-04 rotation safety). Step 2 only matters if you want a hard
gap between old and new tokens for audit purposes.

## Bot Service User Shape

| Column         | Value                | Notes                                                    |
|----------------|----------------------|----------------------------------------------------------|
| `discord_id`   | `'SYSTEM_BOT'`       | Sentinel non-numeric string — Discord snowflakes are pure digits, so collision impossible (T-05-07-02) |
| `username`     | `'Trenchwars Bot'`   | Shown in Filament chrome (User->getFilamentName())       |
| `email`        | `'bot@trenchwars.local'` | Unique placeholder; deliverability irrelevant         |
| `locale`       | `'en'`               | D-013 default; required by users table NOT NULL constraint (D-05-07-B) |
| `password`     | (none)               | No password column — D-017 Discord-OAuth-only           |
| `avatar_url`   | `null`               | (default)                                                |
| `last_login_at`| `null`               | Bot never goes through OAuth                             |

The user row supports `HasApiTokens` (Phase 5 plan 05-03 added the trait), so
`$bot->createToken(...)` writes into `personal_access_tokens` with the SHA-256
hash of the plaintext.

## DatabaseSeeder Amendment

Placement: `BotServiceUserSeeder::class` is now called immediately after
`DiscordGuildSeeder::class`, before `ClanTagSeeder::class`. The two Discord-
bound singletons are paired by convention. Phase 9 reactivation flows that
attribute role-sync writes to the bot causer will rely on the bot row existing
before any ClanMembership seeds; the new placement guarantees that.

```php
$this->call([
    PermissionSeeder::class,
    DiscordGuildSeeder::class,
    BotServiceUserSeeder::class,   // ← Phase 5 plan 05-07 task 2
    ClanTagSeeder::class,
    GameSeeder::class,
]);
```

## Threat Register Coverage

| Threat ID    | Disposition | Coverage in this plan                                                                                   |
|--------------|-------------|---------------------------------------------------------------------------------------------------------|
| T-05-07-01 (EoP — admin edits payload) | mitigate | No Create/Edit pages; getPages omits both. /admin/.../create + /admin/.../{id}/edit return 404 (tested) |
| T-05-07-02 (Spoofing — OAuth collision) | mitigate | discord_id='SYSTEM_BOT' is non-numeric; Discord snowflakes are pure digits → collision impossible       |
| T-05-07-03 (Info disclosure — plaintext in logs) | accept | Operator runs in trusted shell; warning emitted with the token print                                    |
| T-05-07-04 (Tampering — duplicate valid tokens) | mitigate | Rotation safety — `$bot->tokens()->where('name', $name)->delete()` before createToken (tested)          |
| T-05-07-05 (Repudiation — retry not audited) | mitigate | `activity()->causedBy(auth()->user())->event('retry')->log(...)` in the action handler (tested)         |
| T-05-07-06 (Replay — token shows in admin UI) | mitigate | No personal_access_tokens Filament resource ships; only the hash is persisted                          |
| T-05-07-07 (Bot user soft-delete cascade) | accept | LogsActivity captures; revoke + reissue playbook documented above                                       |
| T-05-07-08 (Wrong channel id permission) | accept | Bot worker fails delivery with last_error; admin retries via the new action                            |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 — Missing critical functionality] Bot service user requires `locale` column**

- **Found during:** Task 2 setup — reading the Phase 1 users schema and UserFactory definition
- **Issue:** Plan `<interfaces>` block listed only `discord_id`, `username`, `email` defaults for `firstOrCreate` calls in IssueBotTokenCommand + BotServiceUserSeeder. The users table requires `locale` per Phase 1 schema (NOT NULL with no DB default), and UserFactory always supplies `'en'`. Without `locale` the seeder + command would fail at insert time with a Postgres NOT NULL violation.
- **Fix:** Added `'locale' => 'en'` to both `firstOrCreate` defaults (D-05-07-B). Matches the D-013 default locale + UserFactory convention.
- **Files affected:** `apps/web/app/Console/Commands/IssueBotTokenCommand.php`, `apps/web/database/seeders/BotServiceUserSeeder.php`
- **Commit:** `d6a2e0f`

**2. [Rule 2 — Missing critical functionality] ClanResource discord_announce_channel_id helperText was unwired before plan 05-07**

- **Found during:** Task 1 reading current ClanResource.php
- **Issue:** Phase 2 plan 02-12 had ALREADY shipped the `discord_announce_channel_id` TextInput field (gated by the "Enable Discord field editing" toggle from plan 02-09 T-02-09-02), but it had no helperText and no maxLength constraint. The plan task 1 prescribed both. The amendment had to ADD the missing functionality without re-creating the field.
- **Fix:** Added `->helperText(__('admin.clan.fields.discord_announce_channel_id_help'))` + `->maxLength(20)`. The new i18n key was added to admin.php. The Phase 2 toggle gate was preserved (it's a Phase 2 invariant — disabling that toggle would re-enable accidental snowflake edits).
- **Files affected:** `apps/web/app/Filament/Resources/ClanResource.php`, `apps/web/lang/en/admin.php`
- **Commit:** `f9468b5`

**3. [Rule 1 — Bug] Pint `concat_space` auto-fix on IssueBotTokenCommand**

- **Found during:** Task 2 pint check after first write
- **Issue:** Pint flagged `'Bot service user: ' . $bot->id` style concatenations (Laravel preset prefers `' . '` spacing — actually it was the OPPOSITE; pint wanted spaces around `.`).
- **Fix:** Ran `pint` (auto-write) on the single file; pint applied the canonical spacing. No semantic change.
- **Files affected:** `apps/web/app/Console/Commands/IssueBotTokenCommand.php`
- **Commit:** `d6a2e0f`

### Documentation Deviations

- **D-05-07-A above:** ClanResource amendment was strictly additive (helperText + maxLength) — the field itself was already shipped in Phase 2. Plan task 1 ran additively rather than as a "first introduction" amendment.
- **D-05-07-D above:** DiscordOutboundMessageResourcePresentTest ships 11 tests (plan minimum 8). Net coverage gain: +3 by splitting the retry-visible/hidden enumeration into two it() blocks and adding a structural `getPages omits create+edit` assertion.

### Authentication Gates

None — no external OAuth or third-party credential flows touched. The bot
service user is provisioned locally via the new Artisan command.

## Files Created/Modified

```
11 files changed (3 commits)
```

### Created (8)

```
apps/web/app/Filament/Resources/DiscordOutboundMessageResource.php
apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ListDiscordOutboundMessages.php
apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ViewDiscordOutboundMessage.php
apps/web/app/Console/Commands/IssueBotTokenCommand.php
apps/web/app/Console/Commands/RevokeBotTokenCommand.php
apps/web/database/seeders/BotServiceUserSeeder.php
apps/web/tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php
apps/web/tests/Feature/Bot/IssueBotTokenCommandTest.php
```

### Modified (3)

```
apps/web/app/Filament/Resources/ClanResource.php          (+2 lines — helperText + maxLength on existing field)
apps/web/lang/en/admin.php                                 (+1 line — new help key)
apps/web/database/seeders/DatabaseSeeder.php               (+4 lines — BotServiceUserSeeder call)
```

## Wave 0 Baseline Movement

| Marker                       | Before 05-07            | After 05-07             | Δ                        |
|------------------------------|-------------------------|-------------------------|--------------------------|
| Pest full suite — passed     | 585 (1690 assertions)   | 604 (1758 assertions)   | **+19 tests / +68 asserts** |
| `./vendor/bin/pint --test`   | PASS 330 files          | PASS 338 files          | +8                       |
| `./vendor/bin/phpstan analyse` | No errors             | No errors               | unchanged                |
| `artisan list \| grep trenchwars:bot` | 0                     | 2                        | +2 (issue + revoke)      |
| `route:list \| grep discord-outbound-messages` | 0          | 2                        | +2 (list + view, NO create/edit) |

## SC-3 / SC-5 Resolution

- **SC-3 (admin can replay failed outbound):** functional — the retry action flips state and the next bot poller cycle re-attempts delivery. Verified by `DiscordOutboundMessageResourcePresentTest::it('Retry action flips status=failed → pending ...')`.
- **SC-5 (token issuance + rotation procedure documented):** functional — both Artisan commands ship and are smoke-tested. The rotation playbook is documented in this SUMMARY's "`trenchwars:bot:revoke-token` Playbook" section.

## RESEARCH Open Questions Closed

- **Q1 (channel resolution):** ClanResource exposes `discord_announce_channel_id` with helper text pointing to the Discord channel snowflake source (Channel Settings → Edit Channel → Advanced → Channel ID).
- **Q3 (retry semantics):** Filament Action::make('retry')->visible(failed)->action(closure) — flips status + zeros attempts + clears error/backoff in a single update.
- **Q4 (bot service user shape):** sentinel `discord_id='SYSTEM_BOT'`, idempotent seeder, paired with DiscordGuildSeeder.

## Operator Manual Smoke Step (Production / Staging)

```bash
# 1. SSH into Railway web service (or run locally via docker compose).
# 2. Issue the bot's production token:
docker compose exec web php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90

# 3. Copy the printed plaintext token (shown ONCE).
# 4. Paste into Railway "bot" service env: WEB_API_TOKEN=<paste here>
# 5. Redeploy the bot service.
# 6. Verify bot health: tail Railway bot logs for "authenticated as Trenchwars Bot" line.
# 7. Calendar this 90 days out: rotate before expiry to avoid bot downtime.
```

## Self-Check: PASSED

- [x] `apps/web/app/Filament/Resources/DiscordOutboundMessageResource.php` exists
- [x] `apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ListDiscordOutboundMessages.php` exists
- [x] `apps/web/app/Filament/Resources/DiscordOutboundMessageResource/Pages/ViewDiscordOutboundMessage.php` exists
- [x] `apps/web/app/Console/Commands/IssueBotTokenCommand.php` exists
- [x] `apps/web/app/Console/Commands/RevokeBotTokenCommand.php` exists
- [x] `apps/web/database/seeders/BotServiceUserSeeder.php` exists
- [x] `apps/web/tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php` exists
- [x] `apps/web/tests/Feature/Bot/IssueBotTokenCommandTest.php` exists
- [x] Commit `f9468b5` exists in `git log` (Task 1 — Filament resource + ClanResource amendment)
- [x] Commit `d6a2e0f` exists in `git log` (Task 2 — Artisan commands + seeder)
- [x] Commit `0174710` exists in `git log` (Task 3 — GREEN tests)
- [x] `./vendor/bin/pest tests/Feature/Admin/DiscordOutboundMessageResourcePresentTest.php tests/Feature/Bot/IssueBotTokenCommandTest.php` → 19 passed / 68 assertions
- [x] Full pest baseline: 604 passed / 1758 assertions (was 585 / 1690; +19 / +68)
- [x] `./vendor/bin/phpstan analyse` → No errors (CI scope)
- [x] `./vendor/bin/pint --test` → PASS 338 files
- [x] `artisan list | grep -c 'trenchwars:bot'` → 2
- [x] `route:list | grep -c 'discord-outbound-messages'` → 2 (no create/edit)
- [x] `grep -c 'discord_announce_channel_id' apps/web/app/Filament/Resources/ClanResource.php` → 4 (label + helperText + maxLength implicit + toggle wiring)
