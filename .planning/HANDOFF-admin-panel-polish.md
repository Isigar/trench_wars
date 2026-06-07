# HANDOFF — Admin-panel polish pass

**Created:** 2026-06-07 · **Branch:** `master` (in sync with origin) · **Resume with:** "do the admin-panel queue" + point at this file.

## Context / how we got here

The clan owner is walking the live site tab-by-tab reporting UX issues. The `/my-clan` page is **done + pushed** (commit `17740b1`): active tab persists on save (preserveState), members 3-dots menu (Change role + Remove modals), invites tab shows name+time with a danger revoke-confirm modal, `Button.vue` gained a `danger` variant.

Remaining queue is all **Filament admin panel** (`apps/web/app/Filament/...`). Recon done — root causes + file:line below.

## Conventions (READ FIRST)
- **Container-only** (D-021): tests via `docker compose exec -T web ./vendor/bin/pest ...`; static via `./vendor/bin/pint` + `./vendor/bin/phpstan analyse --no-progress`; Vue via `cd apps/web && ./node_modules/.bin/vue-tsc --noEmit -p tsconfig.json`.
- **Full web suite:** run `./vendor/bin/pest --parallel --processes=4` — the default 24-proc parallel hits a Postgres `max_locks_per_transaction` "out of shared memory" env limit (NOT a code failure).
- **i18n:** every admin label via `__('admin.*')`; lang files in `apps/web/lang/en/*.php`. Filament Livewire tests live in `tests/Feature/Admin` + `tests/Feature/Phase8` (pattern: `Livewire::test(Resource\Pages\X::class)` / `Livewire::test(RelationManager::class, ['ownerRecord'=>$r,'pageClass'=>...])`).
- **Verify in the running panel** at http://localhost:8000/admin (stack is up; nginx :8000). Filament assets may need `docker compose exec -T web php artisan filament:assets` / a vite build for theme.css changes — confirm the build step.

## Queued items

### A — Whole admin panel: "dark green bg should not be there" + fix button styles/CSS
- **Root cause:** `apps/web/resources/css/filament/admin/theme.css` defines a **muted-olive/green dark palette**: `--color-bg:#1A1B16`, `--color-surface:#232518`, `--color-surface-elevated:#2C2E20`, `--color-border:#3A3D2C` (these read as dark green). Primary/accent is `--color-accent:#A4262C` (dark red) and `AdminPanelProvider.php:46` sets Filament `'primary' => Color::hex('#A4262C')`.
- **Why buttons look "danger":** Filament's **primary** color is `#A4262C` (red), so primary action buttons (e.g. "New match") render red and read as danger. (This is the root of item B's button complaint too.)
- **Fix direction:** decide the desired palette with the user (neutral dark grey/slate bg, and a non-red primary — or keep red accent but pick a clearer primary). Update `theme.css` CSS vars + the `Color::hex(...)` primary in `AdminPanelProvider.php`. Rebuild Filament theme asset (`vite build --config vite.filament.config.ts` — see `apps/web/package.json` scripts `build`/`build:filament`). **Ask the user for the target colors before changing — this is a design decision.**

### B — `/admin/matches`: no Remove action + "New match" button danger-styled
- **Remove:** `MatchResource.php:253` — `// INTENTIONALLY no DeleteAction — match deletion cascades to slots/result/mvps.` So it was a deliberate omission. If the user wants delete: add a guarded `Tables\Actions\DeleteAction` (+ confirmation) and/or `DeleteBulkAction`; verify the cascade (FKs) is acceptable, or soft-handle. **Confirm intent with user** (cascade deletes match results/stats).
- **Button color:** fixed by item A (primary color is red). Once primary isn't red, "New match" (the standard Filament `CreateAction`) renders correctly.

### C — `/admin/discord-guilds`: empty edit fields + info div + add button
- **Form fields ARE present:** `DiscordGuildResource.php:47-58` has `guild_id`, `name`, `icon_url` TextInputs. They show **empty because the seeded stub row is unfilled** — `DiscordGuildSeeder.php` creates a stub; the admin is meant to fill guild_id/name/icon after bot setup (docblock line 20). So "empty" = no data yet, not a missing field. Clarify with user (they may just need to type values + save).
- **Add button:** `getPages()` INTENTIONALLY omits `create` to enforce **D-003: exactly one league Discord guild** (one row in `discord_guild`). An Add button would violate D-003. **Do NOT add it without superseding D-003** — surface this to the user.
- **"Info div under title on all pages":** the user wants descriptive help text under each admin page title. Implement via Filament resource `getHeaderWidgets()` / a header view, or `Page::getSubheading()` / `static ?string $navigationGroup` + per-resource subheading. Lightest path: override `getSubheading(): ?string` on List/Edit/View pages, or set it generically. Scope this with the user (all resources vs key ones).

### D — `/admin/events/{id}` View page completely empty
- **Root cause:** `EventResource/Pages/ViewEvent.php` extends `ViewRecord` but **neither `EventResource` nor `ViewEvent` defines an `infolist()`**. Filament falls back to the form for the infolist, but the Event resource form is minimal/empty → blank view.
- **Fix:** add `public static function infolist(Infolist $infolist): Infolist` to `EventResource` (or `infolist()` on `ViewEvent`) with `TextEntry`s for the event fields (title, type, eventable, starts_at, is_public, etc.). Add `admin.event.*` labels if missing.

### E — `/admin/discord-outbound-messages/{id}` View page completely empty
- **Root cause:** same shape — `ViewDiscordOutboundMessage` extends `ViewRecord`; `DiscordOutboundMessageResource.php:61` form is `->schema([])` (empty), so the fallback infolist is empty.
- **Fix:** add an `infolist()` to `DiscordOutboundMessageResource` with `TextEntry`s for message_type, channel_id, recipient_id, status, payload (KeyValueEntry / TextEntry with json), causer, created_at. It's an audit/read surface — read-only entries.

## Suggested order
1. **A first** (with user's chosen colors) — it also resolves B's button complaint and improves everything visually. Needs the Filament theme rebuild step verified.
2. D + E (mechanical: add infolists) — quick, testable with Livewire `assertSuccessful()`.
3. B delete action (confirm cascade intent), C info-div + clarify the empty-fields/add-button expectations vs D-003.

## Open questions for the user (ask before building)
- A: exact target palette (bg + primary color)?
- B: really want match **delete** despite cascade to results/stats?
- C: the discord-guild fields are empty because unfilled — did you expect seeded values, or just need to fill+save? And the single-guild rule (D-003) blocks an "Add" button — override it?
- C: "info div on all pages" — all admin resources, or specific ones, and what content?
