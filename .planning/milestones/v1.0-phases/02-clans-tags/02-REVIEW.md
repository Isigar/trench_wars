---
phase: 2
slug: clans-tags
depth: standard
review_date: 2026-05-17
files_reviewed: 96
status: findings
critical_count: 2
warning_count: 9
info_count: 7
---

# Phase 2: Clans & Tags — Code Review Report

**Reviewed:** 2026-05-17
**Depth:** standard
**Files Reviewed:** 96
**Status:** findings

## Executive Summary

Phase 2 is structurally solid. The architectural invariants for D-003 (one Discord guild), D-008 (clan tags m:n), D-009 (one active membership — partial unique index, service guards, controller transactions), D-013 (translatable JSONB via `HasTranslations`), and D-018 (privacy gate strictly server-side) are all enforced with multiple layers of defence. Mass-assignment surfaces are correctly fenced (FormRequest `validated()` whitelists; `discord_role_id` excluded from My Clan paths; admin Filament gates Discord fields behind a toggle). Public DTO construction defers privacy decisions to `PlayerPrivacyGate` and uses `Optional::create()` to keep withheld fields **absent** from JSON (the documented "absent ≠ null" contract). The state-machine services (`ClanInviteService`, `ClanApplicationService`) wrap accept/decline in `DB::transaction()` so the partial-unique-index race rolls back the invite/application update too.

That said, this review finds **2 critical correctness defects** and a handful of warnings worth attention before the v1.1 polish pass:

1. **CR-01** — `PlayerCard.vue` double-prefixes the Discord tag with `@` (backend already prepends `@`, template prepends another) producing `@@username` on every public profile.
2. **CR-02** — `PlayerCard.vue` links to `/clans/{currentClan.clan_id}` (UUID) instead of `/clans/{clan_slug}`; clan-show route is `{clan:slug}`-bound, so the link 404s on every public profile that has a current clan.
3. **WR-01** — Tag uniqueness has no FormRequest validator; a duplicate tag surfaces as a 500 `QueryException` instead of a friendly validator error.
4. **WR-04** — `PublicPlayerData::fromPlayer()` populates `currentClan` for everyone without consulting any privacy flag, leaking the active clan affiliation of a player whose `show_clan_history=false` (D-018 grey-area).
5. **WR-05** — `ClanInviteService::sendInvite` / `accept` / `ClanApplicationService::accept` perform their D-009 pre-conditions outside the transaction without row locks; the partial-unique index is the only true safeguard, but the invite/application status update can still race in edge cases.

Below: full finding list with file:line and recommended fixes.

---

## Critical findings

### CR-01: Double-`@` prefix on Discord tag in PlayerCard.vue

**Severity:** Critical (correctness — visible defect on every public player profile)
**Category:** correctness
**File:** `apps/web/resources/js/components/players/PlayerCard.vue:53-57`

The backend already prepends `@` to the Discord tag in `PublicPlayerData::fromPlayer()` (line 76-78):

```php
$discordTag = $gate->allowsSection(...)
    ? ($player->user?->username !== null ? '@' . $player->user->username : null)
    : Optional::create();
```

The Vue template then prepends `@` a second time:

```vue
<span class="font-mono text-sm font-semibold text-[var(--color-text-muted)]">
    @{{ player.discordTag }}
</span>
```

Net result on every rendered profile that grants the discord tag: `@@username`.

**Fix:** Remove the literal `@` from the template since the DTO already includes it:

```vue
<span class="font-mono text-sm font-semibold text-[var(--color-text-muted)]">
    {{ player.discordTag }}
</span>
```

(Alternatively, drop the `@` from the backend and keep it in the template — pick one location and document it.)

---

### CR-02: `currentClan` link routes to UUID instead of clan slug

**Severity:** Critical (correctness — link 404s on every profile that shows a current clan)
**Category:** correctness
**File:** `apps/web/resources/js/components/players/PlayerCard.vue:66-72`

```vue
<a
    :href="`/clans/${(player.currentClan as ClanMembershipData).clan_id}`"
    class="..."
>
    {{ (player.currentClan as ClanMembershipData).username ?? t('common.nav.clans') }}
</a>
```

`ClanMembershipData` carries `clan_id` (UUID) but not the clan slug. The `/clans/{clan:slug}` route is slug-bound (`Clan::getRouteKeyName() === 'slug'`), so navigating to `/clans/{uuid}` resolves to no clan and returns 404 via Laravel's implicit binding miss.

Additionally, the link text uses `currentClan.username` (the **member's** username, not the clan name) — `ClanMembershipData` does not carry a `clan_name` or `clan_slug` field, so the displayed link label is also wrong.

**Fix (two-part):**

1. Add `clan_slug` and `clan_name` to `ClanMembershipData` and populate them in `ClanMembershipData::fromModel()` from the eager-loaded `clan` relation:

```php
// app/Data/ClanMembershipData.php
public function __construct(
    // ... existing fields
    public ?string $clan_slug,
    public ?string $clan_name,
) {}

public static function fromModel(ClanMembership $membership): self
{
    $clan = $membership->relationLoaded('clan') ? $membership->clan : null;
    // ...
    return new self(
        // ...
        clan_slug: $clan?->slug,
        clan_name: $clan?->name,
    );
}
```

2. Update the template to use `clan_slug` and `clan_name`:

```vue
<a :href="`/clans/${player.currentClan.clan_slug}`" class="...">
    {{ player.currentClan.clan_name ?? t('common.nav.clans') }}
</a>
```

3. Ensure `PublicPlayerData::fromPlayer()` eager-loads `clan` on the membership it builds:

```php
$activeMembership = ClanMembership::where('user_id', $player->user_id)
    ->whereNull('left_at')
    ->with(['clan', 'clan.tags'])  // already done — good
    ->first();
```

(The eager load already exists; only the DTO + template need updating.)

---

## Warning findings

### WR-01: Clan tag uniqueness not validated at FormRequest layer

**Severity:** Warning (UX / robustness)
**Category:** correctness
**Files:**
- `apps/web/app/Http/Requests/Clans/StoreClanRequest.php:43`
- `apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php:51`

`clans.tag` is `UNIQUE` at the DB level (`apps/web/database/migrations/2026_05_12_100100_create_clans_table.php:28`). Neither the create nor the update FormRequest carries a `unique:clans,tag` rule. A user submitting a duplicate tag in the My Clan form (or POST /clans) bypasses validation entirely and surfaces a raw `Illuminate\Database\QueryException` (SQLSTATE 23505) — Laravel renders that as a generic 500 in production.

**Fix:**

```php
// StoreClanRequest::rules()
'tag' => [
    'required', 'string', "min:{$tagMin}", "max:{$tagMax}",
    'regex:/^[A-Za-z0-9_-]+$/',
    'unique:clans,tag',
],

// UpdateClanProfileRequest::rules()
use Illuminate\Validation\Rule;

'tag' => [
    'sometimes', 'required', 'string', "min:{$tagMin}", "max:{$tagMax}",
    'regex:/^[A-Za-z0-9_-]+$/',
    Rule::unique('clans', 'tag')->ignore($this->route('clan')->id),
],
```

Apply the same treatment to `slug` in `UpdateClanProfileRequest` if it ever becomes editable.

---

### WR-02: `ClanDirectoryController` does not escape `%` / `_` wildcards in `ILIKE` parameter

**Severity:** Warning (information / UX — minor)
**Category:** security (information disclosure — low risk on public surface)
**File:** `apps/web/app/Http/Controllers/ClanDirectoryController.php:48-54`

```php
$query->where(
    fn ($query) => $query
        ->where('name', 'ILIKE', "%{$q}%")
        ->orWhere('tag', 'ILIKE', "%{$q}%")
);
```

The binding is safe against SQL injection (Eloquent parameterises) but `%` and `_` are Postgres LIKE wildcards: a user submitting `_` will match any single character, and `%` matches anything. This over-matches and lets an attacker iterate the directory in unintended ways (and prevents legitimate substring search for literal underscore in clan names).

**Fix:** Escape wildcard chars before interpolation, or use Postgres' `ESCAPE` clause:

```php
$escaped = addcslashes($q, '%_\\');
$query->where(
    fn ($query) => $query
        ->where('name', 'ILIKE', "%{$escaped}%")
        ->orWhere('tag', 'ILIKE', "%{$escaped}%")
);
```

(`addcslashes` matches Postgres' default backslash escape semantics.)

---

### WR-03: `country_code` validation accepts non-alpha 2-char strings ("12", "@!", etc.)

**Severity:** Warning (data integrity)
**Category:** correctness
**Files:**
- `apps/web/app/Http/Requests/Clans/StoreClanRequest.php:45`
- `apps/web/app/Http/Requests/MyClan/UpdateClanProfileRequest.php:53`

`'country_code' => ['nullable', 'string', 'size:2']` admits `12`, `@!`, `--`, etc. The display layer just renders the raw text (Clans/Show.vue line 97), so a junk value leaks straight into the public detail page.

**Fix:** Constrain to ISO-3166-1 alpha-2:

```php
'country_code' => ['nullable', 'string', 'size:2', 'alpha', 'uppercase'],
```

Or load a Laravel package like `umpirsky/country-list` and validate against the official set. At minimum require alpha.

---

### WR-04: `currentClan` is included in `PublicPlayerData` regardless of `show_clan_history`

**Severity:** Warning (privacy — D-018 boundary case)
**Category:** security / correctness
**File:** `apps/web/app/Data/PublicPlayerData.php:83-93`

```php
$activeMembership = $player->user?->id !== null
    ? ClanMembership::where('user_id', $player->user_id)
        ->whereNull('left_at')
        ->with(['clan', 'clan.tags'])
        ->first()
    : null;

$currentClan = $activeMembership !== null
    ? ClanMembershipData::fromModel($activeMembership)
    : null;
```

`clanHistory` is correctly gated on `show_clan_history`, but `currentClan` (the active membership) is always populated. A player who disables `show_clan_history` to keep their clan affiliation private still has `currentClan` leaked to every visitor that passes the tier check. The D-018 specification reads "show_clan_history" as covering the entire clan-affiliation surface; whether `currentClan` belongs under it is ambiguous in PROJECT.md, but the user-facing privacy toggle implies it should.

**Fix:** Gate `currentClan` on the same flag (or define a new `show_current_clan` flag if the product intent is to separate "I am currently in clan X" from "my full history"):

```php
$currentClan = $gate->allowsSection($player, $viewer, 'show_clan_history')
    ? ($activeMembership !== null
        ? ClanMembershipData::fromModel($activeMembership)
        : null)
    : Optional::create();
```

And widen the property type to `Optional|ClanMembershipData|null`.

Note: this is also the dependency for **CR-02** — once `currentClan` is properly gated and the slug/name fields are added, the public profile page becomes both correct and privacy-clean.

---

### WR-05: D-009 invariants checked outside the transaction in invite/application accept paths

**Severity:** Warning (race condition — bounded by DB partial unique index)
**Category:** correctness
**Files:**
- `apps/web/app/Services/ClanInviteService.php:96-123`
- `apps/web/app/Services/ClanApplicationService.php:39-83`
- `apps/web/app/Http/Controllers/Clans/ClanCreateController.php:40-76`

All three accept/create flows query `ClanMembership::where('user_id', …)->whereNull('left_at')->exists()` BEFORE opening the DB transaction. Between that check and the `DB::transaction(...)` block, a parallel write can land — the partial unique index `clan_memberships_one_active` saves us (the second insert throws), but:

- The first failing transaction rolls back the membership AND the invite/application status update — good (audit log entry is rolled back too).
- The user receives a 500 from a raw QueryException, not the friendly DomainException message.
- For `ClanCreateController`, a concurrent second clan creation by the same user results in QueryException → 500 instead of the localized "already_member" message.

**Fix:** Move the pre-condition check inside the transaction and use `lockForUpdate()` on the membership table to serialise concurrent attempts, OR catch `QueryException` with SQLSTATE `23505` and rethrow as the appropriate `DomainException` / `ValidationException`. Simplest pragmatic fix:

```php
DB::transaction(function () use (...): ClanMembership {
    $alreadyMember = ClanMembership::where('user_id', $acceptor->id)
        ->whereNull('left_at')
        ->lockForUpdate()
        ->exists();

    if ($alreadyMember) {
        throw new \DomainException(__('clans.invites.error.invitee_in_clan'));
    }

    $invite->update([...]);
    return ClanMembership::create([...]);
});
```

(Note: a DomainException thrown inside the closure rolls back the transaction automatically because Laravel re-throws after rollback. The catch in the controller still works.)

---

### WR-06: Username enumeration via invite endpoint

**Severity:** Warning (low-severity information disclosure on auth-gated endpoint)
**Category:** security
**File:** `apps/web/app/Http/Requests/MyClan/StoreClanInviteRequest.php:50-61`

```php
protected function prepareForValidation(): void
{
    $username = $this->input('invited_username');
    if ($username !== null && $this->input('invited_user_id') === null) {
        $resolved = User::where('username', $username)->first();
        $this->merge(['invited_user_id' => $resolved?->id]);
    }
}
```

The rule chain returns distinct messages depending on whether the user exists (`invited_user_id` required/exists vs. service-layer "already in clan"). An authenticated attacker (Leader/Officer) can iterate usernames to enumerate valid Discord usernames. The endpoint is auth-gated and role-gated (Leader/Officer only), so the blast radius is small, but combined with no rate limit it allows iterative enumeration.

**Fix:** Either (a) add a per-user throttle middleware on the invite endpoint, or (b) keep the validation messages indistinguishable: always return the same `clans.invites.error.user_not_found_or_unavailable` message regardless of whether the user existed or was already in a clan.

---

### WR-07: `DiscordGuildResource::form()` `guild_id` max length is 32, Discord snowflakes are ≤ 19 digits

**Severity:** Warning (data integrity — admin form admits malformed input)
**Category:** correctness
**File:** `apps/web/app/Filament/Resources/DiscordGuildResource.php:48-52`

```php
Forms\Components\TextInput::make('guild_id')
    ->required()
    ->maxLength(32)
    ->regex('/^[0-9]+$/')
    ->helperText('Discord snowflake — numeric string, up to 19 digits.');
```

The helper text says "up to 19 digits" but the validator permits 32. Migration column is `text` so the DB does not constrain. A 32-char numeric string is not a valid Discord snowflake; it will fail every Discord API call later.

**Fix:** Tighten the limit:

```php
->minLength(17)   // current min snowflake length
->maxLength(20)   // headroom for future-dated snowflakes (2090+)
```

Apply the same constraint to `discord_role_id` and `discord_announce_channel_id` on `ClanResource`.

---

### WR-08: `ClanResource` table is missing `DeleteAction` / `RestoreAction` for SoftDeletes

**Severity:** Warning (UX gap — admins cannot soft-delete clans from the list view)
**Category:** correctness
**File:** `apps/web/app/Filament/Resources/ClanResource.php:194-199`

```php
->actions([
    Tables\Actions\ViewAction::make(),
    Tables\Actions\EditAction::make(),
    Tables\Actions\ForceDeleteAction::make()
        ->visible(fn ($record) => auth()->user()?->can('forceDelete', $record) ?? false),
]);
```

`Clan` uses `SoftDeletes` and `Clan::class` has a `TrashedFilter` (line 192) — but there is no `DeleteAction` (soft-delete) or `RestoreAction`. Admins can hard-delete via Force but cannot soft-delete or recover. With `restrictOnDelete` FK from clan_memberships, a force-delete actually fails for any clan that has had members (a separate concern but a sign the policy/action set is incomplete).

**Fix:**

```php
->actions([
    Tables\Actions\ViewAction::make(),
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make(),
    Tables\Actions\RestoreAction::make(),
    Tables\Actions\ForceDeleteAction::make()
        ->visible(fn ($record) => auth()->user()?->can('forceDelete', $record) ?? false),
]);
```

Also add `delete`, `restore`, `forceDelete` methods to `ClanPolicy` (or rely on the `admin-access` `before()` bypass and document it).

---

### WR-09: `ClanResource` "discord_advanced_fields_enabled" toggle dehydrates to nothing — its previous state is lost across saves

**Severity:** Warning (UX — toggle resets on every edit, admins must re-enable for every change)
**Category:** maintainability
**File:** `apps/web/app/Filament/Resources/ClanResource.php:114-130`

```php
Forms\Components\Toggle::make('discord_advanced_fields_enabled')
    ->label('Enable Discord field editing')
    ->dehydrated(false)
    ->live()
    ->helperText('...');
```

`dehydrated(false)` correctly excludes the toggle from form data (it has no DB column). But because there is no `formatStateUsing` to restore default state on edit and no `default(false)`, the toggle visually resets each time. Worse: the toggle's label `'Enable Discord field editing'` and helper text are NOT translated via `__()` while the other field labels are — D-013 violation (NoHardcodedStringsTest does not currently scan Filament resources but the project convention is universal i18n).

**Fix:**

```php
Forms\Components\Toggle::make('discord_advanced_fields_enabled')
    ->label(__('admin.clan.fields.discord_advanced_toggle'))
    ->default(false)
    ->dehydrated(false)
    ->live()
    ->helperText(__('admin.clan.fields.discord_advanced_toggle_help')),
```

And add the two keys to `lang/en/admin.php`.

---

## Info findings

### IN-01: Dead policy method `ClanPolicy::transferLeadership` and unused i18n key `clans.members.leader_transfer_warning`

**File:** `apps/web/app/Policies/ClanPolicy.php:78-81` + `apps/web/lang/en/clans.php:38`

No production route or controller calls `transferLeadership`. The corresponding warning copy is unused. Either wire up a `/my-clan/transfer-leadership` flow or remove until needed (Phase 9 candidate).

---

### IN-02: `PublicPlayerData::fromPlayer` has a redundant null check

**File:** `apps/web/app/Data/PublicPlayerData.php:84-89`

```php
$activeMembership = $player->user?->id !== null
    ? ClanMembership::where('user_id', $player->user_id)
    : null;
```

`$player->user_id` is a column on `players`; it is always available (and required by `restrictOnDelete` FK) even when the `user` relation is not eager-loaded. The `$player->user?->id !== null` guard is misleading — it gates the query on a relation that might not be loaded but a column that always is. Suggest simplifying:

```php
$activeMembership = ClanMembership::where('user_id', $player->user_id)
    ->whereNull('left_at')
    ->with(['clan', 'clan.tags'])
    ->first();
```

---

### IN-03: `Clan::factory()` slug uses `Str::random(4)` which produces base-36 chars; could collide

**File:** `apps/web/database/factories/ClanFactory.php:29`

`Str::lower(Str::random(4))` has 36⁴ ≈ 1.7M space. The unique() on `fake()->company()` already provides distinct base slugs; the random suffix is overkill and could theoretically collide on bulk-seeded tests. Switch to `Str::lower(Str::random(8))` or use a sequence-based approach in the factory's `unique()` chain. Low risk in current usage.

---

### IN-04: Magic strings for invite/application status & member role across the codebase

**Files:** Many — e.g. `ClanInviteService.php`, `ClanApplicationService.php`, `ClanPolicy.php`, `ClanMembershipPolicy.php`, `Clans/Show.vue`, migrations.

`'pending'`, `'accepted'`, `'leader'`, `'officer'`, etc. are inlined as string literals everywhere. With PHP 8.1+ backed enums available and PHPStan L8 enforcing the project, switching to:

```php
enum ClanMemberRole: string {
    case Leader = 'leader';
    case Officer = 'officer';
    case Member = 'member';
    case Recruit = 'recruit';
}
```

…would let the type system catch the next typo (already saw `'cancelled'` in one badge color mapping for invites that have no such status — `ClanInviteResource.php:103`). Backed enums also serialize fine to JSON for DTOs. Consider for v1.1.

---

### IN-05: `ClanInviteResource::table()` badge mapping references `'cancelled'` status that does not exist for invites

**File:** `apps/web/app/Filament/Resources/ClanInviteResource.php:102`

```php
'declined', 'revoked', 'cancelled' => 'danger',
```

Invite statuses per the migration CHECK constraint are `pending|accepted|declined|revoked|expired`. `cancelled` is an Application status, not an Invite status. The line is dead; it does no harm but it's a copy-paste artefact from the application resource.

**Fix:** Remove `'cancelled'`:

```php
'declined', 'revoked' => 'danger',
```

---

### IN-06: `Clans/Show.vue` accent-color wrapper has a redundant always-empty `:style="{}"` div

**File:** `apps/web/resources/js/pages/Clans/Show.vue:60`

```vue
<div :style="{}">
```

The wrapper is documented as a "forward-compat hook" for Phase 3+ accent colors. Until Phase 3 lands the accent_color column, the div is purely structural. Suggest replacing the empty binding with a normal `<div>` and adding the `:style` only when the field exists — keeps the DOM clean and Vue's reactivity less confused.

---

### IN-07: `StatusBadge.vue` `inlineStyle` returns objects with potentially undefined keys

**File:** `apps/web/resources/js/components/ui/StatusBadge.vue:65-74`

```ts
const inlineStyle = computed(() => {
    if (!needsInlineStyle.value) return {};
    const colorMap: Record<string, string> = { ... };
    return {
        backgroundColor: colorMap[props.variant],
        color: textMap[props.variant],
    };
});
```

If a future variant is added to the union but missed in `colorMap` and `textMap`, this returns `{ backgroundColor: undefined, color: undefined }`. Vue will warn and apply empty style. Use `Variant` as the index type instead of `string` so TS catches missing entries at compile-time:

```ts
const colorMap: Partial<Record<Variant, string>> = { ... };
```

…and assert via switch instead of map lookup for the three opacity variants.

---

## Verified clean

The following invariants were checked and pass review:

- **D-009 partial unique index** is correctly enforced in three layers: DB migration (`clan_memberships_one_active`), service `sendInvite`/`accept`/`store` pre-condition checks, and the `ClanCreateController` 409 guard. The integration test `ClanMembershipUniqueTest.php` covers all three.
- **D-013 translatable JSONB**: `Clan::$translatable = ['description']` and `ClanTag::$translatable = ['label']` are correctly declared; DTOs call `getTranslations(...)` (not the accessor) to surface the full locale map; Filament KeyValue components default to `['en' => '']` with `mutateFormData*Save()` coercion to avoid the null-on-empty pitfall.
- **D-018 PlayerPrivacyGate** is the sole privacy authority. Controller calls `passesTier()` then abort(404); `fromPlayer()` uses `Optional::create()` to make withheld fields **absent** from the JSON output (not `null`). The Vue templates correctly check `=== undefined && !== null` for absent-vs-null and never short-circuit on raw privacy flags.
- **D-003 single discord_guild**: enforced operationally by `DiscordGuildSeeder::firstOrCreate([])` + `DiscordGuildResource::getPages()` omitting the `'create'` route + table actions excluding `DeleteAction`. PROJECT.md/RESEARCH note that DB CHECK constraint was deliberately deferred — that decision is documented and consistent.
- **Slug generation** (`ClanSlugGenerator::generate`): collision-tolerant via `-2`/`-3` suffixing, reserved-slug list checked from `config/clan.php`, throws `ReservedSlugException` / `InvalidArgumentException` for edge cases. Caller (`ClanCreateController`) translates `ReservedSlugException` to a `ValidationException` so users see a friendly message.
- **Mass assignment**: all models have explicit `$fillable`. `ClanMembership` excludes `clan_id`/`user_id` from any user-supplied path (only the controller injects them inside transactions). `Clan` includes `discord_role_id` and `discord_announce_channel_id` in `$fillable` for Filament edit, but `UpdateClanProfileRequest::rules()` excludes them — the FormRequest's `validated()` is what the controller passes to `update()`, so user-supplied `discord_role_id` cannot leak in via the My Clan flow (T-02-05-02 holds).
- **SQL injection**: no `DB::statement()` with user input. All migration raw SQL is hard-coded. Search controller uses parameterized `ILIKE` (separate `%` over-match concern flagged as WR-02 but no injection vector).
- **Filament admin gates**:
  - `ClanMembershipResource`, `ClanInviteResource`, `ClanApplicationResource` are read-only (no Create/Edit/Delete pages, only List + View) per D-009/D-012 audit intent.
  - `DiscordGuildResource` correctly omits `'create'` route and `DeleteAction` (D-003).
  - `ClanResource` gates Discord fields behind an "Enable edit" toggle (T-02-09-02 mitigation works, WR-09 only flags i18n + UX nit).
- **CSRF**: Inertia v2 XSRF cookie handled automatically (no manual `<meta name=csrf-token>`); all POST/PATCH/DELETE go through `router.post|patch|delete` or `useForm(...).submit()` which carry the XSRF header.
- **Race conditions** (invite/application accept): wrapped in `DB::transaction()`; partial unique index is the last-line defense — see WR-05 for the suggested upgrade to `lockForUpdate()`.
- **XSS in Vue templates**: all clan description / bio renders use `{{ }}` interpolation (HTML-escaped by Vue), zero `v-html` usage anywhere in the Phase 2 component set (verified with grep). `whitespace-pre-wrap` is used safely.
- **Authorization**: every My Clan write controller delegates to a `FormRequest::authorize()` that either calls a `Policy::can()` or directly queries `ClanMembership`. `ClanInviteService` / `ClanApplicationService` re-check identity inside the service (`abort(403)` when actor != invitee/applicant) — defence-in-depth. Public read controllers (`ClanDirectoryController`, `ClanShowController`, `PlayerProfileController`) have appropriate access (none required) plus per-field privacy gating.

---

## Next steps

1. **Fix critical findings now (CR-01, CR-02)** — both are visible defects on every public player profile that has a current clan. CR-02 in particular makes the public clan-link on every player profile 404; this is a regression-risk item to ship before any of the documented PENDING_MANUAL_SMOKE Phase 2 items can plausibly pass.
2. **Tag uniqueness validator (WR-01)** should land in the same hotfix — the 500 path on duplicate-tag is reachable via any clan-create attempt where the tag accidentally clashes.
3. **File the remaining warnings as v1.1 polish backlog**:
   - WR-02 (ILIKE wildcard escape)
   - WR-03 (country_code alpha validation)
   - WR-04 (currentClan privacy gating — touches the same area as CR-02, can be bundled)
   - WR-05 (lockForUpdate on D-009 pre-checks)
   - WR-06 (invite endpoint enumeration throttle)
   - WR-07 (Discord snowflake length)
   - WR-08 (Filament soft-delete actions on ClanResource)
   - WR-09 (Discord toggle i18n + default)
4. **Info findings**: address opportunistically. IN-05 (dead `'cancelled'` mapping on invite badge) is a one-line edit and worth doing during the next hotfix; the rest can wait.

---

_Reviewed: 2026-05-17_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
