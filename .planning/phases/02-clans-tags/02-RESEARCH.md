# Phase 2: Clans & tags - Research

**Researched:** 2026-05-12
**Domain:** Laravel 12 + Eloquent + Filament v3 + spatie/laravel-translatable + Inertia v2 + Vue 3 + Postgres partial unique index + privacy-gated Inertia pages
**Confidence:** HIGH (verified against codebase + Context7 + packagist; Phase 1 establishes all foundational patterns)

---

## Summary

Phase 2 lands on a fully-working Phase 1 skeleton. All infrastructure (Filament v3, spatie packages, Inertia v2, Pest, Pint, PHPStan L8, Tailwind v4, Docker, CI) is already running. The work is domain-specific: migrations for 6 new tables, 5 new Eloquent models, 5 new Filament resources, 4 new Inertia pages, and the privacy-gating logic for public player profiles.

Three technical items deserve special planning attention:

1. **spatie/laravel-translatable is not yet installed.** The `clan.description` and `clan_tag.label` columns are JSONB-translatable per `.docs/05-database-schema.md`. The package must be added to `composer.json` in the first task. The Filament spatie-laravel-translatable-plugin (`filament/spatie-laravel-translatable-plugin ^3.2`) is abandoned but still functional at v3.3.50 — however, since Phase 2 ships EN-only (D-013), the simpler `KeyValue` pattern already established for `player.bio` in Phase 1 is the recommended approach for `clan.description` in Filament admin. The `HasTranslations` trait with JSONB storage is added to models; the admin LocaleSwitcher is deferred to Phase 7 (CMS).

2. **Partial unique index requires raw SQL.** The `clan_memberships` table constraint `UNIQUE (user_id) WHERE left_at IS NULL` enforces D-009 but cannot be expressed via Laravel's `Schema::unique()`. It must be written as `DB::statement("CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;")` inside the migration's `up()` method. The matching `down()` must `DROP INDEX clan_memberships_one_active`.

3. **Privacy gate is backend-only.** The Vue page for `/players/{slug}` receives only the fields the controller decides to include. The controller applies `show_to` tier and per-section flag checks before constructing the `PlayerData` DTO, returning 404 for `private` profiles. The Vue page never does `v-if` checks on privacy — it renders whatever it receives and omits fields that are absent from the DTO. This is the canonical pattern per UI-SPEC and is verified by a dedicated privacy Unit test.

**Primary recommendation:** Plan in waves: (1) install spatie/laravel-translatable + migrations; (2) models + factories; (3) Filament resources; (4) Inertia public routes (controllers + pages + DTOs); (5) My Clan auth-gated route (controllers + form + invite/application logic); (6) TypeScript DTO generation + test coverage.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Hard constraints carried from prior decisions:**
- D-003 single league Discord guild → `discord_guild` table holds exactly one row; clans store `discord_role_id` not their own guild id
- D-009 one active `ClanMembership` per player → enforced by partial unique index (`WHERE left_at IS NULL`)
- D-008 tags m:n on clans → `clan_clan_tag` pivot
- D-012 Filament covers every domain entity → ClanResource, ClanTagResource, ClanMembershipResource, ClanInviteResource, ClanApplicationResource land in Filament admin
- D-013 i18n plumbed → all UI strings via `__()` / `t()`; clan/translatable fields use spatie/laravel-translatable JSONB columns
- D-018 per-section + global tier player privacy → /players/{slug} renders only what privacy flags allow

**All other implementation choices are at Claude's discretion** (discuss phase was skipped per `workflow.skip_discuss=true`).

### Claude's Discretion
All implementation choices not covered by locked decisions above, including:
- Controller structure (separate controllers vs single resource controller)
- DTO shape for public vs private player profiles
- Invite/Application state machine action names and service layer location
- Filament resource column ordering and filter configuration
- Slug collision policy and reserved-slug list
- Migration ordering and exact timestamp prefixes

### Deferred Ideas (OUT OF SCOPE)
- Discord bot DM notifications for invites/applications (Phase 5)
- Per-clan accent color admin field (forward-compat hook exists in template; field not surfaced in P2)
- Bio locale tab switcher in My Clan form (Phase 7 CMS)
- "Apply to join" button on public clan detail page (forward-compat hook; My Clan Applications tab is ready)
- Mobile hamburger nav (Phase 9 Polish)
- Application flow from public clan detail page (Phase 9 or later)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-tenancy-single-guild | `discord_guild` table holds exactly one row; clans bind to `discord_role_id` inside that guild; no per-clan Discord servers in the model | `discord_guild` table: single-row enforcement via DB CHECK constraint (see Architecture Patterns § Single-Guild Enforcement). Clan model: `discord_role_id` FK-less text field (Discord snowflake). |
| REQ-constraint-single-guild | Reinforces REQ-tenancy-single-guild — single Discord guild for entire league | Same as above. Seeder creates the row; migration adds CHECK CONSTRAINT on row count. |
| REQ-tenancy-multi-clan | One deployment hosts many clans — public clan directory `/clans`, public clan detail `/clans/{slug}`, public player profiles `/players/{slug}` all reachable without auth | Inertia routes registered in `web.php` as public (no auth middleware). Controller resolves clan/player by slug. Privacy gate strips fields server-side before responding. |
| REQ-goal-public-profiles | Player profiles under `show_to` + per-section privacy flags — `private` returns 404; `community` requires auth; `clan` requires same-clan membership; `public` visible to all | `PlayerProfileController` applies tier check then per-section stripping before constructing DTO. Unit tests cover all 4 show_to tiers + each per-section flag. |
</phase_requirements>

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Clan CRUD (create, update, status) | API/Backend (Laravel controllers) | Database (clans table) | Business rules (slug uniqueness, owner assignment, audit logging) belong server-side |
| Clan directory + detail public pages | Frontend Server / SSR (Inertia page, SSR=false in dev) | Browser (Vue 3 hydration) | Public pages served via Inertia `render()`; privacy gate is server-side only |
| Privacy gate for player profiles | API/Backend (controller before Inertia render) | — | D-018: gate MUST be enforced server-side; Vue page renders whatever it receives |
| Partial unique index enforcement | Database / Storage (Postgres `CREATE UNIQUE INDEX WHERE`) | — | D-009: DB constraint is the only reliable enforcement at concurrent write time |
| Single-guild row enforcement | Database / Storage (CHECK CONSTRAINT or trigger) | API/Backend (seeder, no-Create in admin) | DB-level enforcement prevents multi-row state even from direct SQL |
| Clan membership lifecycle (invite, apply, accept, leave) | API/Backend (dedicated controllers or action classes) | Database (clan_memberships, clan_invites, clan_applications) | State machine transitions require DB transactions + audit logging |
| Filament admin for all 5 new entities | Frontend Server / SSR (Filament Livewire) | Database | D-012: every domain entity has a Filament resource |
| spatie/laravel-data DTOs → TypeScript | Build-time (artisan typescript:generate) | Source-control (api.d.ts + packages/shared-types) | Same as Phase 1 pattern; Phase 2 adds ClanData, ClanMembershipData |
| My Clan management page | API/Backend (auth + membership role gate) | Browser (Vue 3, Inertia form helpers) | Route requires auth + active Leader/Officer membership; role check in middleware or controller |

---

## Standard Stack

### Core (already installed in Phase 1)
| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| `laravel/framework` | `^12.0` | Eloquent models, migrations, routing, queues | Installed [VERIFIED: composer.lock] |
| `filament/filament` | `v3.3.50` | Admin resources, forms, tables, audit tabs | Installed [VERIFIED: composer.lock] |
| `spatie/laravel-activitylog` | `^5.0` | `LogsActivity` trait on every new model | Installed [VERIFIED: composer.lock] |
| `spatie/laravel-data` | `^4.22` | DTOs for Inertia page props + TypeScript generation | Installed [VERIFIED: composer.lock] |
| `spatie/laravel-permission` | `^7.4` | Role-based gate for My Clan (Leader/Officer) | Installed [VERIFIED: composer.lock] |
| `inertiajs/inertia-laravel` | `^2.0` | Inertia server adapter | Installed [VERIFIED: composer.lock] |
| `tightenco/ziggy` | `^2.6` | Named route helper in Vue | Installed [VERIFIED: composer.lock] |

### New — Phase 2 requires installation
| Library | Version | Purpose | Why |
|---------|---------|---------|-----|
| `spatie/laravel-translatable` | `^6.14` | `HasTranslations` trait for `clan.description` and `clan_tag.label` JSONB columns | Required per `.docs/05-database-schema.md` and D-013 |

**Installation:**
```bash
make composer ARGS="require spatie/laravel-translatable:^6.14"
```

### Filament Translatable Plugin — NOT recommended for Phase 2
`filament/spatie-laravel-translatable-plugin ^3.2` is abandoned (Packagist status: abandoned, redirect to lara-zeus/spatie-translatable). [VERIFIED: packagist.org/packages/filament/spatie-laravel-translatable-plugin 2026-05-12] Phase 2 ships EN-only (D-013), so the LocaleSwitcher UI is not needed. The `KeyValue` Filament component (already used for `player.bio` in PlayerResource) is the correct approach for `clan.description` in Filament admin — it round-trips the locale-keyed JSONB without the plugin dependency.

**Do not install** `filament/spatie-laravel-translatable-plugin` in Phase 2.

---

## Architecture Patterns

### System Architecture Diagram

```
Public browser request
        │
        ▼
[Laravel Router: web.php]
  ├─ GET /clans         ──────────────────────► [ClanDirectoryController]
  │                                                    │
  ├─ GET /clans/{slug}  ──────────────────────► [ClanShowController]
  │                                                    │
  ├─ GET /players/{slug} ─────────────────────► [PlayerProfileController]
  │                                            ┌────────────────────┐
  │                                            │ Privacy Gate:      │
  │                                            │  show_to check     │
  │                                            │  per-section strip │
  │                                            └───────┬────────────┘
  │                                                    │ → 404 if private
  └─ GET /my-clan  (auth+membership gate)  ──► [MyClanController]
        │
        ▼
[Inertia::render('Page', $dto)]
        │
        ▼
[Vue page — renders props as received; no privacy logic]

Auth-gated write routes (auth middleware):
POST   /my-clan/profile             → MyClanProfileController@update
POST   /my-clan/members/{id}/role   → MyClanMemberController@updateRole
DELETE /my-clan/members/{id}        → MyClanMemberController@remove
POST   /my-clan/invite              → ClanInviteController@store
DELETE /my-clan/invites/{id}        → ClanInviteController@destroy
POST   /my-clan/applications/{id}/accept  → ClanApplicationController@accept
POST   /my-clan/applications/{id}/decline → ClanApplicationController@decline
POST   /clans/{slug}/apply          → ClanApplicationController@store (deferred P2+)

Admin (Filament Livewire — no Inertia):
/admin/clans              → ClanResource
/admin/clan-tags          → ClanTagResource
/admin/clan-memberships   → ClanMembershipResource
/admin/clan-invites       → ClanInviteResource
/admin/clan-applications  → ClanApplicationResource
```

### Recommended Project Structure — new files in Phase 2

```
apps/web/
├── app/
│   ├── Http/Controllers/
│   │   ├── ClanDirectoryController.php
│   │   ├── ClanShowController.php
│   │   ├── PlayerProfileController.php
│   │   ├── MyClan/
│   │   │   ├── MyClanController.php        (index — the tab page)
│   │   │   ├── MyClanProfileController.php (update clan profile)
│   │   │   ├── MyClanMemberController.php  (role change, remove)
│   │   │   ├── ClanInviteController.php    (store, destroy)
│   │   │   └── ClanApplicationController.php (accept, decline)
│   │   └── Clans/
│   │       └── ClanCreateController.php   (store — create clan flow)
│   ├── Models/
│   │   ├── Clan.php
│   │   ├── ClanTag.php
│   │   ├── ClanMembership.php
│   │   ├── ClanInvite.php
│   │   └── ClanApplication.php
│   ├── Data/
│   │   ├── ClanData.php
│   │   ├── ClanTagData.php
│   │   ├── ClanMembershipData.php
│   │   ├── ClanInviteData.php
│   │   └── ClanApplicationData.php
│   └── Filament/Resources/
│       ├── ClanResource.php  (+ Pages/ + RelationManagers/)
│       ├── ClanTagResource.php
│       ├── ClanMembershipResource.php
│       ├── ClanInviteResource.php
│       └── ClanApplicationResource.php
├── database/
│   ├── migrations/
│   │   ├── 2026_05_XX_100000_create_discord_guild_table.php
│   │   ├── 2026_05_XX_100100_create_clans_table.php
│   │   ├── 2026_05_XX_100200_create_clan_tags_table.php
│   │   ├── 2026_05_XX_100300_create_clan_clan_tag_table.php
│   │   ├── 2026_05_XX_100400_create_clan_memberships_table.php
│   │   ├── 2026_05_XX_100500_create_clan_invites_table.php
│   │   └── 2026_05_XX_100600_create_clan_applications_table.php
│   ├── factories/
│   │   ├── ClanFactory.php
│   │   ├── ClanTagFactory.php
│   │   └── ClanMembershipFactory.php
│   └── seeders/
│       ├── DiscordGuildSeeder.php   (stub single row)
│       └── ClanTagSeeder.php        (EU, NA, Tier-1 starter tags)
├── lang/en/
│   ├── clans.php                    (new — full key map from UI-SPEC)
│   └── players.php                  (new — privacy notice + section keys)
└── resources/js/
    ├── pages/
    │   ├── Clans/
    │   │   ├── Index.vue
    │   │   └── Show.vue
    │   ├── Players/
    │   │   └── Show.vue
    │   └── MyClan/
    │       └── Index.vue
    └── components/
        ├── clans/
        │   ├── ClanCard.vue
        │   ├── ClanTagBadge.vue
        │   ├── ClanRoleBadge.vue
        │   └── MemberRow.vue
        ├── players/
        │   └── PlayerCard.vue
        ├── UserMenu.vue
        └── ui/
            ├── StatusBadge.vue
            ├── TabGroup.vue
            ├── Modal.vue
            ├── TextInput.vue
            ├── Textarea.vue
            └── Select.vue
```

---

### Pattern 1: Partial Unique Index (Postgres — D-009)

**What:** Enforce at most one active `ClanMembership` per user. `left_at IS NULL` = active.

**When to use:** Any constraint that applies only to a subset of rows — Postgres partial index is the standard.

**Critical:** Laravel's `Schema::unique()` does NOT support `WHERE` clauses. Must use `DB::statement`.

```php
// Source: .docs/05-database-schema.md § clan_memberships + Phase 1 migration pattern
// In migration up():
DB::statement(
    'CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;'
);

// In migration down():
DB::statement('DROP INDEX IF EXISTS clan_memberships_one_active;');
```

**Testing this constraint:**
```php
// Pest: expects DB exception on second active membership
it('enforces one active membership per user', function (): void {
    $user = User::factory()->create();
    $clan1 = Clan::factory()->create();
    $clan2 = Clan::factory()->create();
    ClanMembership::factory()->create(['user_id' => $user->id, 'clan_id' => $clan1->id, 'left_at' => null]);

    expect(fn () => ClanMembership::factory()->create([
        'user_id' => $user->id, 'clan_id' => $clan2->id, 'left_at' => null
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

---

### Pattern 2: Privacy Gate in Controller (D-018)

**What:** Controller applies tier check then per-section stripping before building the Inertia DTO. Vue page renders whatever arrives.

**When to use:** Every player profile request through `PlayerProfileController`.

```php
// Source: UI-SPEC.md § Privacy gate logic + D-018
class PlayerProfileController
{
    public function __invoke(Player $player): Response
    {
        $privacy = $player->privacy;
        $viewer  = auth()->user();

        // Step 1: global tier check
        match ($privacy->show_to) {
            'private'   => abort(404),
            'community' => auth()->check() ?: abort(404),
            'clan'      => $this->viewerInSameClan($viewer, $player) ?: abort(404),
            default     => null, // 'public' — allow through
        };

        // Step 2: per-section stripping — build DTO with only permitted fields
        return Inertia::render('Players/Show', [
            'player' => PlayerProfileData::fromModelWithPrivacy($player, $privacy, $viewer),
        ]);
    }
}
```

**Key insight:** `PlayerProfileData` is a separate DTO from `PlayerData` (the admin one). It accepts `null` for sections withheld by privacy. The Vue page renders the section only if the prop is non-null.

---

### Pattern 3: HasTranslations on Clan Model (D-013)

**What:** `clan.description` and `clan_tag.label` are JSONB columns with locale-keyed values. `spatie/laravel-translatable` wraps them.

```php
// Source: Context7 /spatie/laravel-translatable docs
use Spatie\Translatable\HasTranslations;

class Clan extends Model
{
    use HasTranslations;

    public array $translatable = ['description'];

    // Accessing: $clan->description           → current locale string
    // Accessing: $clan->getTranslation('description', 'en') → 'en' value
    // Setting:   $clan->setTranslation('description', 'en', 'My clan description')
}
```

**Filament admin for translatable JSONB (EN-only, no plugin):**

Since Phase 2 ships EN-only (D-013), use the `KeyValue` pattern established by Phase 1 `player.bio`:

```php
// Source: apps/web/app/Filament/Resources/PlayerResource.php (Phase 1 established pattern)
Forms\Components\KeyValue::make('description')
    ->label(__('admin.clan.fields.description'))
    ->keyLabel(__('admin.player.fields.bio_locale'))
    ->valueLabel(__('admin.player.fields.bio_text'))
    ->reorderable(false)
    ->helperText(__('admin.clan.help.description_jsonb')),
```

Do NOT install `filament/spatie-laravel-translatable-plugin` — it is abandoned and the LocaleSwitcher is not needed until Phase 7.

---

### Pattern 4: Single-Guild Row Enforcement (D-003)

**What:** `discord_guild` table holds exactly one row.

**Options (ranked by reliability):**

1. **DB CHECK constraint** — `DB::statement("ALTER TABLE discord_guild ADD CONSTRAINT discord_guild_single_row CHECK (id = '00000000-0000-0000-0000-000000000001'::uuid);")` — strongest; fails at DB level for any non-canonical insert.

2. **No-Create in Filament + seeder** — ClanResource/DiscordGuildResource exposes Edit only (no Create page). Seeder populates the row. This is sufficient for operational safety and is simpler to maintain. [ASSUMED: the locked UUID approach adds complexity for marginal benefit; recommend option 2 unless user explicitly wants DB-level enforcement]

**Recommended approach for Phase 2:** Use the seeder + no-Create Filament pattern (mirrors the Permission resource pattern from Phase 1). The seeder creates a stub row with `null` fields. Admin fills in the `guild_id` and channel IDs after Discord bot setup.

---

### Pattern 5: Slug Generation for Clans

**What:** Clan slug is derived from name at creation time and must be unique and URL-safe.

**Pattern from Phase 1 (Player.slug):** `Str::slug($name) . '-' . Str::lower(Str::random(4))`

**For clans, the random suffix is not ideal** — slugs are in public URLs (`/clans/91st-elite`) and should be readable. Recommended pattern:

```php
// Collision-aware slug without random suffix
$base = Str::slug($request->name);
$slug = $base;
$count = 1;
while (Clan::where('slug', $slug)->exists()) {
    $slug = $base . '-' . $count++;
}
```

**Reserved slugs** (per PROJECT.md Open Questions): `admin`, `me`, `api`, `clans`, `players`, `my-clan`, `login`, `logout`, `health`. Store as a config array and validate against during clan create.

---

### Pattern 6: ClanMembership State Machine

**What:** Invite/Application states per `.docs/04-domain-model.md`.

```
ClanInvite:     pending → accepted | declined | revoked | expired
ClanApplication: pending → accepted | declined | cancelled
```

**Membership transition rules:**
- When invite/application is `accepted`: create `ClanMembership` row with `left_at = null`, set `invited_by` if via invite
- When member leaves: set `left_at = now()` on existing `ClanMembership` (do NOT delete)
- When a player has a pending invite and someone else also invites: either reject (simpler) or allow multiple pending invites (UI-SPEC shows one invite at a time — recommend one-pending-per-player-per-clan constraint)

---

### Pattern 7: My Clan Access Gate

**What:** `/my-clan` route requires auth AND active membership with role `leader` or `officer`.

```php
// In MyClanController::__invoke():
$user = auth()->user();
$membership = ClanMembership::where('user_id', $user->id)
    ->whereNull('left_at')
    ->with('clan')
    ->first();

if (! $membership) {
    // Render "no clan" state page — same Inertia component with null clan prop
    return Inertia::render('MyClan/Index', ['membership' => null]);
}

if (! in_array($membership->role, ['leader', 'officer'])) {
    // Member/Recruit: redirect to public clan page
    return redirect()->route('clans.show', $membership->clan->slug);
}

// Leader/Officer: render management page
```

---

### Anti-Patterns to Avoid

- **Privacy logic in Vue:** Never put `v-if="player.show_discord_tag"` in templates. The field is either in the DTO or absent — Vue renders what it gets. Putting privacy logic in Vue makes it trivially bypassable by API inspection.
- **Using `Schema::unique()` for the partial index:** It silently creates a non-partial unique index. Use `DB::statement('CREATE UNIQUE INDEX ... WHERE ...')`.
- **Installing filament/spatie-laravel-translatable-plugin in P2:** The package is abandoned. KeyValue is sufficient for EN-only Phase 2. Revisit in Phase 7.
- **Storing `left_at = null` updates via `delete()`:** Always set `left_at = now()` on the existing row. Hard-deleting memberships destroys history (D-009 specifies "history preserved").
- **`causer` null on My Clan writes:** All membership lifecycle writes go through authenticated controllers. `auth()->user()` is always available and activitylog captures it automatically via `LogsActivity`. Do not manually set causer.
- **Forgetting `declare(strict_types=1)`:** Every PHP file in this project starts with `declare(strict_types=1)` (PHPStan L8 + project convention). New models, controllers, factories all need it.
- **Hardcoded strings in Vue templates:** NoHardcodedStringsTest scans all `.vue` files under `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/`. Every visible string must go through `t()`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSONB translatable columns | Custom JSON column helpers | `spatie/laravel-translatable` `HasTranslations` | Handles locale fallback, `getTranslations()`, `setTranslation()`, array hydration, filtering |
| Audit log on model mutations | Custom observer + log table | `spatie/laravel-activitylog` `LogsActivity` | Already installed + configured from Phase 1; per-resource Audit tab pattern established |
| Filament form → DB persistence | Custom save logic | Filament's `->relationship()` section binding | Same as Phase 1 `player_privacy` Section — Filament saves the related model automatically |
| BelongsToMany pivot (clan_clan_tag) | Manual pivot insert | Filament `Select::make()->multiple()->relationship()` | Filament handles attach/detach on save automatically for BelongsToMany [VERIFIED: Context7 /filamentphp/filament] |
| TypeScript types for new DTOs | Hand-editing `api.d.ts` | `make artisan typescript:generate` command (Phase 1 wired) | Command regenerates the entire `api.d.ts` from all `#[TypeScript]`-annotated Data classes |
| Slug uniqueness loop | Ad-hoc DB query | Helper method on model or dedicated SlugGenerator class | Encapsulate collision logic; reused for ClanTag slug too |

**Key insight:** Phase 1 established every infrastructure primitive Phase 2 needs. The work is applying patterns, not installing new infrastructure.

---

## Common Pitfalls

### Pitfall 1: Partial Unique Index with Schema Builder
**What goes wrong:** Using `$table->unique(['user_id'])` in the clan_memberships migration creates a non-partial unique index, immediately breaking the invariant that a user can have membership history in multiple clans.
**Why it happens:** Laravel's `Schema::unique()` has no `where` parameter.
**How to avoid:** Always use `DB::statement("CREATE UNIQUE INDEX ... WHERE left_at IS NULL;")` for this constraint.
**Warning signs:** Trying to create a second membership for a user after leaving their first clan throws a QueryException unexpectedly.

### Pitfall 2: Privacy Gate in the Wrong Layer
**What goes wrong:** Adding `v-if="privacy.show_discord_tag"` in `Players/Show.vue` instead of stripping the field server-side.
**Why it happens:** It feels natural to co-locate display logic with template logic.
**How to avoid:** The controller/DTO is the only place privacy is enforced. Vue receives `null` or the field is absent from the DTO. The UI-SPEC explicitly states: "The Vue page receives only the permitted fields — it does NOT perform privacy logic itself."
**Warning signs:** Privacy fields appear in Inertia page props when they should be absent.

### Pitfall 3: HasTranslations trait breaks `array` cast on Player.bio
**What goes wrong:** Adding `HasTranslations` to Player model (for `bio`) conflicts with the existing `'bio' => 'array'` cast in `protected function casts()`.
**Why it happens:** `HasTranslations` manages the attribute accessor/mutator for translatable fields internally — an explicit `array` cast on the same attribute creates a double-conversion conflict.
**How to avoid:** Remove `'bio' => 'array'` from `Player::casts()` when adding `HasTranslations`. The trait handles array serialization. [VERIFIED: Context7 /spatie/laravel-translatable]
**Warning signs:** Bio reads back as a double-JSON-encoded string, or `getTranslation()` returns unexpected types.

### Pitfall 4: `show_to = 'community'` check must include admin users
**What goes wrong:** `auth()->check()` returns `false` for guests but the check doesn't account for the fact that admins viewing profiles should always see `community`-tier profiles.
**Why it happens:** `community` is intentionally defined as "logged-in league members" — any authenticated user passes. This is correct. The pitfall is implementing it as a role check instead of a simple `auth()->check()`.
**How to avoid:** `community` tier = `auth()->check()`. No role requirement.

### Pitfall 5: Forgetting HasTranslations on ClanTag.label
**What goes wrong:** ClanTag `label` is a JSONB column; without `HasTranslations`, Eloquent casts it as array but locale resolution doesn't work.
**Why it happens:** ClanTag is a small model; easy to miss applying the trait.
**How to avoid:** Both `Clan.description` and `ClanTag.label` need `HasTranslations`. Add `$translatable = ['label']` to ClanTag.

### Pitfall 6: Filament KeyValue component saves null instead of `[]`
**What goes wrong:** When the KeyValue component has no entries, it submits `null` rather than `{}`, and the JSONB CHECK constraint requiring at least one locale key fails.
**Why it happens:** Filament KeyValue returns `null` on empty form submission.
**How to avoid:** In the Filament resource, add `->default(['en' => ''])` on the `KeyValue` component. Also add a `mutateFormDataBeforeCreate` / `mutateFormDataBeforeSave` that coerces null to `['en' => '']`. [ASSUMED: based on Filament v3 KeyValue behavior from Phase 1 `player.bio` experience; verify during plan execution]

### Pitfall 7: PHPStan L8 with HasTranslations — `$translatable` type annotation
**What goes wrong:** PHPStan L8 flags `public array $translatable = [...]` with "Property has no type specified" or similar.
**Why it happens:** The `HasTranslations` trait expects `$translatable` to exist on the model but does not declare it — PHPStan's `array` without generics causes a warning at level 8.
**How to avoid:** Annotate: `/** @var list<string> */ public array $translatable = ['description'];`

### Pitfall 8: `discord_guild` no-Create restriction in Filament
**What goes wrong:** Exposing a Create action on a DiscordGuildResource lets admins create multiple rows.
**Why it happens:** Filament shows Create by default on resources.
**How to avoid:** In `getPages()`, do NOT register `'create'` route — mirrors the Permission resource pattern from Phase 1.

---

## Code Examples

### Migration: clan_memberships with partial unique index
```php
// Source: .docs/05-database-schema.md § clan_memberships + Phase 1 migration patterns
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clan_id');
            $table->uuid('user_id');
            $table->text('role')->default('recruit');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->uuid('invited_by')->nullable();
            $table->timestamps();

            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE clan_memberships ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clan_memberships ADD CONSTRAINT clan_memberships_role_check CHECK (role IN ('leader','officer','member','recruit'));");
        // D-009: at most one active membership per user (partial unique index — WHERE clause not supported by Schema builder)
        DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clan_memberships_one_active;');
        Schema::dropIfExists('clan_memberships');
    }
};
```

### Model: Clan with HasTranslations + LogsActivity
```php
// Source: Phase 1 Player model pattern + Context7 /spatie/laravel-translatable
declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

class Clan extends Model
{
    /** @use HasFactory<ClanFactory> */
    use HasFactory;
    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['description'];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'tag', 'name', 'description', 'country_code',
        'owner_user_id', 'status', 'discord_role_id', 'discord_announce_channel_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Clan {$event}");
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<ClanTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClanTag::class, 'clan_clan_tag');
    }

    /** @return HasMany<ClanMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    /** @return HasMany<ClanMembership, $this> */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(ClanMembership::class)->whereNull('left_at');
    }
}
```

### DTO: PlayerProfileData (privacy-aware)
```php
// Source: Phase 1 PlayerData.php pattern + D-018 privacy model
declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Privacy-shaped player profile DTO — null fields indicate withheld sections.
 * Built by PlayerProfileController after applying show_to tier + per-section checks.
 */
#[TypeScript]
final class PlayerProfileData extends Data
{
    /**
     * @param  array<string, string>|null  $bio
     * @param  list<ClanMembershipData>|null  $clanHistory
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $displayName,
        public string $avatarUrl,
        public ?string $discordTag,       // null when show_discord_tag=false
        public ?string $countryCode,
        public ?array $bio,
        public ?ClanMembershipData $currentClan, // null when no active clan or privacy withheld
        public ?array $clanHistory,       // null when show_clan_history=false
        public bool $isOwnProfile,        // true when viewer === player's user
    ) {}
}
```

### Filament: ClanResource tag RelationManager via Select (BelongsToMany)
```php
// Source: Context7 /filamentphp/filament "Configure BelongsToMany Relationship in Select Field"
Forms\Components\Select::make('tags')
    ->label(__('admin.clan.fields.tags'))
    ->multiple()
    ->relationship(titleAttribute: 'slug')
    ->preload(),
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `filament/spatie-laravel-translatable-plugin` install | KeyValue component for EN-only phases | Package abandoned (Packagist, 2024+) | Skip plugin install; KeyValue is sufficient for P2 |
| `Schema::unique()` for partial index | `DB::statement('CREATE UNIQUE INDEX ... WHERE ...')` | Always was required for Postgres partial index | Must use raw SQL |
| `spatie/laravel-data` v3 | v4.22 (installed) | v4 changed `DataCollection` generic signature | Use `Data::collect()` return types per v4 docs |

**Deprecated/outdated:**
- `Schema::unique()` for partial constraints: unsupported in Laravel Schema builder; raw SQL required
- `filament/spatie-laravel-translatable-plugin`: abandoned; use KeyValue for EN-only or lara-zeus/spatie-translatable for multi-locale phases

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Filament KeyValue returns `null` on empty submission (not `{}`) | Common Pitfalls #6 | If it returns `{}`, the JSONB CHECK constraint fix is unnecessary — but the defensive default is still harmless |
| A2 | Single-guild enforcement via seeder + no-Create is sufficient; DB CHECK constraint not required | Architecture Patterns § Pattern 4 | If the user wants DB-level enforcement, the migration needs an additional `CHECK` or trigger |
| A3 | Player slug (already generated at first login from Phase 1) is the identifier for `/players/{slug}` — no new slug generation needed for existing players | Pattern 5 / Routes | If some Phase 1 players lack slugs, a migration backfill is needed |
| A4 | `show_to = 'clan'` check for player profile is: viewer must have active ClanMembership in the same clan as the player. "Same clan" = both player and viewer have active membership in the same clan_id | Pattern 2 (Privacy Gate) | If definition is "any clan in the league", the gate is trivially weaker |

---

## Open Questions

1. **Clan create flow ownership**
   - What we know: SC-3 requires "a clan leader/officer can manage their clan" and the UI-SPEC defines the My Clan page.
   - What's unclear: The UI-SPEC shows a "Create your clan" CTA on `/my-clan` when the user has no clan, but the actual clan creation form is noted as a forward-compat hook. Does P2 ship a full `/clans/create` form, or does clan creation only happen via Filament admin?
   - Recommendation: Ship a minimal create flow (name + tag + description only) accessible from `/my-clan` via a `POST /clans` route. The user becomes the Leader automatically. This satisfies SC-3 without requiring the full admin path.

2. **`discord_guild` table timing**
   - What we know: REQ-tenancy-single-guild requires the table to exist and hold exactly one row.
   - What's unclear: The `discord_guild` table isn't referenced by any Phase 2 clan operation; clans store `discord_role_id` (text) directly, not a FK to `discord_guild`. Does the table need to exist in Phase 2 or can it ship empty in Phase 3?
   - Recommendation: Create the table and add the seeder stub in Phase 2 (the migration is small; deferring it to Phase 3 creates a gap in the schema-is-schema-first-doc invariant). REQ-tenancy-single-guild explicitly requires it.

3. **Clan tag slug for filter URL**
   - What we know: `/clans?tag=eu` filter uses `ClanTagBadge` pill click. The UI-SPEC references tag filter by slug.
   - What's unclear: ClanTag has both `slug` (unique, ASCII) and `label` (JSONB). Which is used in the query parameter?
   - Recommendation: Use `slug` in the URL parameter (`?tag=eu`). It's URL-safe and stable; `label` is locale-dependent.

---

## Environment Availability

Step 2.6 result — Docker is available only inside the WSL Docker Desktop integration; `docker` command not reachable from this shell session. All commands run via `make` targets (CLAUDE.md §1, D-021). No new external dependencies required beyond `spatie/laravel-translatable`; all other tools (Postgres 16, Pest, PHPStan, Pint) are established from Phase 1.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Postgres 16 | clan_memberships partial index | ✓ (via Docker) | 16.x [VERIFIED: Phase 1] | — |
| `spatie/laravel-translatable` | Clan.description, ClanTag.label | ✗ (not in composer.lock) | — | Install in Wave 0 task |
| Pest 4 | All Phase 2 tests | ✓ (installed) | [VERIFIED: Phase 1] | — |
| PHPStan level 8 + Pint | CI gates | ✓ (installed) | [VERIFIED: Phase 1] | — |

**Missing dependencies with no fallback:**
- `spatie/laravel-translatable ^6.14` — must be installed in the first plan's Wave 0

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 with `pest-plugin-laravel` |
| Config file | `apps/web/phpunit.xml` |
| Quick run command | `make pest ARGS="--filter=Clan"` |
| Full suite command | `make pest` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-tenancy-single-guild | `discord_guild` table exists with single-row seeder | Feature | `make pest ARGS="--filter=DiscordGuildSeederTest"` | ❌ Wave 0 |
| REQ-constraint-single-guild | Filament DiscordGuildResource has no Create page | Feature | `make pest ARGS="--filter=DiscordGuildResourceTest"` | ❌ Wave 0 |
| REQ-tenancy-multi-clan | GET /clans returns 200 without auth; contains clan data | Feature | `make pest ARGS="--filter=ClanDirectoryTest"` | ❌ Wave 0 |
| REQ-tenancy-multi-clan | GET /clans/{slug} returns 200 without auth | Feature | `make pest ARGS="--filter=ClanShowTest"` | ❌ Wave 0 |
| REQ-goal-public-profiles | `show_to=private` returns 404 | Feature | `make pest ARGS="--filter=PlayerProfilePrivacyTest"` | ❌ Wave 0 |
| REQ-goal-public-profiles | `show_to=community` returns 404 for guest | Feature | `make pest ARGS="--filter=PlayerProfilePrivacyTest"` | ❌ Wave 0 |
| REQ-goal-public-profiles | `show_discord_tag=false` omits discord_tag from DTO | Unit | `make pest ARGS="--filter=PlayerProfileDataTest"` | ❌ Wave 0 |
| D-009 | Second active membership throws QueryException | Feature | `make pest ARGS="--filter=ClanMembershipModelTest"` | ❌ Wave 0 |
| D-012 | ClanResource, ClanTagResource all reachable at /admin | Feature | `make pest ARGS="--filter=ClanResourcesPresentTest"` | ❌ Wave 0 |
| D-013 | No hardcoded strings in Clans/, Players/, MyClan/ pages | Feature | `make pest ARGS="--filter=NoHardcodedStringsTest"` (existing, auto-covers new dirs) | ✅ exists |

### Sampling Rate
- **Per task commit:** `make pest ARGS="--filter=Clan"` (clan-specific tests only, ~5s)
- **Per wave merge:** `make pest` (full suite, ~30s)
- **Phase gate:** Full suite green + `make pint ARGS="--test"` + `make phpstan` before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Clans/ClanDirectoryTest.php` — covers REQ-tenancy-multi-clan public access
- [ ] `tests/Feature/Clans/ClanShowTest.php` — covers REQ-tenancy-multi-clan public detail
- [ ] `tests/Feature/Clans/PlayerProfilePrivacyTest.php` — covers REQ-goal-public-profiles (all 4 tiers + per-section flags)
- [ ] `tests/Feature/Models/ClanMembershipModelTest.php` — covers D-009 partial unique index
- [ ] `tests/Unit/Data/PlayerProfileDataTest.php` — unit tests for privacy stripping logic
- [ ] `tests/Feature/Admin/ClanResourcesPresentTest.php` — covers D-012 Filament resources
- [ ] `tests/Feature/Clans/DiscordGuildSeederTest.php` — covers REQ-tenancy-single-guild
- [ ] `lang/en/clans.php` — new i18n namespace (not a test file; Wave 0 prerequisite)
- [ ] `lang/en/players.php` — new i18n namespace

---

## Security Domain

`security_enforcement: true`, ASVS level 1.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | partial | Auth gate on `/my-clan` routes via `auth` middleware (established Phase 1) |
| V3 Session Management | inherited | Phase 1 SameSite=Lax + HttpOnly already set; no new session logic |
| V4 Access Control | **yes** | My Clan management requires active Leader/Officer membership — controller-level check (not just auth) |
| V5 Input Validation | **yes** | Clan name/tag/description: validated in FormRequest (max lengths, tag 2-8 chars, reserved slug check) |
| V6 Cryptography | no | No new encryption; `discord_role_id` is a plain snowflake text field |

### Known Threat Patterns for This Stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| IDOR on membership actions | Elevation of Privilege | Verify `membership.clan_id === authed user's clan_id` in every My Clan write controller |
| Clan tag spoofing in URL filter | Tampering | `ClanTag::where('slug', $tag)->firstOrFail()` — 404 on unknown tag rather than empty-result |
| XSS via clan description | Tampering | `spatie/laravel-translatable` stores raw text; Inertia escapes template output in Vue by default; no `v-html` on description |
| Mass-assignment on ClanMembership | Tampering | `$fillable` list on model; never use `fill($request->all())` — always pass explicit keys |
| Privacy bypass via Inertia dehydration | Info Disclosure | Do NOT include withheld fields with `null` value — omit them entirely from the DTO constructor call; absent ≠ null |

**Note on "absent vs null" for privacy:** For withheld sections, do NOT pass `discordTag: null` to the DTO. Construct the DTO with conditional spread or separate constructors so the field is absent from the serialized JSON entirely. This prevents client-side enumeration of which fields exist but are hidden.

---

## Sources

### Primary (HIGH confidence)
- `apps/web/app/Models/User.php`, `Player.php`, `PlayerPrivacy.php` — Phase 1 model patterns (verified codebase 2026-05-12)
- `apps/web/app/Filament/Resources/UserResource.php`, `PlayerResource.php` — Phase 1 Filament resource patterns (verified codebase 2026-05-12)
- `apps/web/database/migrations/2026_05_03_*` — Phase 1 migration patterns including raw `DB::statement()` usage (verified codebase 2026-05-12)
- `.docs/05-database-schema.md` — canonical schema for all Phase 2 tables (verified 2026-05-12)
- `.docs/04-domain-model.md` — entity relationships and invariants (verified 2026-05-12)
- Context7 `/spatie/laravel-translatable` — `HasTranslations` trait API (fetched 2026-05-12)
- Context7 `/filamentphp/spatie-laravel-translatable-plugin` — plugin setup pattern for future reference (fetched 2026-05-12)
- Context7 `/filamentphp/filament` v3 — `Select::make()->multiple()->relationship()` for BelongsToMany (fetched 2026-05-12)
- Context7 `/websites/spatie_be_laravel-data_v4` — `Data::from()`, `Data::collect()`, nullable properties (fetched 2026-05-12)
- `apps/web/composer.lock` — installed package versions (verified 2026-05-12)

### Secondary (MEDIUM confidence)
- Packagist `filament/spatie-laravel-translatable-plugin` — abandoned status confirmed (fetched 2026-05-12)
- `.planning/phases/01-foundations/01-RESEARCH.md` — Phase 1 pattern reference (verified 2026-05-12)

### Tertiary (LOW confidence — marked [ASSUMED] in Assumptions Log)
- Filament KeyValue null-on-empty behavior (A1) — inferred from Phase 1 `player.bio` experience; not directly verified in docs

---

## Metadata

**Confidence breakdown:**
- Database schema: HIGH — directly from `.docs/05-database-schema.md` which is authoritative
- Partial unique index SQL: HIGH — verified against Phase 1 migration raw-SQL pattern + Postgres docs
- spatie/laravel-translatable API: HIGH — verified via Context7
- Filament v3 resource patterns: HIGH — verified via codebase + Context7
- Privacy gate pattern: HIGH — directly specified in UI-SPEC + D-018
- filament/spatie-laravel-translatable-plugin abandon status: HIGH — verified via Packagist
- KeyValue null behavior: LOW — assumed from Phase 1 experience

**Research date:** 2026-05-12
**Valid until:** 2026-08-12 (90 days — all dependencies are locked to installed versions; Filament v3 is on LTS-equivalent support track at this version)
