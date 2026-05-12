---
phase: 02-clans-tags
plan: "05"
subsystem: privacy-gate-dtos
tags: [privacy, dtos, typescript, security]
dependency_graph:
  requires: [02-03]
  provides: [PlayerPrivacyGate, PublicPlayerData, ClanData, ClanTagData, ClanMembershipData, ClanInviteData, ClanApplicationData]
  affects: [02-07, 02-08, 02-09]
tech_stack:
  added: []
  patterns:
    - "PlayerPrivacyGate service — stateless class, no constructor injection, all methods public"
    - "Optional::create() for absent fields in spatie/laravel-data (VisibleDataFieldsResolver strips Optional instances from toArray())"
    - "Optional|T|null PHP union types required to store Optional in typed properties"
    - "fromPlayer() static factory pattern on PublicPlayerData for privacy-aware construction"
key_files:
  created:
    - apps/web/app/Services/PlayerPrivacyGate.php
    - apps/web/app/Data/ClanData.php
    - apps/web/app/Data/ClanTagData.php
    - apps/web/app/Data/ClanMembershipData.php
    - apps/web/app/Data/ClanInviteData.php
    - apps/web/app/Data/ClanApplicationData.php
    - apps/web/app/Data/PublicPlayerData.php
  modified:
    - apps/web/tests/Unit/Services/PlayerPrivacyGateTest.php
    - apps/web/tests/Unit/Data/PlayerProfileDataTest.php
    - apps/web/resources/js/types/api.d.ts
decisions:
  - "Optional|T|null union types on PublicPlayerData constructor — required because PHP type system rejects storing Optional instance in a ?string property; spatie/laravel-data's VisibleDataFieldsResolver handles the stripping"
  - "allowsSection() returns false when privacy row is null (defensive default) not true — fail-closed is correct for privacy"
  - "viewerInSameClan() uses Eloquent pluck + intersect on clan_id sets (intersection check per T-02-03-02 mitigation)"
  - "passesTier() uses explicit null check ($player->privacy !== null ? ...) instead of nullsafe operator chaining — PHPStan L8 rejects nullsafe on the left side of ?? when type cannot be null"
  - "clanHistory/matchHistory/stats placeholders return null (allowed, section present) when gate passes — full implementation in plan 02-07"
metrics:
  duration: "310s (~5 min)"
  completed: "2026-05-12"
  tasks_completed: 2
  files_changed: 10
---

# Phase 2 Plan 05: Privacy Gate + DTOs Summary

Privacy gate service + 6 Phase-2 DTOs implementing the absent-vs-null security rule for player profile serialization.

## What Was Built

### Task 1: PlayerPrivacyGate Service (commit 33016d9)

`App\Services\PlayerPrivacyGate` — stateless service, no constructor injection. All 4 D-018 privacy tiers enforced:

| Method | Signature | Purpose |
|--------|-----------|---------|
| `passesTier` | `(Player, ?User): bool` | Global tier check; false = controller should 404 |
| `viewerInSameClan` | `(?User, Player): bool` | Clan-tier intersection check (T-02-03-02) |
| `allowsSection` | `(Player, ?User, string): bool` | Per-section flag check |
| `isOwnProfile` | `(?User, Player): bool` | Viewer === player's owning user |

Own-profile bypass: `isOwnProfile()` returns true → all tier and section checks pass.

23 unit tests (replacing Wave 0 RED stub) covering:
- 4 show_to tiers (private/community/clan/public) with all viewer permutations
- 5 per-section flags; own-profile bypass for all flags
- Inactive membership (left_at set) does NOT pass the clan tier
- Defensive null-privacy row handling
- InvalidArgumentException on unknown flag name

### Task 2: Phase-2 DTO Suite (commit 66f225a)

**Simpler DTOs (TypeScript wire contracts):**

| DTO | Key fields | Notes |
|-----|-----------|-------|
| `ClanTagData` | id, slug, label (JSONB), color | label: `array<string,string>|null` |
| `ClanMembershipData` | id, clan_id, user_id, role, joined_at, left_at, invited_by, username, avatar_url, player_slug | Denormalized username/avatar for MemberRow |
| `ClanData` | id, slug, tag, name, description (JSONB), country_code, status, discord_role_id, tags, active_member_count | tags: `list<ClanTagData>` |
| `ClanInviteData` | id, clan_id, invited_user_id, inviting_user_id, status, message, decided_at, expires_at | State: pending→accepted|declined|revoked|expired |
| `ClanApplicationData` | id, clan_id, applicant_user_id, status, message, decided_at, decided_by | State: pending→accepted|declined|cancelled |

**Security-critical DTO:**

`PublicPlayerData` — privacy-aware Inertia DTO for `/players/{slug}`:
- Constructor uses `Optional|T|null` union types for withheld fields
- `fromPlayer(Player, ?User, PlayerPrivacyGate): self` static factory calls gate per-section
- Withheld fields receive `Optional::create()` → absent from `toArray()` output
- Always-present fields: id, slug, displayName, avatarUrl, isOwnProfile, countryCode, bio, currentClan
- Conditionally-absent: discordTag, clanHistory, matchHistory, stats

11 PlayerProfileDataTest cases (replacing Wave 0 RED stub) including:
- `array_key_exists('discordTag', $arr) === false` when show_discord_tag=false
- Own-profile bypass: all sections present even when all flags are false
- bio serialized as `Record<string,string>`
- countryCode always present (no D-018 flag controls it)
- currentClan = null when no active membership (present key, null value — not Optional)

**TypeScript regeneration:** `api.d.ts` now includes 6 new types:
- `ClanData`, `ClanTagData`, `ClanMembershipData`, `ClanInviteData`, `ClanApplicationData`
- `PublicPlayerData` — Optional fields appear as `undefined | T | null` in TypeScript

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] PHPStan: nullsafe `?->` on non-nullable `$player->privacy`**
- **Found during:** Task 1 PHPStan run
- **Issue:** `$player->privacy?->show_to ?? 'community'` — PHPStan L8 reports nullsafe access on left side of `??` is unnecessary
- **Fix:** Explicit null check: `$player->privacy !== null ? $player->privacy->show_to : 'community'`
- **Files modified:** `app/Services/PlayerPrivacyGate.php`
- **Commit:** 33016d9

**2. [Rule 1 - Bug] PHPStan: nullsafe `?->username`/`?->avatar_url` after `??`**
- **Found during:** Task 2 PHPStan run on PublicPlayerData
- **Issue:** Two instances of nullsafe accessor on left side of `??`
- **Fix:** Extracted `$user = $player->user` then used explicit ternary
- **Files modified:** `app/Data/PublicPlayerData.php`
- **Commit:** 66f225a

**3. [Pint auto-fix] binary_operator_spaces, braces_position, no_unused_imports**
- **Found during:** Pint runs on PlayerPrivacyGate, PublicPlayerData, PlayerProfileDataTest
- **Fix:** Applied automatically by `./vendor/bin/pint`
- All style issues fixed, no logic changes

## Optional::create() Strategy

The "absent ≠ null" rule (T-02-03-01, RESEARCH.md Security Domain) is implemented via:

```php
// In PublicPlayerData constructor — union type required by PHP type system:
public Optional|string|null $discordTag,

// In fromPlayer() factory — gate decision:
$discordTag = $gate->allowsSection($player, $viewer, 'show_discord_tag')
    ? '@' . $player->user->username
    : Optional::create();  // ← spatie strips this from toArray() output
```

spatie/laravel-data `VisibleDataFieldsResolver::execute()` checks `$value instanceof Optional` and `unset($fields[$field])` before serialization. The TypeScript transformer treats `Optional` as `undefined` in union types.

## api.d.ts Diff Summary

New types added in `resources/js/types/api.d.ts`:
- `App.Data.ClanApplicationData`
- `App.Data.ClanData` (includes `App.Data.ClanTagData[]`)
- `App.Data.ClanInviteData`
- `App.Data.ClanMembershipData`
- `App.Data.ClanTagData`
- `App.Data.PublicPlayerData` (Optional fields surfaced as `undefined | T | null`)

## Test Results

| Test Suite | Passed | Failed | Notes |
|-----------|--------|--------|-------|
| PlayerPrivacyGateTest | 23 | 0 | Replaced Wave 0 RED stub |
| PlayerProfileDataTest | 11 | 0 | Replaced Wave 0 RED stub |
| Full suite (Unit) | 34 | 0 | All unit tests green |
| Full suite (all) | 113 | 9 | 9 remaining failures = pre-existing Wave 0 stubs for plans 02-06..02-13 |

## Known Stubs

The following `fromPlayer()` sections return `null` as placeholders (allowed per plan):
- `clanHistory` — full clan history list implemented in plan 02-07
- `matchHistory` — match history section is heading-only in P2
- `stats` — stats section is heading-only in P2

These are intentional placeholders. The `null` value means "section is present but has no data yet" — different from `Optional::create()` which means "section is withheld by privacy gate". This distinction is correct.

## Threat Surface Scan

No new network endpoints, auth paths, or schema changes introduced by this plan. All new code is service class + DTOs consumed by controllers implemented in later plans (02-07, 02-08).

## Self-Check: PASSED
