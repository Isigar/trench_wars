# Trenchwars — Launch Checklist

Sequential checklist that takes Trenchwars v1.0 from a first Railway deploy to live-with-users. Consolidates the per-phase `PENDING_MANUAL_SMOKE` items from every `*-PHASE-VERIFICATION.md` under `.planning/milestones/v1.0-phases/` into one operator-driven walkthrough.

Cross-references:

- Deploy walkthrough → [`DEPLOYMENT.md`](./DEPLOYMENT.md)
- Env-var matrix → [`CONFIGURATION.md`](./CONFIGURATION.md)
- Conventions + container-only rule → [`CLAUDE.md`](./CLAUDE.md)
- Per-phase verifications → `.planning/milestones/v1.0-phases/{01..09}/*-PHASE-VERIFICATION.md`

All commands run **inside the relevant Railway service's one-off shell or container** (CLAUDE.md §1 / D-021). For local-dev parity, swap `php artisan ...` for `make artisan ARGS="..."` from the repo root.

---

## Table of Contents

1. [Pre-flight](#1-pre-flight)
2. [First-boot](#2-first-boot)
3. [Smoke — consolidated 9-phase manual verification](#3-smoke--consolidated-9-phase-manual-verification)
4. [Go-live sign-off](#4-go-live-sign-off)

---

## 1. Pre-flight

Tick before triggering the first deploy. See [`DEPLOYMENT.md`](./DEPLOYMENT.md) §1-3 for the underlying procedures and [`CONFIGURATION.md`](./CONFIGURATION.md) for the env-var matrix.

### Railway project

- [ ] Railway project deploys from the **`master` ref (or a freshly re-cut `v1.0` tag at current HEAD)** — NOT the existing `v1.0` tag, which is stale: it predates the production-readiness fixes (SSR bundle build, dedicated `scheduler` service, bot tournament/bracket-announce fix, TrustProxies, `DB_URL`/`DATABASE_URL` fallback). See Go-live sign-off for the rationale.
- [ ] Postgres 16 plugin added; `DATABASE_URL` captured. (The app reads `DATABASE_URL` directly — reference it as `DATABASE_URL=${{Postgres.DATABASE_URL}}` on Web env; `DB_URL` is an accepted alias, split `DB_*` keys are the fallback.)
- [ ] Redis 7 plugin added; `REDIS_URL` captured.
- [ ] **6 application services** created with the **Nixpacks builder** and correct Root Directories: `web`, `ssr`, `worker`, `scheduler` (all Root Directory `apps/web`), `bot` (`apps/bot`), `rcon-worker` (`apps/rcon-worker`). Do **not** point the `web` service at `docker/web/Dockerfile` (php-fpm only — fails `/up`). See `DEPLOYMENT.md` §3.
- [ ] Per-service **start-command overrides** set in the dashboard: `ssr` → `php artisan inertia:start-ssr`; `worker` → `php artisan horizon`; `scheduler` → `php artisan schedule:work`. (`web`/`bot`/`rcon-worker` use their default start.)
- [ ] **`scheduler` service running** — without it all four cron jobs (articles publish, sitemap, notification dispatch/prune) are dead (Horizon does NOT run `schedule:run` — D-022).
- [ ] Web env group populated (Web env shared by `web`, `ssr`, `worker`, `scheduler`).
- [ ] Bot env group populated.
- [ ] Worker env group populated (for `rcon-worker`).
- [ ] `WEB_HMAC_SECRET` set to the **same value** on Web env and Worker env (`openssl rand -hex 32` — min 32 chars).
- [ ] `APP_KEY` set on Web env (generated locally via `php artisan key:generate --show`; do not let release-command mutate `.env`).
- [ ] `FILESYSTEM_DISK=s3` on Web env with the S3-compatible bucket credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT` for non-AWS providers). **Required** — `FILESYSTEM_DISK=local` loses every upload on each redeploy (Railway disk is ephemeral). See `CONFIGURATION.md` §3.
- [ ] `INERTIA_SSR_ENABLED=true` and `INERTIA_SSR_URL` pointing at the `ssr` service private domain on the Web env group (SSR is enabled for v1.0).
- [ ] `CACHE_STORE=redis` on Web env (required by Phase 9 plan 09-05 — D-09-05-A).
- [ ] `SESSION_SECURE_COOKIE=true` on Web env (production HTTPS).
- [ ] `APP_DEBUG=false` and `APP_ENV=production` on Web env.
- [ ] Web service **release / pre-deploy command** is `php artisan migrate --force && php artisan storage:link` (ships in `apps/web/railway.json` → `deploy.preDeployCommand`; migrations are automated — do not run by hand). Clear this command on `ssr`/`worker`/`scheduler` so only `web` runs it.

### Discord — OAuth application

- [ ] Discord OAuth application created at <https://discord.com/developers/applications>.
- [ ] Client ID + Client Secret copied; set as `DISCORD_CLIENT_ID` / `DISCORD_CLIENT_SECRET` on Web env.
- [ ] Redirect URI added at Discord Developer Portal → OAuth2 → Redirects: `https://<your-domain>/auth/discord/callback` (no trailing slash; verbatim match — CLAUDE.md §6 Pitfall 2). Same string set as `DISCORD_REDIRECT_URI` on Web env.

### Discord — Bot application

- [ ] Discord bot application created (separate from OAuth or same Application — both work).
- [ ] Bot Token reset and captured; set as `DISCORD_BOT_TOKEN` on Bot env.
- [ ] Privileged Gateway Intents → **Server Members Intent ON** (Phase 5 plan 05-08 RESEARCH §Pitfall 6 — without it `guildMemberUpdate` events never fire and role-sync silently breaks).
- [ ] Bot added to the league guild via OAuth2 URL with scopes `bot applications.commands` and permissions `Send Messages`, `Embed Links`, `Manage Roles`, `View Channels`.
- [ ] Application ID set as `DISCORD_APPLICATION_ID` on Bot env.
- [ ] League guild Server ID set as `DISCORD_GUILD_ID` on Bot env (Discord Settings → Advanced → Developer Mode ON → right-click guild → Copy Server ID).
- [ ] `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` set to the production announce channel snowflake — **on both Web env and Bot env** (audit-hotfix `cdfbfa5`). Bot env value is the fallback when the outbound row's `channel_id` is empty.

### CRCON

- [ ] At least one HLL server provisioned with CRCON server **≥ v10.0.0** (`apps/web/config/rcon.php` `crcon_version_pin`).
- [ ] CRCON websocket endpoint reachable from a Railway egress IP (allow-list Railway's egress range or use a tunnel — Railway egress IPs documented at <https://docs.railway.app/>).
- [ ] CRCON API token captured for entry into the `match_servers.crcon_credentials` encrypted column via the Filament admin UI (Phase 8 plan 08-09 — done in the smoke phase, not pre-flight).

### Local prep (optional but recommended)

- [ ] `apps/web/.env` populated locally with **dev-only** Discord OAuth values (separate application from production).
- [ ] `make up && make migrate && make seed` runs green locally.
- [ ] `make pest`, `make pint ARGS="--test"`, `make phpstan` all green locally.

---

## 2. First-boot

Run **after** the first Railway deploy reports all services Healthy. All `php artisan` commands run via Railway's one-off shell on the `web` service (or `railway run -s web --` from the Railway CLI). The release command on `web` (`php artisan migrate --force && php artisan storage:link`) has already executed by the time you reach this section.

- [ ] **Migrations verified.** Run `php artisan migrate:status` — all migrations from Phase 1 through Phase 9 show `Ran`. v1.0 ships ~50+ migrations across the 9 phases.
- [ ] **Seed the database** (idempotent — safe to re-run):
      ```bash
      php artisan db:seed --force
      ```
      Per `apps/web/database/seeders/DatabaseSeeder.php` this calls 8 seeders in order: `PermissionSeeder`, `ModeratorRoleSeeder`, `DiscordGuildSeeder`, `BotServiceUserSeeder`, `RconWorkerSystemUserSeeder`, `ClanTagSeeder`, `GameSeeder` (HLL preset — 1 Game + 15 Roles + 5 MatchTypes + capacity matrix), `CategorySeeder` (4 starter CMS categories).
- [ ] **Bot token issued + wired:**
      ```bash
      php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90
      ```
      Plaintext shown once. Paste into Bot env group → `WEB_API_TOKEN`. Restart the `bot` service so the new env picks up.
- [ ] **RCON worker token issued + wired** (separate name for scoped revocation):
      ```bash
      php artisan trenchwars:bot:issue-token --name=rcon-worker-prod --ttl=90
      ```
      Paste into Worker env group → `WEB_API_TOKEN`. Restart `rcon-worker`.
- [ ] **Storage link** (already run by release command; idempotent):
      ```bash
      php artisan storage:link
      ```
- [ ] **First Discord login.** Open `https://<your-domain>/` → click **Log in with Discord** → complete OAuth. Verify a `users` + `players` + `player_privacy` row was created for your account. (Filament admin auto-provisions on first login — Phase 1 plan 01-09 `ProvisionFirstLogin` listener.)
- [ ] **Grant yourself admin:**
      ```bash
      php artisan trenchwars:make-admin <YOUR_DISCORD_USER_ID>
      ```
      Reload `/admin` and confirm Filament loads (panel guard `web`, gated by `admin-access` permission per Phase 1 plan 01-12).
- [ ] **(Optional) Grant CMS editor:** for a non-admin user managing articles:
      ```bash
      php artisan trenchwars:make-cms-editor <THEIR_DISCORD_USER_ID>
      ```
- [ ] **(Optional) Generate initial sitemap** (otherwise the `scheduler` service auto-runs it at 03:00 UTC):
      ```bash
      php artisan sitemap:generate
      ```
- [ ] **Scheduler ticking.** Tail `scheduler` logs and confirm `schedule:run` fires every minute (e.g. `articles:publish-scheduled ... ran successfully`). If nothing ticks, the `scheduler` service is missing or has the wrong start command (Horizon does NOT run the scheduler — D-022).
- [ ] **Tail logs** on each service (`web`, `ssr`, `worker`, `scheduler`, `bot`, `rcon-worker`) for 5 minutes — no recurring errors. In particular, `bot` should log a successful Discord client `ready` event; `rcon-worker` should log "no bookings due" idling.

---

## 3. Smoke — consolidated 9-phase manual verification

Each row consolidates the `PENDING_MANUAL_SMOKE` items from the matching phase's `*-PHASE-VERIFICATION.md` document. Tick when you have **personally clicked through the path on the production deploy** and observed the expected outcome.

### Phase 1 — Foundations + auth

- [ ] **Discord OAuth happy path.** Visit `/auth/discord/redirect` while logged out → Discord prompts → callback completes → Inertia lands you on `/`. Inspect Filament dual-Tailwind: `/admin` loads without CSS clash; styles from both Tailwind v4 (rest of app) and the aliased Tailwind v3 (Filament panel theme) cohabit per Phase 1 plan 01-12. Phase 1 verification SC-1 / SC-3.

### Phase 2 — Clans & tags

- [ ] **Create a clan** at `/admin/clans` (or via "My Clan" public surface if your user is not admin).
- [ ] **Invite a second user.** Have a second Discord account log in once, then issue an invite to them from "My Clan → Invites" → they accept → confirm they appear under "Members".
- [ ] **Public directory renders.** `/clans` lists both clans (or just one if you only created one); `/clans/{slug}` shows the clan page with active members.
- [ ] **Player privacy gate.** Visit `/players/{slug}` of a user whose `player_privacy.global` is set to `private` — confirm the page redacts the gated sections (per-section privacy per D-018; `PlayerPrivacyGate` per Phase 2 plan 02-05).

### Phase 3 — Games & match types

- [ ] **HLL preset visible.** Open `/admin/games` → HLL row visible → drill in → confirm 15 roles (Commander, Infantry Officer, Rifleman, … Tank Crewman) and 5 match types (Scrim 50v50, Skirmish 6v6, Friendly, Tournament, Clan War) with capacity matrix populated (Phase 3 plan 03-05).

### Phase 4 — Matches (manual)

- [ ] **Officer creates a Scrim 50v50.** As a clan officer, create a match for `match_type=Scrim 50v50` → confirm 50 `match_slots` rows materialise from the GameMatchTypeRoleLimit snapshot (Phase 4 plan 04-05 `MatchSlotMaterialiserService`).
- [ ] **Concurrent signup race.** Have two users attempt to signup for the same slot concurrently (refresh-race two browsers; or use `curl -X POST` twice in quick succession). Confirm exactly **one** succeeds and the other receives `SlotAlreadyFilledException`-flavoured error (Phase 4 plan 04-06 — D-010 5-guard row-locked order).
- [ ] **Manual result + audit.** As admin, enter a `MatchResult` from the Filament wizard. Open `/admin/audit` and confirm the audit row lands with your user as the causer (D-012 + Phase 4 plan 04-12 `MatchAuditLogTest`).

### Phase 5 — Discord bot v1

- [ ] **`/clan list` slash command** in the league guild returns an embed within the 3-second interaction window (Phase 5 SC-1).
- [ ] **`/match signup` modal** opens, posts to web's API, confirms the slot. Refresh the public match page — the user appears in the slot (Phase 5 SC-2).
- [ ] **Outbound delivery end-to-end.** Admin announces something that enqueues an outbound row (e.g. create a match — `MatchObserver` writes a `match_announce` row). Within the bot's poll interval (default 5s), the embed appears in the configured channel. Confirm `discord_outbound_messages.status` transitions `pending → sent` (Phase 5 SC-3).
- [ ] **Role sync on clan join.** Have a user accept a clan invite. The bot's `SyncDiscordRolesJob` (Horizon-retried) grants the clan role in Discord within seconds (Phase 5 plan 05-06).
- [ ] **`guildMemberUpdate` reconciliation.** Manually strip the clan role from a member in Discord. Within a minute, the bot's reconciler restores it from web (Phase 5 plan 05-11). Then strip the membership in `/admin` and confirm the Discord role drops.
- [ ] **Sanctum scope rejection.** Attempt an admin-only API endpoint with the bot's PAT — confirm 403 (the bot's token has `bot:*` abilities only, not `admin:*` — Phase 5 plan 05-03 T-05-01-05).

### Phase 6 — Tournaments & brackets

- [ ] **Create 8-clan single-elimination tournament.** From `/admin/tournaments`: create + add 8 clan participants + seed by rank → generate brackets. Confirm 7 bracket-match rows materialise (1 final + 2 semis + 4 quarters; Phase 6 plan 06-06 single-elim with inner_outer + byes pattern).
- [ ] **Public bracket renders.** Open `/tournaments/{slug}` → Bracket tab → SVG `BracketCanvas` paints the 8-clan tree. Click a match → public match page opens.
- [ ] **Advance via match result.** Enter a result on a quarter-final match (or use the manual flow). `MatchResultObserver` (`created` AND `updated` two-hook idiom, D-06-08-A) auto-advances the winner to the next round. Refresh `/tournaments/{slug}` → winner now appears in the semis.
- [ ] **Bot embed on bracket creation.** If `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` is configured, a tournament-created announcement embed lands in the announce channel.

### Phase 7 — CMS

- [ ] **CMS editor publishes scheduled article.** As a `cms-editor`, create an article in Filament with `scheduled_at = now + 2 minutes`. Wait 2-3 minutes. `articles:publish-scheduled` (cron `every minute`, both guards on) flips status to `published`. Confirm:
      - `/blog/{slug}` renders the published HTML (Tiptap converter — Phase 7 plan 07-05 Pitfall 10).
      - `/blog` index lists it.
      - Discord announce embed lands in `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID`.
      - `/admin/audit` shows the published transition with the operator as causer.
- [ ] **FullCalendar at `/events`.** Toggle month/week/day views. Click an event → navigates to the underlying match/tournament/article page (Phase 7 plan 07-10).
- [ ] **Search.** `/search?q=<something>` returns ranked results from articles + clans + players (Postgres FTS with `plainto_tsquery`, privacy-gated via `PlayerPrivacyGate` — Phase 7 plan 07-08).
- [ ] **Sitemap.** `curl https://<your-domain>/sitemap.xml` → 200 with article + clan + tournament URLs. Daily regen at 03:00 UTC.
- [ ] **SSR first paint.** `curl -s https://<your-domain>/ | grep '<div id="app"'` — confirm the SSR output includes pre-rendered HTML inside the Inertia mount (not just a blank `<div id="app">` with a `data-page` JSON blob). Also confirm `<html lang="en">` reflects the resolved locale (Phase 7 plan 07-11 Pitfall 8).

### Phase 8 — RCON automation

- [ ] **Register a MatchServer.** `/admin/match-servers` → New → enter CRCON URL + token + ws path. The `crcon_credentials` JSON encrypts at rest (`encrypted:array` cast — Phase 8 plan 08-03).
- [ ] **Test Connection action GREEN.** Click "Test Connection" on the MatchServer row. `TestMatchServerConnectionJob` + `CrconHealthProbe` returns success within the timeout (Phase 8 plan 08-09).
- [ ] **Book a match.** From the Match admin, set a MatchServer + scheduled_start/end. A `match_server_bookings` row materialises with the Postgres `EXCLUDE USING gist` exclusion constraint covering `[scheduled_start − 5m, scheduled_end + 30m]` (Phase 8 plan 08-02). Overlapping bookings on the same server are rejected at the DB level.
- [ ] **Worker ingests live events.** Run a friendly match on the registered server. Tail `rcon-worker` logs: `BookingScheduler` picks up the booking, `MatchLifecycleManager` opens CRCON ws, events stream via `CrconEventNormaliser`. Each event POSTs HMAC-signed to `/api/internal/match/{id}/events`. Confirm `match_events` rows accumulate.
- [ ] **CloseMatchJob writes MatchResult source='rcon'.** On `match_end` event: `MatchPlayerStatAggregator` + `MatchResultService::upsertFromRcon` populate `match_results` with `source='rcon'` and `match_player_stats` with per-player K/D/score (Phase 8 plan 08-08). `MatchResultObserver` then auto-advances any tournament bracket.
- [ ] **Manual override wins.** Edit the MatchResult in Filament. The override persists; `ManualOverrideWinsTest` (Phase 8 plan 08-08) is the spec — D-019 enforced.
- [ ] **Match-result Discord announce.** A `match_result_announce` outbound row lands; the bot dispatcher (`apps/bot/src/services/render.ts` audit-hotfix branch) renders + posts.

### Phase 9 — Polish

- [ ] **Leaderboards render.** `/leaderboards` shows top clans + top players by stat window. Refresh 5+ times; query budget stays within `LeaderboardsQueryBudgetTest` thresholds (cold ≤ 6 deviation, warm/empty ≤ 4 — Phase 9 plan 09-08).
- [ ] **Notification bell.** Schedule a match starting in ~70 minutes. The `notifications:dispatch-upcoming` cron sweep dispatches a `MatchStartingSoon` notification at T-60 (and T-15). Confirm:
      - The bell counter on `PublicLayout` increments (Inertia shared prop `shared.unread_notifications_count` — Phase 9 plan 09-06).
      - The user receives a Discord DM via the `user_dm` outbox kind (Phase 9 plan 09-03 `DiscordChannel`).
      - `notifications:prune` (daily 03:30 UTC) does not remove the just-created row (90-day retention — Open Question 7 LOCKED).
- [ ] **Moderator bulk-ban.** As a `moderator`, select multiple users in `/admin/users` and apply the BulkAction "Ban". `BanService` writes `bans` rows + audit (Phase 9 plan 09-07). Confirm banned users cannot log back in via Discord.
- [ ] **Match dispute flow.** File a dispute via `/admin/match-disputes` → resolve → audit row visible.
- [ ] **Rate limits.** `for i in {1..40}; do curl -i https://<your-domain>/api/public/<endpoint>; done` — confirm `public-api` limiter (30/min) starts returning `429` after 30 requests (Phase 9 plan 09-11).
- [ ] **WebP delivery.** Open a clan logo + player avatar + article hero in DevTools Network. Confirm the served file is `.webp` for browsers that send `Accept: image/webp` (Phase 9 plan 09-09).
- [ ] **Accessibility — `:focus-visible` site-wide.** Tab through `/`, `/clans`, `/matches`, `/blog`, `/admin` (logged in). Confirm a visible focus ring lands on every interactive element (Phase 9 plan 09-10).
- [ ] **axe-core CI green.** First push to `master` after deploy triggers `.github/workflows/a11y.yml`. Confirm the workflow passes on public routes (Phase 9 plan 09-10 PENDING_MANUAL_SMOKE — first-run canonical observation).
- [ ] **Manual keyboard nav 10-step checklist.** Tab from `/` through nav → search → click a clan → tab through clan page → tab back to nav → enter `/login`. All steps reach a focusable element with visible focus.
- [ ] **Abuse report.** From a public clan page, file an abuse report. `report-abuse` limiter (5/hr) caps further reports from the same user. Admin sees the report in `/admin/abuse-reports` (Phase 9 plan 09-11).

---

## 4. Go-live sign-off

The platform is "live with users" when every box below is ticked.

- [ ] All 36 smoke items above are GREEN, OR explicitly deferred to v1.1 with a tracker entry in `.planning/REQUIREMENTS.md` v2 section.
- [ ] Railway monitoring dashboards open and bookmarked. Default: each service's **Logs** + **Metrics** panes.
- [ ] Horizon dashboard reachable at `/horizon` (admin-only, gated by Filament `admin-access`). Confirm the supervisor shows `running` and recent completed jobs are visible.
- [ ] `scheduler` service running and ticking `schedule:run` every minute (its logs show scheduled commands firing). Without it the four cron jobs are dead (Horizon does NOT run the scheduler — D-022).
- [ ] Postgres backups confirmed. Railway → Postgres plugin → **Settings → Backups** → retention window documented (default at time of writing: 7 days for Hobby; verify current Railway docs).
- [ ] DNS + TLS confirmed: `curl -I https://<your-domain>/up` returns `200` with a valid certificate; `curl -I http://<your-domain>/up` redirects or refuses (Railway auto-redirects HTTP → HTTPS).
- [ ] SSR confirmed via `curl -s https://<your-domain>/ | head -50` — pre-rendered HTML visible inside the Inertia mount.
- [ ] Bot reachable in Discord: `/clan list` returns instantly; bot user shows online.
- [ ] rcon-worker reachable: at least one live ingest event arrived during smoke step 8.
- [ ] **Deploy built from the right ref.** Confirm the live deploy was built from the **`master` ref** (or a re-cut `v1.0` tag at current HEAD) — **NOT** the existing `v1.0` tag (`6fa8641`). That tag predates the production-readiness blocker fixes (SSR bundle build, dedicated `scheduler` service, bot tournament/bracket announce fix, `TrustProxies`, `DB_URL`/`DATABASE_URL` fallback); deploying it silently re-introduces those blockers. Re-cut the tag (`git tag -f v1.0 <current-HEAD> && git push --force origin v1.0`) or point Railway at `master`. (The audit-hotfix `cdfbfa5` web→bot dispatcher bridge is already included in the `v1.0` tag — it is not the reason to avoid the tag.)
- [ ] Secret rotation runbook handed to ops (or self): cadences per [`CONFIGURATION.md`](./CONFIGURATION.md) §9.
- [ ] Operator handover signed: this checklist filed under your team's launch-runbook archive with timestamps + initials.

---

For deeper context on any item above, follow the per-phase verification doc: `.planning/milestones/v1.0-phases/{phase-dir}/*-PHASE-VERIFICATION.md`.
