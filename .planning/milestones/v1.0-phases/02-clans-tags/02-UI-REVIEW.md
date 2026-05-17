---
phase: 2
slug: clans-tags
review_date: 2026-05-17
status: findings
overall_score: 2.8
pillar_scores:
  visual_hierarchy: 3
  consistency: 2
  accessibility: 3
  responsiveness: 4
  i18n: 2
  brand_alignment: 3
---

# Phase 2 — Clans & tags — UI Review (Retroactive)

**Audited:** 2026-05-17
**Baseline:** Phase 1 UI-SPEC (`.planning/milestones/v1.0-phases/01-foundations/01-UI-SPEC.md`) + Phase 2 verification (`02-PHASE-VERIFICATION.md`) + CLAUDE.md i18n contract (D-013)
**Screenshots:** not captured (retroactive code-only audit; localhost:8000 reachable but Phase 2 surface no longer matches Phase 2 boundaries — later phases mutated PublicLayout)
**Files audited:** 18 Vue files (4 pages, 1 layout, 5 clan/player components, 7 UI primitives, 1 UserMenu)

---

## 1. Executive Summary

Phase 2 is a **broadly competent first-pass implementation** of a non-trivial multi-page domain surface (directory + detail + 4-tab management UI + privacy-aware player profile). The token system is honoured almost everywhere: every color is `var(--color-…)`, no raw hex appears in any Vue file, and the focus-ring + dark-default contract from Phase 1 is preserved. Where it fails the contract, it does so quietly: a couple of typography sizes drift off-scale, the i18n net has visible holes around role select options and a clan-status badge, and several anchor tags break Inertia SPA navigation. None of these are showstoppers — manual smoke (Phase Verification items A–E) will probably still pass — but they degrade quality enough that a polish pass before v1.0 ship is warranted.

**Five most notable observations:**

1. **Hardcoded English in `MyClan/Index.vue:309-312`** — role `<option>` labels ("Leader", "Officer", "Member", "Recruit") bypass `t()` despite the keys (`common.role.*`) existing and being used elsewhere. Slips past `NoHardcodedStringsTest` because it scans Vue templates differently than these static option children.
2. **`Clans/Index.vue:81-90` — search input is invisible.** `class="sr-only"` is forwarded to the entire `TextInput` component root (not just its `<label>`), so the public clan directory's primary affordance is hidden from sighted users while UI-SPEC § Page `/clans` calls for a visible search field.
3. **Off-scale typography in `MyClan/Index.vue`** — `text-2xl` (24px, lines 161 & 230) and `text-lg` (18px, line 187) are not in the UI-SPEC's 4-size scale (Body/Label/Heading/Display = 16/14/20/28). Every other Phase 2 page uses `text-[28px]` arbitrary value to hit the Display size correctly; MyClan diverges silently.
4. **`<a href>` everywhere instead of Inertia `<Link>`** in ClanCard, MemberRow, PlayerCard, UserMenu, MyClan no-clan state. Each click is a full SSR page reload, killing shared-prop state (auth, notification bell) and adding a white flash. PublicLayout uses `<Link>` correctly — Phase 2 components don't.
5. **`StatusBadge` renders raw enum on suspended/disbanded clans** — `Clans/Show.vue:102-105` passes only `variant`; StatusBadge's fallback (`{{ label ?? variant }}`) then prints the literal word "suspended" / "disbanded" unlocalized. Visitor sees the DB enum, not localized copy. No `clans.status.*` keys exist in `lang/en/clans.php`.

---

## 2. Per-pillar findings

### Pillar 1 — Visual hierarchy: **3/4**

Strong default rhythm (consistent `max-w-3xl mx-auto px-4 md:px-6 py-8`, `gap-8` between sections, headings styled identically across pages) but several drift points.

- **`MyClan/Index.vue:161,187,230`** — Heading sizes are inconsistent: H1 uses `text-2xl` (24px) and H2 uses `text-lg` (18px), instead of Display (28px) and Heading (20px) from UI-SPEC § Typography. Same role, different visual weight than `Clans/Show.vue:78` and `Players/Show.vue:40`. **WARNING.**
- **`Clans/Index.vue:81-90`** — Search input `TextInput` receives `class="sr-only"` on its root. Result: no visible search field on the public clan directory; only the tag filter chips are visible. Manual operator smoke item A explicitly checks "Search filter input visible" — this will fail. **BLOCKER.**
- **`Players/Show.vue:67`** — Clan-history entries render via `{{ JSON.stringify(entry) }}` placeholder. Comment acknowledges this is deferred to Phase 3; until then, a logged-in player viewing their own history sees raw JSON. Acceptable as a stub but should be wrapped in a clear "deferred" empty state. **WARNING.**
- **No icons anywhere on Phase 2 surface** — `lucide-vue-next` is listed in UI-SPEC § Component Inventory but zero icon imports in any clan/player/MyClan file. Country code renders as raw `GB` / `US` text. Acceptable but text-heavy hierarchy; icons would help affordance.

### Pillar 2 — Consistency (Phase 1 design system alignment): **2/4**

Tokens are honoured, but several patterns drift from Phase 1.

- **Font size scale violation (Phase 1 § Typography "exactly 4 sizes, 2 weights — no exceptions")**: 6 distinct `text-*` sizes used across Phase 2 surface — `text-base`, `text-sm`, `text-xl`, `text-xs`, plus off-scale `text-lg` (18px) and `text-2xl` (24px) in `MyClan/Index.vue`. **BLOCKER for design contract** (Phase 1 sign-off depends on this rule). Files: `MyClan/Index.vue:161, 187, 230`.
- **Spacing off-scale**: UI-SPEC § Spacing Scale declares multiples of 4 only (xs/sm/md/lg/xl…). Phase 2 uses `gap-3` (12px) at `ClanCard.vue:45`, `Clans/Index.vue:78`, `MyClan/Index.vue:168,361,391,456`, and `gap-5` (20px) at `MyClan/Index.vue:237`. Tailwind v4 with `--spacing:0.25rem` *allows* these values; the design contract does not. **WARNING.**
- **Arbitrary value `text-[28px]`** used in 5 places to hit Display size — UI-SPEC sanctioned this ("`text-3xl` ≈ 30px; clamped to 28px") but the recurring `text-[28px] leading-[1.2] tracking-tight` block (`Home.vue:27,36`, `Clans/Index.vue:70`, `Clans/Show.vue:78`, `PlayerCard.vue:47`) should be extracted to a `<DisplayHeading>` primitive to avoid drift. **WARNING.**
- **`MemberRow.vue:46-48`** — last row of the list shows a `border-b` (no `:last-child:border-b-0`). Pair this with `Clans/Show.vue:123` which wraps the rows in `overflow-hidden border` — the wrapper's bottom border + the last child's bottom border render fine because of `overflow-hidden`, but every member row inside MyClan's `border rounded-lg overflow-hidden` (`MyClan/Index.vue:291`) has the same redundancy. Cosmetic but a minor consistency tell.

### Pillar 3 — Accessibility: **3/4**

Solid foundation (`role="status"` on empty states, `role="group"` on tag filter, `aria-pressed` on filter pills, `aria-hidden="true"` on decorative initial-avatars and dots, focus rings present via global CSS) with two notable misses.

- **`MyClan/Index.vue:306`** — Role `<select>` `aria-label` is `t('clans.members.role.update.success')`. That key is a success-toast string ("Member role updated."), not a control label. Screen readers will announce the select's purpose as a past-tense success message. Use a dedicated `clans.members.role.aria_label` (or reuse the column header). **WARNING.**
- **`TabGroup.vue:31`** — `<TabsList aria-label="Tabs">` is a hardcoded English string. Also a D-013 i18n contract miss (see Pillar 5). **WARNING.**
- **`PlayerCard.vue:71`** — current-clan link fallback label uses `t('common.nav.clans')` (which renders "Clans" — the nav-item label). Semantically wrong: this should be a player-clan name fallback, not a navigation label. Screen reader hears "link, Clans" instead of the clan name. **WARNING.**
- **`UserMenu.vue:91`** — "My Profile" menu item points to `href="#"` (TODO comment notes Phase 9 wiring). A `#` link inside a `DropdownMenuItem` is reachable by keyboard and screen reader as an active link going nowhere — it should be a `disabled` item, not a live anchor. **WARNING.**
- **Inline remove confirm (`MyClan/Index.vue:316-345`)** — when remove is clicked, a confirm-message + 2 buttons append inline. No focus management: focus stays on the now-replaced "Remove" trigger. Screen reader users may not realize a confirmation appeared. Recommend `v-focus` on the "Yes" button when `confirmingRemoveId === member.id`. **WARNING.**
- **Strong wins**: every form control via `TextInput` / `Textarea` correctly wires `aria-describedby`, `aria-invalid`, and `role="alert"` on error text (`TextInput.vue:42-47`). Empty states all have `role="status"`. Decorative country-separator dot uses `aria-hidden`.

### Pillar 4 — Responsiveness: **4/4**

Mobile-first is executed competently across every Phase 2 page.

- Each page uses `max-w-3xl mx-auto px-4 md:px-6 py-8` as a consistent shell — no horizontal scroll at 360px on any audited file.
- `Clans/Index.vue:159` — clan grid stacks `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6` correctly.
- `PlayerCard.vue:32-42` and `Clans/Show.vue:64-67` use `flex-col sm:flex-row` with `w-14 h-14 sm:w-16 sm:h-16` avatar — clean mobile-stack to desktop-row pivot.
- `TabGroup.vue:30` — `overflow-x-auto` on the tab list handles narrow viewports correctly.
- `PublicLayout.vue:52,120` — nav links and search bar both `hidden md:flex`; mobile gets the wordmark + theme/auth controls only (UI-SPEC § Responsive Breakpoints § Mobile nav note). Mobile-nav drawer is explicitly deferred.
- No fixed-width pixel values; everything uses Tailwind responsive utilities. Tailwind v4 default breakpoints (`sm/md/lg/xl`) unmodified.

### Pillar 5 — i18n (D-013): **2/4**

Tests pass but the contract has real holes — including a hardcoded English literal in a public-facing form.

- **`MyClan/Index.vue:309-312`** — `<option value="leader">Leader</option>` (× 4) — raw English labels in `<option>` children. Keys `common.role.leader|officer|member|recruit` exist and are correctly used in `ClanRoleBadge.vue`. Pure oversight. **BLOCKER for D-013** (which CLAUDE.md § 7 says is a CI failure — yet the test passed; the static scanner likely misses option-tag children).
- **`MyClan/Index.vue:264`** — `:placeholder="'e.g. GB'"` — hardcoded country-code placeholder. Should be `t('clans.form.country.placeholder')`. **BLOCKER for D-013.**
- **`TabGroup.vue:31`** — `aria-label="Tabs"` hardcoded. **WARNING for D-013** (aria-label is user-visible text).
- **`StatusBadge.vue:84`** — fallback `{{ label ?? variant }}` prints the raw English variant (`"suspended"`, `"disbanded"`, `"pending"`) when no `label` is passed. **`Clans/Show.vue:102-105`** uses StatusBadge without a `label` for clan status → suspended/disbanded clans render the English DB enum on the public page. **WARNING** (depends on data: rare in practice; high impact when it hits).
- **`MyClan/Index.vue:368`** — `<StatusBadge variant="pending" :label="invite.status" />` — `invite.status` is the raw DB enum string ("pending"/"accepted"/"rejected"/"expired"), not a localized label. **WARNING.**
- **`PlayerCard.vue:71`** — uses `t('common.nav.clans')` as a fallback string (renders "Clans") for a clan-name field. i18n key exists but is semantically wrong (see Pillar 3). **WARNING.**
- **Strong wins**: every other visible string flows through `t()`. Form labels, button text, headings, empty states, privacy notices, pluralization (`clans.members.count_one|other`), placeholder hints (apart from line 264) — all clean.

### Pillar 6 — Brand alignment: **3/4**

Visual aesthetic lands close to the Phase 1 "trench-military" target without falling into kitsch. Dark default holds, deep-red accent appears sparingly (only on active nav link's `border-l-[3px]`, primary buttons, and leader-role / selected-tag borders), `font-mono` is correctly reserved for tag labels and Discord IDs.

- **Accent usage discipline**: `grep -c bg-\[var\(--color-accent\)\]` across Phase 2 surface = 1 (Button.vue primary variant). `border-[var(--color-accent)]` = 7 (4 nav-active states in PublicLayout, leader role badge, selected tag, hover on clan card). All matches UI-SPEC § Color "Accent reserved for" allowlist. **Strong.**
- **`ClanCard.vue:47`** — `hover:border-[var(--color-accent)]` on whole-card hover is the closest call. UI-SPEC § Color says accent is NOT for "hover states on cards (use surface-elevated lift instead)". `ClanCard` does both: `hover:bg-[var(--color-surface-elevated)] hover:border-[var(--color-accent)]`. The border-accent on hover technically breaks the contract. **WARNING.**
- **`Modal.vue:40`** — `bg-black/60` backdrop is the only non-token color in Phase 2 Vue files, but UI-SPEC explicitly sanctions modal backdrops in `bg-black/60`. **PASS.**
- **Wordmark + monospace tag chips + olive surfaces** consistent across the surface. No emoji in copy (per UI-SPEC § Voice & tone).
- **No per-clan accent override is wired** — `Clans/Show.vue:60` has the forward-compat `<div :style="{}">` wrapper hook ready for Phase 3+, consistent with UI-SPEC § Forward-compat hooks. **Strong.**

---

## 3. Quick wins (<1h each, lift pillar scores meaningfully)

| # | Fix | File:Line | Lifts |
|---|-----|-----------|-------|
| QW-1 | Move `class="sr-only"` from `<TextInput>` root to the inner `<label>`, OR drop `sr-only` and add a visible label "Search clans". | `Clans/Index.vue:81-90` | Visual hierarchy → 4 |
| QW-2 | Replace 4 `<option>` children with `:value=role` + `{{ t(\`common.role.${role}\`) }}` via `v-for` over a roles array. | `MyClan/Index.vue:309-312` | i18n → 3 |
| QW-3 | Replace `:placeholder="'e.g. GB'"` with `:placeholder="t('clans.form.country.placeholder')"` and add the key to `lang/en/clans.php`. | `MyClan/Index.vue:264` | i18n → 3 |
| QW-4 | Add `clans.status.{active,suspended,disbanded}` keys; pass `:label="t(\`clans.status.${clan.status}\`)"` in `Clans/Show.vue`. Same for invite status in `MyClan/Index.vue:368`. | `Clans/Show.vue:102-105`, `MyClan/Index.vue:368` | i18n → 3, brand → 4 |
| QW-5 | Switch `text-2xl` and `text-lg` headings in `MyClan/Index.vue` to the canonical `text-[28px]` (Display) and `text-xl` (Heading) so the typography scale matches the rest of Phase 2 pages. | `MyClan/Index.vue:161,187,230` | Consistency → 3 |
| QW-6 | Add `clans.members.role.aria_label` to `lang/en/clans.php`; reference it in the role `<select>` aria-label instead of the success-toast key. | `MyClan/Index.vue:306` | Accessibility → 4 |
| QW-7 | Replace `aria-label="Tabs"` with `:aria-label="t('common.tabs.label')"` (or pass through `TabGroup` props). | `TabGroup.vue:31` | i18n → 3, accessibility → 4 |

---

## 4. v1.1 polish backlog (non-blocking)

| # | Item | Files |
|---|------|-------|
| P1.1-01 | Convert raw `<a href>` clan-card / member-row / player-card / UserMenu links to Inertia `<Link>` so navigations preserve shared props (auth, notification bell, locale). PublicLayout already uses `<Link>`; Phase 2 components do not. | `ClanCard.vue:44`, `MemberRow.vue:63`, `PlayerCard.vue:67`, `UserMenu.vue:78,91`, `MyClan/Index.vue:170` |
| P1.1-02 | Extract recurring `font-sans font-semibold text-[28px] leading-[1.2] tracking-tight` block into a `<DisplayHeading>` primitive so future phases can't drift. | 5 sites |
| P1.1-03 | Extract recurring inline member-list row container (`border rounded-lg overflow-hidden` + `MemberRow` v-for) into a `<MemberList>` wrapper that handles last-child border removal. | `Clans/Show.vue:123-130`, `MyClan/Index.vue:291-348` |
| P1.1-04 | Add focus management on inline remove-confirm: move focus to the "Yes" button when `confirmingRemoveId` flips; announce the confirmation via `role="alertdialog"`-like pattern. | `MyClan/Index.vue:316-345` |
| P1.1-05 | Replace `JSON.stringify(entry)` clan-history placeholder with a Phase 3-deferred empty state ("Clan history rendering ships in Phase 3"). | `Players/Show.vue:67` |
| P1.1-06 | Tighten icon affordances: add a `MapPin` next to country code, a small `Users` glyph next to member count, `Search` inside the search input, `Plus` on Invite/Create CTAs. Currently the surface is text-only. | `ClanCard.vue`, `Clans/Show.vue`, `Clans/Index.vue` |
| P1.1-07 | "My Profile" UserMenu item with `href="#"` should be disabled (or hidden) until Phase 9 wires it up. | `UserMenu.vue:88-100` |
| P1.1-08 | `TabGroup.vue` does not localize the `aria-label="Tabs"` and gives no API to override it. Add a `:aria-label` prop. | `TabGroup.vue:14-24,31` |
| P1.1-09 | `gap-3` and `gap-5` off-scale spacing — settle on `gap-4` (16px) and `gap-6` (24px) per UI-SPEC § Spacing Scale, or amend the scale to allow 12px/20px and document the exception. | 7 sites in MyClan + Clans/Index + ClanCard |

---

## 5. Verified clean ✓

- **All colors are semantic tokens.** Grep for raw `#hex` / `rgb()` / `bg-(red|gray|…)` returns zero matches across all 18 audited files (except sanctioned `bg-black/60` Modal backdrop).
- **Dark default works.** No file overrides `data-theme=light` selectors; tokens flip cleanly via `:root` + `[data-theme=light]` in `app.css`.
- **Focus rings everywhere.** Every interactive control has either inline `focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]` or inherits from the global `*:focus-visible` rule in `app.css:115-119`.
- **Form a11y.** Every `TextInput`/`Textarea`/`Select` correctly wires `aria-describedby`, `aria-invalid`, `role="alert"`, required-asterisk with `aria-hidden`.
- **Pluralization.** Member counts use `clans.members.count_one|other` per i18n best practice.
- **Privacy "absent ≠ null" contract** (T-02-08-02) — `Players/Show.vue:46,55,74,88` correctly v-if on field presence, not on a privacy flag. Same in `PlayerCard.vue:53,60,65`.
- **No `v-html`.** Plain-text rendering only (T-02-08-01), correctly preserved across descriptions and bios.
- **Reka UI primitives unmodified.** `TabsRoot/List/Trigger/Content`, `DialogRoot/Portal/Overlay/Content/Title/Description/Close`, `DropdownMenuRoot/Trigger/Content/Item` all used as documented — no ARIA stripping.
- **Per-clan accent forward-compat hook present** at `Clans/Show.vue:60` (`<div :style="{}">` wrapper, ready for Phase 3+).
- **Skip link, locale switcher slot, wordmark, theme toggle, notifications bell, login/user menu auth-pair** — all PublicLayout chrome from Phase 1 + later phases is wired and functional in Phase 2 surface.
- **vue-tsc / Pest / Pint / PHPStan L8 all PASS** per `02-PHASE-VERIFICATION.md` — no static-quality regressions.
- **Already addressed in code review (not re-flagged):**
  - PlayerCard.vue double-`@` rendering (CR-01)
  - PlayerCard.vue `currentClan` UUID-instead-of-slug link (CR-02)

---

## Files Audited

1. `apps/web/resources/js/pages/Clans/Index.vue` (171 lines)
2. `apps/web/resources/js/pages/Clans/Show.vue` (173 lines)
3. `apps/web/resources/js/pages/Players/Show.vue` (116 lines)
4. `apps/web/resources/js/pages/MyClan/Index.vue` (474 lines)
5. `apps/web/resources/js/pages/Home.vue` (49 lines)
6. `apps/web/resources/js/layouts/PublicLayout.vue` (148 lines)
7. `apps/web/resources/js/components/clans/ClanCard.vue` (88 lines)
8. `apps/web/resources/js/components/clans/ClanRoleBadge.vue` (32 lines)
9. `apps/web/resources/js/components/clans/ClanTagBadge.vue` (51 lines)
10. `apps/web/resources/js/components/clans/MemberRow.vue` (82 lines)
11. `apps/web/resources/js/components/players/PlayerCard.vue` (76 lines)
12. `apps/web/resources/js/components/ui/StatusBadge.vue` (85 lines)
13. `apps/web/resources/js/components/ui/TabGroup.vue` (57 lines)
14. `apps/web/resources/js/components/ui/Modal.vue` (80 lines)
15. `apps/web/resources/js/components/ui/TextInput.vue` (49 lines)
16. `apps/web/resources/js/components/ui/Textarea.vue` (49 lines)
17. `apps/web/resources/js/components/ui/Select.vue` (51 lines)
18. `apps/web/resources/js/components/UserMenu.vue` (117 lines)

**Total audited:** 2,048 lines across 18 files.

**Reference docs consulted:**
- `CLAUDE.md` § 7 (i18n / D-013)
- `.planning/milestones/v1.0-phases/01-foundations/01-UI-SPEC.md` (token + typography + spacing + color contract)
- `.planning/milestones/v1.0-phases/02-clans-tags/02-PHASE-VERIFICATION.md`
- `apps/web/resources/css/app.css` (theme tokens)
- `apps/web/lang/en/{common,clans}.php` (i18n key existence checks)
