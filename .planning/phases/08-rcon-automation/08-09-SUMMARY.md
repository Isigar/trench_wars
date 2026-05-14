---
phase: 08-rcon-automation
plan: 09
subsystem: filament-match-server-resource-and-test-connection-and-match-resource-extension
tags: [rcon, filament, admin, async-job, http-probe, encrypted-credentials, permission-gate, wave-6, manual-override, d-019, tdd-implicit-red]

# Dependency graph
requires:
  - phase: 08-03
    provides: "App\\Models\\MatchServer with encrypted:array cast on credentials_encrypted; MatchServerFactory + MatchServerBookingFactory; bookings() HasMany relation."
  - phase: 08-07
    provides: "App\\Services\\Rcon\\MatchEventIngestService (Phase 8 worker→web ingest path) and `manual_error` event type semantics — RconUnreachableFlagsManualTest case 2 leans on the factory's manualError() state."
  - phase: 08-08
    provides: "App\\Jobs\\Rcon\\CloseMatchJob::handle() (Wave-5 placeholder filled) — Task 2 RconUnreachableFlagsManualTest exercises the flag-flip-on-no-match_end path the job already enforces."
  - phase: 04-09
    provides: "App\\Filament\\Resources\\MatchResource (Phase 4 wizard + 4 RelationManagers) — surgically extended here with IconColumn + Filter + Action for D-019 manual-entry surface."
  - phase: 01-12
    provides: "PermissionSeeder + admin-access permission idiom; canAccessPanel gate on App\\Models\\User."
provides:
  - "App\\Filament\\Resources\\MatchServerResource — final Filament v3 resource: form (name/host/port_rcon/region/credentials_encrypted.api_token/is_active), table (name/host/port_rcon/region badge/last_test_status badge/last_test_at since/is_active boolean), Test Connection table action, BookingsRelationManager. nav group='RCON' navigationSort=30. Gated behind manage-rcon permission via canViewAny()."
  - "App\\Filament\\Resources\\MatchServerResource\\Pages\\{ListMatchServers, CreateMatchServer, EditMatchServer} — standard Filament v3 page boilerplate. EditMatchServer.mutateFormDataBeforeFill exposes the existing api_token on edit so admin can use ->revealable(); .mutateFormDataBeforeSave strips empty credentials_encrypted so a blank password field on edit preserves the stored token."
  - "App\\Filament\\Resources\\MatchServerResource\\RelationManagers\\BookingsRelationManager — read-only bookings table for a server. Pitfall 3-safe $relationship='bookings'. 'view_match' row action links to MatchResource::getUrl('edit', record: match_id) for admin drill-down."
  - "App\\Jobs\\Rcon\\TestMatchServerConnectionJob — Queueable Horizon job; constructor takes the MatchServer UUID (NOT the model, per the Phase 5 SyncDiscordRolesJob primitive-id idiom); handle() method-injects CrconHealthProbe; updates last_test_at/last_test_status/last_test_error after probe."
  - "App\\Services\\Rcon\\CrconHealthProbe — final, stateless probe. Calls CRCON `GET /api/get_map_rotation` with `Http::withToken($token)->timeout(10)`. Returns `['status'=>'ok'|'error', 'error'=>?string, 'map_rotation'=>?array]`. Status mapping: 200→ok; 401→auth_failed; other HTTP→unreachable; Throwable→unreachable; missing/empty token→permission_denied without HTTP call (Http::assertNothingSent() verified)."
  - "MatchResource extension (surgical edit, preserves Phase 4 wizard + 4 RelationManagers): IconColumn 'manual_entry_required' (danger triangle when true); Filter 'manual_entry_required' (where=true); Action 'clear_manual_entry_flag' visible when flag=true, flips false + success notification."
  - "manage-rcon permission seeded via PermissionSeeder firstOrCreate (idempotent across re-runs). Permission added to the canonical list at the top of PermissionSeeder; super-admin role inherits it implicitly via the whitelist sync."
  - "MatchServerResource registered on AdminPanelProvider after ArticleResource/CategoryResource."
  - "lang/en/admin.php — match.fields.manual_entry_required + match.actions.clear_manual_entry_flag + match.actions.clear_manual_entry_flag_success keys added for D-019 admin surface."
affects:
  - 08-10-PLAN.md (worker outbound normaliser TS mirror — unblocked at the manual_error/match_end ingest seam; RconUnreachableFlagsManualTest case 2's manual_error wire shape is now anchored to the existing factory state and ingest path).
  - 08-11-PLAN.md (BookingScheduler — unblocked at the result-side; scheduler will dispatch CloseMatchJob at booking.reserved_to; Wave 6 has the flag-flip behaviour pre-verified).
  - 08-12-PLAN.md (E2E scrim happy path — unblocked at the admin-side; admin can register MatchServer, run Test Connection, and curate flagged matches via the Filament UI; ScrimE2EHappyPathTest still RED, plan 08-12 owns).

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament v3 password+revealable nested credentials field — `TextInput::make('credentials_encrypted.api_token')->password()->revealable()->dehydrateStateUsing(fn(?string $s) => $s ? ['api_token'=>$s] : null)`. The model's `encrypted:array` cast handles envelope encryption at write time (T-08-03-01 mitigation); the form's password() flag renders as `<input type=\"password\">` so the bearer token never leaks via casual screen shoulder-surfing (T-08-09-01). On edit, mutateFormDataBeforeSave drops empty `credentials_encrypted` from the array so leaving the password field blank preserves the stored token rather than nulling it out."
    - "Async Filament table action via Horizon — `Action::make('test')->action(fn($r) => TestMatchServerConnectionJob::dispatch($r->id))->after(fn() => Notification::make()->title(__('rcon.audit.test_connection_queued',['server'=>$r->name]))->success()->send())`. T-08-09-02 mitigation: PHP-FPM has a 30s timeout; a synchronous CRCON probe against a slow/unreachable server would hit it and fail the request mid-render. The async job uses the existing Horizon redis queue (Phase 5 infra) and updates last_test_* columns when CrconHealthProbe returns. Admin sees an instant 'queued' notification."
    - "Filament v3 canViewAny() permission gate — `public static function canViewAny(): bool { return auth()->user()?->can('manage-rcon') ?? false; }`. Returning false hides the resource from the navigation AND blocks every page (List/Create/Edit) because Filament auto-derives per-page authorisation from canViewAny by default. T-08-09-03 mitigation: non-RCON-admins do not see the resource in the nav and the panel returns 403 on direct URL access. Test cases 6+7 verify both directions."
    - "Encrypted-cast schema-type vs Larastan PHPStan inference miss — `match_servers.credentials_encrypted` is a `text` column at the schema level but the Eloquent cast inflates it to `array` at runtime. Larastan's schema-aware inference sees the column as `string` so any `is_array($credentials)` check on the cast-inflated value is flagged as 'always false' (function.impossibleType). Existing fix from `MatchServerCredentialsController` (plan 08-06): inline PHPDoc cast `/** @var array{api_token?: string}|null $credentials */ $credentials = $server->credentials_encrypted;`. Re-applied in `CrconHealthProbe::probe()` — works because PHPDoc casts override the inferred type at the local scope."
    - "Http::fake() with hostname:port URL keys — `Http::fake(['crcon-eu-01.example.com:8010/api/get_map_rotation' => Http::response(['result' => [...]], 200)])` matches the exact request URL Laravel's HTTP client constructs from `Http::withToken($token)->get('http://crcon-eu-01.example.com:8010/api/get_map_rotation')`. The hostname:port form (no scheme, no leading slash) is the canonical fake-key shape; Http::preventStrayRequests() in beforeEach catches any URL that doesn't have a fake registered (defence against wire-shape drift across tests)."
    - "Http::fake closure-throws ConnectionException — `Http::fake(function (Request $request): never { throw new ConnectionException('cURL error 28: Connection timed out after 10001 milliseconds'); })` simulates a DNS / timeout / connection-refused failure mode. Returning `never` from the closure satisfies PHPStan's flow analysis (a thrown exception is the only exit). The probe's `catch (Throwable)` clause (not the more specific ConnectionException — PHPStan flagged that as `Dead catch` because the try block calls into `Http::withToken(...)->get(...)` which the fake-closure signature shows as throwing only Throwable, not the specific subclass at the static-analysis layer) absorbs it and returns the translated `rcon.errors.unreachable` key."
    - "MatchEventFactory ->create(['match_id' => ...]) bypass for the `for()` relation lookup — Laravel's factory `->for($match)` resolves the relation by class name (looks up `$factory->gameMatch()` for a `GameMatch` model). MatchEvent's BelongsTo method is named `match()` (D-04-03-A LOCKED naming), so `->for($match)` throws BadMethodCallException. RconUnreachableFlagsManualTest uses the explicit-FK form `->create(['match_id' => $match->id])` instead. Same pattern as RconMatchResultIngestionTest (plan 08-08)."

key-files:
  created:
    - apps/web/app/Filament/Resources/MatchServerResource.php
    - apps/web/app/Filament/Resources/MatchServerResource/Pages/ListMatchServers.php
    - apps/web/app/Filament/Resources/MatchServerResource/Pages/CreateMatchServer.php
    - apps/web/app/Filament/Resources/MatchServerResource/Pages/EditMatchServer.php
    - apps/web/app/Filament/Resources/MatchServerResource/RelationManagers/BookingsRelationManager.php
    - apps/web/app/Jobs/Rcon/TestMatchServerConnectionJob.php
    - apps/web/app/Services/Rcon/CrconHealthProbe.php
    - apps/web/tests/Feature/Phase8/MatchServerResourcePresentTest.php
    - apps/web/tests/Feature/Phase8/TestMatchServerConnectionJobTest.php
  modified:
    - apps/web/app/Filament/Resources/MatchResource.php
    - apps/web/app/Providers/Filament/AdminPanelProvider.php
    - apps/web/database/seeders/PermissionSeeder.php
    - apps/web/lang/en/admin.php
    - apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php

key-decisions:
  - "Filament v3 has no `Filament::getResource(class)` method — the docs occasionally suggest it but the actual FilamentManager API is `Filament::getCurrentPanel()->getResources()` returning the registered class list. The plan's <interfaces> case 1 wording (`Filament::getResource(MatchServerResource::class) returns non-null`) reads as pseudo-code; the test asserts the equivalent (`getCurrentPanel()->getResources()` contains MatchServerResource::class). Verified empirically — first iteration with the plan's verbatim call threw `BadMethodCallException: Call to undefined method Filament\\FilamentManager::getResource()`."
  - "EditMatchServer.mutateFormDataBeforeSave drops empty credentials_encrypted from the form data on edit so a blank password field preserves the stored token (UX nicety). This goes beyond the plan's <interfaces> spec but is consistent with the form's dehydrateStateUsing which returns null for empty strings — without the strip, the cast column gets nulled-out, which would lock the admin out of their own server. T-08-09-01 still holds: the new token is encrypted on write."
  - "BookingsRelationManager.view_match action uses `MatchResource::getUrl('edit', ['record' => $record->match_id])` directly (no null guard) because match_id is a NOT NULL foreign key on match_server_bookings (verified against the 08-02 migration). PHPStan flagged the plan's tentative null check as dead code; the simpler form is both safer (compile-time guarantee) and PHPStan-clean."
  - "CrconHealthProbe `catch (Throwable)` — the plan's <interfaces> wrote `catch (\\Throwable $e)` with the comment 'ConnectionException, etc.' PHPStan's flow analysis treats `Http::withToken(...)->get(...)` as throwing only Throwable at the static-analysis layer (it doesn't introspect the underlying Guzzle stack), so a more specific `catch (ConnectionException|Throwable)` was flagged as `Dead catch - Illuminate\\Http\\Client\\ConnectionException is never thrown in the try block`. The single Throwable catch covers all underlying failure modes — including ConnectionException, the connection-refused HTTP-layer error, and any transport-level Throwable. T-08-09-04 mitigation is preserved (raw exception never reaches caller; translated key only)."
  - "MatchServerResource navigationGroup='RCON' navigationSort=30. The plan's <interfaces> says nav group RCON after 'Bot' (DiscordOutboundMessageResource group='Discord', sort=22). Sort=30 places it cleanly after the Phase 5 Discord group; no other Phase 8 resource ships in Wave 6 so there's no contention. Filament's nav auto-groups by `navigationGroup` string, so the 'RCON' group is created implicitly."
  - "Plan-level type is `execute` (not `tdd`) but both tasks have `tdd=\"true\"` — implicit RED gate is the pre-existing Wave-0 RED stub `expect(true)->toBeFalse()` from plan 08-01 for RconUnreachableFlagsManualTest, plus the empirically-observed initial failure of the new MatchServerResourcePresentTest (9/9 failed before MatchServerResource existed). Both tasks landed GREEN suites after the RED gate. TestMatchServerConnectionJobTest is a fresh test file (no prior RED stub) but its 5 GREEN cases were written before final adjustments to CrconHealthProbe; the implementation existed before the test was authored because the probe code is data-shape-dependent on the existing MatchServer model + http facade signature."

# Metrics
duration: 12min
completed: 2026-05-14
---

# Phase 8 Plan 9: Wave 6 — MatchServerResource Filament + TestConnection + permission + MatchResource extension Summary

**Closed the admin-side of the RCON pipeline: shipped `MatchServerResource` (Filament v3 form/table/actions/Pages/BookingsRelationManager) gated behind a fresh `manage-rcon` permission, an async Horizon `TestMatchServerConnectionJob` driven by a stateless `CrconHealthProbe` against CRCON's `/api/get_map_rotation` endpoint, and surgically extended Phase 4's `MatchResource` with the D-019 manual-entry surface (IconColumn + Filter + clear-flag Action). Project regression 1103→1121 PASS (+18 GREEN: 9 Filament-presence + 5 job probe + 4 unreachable D-019), 2→1 FAIL (closed the Wave-0 RconUnreachableFlagsManualTest RED stub; remaining 1 = ScrimE2EHappyPathTest scheduled for plan 08-12). Zero Rule 4 architectural changes; one minor adjustment to the plan's <interfaces> for a non-existent Filament API method (`Filament::getResource()`).**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-05-14T05:03:46Z
- **Completed:** 2026-05-14T05:15:20Z
- **Tasks:** 2 / 2
- **Files created:** 9 (1 resource + 3 pages + 1 relation manager + 1 job + 1 service + 2 test files)
- **Files modified:** 5 (MatchResource extension + AdminPanelProvider register + PermissionSeeder append + lang/en/admin.php keys + RconUnreachableFlagsManualTest RED→GREEN)
- **Commits:** 2 (Task 1 `aeb8a21`; Task 2 `aa279af`)

## Accomplishments

### TDD Gate Sequence

Plan-level type is `execute` (not `tdd`), but both tasks have `tdd="true"` and each follows the GREEN-after-RED idiom:

1. **Task 1 — GREEN MatchServerResourcePresentTest** (commit `aeb8a21`): the test file was authored first against the not-yet-existent resource (empirical RED, 9/9 failures: "Unable to find component: [ListMatchServers]"). Implementation landed (MatchServerResource + 3 Pages + BookingsRelationManager + TestMatchServerConnectionJob stub + CrconHealthProbe stub + AdminPanelProvider register + PermissionSeeder append) and the test went 9/9 GREEN.
2. **Task 2 — GREEN TestMatchServerConnectionJobTest + GREEN RconUnreachableFlagsManualTest** (commit `aa279af`): the latter replaced the Wave-0 RED stub from plan 08-01 (`expect(true)->toBeFalse()`); the former was a fresh test file landing 5 GREEN cases against the existing CrconHealthProbe + TestMatchServerConnectionJob from Task 1. MatchResource extension (IconColumn + Filter + Action) added in the same commit since the plan's must_haves bundles it with Task 2.

### Application code (7 created, 3 modified)

1. **`App\Filament\Resources\MatchServerResource`** — final Filament v3 resource. Form: TextInput name (required, maxLength=255); TextInput host (required, maxLength=255); TextInput port_rcon (required, numeric, default=8010, min=1, max=65535); Select region (eu-central/eu-west/us-east/us-west/ap-southeast); TextInput credentials_encrypted.api_token (password+revealable, maxLength=255, dehydrateStateUsing wraps to ['api_token'=>$state]); Toggle is_active (default=true). Table: name/host/port_rcon/region badge/last_test_status badge (success/danger/gray)/last_test_at since/is_active boolean. Actions: Edit + bespoke `test` (Test Connection — dispatches the async job + sends `rcon.audit.test_connection_queued` notification). Bulk actions: DeleteBulkAction only. canViewAny gates the whole resource behind `manage-rcon`.

2. **`App\Filament\Resources\MatchServerResource\Pages\{ListMatchServers, CreateMatchServer, EditMatchServer}`** — standard Filament v3 page boilerplate. ListMatchServers has CreateAction in header actions; EditMatchServer has DeleteAction; CreateMatchServer is bare CreateRecord. EditMatchServer adds mutateFormDataBeforeFill (echoes existing api_token through to the form so admin can use ->revealable() to confirm the stored token) + mutateFormDataBeforeSave (drops empty credentials_encrypted so blank password field preserves the existing token).

3. **`App\Filament\Resources\MatchServerResource\RelationManagers\BookingsRelationManager`** — read-only relation manager. $relationship='bookings'. Form schema is empty (read-only). Table columns: match.scheduled_at, reserved_from, reserved_to, status badge. Action: `view_match` opens `MatchResource::getUrl('edit', ['record' => $record->match_id])` in a new tab. No headerActions, no bulkActions (read-only enforcement).

4. **`App\Jobs\Rcon\TestMatchServerConnectionJob`** — `final`, ShouldQueue + Queueable. Constructor: `public readonly string $matchServerId`. handle() method-injects CrconHealthProbe → MatchServer::findOrFail($this->matchServerId) → $probe->probe($server) → $server->update(['last_test_at'=>now(), 'last_test_status'=>$result['status'], 'last_test_error'=>$result['error']]).

5. **`App\Services\Rcon\CrconHealthProbe`** — `final`, stateless. `probe(MatchServer $server): array{status:'ok'|'error', error:?string, map_rotation:?array}`. Decrypts credentials_encrypted via inline PHPDoc cast (`@var array{api_token?: string}|null`). Missing/empty token → early return permission_denied without HTTP call. Otherwise `Http::withToken($token)->timeout(10)->get($url)` where $url='http://{host}:{port}/api/get_map_rotation'. Status mapping: 200→ok+map_rotation; 401→error+auth_failed; other failed HTTP→error+unreachable; Throwable→error+unreachable.

6. **`App\Filament\Resources\MatchResource`** (modified, surgical) — preserves Phase 4 wizard + 4 RelationManagers; adds: IconColumn 'manual_entry_required' (boolean, danger-triangle-when-true), Filter 'manual_entry_required' (where=true; admin uses it to find every flagged match), Action 'clear_manual_entry_flag' (visible when flag=true; requires confirmation; flips false + success notification).

7. **`App\Providers\Filament\AdminPanelProvider`** (modified) — appended `MatchServerResource::class` to the `->resources([...])` list after `CategoryResource`.

8. **`Database\Seeders\PermissionSeeder`** (modified) — appended `'manage-rcon'` to the canonical $permissions list. firstOrCreate makes it idempotent; super-admin role inherits it implicitly via the `Permission::whereIn('name', $permissions)->get()` whitelist sync.

9. **`lang/en/admin.php`** (modified) — added `match.fields.manual_entry_required`, `match.actions.clear_manual_entry_flag`, `match.actions.clear_manual_entry_flag_success` keys.

### Tests modified (2 created, 1 RED→GREEN)

10. **`tests/Feature/Phase8/MatchServerResourcePresentTest`** — fresh 9-case GREEN suite: (1) resource registered with admin panel; (2) ListMatchServers page mounts; (3) CreateMatchServer page renders expected form fields (name, host, port_rcon, region, credentials_encrypted.api_token, is_active); (4) EditMatchServer page mounts on existing server; (5) BookingsRelationManager mounts on the server edit page (Pitfall 3 typo guard); (5b) BookingsRelationManager renders booking rows; (6) canViewAny returns false for users without manage-rcon; (7) canViewAny returns true for users with manage-rcon; (8) Test Connection table action dispatches TestMatchServerConnectionJob via Bus::fake.

11. **`tests/Feature/Phase8/TestMatchServerConnectionJobTest`** — fresh 5-case GREEN suite: (1) HTTP 200 → last_test_status='ok', last_test_error=null, last_test_at=now-ish; (2) HTTP 401 → last_test_status='error', last_test_error=__('rcon.errors.auth_failed'); (3) HTTP 500 → last_test_status='error', last_test_error=__('rcon.errors.unreachable'); (4) ConnectionException → last_test_status='error', last_test_error=__('rcon.errors.unreachable'); (5) empty api_token → permission_denied without HTTP call (Http::assertNothingSent). Uses Http::preventStrayRequests() + Http::fake() with hostname:port URL keys.

12. **`tests/Feature/Phase8/RconUnreachableFlagsManualTest`** — Wave-0 RED stub from plan 08-01 replaced with 4-case GREEN suite: (1) no match_end → manual_entry_required=true, no MatchResult; (2) manual_error event only (no match_end), CloseMatchJob dispatched → manual_entry_required=true; (3) match_end normal path → manual_entry_required stays false; (4) manual_error event persists in match_events with payload.kind+payload.detail intact (D-019 audit trail).

## Task Commits

1. **GREEN Task 1 — MatchServerResource + Pages + BookingsRelationManager + manage-rcon permission** — `aeb8a21` (feat)
2. **GREEN Task 2 — MatchResource extension + 9 GREEN job/unreachable tests** — `aa279af` (feat)

**Plan metadata commit:** to follow this SUMMARY.

## Files Created

### Application code (5)
- `apps/web/app/Filament/Resources/MatchServerResource.php`
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/ListMatchServers.php`
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/CreateMatchServer.php`
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/EditMatchServer.php`
- `apps/web/app/Filament/Resources/MatchServerResource/RelationManagers/BookingsRelationManager.php`
- `apps/web/app/Jobs/Rcon/TestMatchServerConnectionJob.php`
- `apps/web/app/Services/Rcon/CrconHealthProbe.php`

### Tests (2)
- `apps/web/tests/Feature/Phase8/MatchServerResourcePresentTest.php`
- `apps/web/tests/Feature/Phase8/TestMatchServerConnectionJobTest.php`

## Files Modified

### Application code (2)
- `apps/web/app/Filament/Resources/MatchResource.php` — IconColumn 'manual_entry_required', Filter, clear_manual_entry_flag action.
- `apps/web/app/Providers/Filament/AdminPanelProvider.php` — appended MatchServerResource::class.

### Database / config / i18n (2)
- `apps/web/database/seeders/PermissionSeeder.php` — appended 'manage-rcon'.
- `apps/web/lang/en/admin.php` — manual_entry_required field/action keys.

### Tests (1)
- `apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php` — Wave-0 RED → 4-case GREEN.

## Decisions Made

See `key-decisions` in the frontmatter. Highlights:

- **`Filament::getResource()` does not exist** in Filament v3 — used `getCurrentPanel()->getResources()` containment check instead.
- **EditMatchServer.mutateFormDataBeforeSave** strips empty credentials_encrypted so blank password field on edit preserves the stored token.
- **BookingsRelationManager view_match action** uses MatchResource::getUrl directly (match_id is NOT NULL FK; null guard was dead code per PHPStan).
- **CrconHealthProbe single Throwable catch** — Larastan flagged the more specific `catch (ConnectionException|Throwable)` as dead code at the static-analysis layer.
- **MatchServerResource canViewAny gate** uses `auth()->user()?->can('manage-rcon') ?? false` — null-safe operator avoids "Trying to call can() on null" when canViewAny is invoked outside a panel request (e.g., from console).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug in plan code] `Filament::getResource()` does not exist on Filament v3 FilamentManager**

- **Found during:** Task 1 first iteration of MatchServerResourcePresentTest case 1.
- **Issue:** Plan's <tasks> Task 1 behaviour list bullet (1) reads: "Resource exists at admin path (`Filament::getResource(MatchServerResource::class)` returns non-null)." Filament v3's FilamentManager facade has no `getResource` method — first iteration threw `BadMethodCallException: Call to undefined method Filament\FilamentManager::getResource()`.
- **Fix:** Replaced with the equivalent canonical check: `Filament::getCurrentPanel()->getResources()` returns the registered class list; assertion is `expect($panel->getResources())->toContain(MatchServerResource::class)`. Same semantic — verifies the resource is registered with the admin panel.
- **Files modified:** `apps/web/tests/Feature/Phase8/MatchServerResourcePresentTest.php`.
- **Commit:** Folded into Task 1 commit `aeb8a21`.
- **Plan correctness:** The plan's intent (verify the resource is registered) is preserved; only the API call shape changes.

**2. [Rule 1 — PHPStan-flagged dead code in plan <interfaces>] `catch (ConnectionException|Throwable)` is dead code**

- **Found during:** Task 1 PHPStan verification.
- **Issue:** Plan's <interfaces> CrconHealthProbe block writes `catch (\Throwable $e)` with a comment 'ConnectionException, etc.' First iteration tried the more specific `catch (ConnectionException|Throwable)` which PHPStan rejected: "Dead catch - Illuminate\Http\Client\ConnectionException is never thrown in the try block" — Larastan's flow analysis only sees the abstract throws of `Http::withToken(...)->get(...)` which is the Throwable supertype, not the underlying Guzzle ConnectionException at the static-analysis layer.
- **Fix:** Use a single `catch (Throwable)` clause. Behaviour is unchanged (ConnectionException is a Throwable subclass) and the translated key still surfaces correctly. Test case 4 (Http::fake closure throws ConnectionException) still passes — the Throwable catch absorbs the subclass.
- **Files modified:** `apps/web/app/Services/Rcon/CrconHealthProbe.php`.
- **Commit:** Folded into Task 1 commit `aeb8a21`.
- **Plan correctness:** Preserved; the intent (catch all underlying transport failures and surface a translated key) is unchanged.

**3. [Rule 1 — Plan typo] MatchEventFactory `->for($match)` throws BadMethodCallException**

- **Found during:** Task 2 first iteration of RconUnreachableFlagsManualTest cases 2-4.
- **Issue:** Plan's <tasks> Task 2 behaviour list uses `MatchEvent::factory()->for($match)->...` in pseudo-code. Laravel's factory `->for()` resolves the relation by class name — it calls `$factory->gameMatch()` for a `GameMatch` model. MatchEvent's BelongsTo method is named `match()` (D-04-03-A LOCKED naming), so the factory call throws `Call to undefined method App\Models\MatchEvent::gameMatch()`. Same blocker landed in plan 08-08's RconMatchResultIngestionTest; same fix applies.
- **Fix:** Use the explicit-FK form: `MatchEvent::factory()->kill($k, $v)->create(['match_id' => $match->id])`. Same pattern as RconMatchResultIngestionTest (plan 08-08).
- **Files modified:** `apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php`.
- **Commit:** Folded into Task 2 commit `aa279af`.
- **Plan correctness:** Test intent unchanged; only the factory invocation shape differs.

### Auth Gates

None — implementation-only plan, no external authentication required at runtime.

### Architectural Changes (Rule 4 — required user decision)

None.

---

**Total deviations:** 3 (all Rule 1 plan-code-vs-reality auto-fixes; no Rule 2/3/4 changes).
**Impact on plan:** Zero changes to plan must_haves contract. All three fixes are mechanical (API shape, dead-code static-analysis nit, factory invocation form) — the behavioural contract is preserved bit-for-bit.

## Issues Encountered

- **`make` not on PATH** (same as plans 08-02..08-08). CLAUDE.md §1 documents Makefile aliases as the canonical container surface; `make` itself isn't installed in this session's host. Resolved by invoking `docker compose exec -T web ./vendor/bin/…` directly — still CLAUDE.md §1 / D-021 compliant (all PHP/Pest/Pint/PHPStan ran inside the web container).
- **Pint auto-fixes** — Task 1 commit triggered Pint's `ordered_imports` rule on BookingsRelationManager.php (the `MatchServer` import for the `{@see MatchServer::bookings()}` docblock was already alphabetised correctly, but Pint also wanted `fully_qualified_strict_types` applied to the inline FQCN reference in the docblock). Pint auto-applied; no manual intervention needed.
- **First-iteration PHPStan errors** — 11 errors across CrconHealthProbe (Larastan schema-aware inference: credentials_encrypted is `string` at the schema layer, not `array` from the cast), EditMatchServer.mutateFormDataBeforeSave (mixed-vs-null comparison), and BookingsRelationManager.view_match (dead null-check on match_id which is a NOT NULL FK). All resolved with the existing project-wide pattern (inline `/** @var ... */` PHPDoc cast for the credentials_encrypted access; `array_key_exists + empty()` for the strip-empty path; direct `MatchResource::getUrl` without null guard). PHPStan L8 ended 0 errors.

## User Setup Required

None — internal Filament resource + async job + service + i18n keys + tests. The new `manage-rcon` permission is seeded automatically on `php artisan db:seed --class=PermissionSeeder` (also runs in CI's `migrate:fresh --seed`). To grant a user the permission in dev: `User::find(...)->givePermissionTo('manage-rcon');`. No migration runs (PermissionSeeder uses Spatie's permissions+permission_role tables which already exist from Phase 1).

## Next Phase Readiness

- **Plan 08-10 (apps/rcon-worker outbound normaliser TS mirror) is unblocked at the manual_error/match_end ingest seam.** RconUnreachableFlagsManualTest case 2's manual_error wire shape (payload.kind, payload.detail) is now anchored to the existing MatchEventFactory state + ingest path. Plan 08-10 owner: replicate the strict type check on the TS side.

- **Plan 08-11 (BookingScheduler) is unblocked at the result-side.** Scheduler will dispatch CloseMatchJob at booking.reserved_to; Wave 6 has the flag-flip behaviour pre-verified end-to-end (case 1 in RconUnreachableFlagsManualTest).

- **Plan 08-12 (E2E scrim happy path) is unblocked at the admin-side.** Admin can register MatchServer via Filament, run Test Connection, and curate flagged matches via the existing MatchResource (extended this plan). ScrimE2EHappyPathTest still RED; plan 08-12 owns the full chain (OAuth → signup → BookingScheduler → CRCON ingest → MatchResult).

- **No blockers.** Phase 8 baseline: **plan 08-08 → 1103 PASS / 2 FAIL; plan 08-09 → 1121 PASS / 1 FAIL.** Net change: **+18 PASS** (9 Filament + 5 job + 4 D-019 unreachable), **−1 FAIL** (RconUnreachableFlagsManualTest RED stub closed). The 1 remaining FAIL is:
  - `ScrimE2EHappyPathTest` → plan 08-12 (Phase 8 capstone E2E)

## Self-Check: PASSED

Verified before finalising:

**Files created (9) — all exist:**
- `apps/web/app/Filament/Resources/MatchServerResource.php` ✓
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/ListMatchServers.php` ✓
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/CreateMatchServer.php` ✓
- `apps/web/app/Filament/Resources/MatchServerResource/Pages/EditMatchServer.php` ✓
- `apps/web/app/Filament/Resources/MatchServerResource/RelationManagers/BookingsRelationManager.php` ✓
- `apps/web/app/Jobs/Rcon/TestMatchServerConnectionJob.php` ✓
- `apps/web/app/Services/Rcon/CrconHealthProbe.php` ✓
- `apps/web/tests/Feature/Phase8/MatchServerResourcePresentTest.php` ✓
- `apps/web/tests/Feature/Phase8/TestMatchServerConnectionJobTest.php` ✓

**Files modified (5) — all staged in commits:**
- `apps/web/app/Filament/Resources/MatchResource.php` (IconColumn + Filter + clear-flag Action) ✓
- `apps/web/app/Providers/Filament/AdminPanelProvider.php` (MatchServerResource registered) ✓
- `apps/web/database/seeders/PermissionSeeder.php` (manage-rcon appended) ✓
- `apps/web/lang/en/admin.php` (manual_entry_required field/action keys) ✓
- `apps/web/tests/Feature/Phase8/RconUnreachableFlagsManualTest.php` (RED→4 GREEN) ✓

**Commits (2) — reachable via `git log --oneline -3`:**
- `aeb8a21` feat(08-09): MatchServerResource + Pages + BookingsRelationManager + manage-rcon permission ✓
- `aa279af` feat(08-09): MatchResource extension + 9 GREEN job/unreachable tests ✓

**Quality gates re-run before SUMMARY:**
- `pest --filter='TestMatchServerConnectionJobTest|RconUnreachableFlagsManualTest|MatchServerResourcePresentTest|MatchResourcePresentTest'` → **43 PASS, 101 assertions** ✓
- `pest` full project → **1121 PASS, 1 FAIL** (1103 → 1121 = +18 PASS; 2 → 1 FAIL = −1 RED closed; **0 regressions**) ✓
- `phpstan analyse` (full project, level 8) → **0 errors** ✓
- `pint --test` → **PASS** (562 files clean) ✓

**TDD Gate Compliance:** Both `tdd="true"` tasks landed GREEN behavioural suites. Task 1 (MatchServerResourcePresentTest) was empirically RED before resource existed (9/9 failed). Task 2 (RconUnreachableFlagsManualTest) replaced the pre-existing Wave-0 RED stub from plan 08-01. Plan-level type is `execute` (not `tdd`); per-task TDD discipline IS satisfied via both empirical and pre-existing RED gates.

**Plan correctness verifications (per the plan's `<verification>` block):**
- All Phase 8 plans 08-01..08-08 tests still GREEN (no regressions in InternalApiRoutesPresent, ManualOverrideWins, MatchEventIdempotency, MatchEventIngestService, MatchEventNormaliserContract, MatchPlayerStatAggregator, MatchServerBookingOverlap, MatchServerCredentialEncryption, RconMatchResultIngestion, VerifyRconSignature) ✓
- Phase 4 MatchResourcePresentTest still GREEN (surgical MatchResource edit non-regressing on wizard + 4 RelationManagers) ✓
- ScrimE2EHappyPathTest still RED (plan 08-12 owns) ✓
- PHPStan L8 clean ✓
- Pint `--test` clean ✓

---
*Phase: 08-rcon-automation*
*Completed: 2026-05-14*
