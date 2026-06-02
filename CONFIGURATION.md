# Trenchwars — Configuration Reference

Authoritative environment-variable matrix for the 6 Railway app services + the 2 datastore plugins. Sourced verbatim from `apps/web/.env.example`, `apps/bot/.env.example`, `apps/rcon-worker/src/config.ts`, `docker-compose.yml`, and the relevant `config/*.php` files.

Cross-references:

- Deploy walkthrough → [`DEPLOYMENT.md`](./DEPLOYMENT.md)
- Go-live checklist → [`LAUNCH-CHECKLIST.md`](./LAUNCH-CHECKLIST.md)
- Container-only rule → [`CLAUDE.md`](./CLAUDE.md) §1 (D-021)
- Locked decisions → [`.planning/PROJECT.md`](./.planning/PROJECT.md)

---

## Table of Contents

1. [Conventions](#1-conventions)
2. [Shared (Postgres + Redis plugins)](#2-shared-postgres--redis-plugins)
3. [Web service](#3-web-service-appswebenv)
4. [Bot service](#4-bot-service-appsbotenv)
5. [RCON worker service](#5-rcon-worker-service-appsrcon-workerenv)
6. [SSR service](#6-ssr-service)
7. [Worker service (Horizon)](#7-worker-service-horizon)
8. [Scheduler service (cron)](#8-scheduler-service-cron)
9. [Rotation cadence + revocation](#9-rotation-cadence--revocation)
10. [Secret management](#10-secret-management)

---

## 1. Conventions

- **Variable names** match Laravel/Node conventions: `UPPER_SNAKE_CASE`. Service-local prefixes (`DISCORD_*`, `RCON_*`, `INERTIA_SSR_*`) keep ownership obvious.
- **Sensitive** column means the value is a secret — never commit, never log, never echo from a Slash command or admin page. Inject only via Railway env groups (D-014) or `apps/{web,bot}/.env` for local dev.
- **Required** column means the service fails fast at boot when the var is missing or empty. Both the bot (`apps/bot/src/env.ts`) and rcon-worker (`apps/rcon-worker/src/config.ts`) implement zod-style fail-loud loaders.
- **Rotate** column gives the default cadence; rotation procedure for each is in §9.
- `.env.example` files commit the **shape** only — empty values for every secret-like field (CLAUDE.md §6).
- Railway services share variables via **Env Groups**. Recommend three groups: **Web env** (used by `web`, `ssr`, `worker`, `scheduler`), **Bot env** (used by `bot`), **Worker env** (used by `rcon-worker`). Service-specific overrides live on the service (e.g. the start-command override and, for `ssr`, `INERTIA_SSR_ENABLED=true`).

---

## 2. Shared (Postgres + Redis plugins)

Railway plugins auto-provide connection strings; consume them in app env via `${{Postgres.DATABASE_URL}}` / `${{Redis.REDIS_URL}}` reference syntax, or copy the split values into `DB_*` / `REDIS_*` keys.

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `DATABASE_URL` | Yes | Yes | — | `postgres://user:pw@host:5432/db` | Plugin-managed | Provided by the Postgres 16 plugin. Laravel reads it as the canonical connection string when set; otherwise reads the split `DB_*` keys. |
| `REDIS_URL` | Yes | Yes | — | `redis://default:pw@host:6379` | Plugin-managed | Provided by the Redis 7 plugin. Consumed by Laravel for session/cache/queue and by the bot + rcon-worker. |

Split-key alternative (if the runtime cannot parse `DATABASE_URL`):

| `DB_CONNECTION` | Yes | No | `pgsql` | `pgsql` | — | Locked to Postgres (D-016). |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Yes | mixed | — | from plugin | Plugin-managed | Set if `DATABASE_URL` is not used. |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Yes | mixed | — | from plugin | Plugin-managed | Set if `REDIS_URL` is not used. |

---

## 3. Web service (`apps/web/.env`)

Used by `web`, `ssr`, `worker` services.

### Application

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `APP_NAME` | No | No | `Trenchwars` | `Trenchwars` | Never | Cosmetic; also seeded into Vite as `VITE_APP_NAME`. |
| `APP_ENV` | Yes | No | `local` | `production` | Never | MUST be `production` on Railway. Drives Laravel's strict-mode toggle (`Model::shouldBeStrict(! production)` per Phase 9 plan 09-08). |
| `APP_KEY` | Yes | Yes | — | `base64:…32 bytes…` | Yearly | Generated once with `php artisan key:generate --show`. Identical on `web`, `ssr`, `worker`. |
| `APP_DEBUG` | Yes | No | `true` | `false` | Never | MUST be `false` in production. |
| `APP_URL` | Yes | No | `http://localhost:8000` | `https://trenchwars.example` | On domain change | Used for absolute URL generation, sitemap.xml, Inertia SSR. |
| `APP_LOCALE` | No | No | `en` | `en` | Never | EN at launch (D-013). |
| `APP_FALLBACK_LOCALE` | No | No | `en` | `en` | Never | — |
| `APP_TIMEZONE` | No | No | `UTC` | `UTC` | Never | All timestamps stored UTC; rendered in the user's locale on the client. |

### Database

Either `DATABASE_URL` (from §2) or the `DB_*` split keys. Compose default for local dev: `DB_HOST=postgres DB_PORT=5432 DB_DATABASE=trenchwars DB_USERNAME=trenchwars DB_PASSWORD=trenchwars`.

### Cache / session / queue (Redis required)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `CACHE_STORE` | Yes | No | `redis` | `redis` | Never | **MUST be `redis`** (Phase 9 plan 09-05 D-09-05-A). `Cache::tags` is used by `LeaderboardService` and the array driver does not implement it. |
| `SESSION_DRIVER` | Yes | No | `redis` | `redis` | Never | Redis-backed sessions enable horizontal scaling of `web`. |
| `SESSION_LIFETIME` | No | No | `43200` (30 days) | `43200` | Never | Minutes. |
| `SESSION_SECURE_COOKIE` | Yes | No | `false` | `true` (prod) | Never | **MUST be `true` in production** (CLAUDE.md §6). |
| `SESSION_SAME_SITE` | No | No | `lax` | `lax` | Never | Required by Discord OAuth round-trip. |
| `QUEUE_CONNECTION` | Yes | No | `redis` | `redis` | Never | Horizon backend. |
| `BROADCAST_CONNECTION` | No | No | `log` | `log` | Never | No live broadcast at v1.0 (deferred to v2 — see REQUIREMENTS.md TOUR-V2-01). |

### Filesystems

> **Production MUST use an S3-compatible object store.** Railway's container filesystem is **ephemeral** — with `FILESYSTEM_DISK=local`, every uploaded clan logo, player avatar, and article cover is destroyed on each redeploy. Set `FILESYSTEM_DISK=s3` and the `AWS_*` credentials below. Laravel already ships the `s3` disk in `config/filesystems.php`; no code change is needed — this is an operator configuration requirement. Any S3-compatible provider works (AWS S3, Cloudflare R2, Backblaze B2, MinIO).

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `FILESYSTEM_DISK` | Yes (prod) | No | `local` | `s3` | Never | `local` for local dev only. **Must be `s3` in production** (Railway disk is ephemeral). |
| `AWS_ACCESS_KEY_ID` | Yes (prod, if `s3`) | Yes | — | `AKIA…` | On compromise | S3 access key. |
| `AWS_SECRET_ACCESS_KEY` | Yes (prod, if `s3`) | Yes | — | `…` | On compromise | S3 secret key. |
| `AWS_DEFAULT_REGION` | Yes (prod, if `s3`) | No | — | `us-east-1` | Never | Bucket region (use `auto` for Cloudflare R2). |
| `AWS_BUCKET` | Yes (prod, if `s3`) | No | — | `trenchwars-media` | Never | Bucket name. |
| `AWS_ENDPOINT` | No (AWS) / Yes (non-AWS) | No | — | `https://<account>.r2.cloudflarestorage.com` | Never | Custom endpoint for non-AWS S3-compatible providers (R2 / B2 / MinIO). Omit for AWS S3. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | No | No | `false` | `true` | Never | Set `true` for MinIO and most non-AWS providers; `false` for AWS S3. |

### Discord OAuth (D-002)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `DISCORD_CLIENT_ID` | Yes | No | — | `1234567890123456789` | On compromise | From Discord Developer Portal → OAuth2 → Client ID. Public-ish but pin it in env so the OAuth controller resolves it consistently. |
| `DISCORD_CLIENT_SECRET` | Yes | Yes | — | `Abc…XYZ` | 90 days | From Discord Developer Portal → OAuth2 → Client Secret. Treat as secret. |
| `DISCORD_REDIRECT_URI` | Yes | No | `http://localhost:8000/auth/discord/callback` | `https://trenchwars.example/auth/discord/callback` | On domain change | MUST match the value registered at Discord Developer Portal → OAuth2 → Redirects **verbatim**, including protocol and trailing slash (CLAUDE.md §6 Pitfall 2). |

### Discord announce (Phase 7 plan 07-06, Open Question 1 LOCKED)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` | No | No | `` (empty) | `1234567890123456789` | Never | Single global league channel where `ArticleObserver`, `MatchResultObserver`, and the notification dispatcher enqueue `article_announce` / `match_result_announce` / `user_dm` outbound rows. Empty string disables the announce side-effect. Same value also goes on the bot env group as a fallback (see §4). |

### Inertia v2 SSR (Phase 7 plan 07-11)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `INERTIA_SSR_ENABLED` | No | No | `false` | `true` (prod) | Never | Off in dev (keeps Vite HMR loop tight); on in production. |
| `INERTIA_SSR_URL` | No | No | `http://ssr:13714` | `http://${{ssr.RAILWAY_PRIVATE_DOMAIN}}:13714` | Never | Web service resolves the SSR sidecar via Docker service-name DNS in compose; on Railway use the private domain reference. |
| `INERTIA_ENCRYPT_HISTORY` | No | No | `false` | `false` | Never | Inertia v2 history encryption — defaults off. |

### RCON HMAC (Phase 8 plan 08-05)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `WEB_HMAC_SECRET` | Yes (web, rcon-worker) | Yes | — | `<64-char hex>` (from `openssl rand -hex 32`) | 90 days | Symmetric secret shared between web and rcon-worker. The worker signs every request `HMAC-SHA256(timestamp + raw_body)`; web's `VerifyRconSignature` middleware re-derives and constant-time compares (`apps/web/config/rcon.php`). **MUST be identical** on both env groups. Min 32 chars (worker zod check). Empty value makes the middleware fail-loud (`InvalidArgumentException`). |
| `RCON_FRESHNESS_WINDOW_MS` | No | No | `60000` | `60000` | Never | Replay window in ms — `abs(now − timestamp)` must be ≤ this. Defaults to 60s per CON-arch-rcon-to-web-comm. |
| `RCON_NONCE_TTL_SECONDS` | No | No | `120` | `120` | Never | TTL for the Redis nonce store (2× freshness window — defence in depth). |
| `CRCON_VERSION_PIN` | No | No | `10.0.0` | `10.0.0` | On CRCON upgrade | Operational pin telling the rcon-worker which CRCON tag to deploy alongside game servers (D-005). Not negotiated at runtime. |

### Sanctum (Phase 5 plan 05-01)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | No | No | `` (empty) | `` | Never | Left blank so bot/rcon-worker hostnames cannot accidentally promote token auth into session auth (T-05-01-05 / Pitfall 4). Local dev uses the Sanctum default (`localhost`, `127.0.0.1`). |

### Mail (v1.0 — log driver only)

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `MAIL_MAILER` | No | No | `log` | `log` | Never | v1.0 ships with the `log` driver only — no transactional email (v2 candidate, REQUIREMENTS.md NOTF-02). Discord DM via outbox is the in-product notification channel. |

---

## 4. Bot service (`apps/bot/.env`)

The bot's loader (`apps/bot/src/env.ts`) throws at module load if any **Required** value is empty (T-05-08-02 mitigation).

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `DISCORD_BOT_TOKEN` | Yes | Yes | — | `MTI…` | 90 days | Discord Developer Portal → Bot → Reset Token. Privileged — anyone with this token controls the bot. |
| `DISCORD_APPLICATION_ID` | Yes | No | — | `1234567890123456789` | Never | Discord Developer Portal → General Information → Application ID. |
| `DISCORD_GUILD_ID` | Yes | No | — | `1234567890123456789` | Never | The single league guild (D-003 — one guild per deploy). Right-click guild with Developer Mode on → Copy Server ID. |
| `WEB_API_URL` | Yes | No | `http://web-nginx` (compose default) | `https://${{web.RAILWAY_PRIVATE_DOMAIN}}` or `https://trenchwars.example` | On domain change | Base URL for web's API (bot calls `${WEB_API_URL}/api/internal/...`). Prefer Railway private domain to keep bot↔web traffic internal. **No `/api` suffix** — the bot's `apiContracts.ts` appends paths. |
| `WEB_API_TOKEN` | Yes | Yes | — | `<plaintext PAT from artisan>` | 90 days | Sanctum personal access token with abilities `bot:read`, `bot:act-as-user`, `bot:write-outbound`, `bot:reconcile`. Issued via `php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90`. Plaintext shown ONCE on issuance — Sanctum stores only the SHA-256 hash. |
| `OUTBOUND_POLL_INTERVAL_MS` | No | No | `5000` | `5000` | Never | Polling cadence for `GET /api/bot/outbound-messages` (the bot token path; `/api/internal/*` is the rcon-worker HMAC path). Phase 5 plan 05-11. |
| `DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID` | No | No | `` (empty) | `1234567890123456789` | Never | **v1.0 audit-hotfix `cdfbfa5`** — fallback channel when an outbound row has `channel_id=''` (the web side's `ArticleObserver` writes empty channel_id because the channel is resolved at dispatch time via `config('discord.league_announce_channel_id')`). Set to the same value as the web side. Empty here means "no fallback configured" — render.ts marks the row `failed` instead of throwing on `client.channels.fetch('')`. |
| `REDIS_URL` | No | No | — | `redis://default:pw@host:6379` | Plugin-managed | Optional Redis URL — Phase 5 plan 05-11 keeps the bot stateless and does not require it for v1.0. Reserved for future backpressure. |
| `NODE_ENV` | No | No | `development` | `production` | Never | Set to `production` on Railway to skip dev-only logging. |

---

## 5. RCON worker service (`apps/rcon-worker/.env`)

Shape from `apps/rcon-worker/src/config.ts` (zod schema, fail-fast on boot).

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `WEB_HMAC_SECRET` | Yes | Yes | — | `<64-char hex>` | 90 days | **MUST equal** the web service's `WEB_HMAC_SECRET` (§3 RCON HMAC). The zod schema requires `min(32)`. Used to HMAC-SHA256 sign every outbound POST to web. |
| `WEB_INTERNAL_URL` | Yes | No | — | `https://${{web.RAILWAY_PRIVATE_DOMAIN}}` or `http://web-nginx` (compose) | On domain change | Base URL for web's internal API. Worker calls `${WEB_INTERNAL_URL}/api/internal/bookings/due`, `/api/internal/match/{id}/events`, `/api/internal/match-servers/{id}/credentials`. |
| `REDIS_URL` | No | No | `redis://redis:6379` (compose) | `redis://default:pw@host:6379` | Plugin-managed | Used by `RedisFailoverQueue` (Phase 8 plan 08-11) to buffer events when web is unreachable. |
| `POLL_INTERVAL_MS` | No | No | `30000` | `30000` | Never | BookingScheduler poll cadence — every 30s checks `GET /api/internal/bookings/due`. |
| `NODE_ENV` | No | No | `development` | `production` | Never | Set to `production` on Railway. |

Optional (set only if the rcon-worker uses its own Sanctum PAT for any non-HMAC endpoint — recommended for symmetry with the bot):

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `WEB_API_TOKEN` | No | Yes | — | `<plaintext PAT from artisan>` | 90 days | Separate Sanctum PAT (`php artisan trenchwars:bot:issue-token --name=rcon-worker-prod`). v1.0 uses HMAC for the worker→web ingest path; this PAT is reserved for future internal-API needs. |

---

## 6. SSR service

Reuses the same Nixpacks `apps/web` image (Root Directory `apps/web`, start-command override `php artisan inertia:start-ssr`) and inherits the **Web env** group. SSR is **enabled** for v1.0. Service-level overrides:

| Var | Required | Sensitive | Default | Example | Rotate | Notes |
|---|---|---|---|---|---|---|
| `INERTIA_SSR_ENABLED` | Yes | No | `false` | `true` | Never | Set per-service to `true` (the global Web env default may be `false` to keep local dev tight). |
| `INERTIA_SSR_URL` | Yes | No | `http://ssr:13714` | `http://0.0.0.0:13714` | Never | Service-internal listener binding. |
| `APP_ENV` | Yes | No | `local` | `production` | Never | Same as Web env. |

No new secrets — all DB/Redis/Discord config inherits from the Web env group.

---

## 7. Worker service (Horizon)

Reuses the same Nixpacks `apps/web` image and **fully inherits the Web env group**. The start command override (`php artisan horizon`, set per-service in the dashboard) replaces php-fpm with the Horizon supervisor; everything else is identical to the `web` service.

No additional env vars required. Horizon config lives in `apps/web/config/horizon.php` (committed to the repo).

> **Horizon does NOT run the Laravel scheduler.** `php artisan horizon` supervises queue workers only; it does **not** tick `schedule:run`. The cron jobs in `apps/web/routes/console.php` are driven by the dedicated **`scheduler`** service (§8), not by `worker` (D-022).

---

## 8. Scheduler service (cron)

Reuses the same Nixpacks `apps/web` image and **fully inherits the Web env group**. The start command override (`php artisan schedule:work`, set per-service in the dashboard) runs the long-lived scheduler loop that invokes `schedule:run` every 60 seconds. Without this service, all four scheduled commands are dead in production (D-022):

- `articles:publish-scheduled` (every minute; Phase 7 plan 07-07)
- `sitemap:generate` (daily 03:00 UTC; Phase 7 plan 07-12)
- `notifications:dispatch-upcoming` (every minute; Phase 9 plan 09-04)
- `notifications:prune` (daily 03:30 UTC; Phase 9 plan 09-04)

No additional env vars required. Every schedule entry is guarded by `->withoutOverlapping()->onOneServer()` (`apps/web/routes/console.php`), so the scheduler is safe to coexist with multi-replica workers — but run a **single** `scheduler` replica.

---

## 9. Rotation cadence + revocation

| Secret | Cadence | Owner | Rotation procedure |
|---|---|---|---|
| `APP_KEY` | Yearly (or on compromise) | Web | Generate new key with `php artisan key:generate --show` in a local container. Update Web env group `APP_KEY`. **Existing session cookies become unreadable** — users must re-login. Encrypted columns (e.g. `match_servers.crcon_credentials` per Phase 8) become unreadable too; re-encrypt with the old + new key pair via `php artisan crypt:reencrypt` (Laravel 12 feature) before swapping. |
| `DISCORD_CLIENT_SECRET` | 90 days | Web | Discord Developer Portal → OAuth2 → **Reset Secret**. Update Web env group `DISCORD_CLIENT_SECRET`. No user impact — existing sessions stay valid; only new logins use the new secret. |
| `DISCORD_BOT_TOKEN` | 90 days | Bot | Discord Developer Portal → Bot → **Reset Token**. Update Bot env group `DISCORD_BOT_TOKEN`. The bot disconnects + reconnects with the new token. The old token is immediately invalid. |
| `WEB_API_TOKEN` (bot) | 90 days | Web → Bot | Re-run `php artisan trenchwars:bot:issue-token --name=bot-prod --ttl=90` on web. The command deletes the old token with the same `--name` and prints the new plaintext token (T-05-07-04 mitigation). Update Bot env group. |
| `WEB_API_TOKEN` (rcon-worker) | 90 days | Web → Worker | `php artisan trenchwars:bot:issue-token --name=rcon-worker-prod --ttl=90`. Update Worker env group. |
| `WEB_HMAC_SECRET` | 90 days | Web + Worker | Generate new value: `openssl rand -hex 32`. Update Web env group AND Worker env group in lockstep. In-flight requests during the swap window will 401 (stale signature) — coordinate during a low-traffic window. |
| `DATABASE_URL` / `REDIS_URL` passwords | Plugin-managed | Railway plugin | Trigger a plugin password rotation in Railway dashboard; services auto-pick up the new value via `${{Plugin.VAR}}` reference syntax. Restart services if you copy-paste static values instead. |

**Revocation.** To force-revoke a bot or rcon-worker token without rotating cadence:

```bash
php artisan trenchwars:bot:revoke-token --name=bot-prod
```

The Sanctum token row is hard-deleted. The bot's next API call returns 401 — bot logs the error and exits (T-05-08-02). Issue a new token and restart the bot service.

---

## 10. Secret management

- **`.env.example` files commit the SHAPE ONLY.** Every secret-like field is empty in version control (CLAUDE.md §6). `git grep -l 'DISCORD_CLIENT_SECRET=[A-Za-z0-9]' apps/` MUST return zero results.
- **`.env` files are `.gitignored`.** Never `git add apps/web/.env` or `apps/bot/.env`.
- **Railway env groups are the production source of truth.** Three groups recommended: **Web env** (`web`, `ssr`, `worker`, `scheduler`), **Bot env** (`bot`), **Worker env** (`rcon-worker`). Sharing via groups means rotating a value once flows to all consuming services on next deploy.
- **Audit trail.** Railway logs env var changes to the project audit log. For internal handover: capture which operator rotated which secret + when, in your team's secret-rotation runbook.
- **Local dev** uses `apps/web/.env`, `apps/bot/.env`, and (when scaffolded) `apps/rcon-worker/.env`. Copy from `.env.example` and fill in dev-only values (e.g. a separate Discord dev application — not the production one).
- **CI** does not need secrets for the standard quality gates (Pest + Pint + PHPStan use `phpunit.xml` `<env>` overrides). The `axe-core` workflow (`.github/workflows/a11y.yml`) targets the dev compose stack, not production.

---

For step-by-step deploy instructions referencing this matrix, see [`DEPLOYMENT.md`](./DEPLOYMENT.md).
For first-deploy verification using these values, see [`LAUNCH-CHECKLIST.md`](./LAUNCH-CHECKLIST.md).
