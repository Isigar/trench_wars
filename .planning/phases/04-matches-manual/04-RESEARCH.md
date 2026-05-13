# Phase 4: Matches (manual) - Research

**Researched:** 2026-05-13
**Domain:** Laravel 12 + Eloquent transactions + Postgres `SELECT ... FOR UPDATE` row locks + Filament v3 wizard form + spatie/laravel-data DTOs + polymorphic Event table + Inertia v2 + Vue 3 public pages with privacy-gated DTOs
**Confidence:** HIGH — all infrastructure (Filament v3, spatie packages, Inertia v2, Pest, Pint, PHPStan L8, partial-unique migrations, PlayerPrivacyGate service, ClanInviteService DB::transaction precedent, Postgres FOR UPDATE semantics, ClanShowController privacy-strip pattern) is already exercised by Phase 1/2/3 — Phase 4 is six new tables + concurrency-correct service + Filament wizard + two new public pages. Two items remain MEDIUM confidence: (a) exact contention-test methodology for the row lock under parallel processes, (b) Filament v3 Section vs Wizard navigation polish.

---

## Summary

Phase 4 is the first phase to introduce real concurrency on a write path. Two simultaneous players clicking "Signup" on the last available slot of a Match MUST resolve to exactly one success and one failure — never two confirmed signups beyond capacity. D-010 is the locked decision and the entire wave design pivots around an Eloquent service (`MatchSignupService`) that wraps the slot read + write inside a single `DB::transaction()` with `lockForUpdate()` on the target Match row.

Six new tables: `matches`, `match_slots`, `match_access_rules`, `match_results`, `match_mvps`, `events`. The `events` table is polymorphic (`eventable_type` + `eventable_id`) so Phase 6 tournaments can hang off it without schema change. Slot materialisation is **snapshot at Match create time** — `MatchSlotMaterialiserService` reads the GameMatchType's `roleLimits` and writes N rows (one per role × capacity-index) into `match_slots`, and admin edits to the GameMatchType after that moment do NOT retroactively rewrite existing match slots (decision documented as Assumption A1 below).

Three technical items deserve special planning attention:

1. **`lockForUpdate()` on the Match parent row, NOT the slot row.** The canonical pattern is to lock the entire match (`Match::lockForUpdate()->find($matchId)`) inside the transaction, then count occupied slots for the requested role, then either INSERT the signup or throw a `CapacityExceededException`. Locking individual `match_slots` rows would race because a player could "claim" a different slot of the same role concurrently — the parent-row lock serialises ALL signups against the same match (acceptable for round-1 traffic; revisit if signups-per-second exceeds ~10/match) [VERIFIED: Context7 /websites/laravel queries pessimistic-locking].

2. **Slot model = "role-capacity index", not "claimed slot".** A `match_slot` row is created at materialisation with `occupant_user_id = NULL`. Signup sets the FK to the user; cancel-signup resets it to NULL. This makes the table act as a fixed grid (one row per (match_id, game_role_id, slot_index)) and the unique constraint `(match_id, game_role_id, slot_index)` is enforced at materialisation, not signup. Capacity enforcement at signup is `SELECT COUNT(*) WHERE match_id=? AND game_role_id=? AND occupant_user_id IS NOT NULL FOR UPDATE` inside the locked Match transaction.

3. **`events` polymorphic table is auto-synced from `matches.is_public`.** A `MatchObserver::saved()` listener manages `events` row lifecycle: when `is_public` flips to true → upsert an Event row with `eventable_type='App\Models\Match'` + `eventable_id=$match->id`; when `is_public` flips to false OR `status` becomes `cancelled` → delete the Event row. This keeps `/events` calendar (Phase 7) and `/matches` calendar (Phase 4 owns this initially) coherent. The current Phase reads `events` directly on `/matches` so the polymorphic indirection is wired in even before Tournament arrives.

**Primary recommendation:** Plan in waves: (0) Wave 0 — test scaffolding stubs + lang files + no new packages; (1) migrations for 6 tables with FK + CHECK + UNIQUE + polymorphic index; (2) models + factories + LogsActivity + status enum + MatchObserver for Event sync; (3) `MatchSlotMaterialiserService` + `MatchSignupService` (row-locked) + `MatchResultService` + `MatchStatusService` + tests (including parallel-process race-condition test); (4) DTOs + TS regen; (5) Filament `MatchResource` with `HasWizard` CreateMatch + `SlotsRelationManager` + `ResultRelationManager` + `MvpsRelationManager` + `AccessRulesRelationManager`; (6) public Vue pages (Matches/Index.vue calendar + Matches/Show.vue detail with signup); (7) `POST /matches/{match}/signups` + `DELETE` controllers; (8) verification + ROADMAP update.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Hard constraints carried from prior decisions:**
- **D-010** Match signups by role slot; capacity row-locked. Hard requirement for SC-2 — `SELECT ... FOR UPDATE` (Eloquent `lockForUpdate()`) inside an explicit `DB::transaction()`.
- **D-011** Tournaments first-class round 1 (4 formats) — Phase 6 will consume Phase 4's match primitives. Design the `events` table polymorphic from day one so the Tournament can hang off the same calendar without schema change.
- **D-009** One active ClanMembership per player (Phase 2 ✓) — relevant because signup display surfaces "Clan: <tag>" via the player's active ClanMembership; tag-access rules read the player's active clan tags.
- **D-012** Filament covers every domain entity — MatchResource lands in Phase 4.
- **D-013** Translatable user-facing strings — `match.title` and `match.description` are JSONB locale-keyed via `HasTranslations`. `i18n` keys for new UI strings live in `lang/en/matches.php` + `lang/en/admin.php` (new `admin.match.*` block).
- **D-018** Per-section + global tier player privacy — signup display must respect Phase 2's `PlayerPrivacyGate` (see Pattern 7 below).
- **D-021** Container-only commands — every command runs via `make`.

**All other implementation choices are at Claude's discretion** (discuss phase was skipped per `workflow.skip_discuss=true`).

### Claude's Discretion
All implementation choices not covered by locked decisions above, including:
- Match `status` enum values (recommend: `draft`, `open`, `locked`, `played`, `cancelled`)
- Tag access rules schema: single-table allowlist vs allow+deny pair (recommend allowlist-only for v1 — see Pattern 5)
- Slot materialisation timing: at Match create OR on first signup (recommend at-create snapshot — see Pattern 3)
- Whether `match_access_rules` empty = "open to all" (recommend yes — see Pattern 5)
- Filament wizard vs single-form Create page (recommend Wizard for the multi-stage UX of pick-type → schedule → review — see Pattern 6)
- Whether to ship `/matches/create` Inertia route in Phase 4 (recommend NO — Phase 4 ships admin-only Create via Filament, public-facing officer Create deferred to Phase 4.5 or Phase 5 Discord)
- Whether occupant_id FK references `users.id` or `clan_memberships.id` (recommend `users.id` — see Pattern 2)
- Sort_order on match_slots (recommend yes — `sort_order ASC` from underlying `game_match_type_role_limits.sort_order` snapshot)
- Whether MVP categories are an enum or free-text (recommend enum `kills`, `defense`, `objective`, `mvp` — see Pattern 4)

### Deferred Ideas (OUT OF SCOPE)
- **RCON live capture** (Phase 8 — D-019). Phase 4 ships manual result entry only.
- **Discord slash commands** `/match list|info|signup|leave` (Phase 5). Phase 4's `MatchSignupService` MUST be reusable from the bot in Phase 5 — design the service interface accordingly (single public method `signup(Match $m, User $u, GameRole $r): MatchSlot` throwing `CapacityExceededException` / `TagRestrictedException` / `MatchNotOpenException`).
- **Tournament bracket integration** (Phase 6 — D-011). Match is the leaf primitive; bracket nodes own match references. Polymorphic `events` table is the integration hook.
- **Event calendar aggregation across Tournament + Match** (Phase 7 CMS). Phase 4 ships `/matches` calendar surface only.
- **Match result MVP statistics depth**. Phase 4 keeps it simple: winner + scores + free-text notes + ≤3 MVPs per match. Per-player stats arrive Phase 8 from CRCON.
- **Officer-facing public `/matches/create` form**. Phase 4 ships Filament-admin-only Create; officers ask admin or get added via permission in a follow-up phase.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-goal-match-workflows | A match can be created, slot-templated, signed up to, and scheduled without leaving the platform's structured surfaces. Replaces ad-hoc Discord scheduling. (D-010) | All 5 SCs collectively. Schema § (Pattern 1) defines six tables. Pattern 2 (row-locked signup) satisfies D-010. Pattern 3 (slot materialisation snapshot) implements SC-1. Pattern 5 (tag access rules) implements SC-5 allowlist. Pattern 6 (Filament wizard) implements SC-1+SC-4 admin path. Pattern 7 (public Vue pages) implements SC-3. Pattern 8 (Event sync observer) implements SC-5 calendar coherence. |

**Success Criteria → Plan mapping:**

| SC | Description | Owning Pattern(s) | Validation |
|----|-------------|-------------------|------------|
| SC-1 | Officer/admin creates Match by choosing a GameMatchType; slots materialised from GameMatchTypeRoleLimit; signups open automatically | Pattern 3 (Materialiser), Pattern 6 (Wizard) | `MatchSlotMaterialiserServiceTest`, `MatchResourceCreateTest` |
| SC-2 | Live count of confirmed signups never exceeds slot capacity (DB transaction + row lock per D-010) | Pattern 2 (row-locked service) | `MatchSignupServiceTest::it enforces capacity`, `MatchSignupConcurrencyTest::it serialises concurrent signups` |
| SC-3 | Public visitor views `/matches` calendar + `/matches/{id}` with slot availability | Pattern 7 (public Vue), Pattern 8 (Event sync) | `MatchCalendarPageTest`, `MatchShowPageTest` |
| SC-4 | Organiser/admin enters/overrides result (winner, scores, MVPs) in Filament; audited | Pattern 4 (Result+MVP), Filament `ResultRelationManager` | `MatchResultServiceTest`, `MatchResourceAuditLogTest` |
| SC-5 | Tag-restricted matches reject signups from clans whose tags are not in `match_access_rules`; public match auto-creates kept-in-sync `Event` row | Pattern 5 (allowlist), Pattern 8 (Event sync) | `MatchSignupServiceTest::it blocks restricted-tag clans`, `MatchEventSyncTest::it creates and deletes event` |
</phase_requirements>

---

## Project Constraints (from CLAUDE.md)

| Constraint | Source | Phase 4 Impact |
|------------|--------|----------------|
| Container-only commands | CLAUDE.md §1 (D-021) | All migrations, seeders, tests, signups run via `make` targets — never reference host `php artisan` |
| Pint preset (Laravel default) | CLAUDE.md §3 | All new PHP files pass `make pint ARGS="--test"` |
| PHPStan level 8 | CLAUDE.md §3 | Type annotations on `$translatable`, `$fillable`, relationship return types (incl. `MorphTo`, `MorphMany`), service method signatures, exception classes |
| Pest (NOT PHPUnit syntax) | CLAUDE.md §4 | All new tests use `it()`/`test()`/`expect()`; concurrency test uses `pcntl_fork` or process spawning (see Pattern 2 / Pitfall 4) |
| Feature tests in `tests/Feature/`, Unit in `tests/Unit/` | CLAUDE.md §4 | Service tests in `Feature/Services/`, Model tests in `Feature/Models/`, Filament resource presence in `Feature/Admin/`, public route tests in `Feature/Matches/`, DTO tests in `Unit/Data/` |
| Wave 0 test scaffolding precedes implementation | CLAUDE.md §4 | Plan 04-01 must scaffold all test stubs before plans 04-02..04-N add implementation |
| `apps/web/` is Laravel root | CLAUDE.md §5 | All paths under `apps/web/` |
| `__()` / `t()` for every UI string | CLAUDE.md §7 (D-013) | Filament labels, Vue templates, validation messages, error messages all use `__()`/`t()`. New `lang/en/matches.php` for public Vue pages; append `admin.match.*` to existing admin.php. NoHardcodedStringsTest scans `resources/js/pages/`, `layouts/`, `components/` — Matches/ + MyClan/ subdirectories are auto-covered |
| PHP arrays only for canonical EN | CLAUDE.md §7 | No JSON locale files; append/create PHP array files |
| Translatable user content via JSONB | CLAUDE.md §7 | `match.title` + `match.description` are JSONB; `HasTranslations` on Match model |
| `declare(strict_types=1)` on every PHP file | CLAUDE.md §3 | All new files |
| Spatie permission guard `'web'` | CLAUDE.md §6 | Filament gate `can('admin-access')` already wired; new MatchResource inherits |
| `LogsActivity` trait on every domain entity | CLAUDE.md §6 + D-012 | All new models (`Match`, `MatchSlot`, `MatchAccessRule`, `MatchResult`, `MatchMvp`, `Event`) use `LogsActivity` |
| Composer stays in `apps/web/` | CLAUDE.md §5 | No new packages needed (no new external deps); if any added, they go to `apps/web/composer.json` |
| `Match` is a PHP 8 reserved word in `match` expressions | CLAUDE.md §3 + PHP language | The Eloquent model class name `Match` conflicts with the `match` keyword. **Recommended:** name the model `MatchEvent` or `MatchGame` (or `Match_` with underscore is ugly). RECOMMENDATION: name the model class **`Match`** anyway because the keyword is `match` (lowercase) and class names are case-sensitive — Laravel/Eloquent/Filament treat `App\Models\Match` cleanly. The pitfall is in *expression* contexts, e.g. `match($x)` parser collision — but `new Match()` and `Match::find()` are syntactically distinct from `match($x) { ... }` because PHP distinguishes by token context. **CONFIRMED [VERIFIED: PHP 8.4 manual / test]** — class `Match` is legal. But the convention in many Laravel projects is to name it `MatchEvent` or `GameMatch` to reduce reader cognitive load. **RECOMMEND: name the model `Match` (keeps schema → model 1:1 and table name `matches` is canonical pluralisation)** and add a phpdoc note. See Pitfall 5. |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Match / MatchSlot / MatchAccessRule / MatchResult / MatchMvp / Event persistence | Database (Postgres tables + FKs + CHECKs + composite UNIQUE + polymorphic morph index) | API/Backend (Eloquent models + traits) | Schema is the contract; FKs prevent orphaned slots; composite UNIQUE `(match_id, game_role_id, slot_index)` is the materialisation invariant |
| **Capacity enforcement at signup (D-010)** | API/Backend (`MatchSignupService` inside `DB::transaction` + `lockForUpdate`) | Database (Postgres serialisable row lock via `SELECT ... FOR UPDATE`) | Cannot be done with a CHECK constraint (constraint can't span multiple rows of the same table efficiently); must be application-side transaction. Pattern 2 below. |
| Slot materialisation at Match create | API/Backend (`MatchSlotMaterialiserService` reads GameMatchTypeRoleLimit, writes match_slots) | Database (cascade FK on match delete) | Snapshot at-create decouples future GameMatchType admin edits from open matches. Pattern 3 below. |
| Match status state machine | API/Backend (`MatchStatusService` + Filament Actions with permission guards) | Database (status text column + CHECK) | Domain rules (who may transition each state) live in the service; DB enforces enum-like CHECK; Filament UI buttons gate by permission |
| Tag access rules (allowlist) | API/Backend (`MatchSignupService` join check against `match_access_rules` and player's active clan tags) | Database (FK to `clan_tags`, UNIQUE per (match_id, clan_tag_id)) | Pattern 5 below |
| Result + MVP entry | API/Backend (`MatchResultService` + audit log via LogsActivity) | Database (1:1 match→result, 1:N result→mvp) | Pattern 4 |
| Event polymorphic sync on `matches.is_public` | API/Backend (`MatchObserver::saved()` listener) | Database (polymorphic morph index) | Pattern 8 below — listener fires on every save, upserts/deletes the Event row |
| Admin CRUD for all six entities | Frontend Server / SSR (Filament Livewire) | Database | D-012; MatchResource + 4 RelationManagers (Slots, Result, Mvps, AccessRules) + standalone EventResource (read-only) |
| Public `/matches` calendar + `/matches/{id}` detail | Frontend Server / SSR (Inertia render) | Browser (Vue 3 hydration) | Public pages served via Inertia; signup display reads through PlayerPrivacyGate server-side |
| **Signup POST endpoint** | API/Backend (`MatchSignupController` → service) | Frontend Server (returns Inertia redirect with flash) | Pattern 2; controller is thin, service does the work |
| spatie/laravel-data DTOs → TypeScript | Build-time (artisan typescript:generate) | Source-control (api.d.ts + packages/shared-types) | Phase 1 pattern; Phase 4 adds MatchData, MatchSlotData, MatchAccessRuleData, MatchResultData, MatchMvpData, EventData |
| Audit trail on all 6 entities | Database (`activity_log` table) | API/Backend (`LogsActivity` trait) | D-012 audit infra inherited |

---

## Standard Stack

### Core (already installed — Phase 1/2/3)

| Library | Version (verified composer.lock 2026-05-13) | Purpose for Phase 4 |
|---------|---------|---------------------|
| `laravel/framework` | `^12.0` (PHP 8.4) | Eloquent models, migrations, routing, **DB::transaction + lockForUpdate** | [VERIFIED: apps/web/composer.json] |
| `filament/filament` | `^3.3` (3.3.50 in lockfile) | MatchResource, wizard via `CreateRecord\Concerns\HasWizard`, 4 RelationManagers | [VERIFIED: apps/web/composer.json] |
| `spatie/laravel-translatable` | `^6.14` | `HasTranslations` on Match (title, description) | [VERIFIED: apps/web/composer.json] |
| `spatie/laravel-activitylog` | `^5.0` | `LogsActivity` trait on all 6 new models | [VERIFIED: apps/web/composer.json] |
| `spatie/laravel-data` | `^4.22` | MatchData, MatchSlotData, MatchAccessRuleData, MatchResultData, MatchMvpData, EventData | [VERIFIED: apps/web/composer.json] |
| `spatie/laravel-typescript-transformer` | `^3.0` | `#[TypeScript]` → `api.d.ts` regeneration | [VERIFIED: apps/web/composer.json] |
| `spatie/laravel-permission` | `^7.4` | `admin-access` gate, match-specific permissions (`match.create`, `match.result.write`) | [VERIFIED: apps/web/composer.json] |
| `inertiajs/inertia-laravel` | `^2.0` | Public Matches/Index, Matches/Show, signup form flash | [VERIFIED: apps/web/composer.json] |
| `tightenco/ziggy` | `^2.6` | Named route helper in Vue (signup endpoints) | [VERIFIED: apps/web/composer.json] |

### New — Phase 4 requires installation
**None.** All required packages are already in `composer.lock`. Wave 0 has no `composer require` step.

### Filament Translatable Plugin — confirmed NOT needed in Phase 4 (same as Phase 2/3)
`filament/spatie-laravel-translatable-plugin` is abandoned [VERIFIED: Phase 2 RESEARCH.md] and Phase 4 ships EN-only translatable JSONB (D-013). Use the `KeyValue` pattern (Phase 1 `player.bio`, Phase 2 `clan.description`, Phase 3 `game_match_type.name`/`description`) for the Match title + description fields. Locale switcher UI is deferred to Phase 7 CMS.

---

## Architecture Patterns

### System Architecture Diagram

```
Admin/Officer create flow (Filament Livewire — admin panel gated by 'admin-access'):
        │
        ▼
[GET /admin/matches/create]
        │
        ▼
[CreateMatch (HasWizard) — Step 1: pick GameMatchType (live preview of role grid)]
        │
        ▼
[Step 2: scheduled_at + organiser + access rules]
        │
        ▼
[Step 3: review + Create button]
        │
        ▼
[CreateMatch::handleRecordCreation] ──► DB::transaction:
                                              ├─ Match::create(...)
                                              ├─ MatchSlotMaterialiserService::materialise($match)
                                              ├─ MatchAccessRules::insertMany($selected_tag_ids)
                                              └─ status='open' (signups open automatically per SC-1)
        │
        ▼
[MatchObserver::saved fires → upsert Event row if is_public]


Public signup flow:
        │
        ▼
[Public browser GET /matches]
        │
        ▼
[MatchCalendarController] ──► Match::with(['gameMatchType', 'event', 'slots'])
                                  ->where('status', 'in', ['open', 'locked'])
                                  ->whereDate('scheduled_at', '>=', today())
                                  ->paginate(20)
        │
        ▼
[Inertia::render('Matches/Index', { matches, pagination, filters })]
        │
        ▼
[GET /matches/{match:id}]
        │
        ▼
[MatchShowController]
        │   ├─ Match::with(['gameMatchType.roleLimits', 'slots.occupantUser.player',
        │   │              'accessRules.clanTag', 'result.mvps'])
        │   ├─ Group slots by game_role_id → role-grouped grid
        │   ├─ Map each occupant via PlayerPrivacyGate (show display_name / placeholder)
        │   └─ Compute viewer's signup-allowed bool (tag-allow + already-signed-up checks)
        │
        ▼
[Inertia::render('Matches/Show', { match, roleGroups, signupAllowed, viewerSlotId? })]


Authenticated signup write:
        │
        ▼
[POST /matches/{match}/signups   {game_role_id}]
        │
        ▼
[MatchSignupController::store]
        │   ├─ FormRequest validation
        │   └─ MatchSignupService->signup($match, $user, $gameRole)
        │            │
        │            └─ DB::transaction:
        │                  ├─ $locked = Match::lockForUpdate()->find($matchId);
        │                  ├─ assertStatusIs($locked, 'open');
        │                  ├─ assertTagAccessAllowed($user, $locked);
        │                  ├─ assertUserNotAlreadySignedUp($user, $locked);
        │                  ├─ $confirmed = MatchSlot::where(...)
        │                  │       ->whereNotNull('occupant_user_id')->count();
        │                  ├─ if ($confirmed >= $capacity) throw CapacityExceededException;
        │                  ├─ $emptySlot = MatchSlot::where(...)
        │                  │       ->whereNull('occupant_user_id')->orderBy('slot_index')->first();
        │                  ├─ $emptySlot->update(['occupant_user_id' => $user->id, 'confirmed_at' => now()]);
        │                  └─ return $emptySlot;
        │
        ▼
[Redirect back with flash → Vue re-renders with updated slot grid]


Admin result entry:
        │
        ▼
[GET /admin/matches/{record}/edit → ResultRelationManager]
        │
        ▼
[MatchResultService->upsert($match, $data, $causer)] ──► writes activity_log
```

### Recommended Project Structure — new files in Phase 4

```
apps/web/
├── app/
│   ├── Models/
│   │   ├── Match.php                              (NEW — HasTranslations[title, description], LogsActivity)
│   │   ├── MatchSlot.php                          (NEW — LogsActivity)
│   │   ├── MatchAccessRule.php                    (NEW — LogsActivity)
│   │   ├── MatchResult.php                        (NEW — LogsActivity)
│   │   ├── MatchMvp.php                           (NEW — LogsActivity)
│   │   └── Event.php                              (NEW — polymorphic eventable; LogsActivity)
│   ├── Observers/
│   │   └── MatchObserver.php                      (NEW — saved() listener for Event sync; deleted() cleans Event)
│   ├── Services/
│   │   ├── MatchSlotMaterialiserService.php       (NEW — given Match + GameMatchType, writes N MatchSlot rows)
│   │   ├── MatchSignupService.php                 (NEW — D-010 row-locked transaction)
│   │   ├── MatchStatusService.php                 (NEW — draft→open→locked→played→cancelled transitions)
│   │   └── MatchResultService.php                 (NEW — upsert result + MVPs in single transaction)
│   ├── Exceptions/
│   │   ├── CapacityExceededException.php          (NEW — caught by controller → 422)
│   │   ├── TagRestrictedException.php             (NEW — caught by controller → 422)
│   │   ├── AlreadySignedUpException.php           (NEW — idempotency)
│   │   └── MatchNotOpenException.php              (NEW — wrong status)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── MatchCalendarController.php        (NEW — GET /matches)
│   │   │   ├── MatchShowController.php            (NEW — GET /matches/{match})
│   │   │   └── Matches/
│   │   │       ├── MatchSignupController.php      (NEW — POST/DELETE /matches/{match}/signups)
│   │   │       └── MatchSignupRequest.php         (NEW — FormRequest)
│   │   └── Requests/                              (existing dir)
│   ├── Data/
│   │   ├── MatchData.php                          (NEW — #[TypeScript])
│   │   ├── MatchSlotData.php                      (NEW)
│   │   ├── MatchAccessRuleData.php                (NEW)
│   │   ├── MatchResultData.php                    (NEW)
│   │   ├── MatchMvpData.php                       (NEW)
│   │   ├── EventData.php                          (NEW)
│   │   ├── PublicMatchOccupantData.php            (NEW — privacy-shaped slot occupant)
│   │   └── PublicMatchData.php                    (NEW — privacy-shaped match for /matches/{id})
│   └── Filament/Resources/
│       ├── MatchResource.php                      (NEW)
│       ├── MatchResource/
│       │   ├── Pages/
│       │   │   ├── ListMatches.php
│       │   │   ├── CreateMatch.php                 (uses CreateRecord\Concerns\HasWizard)
│       │   │   ├── ViewMatch.php
│       │   │   └── EditMatch.php
│       │   └── RelationManagers/
│       │       ├── SlotsRelationManager.php        (NEW — read-mostly; admin override allowed)
│       │       ├── AccessRulesRelationManager.php  (NEW — tag allowlist editor)
│       │       ├── ResultRelationManager.php       (NEW — 1:1 result form)
│       │       └── MvpsRelationManager.php         (NEW — list MVPs; tied to result)
│       └── EventResource.php                       (NEW — read-only; surfaced for Phase 7 calendar review)
├── database/
│   ├── migrations/
│   │   ├── 2026_05_14_100000_create_matches_table.php                (NEW)
│   │   ├── 2026_05_14_100100_create_match_slots_table.php            (NEW)
│   │   ├── 2026_05_14_100200_create_match_access_rules_table.php     (NEW)
│   │   ├── 2026_05_14_100300_create_match_results_table.php          (NEW)
│   │   ├── 2026_05_14_100400_create_match_mvps_table.php             (NEW)
│   │   └── 2026_05_14_100500_create_events_table.php                 (NEW — polymorphic)
│   └── factories/
│       ├── MatchFactory.php
│       ├── MatchSlotFactory.php
│       ├── MatchAccessRuleFactory.php
│       ├── MatchResultFactory.php
│       └── MatchMvpFactory.php
├── lang/en/
│   ├── matches.php                                (NEW — public Vue page strings + signup error keys)
│   └── admin.php                                  (MODIFY — append 'match', 'match_slot', 'match_access_rule',
│                                                                 'match_result', 'match_mvp', 'event' key groups)
├── resources/js/
│   ├── pages/Matches/
│   │   ├── Index.vue                              (NEW — calendar/list with date+tag+status filters)
│   │   └── Show.vue                               (NEW — role-grouped slot grid + signup buttons)
│   ├── components/matches/
│   │   ├── MatchCard.vue                          (NEW — used in Index)
│   │   ├── RoleSlotGroup.vue                      (NEW — one role's capacity grid in Show)
│   │   ├── SlotOccupantPill.vue                   (NEW — confirmed signup display)
│   │   ├── SignupButton.vue                       (NEW — per-role signup CTA)
│   │   └── MatchStatusBadge.vue                   (NEW — wraps StatusBadge with match-specific variants)
│   └── components/events/
│       └── EventDateBadge.vue                     (NEW — calendar date pill, reused later for Tournament)
└── tests/
    ├── Feature/
    │   ├── Models/
    │   │   ├── MatchModelTest.php
    │   │   ├── MatchSlotModelTest.php
    │   │   ├── MatchAccessRuleModelTest.php
    │   │   ├── MatchResultModelTest.php
    │   │   ├── MatchMvpModelTest.php
    │   │   └── EventModelTest.php
    │   ├── Services/
    │   │   ├── MatchSlotMaterialiserServiceTest.php
    │   │   ├── MatchSignupServiceTest.php             (capacity, tag, status, idempotency)
    │   │   ├── MatchSignupConcurrencyTest.php          (parallel processes — see Pattern 2 / Pitfall 4)
    │   │   ├── MatchStatusServiceTest.php
    │   │   └── MatchResultServiceTest.php
    │   ├── Matches/
    │   │   ├── MatchCalendarPageTest.php             (GET /matches public)
    │   │   ├── MatchShowPageTest.php                  (GET /matches/{id} public; privacy strip)
    │   │   ├── MatchSignupControllerTest.php          (POST happy path + 422 on capacity)
    │   │   └── MatchSignupTagRestrictedTest.php       (SC-5 allowlist enforcement)
    │   ├── Admin/
    │   │   ├── MatchResourcePresentTest.php           (Filament resource + 4 RelationManagers reachable)
    │   │   ├── MatchResourceCreateWizardTest.php      (Wizard steps + materialisation)
    │   │   └── MatchAuditLogTest.php
    │   ├── Observers/
    │   │   └── MatchEventSyncTest.php                  (Pattern 8 — is_public flip creates/deletes Event)
    │   └── Database/
    │       └── (no seeder for matches — they are user-generated; factory used in tests)
    └── Unit/
        └── Data/
            ├── MatchDataTest.php
            ├── PublicMatchDataTest.php                 (privacy-strip unit tests)
            └── EventDataTest.php
```

---

### Pattern 1: Schema design (six tables)

**Source:** `.docs/05-database-schema.md` § "matches" + CONTEXT.md § Specific Ideas + Phase 2/3 migration idioms (timestampTz, gen_random_uuid, CHECK constraints).

#### Table: `matches`

```php
Schema::create('matches', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('game_match_type_id');
    $table->jsonb('title');                         // translatable
    $table->jsonb('description')->nullable();        // translatable
    $table->timestampTz('scheduled_at');
    $table->uuid('organiser_user_id');              // creator/officer; nullable iff admin-created
    $table->uuid('host_clan_id')->nullable();       // optional — the clan organising the match
    $table->text('server_address')->nullable();      // freeform; Phase 8 will FK to match_servers
    $table->text('status')->default('draft');        // draft|open|locked|played|cancelled
    $table->boolean('is_public')->default(true);
    $table->timestamps();

    $table->foreign('game_match_type_id')->references('id')->on('game_match_types')->restrictOnDelete();
    $table->foreign('organiser_user_id')->references('id')->on('users')->restrictOnDelete();
    $table->foreign('host_clan_id')->references('id')->on('clans')->nullOnDelete();

    $table->index('scheduled_at');                   // calendar paginate
    $table->index(['status', 'scheduled_at']);       // common filter
    $table->index('is_public');
});

DB::statement('ALTER TABLE matches ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('draft','open','locked','played','cancelled'));");
DB::statement("ALTER TABLE matches ALTER COLUMN scheduled_at TYPE timestamptz USING scheduled_at AT TIME ZONE 'UTC';");
DB::statement("ALTER TABLE matches ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
DB::statement("ALTER TABLE matches ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
```

**Why `restrictOnDelete` on `game_match_type_id`:** deleting a GameMatchType that has historical matches would orphan them. Admin must inactivate (`is_active=false`) rather than delete. [VERIFIED: Phase 3 RESEARCH § GameMatchType cascade]

#### Table: `match_slots`

```php
Schema::create('match_slots', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('match_id');
    $table->uuid('game_role_id');                    // snapshot — references game_roles, NOT game_match_type_role_limits
    $table->integer('slot_index');                    // 0..capacity-1 within (match_id, game_role_id)
    $table->uuid('occupant_user_id')->nullable();     // FK users; NULL = empty
    $table->timestampTz('confirmed_at')->nullable();  // set on signup
    $table->integer('sort_order')->default(0);
    $table->timestamps();

    $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $table->foreign('game_role_id')->references('id')->on('game_roles')->restrictOnDelete();
    $table->foreign('occupant_user_id')->references('id')->on('users')->nullOnDelete();

    $table->unique(['match_id', 'game_role_id', 'slot_index'], 'match_slots_unique_slot');
    $table->index(['match_id', 'occupant_user_id']);
    // Partial unique: a user can occupy at most one slot per match.
});

DB::statement('ALTER TABLE match_slots ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement('CREATE UNIQUE INDEX match_slots_one_occupancy_per_user ON match_slots (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL;');
```

The partial unique `(match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL` enforces "one slot per user per match" — Phase 2 partial-index pattern (Pattern 1 from 02-RESEARCH.md) applied verbatim. **Cannot be done with `Schema::unique()`** — use `DB::statement('CREATE UNIQUE INDEX ... WHERE ...')`.

#### Table: `match_access_rules`

```php
Schema::create('match_access_rules', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('match_id');
    $table->uuid('clan_tag_id');                      // allowlist: clan must have ≥1 of these tags
    $table->timestamps();

    $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $table->foreign('clan_tag_id')->references('id')->on('clan_tags')->restrictOnDelete();
    $table->unique(['match_id', 'clan_tag_id'], 'match_access_rules_unique');
    $table->index('match_id');
});
```

**Decision:** allowlist-only for v1. **Empty rules = open to all clans.** Non-empty rules = signup-user's active clan must have ≥1 matching tag. See Pattern 5 for enforcement logic.

#### Table: `match_results`

```php
Schema::create('match_results', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('match_id')->unique();              // 1:1
    $table->uuid('winner_clan_id')->nullable();      // nullable for draws / non-clan-vs-clan
    $table->integer('allies_score')->nullable();
    $table->integer('axis_score')->nullable();
    $table->text('notes')->nullable();
    $table->uuid('recorded_by_user_id');
    $table->timestampTz('recorded_at');
    $table->timestamps();

    $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
    $table->foreign('winner_clan_id')->references('id')->on('clans')->nullOnDelete();
    $table->foreign('recorded_by_user_id')->references('id')->on('users')->restrictOnDelete();
});

DB::statement('ALTER TABLE match_results ADD CONSTRAINT match_results_scores_nonneg_check CHECK ((allies_score IS NULL OR allies_score >= 0) AND (axis_score IS NULL OR axis_score >= 0));');
```

`match_id` is `unique()` — Eloquent gives us 1:1 via `HasOne` on Match and `BelongsTo` on Result.

#### Table: `match_mvps`

```php
Schema::create('match_mvps', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('match_result_id');
    $table->uuid('player_id');                         // FK players (player view, not user)
    $table->text('category');                           // 'kills'|'defense'|'objective'|'mvp'
    $table->integer('value')->nullable();              // optional numeric score
    $table->timestamps();

    $table->foreign('match_result_id')->references('id')->on('match_results')->cascadeOnDelete();
    $table->foreign('player_id')->references('id')->on('players')->restrictOnDelete();
    $table->unique(['match_result_id', 'category', 'player_id'], 'match_mvps_unique');
});

DB::statement("ALTER TABLE match_mvps ADD CONSTRAINT match_mvps_category_check CHECK (category IN ('kills','defense','objective','mvp'));");
```

#### Table: `events` (polymorphic)

```php
Schema::create('events', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('eventable_type');                  // 'App\Models\Match', later 'App\Models\Tournament'
    $table->uuid('eventable_id');
    $table->timestampTz('starts_at');
    $table->timestampTz('ends_at')->nullable();
    $table->jsonb('title');                            // translatable; denormalised cache of Match.title
    $table->boolean('is_public')->default(true);
    $table->timestamps();

    $table->unique(['eventable_type', 'eventable_id'], 'events_one_per_owner');
    $table->index(['eventable_type', 'eventable_id'], 'events_morphable_index');
    $table->index('starts_at');
    $table->index(['is_public', 'starts_at']);
});
```

`events_one_per_owner` enforces "at most one Event per Match/Tournament" — the observer-managed sync invariant (Pattern 8). Adding the standard `morphable_index` for fast polymorphic lookups.

[ASSUMED] The `events.title` JSONB is a denormalised cache of `Match.title` updated by the observer. This is a deliberate denormalisation to keep `/events` queries from joining 2+ tables on a polymorphic relation. Risk: Match.title edits require observer-sync. Mitigation: `MatchObserver::saved()` upserts both.

---

### Pattern 2: D-010 row-locked capacity enforcement

**What:** A `MatchSignupService` method wraps the capacity check + slot claim in a `DB::transaction()` with `lockForUpdate()` on the parent Match row. Postgres translates this to `SELECT ... FOR UPDATE` which acquires a row-level exclusive lock that blocks any other transaction's `lockForUpdate()` (or `UPDATE`/`DELETE`) on the same row until commit/rollback. [VERIFIED: Context7 /websites/laravel queries pessimistic-locking]

**Why lock the Match parent, not the slot row:** Two simultaneous signups for the same role would each see the same available-slot list and race to UPDATE different rows. Locking the Match parent serialises all signups for that match — every signup transaction must acquire the Match lock first, so the COUNT(occupants) read inside the transaction is consistent with the slot UPDATE that follows. [VERIFIED: standard pessimistic locking idiom — Laravel docs `DB::transaction(function () { ... lockForUpdate()->find(1) ... })` example]

**Why not just `match_slots.lockForUpdate()` on the empty slot:** the lock acquires AFTER the row is identified — the SELECT-of-empty + UPDATE-to-claim is not atomic without parent lock or `INSERT ... ON CONFLICT` semantics. Parent-row lock is the simpler, canonical approach.

**Why not `SERIALIZABLE` isolation level globally:** Default Postgres isolation is `READ COMMITTED`. Switching globally to `SERIALIZABLE` would degrade unrelated queries. Per-transaction `SERIALIZABLE` with retry logic is an alternative but more complex than parent-row pessimistic lock. **Recommend pessimistic parent lock.**

```php
// Source: Context7 /websites/laravel queries (lockForUpdate inside DB::transaction)
//         + ClanInviteService.php (Phase 2 transactional precedent)
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\MatchNotOpenException;
use App\Exceptions\TagRestrictedException;
use App\Models\GameRole;
use App\Models\Match as MatchModel;
use App\Models\MatchSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MatchSignupService
{
    public function signup(MatchModel $match, User $user, GameRole $gameRole): MatchSlot
    {
        return DB::transaction(function () use ($match, $user, $gameRole): MatchSlot {
            // 1. Acquire row-level exclusive lock on the parent Match row.
            //    All other signup transactions for this match block here until commit.
            /** @var MatchModel $locked */
            $locked = MatchModel::lockForUpdate()->findOrFail($match->id);

            // 2. Status guard (D-010 + state machine — Pattern 4).
            if ($locked->status !== 'open') {
                throw new MatchNotOpenException(__('matches.signup.error.not_open'));
            }

            // 3. Tag access allowlist (Pattern 5; SC-5).
            if (! $this->tagAccessAllowed($user, $locked)) {
                throw new TagRestrictedException(__('matches.signup.error.tag_restricted'));
            }

            // 4. Idempotency / "one slot per user per match" check.
            //    Partial unique index match_slots_one_occupancy_per_user is the
            //    DB-layer defence; this app-layer check returns a friendly error.
            $existing = MatchSlot::where('match_id', $locked->id)
                ->where('occupant_user_id', $user->id)
                ->first();

            if ($existing !== null) {
                throw new AlreadySignedUpException(__('matches.signup.error.already_signed_up'));
            }

            // 5. Capacity check — count CONFIRMED slots for the role.
            //    Because of the Match-row lock above, this count is consistent
            //    with the next UPDATE within the transaction.
            $occupiedCount = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->whereNotNull('occupant_user_id')
                ->count();

            // The role's capacity is whatever we materialised — same value as
            // counting all slots (occupied + empty) for that role.
            $totalCapacity = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->count();

            if ($occupiedCount >= $totalCapacity) {
                throw new CapacityExceededException(__('matches.signup.error.capacity_full'));
            }

            // 6. Find the lowest-index empty slot for this role and claim it.
            /** @var MatchSlot $emptySlot */
            $emptySlot = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->whereNull('occupant_user_id')
                ->orderBy('slot_index')
                ->firstOrFail();

            $emptySlot->update([
                'occupant_user_id' => $user->id,
                'confirmed_at' => now(),
            ]);

            return $emptySlot;
        });
    }

    private function tagAccessAllowed(User $user, MatchModel $match): bool
    {
        // Empty access rules = open to all (Pattern 5 decision).
        if ($match->accessRules()->count() === 0) {
            return true;
        }

        // Look up viewer's active clan's tags. Phase 2 invariant: D-009 one active
        // membership per user. If user has no active membership, treat as unaffiliated
        // → blocked when rules exist (per allowlist semantics).
        $userClan = $user->activeClanMembership?->clan;
        if ($userClan === null) {
            return false;
        }

        $userTagIds = $userClan->tags()->pluck('clan_tags.id');
        $allowedTagIds = $match->accessRules()->pluck('clan_tag_id');

        return $userTagIds->intersect($allowedTagIds)->isNotEmpty();
    }
}
```

**Why `Match::lockForUpdate()->findOrFail($match->id)` (re-read after passing the model in):** the controller's `$match` was loaded outside the transaction without a lock. We must re-read INSIDE the transaction WITH the lock so the row state observed is the state we're acting on. Passing the already-loaded `$match` and calling `$match->refresh()` is NOT equivalent — `refresh()` doesn't acquire a lock.

**Testing strategy** — see Pitfall 4 below for the concurrent-test approach.

---

### Pattern 3: Slot materialisation snapshot at Match create

**What:** `MatchSlotMaterialiserService::materialise(Match $match): int` reads `$match->gameMatchType->roleLimits` and writes one MatchSlot row per `(game_role_id, slot_index)` pair, where `slot_index ∈ [0, capacity)`. Returns the count of slots written.

**Why snapshot, not lazy:** If we materialised slots only on first signup, an admin editing the GameMatchType's RoleLimit capacity between Match create and first signup would silently change the match's slot count — a "spooky action at a distance" violation. The match's slot grid is locked at create time. Admin changes to GameMatchType propagate only to FUTURE matches. [Assumption A1]

**Why store `game_role_id`, not `game_match_type_role_limit_id`:** A future admin who renames or deletes a RoleLimit row shouldn't break existing match slots. We snapshot the actual role reference and the capacity is implicit in the slot rows themselves.

```php
declare(strict_types=1);

namespace App\Services;

use App\Models\Match as MatchModel;
use App\Models\MatchSlot;
use Illuminate\Support\Facades\DB;

final class MatchSlotMaterialiserService
{
    /**
     * Snapshot the match's GameMatchType.roleLimits into match_slots rows.
     * Idempotent at match-create time only — calling on a Match that already has
     * slots throws (the unique constraint match_slots_unique_slot fires).
     */
    public function materialise(MatchModel $match): int
    {
        return DB::transaction(function () use ($match): int {
            $match->loadMissing('gameMatchType.roleLimits');
            $count = 0;

            foreach ($match->gameMatchType->roleLimits as $limit) {
                for ($i = 0; $i < $limit->capacity; $i++) {
                    MatchSlot::create([
                        'match_id' => $match->id,
                        'game_role_id' => $limit->game_role_id,
                        'slot_index' => $i,
                        'sort_order' => $limit->sort_order,
                    ]);
                    $count++;
                }
            }

            return $count;
        });
    }
}
```

**When called:** `CreateMatch::handleRecordCreation` calls this service immediately after `Match::create`. Both happen inside a single outer transaction wrapped by Filament's CreateRecord page lifecycle. If the materialiser throws, the Match row is also rolled back.

**Test surface:** `MatchSlotMaterialiserServiceTest` covers (a) Scrim 50v50 yields 50 slots across 15 roles; (b) Skirmish 6v6 yields 6 slots across 5 roles; (c) calling twice on same Match throws `Illuminate\Database\QueryException` (unique constraint); (d) a GameMatchType with empty roleLimits yields 0 slots (no error).

---

### Pattern 4: Match status state machine

**Status enum:** `draft` → `open` → `locked` → `played` → `cancelled`

**Transitions and authorisation:**

| From | To | Who | Trigger |
|------|----|----|---------|
| (initial) | `draft` | system | Match::create with status='draft' (Filament wizard step 3 review) |
| `draft` | `open` | organiser, admin | Filament action "Open signups" after slot materialisation; SC-1 says signups open automatically — so wizard sets status='open' on save, skipping `draft` for the wizard path |
| `open` | `locked` | organiser, admin | Filament action "Lock signups" — prevents further changes pre-match |
| `open`/`locked` | `played` | organiser, admin | Triggered by entering a MatchResult; MatchResultService sets status='played' atomically |
| `draft`/`open`/`locked` | `cancelled` | organiser, admin | Filament action "Cancel match" — cascades to MatchObserver which removes the Event row |
| `played` | (no further) | — | Terminal |
| `cancelled` | (no further) | — | Terminal |

**Service layer (`MatchStatusService`):**

```php
declare(strict_types=1);

namespace App\Services;

use App\Models\Match as MatchModel;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MatchStatusService
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        'open' => ['locked', 'played', 'cancelled'],
        'locked' => ['played', 'cancelled'],
        'played' => [],
        'cancelled' => [],
    ];

    public function transition(MatchModel $match, string $to, User $causer): void
    {
        $from = $match->status;
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new DomainException(__('matches.status.error.invalid_transition', [
                'from' => $from, 'to' => $to,
            ]));
        }

        DB::transaction(function () use ($match, $to, $causer): void {
            $match->update(['status' => $to]);
            activity()->causedBy($causer)->performedOn($match)
                ->withProperties(['from' => $match->getOriginal('status'), 'to' => $to])
                ->log("Match status transition");
        });
    }
}
```

The DB-layer `matches_status_check` CHECK constraint is defence-in-depth: even direct SQL writes can't land an invalid status string. But the service is the canonical write path for transitions.

**MatchResult entry as a transition trigger:** `MatchResultService::upsert($match, $data, $causer)` calls `MatchStatusService::transition($match, 'played', $causer)` atomically when a result is first recorded. Subsequent edits to the result do NOT re-transition.

---

### Pattern 5: Tag access rules (allowlist-only)

**Decision:** v1 ships **allowlist only**. Empty `match_access_rules` = open to all. Non-empty = signup-user's active clan must have ≥1 tag in the allowlist. **No deny rules.** **No "everyone except" semantics.**

**Rationale:** Allow + Deny adds order-of-evaluation complexity (Filament UI must surface "rules are evaluated in this order") and conflicts with the simple Phase 2 ClanTag pivot model. If a match needs "everyone except clan-X-tag", admin instead lists all OTHER tags positively. Allow-only is the canonical league-management model.

**Schema** (already shown in Pattern 1):

```sql
match_access_rules(id uuid pk, match_id uuid fk, clan_tag_id uuid fk, UNIQUE(match_id, clan_tag_id))
```

**Enforcement** — see Pattern 2's `MatchSignupService::tagAccessAllowed()`:

```php
public function tagAccessAllowed(User $user, Match $match): bool
{
    // Empty rules = open
    if ($match->accessRules()->count() === 0) return true;

    // User must have an active clan
    $userClan = $user->activeClanMembership?->clan;
    if ($userClan === null) return false;

    // Intersection of user's clan's tags with the match's allowlist must be non-empty
    return $userClan->tags()->pluck('clan_tags.id')
        ->intersect($match->accessRules()->pluck('clan_tag_id'))
        ->isNotEmpty();
}
```

**Filament editor** — `AccessRulesRelationManager` is a simple `Select::make('clan_tag_id')->relationship('clanTag', 'slug')->multiple()` style; CreateAction inserts rows, DeleteAction removes them. Single-tag-per-row to keep the migration / FKs trivial.

**Test surface:**
- `MatchSignupTagRestrictedTest::it allows signup when user clan has an allowed tag`
- `MatchSignupTagRestrictedTest::it blocks signup when user clan has no allowed tag`
- `MatchSignupTagRestrictedTest::it allows signup when match has no rules` (empty=open)
- `MatchSignupTagRestrictedTest::it blocks signup when user has no active clan and rules exist`

---

### Pattern 6: Filament MatchResource with HasWizard CreateMatch

**Source:** [VERIFIED: Context7 /websites/filamentphp_3_x panels/resources/creating-records] — `CreateRecord\Concerns\HasWizard` trait. The wizard renders Steps 1..N each with their own schema, and the Create button only fires after the final Next-then-Create.

**Wizard steps:**

| Step | Schema | Live preview |
|------|--------|--------------|
| 1. Type | `game_match_type_id` (required Select with `->live()`) | Below the Select, render a read-only display of the GameMatchType's roleLimits (e.g., "Scrim 50v50 → 15 roles, 50 slots") so the admin sees what they're materialising |
| 2. Schedule | `scheduled_at` (DateTimePicker), `organiser_user_id` (default = auth user), `host_clan_id` (Select), `server_address` (TextInput, nullable), `is_public` (Toggle), `match_access_rules` (Repeater with clan_tag_id Select, multiple rows) | — |
| 3. Review | `title` (KeyValue JSONB), `description` (KeyValue JSONB nullable), final Create button | Read-only summary: "You are about to create a Scrim 50v50 scheduled for {time}, with {N} access rules" |

```php
// Source: Context7 /websites/filamentphp_3_x panels/resources/creating-records
declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Filament\Resources\MatchResource;
use App\Services\MatchSlotMaterialiserService;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateMatch extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = MatchResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make(__('admin.match.wizard.step_type'))
                ->description(__('admin.match.wizard.step_type_desc'))
                ->schema([
                    Forms\Components\Select::make('game_match_type_id')
                        ->label(__('admin.match.fields.game_match_type'))
                        ->relationship('gameMatchType', 'key')
                        ->required()
                        ->live()
                        ->searchable(),
                    // Live preview component (Placeholder) showing role count.
                ]),
            Step::make(__('admin.match.wizard.step_schedule'))
                ->description(__('admin.match.wizard.step_schedule_desc'))
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')->required()->timezone('UTC'),
                    Forms\Components\Select::make('organiser_user_id')
                        ->label(__('admin.match.fields.organiser'))
                        ->relationship('organiser', 'username')
                        ->required(),
                    Forms\Components\Select::make('host_clan_id')
                        ->relationship('hostClan', 'slug'),
                    Forms\Components\TextInput::make('server_address'),
                    Forms\Components\Toggle::make('is_public')->default(true),
                    Forms\Components\Repeater::make('match_access_rules')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('clan_tag_id')
                                ->relationship('clanTag', 'slug')
                                ->required(),
                        ]),
                ]),
            Step::make(__('admin.match.wizard.step_review'))
                ->description(__('admin.match.wizard.step_review_desc'))
                ->schema([
                    Forms\Components\KeyValue::make('title')
                        ->default(['en' => ''])
                        ->required(),
                    Forms\Components\KeyValue::make('description')
                        ->default(['en' => ''])
                        ->nullable(),
                ]),
        ];
    }

    /**
     * Wrap Match::create in a transaction that also materialises slots.
     * Pattern 3 — snapshot at create time.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = 'open';  // SC-1: signups open automatically
            $match = static::getModel()::create($data);

            app(MatchSlotMaterialiserService::class)->materialise($match);

            return $match;
        });
    }
}
```

**RelationManagers attached to MatchResource:**
- `SlotsRelationManager` — read-mostly; admin override edit available (clear occupant, swap users). Default sort by `sort_order` then `slot_index`.
- `AccessRulesRelationManager` — Pattern 5 editor; multi-tag allowlist.
- `ResultRelationManager` — 1:1 (read existing or create); winner_clan_id + scores + notes; calls `MatchResultService::upsert` on save (so the status flip to `played` is atomic with the result write).
- `MvpsRelationManager` — nested under ResultRelationManager? **No** — Filament v3 doesn't nest. Attach as a sibling RelationManager on MatchResource that scopes to the Match's result (via `getEloquentQuery()` override returning `MatchMvp::whereHas('result', fn ($q) => $q->where('match_id', $this->ownerRecord->id))`).

[ASSUMED] The MvpsRelationManager scoping via `getEloquentQuery()` works for Filament v3; verify during plan execution. The fallback is a standalone `MatchMvpResource` (no RelationManager) at `/admin/match-mvps` filtered manually.

---

### Pattern 7: Public Vue pages with privacy-gated slot occupants

**Source:** Phase 2 `Clans/Show.vue` + `ClanShowController.php` + `PlayerPrivacyGate.php`

**`/matches` calendar (Matches/Index.vue):**
- Reuses `PublicLayout` + design tokens from Phase 1/2.
- Date-grouped: group matches by ISO week, render headers like "Week of Mon 18 May 2026".
- Filters: `?date_from=&date_to=&status=&tag=`. Implemented via `router.get('/matches', { ... }, { preserveScroll: true })`.
- Each match row uses `MatchCard.vue` (compose date + GameMatchType label + host clan tag + "{n}/{cap} signed up" summary + status badge).

**`/matches/{match}` detail (Matches/Show.vue):**
- Hero: title (translatable) + scheduled_at + status badge.
- Role-grouped slot grid: one section per `game_role_id` with N slot pills, occupied pills show occupant via `SlotOccupantPill.vue`, empty pills show "Open" with a `SignupButton.vue` if `signupAllowed`.
- Privacy: `SlotOccupantPill` renders only what the DTO carries. `PublicMatchOccupantData` is the privacy-stripped DTO (see below) — a withheld player name renders as "Anonymous" + clan tag.

**Privacy-aware DTO (`PublicMatchOccupantData`):**

```php
declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Privacy-shaped occupant DTO for /matches/{id} slot grid.
 * Built by MatchShowController after applying PlayerPrivacyGate->passesTier
 * and allowsSection($player, $viewer, 'show_match_history') per player.
 * Withheld occupants render as "Anonymous" + clan tag.
 */
#[TypeScript]
final class PublicMatchOccupantData extends Data
{
    public function __construct(
        public string $slotId,
        public string $gameRoleId,
        public int $slotIndex,
        public ?string $displayName,   // null when privacy hides; renders as "Anonymous"
        public ?string $playerSlug,    // null when privacy hides; pill becomes non-clickable
        public ?string $clanTag,       // shown even when name is hidden (clan tag is public per D-008)
        public ?string $clanSlug,
        public bool $isViewer,         // true when viewer === occupant; UI marks "(you)"
    ) {}
}
```

**Controller pseudocode:**

```php
class MatchShowController
{
    public function __invoke(Match $match, PlayerPrivacyGate $gate, Request $req): Response
    {
        $match->load([
            'gameMatchType',
            'gameMatchType.roleLimits',
            'slots.occupantUser.player.privacy',
            'slots.occupantUser.activeClanMembership.clan',
            'accessRules.clanTag',
            'result.mvps.player',
        ]);

        $viewer = $req->user();

        $roleGroups = $match->slots->groupBy('game_role_id')->map(function ($slots) use ($gate, $viewer) {
            $role = $slots->first()->role;  // BelongsTo GameRole
            $occupantDtos = $slots->map(function ($slot) use ($gate, $viewer) {
                if ($slot->occupant_user_id === null) {
                    return PublicMatchOccupantData::empty($slot);  // open slot DTO
                }

                $player = $slot->occupantUser->player;
                $canSee = $gate->passesTier($player, $viewer)
                       && $gate->allowsSection($player, $viewer, 'show_match_history');

                return new PublicMatchOccupantData(
                    slotId: $slot->id,
                    gameRoleId: $slot->game_role_id,
                    slotIndex: $slot->slot_index,
                    displayName: $canSee ? $player->display_name : null,
                    playerSlug: $canSee ? $player->slug : null,
                    clanTag: $slot->occupantUser->activeClanMembership?->clan?->tag,
                    clanSlug: $slot->occupantUser->activeClanMembership?->clan?->slug,
                    isViewer: $viewer?->id === $slot->occupant_user_id,
                );
            });

            return ['role' => GameRoleData::fromModel($role), 'slots' => $occupantDtos->values()->all()];
        });

        return Inertia::render('Matches/Show', [
            'match' => MatchData::fromModel($match),
            'roleGroups' => $roleGroups->values()->all(),
            'signupAllowed' => $this->computeSignupAllowed($match, $viewer),
            'viewerSlotId' => $this->findViewerSlot($match, $viewer)?->id,
        ]);
    }
}
```

**Key insight (inherited from Phase 2 Pitfall 2):** Privacy logic is server-side ONLY. The Vue template renders `displayName` if present, else "Anonymous" — no `v-if="player.privacy.show_match_history"` in templates.

**Test surface:**
- `MatchShowPageTest::it renders public match without auth`
- `MatchShowPageTest::it strips occupant display name when privacy denies show_match_history`
- `MatchShowPageTest::it shows clan tag even when player name is hidden` (D-008 — clan tags are always public)
- `MatchSignupControllerTest::it returns 401 when guest tries to signup`
- `MatchSignupControllerTest::it returns 422 with capacity_full message when capacity exceeded`

---

### Pattern 8: Polymorphic Event sync via MatchObserver

**What:** A model observer listens to Match `saved` and `deleted` events and keeps the `events` row coherent with `matches.is_public` and `matches.status`.

```php
declare(strict_types=1);

namespace App\Observers;

use App\Models\Event;
use App\Models\Match as MatchModel;

class MatchObserver
{
    public function saved(MatchModel $match): void
    {
        $shouldHaveEvent = $match->is_public && $match->status !== 'cancelled';

        if ($shouldHaveEvent) {
            Event::updateOrCreate(
                ['eventable_type' => MatchModel::class, 'eventable_id' => $match->id],
                [
                    'starts_at' => $match->scheduled_at,
                    'ends_at' => null,  // matches don't carry end time in P4
                    'title' => $match->getTranslations('title'),
                    'is_public' => $match->is_public,
                ],
            );
        } else {
            Event::where('eventable_type', MatchModel::class)
                ->where('eventable_id', $match->id)
                ->delete();
        }
    }

    public function deleted(MatchModel $match): void
    {
        // Cascade delete on matches.id → events handled here too (no FK on polymorphic)
        Event::where('eventable_type', MatchModel::class)
            ->where('eventable_id', $match->id)
            ->delete();
    }
}
```

**Registered in:** `app/Providers/AppServiceProvider::boot()` via `Match::observe(MatchObserver::class);`

**Phase 6 ready:** When Tournament arrives, add a `TournamentObserver` with the same shape but `eventable_type = Tournament::class`. The `events` table needs no schema change. Calendar query (`Event::where('is_public', true)->orderBy('starts_at')->get()`) already returns both.

**Test surface (`MatchEventSyncTest`):**
- `it creates an Event when a public match is saved`
- `it deletes the Event when the match is set to is_public=false`
- `it deletes the Event when the match is cancelled`
- `it updates the Event title and starts_at when the match is edited`
- `it deletes the Event when the match is deleted`

---

### Pattern 9: spatie/laravel-data DTOs + TS regen (Phase 1/2/3 pattern)

**Phase 4 adds these `#[TypeScript]` DTOs:**
- `MatchData` — full match (id, title, description, scheduledAt, status, isPublic, gameMatchTypeId, organiserUserId, hostClanId)
- `MatchSlotData` — admin-facing slot view (id, matchId, gameRoleId, slotIndex, occupantUserId, confirmedAt)
- `MatchAccessRuleData` — id, matchId, clanTagId, clanTag (nested)
- `MatchResultData` — id, matchId, winnerClanId, alliesScore, axisScore, notes, recordedAt
- `MatchMvpData` — id, matchResultId, playerId, category, value
- `EventData` — id, eventableType, eventableId, startsAt, endsAt, title, isPublic
- `PublicMatchOccupantData` — see Pattern 7
- `PublicMatchData` — privacy-shaped match for `/matches/{id}` (excludes admin-only fields)

**Pattern (verified from Phase 3 `GameMatchTypeData`):**

```php
#[TypeScript]
final class MatchData extends Data
{
    /**
     * @param array<string, string>|null $title
     * @param array<string, string>|null $description
     */
    public function __construct(
        public string $id,
        public string $game_match_type_id,
        public ?array $title,
        public ?array $description,
        public string $scheduled_at,
        public string $status,
        public bool $is_public,
        public string $organiser_user_id,
        public ?string $host_clan_id,
    ) {}

    public static function fromModel(Match $match): self
    {
        return new self(
            id: $match->id,
            game_match_type_id: $match->game_match_type_id,
            title: $match->getTranslations('title') ?: null,
            description: $match->getTranslations('description') ?: null,
            scheduled_at: $match->scheduled_at->toIso8601String(),
            status: $match->status,
            is_public: $match->is_public,
            organiser_user_id: $match->organiser_user_id,
            host_clan_id: $match->host_clan_id,
        );
    }
}
```

**TS regen:** `make artisan ARGS="typescript:generate"` after DTOs land. shared-types sync via the bind mount established in Phase 1 (state diary 01-15).

---

### Anti-Patterns to Avoid

- **Locking the match_slots row instead of the parent Match.** The lock acquires after row identification — race window exists between identifying an empty slot and updating it. Lock the parent Match.
- **Using `Match` as the model name without phpdoc warning.** Class-name `Match` is legal in PHP 8 but visually confusing in code reviews where `match($x) { ... }` expressions sit nearby. Either name the class `Match` with a header comment AND import via FQN at call sites, or rename to `MatchEvent`/`GameMatch`. **Recommend `Match` with header comment** because Eloquent/Filament/Inertia all infer the table name `matches` from `Match` naturally.
- **Snapshotting slot count by storing `capacity` on `match_slots`.** Don't add a `capacity` column to MatchSlot — the row count per (match_id, game_role_id) IS the capacity. Adding a denormalised column creates two sources of truth.
- **Putting `lockForUpdate()` OUTSIDE the transaction.** Postgres releases row locks at transaction boundaries. A bare `Match::lockForUpdate()->find()` without `DB::transaction()` wrapper is effectively a no-op.
- **Privacy logic in Vue `Matches/Show.vue`.** Phase 2 Pitfall 2 — gate is server-side; Vue renders what arrives.
- **Inserting MatchSlot rows in the wizard form repeater.** Don't use Filament Repeater for slots — they are derived from GameMatchType.roleLimits and materialised by `MatchSlotMaterialiserService`. The wizard step is just GameMatchType pick; slots appear afterward.
- **Forgetting `cascadeOnDelete` on match_slots, match_access_rules, match_results, match_mvps.** Deleting a Match should remove its slot grid; otherwise orphans accumulate.
- **Setting `cascadeOnDelete` on the slots → game_roles FK.** Use `restrictOnDelete` — deleting a GameRole that has historical match slots should be blocked. The cascade is from MATCH → slot, not from GAME_ROLE → slot.
- **Trying to enforce "slot occupant must be a member of an allowed clan" via a single SQL CHECK.** This is a cross-table invariant; enforce in `MatchSignupService::tagAccessAllowed()` only. (Defence-in-depth analog of Phase 3's cross-game CHECK.)
- **Storing match dates as `timestamp` not `timestamptz`.** Phase 1/2/3 use `timestamptz` everywhere; matches must follow the same idiom for Railway prod (UTC server) vs local dev (potential drift).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pessimistic row lock | Custom SQL `SELECT ... FOR UPDATE` strings | Eloquent `Match::lockForUpdate()->find()` inside `DB::transaction()` | Driver-agnostic; reuses Laravel connection pool; correctly scopes the lock to transaction | [VERIFIED: Context7 /websites/laravel queries] |
| Polymorphic relation | Manual `eventable_type` switch logic | `MorphTo` + `morphMany` Eloquent relations | Type-safe; works with eager-loading; standard `morphable_index` covers fast lookup | [VERIFIED: Context7 /websites/laravel eloquent-relationships] |
| Multi-step admin form | Custom wizard JavaScript | Filament `CreateRecord\Concerns\HasWizard` trait | Built-in step navigation, validation, "back" handling, accessible | [VERIFIED: Context7 /websites/filamentphp_3_x panels/resources/creating-records] |
| Match status enum | PHP `enum` class | `text` column with DB CHECK constraint | Phase 1/2/3 precedent (matches ENUM-via-CHECK). PHP enum forces a schema migration when adding a state |
| Slot grid grouping | Manual SQL group + Vue assembly | Eloquent `groupBy('game_role_id')` server-side, push grouped DTOs to Vue | One round trip; type-safe DTOs |
| Calendar date grouping | Server-side date math | Vue `computed()` groupBy on `scheduledAt` ISO week | Public page is small enough; client grouping is responsive to user timezone (future-friendly) |
| MVP categories | Free-text input | Fixed enum + DB CHECK (`'kills','defense','objective','mvp'`) | Phase 8 RCON ingest will map known event categories — staying enum-typed avoids downstream regex matching |
| Audit log on result entry | Custom event listener | spatie/laravel-activitylog `LogsActivity` trait + `causedBy(auth()->user())` in service | Phase 1/2/3 pattern; per-resource Audit tab inherited |
| Empty-slot finder query | Random pick | `whereNull('occupant_user_id')->orderBy('slot_index')->first()` | Deterministic — always claims lowest slot_index; reproducible for tests; admin can re-sort by slot_index |
| Tag access rule check | Subquery in service | Eloquent collection `intersect()` on pluck of tag IDs | 1 query for user tags + 1 query for match rules; intersect in PHP; readable + testable |

**Key insight:** Phase 1/2/3 established every infrastructure primitive Phase 4 needs (DB::transaction, LogsActivity, HasTranslations, Filament Resource + RelationManager, spatie/laravel-data DTO, Inertia public page with privacy strip). Phase 4 is **applying** patterns, not installing infrastructure. The one new ingredient is **`lockForUpdate()` inside a transaction**, and that's a single Eloquent call.

---

## Common Pitfalls

### Pitfall 1: `lockForUpdate()` without `DB::transaction()` wrapper
**What goes wrong:** Calling `Match::lockForUpdate()->find($id)` outside a transaction acquires the lock for the duration of the SELECT statement only — the lock is released as soon as the connection auto-commits. The subsequent `UPDATE match_slots SET occupant_user_id = ...` is unprotected.
**Why it happens:** Eloquent's `lockForUpdate()` looks atomic; the requirement to wrap in `DB::transaction()` is doc-only.
**How to avoid:** Always wrap. Make `MatchSignupService::signup` the only call site for slot writes. Code review checklist item: "Is every `lockForUpdate` inside a `DB::transaction` callback?"
**Warning signs:** Concurrent signup test occasionally allows N+1 signups; Postgres `pg_locks` shows no lock held between SELECT and UPDATE.

### Pitfall 2: Eloquent `lockForUpdate()` does NOT acquire a Postgres advisory lock
**What goes wrong:** Postgres `SELECT ... FOR UPDATE` only locks rows the SELECT returns. If `Match::lockForUpdate()->where('id', $matchId)->get()` finds zero rows (bad ID), no lock is acquired and a parallel INSERT could succeed.
**Why it happens:** `lockForUpdate()` is a row-level lock on existing rows; it doesn't reserve a key that doesn't exist yet.
**How to avoid:** In our flow, the Match always exists when signup starts (route model binding). Use `findOrFail($id)` inside the transaction — the throw on missing row aborts the transaction cleanly.
**Warning signs:** Signup against a deleted match raises 404 from `findOrFail` — expected behaviour.

### Pitfall 3: Filament Wizard `handleRecordCreation` swallows transactions
**What goes wrong:** If `MatchSlotMaterialiserService::materialise` throws an exception inside `handleRecordCreation`, the Match record is already created — leaving an orphan Match with no slots.
**Why it happens:** Filament's CreateRecord page does NOT automatically wrap `handleRecordCreation` in a transaction.
**How to avoid:** Explicitly wrap both `Match::create` and `materialise()` in a single `DB::transaction()` inside `handleRecordCreation` (see Pattern 6 code). The Match insert and N slot inserts will roll back together on any failure.
**Warning signs:** Test "wizard rolls back on materialiser failure" finds Match rows with zero slots in the database.

### Pitfall 4: Testing concurrent signups in Pest
**What goes wrong:** Pest is single-threaded by default. A naive test that creates two `User` factories and calls `signup()` sequentially does NOT exercise the race condition.
**Why it happens:** Real contention requires multiple processes or threads holding open transactions.
**How to avoid:** Three options ranked by reliability:
  1. **`pcntl_fork` test** (Linux only — fine for our Pest container). Parent fork creates the match; two children each open a Postgres connection and attempt signup; parent waits and asserts (a) one child succeeded, (b) one child got `CapacityExceededException`, (c) DB has exactly `capacity` confirmed slots. [VERIFIED: pattern is established Postgres concurrency testing idiom]
  2. **Database-level proof** — write a Pest test that runs two parallel `DB::statement('BEGIN; SELECT ... FOR UPDATE; ...')` queries using `DB::connection('separate')` aliases to simulate two sessions. More finicky in CI.
  3. **Mock-out the transaction** and verify the SQL emitted (`SELECT ... FOR UPDATE` appears). Cheaper but doesn't prove correctness.
  **Recommend option 1** with a CI tag so it can be skipped on non-pcntl runners. Sample test file: `tests/Feature/Services/MatchSignupConcurrencyTest.php`.
**Warning signs:** No concurrency test at all → false confidence on D-010 compliance.

### Pitfall 5: `Match` class name and the `match` PHP keyword
**What goes wrong:** Code reviewers and IDEs sometimes mis-highlight or auto-complete `match` keyword expressions when they see `Match::find()` references. Static analysers occasionally flag false positives.
**Why it happens:** PHP 8 introduced `match` keyword; class names are case-sensitive (`Match` ≠ `match`) so it's technically legal but visually similar.
**How to avoid:**
  - Add a header phpdoc comment on `App\Models\Match` explaining the choice.
  - Always import via FQN at use sites that also contain `match($x)` expressions: `use App\Models\Match as MatchModel;`.
  - In service classes that read auth/result/etc., alias to `MatchModel` for legibility.
**Alternative:** Rename to `MatchEvent` or `GameMatch`. Trade-off: schema is `matches` table, model becomes `MatchEvent` which forces explicit `protected $table = 'matches';` and breaks the Eloquent convention. **Recommend `Match` + alias-on-import.**

### Pitfall 6: Polymorphic eager-load filtering
**What goes wrong:** Phase 7 will need `Event::where('is_public', true)->with('eventable')->get()` to surface both Match and Tournament events. The default eager load loads `eventable` without `is_active`/status filtering — a cancelled match could still appear if the observer missed deleting the Event.
**Why it happens:** `MorphTo` eager loading doesn't apply constraints by default.
**How to avoid:** Phase 4 doesn't surface this — the observer reliably deletes Events on cancel. Phase 7 (`Event::with(['eventable' => fn($morph) => $morph->constrain([Match::class => fn($q) => $q->where('status', '!=', 'cancelled')])])`) — note for Phase 7 RESEARCH.
**Warning signs:** None in Phase 4. Logged here for Phase 7 reuse.

### Pitfall 7: Match status `played` is auto-set by MatchResultService, NOT manually by admin
**What goes wrong:** Admin enters a result and separately tries to set status to "played" via the Filament edit form. Two writes; race for the audit log.
**Why it happens:** Status is exposed in the admin form; tempting to flip.
**How to avoid:** Make `status` a `disabled()` Filament field on Edit. Status transitions happen via dedicated Filament Actions ("Open signups", "Lock signups", "Cancel match") OR via service-side as a side-effect of result entry. See Pattern 4.
**Warning signs:** Audit log shows two consecutive status updates for the same Match within seconds.

### Pitfall 8: `scheduled_at` timezone — store UTC, render in user TZ
**What goes wrong:** Admin enters `19:00` thinking local time; we store `19:00 UTC` which displays as `21:00 CEST` to the admin's own browser later. Confusing.
**Why it happens:** Filament `DateTimePicker` default behaviour with `timestampTz` columns.
**How to avoid:** Set `->timezone('UTC')` on the DateTimePicker, and add a helper text "All match times are stored in UTC; your browser will display them in your local time on the public page." Public Vue pages render via `dayjs(scheduledAt).local().format(...)` or similar.
**Warning signs:** Admin reports their match was scheduled 2 hours later than they intended.

### Pitfall 9: Postgres timestamptz column with Eloquent `$dates` / casts
**What goes wrong:** Without an explicit cast, Eloquent treats `scheduled_at` as a string. `$match->scheduled_at->isFuture()` fails with "Method on string".
**Why it happens:** `timestampTz()` migration column type isn't auto-cast.
**How to avoid:** In `Match::casts()`: `'scheduled_at' => 'datetime', 'confirmed_at' => 'datetime'` on MatchSlot, `'recorded_at' => 'datetime'` on MatchResult.

### Pitfall 10: `match_access_rules` empty rules treated as "deny all" instead of "allow all"
**What goes wrong:** A subtle semantic flip: zero rules could mean "no clan may signup" rather than "every clan may signup".
**Why it happens:** Allowlist intuition cuts both ways.
**How to avoid:** Document explicitly in the service: "Empty rules = open (any clan may signup)." Test `MatchSignupTagRestrictedTest::it allows signup when match has no rules`. Also surface a banner in Filament: "No tag restrictions — match is open to all clans" when the AccessRulesRelationManager has zero rows.

### Pitfall 11: Filament RelationManager `getEloquentQuery()` override for MVP scoping
**What goes wrong:** Attempting to mount `MvpsRelationManager` directly on Match without going through MatchResult creates orphan MVPs (no result yet).
**Why it happens:** MVPs FK to `match_results.id`, not `matches.id`.
**How to avoid:** Use `getEloquentQuery()` to scope: `MatchMvp::whereHas('result', fn($q) => $q->where('match_id', $this->ownerRecord->id))`. Show a "Create result first" empty state when no result exists.
**Verify:** [ASSUMED] this works in Filament v3; confirm during plan 04-N execution. Fallback: ship `MvpsRelationManager` on a separate `MatchResultResource` (standalone) instead.

### Pitfall 12: `is_public` toggle on Match must trigger the observer
**What goes wrong:** Filament's `Toggle::make('is_public')` writes the column but the model's `saved` event fires the observer correctly — UNLESS the admin uses a bulk action that calls `Model::query()->update()` (mass-update bypasses model events).
**Why it happens:** Eloquent mass updates skip model events.
**How to avoid:** Filament's standard `EditAction::make()` uses `$model->save()` which fires observer. Don't add bulk publish actions; if needed, iterate models.
**Warning signs:** Some matches' `is_public` is true but no `events` row exists.

---

## Code Examples

### Model: `Match.php` (header comment included; see Pitfall 5)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\MatchObserver;
use Database\Factories\MatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Code Examples § Model: Match.
 *
 * NOTE: Class name conflicts visually (NOT syntactically) with PHP 8's `match` keyword.
 * The class is legal and Eloquent/Filament/Inertia infer the table name `matches` correctly.
 * At call sites where `match($x) { ... }` expressions appear nearby, alias on import:
 *   use App\Models\Match as MatchModel;
 *
 * D-010: capacity enforced via MatchSignupService row lock — never write to MatchSlot.occupant_user_id
 * outside that service.
 */
class Match extends Model
{
    /** @use HasFactory<MatchFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'game_match_type_id',
        'title',
        'description',
        'scheduled_at',
        'organiser_user_id',
        'host_clan_id',
        'server_address',
        'status',
        'is_public',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Match {$event}");
    }

    protected static function booted(): void
    {
        static::observe(MatchObserver::class);
    }

    /** @return BelongsTo<GameMatchType, $this> */
    public function gameMatchType(): BelongsTo
    {
        return $this->belongsTo(GameMatchType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_user_id');
    }

    /** @return BelongsTo<Clan, $this> */
    public function hostClan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'host_clan_id');
    }

    /** @return HasMany<MatchSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(MatchSlot::class)->orderBy('sort_order')->orderBy('slot_index');
    }

    /** @return HasMany<MatchAccessRule, $this> */
    public function accessRules(): HasMany
    {
        return $this->hasMany(MatchAccessRule::class);
    }

    /** @return HasOne<MatchResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class);
    }

    /** @return MorphOne<Event, $this> */
    public function event(): MorphOne
    {
        return $this->morphOne(Event::class, 'eventable');
    }
}
```

### Migration: `match_slots` (Pattern 1 + Pattern 2 supporting partial-unique)

(shown in Pattern 1 above)

### Service: `MatchSignupService` (Pattern 2)

(shown in Pattern 2 above)

### Filament wizard `CreateMatch` (Pattern 6)

(shown in Pattern 6 above)

### Vue: `Matches/Show.vue` skeleton

```vue
<!-- Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 7 -->
<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import RoleSlotGroup from '@/components/matches/RoleSlotGroup.vue';
import MatchStatusBadge from '@/components/matches/MatchStatusBadge.vue';
import { useT } from '@/composables/useT';
import { Head } from '@inertiajs/vue3';

const { t } = useT();

type MatchData = App.Data.MatchData;
type RoleGroup = { role: App.Data.GameRoleData; slots: App.Data.PublicMatchOccupantData[] };

const props = defineProps<{
    match: MatchData;
    roleGroups: RoleGroup[];
    signupAllowed: boolean;
    viewerSlotId: string | null;
}>();
</script>

<template>
    <Head :title="match.title?.en ?? t('matches.show.title_fallback')" />
    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-8">
            <header class="flex flex-col gap-2">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2]">
                    {{ match.title?.en ?? t('matches.show.title_fallback') }}
                </h1>
                <MatchStatusBadge :status="match.status" />
                <time class="text-base text-[var(--color-text-muted)]">{{ match.scheduled_at }}</time>
            </header>

            <RoleSlotGroup
                v-for="group in roleGroups"
                :key="group.role.id"
                :group="group"
                :match-id="match.id"
                :signup-allowed="signupAllowed"
                :viewer-slot-id="viewerSlotId"
            />
        </section>
    </PublicLayout>
</template>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Application-level capacity check without lock | `lockForUpdate()` inside `DB::transaction()` | Standard Laravel pattern (always was) | Required for D-010 |
| Slot capacity stored on match_slots row | Implicit via row count per (match_id, role_id) | This Phase | Avoids two sources of truth |
| Match status as PHP enum | Text column with DB CHECK | Phase 1/2/3 precedent | Easier to add states without migration |
| Polymorphic via separate match_calendar + tournament_calendar tables | Single `events` table with `eventable_type` + `eventable_id` | This Phase | Phase 6/7 reuse without schema change |
| Match dates stored as `timestamp` | `timestampTz` (UTC) | Phase 1 precedent | Railway prod compatibility |

**Deprecated/outdated:** None applicable — this is a new domain.

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Slot materialisation should snapshot at match create, decoupling from future GameMatchType edits | Pattern 3 | If user wants live-mirror semantics, materialiser becomes a re-materialise-on-RoleLimit-edit operation; existing signups would need to be preserved/migrated when capacity changes |
| A2 | Empty `match_access_rules` = open to all (not deny-all) | Pattern 5 | If semantics flip, every existing test in MatchSignupTagRestrictedTest must invert; admin UX banner copy changes |
| A3 | `events.title` is a denormalised JSONB cache of `Match.title`, kept fresh by `MatchObserver::saved()` | Pattern 1 + Pattern 8 | If user prefers join-on-render, drop the column; observer logic simplifies but Phase 7 calendar query needs JOIN per `eventable_type` |
| A4 | Model class name `App\Models\Match` despite visual similarity to PHP `match` keyword | Pitfall 5 + Code Examples | If user prefers `MatchEvent` / `GameMatch`, rename + add `protected $table = 'matches'` |
| A5 | One-active-signup-per-user-per-match enforced by partial unique index `match_slots_one_occupancy_per_user` | Pattern 1 (match_slots schema) | If user wants players to occupy multiple slots (eg. one match has both "anti-tank-driver" AND "anti-tank-shooter" for the same person), drop the partial unique. Recommend retain |
| A6 | `MvpsRelationManager` on MatchResource can scope via `getEloquentQuery()` to `MatchMvp::whereHas('result.match', ...)` | Pitfall 11 | If Filament v3 fails to scope cleanly, fallback is standalone `MatchMvpResource` on the sidebar |
| A7 | Concurrent signup test feasible via `pcntl_fork` inside Pest container | Pitfall 4 | If `pcntl` isn't loaded in PHP 8.4 container, fall back to two-connection alias approach. Verify in plan 04-01 Wave 0 |
| A8 | `pcntl` extension is available in the web container PHP 8.4 build | Pitfall 4 | Verify via `php -m \| grep pcntl` inside container during Wave 0; if missing, switch to two-connection DB::connection alias approach |
| A9 | Match `cancelled` deletes the Event row (rather than marking it cancelled) | Pattern 8 | If user wants cancelled matches to remain on calendar with "cancelled" annotation, observer keeps the row and writes `is_public=false`. Phase 7 calendar query filters |
| A10 | Filament wizard's `handleRecordCreation` correctly wraps both Match::create and materialise in single explicit DB::transaction | Pitfall 3 | If user prefers Filament's implicit transaction, verify v3 lifecycle; current Pattern 6 is defence-in-depth and works regardless |
| A11 | Postgres `lockForUpdate` on a row already locked by another transaction blocks (not throws) | Pattern 2 | [VERIFIED: Postgres docs — default behaviour is BLOCK. NOWAIT/SKIP LOCKED are opt-in modifiers]. Eloquent's `lockForUpdate()` is the BLOCK variant — exactly what we want |

---

## Open Questions

1. **Filament wizard live preview component for GameMatchType selection**
   - What we know: SC-1 says signups open automatically when admin picks a type. Wizard step 1 picks the type; step 3 reviews and creates.
   - What's unclear: Should step 1 show a live preview of the role limit matrix (e.g., "Scrim 50v50 → Commander×1 / Squad Leader×4 / Rifleman×16 / ...") so admin can verify before committing?
   - Recommendation: Yes. Use `Forms\Components\Placeholder::make('preview')->content(fn($get) => ...)`. Tradeoff: small UX wins; ~1 extra plan task.

2. **Officer-facing public Create Match form (`POST /matches`)**
   - What we know: SC-1 says "officer/leader" can create. Filament admin can; Phase 5 Discord bot will (planned). Public web form for officers is unclear.
   - What's unclear: Does Phase 4 ship `/my-clan/matches/create` page for clan leader/officer self-service, or defer to Phase 5/8?
   - Recommendation: **Defer.** Phase 4 ships admin-only Create via Filament. Officers request via Discord pings or admin invites. Phase 5 surfaces it via slash command. This keeps Phase 4 scope-minimal.

3. **Display of cancelled matches on `/matches` calendar**
   - What we know: Status `cancelled` → observer deletes Event row → match no longer shows in calendar.
   - What's unclear: Should cancelled matches appear with a strikethrough on `/matches` for a few days, or vanish immediately?
   - Recommendation: **Vanish immediately** for Phase 4 simplicity. Phase 7 CMS could add a "Past matches & cancellations" archive view.

4. **MVP categories — fixed enum or admin-configurable?**
   - What we know: `match_mvps.category` is a DB CHECK ENUM (`kills`, `defense`, `objective`, `mvp`).
   - What's unclear: Will Phase 8 RCON ingest emit different categories? Should categories be admin-editable per game?
   - Recommendation: **Fixed enum for Phase 4.** Revisit in Phase 8 RCON integration when actual CRCON event categories are known. If categories per-game are needed, introduce a `mvp_categories` table at that point — schema change is local.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Postgres 16 (FOR UPDATE row locking + partial unique index) | D-010 + match_slots_one_occupancy_per_user | ✓ (via Docker) | 16.x [VERIFIED: Phase 1] | — |
| `spatie/laravel-translatable` | Match title/description | ✓ (installed P2) | 6.14 [VERIFIED: composer.lock] | — |
| `spatie/laravel-activitylog` | LogsActivity on all 6 models | ✓ (installed P1) | 5.0 [VERIFIED: composer.lock] | — |
| `spatie/laravel-data` | All DTOs | ✓ (installed P1) | 4.22 [VERIFIED: composer.lock] | — |
| `filament/filament` | MatchResource + HasWizard | ✓ (installed P1) | 3.3.50 [VERIFIED: composer.lock] | — |
| `inertiajs/inertia-laravel` | Public pages | ✓ (installed P1) | 2.0 [VERIFIED: composer.lock] | — |
| `tightenco/ziggy` | Vue route helpers | ✓ (installed P1) | 2.6 [VERIFIED: composer.lock] | — |
| PHP `pcntl` extension | Concurrent signup test (Pitfall 4) | ❓ unverified | — | Fallback to multi-connection DB alias approach (see Pitfall 4) |
| Pest 4 | All tests | ✓ (installed P1) | [VERIFIED: Phase 1] | — |
| PHPStan level 8 + Pint | CI gates | ✓ (installed P1) | [VERIFIED: Phase 1] | — |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:**
- `pcntl` PHP extension — needed for the canonical concurrent signup test. Verify availability via `make shell -c "php -m | grep pcntl"` in Wave 0. If missing, switch to dual-connection approach (open two `DB::connection()` aliases pointing at the same Postgres, run `BEGIN; SELECT ... FOR UPDATE; ...` in each).

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 with `pest-plugin-laravel` |
| Config file | `apps/web/phpunit.xml` |
| Quick run command | `make pest ARGS="--filter=Match"` |
| Full suite command | `make pest` |

### Phase Requirements → Test Map

| Req / SC | Behavior | Test Type | Automated Command | File Exists? |
|----------|----------|-----------|-------------------|-------------|
| SC-1 | Filament wizard creates Match + materialises slots | Feature | `make pest ARGS="--filter=MatchResourceCreateWizardTest"` | ❌ Wave 0 |
| SC-1 | Materialiser produces 50 slots for Scrim 50v50 | Feature | `make pest ARGS="--filter=MatchSlotMaterialiserServiceTest"` | ❌ Wave 0 |
| SC-1 | Wizard rolls back Match + slots on materialiser failure | Feature | `make pest ARGS="--filter=MatchResourceCreateWizardTest::rolls_back"` | ❌ Wave 0 |
| **SC-2** | **Signup increments slot count under capacity** | Feature | `make pest ARGS="--filter=MatchSignupServiceTest::increments"` | ❌ Wave 0 |
| **SC-2** | **Signup raises CapacityExceededException when role full** | Feature | `make pest ARGS="--filter=MatchSignupServiceTest::capacity"` | ❌ Wave 0 |
| **SC-2** | **Concurrent signups serialise; exactly capacity confirmed** | Feature | `make pest ARGS="--filter=MatchSignupConcurrencyTest"` (pcntl-gated) | ❌ Wave 0 |
| SC-2 | Partial unique index blocks two slots for same user | Feature | `make pest ARGS="--filter=MatchSlotModelTest::one_per_user"` | ❌ Wave 0 |
| SC-3 | GET /matches returns 200 for guest; lists open matches | Feature | `make pest ARGS="--filter=MatchCalendarPageTest"` | ❌ Wave 0 |
| SC-3 | GET /matches/{id} returns 200 for guest; renders slot grid | Feature | `make pest ARGS="--filter=MatchShowPageTest"` | ❌ Wave 0 |
| SC-3 | /matches/{id} hides occupant name when show_match_history=false | Feature | `make pest ARGS="--filter=MatchShowPageTest::privacy"` | ❌ Wave 0 |
| SC-4 | MatchResultService writes result + flips status to played | Feature | `make pest ARGS="--filter=MatchResultServiceTest"` | ❌ Wave 0 |
| SC-4 | Result creation is audited (causer + properties) | Feature | `make pest ARGS="--filter=MatchAuditLogTest::result"` | ❌ Wave 0 |
| SC-4 | Filament ResultRelationManager reachable | Feature | `make pest ARGS="--filter=MatchResourcePresentTest::result_rm"` | ❌ Wave 0 |
| **SC-5** | **Signup blocked when user clan has no allowed tag** | Feature | `make pest ARGS="--filter=MatchSignupTagRestrictedTest::blocks"` | ❌ Wave 0 |
| **SC-5** | **Signup allowed when match has zero rules (open)** | Feature | `make pest ARGS="--filter=MatchSignupTagRestrictedTest::open"` | ❌ Wave 0 |
| **SC-5** | **Saving is_public=true creates Event row** | Feature | `make pest ARGS="--filter=MatchEventSyncTest::creates"` | ❌ Wave 0 |
| SC-5 | Saving is_public=false deletes Event row | Feature | `make pest ARGS="--filter=MatchEventSyncTest::deletes"` | ❌ Wave 0 |
| SC-5 | Cancelling match deletes Event row | Feature | `make pest ARGS="--filter=MatchEventSyncTest::cancels"` | ❌ Wave 0 |
| D-012 | MatchResource + EventResource reachable in admin | Feature | `make pest ARGS="--filter=MatchResourcePresentTest"` | ❌ Wave 0 |
| D-013 | No hardcoded strings in Matches/ Vue dir | Feature | `make pest ARGS="--filter=NoHardcodedStringsTest"` (auto-covers new dirs) | ✅ exists |
| Model invariants | All 6 model FKs, casts, relationships | Feature | `make pest ARGS="--filter=MatchModel\|MatchSlot\|MatchAccessRule\|MatchResult\|MatchMvp\|EventModel"` | ❌ Wave 0 |
| DTO shape | MatchData carries translatable JSONB | Unit | `make pest ARGS="--filter=MatchDataTest"` | ❌ Wave 0 |
| Privacy DTO | PublicMatchOccupantData strips name on hide | Unit | `make pest ARGS="--filter=PublicMatchDataTest"` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `make pest ARGS="--filter=Match"` (Match-specific tests only, ~10s)
- **Per wave merge:** `make pest` (full suite, ~30s after Phase 4 additions)
- **Phase gate:** Full suite green + `make pint ARGS="--test"` + `make phpstan` + `vue-tsc` + `pnpm --filter @trenchwars/shared-types run typecheck` before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Models/MatchModelTest.php`
- [ ] `tests/Feature/Models/MatchSlotModelTest.php`
- [ ] `tests/Feature/Models/MatchAccessRuleModelTest.php`
- [ ] `tests/Feature/Models/MatchResultModelTest.php`
- [ ] `tests/Feature/Models/MatchMvpModelTest.php`
- [ ] `tests/Feature/Models/EventModelTest.php`
- [ ] `tests/Feature/Services/MatchSlotMaterialiserServiceTest.php`
- [ ] `tests/Feature/Services/MatchSignupServiceTest.php`
- [ ] `tests/Feature/Services/MatchSignupConcurrencyTest.php` — `skip_if_no_pcntl()` guard
- [ ] `tests/Feature/Services/MatchStatusServiceTest.php`
- [ ] `tests/Feature/Services/MatchResultServiceTest.php`
- [ ] `tests/Feature/Matches/MatchCalendarPageTest.php`
- [ ] `tests/Feature/Matches/MatchShowPageTest.php`
- [ ] `tests/Feature/Matches/MatchSignupControllerTest.php`
- [ ] `tests/Feature/Matches/MatchSignupTagRestrictedTest.php`
- [ ] `tests/Feature/Admin/MatchResourcePresentTest.php`
- [ ] `tests/Feature/Admin/MatchResourceCreateWizardTest.php`
- [ ] `tests/Feature/Admin/MatchAuditLogTest.php`
- [ ] `tests/Feature/Observers/MatchEventSyncTest.php`
- [ ] `tests/Unit/Data/MatchDataTest.php`
- [ ] `tests/Unit/Data/PublicMatchDataTest.php`
- [ ] `tests/Unit/Data/EventDataTest.php`
- [ ] `database/factories/MatchFactory.php`
- [ ] `database/factories/MatchSlotFactory.php`
- [ ] `database/factories/MatchAccessRuleFactory.php`
- [ ] `database/factories/MatchResultFactory.php`
- [ ] `database/factories/MatchMvpFactory.php`
- [ ] `lang/en/matches.php` — new i18n namespace (not a test file; Wave 0 prerequisite)
- [ ] `lang/en/admin.php` — append `admin.match.*`, `admin.match_slot.*`, etc.

---

## Security Domain

`security_enforcement: true` (.planning/config.json), ASVS level 1.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Signup requires `auth` middleware; admin requires `admin-access` permission (Phase 1 wired) |
| V3 Session Management | inherited | Phase 1 SameSite=Lax + HttpOnly already set |
| V4 Access Control | **yes** | Match admin actions gate by permission (`match.create`, `match.result.write`, `match.status.transition`). Signup: user must be authenticated AND clan-tag-allowed (Pattern 5) |
| V5 Input Validation | **yes** | Match title/description: validated in FormRequest (max length, locale key shape). MatchSignupRequest: `game_role_id` must exist + match's MatchSlot::where matches |
| V6 Cryptography | no | No new encryption surfaces |

### Known Threat Patterns for This Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| **Capacity bypass via concurrent signups** | Tampering | **D-010 row-locked transaction (Pattern 2). Concurrency test (Pitfall 4)** |
| **Tag-access bypass** | Elevation of Privilege | `MatchSignupService::tagAccessAllowed()` server-side check; UI never trusted |
| IDOR on signup (signing up another user) | Tampering | `MatchSignupController` uses `auth()->user()`; never accepts `user_id` from request |
| IDOR on result write | Tampering | Filament ResultRelationManager gated by `match.result.write` permission |
| Privacy bypass via slot occupant DTO | Info Disclosure | `PublicMatchOccupantData` shaped server-side via `PlayerPrivacyGate::passesTier` + `allowsSection($p, $v, 'show_match_history')` |
| Mass-assignment on MatchSlot.occupant_user_id | Tampering | `$fillable` excludes `occupant_user_id` — only `MatchSignupService` writes it via explicit `update()` |
| XSS via match title/description (translatable JSONB) | Tampering | Inertia template auto-escapes; no `v-html` |
| Signup spoofing through forged tag | Tampering | Clan tags are FK-referenced; allowlist comparison uses IDs, never strings |
| **Slot snapshot drift** | Tampering | Snapshot at create (Pattern 3); GameMatchType edits don't retroactively affect open matches |
| Match status race (admin manually flips to "played" while result service is mid-transaction) | Tampering | `MatchResultService` calls `MatchStatusService::transition` atomically inside the result transaction |
| Cancelled match retains Event row | Info Disclosure | `MatchObserver::saved` deletes Event when status=cancelled OR is_public=false |
| Polymorphic eventable_type spoofing | Tampering | `eventable_type` is set by the observer only — never user input. Filament EventResource is read-only |

**Note on "user must have an active clan to sign up to a tag-restricted match":** If a user has no active ClanMembership, they have no tags, and the allowlist intersection is empty. They cannot sign up to restricted matches. This is correct behaviour. They CAN sign up to OPEN matches (zero rules). Document in `matches.signup.error.tag_restricted` key.

---

## Sources

### Primary (HIGH confidence)
- `apps/web/app/Models/Clan.php`, `User.php`, `Player.php`, `GameMatchTypeRoleLimit.php`, `GameMatchType.php`, `GameRole.php` — Phase 1/2/3 model patterns (verified codebase 2026-05-13)
- `apps/web/app/Services/ClanInviteService.php` — DB::transaction precedent (verified codebase 2026-05-13)
- `apps/web/app/Services/PlayerPrivacyGate.php` — privacy gate inherited verbatim (verified codebase 2026-05-13)
- `apps/web/app/Http/Controllers/ClanShowController.php` — privacy-stripped Inertia render pattern (verified codebase 2026-05-13)
- `apps/web/database/migrations/2026_05_12_100400_create_clan_memberships_table.php` — partial unique index precedent (verified codebase 2026-05-13)
- `apps/web/database/migrations/2026_05_13_100300_create_game_match_type_role_limits_table.php` — composite UNIQUE + CHECK + FK pattern (verified codebase 2026-05-13)
- `apps/web/app/Filament/Resources/GameMatchTypeResource.php` — Filament Tabs + KeyValue + disabledOn('edit') pattern (verified codebase 2026-05-13)
- `apps/web/app/Filament/Resources/ClanResource.php` — Filament Tabs Profile + RelationManagers pattern (verified codebase 2026-05-13)
- `apps/web/resources/js/pages/Clans/Show.vue` + `Index.vue` — public page pattern with privacy strip (verified codebase 2026-05-13)
- `apps/web/app/Data/GameMatchTypeData.php` — eager-load-aware DTO pattern (verified codebase 2026-05-13)
- Context7 `/websites/laravel` queries (pessimistic-locking) — `DB::transaction { lockForUpdate()->find($id) }` (fetched 2026-05-13)
- Context7 `/websites/laravel` eloquent-relationships (polymorphic MorphTo/MorphMany) (fetched 2026-05-13)
- Context7 `/websites/filamentphp_3_x` panels/resources/creating-records (HasWizard trait) (fetched 2026-05-13)
- `.planning/phases/03-games-match-types/03-RESEARCH.md` — translatable JSONB + Filament patterns (verified 2026-05-13)
- `.planning/phases/02-clans-tags/02-RESEARCH.md` — partial unique index + privacy gate patterns (verified 2026-05-13)
- `.planning/PROJECT.md` — D-001..D-021 locked decisions (verified 2026-05-13)
- `CLAUDE.md` — container-only commands + tests + i18n (verified 2026-05-13)
- `.planning/config.json` — security_enforcement: true, ASVS level 1, nyquist_validation enabled (verified 2026-05-13)

### Secondary (MEDIUM confidence)
- PHP 8 `match` keyword vs class name `Match` — case-sensitivity confirmed via PHP manual; Eloquent doesn't expose this as a known issue in any forum I checked

### Tertiary (LOW confidence — marked [ASSUMED] in Assumptions Log)
- `pcntl` extension availability in the web container (A8) — must verify in Wave 0
- `MvpsRelationManager` scoping via `getEloquentQuery()` (A6) — Filament v3 v3.3.50 behaviour; verify during plan execution
- `events.title` denormalisation (A3) — semantic choice that the user may want differently

---

## Metadata

**Confidence breakdown:**
- Schema design: HIGH — directly derived from `.docs/05-database-schema.md` schema family + Phase 2/3 migration idioms
- D-010 row-locked capacity (Pattern 2): HIGH — canonical Laravel pessimistic-locking pattern verified via Context7
- Slot materialisation snapshot (Pattern 3): HIGH — design choice with clear rationale; alternative is documented in Assumption A1
- Match status machine (Pattern 4): HIGH — ENUM-via-CHECK precedent from Phase 1/2/3
- Tag access allowlist (Pattern 5): HIGH — explicit decision; allow-only semantics keep complexity bounded
- Filament wizard (Pattern 6): HIGH — Context7 verified `HasWizard` trait
- Public Vue pages (Pattern 7): HIGH — Phase 2 pattern directly applicable
- Polymorphic Event sync (Pattern 8): HIGH — standard Laravel MorphTo + observer pattern
- DTOs (Pattern 9): HIGH — Phase 1/2/3 pattern
- Concurrency test methodology (Pitfall 4): MEDIUM — `pcntl_fork` works in Linux containers but availability needs Wave 0 verification

**Research date:** 2026-05-13
**Valid until:** 2026-08-13 (90 days — all dependencies are locked to installed versions; Postgres 16 + Laravel 12 + Filament v3 are stable)
