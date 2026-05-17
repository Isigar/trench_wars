---
phase: 01-foundations
plan: 07
subsystem: ui-design-system-tailwind-v4-public-layout
tags:
  - tailwind-v4
  - tailwind-css-first-config
  - tailwindcss-vite-plugin
  - reka-ui-2.9
  - lucide-vue-next
  - fontsource-variable-inter
  - fontsource-variable-jetbrains-mono
  - data-theme-attribute
  - dark-default
  - localstorage-theme-persist
  - ui-spec-tokens
  - public-layout-vue
  - inertia-vue3-link
  - skip-link-a11y
  - focus-ring-2px-visible
  - touch-target-44px
  - cubic-bezier-motion-tokens

# Dependency graph
dependency_graph:
  requires:
    - inertia-vue-bootstrap                # plan 01-06 — createInertiaApp + import '../css/app.css' line already in place
    - inertia-page-home                    # plan 01-06 — Home.vue placeholder we replaced
    - inertia-root-blade                   # plan 01-06 — <html data-theme="dark"> attr already set on the root blade
    - vite-config-ts                       # plan 01-06 — tailwindcss() plugin import was commented out, this plan uncomments
    - resources-css-app-css-placeholder    # plan 01-06 — body{} placeholder we replaced with full @theme block
    - inertia-smoke-test                   # plan 01-06 — still green after Home.vue rewrite (4/4 in Home* + Inertia* sub-suite, 15/15 overall)
    - pest-4-test-framework                # plan 01-05 — LayoutTokensTest extends the harness
    - pint-laravel-preset                  # plan 01-05 — pint clean on the new test file
    - phpstan-level-8-gate                 # plan 01-05 — PHPStan clean (no PHP code outside the new feature test, but gate is verified)
    - boot-healthcheck-test                # plan 01-05 — still green
  provides:
    - tailwind-v4-css-first-pipeline       # apps/web/resources/css/app.css with @import "tailwindcss" + @source + @custom-variant dark + @theme block
    - tailwindcss-vite-plugin-active       # apps/web/vite.config.ts with tailwindcss() in plugins
    - ui-spec-design-tokens                # full UI-SPEC palette (dark default on :root + light on [data-theme=light]) + spacing/radii/motion vars
    - usetheme-composable                  # resources/js/composables/useTheme.ts — reactive theme state, localStorage trenchwars.theme, applies data-theme attr on documentElement
    - ui-button-primitive                  # resources/js/components/ui/Button.vue — primary/secondary/ghost × sm/md/lg
    - ui-iconbutton-primitive              # resources/js/components/ui/IconButton.vue — 44×44 mobile / 36×36 desktop touch target, aria-label required
    - ui-themetoggle-primitive             # resources/js/components/ThemeToggle.vue — Lucide Moon/Sun + useTheme().toggle()
    - ui-wordmark-primitive                # resources/js/components/Wordmark.vue — text-only "Trenchwars" Display 28px / weight 600 / tracking-tight via Inertia <Link href="/">
    - public-layout                        # resources/js/layouts/PublicLayout.vue — skip-link + header (Wordmark + nav slot + locale-switcher slot + ThemeToggle + auth-action slot) + main slot + footer with copyright slot
    - home-page-with-public-layout         # resources/js/pages/Home.vue — wraps content in PublicLayout, hero block per UI-SPEC § Page: `/` (Home — logged-out state), disabled "Log in with Discord" CTA placeholder
    - layout-tokens-feature-test           # tests/Feature/Home/LayoutTokensTest.php — GET / 200 + data-theme="dark" + "Trenchwars" wordmark + manifest.json has resources/css/app.css
    - lucide-vue-next-icon-pack            # node_modules + package.json dep — Moon/Sun used in ThemeToggle; broader use lands in plans 08+
    - inter-jetbrains-mono-self-hosted     # @fontsource-variable/* — woff2 emitted by Vite, loaded via @import in app.css
    - reka-ui-installed                    # 2.9 — primitives wired in plans 08 (LocaleSwitcher) + 09 (LoginButton) + 13+ (RBAC-gated components)
  affects:
    - "01-08 (i18n) — replaces literal English strings in Home.vue + ThemeToggle.vue + Wordmark.vue + PublicLayout.vue with t() calls; NoHardcodedStringsTest comes online here and re-validates this plan's surfaces"
    - "01-09 (Discord OAuth) — replaces the disabled placeholder Button in Home.vue with a real LoginButton wired to /auth/discord/redirect; CTA uses variant=primary already established here"
    - "01-12 (Filament v3) — separate Tailwind v3 pipeline for Filament theme via the dual-Tailwind workaround documented in 01-RESEARCH.md; this plan owns ONLY the public site v4 path"
    - "01-13 (RBAC) — admin-only nav items go into PublicLayout's named nav slot; gating via resolved auth.user shape from middleware"
    - "01-15 (DTO TS types) — shared types (resources/js/types/api.d.ts) compose with these primitives for typed forms in phase 2+"
    - "01-16 (CI matrix) — vue-tsc + pnpm run build already green here; CI re-runs as a regression gate"
    - "01-18 (BLOCKING smoke) — exercises the Home page through nginx to validate dark default + token rendering"
    - "Phase 2+ (clans, players, matches) — every page wraps PublicLayout; every UI surface uses var(--color-*) tokens; no raw hex outside app.css"

# Tech tracking
tech-stack:
  added:
    - "tailwindcss 4.2.4 (CSS-first config — NO tailwind.config.js per UI-SPEC contract; Tailwind v4 reads @theme + @source + @custom-variant directly from app.css)"
    - "@tailwindcss/vite 4.2.4 (replaces postcss-loader pipeline; produces 63 kB minified CSS bundle including all token vars + woff2 font face decls)"
    - "reka-ui 2.9.6 (Vue port of Radix primitives — installed for use in plans 08/09/13+; no Reka components imported in this plan, but the package is on the dep graph)"
    - "lucide-vue-next 1.0.0 (icon pack; only Moon/Sun used here in ThemeToggle, more lands in phase 2 nav + admin)"
    - "@fontsource-variable/inter 5.2.8 (variable font; latin + latin-ext + cyrillic + cyrillic-ext + greek + greek-ext + vietnamese subsets all emitted by Vite)"
    - "@fontsource-variable/jetbrains-mono 5.2.8 (variable mono font for clan tags / Discord IDs / slugs per UI-SPEC § Typography)"
  patterns:
    - "Tailwind v4 CSS-first authoring — every token (color/spacing/radii/motion) lives in @theme inside app.css; semantic palette declared on :root (dark default) + [data-theme=light]; @custom-variant dark targets [data-theme=dark]. NO tailwind.config.js exists. Plan 12 will set up a SEPARATE Tailwind v3 install (aliased as `tailwindcss-v3`) for Filament v3's theme — that workaround is documented in 01-RESEARCH.md and does NOT touch this plan's pipeline."
    - "Theme persistence pattern — useTheme composable owns a module-scope ref<Theme> that survives across component instances; onMounted reads localStorage('trenchwars.theme'); watchEffect mirrors the ref to documentElement.dataset and back to localStorage. SSR-safe: typeof document/localStorage guards. Dark is the default both at the HTML root (server-rendered <html data-theme=\"dark\"> from plan 06's blade) AND in the ref's initial value, so a missing localStorage entry STILL renders dark."
    - "Semantic-tokens-only in component templates — components use bg-[var(--color-surface)] / text-[var(--color-text)] / border-[var(--color-border)] etc. No raw hex outside app.css's :root + [data-theme=*] blocks. UI-SPEC § Color enforces this; the static check that asserts this lands in plan 08 alongside the i18n NoHardcodedStringsTest. This plan's components are written to that contract pre-emptively."
    - "Inertia <Link> for the wordmark — Wordmark.vue imports Link from @inertiajs/vue3 so clicking the wordmark performs a SPA-style Inertia visit to / (no full page reload). Stays consistent with how the rest of the SPA navigates."
    - "44×44 touch target on mobile, 36×36 on desktop — IconButton.vue uses h-11 w-11 md:h-9 md:w-9 (Tailwind sizing × 4px base = 44px / 36px). Padding can be visually smaller, but the click target is preserved per UI-SPEC § Spacing Scale Exceptions."
    - "Disabled CTA placeholder — Home.vue's Button has variant=primary size=lg disabled. The disabled attr triggers Tailwind's disabled:opacity-50 disabled:cursor-not-allowed utilities defined in Button.vue, plus signals to plan 09 that this is the slot to wire to /auth/discord/redirect. Two TODO comments in Home.vue point to plan-08 (i18n strings) + plan-09 (OAuth wire-up) so future agents can grep for them."
    - "@source directives in CSS — Tailwind v4 needs explicit content paths since it no longer reads tailwind.config.js. We declare four: ../views/**/*.blade.php (root view), ../js/**/*.{ts,vue} (Inertia pages + components), ../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php (so paginator utility classes survive bundling), ../../storage/framework/views/*.php (compiled blade — defensive, in case classes only appear post-compile). Paths are RELATIVE to app.css per Tailwind v4's @source resolution rules."
    - "ThemeToggle aria-label is dynamic — computed off the current theme so screen readers announce 'Switch to light theme' when in dark mode and vice-versa. Stays semantically correct as the toggle action changes meaning."

key-files:
  created:
    - apps/web/resources/css/app.css                                      # Tailwind v4 entry: @import tailwindcss + fontsource + @source + @custom-variant + @theme + dark + light palettes + body/html base + reduced-motion + focus-ring + skip-link
    - apps/web/resources/js/composables/useTheme.ts                       # reactive Theme ref + onMounted localStorage hydration + watchEffect mirror to documentElement.dataset.theme + toggle/setTheme exports
    - apps/web/resources/js/components/ui/Button.vue                      # variant (primary/secondary/ghost) × size (sm/md/lg) + type/disabled props; semantic tokens via var(--color-*); transition uses --motion-duration-fast + --ease-default
    - apps/web/resources/js/components/ui/IconButton.vue                  # h-11 w-11 md:h-9 md:w-9 (44/36 touch target); aria-label required prop; muted text → text-[var(--color-text)] on hover
    - apps/web/resources/js/components/ThemeToggle.vue                    # IconButton + Lucide Moon/Sun; calls useTheme().toggle(); dynamic aria-label
    - apps/web/resources/js/components/Wordmark.vue                       # Inertia <Link href="/"> with Display 28px / weight 600 / tracking-tight; hover → accent
    - apps/web/resources/js/layouts/PublicLayout.vue                      # skip-link → main; header (Wordmark + nav slot + locale-switcher slot + ThemeToggle + auth-action slot); main slot; footer with named slot + default copyright
    - apps/web/tests/Feature/Home/LayoutTokensTest.php                    # 2 it-blocks: data-theme="dark" + "Trenchwars" in HTML; manifest.json contains resources/css/app.css entry
  modified:
    - apps/web/package.json                                                # +reka-ui ^2.9 +lucide-vue-next +@fontsource-variable/inter +@fontsource-variable/jetbrains-mono in dependencies (tailwindcss + @tailwindcss/vite were already in devDeps from plan 04 default install — pinned ^4.2.4)
    - apps/web/pnpm-lock.yaml                                              # 21 packages added (4 direct + transitive)
    - apps/web/vite.config.ts                                              # uncommented `import tailwindcss from '@tailwindcss/vite'` + added `tailwindcss()` to plugins array
    - apps/web/resources/css/app.css                                       # full overwrite — replaced placeholder body{} with the complete UI-SPEC token system
    - apps/web/resources/js/pages/Home.vue                                 # replaced 11-line placeholder with PublicLayout-wrapped hero (H1 + p + disabled "Log in with Discord" CTA Button); added two TODO comments for plan-08 (i18n) + plan-09 (OAuth)

key-decisions:
  - "Dark is the default at the HTML root AND in useTheme's initial ref value — a missing localStorage entry renders dark; the SSR-rendered <html data-theme=\"dark\"> attribute matches the client-hydrated state with no flicker."
  - "Tokens authored on :root, :root duplicated under [data-theme=dark] selector — guarantees dark applies even if a future agent removes the data-theme attr from the root blade. Light tokens live ONLY under [data-theme=light]."
  - "Tailwind v4 plugin loaded via @tailwindcss/vite, NOT via PostCSS — gives us @theme + @custom-variant + @source authoring in app.css with no separate config file. Filament theme (plan 12) keeps a SEPARATE Tailwind v3 install (aliased) per the dual-Tailwind workaround in 01-RESEARCH.md."
  - "Wordmark uses Inertia <Link href=\"/\"> — clicking the brand performs an Inertia visit, not a full page reload. Stays consistent with the SPA navigation pattern."
  - "Literal English strings stay in templates for now — plan 08 swaps them to t() and turns on the NoHardcodedStringsTest. TODO(plan-08) comments mark every literal site for the next pass."
  - "Disabled placeholder CTA in Home.vue rather than a fully-styled button — encodes that the OAuth flow is plan-09's responsibility and gives plan-09 a clear textual hook (TODO(plan-09)) to wire to /auth/discord/redirect."
  - "IconButton requires a `label: string` prop — non-optional accessibility contract; aria-label is the only way screen readers can name an icon-only button. Dynamic via :aria-label binding so ThemeToggle can announce 'Switch to dark theme' / 'Switch to light theme' contextually."

patterns-established:
  - "Pattern: UI primitive surface area = (Button, IconButton, ThemeToggle, Wordmark) + (PublicLayout) — every Phase 2+ page wraps PublicLayout and uses these primitives instead of authoring from scratch."
  - "Pattern: variant + size as withDefaults props — Button.vue exposes variant: 'primary'|'secondary'|'ghost' and size: 'sm'|'md'|'lg' as the canonical shape. Future Reka-UI-backed primitives (Dialog, Popover, Toast) will adopt the same API."
  - "Pattern: semantic-only token references in templates — every color/border/bg in a component template is var(--color-*) from app.css. Raw hex would have to come from app.css's :root or [data-theme=*] blocks (which is the only place hex is allowed)."
  - "Pattern: data-theme attr on <html> + localStorage('trenchwars.theme') — locale persistence (plan 08) will follow the same shape: 'trenchwars.locale' key on localStorage + a corresponding HTML attr or middleware-driven cookie."
  - "Pattern: TODO(plan-NN) inline comments — every deferred piece (i18n strings, OAuth wire-up) is marked with a grep-able TODO referencing the next plan number; future agents grep for `TODO(plan-08)` to find every i18n hook etc."

requirements-completed:
  - REQ-constraint-railway-deploy
  - REQ-constraint-en-launch-i18n-ready

# Metrics
duration: 5min
completed: 2026-05-04
---

# Phase 1 Plan 07: Tailwind v4 + UI Primitives + Public Layout Summary

**Tailwind v4 CSS-first pipeline plus the full UI-SPEC design-token system, the P1 primitive set (Button / IconButton / ThemeToggle / Wordmark / PublicLayout), and a Home page wired through PublicLayout with a placeholder OAuth CTA — all gated by Pest, Pint, and PHPStan level 8.**

## Performance

- **Duration:** ~5 min (286s wall clock)
- **Started:** 2026-05-04T17:24:45Z
- **Completed:** 2026-05-04T17:29:31Z
- **Tasks:** 2/2 (both `type=auto tdd=true`)
- **Files modified:** 5
- **Files created:** 7

## Accomplishments

- Tailwind v4 CSS-first pipeline live: `app.css` declares `@import "tailwindcss"`, `@source` directives, `@custom-variant dark`, `@theme` (fonts/spacing/radii/motion), and full UI-SPEC dark + light palettes on `:root` and `[data-theme=light]`. NO `tailwind.config.js` exists.
- `@tailwindcss/vite` plugin uncommented in `vite.config.ts`; build produces 63.07 kB CSS bundle containing every token var (`--color-bg`, `--color-accent`, `--color-text`, …) plus woff2 font subsets for Inter + JetBrains Mono.
- Five P1 UI primitives authored to the UI-SPEC contract: `Button` (variant × size), `IconButton` (44×44 mobile / 36×36 desktop touch target, required aria-label), `ThemeToggle` (Moon/Sun via Lucide), `Wordmark` (Inertia `<Link href="/">`), `PublicLayout` (skip-link + header w/ named slots + main + footer).
- `useTheme` composable persists `data-theme` to `localStorage('trenchwars.theme')`, with dark as the default at both the SSR HTML root and the client ref initial value (no flicker).
- `Home.vue` rewritten through `PublicLayout` — hero block with H1 tagline + sub-copy + disabled "Log in with Discord" `Button variant=primary size=lg` placeholder. Plan-08 (i18n) and plan-09 (OAuth) hooks marked with `TODO(plan-NN)` comments.
- LayoutTokensTest asserts the public layout: `data-theme="dark"` + "Trenchwars" wordmark text in HTML + `manifest.json` contains the css entry. InertiaSmokeTest still passes after Home.vue rewrite. Full Pest suite: **15/15 green** (37 assertions).

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Tailwind v4 + Reka UI + Lucide + Fontsource; author app.css with full UI-SPEC `@theme` block; uncomment Tailwind plugin in vite.config.ts** — `283b33b` (feat)
2. **Task 2: Author UI primitives (Button, IconButton, ThemeToggle, Wordmark, PublicLayout) and update Home.vue** — `87afe5e` (feat)

**Plan metadata commit:** added by the closing docs commit (this SUMMARY + STATE + ROADMAP).

_Note: this plan's tasks were marked `tdd="true"` but the plan body specifies a single combined verify-by-build cycle for task 1 (build must succeed; manifest must contain css) and a feature-test for task 2 (LayoutTokensTest). Both are written + run before commit per the plan's `<verify>` blocks. No separate RED commit was required because the plan author explicitly opted for one combined GREEN per task — see plan body verification-first wording._

## Files Created/Modified

**Created:**
- `apps/web/resources/js/composables/useTheme.ts` — reactive Theme ref + localStorage hydration + watchEffect mirror to `documentElement.dataset.theme`; exports `theme`, `toggle`, `setTheme`.
- `apps/web/resources/js/components/ui/Button.vue` — variant × size primitive; semantic tokens only.
- `apps/web/resources/js/components/ui/IconButton.vue` — `h-11 w-11 md:h-9 md:w-9` touch target; required `label` prop for aria-label.
- `apps/web/resources/js/components/ThemeToggle.vue` — IconButton + Lucide Moon/Sun + dynamic aria-label off `useTheme().theme`.
- `apps/web/resources/js/components/Wordmark.vue` — Inertia `<Link>` with Display 28px / weight 600 / tracking-tight.
- `apps/web/resources/js/layouts/PublicLayout.vue` — skip-link + header (Wordmark + 3 named slots + ThemeToggle) + main slot + footer slot.
- `apps/web/tests/Feature/Home/LayoutTokensTest.php` — 2 it-blocks (dark theme + manifest css entry).

**Modified:**
- `apps/web/package.json` — added `reka-ui ^2.9`, `lucide-vue-next`, `@fontsource-variable/inter`, `@fontsource-variable/jetbrains-mono`. (`tailwindcss` + `@tailwindcss/vite` were already in devDeps from plan 04's Laravel 12 default install — pinned at `^4.2.4`.)
- `apps/web/pnpm-lock.yaml` — 21 packages added (4 direct + transitive).
- `apps/web/vite.config.ts` — uncommented `import tailwindcss from '@tailwindcss/vite'` and added `tailwindcss()` to the plugins array.
- `apps/web/resources/css/app.css` — full overwrite from placeholder `body { font-family: ... }` to the complete UI-SPEC token system.
- `apps/web/resources/js/pages/Home.vue` — replaced placeholder content with `PublicLayout` wrap + hero block + disabled OAuth placeholder Button.

## Decisions Made

- **Dark default lives in two places** — the SSR-rendered `<html data-theme="dark">` attribute (set in `app.blade.php` from plan 06) AND the `theme` ref's initial `'dark'` value in `useTheme.ts`. Both must agree to avoid hydration flicker. If a user has a `'light'` value in localStorage, `onMounted` flips both the ref and the DOM attribute on the next tick.
- **Tokens declared on `:root` AND `[data-theme=dark]`** — duplicated, not de-duplicated. This guarantees dark renders even if a future agent strips the `data-theme` attribute from the root blade.
- **Reka UI is installed but not yet imported** — the package is on the dep graph for plans 08 (LocaleSwitcher), 09 (LoginButton), 13+ (RBAC-gated components, Dialog, Popover). This plan keeps the import surface minimal; Reka primitives land where they're actually used.
- **Lucide imports are tree-shakeable** — `import { Moon, Sun } from 'lucide-vue-next'` only pulls those two icons into the bundle. Phase-2+ nav will add more icons via the same import pattern.
- **Variable fonts via @fontsource-variable** — gets us a single woff2 per subset that covers all weights (200..900); Vite emits 14 woff2 files (latin/latin-ext/cyrillic/cyrillic-ext/greek/greek-ext/vietnamese × 2 fonts). The CSS bundle's `@font-face` declarations all resolve to `/build/assets/*.woff2`.
- **Disabled placeholder CTA** — Home.vue's Button has `disabled` set; the disabled state's opacity comes from Button.vue's `disabled:opacity-50 disabled:cursor-not-allowed` utility classes. Plan-09 will remove `disabled` and bind `@click` to a Form post to `/auth/discord/redirect`.

## Deviations from Plan

None — plan executed exactly as written. The only environmental note (not a deviation): docker-compose was stopped at agent start; daemon was reachable but containers (`trenchwars-web` + postgres + redis) were `Exited (255)`. Brought them up via `docker compose up -d web postgres redis`, skipping the rcon-worker service because its build is failing (out of scope for this plan — pre-existing blocker; logged below).

## Out-of-Scope Discoveries

- **rcon-worker docker build failure** — `docker compose up -d` (without service filter) attempts to rebuild `trenchwars-rcon-worker` which fails at `RUN pnpm install --frozen-lockfile=false --filter @trenchwars/rcon-worker... && cd apps/rcon-worker && pnpm build` (exit code 2). Did NOT fix here — out of scope for plan 01-07. Worked around by starting only `web postgres redis`. The rcon-worker image is needed only for plan 08+ in dev (per the phase plan inventory) and for the Phase 8 implementation. Not blocking this plan; logging in `deferred-items.md` for the phase.
- **Pre-existing untracked `.docs/` directory** at the repo root — pre-existed before this plan started; intentionally NOT touched. Listed in the original plan-01 work as "Frozen design docs (read-only reference)" per CLAUDE.md § 5.

## Auth Gates

None.

## Verification

- `docker compose exec web pnpm run build` — Vite v7.3.2 succeeded; emitted `public/build/manifest.json`, `public/build/assets/app-BZvmeeTV.css` (63.07 kB), `public/build/assets/Home-CsRfHK33.js` (6.11 kB), `public/build/assets/app-CUaLTq36.js` (264.23 kB), 14 woff2 font subsets.
- `grep -oE -- '--color-accent[^;]*' public/build/assets/app-*.css` — emitted both `--color-accent:#a4262c` (dark) and `--color-accent:#8e1e22` (light); the entire UI-SPEC palette survived Tailwind's CSS minifier.
- `docker compose exec web ./vendor/bin/pest tests/Feature/Home/LayoutTokensTest.php tests/Feature/InertiaSmokeTest.php` — **4/4 PASS** (both new tests + both pre-existing Inertia tests).
- `docker compose exec web ./vendor/bin/pest` — full suite **15/15 PASS** (37 assertions, 0.45s).
- `docker compose exec web ./vendor/bin/pint --test` — **PASS** (39 files, no style issues).
- `docker compose exec web ./vendor/bin/phpstan analyse --no-progress` — **OK No errors** (level 8).

## Threat Surface Scan

Reviewed files created/modified vs plan's `<threat_model>`:
- T-1-19 (XSS via Vue templates) — Vue auto-escapes `{{ }}` in all new components. No `v-html` introduced anywhere. Wordmark's "Trenchwars" + Home's tagline + sub-copy + button label are static strings, not user input. **Mitigated.**
- T-1-20 (localStorage information disclosure) — `useTheme.ts` stores only `'dark'` or `'light'` under `'trenchwars.theme'`. No PII, no auth tokens. **Accepted.**

No new security-relevant surface introduced beyond what the plan's threat register covered.

## Known Stubs

- **`Home.vue` "Log in with Discord" Button is `disabled`** — placeholder for plan 09's Discord OAuth wire-up. Marked with `<!-- TODO(plan-09): Wire to /auth/discord/redirect. -->` and `<!-- TODO(plan-08): Replace literal strings with t('auth.discord.button_label') etc. -->`. Intentional stub; resolved in plan 09. Documented in plan 01-07's task 2 action notes; documented in plan 01-09's `<context>` (planner's responsibility — not this executor's).
- **`PublicLayout.vue` `nav` named slot** — empty in P1; populated in Phase 2 (Clans, Players, Matches links). Marked with HTML comment in the layout. Intentional, not a bug.
- **`PublicLayout.vue` `locale-switcher` named slot** — empty in P1; populated in plan 08 (i18n LocaleSwitcher Reka primitive). Intentional.
- **`PublicLayout.vue` `auth-action` named slot** — empty in P1; populated in plan 09 (LoginButton when logged-out, UserMenu when logged-in). Intentional.

These stubs are part of the slot-based composition contract and resolve in subsequent plans per the `affects` dependency graph above.

## TDD Gate Compliance

The plan tasks are marked `tdd="true"` but use a "verify-by-build + verify-by-test" combined cycle rather than the strict RED→GREEN→REFACTOR three-commit sequence. Both tasks committed as `feat(01-07): ...` after running:
- Task 1 verify: `pnpm run build` exits 0 + grep tokens in manifest output.
- Task 2 verify: LayoutTokensTest written + run + green BEFORE commit (test was authored alongside the components, so the test was effectively "GREEN-on-arrival"; no separate RED commit).

The plan author's `<verify>` blocks specify this combined cycle explicitly (single test command at the end of task 2). Non-compliance with the strict three-commit pattern is acknowledged here for plan-level audit; both `feat` commits include their respective verification rationale.

## Self-Check: PASSED

**Files (12/12 FOUND):**
- apps/web/resources/css/app.css — FOUND
- apps/web/resources/js/composables/useTheme.ts — FOUND
- apps/web/resources/js/components/ui/Button.vue — FOUND
- apps/web/resources/js/components/ui/IconButton.vue — FOUND
- apps/web/resources/js/components/ThemeToggle.vue — FOUND
- apps/web/resources/js/components/Wordmark.vue — FOUND
- apps/web/resources/js/layouts/PublicLayout.vue — FOUND
- apps/web/resources/js/pages/Home.vue — FOUND
- apps/web/tests/Feature/Home/LayoutTokensTest.php — FOUND
- apps/web/vite.config.ts — FOUND
- apps/web/package.json — FOUND
- apps/web/pnpm-lock.yaml — FOUND

**Commits (2/2 FOUND in git log):**
- 283b33b — FOUND (Task 1: Tailwind v4 + Reka UI + tokens)
- 87afe5e — FOUND (Task 2: UI primitives + Home.vue + LayoutTokensTest)
