---
phase: 02-clans-tags
plan: "06"
subsystem: services + i18n + vue-primitives
tags: [slug-generator, i18n, vue-components, reka-ui, tailwind-v4]
dependency_graph:
  requires:
    - 02-05  # DTOs + privacy gate (ClanData, ClanTagData types referenced by components)
    - 02-03  # Clan model (ClanSlugGenerator queries Clan.slug)
  provides:
    - ClanSlugGenerator service (used by 02-09 FormRequest + 02-07 ClanCreateController)
    - lang/en/clans.php (all clan UI keys for 02-08 Vue pages)
    - lang/en/players.php (player profile keys for 02-08)
    - lang/en/common.php nav + role keys (for all Phase 2 Vue pages)
    - lang/en/admin.php clan resource labels + audit subjects (for 02-12 Filament resources)
    - 8 Vue primitives: StatusBadge, TabGroup, Modal, TextInput, Textarea, Select, ClanTagBadge, ClanRoleBadge
  affects: []
tech_stack:
  added: []
  patterns:
    - collision-aware slug generation (Str::slug + reserved check + -2/-3 suffix loop)
    - config-driven reserved-slug list (T-02-06-01 mitigation)
    - Reka UI Dialog wrapper (focus trap built-in; T-02-06-03 mitigation)
    - Reka UI Tabs wrapper (arrow-key nav built-in)
    - defineModel Vue 3.4 pattern for form inputs
    - color-mix() inline style for opacity badge backgrounds (Tailwind v4 CSS variable limitation)
    - computed hasError + errorId pattern to avoid NoHardcodedStringsTest false positives
key_files:
  created:
    - apps/web/config/clan.php
    - apps/web/app/Services/ClanSlugGenerator.php
    - apps/web/app/Exceptions/ReservedSlugException.php
    - apps/web/lang/en/clans.php
    - apps/web/lang/en/players.php
    - apps/web/resources/js/components/ui/StatusBadge.vue
    - apps/web/resources/js/components/ui/TabGroup.vue
    - apps/web/resources/js/components/ui/Modal.vue
    - apps/web/resources/js/components/ui/TextInput.vue
    - apps/web/resources/js/components/ui/Textarea.vue
    - apps/web/resources/js/components/ui/Select.vue
    - apps/web/resources/js/components/clans/ClanTagBadge.vue
    - apps/web/resources/js/components/clans/ClanRoleBadge.vue
  modified:
    - apps/web/lang/en/common.php
    - apps/web/lang/en/admin.php
decisions:
  - ClanSlugGenerator is stateless; reserved-slug list lives in config/clan.php not hardcoded in service
  - ReservedSlugException declared as separate file in app/Exceptions/ (plan option was same-file or separate — separate chosen for PSR-4 discoverability)
  - common.php extended with nav + role keys; role keys referenced by ClanRoleBadge via t('common.role.*')
  - common.actions.close added to common.php to satisfy Modal.vue IconButton label (Rule 2 — missing critical key)
  - Select uses native <select> per UI-SPEC "use native for P2" directive; no Reka UI SelectRoot
  - StatusBadge: pending/public/private use color-mix() inline style; cannot use Tailwind opacity modifier with CSS variables in v4
  - TextInput/Textarea/Select refactored to computed hasError + errorId to avoid test false positives from ternaries containing '>'
metrics:
  duration: "~5 minutes"
  completed: "2026-05-12"
  tasks_completed: 3
  files_changed: 15
---

# Phase 2 Plan 06: ClanSlugGenerator + i18n + Vue UI Primitives Summary

Collision-aware slug service, full i18n key set, and 8 Vue primitives ready for Wave 3 consumer pages.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | ClanSlugGenerator service + config/clan.php | 68af3a3 | config/clan.php, app/Services/ClanSlugGenerator.php, app/Exceptions/ReservedSlugException.php |
| 2 | i18n key files | 32d7007 | lang/en/clans.php, lang/en/players.php, lang/en/common.php, lang/en/admin.php |
| 3 | 8 Vue components | 18b5ec2 | 6 ui/ + 2 clans/ components |

## ClanSlugGenerator Algorithm

```
generate(name: string) -> string
  1. base = Str::slug(name)         -- empty name throws InvalidArgumentException
  2. if isReserved(base)            -- throws ReservedSlugException (T-02-06-01)
  3. slug = base; i = 2
  4. while Clan.where('slug', slug).exists()
       slug = base + '-' + i++
  5. return slug
```

Reserved list (9 words): `admin`, `me`, `api`, `clans`, `players`, `my-clan`, `login`, `logout`, `health`

`isReserved()` reads `config('clan.reserved_slugs')` — list is config-driven, not hardcoded.

## i18n Key Count

| File | Keys added | Action |
|------|-----------|--------|
| `lang/en/clans.php` | 40 keys across 13 top-level namespaces | NEW |
| `lang/en/players.php` | 7 keys across 4 top-level namespaces | NEW |
| `lang/en/common.php` | 9 keys (nav x4, role x4, actions.close x1) | EXTENDED |
| `lang/en/admin.php` | ~35 keys (5 resource label/plural_label, 5 audit.subject, per-resource fields) | EXTENDED |

## Vue Component API Summary

| Component | Props | Reka UI | Notes |
|-----------|-------|---------|-------|
| `StatusBadge` | `variant: Variant`, `label?: string` | None | 12 variants; color-mix() inline style for opacity variants |
| `TabGroup` | `tabs: Tab[]`, `defaultValue?: string` | TabsRoot/List/Trigger/Content | Slot per tab.value |
| `Modal` | `open: boolean`, `title: string`, `description?: string` + emit `update:open` | DialogRoot/Portal/Overlay/Content | Focus trap built-in (T-02-06-03) |
| `TextInput` | `label, id, type?, placeholder?, required?, errors?` + defineModel | None | hasError computed avoids test false positives |
| `Textarea` | `label, id, rows?, placeholder?, required?, errors?` + defineModel | None | resize-vertical |
| `Select` | `label, id, options: SelectOption[], required?, errors?` + defineModel | None | Native `<select>` per UI-SPEC |
| `ClanTagBadge` | `tag: ClanTagData`, `selected?: boolean`, `as?: 'span'|'button'` | None | aria-pressed when as=button |
| `ClanRoleBadge` | `role: 'leader'|'officer'|'member'|'recruit'` | None | t('common.role.*') for label; leader gets accent border |

## Deviations from Plan

### Auto-added Missing Critical Functionality

**1. [Rule 2 - Missing Key] Added `common.actions.close` to common.php**
- **Found during:** Task 3 (Modal.vue requires `t('common.actions.close')` for the close IconButton aria-label)
- **Issue:** Plan's Task 3 acceptance criteria reference `t('common.actions.close')` but this key was not in the original Task 2 scope for common.php additions
- **Fix:** Added `actions.close = 'Close'` to common.php during Task 2 extension
- **Files modified:** apps/web/lang/en/common.php
- **Commit:** 32d7007

**2. [Rule 1 - Bug] TextInput/Textarea/Select template refactor to fix NoHardcodedStringsTest false positives**
- **Found during:** Task 3 verification
- **Issue:** Multi-line attribute bindings containing `errors.length > 0` caused the test's `/>([^<]{3,})<` regex to match from `>` in the comparison to the next `<`, producing false-positive "hardcoded string" findings
- **Fix:** Extracted `hasError` and `errorId` computed properties; moved multi-line class attributes to single-line; error `<p>` content on same line as tags
- **Files modified:** TextInput.vue, Textarea.vue, Select.vue
- **Commit:** 18b5ec2 (fixed before commit)

## Known Stubs

None. This plan delivers scaffolding only; no data rendering stubs.

## Threat Flags

No new security-relevant surface beyond what the plan's threat model covers.

## Self-Check: PASSED
