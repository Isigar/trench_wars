---
phase: 1
slug: foundations
status: draft
shadcn_initialized: false
preset: not applicable
created: 2026-05-03
---

# Phase 1 — UI Design Contract

> Visual and interaction contract for the Trenchwars **public site** + Filament theme overrides. This phase establishes the design tokens that ALL subsequent phases (2–9) inherit. Scope is deliberately narrow: landing page, Discord login surface, and Filament panel chrome — but the token system, primitives, typography scale, and color contract here are the source of truth for every page that ships in Phase 2 onward.

> **Note on `shadcn`:** Not applicable. Stack is Vue 3 + Inertia, not React; shadcn is React-only. The owned-primitives strategy for Vue is **Reka UI** (Vue port of Radix UI) with Tailwind-styled wrappers in `resources/js/components/ui/` — see `.docs/09-frontend.md`. Registry safety gate is therefore not required.

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (custom token system; Tailwind v4 CSS-first config) |
| Preset | not applicable (Vue stack — shadcn React-only) |
| Component library | Reka UI (Vue port of Radix UI) — headless, accessible primitives wrapped in `resources/js/components/ui/` |
| Icon library | `lucide-vue-next` |
| Font (UI) | Inter (variable) — self-hosted via `@fontsource-variable/inter` |
| Font (mono) | JetBrains Mono — self-hosted via `@fontsource-variable/jetbrains-mono`; used for tags, IDs, small-caps labels |
| Token authoring | Single `@theme` block in `resources/css/app.css` (Tailwind v4 — no `tailwind.config.js`) |
| Theme switching | `@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *));` — `data-theme` attr on `<html>`; **dark default**, light option |
| Filament theme | Custom theme file at `resources/css/filament/admin/theme.css`; reuses public site's color tokens; `Filament::registerTheme()` wired in `AdminPanelProvider` |

### Token authoring contract

```css
/* resources/css/app.css */
@import "tailwindcss";

@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *));

@theme {
  /* Fonts */
  --font-sans: "Inter Variable", "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-mono: "JetBrains Mono Variable", "JetBrains Mono", ui-monospace, monospace;

  /* Spacing — declared explicitly so the contract is unambiguous */
  --spacing: 0.25rem;       /* base — Tailwind v4 multiplier */

  /* Radii */
  --radius-sm: 4px;          /* inputs */
  --radius-md: 6px;          /* default */
  --radius-lg: 10px;         /* cards */

  /* Color tokens — semantic, theme-agnostic names, see Color section */
  /* (values declared per-theme in :root and [data-theme=dark]) */
}

/* Default (dark) theme tokens live on :root so dark is the no-attr default */
:root,
[data-theme=dark] {
  --color-bg:               #1A1B16;   /* trench black-olive */
  --color-surface:          #232518;   /* card surface */
  --color-surface-elevated: #2C2E20;   /* popover, modal */
  --color-border:           #3A3D2C;
  --color-text:             #EDEAD9;   /* off-white */
  --color-text-muted:       #A8A693;
  --color-accent:           #A4262C;   /* deep red — placeholder per Open Questions */
  --color-accent-fg:        #FFFFFF;   /* on-accent text */
  --color-danger:           #C03A2B;
  --color-success:          #6B8E3D;
  --color-warning:          #C8932A;   /* warning ochre */
  --color-focus-ring:       #C7A23A;   /* high-contrast against both themes */
}

[data-theme=light] {
  --color-bg:               #F5F2E6;
  --color-surface:          #FFFFFF;
  --color-surface-elevated: #FFFFFF;
  --color-border:           #D6D2BE;
  --color-text:             #1A1B16;
  --color-text-muted:       #5C5D4A;
  --color-accent:           #8E1E22;   /* slightly darkened for AA contrast on light bg */
  --color-accent-fg:        #FFFFFF;
  --color-danger:           #A6271A;
  --color-success:          #4F6E25;
  --color-warning:          #9C7220;
  --color-focus-ring:       #6B5210;
}
```

The `@theme { }` block exposes these as utilities (`bg-bg`, `bg-surface`, `text-accent`, etc.). All component code references **semantic tokens**, never raw hex.

---

## Spacing Scale

Declared values (multiples of 4 only). Tailwind v4's `--spacing: 0.25rem` base means utilities `p-1` → `p-16` map naturally to this scale.

| Token | Value | Tailwind utility | Usage |
|-------|-------|------------------|-------|
| xs | 4px | `p-1`, `gap-1` | Icon gaps, inline padding |
| sm | 8px | `p-2`, `gap-2` | Compact element spacing, between label and control |
| md | 16px | `p-4`, `gap-4` | Default element spacing, form rows, list items |
| lg | 24px | `p-6`, `gap-6` | Section padding, card internal padding |
| xl | 32px | `p-8`, `gap-8` | Layout gaps, between sections within a page |
| 2xl | 48px | `p-12`, `gap-12` | Major section breaks |
| 3xl | 64px | `p-16`, `gap-16` | Page-level top/bottom rhythm |

**Exceptions:**
- Touch targets: minimum hit-area **44×44px** for icon-only buttons (`IconButton` primitive enforces `h-11 w-11` on mobile, `h-9 w-9` on `md+`). Padding may be visually smaller but click target is preserved with negative margin.
- Filament panel: Filament v3 ships its own internal spacing; do not override unless an alignment glitch is visible.

---

## Typography

Exactly **4 sizes, 2 weights** — no exceptions across Phase 1 surfaces.

| Role | Size | Weight | Line Height | Usage |
|------|------|--------|-------------|-------|
| Body | 16px (`text-base`) | 400 (regular) | 1.5 | Paragraphs, default UI text, form values |
| Label | 14px (`text-sm`) | 600 (semibold) | 1.4 | Form labels, button text, tab triggers, table headers |
| Heading | 20px (`text-xl`) | 600 (semibold) | 1.3 | Card titles, section headers, dialog titles |
| Display | 28px (`text-3xl` ≈ 30px; clamped to **28px**) | 600 (semibold) | 1.2 | Page H1, landing wordmark fallback when SVG mark not yet defined |

- **Mono usage** (`font-mono`): clan tags, Discord IDs, slugs, small-caps role labels — same size as Body unless smaller is required.
- **No italic** in P1.
- **No weight 300, 500, 700, or 800** — only 400 + 600.
- **Wordmark "Trenchwars"** on landing renders as Display weight 600 in `font-sans` (per Open Questions: text-only placeholder, no logo SVG yet). Letter-spacing `tracking-tight` (`-0.025em`).

### i18n typography note (D-013)

All text passes through `t()` / `__()`. **No literal strings in `<template>` outside the i18n allowlist** (per `.docs/09-frontend.md` lint rule). When estimating layout, assume CS/DE/PL strings are ~30% longer than EN — leave horizontal slack on buttons and tabs.

---

## Color

60 / 30 / 10 split anchored to the trench-military palette. **Dark default**; light option must satisfy AA contrast (CON-frontend-goals).

| Role | Token | Dark hex | Light hex | Usage |
|------|-------|----------|-----------|-------|
| Dominant (60%) | `--color-bg` | `#1A1B16` | `#F5F2E6` | Page background — `<body>` and full-bleed sections |
| Secondary (30%) | `--color-surface` | `#232518` | `#FFFFFF` | Cards, sidebar, nav, panels, form rows |
| Secondary-elevated | `--color-surface-elevated` | `#2C2E20` | `#FFFFFF` | Dialog, popover, dropdown menu, toast |
| Accent (10%) | `--color-accent` | `#A4262C` | `#8E1E22` | See "Accent reserved for" below |
| Destructive | `--color-danger` | `#C03A2B` | `#A6271A` | Destructive button bg, delete confirmation |
| Success | `--color-success` | `#6B8E3D` | `#4F6E25` | Success toast, "active" status pill |
| Warning | `--color-warning` | `#C8932A` | `#9C7220` | Warning toast, "pending" / "scheduled" pill |

### Accent reserved for (explicit list — never "all interactive elements")

The accent (`#A4262C` deep red) is the league brand color. It must appear **sparingly** in P1:

1. **Primary CTA** on the landing page — the "Log in with Discord" button (background fill).
2. **Active/selected state on primary nav links** — left border accent strip, 3px wide, on the active nav item only.
3. **Filament panel primary color** — wired via Filament's `colors()` API in `AdminPanelProvider` (mapped to `Color::hex('#A4262C')`).
4. **Focus ring fallback** for accent-bearing controls only (default focus ring uses `--color-focus-ring` / ochre for non-accent controls — better contrast on dark olive bg).

Accent is **NOT** used for:
- Body links (use underlined text in `--color-text`)
- Tab triggers (use semibold + underline indicator in `--color-text`)
- Form input borders (use `--color-border`, focus → `--color-focus-ring`)
- Hover states on cards (use surface-elevated lift instead)
- Icon color (use `--color-text-muted` by default)

### Contrast contract (AA mandatory)

| Pair | Dark theme ratio | Light theme ratio | AA target |
|------|------------------|-------------------|-----------|
| `--color-text` on `--color-bg` | ≥ 13:1 | ≥ 13:1 | 4.5:1 (body) |
| `--color-text-muted` on `--color-bg` | ≥ 4.6:1 | ≥ 4.7:1 | 4.5:1 (body) |
| `--color-accent-fg` on `--color-accent` | ≥ 5.4:1 | ≥ 6.1:1 | 4.5:1 (body) — required because CTA label is white-on-red |
| `--color-text` on `--color-surface` | ≥ 11:1 | ≥ 13:1 | 4.5:1 |

The checker MUST verify these with a contrast tool before approving the spec.

### Per-clan accent (forward-compat hook, not used in P1)

`.docs/09-frontend.md` reserves `clans.accent_color` for Phase 2+. Token system already supports it: clan-scoped pages will set `--color-accent` on a wrapper div, overriding the league default. **Do not implement in P1**, but do not block this hook with hardcoded `#A4262C` references in components — always reference `var(--color-accent)`.

---

## Copywriting Contract

All copy goes through `t('namespace.key')` from `lang/en/*.php`. **Hardcoded literals are a CI failure.** i18n key naming (D-013):

- Namespaced by surface area: `auth.*`, `common.*`, `validation.*`, `admin.*`.
- Snake-case keys: `auth.discord.button_label`, `common.errors.generic`.
- Parameter interpolation: `:?param` (Laravel-style), surfaced through `t()` helper as `t('key', { param: value })`.

| Element | i18n key | EN copy |
|---------|----------|---------|
| Primary CTA (logged-out) | `auth.discord.button_label` | **Log in with Discord** |
| Landing tagline (logged-out) | `home.tagline` | **The league for clan-organised matches.** |
| Landing sub-copy (logged-out) | `home.subcopy` | **Schedule scrims, sign up for slots, and let results write themselves.** |
| Landing greeting (logged-in stub) | `home.welcome_back` | **Welcome back, :name.** |
| Empty state heading (audit page first load) | `admin.audit.empty.heading` | **No activity yet** |
| Empty state body (audit page) | `admin.audit.empty.body` | **Admin actions across the panel will appear here as they happen.** |
| Generic error toast | `common.errors.generic` | **Something went wrong. Please try again.** |
| Auth error (Discord cancel) | `auth.discord.error.cancelled` | **Discord login was cancelled.** |
| Auth error (provider fail) | `auth.discord.error.provider` | **Couldn't reach Discord. Try again in a moment.** |
| First-login success toast | `auth.discord.success` | **Signed in as :name.** |
| Logout link | `common.actions.logout` | **Log out** |
| Logout confirmation (dropdown — no modal) | `common.actions.logout_confirm` | (none — logout is single-click via POST; not destructive in P1) |
| Theme toggle label (light) | `common.theme.light` | **Light theme** |
| Theme toggle label (dark) | `common.theme.dark` | **Dark theme** |
| Locale switcher label | `common.locale.label` | **Language** |

### Destructive actions in P1

P1 has **no user-facing destructive actions** outside the Filament panel. Filament v3 supplies its own confirmation dialogs (delete, force-delete) which inherit our theme. Spec does not need to redefine destructive copy in P1; future phases (clan disband, match cancel, account deletion) will add destructive entries to `common.actions.*` and `<resource>.confirm.*`.

### Voice & tone

- **Direct, no marketing fluff.** "Log in with Discord", not "Continue your journey with Discord".
- **Sentence case for everything except wordmark.** Buttons, labels, headings — sentence case. Even on the landing CTA: `Log in with Discord`, not `Log In with Discord`.
- **Active voice in errors.** "Couldn't reach Discord" not "Discord could not be reached".
- **No emoji in copy.** Discord interactions in later phases may use emoji; the website chrome does not.

---

## Layout & Interaction Contract (P1 surfaces)

### Page: `/` (Home — logged-out state)

```
┌──────────────────────────────────────────────────────────────┐
│  PublicLayout header                                         │
│   ┌─────────┐                       ┌────────────────────┐  │
│   │Trenchwars│ wordmark (Display)   │ [theme] [language] │  │
│   └─────────┘                       └────────────────────┘  │
│  ────────────────────────────────────────────────────────── │
│                                                              │
│   Display: "The league for clan-organised matches."         │
│   Body-muted: "Schedule scrims, sign up for slots, …"       │
│                                                              │
│   [ Log in with Discord ]   ← primary CTA, accent fill      │
│                                                              │
│  Footer (top-margin 3xl): copyright, links (empty in P1)    │
└──────────────────────────────────────────────────────────────┘
```

- Container max-width: `max-w-3xl` (~768px), centered, `px-6 py-3xl` (24/64).
- Mobile: same stack, full-width buttons (`w-full`), `px-4` outer.
- Hero block vertical rhythm: `gap-6` (24px) between Display, sub-copy, CTA.
- CTA: `Button` primitive, `variant="primary"`, `size="lg"`, full-width on mobile, auto-width on `md+`.
- Discord icon (`lucide-vue-next` `<Discord />` — verified present in lucide; if name differs in installed version, use brand SVG fallback in `components/icons/`) sits left of label inside button at `gap-2`.

### Page: `/` (Home — logged-in stub state)

Same layout as logged-out, but CTA replaced by:
- Heading: `t('home.welcome_back', { name: user.global_name || user.username })`
- Body: short hint text (P1 placeholder): `t('home.next_steps')` — "Clans, matches, and tournaments are landing in upcoming phases."
- No further CTAs in P1.

### Page: `/admin` (Filament panel)

Filament owns its own layout. P1 customisations (in `AdminPanelProvider`):
- `->colors(['primary' => Color::hex('#A4262C')])`
- `->darkMode()` enabled with custom theme
- `->font('Inter')` matching public site
- `->brandName('Trenchwars')` (text only, no logo)
- `->theme(asset('build/admin-theme.css'))` — generated from `resources/css/filament/admin/theme.css` using the same `@theme` token block

Resources: `User`, `Player`, `Role`, `Permission` — Filament's default Resource scaffolding (list/view/edit). No custom column visual treatment in P1 beyond theme tokens.

### Page: `/admin/audit` (custom Filament page)

- Filament Page extending `Filament\Pages\Page`.
- Single `Filament\Tables\Table` with columns: `created_at`, `causer.name`, `event`, `subject_type`, `subject_id`, `description`.
- Filters: causer dropdown, subject_type select, date range (Filament's `DateRangeFilter`).
- Empty state uses copy from `admin.audit.empty.*` (above).
- No edit/delete actions — read-only per `.docs/06-permissions-and-audit.md`.

### Layout primitives delivered in P1

`PublicLayout.vue` and `AuthedLayout.vue` (per `.docs/09-frontend.md`). Header chrome consistent across both.

```
PublicLayout header (shared with AuthedLayout):
  Wordmark (link to /)
  Center: nav slot (empty in P1 — populated in P2: Clans, Players, Matches…)
  Right: ThemeToggle, LocaleSwitcher, [LoginButton | UserMenu]
```

### Components delivered in P1

| Primitive | Purpose | Source |
|-----------|---------|--------|
| `Button` | All buttons; variants `primary` / `secondary` / `ghost`; sizes `sm` / `md` / `lg` | Reka UI base + Tailwind wrapper |
| `IconButton` | Icon-only with 44px touch target | Reka UI base + Tailwind wrapper |
| `ThemeToggle` | Switches `data-theme` on `<html>`; persisted in `localStorage` + synced to `users.theme` later | Composable `useTheme()` |
| `LocaleSwitcher` | P1 shows EN only, dropdown still rendered (single item) for forward-compat | Composable `useLocale()` |
| `Toast` | Inertia flash → `vue-sonner` instance | wrapper `<Toaster />` in root layout |
| `FormField` | Label + control + error message, used by Filament-adjacent custom forms only in P1 | Wrapper composable |
| `Wordmark` | Text-only Trenchwars wordmark (Display, 600, tracking-tight) | Plain Vue SFC |

Domain components from `.docs/09-frontend.md` (`ClanCard`, `MatchCard`, etc.) are **not** built in P1 — Phase 2+.

---

## Accessibility Contract

- **Focus visible everywhere.** All interactive elements get a 2px ring in `--color-focus-ring`, 2px offset from element. Use `:focus-visible` to suppress on mouse click but show on keyboard.
- **Reka UI primitives ship correct ARIA**: do not strip roles/labels.
- **Touch targets**: 44×44px minimum for icon-only on mobile.
- **Skip link**: `<a href="#main">Skip to content</a>` rendered first in `<body>`, visually hidden until focused.
- **`<html lang>`**: bound to active locale via Inertia shared `locale` prop in `app.blade.php`.
- **Keyboard nav**: Tab order matches DOM order; ThemeToggle and LocaleSwitcher reachable via keyboard from header.
- **Reduced motion**: Respect `prefers-reduced-motion` for any transitions added later — token `--motion-duration-fast: 150ms` falls back to `0ms` under reduced motion.
- **Contrast**: see Color section AA table — verified before checker sign-off.

---

## Motion Contract (minimal in P1)

| Token | Value | Usage |
|-------|-------|-------|
| `--ease-default` | `cubic-bezier(0.2, 0, 0, 1)` | Default for hover, focus, theme toggle |
| `--motion-duration-fast` | `150ms` | Hover, focus ring fade |
| `--motion-duration-base` | `200ms` | Theme toggle, button press |

Theme toggle: cross-fade body bg + text color over 200ms when `data-theme` changes — but **only if `prefers-reduced-motion: no-preference`**.

No page transitions in P1 (Inertia visits remain snap-default).

---

## Responsive Breakpoints

Tailwind v4 defaults — kept unchanged.

| Token | Min width | Usage |
|-------|-----------|-------|
| `sm` | 640px | Stack-to-row transitions |
| `md` | 768px | Header full layout |
| `lg` | 1024px | Side-by-side content where applicable |
| `xl` | 1280px | Max comfortable text width |
| `2xl` | 1536px | Large desktop only — content max-width still capped |

Mobile-first per CON-frontend-goals: every component must look correct at 360px viewport before considering desktop.

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| shadcn official | none | not applicable — Vue stack, shadcn is React-only |
| third-party | none | not applicable |

**No external component blocks consumed in P1.** All primitives are owned wrappers around Reka UI (own organisation, MIT-licensed, audited by maintainers of Radix/Reka). The "owned, not a black box" principle in `.docs/09-frontend.md` is the design rule going forward — third-party UI registries are not introduced in P1 and any future addition will require a vetting gate.

---

## Definition of "Visually Correct" for Phase 1 Sign-Off

Implementation passes the contract when:

1. Every element on `/` and `/admin` references semantic tokens (`var(--color-…)`); no raw hex anywhere except `app.css` `@theme`/`:root` blocks.
2. Toggling `data-theme=dark` ↔ `data-theme=light` on `<html>` re-skins the entire app without missed surfaces.
3. AA contrast verified for the four pairs in the contract on both themes.
4. All visible text resolves through `t()` / `__()` — grep for hardcoded strings returns zero matches in `resources/js/pages/Home.vue`, layouts, and the Filament `AdminPanelProvider` brand name (which uses `__()`).
5. Mobile viewport at 360px renders the landing page without horizontal scroll, with the CTA reachable in one thumb-stretch.
6. Filament panel `/admin` chrome shows the deep-red primary, dark theme by default, Inter font.
7. `/admin/audit` page renders the empty state copy when no activity exists.
8. Skip link appears on first Tab press from `<body>`.
9. Lighthouse Accessibility ≥ 95 on both themes for `/`.

---

## Forward-Compat Hooks (declared, not built in P1)

These are token-system contracts that future phases inherit:

- **Per-clan accent override**: `<div data-clan="…" style="--color-accent: …">` scope on clan pages (Phase 2).
- **Light theme parity**: every component must look correct in both themes from day one — do not allow phase 2+ to ship dark-only components.
- **Multi-locale layout slack**: components must tolerate +30% string length without truncation.
- **SSR compatibility**: every primitive must be SSR-safe (no `window` access at module top level). Phase 1 ships SSR config scaffolded; later phases enable.
- **Filament theme parity**: when later phases add Filament resources, theme tokens are reused via the same `theme.css` import — no per-resource overrides.

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
| Component library (Reka UI) | `.docs/09-frontend.md` |
| Tailwind v4 CSS-first | D-001 + CON-stack-frontend-libraries + PROJECT.md |
| Dark default + light option | CON-frontend-goals |
| Accent #A4262C placeholder | PROJECT.md Open Questions ("default accent confirmed (current placeholder: deep red ~#A4262C on muted olive)") |
| Wordmark text-only | PROJECT.md Open Questions ("Logo / mark direction" unresolved) |
| Inter + JetBrains Mono | `.docs/09-frontend.md` |
| i18n key conventions | D-013, `.docs/10-i18n.md`, CONTEXT.md "i18n end-to-end wiring" |
| Login UI surface (CTA on `/`) | CONTEXT.md "Login UI surface" |
| Filament panel + 4 resources | CONTEXT.md "P1 Filament resources" |
| Audit page + empty state | CONTEXT.md + `.docs/06-permissions-and-audit.md` |
| AA contrast requirement | CON-frontend-goals + `.docs/09-frontend.md` Accessibility |
| 44px touch target | accessibility best practice (WCAG 2.5.5 AAA / 2.1 AA target size) |
| Tailwind v4 `@theme` + `@custom-variant dark` syntax | Tailwind Labs official docs (verified via Context7 / ctx7 CLI 2026-05-03) |
