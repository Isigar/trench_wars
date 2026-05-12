---
phase: 2
slug: clans-tags
status: draft
shadcn_initialized: false
preset: not applicable
created: 2026-05-12
---

# Phase 2 — UI Design Contract

> Visual and interaction contract for Phase 2: Clans & tags. This spec **extends** the Phase 1 contract (`01-UI-SPEC.md`); all tokens, typography, color, spacing, motion, and accessibility rules from Phase 1 are inherited in full. Only the delta — new surfaces, new components, new copywriting — is specified here. Executors MUST read the Phase 1 spec first.

> **Note on `shadcn`:** Not applicable. Vue 3 + Inertia stack; shadcn is React-only. Design system is owned primitives on Reka UI + Tailwind v4 CSS-first tokens. Same as Phase 1.

---

## Design System

| Property | Value | Source |
|----------|-------|--------|
| Tool | none (custom token system; Tailwind v4 CSS-first) | Phase 1 01-UI-SPEC.md |
| Preset | not applicable (Vue stack) | Phase 1 01-UI-SPEC.md |
| Component library | Reka UI (Vue port of Radix UI) — headless, accessible primitives in `resources/js/components/ui/` | Phase 1 01-UI-SPEC.md |
| Icon library | `lucide-vue-next` | Phase 1 01-UI-SPEC.md |
| Font (UI) | Inter Variable — self-hosted via `@fontsource-variable/inter` | Phase 1 01-UI-SPEC.md |
| Font (mono) | JetBrains Mono Variable — used for clan tags, slugs, IDs | Phase 1 01-UI-SPEC.md |
| Token authoring | `resources/css/app.css` `@theme` block — no new tokens required in P2; all semantic tokens inherited | Codebase scan 2026-05-12 |
| Theme switching | `data-theme` on `<html>`; dark default, light option — unchanged | Phase 1 01-UI-SPEC.md |

### No new CSS tokens

Phase 2 introduces no new `--color-*`, `--spacing`, or `--radius` tokens. All values come from the Phase 1 `@theme` block (already in `apps/web/resources/css/app.css`). Per the Phase 1 forward-compat hook, per-clan accent override (`--color-accent` scoped on a wrapper div) is declared here as a **pattern** but the actual color value comes from `clans.accent_color` data — not a hardcoded token.

#### Per-clan accent override pattern (Phase 2 activation)

Clan-scoped pages (`/clans/{slug}`, My Clan) wrap their content in:

```html
<div :style="clan.accent_color ? { '--color-accent': clan.accent_color } : {}">
  <!-- clan-specific content -->
</div>
```

If `clan.accent_color` is null, the league default accent (`#A4262C`) is used unchanged. This is the forward-compat hook declared in Phase 1 — Phase 2 activates it. No clan accent color is surfaced in the P2 UI (not in the clan creation or edit form) because the `clans` table column is noted as a future addition; the hook must be in the template but the data field is null for all P2-era clans.

---

## Spacing Scale

**Inherited from Phase 1 without changes.**

| Token | Value | Tailwind utility | Usage |
|-------|-------|------------------|-------|
| xs | 4px | `gap-1`, `p-1` | Icon gaps, tag internal padding |
| sm | 8px | `gap-2`, `p-2` | Between label and control, compact rows |
| md | 16px | `gap-4`, `p-4` | Default element spacing, list items |
| lg | 24px | `gap-6`, `p-6` | Section padding, card internal padding |
| xl | 32px | `gap-8`, `p-8` | Layout gaps between sections |
| 2xl | 48px | `gap-12`, `p-12` | Major section breaks |
| 3xl | 64px | `gap-16`, `p-16` | Page-level top/bottom rhythm |

**Phase 2 exception:** None beyond the Phase 1 exception (44px touch targets on icon-only buttons).

**Phase 2 grid note:** Clan directory `/clans` uses a card grid. Grid gaps use `gap-4` (16px) at mobile, `gap-6` (24px) at `md+`. Max content width remains `max-w-3xl` (same as Home and Phase 1 layout contract). Clan detail and player profile pages use the same `max-w-3xl` centered container.

---

## Typography

**Inherited from Phase 1 — exactly 4 sizes, 2 weights. No new sizes or weights added.**

| Role | Size | Weight | Line Height | Usage in P2 |
|------|------|--------|-------------|-------------|
| Body | 16px (`text-base`) | 400 | 1.5 | Clan description, member bio excerpt, player bio, empty-state body |
| Label | 14px (`text-sm`) | 600 | 1.4 | Tag badges, member role badge, form labels, table headers, nav items |
| Heading | 20px (`text-xl`) | 600 | 1.3 | Clan card name, section headers (Members, Tags, Recent Activity), dialog titles (invite, remove) |
| Display | 28px | 600 | 1.2 | Clan detail page H1 (clan name), Player profile page H1 (display name / username) |

**Mono usage in P2 (`font-mono`):**
- Clan tag badge, e.g., `EU`, `NA`, `Tier-1` — Label size (14px), font-mono, weight 600
- Clan slug in admin Filament table — same as Phase 1 slug column pattern
- Player slug column in admin — already established in Phase 1

**i18n typography note (D-013):** +30% horizontal slack on all buttons and badges — clan tag labels may be localised in the `clan_tags.label` jsonb column; the rendered badge must tolerate "Nordeuropa" as a value without truncation (use `overflow-hidden text-ellipsis` only as absolute last resort; prefer wrapping where possible).

---

## Color

**Inherited from Phase 1 — all semantic tokens unchanged.**

| Role | Token | Dark hex | Light hex | P2 usage |
|------|-------|----------|-----------|----------|
| Dominant (60%) | `--color-bg` | `#1A1B16` | `#F5F2E6` | Page background |
| Secondary (30%) | `--color-surface` | `#232518` | `#FFFFFF` | Clan cards, member list rows, sidebar panels |
| Secondary-elevated | `--color-surface-elevated` | `#2C2E20` | `#FFFFFF` | Invite modal, confirm-remove dialog, dropdowns |
| Accent (10%) | `--color-accent` | `#A4262C` | `#8E1E22` | See "Accent reserved for P2" below |
| Destructive | `--color-danger` | `#C03A2B` | `#A6271A` | Remove member button, disband clan confirmation |
| Success | `--color-success` | `#6B8E3D` | `#4F6E25` | Active membership status badge |
| Warning | `--color-warning` | `#C8932A` | `#9C7220` | Pending invite / application badge |

### Accent reserved for in Phase 2 (explicit list)

Accent (`var(--color-accent)`) is used **only** for:

1. **Primary CTA button fill** — "Create clan" on the directory page (logged-in, no clan), "Send invite" submit on the invite modal, "Save changes" on My Clan edit form.
2. **Active nav item** — left-border 3px accent strip on the active nav link (Clans / Players), consistent with Phase 1's nav contract.
3. **Clan tag badge border** — 1px border in `var(--color-accent)` on selected/applied filter tags on `/clans`; unselected tags use `--color-border`.
4. **Filament primary** — inherited; no additional uses in new Filament resources.
5. **Leader role badge border** — `1px border var(--color-accent)` on `ClanRoleBadge` when `role=leader` (semantic: Leader is the elevated authority on the clan; the accent border distinguishes them at a glance without a separate color).
6. **Invite modal selected search result border** — `border border-[var(--color-accent)]` on the highlighted/selected result item in the player search list (semantic: keyboard navigation focus indicator within the search results list, distinct from the global focus ring).

Accent is **NOT** used for:
- Clan cards hover state (use `--color-surface-elevated` lift)
- Player profile links
- Tag badges in the "unselected" state on the directory filter bar
- Privacy tier badges (use semantic status colors or `--color-text-muted`)
- Role badges for Officer/Member/Recruit — use `--color-surface-elevated` bg + `--color-text-muted` fg

### Status pill color assignments

| Status value | Bg token | Text token | Context |
|---|---|---|---|
| `active` (clan status) | `--color-success` | `--color-accent-fg` | Clan detail, admin table badge |
| `suspended` | `--color-warning` | `--color-accent-fg` | Admin only |
| `disbanded` | `--color-danger` | `--color-accent-fg` | Admin only |
| `Leader` (member role) | `--color-surface-elevated` + 1px `--color-accent` border | `--color-text` | Member roster row |
| `Officer` | `--color-surface-elevated` | `--color-text` | Member roster row |
| `Member` | `--color-surface-elevated` | `--color-text-muted` | Member roster row |
| `Recruit` | `--color-surface-elevated` | `--color-text-muted` | Member roster row |
| `pending` (invite/application) | `--color-warning` at 20% opacity bg | `--color-warning` text | My Clan invite list |
| `public` (privacy tier) | `--color-success` at 20% opacity bg | `--color-success` text | Player profile (own view) |
| `community` | `--color-surface-elevated` | `--color-text-muted` | Player profile (own view) |
| `clan` | `--color-surface-elevated` | `--color-text-muted` | Player profile (own view) |
| `private` | `--color-danger` at 20% opacity bg | `--color-danger` text | Player profile (own view) |

**Implementation note on opacity badges:** In Tailwind v4 with CSS variables, use inline `style` for the semi-transparent bg: `style="background-color: color-mix(in srgb, var(--color-warning) 20%, transparent)"` — do not hardcode hex alpha values.

---

## Layout & Interaction Contract — New P2 Surfaces

### Shared layout note

All P2 public pages use `PublicLayout.vue` (from Phase 1). The `<slot name="nav">` in the header is **populated in Phase 2** with navigation links: Clans and Players. Nav items are `<a>` rendered as Label (14px, 600) text with the active-link accent strip (3px left border on desktop, underline on mobile).

Nav structure in header (populated in P2):
```
Trenchwars [Clans] [Players]          [theme] [auth]
```

Auth slot: when logged in, a `UserMenu` component (avatar + username + dropdown with "My Clan", "My Profile", "Log out"). When logged out, the `LoginButton` from Phase 1.

---

### Page: `/clans` (Public clan directory)

```
┌──────────────────────────────────────────────────────────────┐
│  PublicLayout header + nav                                   │
│  ──────────────────────────────────────────────────────────  │
│  Container max-w-3xl, px-4 md:px-6, py-8                    │
│                                                              │
│  H1 Display "Clans" (28px, 600)                              │
│  Sub-copy Body-muted "Browse and discover league clans."     │
│  gap-4 vertical                                              │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Filter bar: [search input]  [tag pills] [clear]     │   │
│  └──────────────────────────────────────────────────────┘   │
│  gap-6 vertical                                              │
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                  │
│  │ ClanCard │  │ ClanCard │  │ ClanCard │                   │
│  └──────────┘  └──────────┘  └──────────┘                  │
│  (responsive grid: 1 col mobile, 2 col sm+, 3 col lg+)      │
│                                                              │
│  [empty state — see copy below]                             │
│  Two variants: filtered zero-results vs. no clans at all    │
│                                                              │
│  ── or when logged in + no active clan ──────────────────── │
│  [ Create your clan ]   ← primary CTA, accent fill          │
│                                                              │
│  Footer                                                      │
└──────────────────────────────────────────────────────────────┘
```

**Filter bar:**
- Search: `<input type="search">` with Label size placeholder, `border border-[var(--color-border)]`, `bg-[var(--color-surface)]`, `rounded-md`, height 40px (h-10, matches `Button size="md"`). Full-width at mobile, max-width 320px at md+.
- Tag filter pills: horizontal scroll row (overflow-x-auto, no scrollbar) of `ClanTagBadge` components. Selected tag gets accent border (`border-[var(--color-accent)]`); unselected gets `border-[var(--color-border)]`.
- Clear link: ghost text `t('clans.filter.clear')` shown only when any filter is active. Label size, muted color.
- Filter is client-side on the initial page data; deep-linking via query string `?tag=eu&q=search-term` is supported via Inertia `router.get`.

**Empty state — two cases:**
- **Filtered zero results** (search/tag active, no matches): render `role="status"` container with Body text `t('clans.directory.empty_results')`. Show the Clear filters link below the copy.
- **Default zero clans** (no clans exist in the system, no filters active): render `role="status"` container with Body text `t('clans.directory.empty_default')`.

**ClanCard component:**
```
┌─────────────────────────────────────────┐
│  [Avatar 48×48 rounded-lg]              │
│  Clan Name (Heading 20px, 600)          │
│  [EU] [Tier-1]  ← ClanTagBadge(s)      │
│  Body-muted: first 80 chars description │
│  N members · Country flag emoji (if set)│
└─────────────────────────────────────────┘
```
- Card bg: `bg-[var(--color-surface)]`, `rounded-lg`, `p-4` (16px), border `border border-[var(--color-border)]`.
- Hover: `hover:bg-[var(--color-surface-elevated)]` + `hover:border-[var(--color-accent)]` — no box-shadow.
- Entire card is a `<a href="/clans/{slug}">` link.
- Avatar placeholder: clan initials in a `div` with `bg-[var(--color-surface-elevated)]` and `--color-text-muted` text at 20px, semibold — same treatment as Discord avatar placeholder from Phase 1.
- Member count: Body size (16px), weight 400, muted color. "N members" — use `t('clans.members.count', { count: N })` with pluralization.
- Description truncated to 80 characters with "…" suffix; no `line-clamp` — explicit JS truncation from the DTO.

---

### Page: `/clans/{slug}` (Public clan detail)

```
┌──────────────────────────────────────────────────────────────┐
│  PublicLayout header + nav                                   │
│  ──────────────────────────────────────────────────────────  │
│  Container max-w-3xl, px-4 md:px-6, py-8                    │
│                                                              │
│  ┌── Clan hero block ─────────────────────────────────────┐ │
│  │  [Avatar 64×64 rounded-lg]  Clan Name (H1 Display)     │ │
│  │                             [EU] [Tier-1]  tag badges   │ │
│  │                             N members · Country         │ │
│  │                             [Active] status badge        │ │
│  └────────────────────────────────────────────────────────┘ │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Description section ─────────────────────────────────┐ │
│  │  Body 16px, --color-text (full description, no truncate)│ │
│  └────────────────────────────────────────────────────────┘ │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Members section ─────────────────────────────────────┐ │
│  │  Heading "Members" (20px, 600)                          │ │
│  │  Roster list (visible if show_clan_history permits)     │ │
│  │  ┌─ MemberRow ──────────────────────────────────────┐  │ │
│  │  │ [Avatar 32×32]  Display name  [Leader] badge      │  │ │
│  │  └──────────────────────────────────────────────────┘  │ │
│  │  [privacy-blocked message if show_to tier hides roster] │ │
│  └────────────────────────────────────────────────────────┘ │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Recent activity (placeholder) ───────────────────────┐ │
│  │  Heading "Recent activity" (20px, 600)                  │ │
│  │  Body-muted "Match history available in a future phase."│ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  Footer                                                      │
└──────────────────────────────────────────────────────────────┘
```

**Clan hero block layout:**
- Mobile: avatar on top, name + tags + meta below (column stack).
- `sm+`: avatar left, name/tags/meta right (row layout, `items-start`, `gap-4`).
- Avatar: 64×64px on desktop, 56×56px on mobile; `rounded-lg`; initials placeholder if no URL.
- Tags: inline `flex flex-wrap gap-2` of `ClanTagBadge` components.
- Status badge: only shown if NOT `active` (suspended/disbanded) — `active` is the normal state and adding noise is unwanted. Default to no badge = active.

**Members section:**
- Privacy gate: if `show_clan_history` is `false` for a member's privacy settings, that member's row is omitted from the list. If `show_to = 'private'` the entire member count is hidden.
- Show a privacy-muted notice when the roster is partially or fully hidden: `t('clans.privacy.roster_hidden_partial')` — "Some members have private profiles." Shown below the visible roster if any rows were hidden; shown instead of the roster if all rows are hidden.
- Member rows: `bg-[var(--color-surface)]`, border-b `border-[var(--color-border)]`, `p-3` (12px), `flex items-center gap-3`.
- Avatar: 32×32px, `rounded-full`, initials fallback.
- Name: Body (16px, 400). Clicking opens `/players/{slug}` if player has a public-facing profile.
- Role badge: `ClanRoleBadge` — Label size (14px, 600), `font-mono`, `px-2 py-1`, `rounded-sm`. Leader gets accent border; others get standard surface-elevated bg.
- Pagination: show max 20 members; if >20, show "Show all N members" link (ghost style, `t('clans.members.show_all', { count: N })`). Inertia partial reload on click.

**Recent activity placeholder:**
- P2 ships this section as a static placeholder. Copy: `t('clans.activity.placeholder')` — "Match history will appear here once this clan plays their first match."
- Section uses the standard Heading + Body-muted pattern with `bg-[var(--color-surface)] p-4 rounded-lg`.

---

### Page: `/players/{slug}` (Public player profile)

```
┌──────────────────────────────────────────────────────────────┐
│  PublicLayout header + nav                                   │
│  ──────────────────────────────────────────────────────────  │
│  Container max-w-3xl, px-4 md:px-6, py-8                    │
│                                                              │
│  ┌── Player hero block ───────────────────────────────────┐ │
│  │  [Avatar 64×64 rounded-full]  Display Name (H1 Display)│ │
│  │                               @discord_tag (if shown)  │ │
│  │                               Country flag             │ │
│  │                               [Current clan]           │ │
│  └────────────────────────────────────────────────────────┘ │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Bio section (if show_to permits) ────────────────────┐ │
│  │  Body 16px, --color-text                                │ │
│  └────────────────────────────────────────────────────────┘ │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Clan history (if show_clan_history=true) ─────────────┐│
│  │  Heading "Clan history" (20px, 600)                     ││
│  │  Timeline list of past memberships (joined/left dates)  ││
│  └────────────────────────────────────────────────────────┘│
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Match history placeholder (if show_match_history) ───┐ │
│  │  Heading "Match history" (20px, 600)                    │ │
│  │  Body-muted "Match data available in a future phase."   │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌── Stats placeholder (if show_stats) ───────────────────┐ │
│  │  Heading "Stats" (20px, 600)                            │ │
│  │  Body-muted "Stats available once matches are recorded."│ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  Footer                                                      │
└──────────────────────────────────────────────────────────────┘
```

**Privacy gate logic (D-018):**

Privacy is evaluated by the backend before the Inertia page data is shaped. The backend applies:
1. `show_to` tier check: `public` = visible to all; `community` = visible to logged-in users; `clan` = visible to same-clan members only; `private` = no profile at all (returns 404).
2. Per-section flag check after tier pass: if `show_discord_tag = false`, omit `discord_tag` from DTO; if `show_clan_history = false`, omit membership history; etc.

The Vue page receives only the permitted fields — it does NOT perform privacy logic itself. This means:
- If `discord_tag` is absent from the DTO, the `@discord_tag` line is not rendered.
- If `show_clan_history` section was withheld, the "Clan history" section is omitted entirely (not shown with a lock icon).
- If `show_to = 'private'`, the controller returns 404.

**Privacy notice on own profile (logged-in user viewing their own `/players/{slug}`):**
- Show a subtle inline notice at the top of the page: `t('players.privacy.your_profile_note')` — "Some sections of your profile are hidden from other visitors. Adjust privacy in account settings."
- Note rendered in `bg-[var(--color-surface)] border border-[var(--color-border)] p-3 rounded-md` at Body size, muted color. Uses `info` styling — no accent or danger colors.

**Avatar:** 64×64px on desktop, 56×56px on mobile; `rounded-full` (circular for players; contrast with clan `rounded-lg`).

**Discord tag:** rendered as `@username` in Label (14px, 600), `font-mono`, `--color-text-muted`.

**Current clan:** clan name as a link to `/clans/{slug}` in Body size. If no active clan, omit the field entirely.

---

### Page: `/my-clan` (Auth-gated clan management — Leader/Officer only)

Access gate: this route requires authentication AND an active clan membership with role `leader` or `officer`. Users without a clan see either a "Create clan" page or are redirected to `/clans`. Users with `member` or `recruit` role see their clan detail page at `/clans/{slug}` with a read-only roster.

```
┌──────────────────────────────────────────────────────────────┐
│  PublicLayout header + nav (auth state)                      │
│  ──────────────────────────────────────────────────────────  │
│  Container max-w-3xl, px-4 md:px-6, py-8                    │
│                                                              │
│  H1 Display "Manage your clan" (28px, 600)                   │
│  gap-8 vertical                                              │
│                                                              │
│  ┌── Tabs ────────────────────────────────────────────────┐ │
│  │  [Profile]  [Members]  [Invites]  [Applications]        │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌── Tab: Profile ─────────────────────────────────────────┐│
│  │  Inline edit form: Name, Tag, Description, Country      ││
│  │  Tags (multi-select)                                     ││
│  │  [ Save changes ]  ← primary CTA, accent fill           ││
│  └────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌── Tab: Members ──────────────────────────────────────── ┐│
│  │  Roster list with role assignment dropdowns              ││
│  │  [ Invite member ]  ← secondary button, opens modal     ││
│  │  [Remove] action per row (destructive, danger color)     ││
│  └────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌── Tab: Invites ─────────────────────────────────────────┐│
│  │  List of pending outgoing invites                        ││
│  │  [Revoke] action per invite (destructive)                ││
│  └────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌── Tab: Applications ────────────────────────────────────┐│
│  │  List of pending incoming applications                   ││
│  │  [Accept] / [Decline] actions per application            ││
│  └────────────────────────────────────────────────────────┘│
│                                                              │
│  Footer                                                      │
└──────────────────────────────────────────────────────────────┘
```

**Tab component:** `TabGroup` using Reka UI's `TabsRoot / TabsList / TabsTrigger / TabsContent`. Tab triggers: Label size (14px, 600), bottom-border indicator in `--color-accent` for active tab. Inactive tab text: `--color-text-muted`. Tab trigger height: 40px (h-10).

**Profile tab — form:**
- `Name`: text input, required, max 255.
- `Tag`: text input, `font-mono`, max 8 chars (per Open Questions "2–8 chars"), hint copy `t('clans.form.tag.hint')` — "Short identifier shown with your clan, e.g. 91st".
- `Description`: `<textarea>` rows-4, resize-vertical. Body size. Translatable input — P2 ships EN-only editing (single locale textarea); later phases add locale tab switcher.
- `Country`: select or text input for 2-char country code.
- `Save changes`: `Button variant="primary" size="md"`, full-width on mobile, auto-width on `md+`. Submit fires Inertia `router.post` or `router.patch`.

**Members tab — roster:**
- Same `MemberRow` layout as the public clan detail page, but with role management controls.
- Role assignment: select dropdown (Leader/Officer/Member/Recruit) per row. Inline save on change — Inertia `router.patch` to `/my-clan/members/{membership_id}/role`. Show success toast on save.
- Remove member: `Button variant="ghost" size="sm"` with `--color-danger` text color and trash icon (`lucide-vue-next` `Trash2`). On click, opens inline confirmation: replaces the Remove button with "Confirm remove? [Yes, remove] [Cancel]" in the same row. **No modal** — inline confirmation is sufficient for this non-catastrophic action.
- **Leader transfer:** If a Leader changes their own role to Officer/Member, show a warning inline: `t('clans.members.leader_transfer_warning')` — "You are about to give leadership of this clan to another member. This cannot be undone without admin action."

**Invites tab:**
- List of pending invites: `[Player name] → [status: pending]`, sent date, [Revoke] ghost-danger button.
- Empty state: `t('clans.invites.empty')` — "No pending invites. Invite members from the Members tab."

**Applications tab:**
- List of pending applications: `[Player name]`, message (truncated to 120 chars), submitted date, [Accept] primary small button, [Decline] ghost-danger small button.
- Accept fires Inertia `router.post /my-clan/applications/{id}/accept`. Decline fires `router.post /my-clan/applications/{id}/decline`.
- Empty state: `t('clans.applications.empty')` — "No pending applications. Members can apply to join from the clan directory."

**No clan state** (logged-in user visits `/my-clan` with no active membership):
```
Display: "You're not in a clan"
Body-muted: "Join an existing clan from the directory or create your own."
[ Browse clans ]  ← secondary button → /clans
[ Create your clan ]  ← primary button, accent fill
```

---

### Modal: Invite member

Triggered from the Members tab "Invite member" button. Uses Reka UI `DialogRoot/DialogContent`.

```
┌── Invite a member ──────────────────────────────────────┐
│  [× close]                                              │
│                                                         │
│  Label: "Search by username or Discord tag"             │
│  Input: text search (typeahead, min 2 chars)            │
│  [search results list, max 5 items]                     │
│                                                         │
│  Label: "Message (optional)"                            │
│  Textarea: rows-2, placeholder "Add a personal note…"  │
│                                                         │
│  [ Cancel ]  [ Send invite ]                            │
│           secondary    primary (accent fill)            │
└─────────────────────────────────────────────────────────┘
```

- Modal title: `t('clans.invites.modal_title')` — "Invite a member"
- `bg-[var(--color-surface-elevated)]`, `rounded-lg`, `p-6`, `max-w-lg w-full`, backdrop `bg-black/60`.
- Search results: each result shows `[Avatar 32×32] Display name @discord_tag (if permitted)`. Click selects, highlighted with `bg-[var(--color-surface)] border border-[var(--color-accent)]`.
- "Send invite" disabled until a player is selected from results.
- On success: toast `t('clans.invites.sent')` — "Invite sent." Modal closes.
- On failure (player already in a clan): inline error below results — `t('clans.invites.error.already_in_clan')` — "This player is already a member of a clan."

---

## Component Inventory — New in Phase 2

| Component | File path | Purpose | Built on |
|-----------|-----------|---------|----------|
| `ClanCard` | `components/clans/ClanCard.vue` | Directory grid item — avatar, name, tags, member count, description excerpt | Plain Vue (no Reka primitive needed) |
| `ClanTagBadge` | `components/clans/ClanTagBadge.vue` | Tag pill used on cards, detail page, filter bar | Plain `<span>` with CSS tokens |
| `ClanRoleBadge` | `components/clans/ClanRoleBadge.vue` | Member role pill (Leader/Officer/Member/Recruit) | Plain `<span>` with CSS tokens |
| `MemberRow` | `components/clans/MemberRow.vue` | Reusable member row for detail page + My Clan roster | Plain Vue |
| `StatusBadge` | `components/ui/StatusBadge.vue` | Generic status pill for clan status + privacy tier | Plain `<span>` with color-map prop |
| `TabGroup` | `components/ui/TabGroup.vue` | Accessible tabs (My Clan tabs + future use) | Reka UI `TabsRoot/TabsList/TabsTrigger/TabsContent` |
| `Modal` | `components/ui/Modal.vue` | Generic modal dialog (invite modal + future) | Reka UI `DialogRoot/DialogContent/DialogOverlay` |
| `TextInput` | `components/ui/TextInput.vue` | Labeled text input with error state | Plain Vue + CSS tokens |
| `Textarea` | `components/ui/Textarea.vue` | Labeled textarea | Plain Vue + CSS tokens |
| `Select` | `components/ui/Select.vue` | Accessible select with label | Reka UI `SelectRoot` or native `<select>` — use native for P2 (simpler, fully accessible) |
| `PlayerCard` | `components/players/PlayerCard.vue` | Player profile hero (avatar, name, tag, clan link) | Plain Vue |
| `UserMenu` | `components/UserMenu.vue` | Authed header dropdown (avatar, My Clan, My Profile, Log out) | Reka UI `DropdownMenuRoot` |

**Reused from Phase 1 without modification:**
- `Button`, `IconButton`, `ThemeToggle`, `Wordmark`, `PublicLayout`, `LoginButton`

**Forward-compat note:** `TextInput`, `Textarea`, `Select`, and `Modal` become the standard form primitives for Phase 3+ as well. Keep API generic (no clan-specific props).

---

## New Pages — Inertia Route Map

| Route | Vue page file | Auth required | Layout |
|-------|---------------|---------------|--------|
| `GET /clans` | `pages/Clans/Index.vue` | No | `PublicLayout` |
| `GET /clans/{slug}` | `pages/Clans/Show.vue` | No | `PublicLayout` |
| `GET /players/{slug}` | `pages/Players/Show.vue` | No | `PublicLayout` |
| `GET /my-clan` | `pages/MyClan/Index.vue` | Yes (Leader/Officer) | `PublicLayout` |

All four pages receive Inertia props shaped by Laravel DTOs (`ClanData`, `ClanMembershipData`, `PlayerData` — generated into `packages/shared-types` per D-020).

---

## Filament Admin Resources — Phase 2

New resources follow the **exact** pattern established by `UserResource` and `PlayerResource`:
- `declare(strict_types=1);`
- `getModelLabel()` / `getPluralModelLabel()` via `__('admin.<resource>.label')` (D-013)
- `Tabs::make()` with Profile + Audit tabs for detail/edit views
- Audit tab: `Placeholder::make('audit_log')->content(fn($r) => view('filament.partials.audit-tab', ['subject' => $r]))`
- Navigation sort order: ClanResource = 3, ClanTagResource = 4, ClanMembershipResource = 5, ClanInviteResource = 6, ClanApplicationResource = 7
- Navigation icons: `heroicon-o-user-group` (Clan), `heroicon-o-tag` (ClanTag), `heroicon-o-users` already used — use `heroicon-o-identification` (ClanMembership), `heroicon-o-envelope` (ClanInvite), `heroicon-o-inbox-arrow-down` (ClanApplication)

**ClanResource:**
- List: `slug` (mono, searchable), `name` (sortable), `tag` (mono), `status` (badge), `owner` (username link), `created_at`.
- Edit form tabs: Profile (all editable fields), Members (RelationManager), Invites (RelationManager), Applications (RelationManager), Audit.
- Status select: `active / suspended / disbanded`.
- `description` field: `KeyValue` component (same pattern as bio) for jsonb — until a structured locale editor lands.
- `discord_role_id` / `discord_announce_channel_id`: text inputs, `font-mono`, disabled by default (admin-only override enabled with a separate "Enable edit" toggle action).
- Actions: View, Edit (all roles), `ForceDeleteAction` (super-admin only, confirmation required).

**ClanTagResource:**
- List: `slug` (mono), `label` (jsonb, show 'en' key), `color` (color swatch preview).
- Form: `slug` (auto-generated from label['en']), `label` KeyValue, `color` ColorPicker or TextInput with `#` prefix validation.
- No Create restriction — admin can create tags freely.
- No Delete action (tags may be referenced by clans) — expose a Filament `DetachAction` only via ClanResource's tag RelationManager.

**ClanMembershipResource:**
- List only (no edit page): `user.username`, `clan.name`, `role`, `joined_at`, `left_at` (null = active, highlight with success color).
- Table filter: active only (WHERE left_at IS NULL), clan select, role select.
- Actions: View only. No create/edit/delete — membership lifecycle managed through My Clan page and domain logic.

**ClanInviteResource:**
- List: `clan.name`, `user.username`, `status`, `message` (truncated 60 chars), `decided_at`.
- Actions: View only. No admin create/edit.

**ClanApplicationResource:**
- List: `clan.name`, `user.username`, `status`, `message` (truncated 60 chars), `decided_at`.
- Actions: View only. No admin create/edit.

**Audit tab extension (admin.php i18n):**
Add subject type labels: `'Clan' => 'Clan'`, `'ClanTag' => 'Tag'`, `'ClanMembership' => 'Membership'`, `'ClanInvite' => 'Invite'`, `'ClanApplication' => 'Application'`.

---

## Copywriting Contract

All copy flows through `t()` (Vue) or `__()` (Blade/PHP). New namespace: `clans.php`. No hardcoded strings in Vue templates. i18n key naming: snake-case, namespaced by surface area (D-013).

### New `lang/en/clans.php` keys

| Element | i18n key | EN copy |
|---------|----------|---------|
| Directory page title | `clans.directory.title` | **Clans** |
| Directory page sub-copy | `clans.directory.subtitle` | **Browse and discover league clans.** |
| Directory filter clear | `clans.filter.clear` | **Clear filters** |
| Directory filter tag label | `clans.filter.tag_label` | **Filter by tag** |
| Directory search placeholder | `clans.directory.search_placeholder` | **Search clans…** |
| Directory empty: filtered no results | `clans.directory.empty_results` | **No clans match your search. Try different keywords or clear the filters.** |
| Directory empty: no clans exist | `clans.directory.empty_default` | **No clans have been created yet.** |
| Member count (singular) | `clans.members.count_one` | **1 member** |
| Member count (plural) | `clans.members.count_other` | **:count members** |
| Show all members link | `clans.members.show_all` | **Show all :count members** |
| Invite member button | `clans.members.invite_button` | **Invite member** |
| Section: members | `clans.section.members` | **Members** |
| Section: recent activity | `clans.section.recent_activity` | **Recent activity** |
| Recent activity placeholder | `clans.activity.placeholder` | **Match history will appear here once this clan plays their first match.** |
| Privacy: partial roster hidden | `clans.privacy.roster_hidden_partial` | **Some members have private profiles.** |
| Privacy: all roster hidden | `clans.privacy.roster_hidden_all` | **This clan's member list is private.** |
| My Clan page title | `clans.my_clan.title` | **Manage your clan** |
| My Clan tab: Profile | `clans.my_clan.tab.profile` | **Profile** |
| My Clan tab: Members | `clans.my_clan.tab.members` | **Members** |
| My Clan tab: Invites | `clans.my_clan.tab.invites` | **Invites** |
| My Clan tab: Applications | `clans.my_clan.tab.applications` | **Applications** |
| Form: clan name label | `clans.form.name.label` | **Clan name** |
| Form: clan tag label | `clans.form.tag.label` | **Clan tag** |
| Form: clan tag hint | `clans.form.tag.hint` | **Short identifier for your clan, e.g. 91st (2–8 characters).** |
| Form: description label | `clans.form.description.label` | **Description** |
| Form: country label | `clans.form.country.label` | **Country** |
| Form: tags label | `clans.form.tags.label` | **Tags** |
| Primary CTA: save | `clans.form.save` | **Save changes** |
| Primary CTA: create clan | `clans.create.cta` | **Create your clan** |
| Create clan: not in clan title | `clans.no_clan.title` | **You're not in a clan** |
| Create clan: not in clan body | `clans.no_clan.body` | **Join an existing clan from the directory or create your own.** |
| Create clan: browse link | `clans.no_clan.browse` | **Browse clans** |
| Invites tab empty | `clans.invites.empty` | **No pending invites. Invite members from the Members tab.** |
| Applications tab empty | `clans.applications.empty` | **No pending applications. Members can apply to join from the clan directory.** |
| Invite modal title | `clans.invites.modal_title` | **Invite a member** |
| Invite modal search label | `clans.invites.search_label` | **Search by username** |
| Invite modal message label | `clans.invites.message_label` | **Message (optional)** |
| Invite modal message placeholder | `clans.invites.message_placeholder` | **Add a personal note…** |
| Invite CTA | `clans.invites.send` | **Send invite** |
| Invite sent toast | `clans.invites.sent` | **Invite sent.** |
| Invite error: already in clan | `clans.invites.error.already_in_clan` | **This player is already a member of a clan.** |
| Leader transfer warning | `clans.members.leader_transfer_warning` | **You are about to transfer leadership of this clan. This cannot be undone without admin action.** |
| Remove member confirm prefix | `clans.members.remove_confirm` | **Remove :name from the clan?** |
| Remove member confirm yes | `clans.members.remove_yes` | **Yes, remove** |
| Cancel action | `clans.actions.cancel` | **Cancel** |
| Revoke invite | `clans.invites.revoke` | **Revoke** |
| Accept application | `clans.applications.accept` | **Accept** |
| Decline application | `clans.applications.decline` | **Decline** |
| Accept success toast | `clans.applications.accepted` | **Application accepted. :name has joined the clan.** |
| Decline success toast | `clans.applications.declined` | **Application declined.** |

**Note on single-word table-row action labels** (`Revoke`, `Accept`, `Decline`): These are intentionally terse. The row itself provides the noun context (player name, invite status, application message), making "Revoke" equivalent in meaning to "Revoke invite" and "Accept" / "Decline" equivalent to "Accept application" / "Decline application". Screen readers will announce the button label along with the row context (via `aria-label` on the button: `t('clans.invites.revoke') + ' ' + playerName`, etc.) — the implementation MUST add descriptive `aria-label` attributes to these buttons to complete the accessible name. Copy shorthand is justified by row context; ARIA label is required.

### New `lang/en/players.php` keys

| Element | i18n key | EN copy |
|---------|----------|---------|
| Player profile: own privacy notice | `players.privacy.your_profile_note` | **Some sections of your profile are hidden from other visitors. Adjust privacy in your account settings.** |
| Player profile: section clan history | `players.section.clan_history` | **Clan history** |
| Player profile: section match history | `players.section.match_history` | **Match history** |
| Player profile: section stats | `players.section.stats` | **Stats** |
| Match history placeholder | `players.match_history.placeholder` | **Match data will be available once this player's matches are recorded.** |
| Stats placeholder | `players.stats.placeholder` | **Stats will appear once matches are recorded.** |
| No current clan | (omit field entirely — do not display "No clan") | N/A — field hidden when absent |

### Additions to `lang/en/common.php`

| Element | i18n key | EN copy |
|---------|----------|---------|
| Nav: Clans | `common.nav.clans` | **Clans** |
| Nav: Players | `common.nav.players` | **Players** |
| Nav: My Clan | `common.nav.my_clan` | **My Clan** |
| User menu: profile | `common.nav.my_profile` | **My Profile** |

### Additions to `lang/en/admin.php`

| Element | i18n key | EN copy |
|---------|----------|---------|
| Clan resource label | `admin.clan.label` | **Clan** |
| Clan resource plural | `admin.clan.plural_label` | **Clans** |
| ClanTag resource label | `admin.clan_tag.label` | **Clan tag** |
| ClanTag resource plural | `admin.clan_tag.plural_label` | **Clan tags** |
| ClanMembership resource label | `admin.clan_membership.label` | **Membership** |
| ClanMembership resource plural | `admin.clan_membership.plural_label` | **Memberships** |
| ClanInvite resource label | `admin.clan_invite.label` | **Invite** |
| ClanInvite resource plural | `admin.clan_invite.plural_label` | **Invites** |
| ClanApplication resource label | `admin.clan_application.label` | **Application** |
| ClanApplication resource plural | `admin.clan_application.plural_label` | **Applications** |
| Audit subject: Clan | `admin.audit.subject.Clan` | **Clan** |
| Audit subject: ClanTag | `admin.audit.subject.ClanTag` | **Tag** |
| Audit subject: ClanMembership | `admin.audit.subject.ClanMembership` | **Membership** |
| Audit subject: ClanInvite | `admin.audit.subject.ClanInvite` | **Invite** |
| Audit subject: ClanApplication | `admin.audit.subject.ClanApplication` | **Application** |

### Destructive actions in Phase 2

| Action | Trigger | Confirmation approach | Copy |
|--------|---------|----------------------|------|
| Remove member from clan | Remove button in My Clan Members tab | Inline row-level confirm (no modal) | `clans.members.remove_confirm` + `clans.members.remove_yes` + `clans.actions.cancel` |
| Revoke outgoing invite | Revoke button in My Clan Invites tab | Single-click (low stakes — no confirm) | `clans.invites.revoke` |
| Decline application | Decline button in My Clan Applications tab | Single-click (reversible — can re-invite) | `clans.applications.decline` |
| Transfer leadership | Role dropdown change on own row (Leader → lower role) | Warning inline notice, then confirm dropdown change | `clans.members.leader_transfer_warning` |
| Admin: force-delete clan | Filament `ForceDeleteAction` | Filament's built-in modal confirmation dialog | Filament default copy |

**No "Disband clan" action in P2** — disbanding is admin-only via Filament status change to `disbanded`. No public-facing disband flow.

---

## Accessibility Contract

Inherited from Phase 1 in full. Phase 2 additions:

- **Tag badge contrast:** `ClanTagBadge` text must maintain AA contrast on both `--color-surface` (card bg) and `--color-bg` (direct on page). Use `--color-text` on `--color-surface-elevated` bg. Verify in both themes.
- **Role badge contrast:** Same requirement. `--color-text` on `--color-surface-elevated` passes on both themes.
- **Modal focus trap:** `Modal.vue` using Reka UI `DialogContent` ships a built-in focus trap. Do not remove or override `inert` / `aria-modal`.
- **Tab keyboard nav:** `TabGroup` using Reka UI ships arrow-key navigation. Do not suppress.
- **Status pills:** Never convey state by color alone. Every status badge includes text (`Active`, `Pending`, etc.) — color is supplemental.
- **Filter tag pills:** Each pill is a `<button>` with `aria-pressed="true|false"` and the full tag label visible (not icon-only).
- **Search input in invite modal:** `aria-label="t('clans.invites.search_label')"`, `role="combobox"`, results list `role="listbox"`.
- **Empty states:** `role="status"` on empty-state containers so screen readers announce content.
- **Privacy notice:** Not an alert. Use a `<p>` with Body styling — no `role="alert"` (non-urgent).
- **Table-row action buttons** (`Revoke`, `Accept`, `Decline`): each button MUST carry a descriptive `aria-label` that includes the player name, e.g. `aria-label="Revoke invite for {playerName}"`. The visible label is intentionally terse; the accessible name is complete.

---

## Motion Contract

**Inherited from Phase 1:** `--ease-default`, `--motion-duration-fast` (150ms), `--motion-duration-base` (200ms).

Phase 2 additions:
- **ClanCard hover:** `transition-colors duration-[var(--motion-duration-fast)]` on `background-color` and `border-color`. No scale/translate transforms.
- **Tag filter pill selection:** `transition-colors duration-[var(--motion-duration-fast)]` on `border-color`.
- **Modal open/close:** Reka UI `DialogContent` ships enter/exit transitions. Set `data-[state=open]:animate-in data-[state=closed]:animate-out` with `duration-[var(--motion-duration-base)]`. Fade only — no slide (reduces motion sensitivity).
- **Tab indicator:** `transition-colors duration-[var(--motion-duration-fast)]` on the active tab bottom-border — no width animation.

All transitions respect `prefers-reduced-motion: reduce` via the Phase 1 pattern (0ms under reduced motion).

---

## Responsive Breakpoints

**Inherited from Phase 1 unchanged.** Phase 2 breakpoint-specific rules:

| Surface | Mobile (< 640px) | `sm` (640px+) | `md` (768px+) | `lg` (1024px+) |
|---------|-----------------|--------------|--------------|---------------|
| Clan directory grid | 1 column | 2 columns | 2 columns | 3 columns |
| Clan hero block | Avatar on top, details below | Row layout (avatar left) | Row layout | Row layout |
| Player hero block | Avatar on top, details below | Row layout | Row layout | Row layout |
| My Clan tabs | Horizontal scroll (overflow-x-auto) | Horizontal tabs | Horizontal tabs | Horizontal tabs |
| Filter bar tag pills | Horizontal scroll row | Horizontal scroll row | Full display | Full display |
| Nav items (Clans / Players) | Hidden (burger menu — NOT in P2; omit on mobile) | Hidden | Visible center | Visible center |

**Mobile nav note:** In P2, the center nav items (Clans, Players) are hidden on mobile (`hidden md:flex`). The user can reach `/clans` and `/players` from the UserMenu dropdown (via direct links) or from the home page. A mobile hamburger nav is out of Phase 2 scope — add in Phase 9 (Polish).

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| shadcn official | none | not applicable — Vue stack, shadcn is React-only |
| third-party | none | not applicable |

No external component blocks consumed in Phase 2. All new components are owned wrappers around Reka UI (MIT) or plain Vue. Same "owned, not a black box" rule as Phase 1.

---

## Definition of "Visually Correct" for Phase 2 Sign-Off

Implementation passes this contract when:

1. `/clans` renders a grid of ClanCards with no horizontal scroll at 360px viewport.
2. `/clans?tag=eu` filters to clans tagged `EU` only; the EU tag pill shows accent border.
3. `/clans/{slug}` shows clan hero, tags, member roster (with privacy gates applied server-side), and activity placeholder.
4. `/players/{slug}` shows only fields permitted by the player's `show_to` tier and per-section flags; `show_to = 'private'` returns 404.
5. A logged-in Leader visiting `/my-clan` sees the 4-tab management interface; all tabs are keyboard-reachable.
6. The Invite modal opens, traps focus, closes on Escape or backdrop click, and announces to screen readers.
7. Removing a member from the My Clan roster triggers the inline confirmation (not a modal).
8. All visible text resolves through `t()` — grep for hardcoded EN strings in `resources/js/pages/Clans/`, `resources/js/pages/Players/`, `resources/js/pages/MyClan/` returns zero matches.
9. Toggling `data-theme=dark` ↔ `data-theme=light` on `<html>` re-skins all P2 surfaces without missed tokens.
10. Five new Filament resources are reachable at `/admin`; each has a working Audit tab with the Phase 1 audit-tab partial.
11. AA contrast is maintained on both themes for all new status pill combinations (verify `--color-success` text on surface bg and `--color-warning` text on surface bg).
12. The per-clan accent override hook is in the Clan detail template (even though `clan.accent_color` is null for all P2 clans — the scope wrapper must exist for Phase 3+ to activate it).
13. `/clans` with active filters and zero results renders the `clans.directory.empty_results` empty state with a "Clear filters" link.
14. `/clans` with no clans in the system and no active filters renders the `clans.directory.empty_default` empty state.

---

## Forward-Compat Hooks (declared in P2, not built)

- **Per-clan accent color:** `clans.accent_color` column + scope wrapper in templates — activate in a future phase when the admin field is exposed.
- **Bio locale switcher (My Clan description):** P2 ships single-locale textarea; structured locale-tab editor deferred to Phase 7 (CMS).
- **Player profile "Edit privacy" link:** Own-profile privacy-notice calls out "account settings" — the Settings page does not exist in P2. The link can be a disabled/missing link in P2 and must be wired in a future phase (Phase 9 or a dedicated settings phase).
- **Mobile hamburger nav:** Nav items hidden on mobile in P2. `UserMenu` provides minimal navigation. Full mobile nav ships in Phase 9.
- **Application flow (public):** `/clans/{slug}` will have an "Apply to join" button in a future phase — the My Clan Applications tab is ready to receive these. No apply button on the public clan detail page in P2.
- **Clan create page:** P2 shows a "Create your clan" CTA but the actual clan creation form is scope-defined here (it is needed for SC-3 of Phase 2: "clan leader/officer can manage their clan"). If the planning phase determines clan create is deferred, the CTA button should be present but may be wired to a placeholder or Filament admin-only flow.

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: PASS
- [ ] Dimension 2 Visuals: PASS
- [ ] Dimension 3 Color: PASS
- [ ] Dimension 4 Typography: PASS
- [ ] Dimension 5 Spacing: PASS
- [ ] Dimension 6 Registry Safety: PASS (n/a — no external blocks)

**Approval:** pending

---

## Source Provenance

| Section | Source |
|---------|--------|
| All inherited tokens (spacing, typography, color, motion, a11y) | `.planning/phases/01-foundations/01-UI-SPEC.md` (codebase canonical) |
| Tailwind v4 CSS-first token system | `apps/web/resources/css/app.css` (codebase scan 2026-05-12) |
| Clan entity structure (name, tag, slug, description, country, discord_role_id, status) | `.docs/04-domain-model.md` + `.docs/05-database-schema.md` |
| ClanTag, ClanMembership, ClanInvite, ClanApplication entities | `.docs/04-domain-model.md` + `.docs/05-database-schema.md` |
| D-008 tags m:n on clans | `.planning/PROJECT.md` D-008 |
| D-009 one active ClanMembership per player | `.planning/PROJECT.md` D-009 |
| D-013 i18n every string via t()/__(). | `.planning/PROJECT.md` D-013 + `CLAUDE.md` §7 |
| D-018 per-section + global tier player privacy | `.planning/PROJECT.md` D-018 |
| Phase 2 success criteria | `.planning/ROADMAP.md` Phase 2 |
| Filament resource pattern (Tabs + Audit) | `apps/web/app/Filament/Resources/UserResource.php` + `PlayerResource.php` (codebase scan 2026-05-12) |
| Button + IconButton primitives | `apps/web/resources/js/components/ui/Button.vue` + `IconButton.vue` (codebase scan 2026-05-12) |
| PublicLayout nav slot ("empty in P1, populated in Phase 2") | `apps/web/resources/js/layouts/PublicLayout.vue` comment (codebase scan 2026-05-12) |
| Per-clan accent override forward-compat hook | `01-UI-SPEC.md` §Forward-Compat Hooks |
| Clan tag length (2–8 chars) | `.planning/PROJECT.md` Open Questions |
| Privacy 404 for private profiles | `.docs/05-database-schema.md` player_privacy + Phase 2 CONTEXT.md |
| Component library (Reka UI) | `.docs/09-frontend.md` + `01-UI-SPEC.md` |
| Mobile nav hidden on md breakpoint | `apps/web/resources/js/layouts/PublicLayout.vue` `hidden md:flex` pattern (codebase scan) |
| BLOCK 1 fix: empty state i18n keys | gsd-ui-checker revision 1 (2026-05-12) |
| BLOCK 2 fix: ClanRoleBadge py-0.5 → py-1 | gsd-ui-checker revision 1 (2026-05-12) |
| FLAG fixes: invite_button key, applications.empty copy, accent list, a11y note | gsd-ui-checker revision 1 (2026-05-12) |
