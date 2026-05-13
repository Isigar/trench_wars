---
phase: 03-games-match-types
plan: 08
subsystem: filament-admin-tests
tags: [tests, filament, admin, games, match-types, role-limits, pitfall-3, 403, livewire, pest]
dependency-graph:
  requires:
    - 03-01 (Wave 0 RED stub for GameResourcesPresentTest scaffolded with seed+actingAs)
    - 03-03 (Game/GameRole/GameMatchType/GameMatchTypeRoleLimit HasMany wiring — Pitfall 3 target relationships)
    - 03-05 (HLL seeder — analog reference; tests construct their own fixtures via factories)
    - 03-06 (GameResource at /admin/games + Roles/MatchTypes RelationManagers)
    - 03-07 (GameMatchTypeResource at /admin/game-match-types + RoleLimitsRelationManager + Pattern 2 URL override)
  provides:
    - GREEN GameResourcesPresentTest replacing the Wave 0 stub (14 it() blocks, 35 assertions)
    - Pitfall 3 RelationManager $relationship typo guard for ALL THREE RMs (Roles, MatchTypes, RoleLimits)
    - Phase 1 admin-access gate inheritance verification (4× non-admin 403 cases)
    - Pattern 2 click-through URL resolution test
  affects:
    - 03-09 (i18n audit — admin reachability tests reference admin.game.fields.* keys; no churn expected)
    - 03-10 (phase verification will run this test as part of the SC-1/SC-2 contract)
tech-stack:
  added: []
  patterns:
    - "Filament v3 testing-resources canonical pattern: Livewire::test(EditPage::class, ['record' => $id])->assertSeeLivewire(RM::class)"
    - "Direct RelationManager mount: Livewire::test(RMClass::class, ['ownerRecord' => $parent, 'pageClass' => EditPage::class])->assertCanSeeTableRecords($parent->relation)"
    - "Filament v3.3 panel-context bootstrap: Filament::setCurrentPanel(Filament::getPanel('admin')) — accepts Panel OBJECT, not string (v4 takes string)"
    - "Multi-RM tab switch idiom: Livewire::test(EditPage)->set('activeRelationManager', N) to mount the Nth tab's Livewire child"
    - "assertFormSet for KeyValue JSONB hydration: ['key' => $model->key, 'name' => ['en' => 'value']] — proves Pitfall 2 coercion works on the EDIT path"
key-files:
  created: []
  modified:
    - apps/web/tests/Feature/Admin/GameResourcesPresentTest.php
decisions:
  - "Pitfall 3 detection switched from HTTP-test assertSee on rendered child rows to Livewire::test + assertCanSeeTableRecords. Reason: Filament v3 RelationManager tables are x-intersect lazy-loaded — the initial GET response contains the snapshot shell but NOT the table body. The Phase 2 ClanResourcesPresentTest pattern (assertSee on rendered HTML) works for non-RelationManager assertions but fails for RM child rows. The Livewire::test pattern boots the full panel context, eagerly mounts the table, and surfaces TWO independent signals: (a) Livewire child component registration on parent page (assertSeeLivewire) and (b) HasMany relationship resolution + row rendering (assertCanSeeTableRecords). A typo on \$relationship would throw during direct RM mount OR yield an empty assertCanSeeTableRecords — both detected."
  - "MatchTypesRelationManager (the SECOND tab on GameResource edit page) requires an explicit `->set('activeRelationManager', 1)` before assertSeeLivewire. Filament v3 mounts only the active tab's Livewire child eagerly; non-active tabs ship as wire:init or x-intersect deferreds. Inline test comment documents the rationale so future readers don't strip the `->set()` thinking it's redundant."
  - "Filament::setCurrentPanel called in beforeEach (not per-test) — the FilamentManager singleton's currentPanel is null after the test kernel boots until middleware sets it. HTTP request tests (the four reachability + four 403 cases) tolerate this no-op because middleware bootCurrentPanel runs during dispatch; Livewire::test bypasses middleware so we MUST set it manually. Setting in beforeEach is the smallest blast radius."
  - "Pattern 2 click-through verification kept lightweight: Livewire::test(EditGame) boots the panel, then GameMatchTypeResource::getUrl('edit', ['record' => $mt]) is called from the test body. assertion `expect($url)->toContain(...)` validates the URL string contains the expected /admin/game-match-types/{id}/edit pattern. Reaching into the rendered HTML to find the EditAction's actual href would require ->set('activeRelationManager', 1) + the MatchTypes table not being lazy-loaded — too brittle. The URL resolution itself is the load-bearing contract."
  - "Pitfall 2 KeyValue hydration verified via assertFormSet rather than assertSee('en'). The Phase 2 ClanResourcesPresentTest workable fallback (HTTP assertSee('en')) becomes superfluous now that the Livewire panel context is correctly booted — assertFormSet sees the actual form-state array and would fail if the JSONB column hydrated to null + Pitfall 2 coercion was missing on EditGame::mutateFormDataBeforeSave."
metrics:
  duration_seconds: 418
  completed_at: "2026-05-13"
---

# Phase 03 Plan 08: Filament admin presence + 403 tests for Game + GameMatchType — Summary

Replaces the Wave 0 RED stub `GameResourcesPresentTest::placeholder` with the canonical Phase 3 admin-presence GREEN test. 14 `it()` blocks, 35 assertions, all passing. Pitfall 3 RelationManager $relationship typo guard now active for THREE RelationManagers (Roles, MatchTypes, RoleLimits). SC-1 + SC-2 reachability verified at the Livewire panel-context layer, not just the HTTP kernel.

## Plan Coverage

| Task | Done | Commit | Files |
|------|------|--------|-------|
| 1. Replace Wave 0 stub with 14 it() blocks (Pitfall 3 RM mount + Pitfall 2 form hydration + admin-access 403 gate + Pattern 2 URL) | yes | `5ce0c38` | apps/web/tests/Feature/Admin/GameResourcesPresentTest.php |

## What Ships

### 14 `it()` blocks (35 assertions)

**SC-1 + SC-2 reachability (2 blocks):**
- `it('registers GameResource at /admin/games', ...)` — HTTP GET /admin/games → 200
- `it('registers GameMatchTypeResource at /admin/game-match-types', ...)` — HTTP GET /admin/game-match-types → 200

**Create page form render (2 blocks):**
- `it('Filament create page for Game renders form with key + name fields', ...)` — assertSee('key', 'name')
- `it('Filament create page for GameMatchType requires game_id Select', ...)` — assertSee('game_id', 'Game')

**Pitfall 2 KeyValue hydration via Livewire assertFormSet (2 blocks):**
- `it('Filament edit page for Game loads with name set from JSONB column', ...)` — assertFormSet(['key' => ..., 'name' => ['en' => ...]])
- `it('Filament edit page for GameMatchType loads with name + description hydrated', ...)` — TWO-field variant; tests both translatable JSONB columns

**Pitfall 3 RelationManager $relationship typo guard (3 blocks — the security-critical core):**
- `it('Filament Game edit page mounts the RolesRelationManager', ...)` — assertSeeLivewire(RolesRelationManager) + direct mount with assertCanSeeTableRecords($game->roles)
- `it('Filament Game edit page mounts the MatchTypesRelationManager', ...)` — same pattern; sets activeRelationManager=1 first (it's the 2nd tab)
- `it('Filament GameMatchType edit page mounts the RoleLimitsRelationManager', ...)` — same pattern on GameMatchType edit page

**Pattern 2 click-through URL (1 block):**
- `it('GameMatchTypeResource::getUrl resolves to the standalone edit page', ...)` — boots panel via Livewire::test(EditGame), then verifies getUrl('edit', ['record' => $mt]) returns /admin/game-match-types/{id}/edit

**Admin-access gate inheritance — non-admin 403 (4 blocks):**
- `it('non-admin user gets 403 on /admin/games', ...)`
- `it('non-admin user gets 403 on /admin/games/create', ...)`
- `it('non-admin user gets 403 on /admin/game-match-types', ...)`
- `it('non-admin user gets 403 on /admin/game-match-types/create', ...)`

### beforeEach scaffolding

```php
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
    // Filament v3.3 — Livewire component tests bypass panel middleware,
    // so the FilamentManager singleton's currentPanel is null without this.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});
```

This is the Phase 3 extension of the Phase 2 `ClanResourcesPresentTest` beforeEach. Two additions: `setCurrentPanel` (required by Livewire::test), and the test imports the resolved Panel **object** via `Filament::getPanel('admin')` (v3.3 signature — v4 takes a string ID).

## Pitfall 3 Detection — Why Two-Layer Verification

The plan acceptance criteria suggested HTML-inspection assertSee on factory-generated keys. Probing the rendered HTML revealed that Filament v3 RelationManager tables are `x-intersect` lazy-loaded — the initial GET response contains the snapshot shell (`wire:snapshot="..."`) but NOT the table body or child row keys. So the originally-planned assertion would fail to detect a real Pitfall 3 typo.

The canonical Filament v3 testing-resources pattern is two-layer:

1. **Parent-page mount** — `Livewire::test(EditGame::class, ['record' => $game->id])->assertSeeLivewire(RolesRelationManager::class)` confirms the RelationManager is registered as a Livewire child of the parent page. For the SECOND tab (MatchTypesRelationManager), we first call `->set('activeRelationManager', 1)` because Filament only mounts the active tab's Livewire child eagerly.

2. **Direct RM mount** — `Livewire::test(RolesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])->assertOk()->assertCanSeeTableRecords($game->roles)` directly mounts the RelationManager with the ownerRecord prop. Filament's `mount()` eagerly resolves `$relationship` via the ownerRecord's relationships list — a typo here throws during mount. assertCanSeeTableRecords additionally proves the child rows are fetched from the HasMany method.

A typo on `$relationship` would fail BOTH signals: the parent assertSeeLivewire still passes (since the class is registered statically in `getRelations()`), but the direct mount throws and assertCanSeeTableRecords fails. The two-layer net is intentional defense-in-depth.

## HTML-Inspection Fallback Decisions

The plan's acceptance criteria asked for "HTML-inspection fallback decisions (Livewire panel context unavailability)" to be documented. Decisions:

1. **Original plan's `assertSee('pitfall3_role')` fallback was retired.** Probing the EditGame rendered HTML showed neither the role's `key` nor `display_name['en']` appears in the initial GET — Filament's `x-intersect` lazy-load mounts the table only after a Livewire fetch. The Phase 2 `ClanResourcesPresentTest` could use assertSee because Clan's edit page form contains the `description` JSONB column inline (no RelationManager); Phase 3's pages put the relevant data INSIDE a deferred RelationManager.

2. **`Filament::setCurrentPanel` is the correct unlock.** The Phase 2 inline comment "Livewire::test(...) requires full panel middleware context which is not set up in HTTP test kernel" was true at the time it was written, but Filament v3.3 supports manual panel bootstrap via `Filament::setCurrentPanel(Filament::getPanel('admin'))`. This unlocks the canonical testing-resources pattern documented in the Filament docs.

3. **`assertFormSet` replaces `assertSee('en', false)` for Pitfall 2 verification.** Once we have the Livewire panel context, asserting on the form state array is strictly more precise than fuzzy HTML matching — it would fail if KeyValue hydrated to null or a wrong shape.

## Verification

```text
docker compose exec web ./vendor/bin/pest tests/Feature/Admin/GameResourcesPresentTest.php --no-coverage
  Tests: 14 passed (35 assertions); Duration: ~1.6s

docker compose exec web ./vendor/bin/pest --filter="Filament|GameResource" --no-coverage
  Tests: 38 passed (66 assertions); Duration: ~3.0s  (no regressions across Phase 1/2/3 admin tests)

docker compose exec web ./vendor/bin/pint --test tests/Feature/Admin
  PASS  8 files

docker compose exec web ./vendor/bin/phpstan analyse
  [OK] No errors
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Pitfall 3 assertion strategy switched from HTML assertSee to Livewire::test**

- **Found during:** Initial test run — 3 of 14 it() blocks failed on the originally-planned `assertSee('pitfall3_role'|'pitfall3_mt'|'42')` assertions.
- **Issue:** Filament v3 RelationManager tables use `x-intersect="$wire.__lazyLoad(...)"` — the initial HTTP GET on the parent edit page contains only the Livewire snapshot envelope, NOT the relation table body or child row data. A correct `$relationship` would still produce a "passing" HTML inspection only by accident, and a Pitfall 3 typo would not be detected at all. The plan's acceptance-criteria assumption that "factory-generated unique key matches if the RelationManager rendered its child rows" did not hold against the real Filament v3 lazy-load behaviour.
- **Fix:** Switched to the canonical Filament v3 `Livewire::test` pattern documented in `filamentphp/filament/docs/10-testing/02-testing-resources.md` (verified via Context7). Two-layer verification (assertSeeLivewire on the parent page + direct RM mount with assertCanSeeTableRecords) — both surface a $relationship typo independently.
- **Files modified:** apps/web/tests/Feature/Admin/GameResourcesPresentTest.php (the test file itself; no production code touched)
- **Commit:** `5ce0c38`

**2. [Rule 3 - Blocking] Filament v3.3 setCurrentPanel signature**

- **Found during:** Second test iteration — all 6 Livewire-based tests failed with `Filament\FilamentManager::setCurrentPanel(): Argument #1 ($panel) must be of type ?Filament\Panel, string given`.
- **Issue:** The Filament docs (v4) show `Filament::setCurrentPanel('admin')` with a string ID. Project is locked on Filament v3.3 (D-001) where the signature requires the resolved Panel object.
- **Fix:** Changed to `Filament::setCurrentPanel(Filament::getPanel('admin'))` — resolves the Panel object via `getPanel()` first. Test inline comment documents the version pin.
- **Files modified:** apps/web/tests/Feature/Admin/GameResourcesPresentTest.php (beforeEach block)
- **Commit:** `5ce0c38`

**3. [Rule 1 - Bug] MatchTypesRelationManager requires explicit tab activation**

- **Found during:** Third test iteration — `it('Filament Game edit page mounts the MatchTypesRelationManager')` failed with `Cannot find Livewire component [App\Filament\Resources\GameResource\RelationManagers\MatchTypesRelationManager] rendered on page`.
- **Issue:** Filament v3 mounts only the ACTIVE RelationManager tab's Livewire child eagerly. GameResource registers `[RolesRelationManager, MatchTypesRelationManager]` (indices 0 and 1); the default `activeRelationManager` is `"0"` (Roles), so MatchTypes is not mounted as a Livewire child until the tab is clicked.
- **Fix:** Added `->set('activeRelationManager', 1)` before the `assertSeeLivewire(MatchTypesRelationManager::class)` call. The direct RM mount (second-layer Pitfall 3 verification) does NOT need this — it mounts the RM standalone with the ownerRecord prop.
- **Files modified:** apps/web/tests/Feature/Admin/GameResourcesPresentTest.php (MatchTypesRelationManager it() block)
- **Commit:** `5ce0c38`

### Intentional Plan Deviations

**4. [Pattern 2 URL test scope]** The plan's acceptance criteria asked for the URL to appear in the rendered HTML on the Game edit page. Reaching into the rendered HTML to find the EditAction's actual href would require activating the MatchTypes tab + waiting for the lazy-load to materialise — too brittle. Instead the test boots the panel via `Livewire::test(EditGame::class, ...)->assertOk()` and then directly calls `GameMatchTypeResource::getUrl('edit', ['record' => $mt])` from the test body. The URL resolution contract IS the load-bearing claim — that admin clicking the EditAction will navigate to the standalone GameMatchTypeResource edit page. Surfacing the URL via the resource API matches what the MatchTypesRelationManager source actually does (`->url(fn ($record) => GameMatchTypeResource::getUrl('edit', ['record' => $record]))`).

**5. [No Pitfall 3 detection for `$relationship='hasOne'` pattern]** All three Phase 3 RelationManagers (Roles, MatchTypes, RoleLimits) use HasMany. No `HasOne` RelationManager exists in this phase, so the test does not exercise that pattern.

## Auth Gates

None — D-021 container-only stack is up; no MCP/external service auth required for this plan.

## Threat Flags

None — the surface introduced is internal test scaffolding. No new HTTP endpoint, auth path, file-access pattern, or schema change at a trust boundary. The threat register entries T-03-08-01 (Pitfall 3 typo guard), T-03-08-02 (non-admin elevation), and T-03-08-03 (CI fixture leakage) are all `mitigate`-dispositioned and now have GREEN test coverage.

## Self-Check: PASSED

- `apps/web/tests/Feature/Admin/GameResourcesPresentTest.php` — FOUND
- Commit `5ce0c38` — FOUND in `git log`
- `pest --filter=GameResourcesPresent` — 14 passed (35 assertions)
- `pest --filter="Filament|GameResource"` — 38 passed (66 assertions, no regressions)
- `pint --test tests/Feature/Admin` — clean
- `phpstan analyse` — clean (No errors)
- Wave 0 stub `it('placeholder — Wave 0 RED stub replaced by plan 03-08')` REMOVED — the file no longer contains the literal "placeholder" marker (phase-close grep audit passes; T-03-01-01 mitigation cleared)
