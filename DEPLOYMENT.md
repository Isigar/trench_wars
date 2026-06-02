# Trenchwars — Production Deployment Guide

Step-by-step Railway deploy walkthrough for Trenchwars v1.0. Operationalises locked decision **D-022** (Railway 6 app services + Postgres + Redis plugins — supersedes the D-014 service count) and **D-021** (container-only local dev). Production builds every app service with the **Nixpacks** builder (matches the committed `apps/*/railway.json` files); the `docker/` Dockerfiles are the local-dev images only.

Cross-references:

- Environment variable matrix → [`CONFIGURATION.md`](./CONFIGURATION.md)
- First-deploy / go-live checklist → [`LAUNCH-CHECKLIST.md`](./LAUNCH-CHECKLIST.md)
- AI/developer conventions → [`CLAUDE.md`](./CLAUDE.md)
- Locked architectural decisions → [`.planning/PROJECT.md`](./.planning/PROJECT.md)
- Milestone audit + per-phase smoke notes → [`.planning/milestones/v1.0-MILESTONE-AUDIT.md`](./.planning/milestones/v1.0-MILESTONE-AUDIT.md)

Railway features evolve; when in doubt against the steps below, follow the current Railway docs at <https://docs.railway.app/>.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Railway project setup](#2-railway-project-setup)
3. [Services — one section per service](#3-services--one-section-per-service)
   - [3.1 `web` (Laravel HTTP + Filament admin)](#31-web-laravel-http--filament-admin)
   - [3.2 `ssr` (Inertia v2 SSR sidecar)](#32-ssr-inertia-v2-ssr-sidecar)
   - [3.3 `bot` (Discord bot)](#33-bot-discord-bot)
   - [3.4 `rcon-worker` (CRCON adapter)](#34-rcon-worker-crcon-adapter)
   - [3.5 `worker` (Horizon queue)](#35-worker-horizon-queue)
   - [3.6 `scheduler` (Laravel cron)](#36-scheduler-laravel-cron)
4. [First deploy](#4-first-deploy)
5. [First-boot data](#5-first-boot-data)
6. [Production domain + DNS](#6-production-domain--dns)
7. [TLS](#7-tls)
8. [Scaling notes](#8-scaling-notes)
9. [Troubleshooting](#9-troubleshooting)
10. [Rollback](#10-rollback)

---

## 1. Prerequisites

Before opening Railway you need:

- **A Railway account** with billing configured. The topology is 6 app services + 2 plugins; verify current per-plan service limits at <https://railway.app/pricing>.
- **A Discord OAuth application** (used by web for login).
  - Create at <https://discord.com/developers/applications>.
  - Capture **Client ID** and **Client Secret**.
  - Under **OAuth2 → Redirects**, add the production callback exactly: `https://<your-domain>/auth/discord/callback` (no trailing slash; Discord matches verbatim — see `CLAUDE.md` §6).
- **A Discord bot application** (used by the `bot` service).
  - Same Developer Portal; under **Bot → Reset Token** capture the bot token.
  - Under **Bot → Privileged Gateway Intents** enable **Server Members Intent** (required by Phase 5 plan 05-08; without it `guildMemberUpdate` events never fire and clan role-sync silently breaks).
  - Generate an OAuth2 URL with scopes `bot applications.commands` and permissions `Send Messages`, `Embed Links`, `Manage Roles`, `View Channels`. Add the bot to your league guild.
  - Capture **Application ID** and **Guild ID** (right-click the league guild with Developer Mode on → Copy Server ID).
- **CRCON access for at least one HLL server** (Phase 8 — CRCON ≥ v10.0.0 per `apps/web/config/rcon.php` `crcon_version_pin`). League deploys CRCON alongside the game servers (D-005); the rcon-worker connects to CRCON over websocket from a Railway egress IP.
- **`gh` or git CLI** locally and push access to the repo this deploys from.

You do **NOT** need PHP, Composer, Postgres, Redis, or Node on your operator workstation. Everything builds inside containers on Railway (mirrors D-021).

---

## 2. Railway project setup

1. In Railway, create a new project. Source: **Deploy from GitHub repo**, choose this repo, branch `master` (or whichever branch carries the `v1.0` tag).
2. Add the **Postgres 16** plugin (Railway dashboard → New → Database → Postgres). Capture the auto-provided `DATABASE_URL` (visible under Postgres service → Variables). The project ships migrations targeting Postgres 16 (D-016) and uses Postgres-only features (`citext`, `uuid-ossp`, `EXCLUDE USING gist`, JSONB, FTS) — do not substitute MySQL. **The app reads `DATABASE_URL` directly** (the Railway plugin default name); reference it on the Web env group as `DATABASE_URL=${{Postgres.DATABASE_URL}}`. (`DB_URL` is also accepted as an alias, and the split `DB_*` keys remain a fallback — see [`CONFIGURATION.md`](./CONFIGURATION.md) §2.)
3. Add the **Redis 7** plugin. Capture `REDIS_URL`. Redis carries session, cache (`Cache::tags` is required by Phase 9 plan 09-05 — set `CACHE_STORE=redis`), queue (Horizon), the bot outbound-message backpressure, and the RCON nonce store.
4. Do **not** create the 6 application services yet — they need env values from steps 2-3, set those up in §3 below.

---

## 3. Services — one section per service

All 6 app services deploy from the **same GitHub repo**. Each service points at a sub-tree (`apps/web`, `apps/bot`, `apps/rcon-worker`) via its **Root Directory** and builds with **Nixpacks**. Four services (`web`, `ssr`, `worker`, `scheduler`) share Root Directory `apps/web` and therefore share `apps/web/railway.json` + `apps/web/nixpacks.toml`; they differ only by a per-service dashboard **Start-Command override**. Per-service env values are listed in [`CONFIGURATION.md`](./CONFIGURATION.md); only the deploy plumbing is documented here.

> **Builder = NIXPACKS (single supported web path).** Production builds every app service with **Nixpacks** (`apps/*/nixpacks.toml` + `apps/*/railway.json` from Phase 1 plan 01-17). Nixpacks' PHP provider auto-wires Caddy + php-fpm (`/start-server.sh`), so `web` serves HTTP on the port Railway expects and passes the `/up` healthcheck. The `docker/{web,bot,rcon-worker}/Dockerfile` images are the **local-dev** containers used by `docker-compose.yml` (D-021) — do **not** point the production `web` service at `docker/web/Dockerfile`: that image is php-fpm only (port 9000, no HTTP listener) and would fail the `/up` healthcheck. Leave each `railway.json` `builder` as `NIXPACKS`; read the per-service `nixpacks.toml` for the install/build/start contract.

### 3.1 `web` (Laravel HTTP + Filament admin)

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (`apps/web/railway.json` → `builder: NIXPACKS`). Do **not** use `docker/web/Dockerfile` for this service — it is php-fpm only and fails the `/up` healthcheck. |
| Root directory | `apps/web` |
| Build-time env | `NIXPACKS_PHP_ROOT_DIR=/app/public` (points Caddy at Laravel's `public/`; `apps/web/nixpacks.toml` requires it). |
| Internal port | Nixpacks' PHP provider listens on the port Railway injects via `$PORT` (Caddy + php-fpm via `/start-server.sh`). No manual port config needed. |
| Healthcheck path | `/up` (Laravel default health endpoint; `apps/web/railway.json` sets it). |
| Release / Pre-deploy command | `php artisan migrate --force && php artisan storage:link` — already declared in `apps/web/railway.json` (`deploy.preDeployCommand`). Railway runs it after build, before the deploy goes live. **Migrations are automated; do not run them by hand.** Clear this command on the `ssr`/`worker`/`scheduler` siblings so only `web` owns the release migration. |
| Env group | "Web env" (see [`CONFIGURATION.md`](./CONFIGURATION.md) §3) |

**SSR client wiring.** Set `INERTIA_SSR_ENABLED=true` and `INERTIA_SSR_URL` pointing at the `ssr` service's internal address (Railway resolves service-to-service via `${{ssr.RAILWAY_PRIVATE_DOMAIN}}:13714`).

**Object storage (production-critical).** Set `FILESYSTEM_DISK=s3` and the S3-compatible credentials (see [`CONFIGURATION.md`](./CONFIGURATION.md) §3 Filesystems). Railway's container filesystem is **ephemeral** — with `FILESYSTEM_DISK=local`, every uploaded clan logo, player avatar, and article cover is lost on each redeploy. Use a persistent object store (any S3-compatible bucket: AWS S3, Cloudflare R2, Backblaze B2, MinIO).

### 3.2 `ssr` (Inertia v2 SSR sidecar)

Phase 7 plan 07-11 (`Open Question 7 LOCKED — split service`). Reuses the **same Nixpacks `apps/web` image** and runs `php artisan inertia:start-ssr` so SSR is dev/prod parity with the web image. SSR is **enabled** for v1.0.

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (same `apps/web/railway.json` + `apps/web/nixpacks.toml` as `web`). |
| Root directory | `apps/web` |
| Build-time env | `NIXPACKS_PHP_ROOT_DIR=/app/public` |
| Start command override | `php artisan inertia:start-ssr` (per-service dashboard override) |
| Internal port | `13714` (Inertia SSR default) |
| Public ports | **None.** Internal-only. Set `Settings → Networking → Public Networking = off`. T-07-11-04 mitigation: the SSR Node process must never be reachable from the public internet. |
| Healthcheck path | `/health` on `13714` (Inertia v2 SSR ships a `GET /health` handler). |
| Release / Pre-deploy command | **None** — clear the inherited `preDeployCommand` so only `web` runs migrations. |
| Env group | "Web env" — must share `APP_KEY`, DB, Redis, locale resolution with web. Service-level overrides: `INERTIA_SSR_ENABLED=true`, `INERTIA_SSR_URL=http://0.0.0.0:13714`, `APP_ENV=production`. |

### 3.3 `bot` (Discord bot)

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (`apps/bot/railway.json` → `builder: NIXPACKS`). |
| Root directory | `apps/bot` |
| Build-time env | `NIXPACKS_NODE_VERSION=22` |
| Start command | `node dist/index.js` (default from `apps/bot/nixpacks.toml`) |
| Internal port | None — outbound-only client (Discord Gateway WS + polls `${WEB_API_URL}/api/bot/outbound-messages`) |
| Public ports | None. |
| Healthcheck | The compose-level healthcheck is `pgrep node`; Railway healthchecks default to "service is up" if no public port is exposed. No path required. |
| Env group | "Bot env" (see [`CONFIGURATION.md`](./CONFIGURATION.md) §4) |

`WEB_API_URL` should point at the web service's internal Railway domain (e.g. `https://${{web.RAILWAY_PRIVATE_DOMAIN}}`) or the public domain (`https://trenchwars.example`) if you have not enabled private networking. **No `/api` suffix** — the bot's `apiContracts.ts` appends `/api/bot/...` itself (a trailing `/api` would produce `/api/api/bot` and 404 every call). This matches [`CONFIGURATION.md`](./CONFIGURATION.md) §4 verbatim.

### 3.4 `rcon-worker` (CRCON adapter)

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (`apps/rcon-worker/railway.json` → `builder: NIXPACKS`). |
| Root directory | `apps/rcon-worker` |
| Build-time env | `NIXPACKS_NODE_VERSION=22` |
| Start command | `node dist/index.js` (default from `apps/rcon-worker/nixpacks.toml`) |
| Internal port | None — outbound-only (CRCON WS + HMAC-signed POST to web `/api/internal/match/{id}/events`) |
| Public ports | None. |
| Env group | "Worker env" (see [`CONFIGURATION.md`](./CONFIGURATION.md) §5) |

The worker must reach the league's CRCON instance over the public internet (or via a Railway egress IP that CRCON allow-lists). The worker is a **normaliser only** — all business logic stays in `web` (CON-arch-rcon-to-web-comm, CLAUDE.md §8). The `/api/internal/*` namespace is the **rcon-worker's HMAC-signed** path — distinct from the bot's `/api/bot/*` token path.

### 3.5 `worker` (Horizon queue)

The `worker` service runs the same Nixpacks `apps/web` image and overrides the start command to `php artisan horizon`. Horizon owns the Redis-backed queue (Phase 5+).

> **Horizon does NOT run the Laravel scheduler.** `php artisan horizon` supervises queue workers only — it does **not** tick `schedule:run`. The four cron jobs in `apps/web/routes/console.php` are driven by the dedicated **`scheduler`** service (§3.6), not by `worker`. Without that service every scheduled command is dead in production (D-022).

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (same `apps/web/railway.json` + `apps/web/nixpacks.toml` as `web`). |
| Root directory | `apps/web` |
| Build-time env | `NIXPACKS_PHP_ROOT_DIR=/app/public` |
| Start command override | `php artisan horizon` (per-service dashboard override) |
| Release / Pre-deploy command | **None** — clear the inherited `preDeployCommand` so only `web` runs migrations. |
| Public ports | None. |
| Healthcheck | No public port = "service up" suffices on Railway; tail logs and confirm the Horizon supervisor started. |
| Env group | "Web env" — inherits everything `web` has so Horizon shares DB/Redis/queue config. |

### 3.6 `scheduler` (Laravel cron)

The `scheduler` service runs the same Nixpacks `apps/web` image and overrides the start command to `php artisan schedule:work`. This is the process that ticks Laravel's scheduler — **`php artisan horizon` does NOT run `schedule:run`** (the older claim that Horizon supervises the scheduler was wrong). Without this service every scheduled command in `apps/web/routes/console.php` is dead in production (D-022):

- `articles:publish-scheduled` (every minute; Phase 7 plan 07-07)
- `sitemap:generate` (daily 03:00 UTC; Phase 7 plan 07-12)
- `notifications:dispatch-upcoming` (every minute; Phase 9 plan 09-04)
- `notifications:prune` (daily 03:30 UTC; Phase 9 plan 09-04)

`schedule:work` is a long-running foreground loop that invokes `schedule:run` every 60 seconds. Every schedule entry is already guarded with `->withoutOverlapping()->onOneServer()` (Phase 7 plan 07-07 Pitfall 12; Phase 9 plan 09-04), so the scheduler coexists safely with multi-replica `worker`s — but run a **single** `scheduler` replica for the simplest reasoning model.

| Setting | Value |
|---|---|
| Source | GitHub repo, this repo |
| Builder | **Nixpacks** (same `apps/web/railway.json` + `apps/web/nixpacks.toml` as `web`). |
| Root directory | `apps/web` |
| Build-time env | `NIXPACKS_PHP_ROOT_DIR=/app/public` |
| Start command override | `php artisan schedule:work` (per-service dashboard override) |
| Release / Pre-deploy command | **None** — clear the inherited `preDeployCommand` so only `web` runs migrations. |
| Public ports | None. |
| Healthcheck | No public port = "service up" suffices. Verify after first deploy: tail Railway logs on `scheduler` and confirm `Running scheduled command: ...` / `articles:publish-scheduled ... ran successfully` appears each minute. |
| Replicas | **1** (the `->onOneServer()` lock makes multi-replica safe, but one replica is simplest). |
| Env group | "Web env" — inherits everything `web` has so the scheduled commands share DB/Redis/queue config. |

---

## 4. First deploy

1. **Push the v1.0 tag** (or the branch you intend to deploy from). Railway auto-builds on push when the GitHub integration is connected.
2. In Railway, **trigger a deploy** on each service. Build order is independent — services build in parallel. The web service's **release / pre-deploy command** (`php artisan migrate --force && php artisan storage:link`, declared in `apps/web/railway.json` → `deploy.preDeployCommand`) runs after the build but before the service goes live. Clear that command on `ssr`/`worker`/`scheduler` so only `web` owns the release migration; `bot` and `rcon-worker` have no release step.
3. **Watch the build logs.** First-time builds typically take 5-10 minutes (the web image installs PHP 8.4 + Node 22 + composer/pnpm). Subsequent builds are layer-cached.
4. **Verify service health** in this order:
   1. `postgres` and `redis` plugins are Healthy.
   2. `web` build completes; pre-deploy command logs show migrations applied.
   3. `ssr` reaches Healthy on the `/health` probe.
   4. `worker` (Horizon) reaches Healthy; tail logs and confirm the Horizon supervisor started.
   5. `scheduler` is up; tail logs and confirm `schedule:run` ticks every minute (e.g. `articles:publish-scheduled ... ran successfully`).
   6. `bot` connects to Discord (look for `Ready! Logged in as <bot-name>#<tag>` in logs).
   7. `rcon-worker` starts; on first boot it polls `/api/internal/bookings/due` and idles when no booking is due.
5. **Curl the web service** on its Railway-provided public URL:
   ```bash
   curl -i https://<railway-generated-domain>/up
   # HTTP/2 200
   ```

---

## 5. First-boot data

These run **once**, against the production database, after step 4 above succeeds. Use Railway's one-off shell (`Service → Settings → Run Command`) or the Railway CLI (`railway run`) targeting the `web` service.

Per **CLAUDE.md §1**, every `php artisan` command runs inside a container — on Railway, the one-off shell IS that container.

1. **App key.** Prefer setting `APP_KEY` directly in the Web env group (single source of truth, no drift across redeploys). Generate one locally and paste:
   ```bash
   # Locally, inside a container that has PHP — e.g. the web container of your dev compose stack
   docker compose exec web php artisan key:generate --show
   # Copy the base64:... string into Railway env group APP_KEY
   ```
   Only fall back to `php artisan key:generate --force` on the live service if you absolutely cannot pre-set the env var — that mutates `.env` in the running container, which the next redeploy rebuilds and clobbers.

2. **Seed the database.** The first deploy's release command already ran `migrate --force`. Seed once:
   ```bash
   php artisan db:seed --force
   ```
   Per `apps/web/database/seeders/DatabaseSeeder.php` this runs 8 seeders in order: `PermissionSeeder`, `ModeratorRoleSeeder`, `DiscordGuildSeeder`, `BotServiceUserSeeder`, `RconWorkerSystemUserSeeder`, `ClanTagSeeder`, `GameSeeder` (HLL preset — 1 Game + 15 Roles + 5 MatchTypes + capacity matrix), `CategorySeeder` (4 starter CMS categories). All seeders are idempotent (`firstOrCreate`) — safe to re-run.

3. **Issue the bot's Sanctum token.** The bot authenticates to web via a personal access token with abilities `bot:read`, `bot:act-as-user`, `bot:write-outbound`, `bot:reconcile`:
   ```bash
   php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90
   ```
   The command prints the plaintext token **once**. Copy it into the bot service's `WEB_API_TOKEN` env var. Rotation: re-run the command with the same `--name` every 90 days; it deletes the old token before issuing the new one (T-05-07-04 mitigation).

4. **Issue the rcon-worker's Sanctum token.** Recommended: use a separate token so revocation can scope to one service. The same Artisan command works — use a different `--name`:
   ```bash
   php artisan trenchwars:bot:issue-token --name=rcon-worker-prod --ttl=90
   ```
   Copy into the rcon-worker service's `WEB_API_TOKEN` env var. (The worker's API client uses a generic bearer token; the abilities granted by `trenchwars:bot:issue-token` are a superset of what the worker actually exercises — Phase 8 plan 08-06.)

5. **Storage link** (already run by the release command, but harmless to re-run if you skipped that step):
   ```bash
   php artisan storage:link
   ```

6. **Log in once via Discord.** Open `https://<your-domain>/` in a browser, click **Log in with Discord**, complete the OAuth flow. This provisions your `users`, `players`, and `player_privacy` rows (`ProvisionFirstLogin` listener, Phase 1 plan 01-09).

7. **Grant yourself admin.** Find your Discord User ID (Discord Settings → Advanced → Developer Mode ON → right-click yourself → Copy User ID). Then:
   ```bash
   php artisan trenchwars:make-admin <YOUR_DISCORD_USER_ID>
   ```
   You now have access to `/admin` (Filament panel, gated by the `admin-access` permission per Phase 1 plan 01-12).

8. **(Optional) Grant CMS editor.** If a different user owns editorial content:
   ```bash
   php artisan trenchwars:make-cms-editor <THEIR_DISCORD_USER_ID>
   ```

---

## 6. Production domain + DNS

1. In Railway, on the `web` service: **Settings → Networking → Custom Domain**, add `trenchwars.example` (or your domain). Railway prints CNAME / A record targets — set them at your DNS provider.
2. Update `APP_URL` env var on the `web` env group to `https://trenchwars.example` (no trailing slash).
3. Update `DISCORD_REDIRECT_URI` to `https://trenchwars.example/auth/discord/callback` and add the **same value** to the Discord application's OAuth2 → Redirects list. Discord matches verbatim including protocol and trailing slash (CLAUDE.md §6).
4. Update the bot's `WEB_API_URL` if it points at the public web domain (recommended: use Railway's private domain so the bot↔web traffic stays internal; see Railway's docs on private networking).
5. (Optional) Update `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` to the production channel you want article/match-result announces routed to. Same value goes on both `web` and `bot` env groups (the web side writes it into `discord_outbound_messages.channel_id`; the bot side uses it as a fallback when the outbound row's channel_id is empty — audit-hotfix `cdfbfa5`).

---

## 7. TLS

Railway terminates TLS at the edge with a managed certificate (free, auto-renewed). Verify after DNS propagates:

```bash
curl -I https://trenchwars.example/up
# HTTP/2 200
```

Application-side requirements:

- `APP_URL=https://...` (Laravel uses this to generate absolute URLs).
- `SESSION_SECURE_COOKIE=true` — `apps/web/.env.example` defaults this to `false` for local HTTP; **must** be `true` in production so the session cookie carries the `Secure` flag (CLAUDE.md §6).
- `SESSION_SAME_SITE=lax` (default; Inertia v2 + Discord OAuth round-trip relies on `Lax`).

Discord's OAuth callback **must** be `https://` in production; Discord rejects `http://` for non-localhost hosts.

---

## 8. Scaling notes

- **`withoutOverlapping()->onOneServer()`** is already wired on every Schedule entry in `apps/web/routes/console.php` (Phase 7 plan 07-07 Pitfall 12; Phase 9 plan 09-04). The cache lock prevents duplicate publishes/dispatches across multiple replicas — the `scheduler` service ticks these (not Horizon).
- **For v1.0 run 1 replica of `worker` and 1 replica of `scheduler`.** The `worker` keeps the Horizon supervisor/queue on a single host; the `scheduler` runs `schedule:work`. Scaling `worker` out is safe later (the multi-replica guards exist), but it adds nothing for v1.0 traffic. Keep `scheduler` at 1 replica.
- **`web` can scale horizontally.** Sessions are in Redis (`SESSION_DRIVER=redis`), cache is in Redis, no host-local state.
- **`ssr` can also scale horizontally**, but in practice 1 replica suffices until the SSR render budget becomes the bottleneck.
- **`bot` MUST stay at 1 replica.** discord.js opens a Gateway shard per process; running two replicas creates two clients responding to every interaction, doubling responses and confusing Discord's interaction-token routing. Phase 5 plan 05-11 does not implement sharding.
- **`rcon-worker` MUST stay at 1 replica** for the same reason: each replica would open its own CRCON websocket and ingest events twice. The Redis nonce store (Phase 8 plan 08-05) would catch most duplicates server-side but the worker is not designed for active-active.
- **Postgres + Redis** scale via Railway plugin upgrades; no application changes needed.

---

## 9. Troubleshooting

- **`web` fails the `/up` healthcheck / serves nothing on the public URL.** Most likely the service was pointed at `docker/web/Dockerfile` (php-fpm only, port 9000, no HTTP listener) instead of Nixpacks. Set the `web` service builder to **Nixpacks** and the build-time env `NIXPACKS_PHP_ROOT_DIR=/app/public`; the Nixpacks PHP provider wires Caddy + php-fpm and binds `$PORT`.
- **Imagick missing on web (`Class "Imagick" not found`).** Production builds with Nixpacks, and `apps/web/nixpacks.toml` does **not** include Imagick — fall back to GD (which is in the Nix package list) and add `MEDIA_LIBRARY_IMAGE_DRIVER=gd` to the Web env group. WebP conversions still work but a wider format set is unsupported. (The local-dev `docker/web/Dockerfile` ships Imagick, so this only bites in production.)
- **`intl` extension missing.** `apps/web/nixpacks.toml` includes `php84Extensions.intl`. If you see `Class "NumberFormatter" not found`, your build skipped this step — rebuild from scratch.
- **`CACHE_STORE=array` in production.** Phase 9 plan 09-05 (D-09-05-A) requires `redis` because the leaderboard service uses `Cache::tags`, which the array driver does not implement. Symptom: 500 on `/leaderboards`. Fix: set `CACHE_STORE=redis` on the Web env group.
- **Discord OAuth `invalid_redirect_uri`.** The URI on the Discord developer portal must match `DISCORD_REDIRECT_URI` **verbatim**, including the trailing slash and protocol (CLAUDE.md §6 Pitfall 2). Discord does not normalise.
- **Bot logs `Missing required env var: WEB_API_TOKEN`.** The bot's `apps/bot/src/env.ts` fail-fast loader throws if any required env var is empty (Phase 5 plan 05-08 — T-05-08-02 mitigation). Set it from the Artisan-issued PAT (§5 step 3).
- **rcon-worker `WEB_HMAC_SECRET too short`.** The worker's zod schema requires `min(32)` characters (`apps/rcon-worker/src/config.ts`). Generate a 64-char hex secret: `openssl rand -hex 32` and set it identically on **both** the web env group (`WEB_HMAC_SECRET`) and the rcon-worker env group.
- **SSR returns blank pages.** Check `ssr` service logs for the actual exception — common causes are missing `APP_KEY` parity with `web`, or an out-of-date Vite manifest. Re-run the build and confirm `public/build/manifest.json` lands inside the SSR container's `/app/public/build/`.
- **Rebuild after compose changes.** Railway rebuilds on every push but does **not** rebuild on env-var changes — if you change a build-time env var (like `NIXPACKS_PHP_ROOT_DIR`), trigger a manual redeploy.

---

## 10. Rollback

Railway retains previous deploys per-service. Two-step rollback:

1. **App rollback.** Each service → **Deployments → choose previous successful build → Redeploy**. This swaps the container image back in seconds, no data loss.
2. **Database rollback.** Every Phase XX-02 plan verifies migrations are reversible (each migration has a real `down()`). To roll back the most recent migration batch:
   ```bash
   php artisan migrate:rollback --force --step=1
   ```
   Or to a specific batch:
   ```bash
   php artisan migrate:rollback --force --batch=<batch_number>
   ```
   Confirm the migration history via:
   ```bash
   php artisan migrate:status
   ```

Cross-service rollback ordering: roll back **app first**, **then** DB (so the older app code does not see new columns). For destructive DB changes (column drops), restore from the Railway Postgres backup snapshot taken before the release — verify the retention window in your plugin settings.

For pre-release safety, **always** snapshot Postgres before running a production migration that includes a `dropColumn`, `dropTable`, or `renameColumn`. Postgres plugin backups are configurable under the plugin's **Settings → Backups**.
