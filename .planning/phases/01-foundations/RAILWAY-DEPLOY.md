# Railway Deploy Walkthrough — Phase 1

> **Source:** D-014 LOCKED + `.planning/phases/01-foundations/01-RESEARCH.md` (Pitfall 6).
>
> One Railway project. **5 services + 2 plugins**:
>
> | Service       | Type    | Root Directory       | Public network | Notes                                       |
> | ------------- | ------- | -------------------- | -------------- | ------------------------------------------- |
> | `web`         | service | `apps/web`           | yes (HTTPS)    | Laravel + Inertia + Filament                |
> | `worker`      | service | `apps/web`           | no             | Same image as `web`; `queue:work` override  |
> | `bot`         | service | `apps/bot`           | no             | Discord bot (Phase 5 wiring)                |
> | `rcon-worker` | service | `apps/rcon-worker`   | no             | CRCON normaliser (Phase 8 wiring)           |
> | `db`          | plugin  | —                    | —              | Postgres 16                                 |
> | `redis`       | plugin  | —                    | —              | Redis 7                                     |

This document is the operator-facing runbook for the **first-time Railway deploy** of Trenchwars.
Every step is manual one-time setup; none of it is automated by the code in this repo.
Everything in this repo (the per-service `railway.json` + `nixpacks.toml` files) tells Railway *how to build* once the operator has wired the service tree below.

---

## Prerequisites

1. A Railway account at https://railway.com with the project owner role (or invited as a member with deploy permissions).
2. Railway CLI installed locally:
   ```bash
   npm install -g @railway/cli
   railway login
   ```
3. Discord OAuth credentials from https://discord.com/developers/applications:
   - `DISCORD_CLIENT_ID`
   - `DISCORD_CLIENT_SECRET`
   - The production redirect URI registered there must EXACTLY match the production `DISCORD_REDIRECT_URI` env value, including protocol and trailing-slash. (Pitfall 2 in `01-RESEARCH.md`.)
4. The repo cloned locally at `trenchwars/` with `apps/web/`, `apps/bot/`, `apps/rcon-worker/`, `packages/shared-types/`.
5. A locally-generated `APP_KEY` for production:
   ```bash
   docker compose exec web php artisan key:generate --show
   ```
   (Save the output — you'll paste it into the `app` env group below. Do NOT commit it.)

---

## Step 1 — Create the Railway project

```bash
cd trenchwars
railway init
```

Pick **Empty Project** and name it `trenchwars-prod`. Railway generates a `RAILWAY_PROJECT_ID` and links the local directory.

---

## Step 2 — Attach Postgres + Redis plugins

In the Railway dashboard:

1. **+ New** → **Database** → **Add PostgreSQL**. Pick PostgreSQL 16. Railway provisions a managed instance and exposes `DATABASE_URL` (and individual `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`, `PGDATABASE`) as service env.
2. **+ New** → **Database** → **Add Redis**. Pick Redis 7. Exposes `REDIS_URL`.

Both plugins live inside the same Railway project; their variables become referenceable across services via `${{Postgres.DATABASE_URL}}` and `${{Redis.REDIS_URL}}`.

---

## Step 3 — Create env groups (Shared Variables)

In the Railway dashboard, navigate to **Project Settings → Variables → Shared Variables**. Create FOUR shared variable groups (logical naming, not enforced by Railway):

| Group      | Variables                                                                                                                                                                                                                                                                                |
| ---------- | -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app`      | `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY=<paste from prerequisite #5>`, `APP_URL=https://your-trenchwars-domain.com`, `LOG_CHANNEL=stderr`, `LOG_DEPRECATIONS_CHANNEL=null`                                                                                                       |
| `database` | `DB_CONNECTION=pgsql`, `DB_URL=${{Postgres.DATABASE_URL}}` (Railway substitutes at deploy time)                                                                                                                                                                                            |
| `redis`    | `REDIS_URL=${{Redis.REDIS_URL}}`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`                                                                                                                                                                                    |
| `discord`  | `DISCORD_CLIENT_ID=<value>`, `DISCORD_CLIENT_SECRET=<value>` (mark as secret), `DISCORD_REDIRECT_URI=https://your-trenchwars-domain.com/auth/discord/callback`                                                                                                                              |

**Service ↔ env-group mapping:**

| Service       | Attaches groups                  |
| ------------- | -------------------------------- |
| `web`         | `app` + `database` + `redis` + `discord` |
| `worker`      | `app` + `database` + `redis` + `discord` |
| `bot`         | `app` + `discord`                |
| `rcon-worker` | `app` + `redis` (RCON-specific keys come in Phase 8) |

Mark `APP_KEY` and `DISCORD_CLIENT_SECRET` as **Secrets** in the Railway UI so they're redacted from build logs (Threat T-1-12-c, T-1-35).

---

## Step 4 — Configure four services

For each service, use **+ New** → **Empty Service**, name it, then attach to the same GitHub repo (or use `railway up` to push from local). Set the **Root Directory** per the topology table at the top of this doc.

### 4a — `web` service

1. **Settings → Service → Source → Repo**: link to the `trenchwars` GitHub repo.
2. **Settings → Service → Source → Root Directory**: `apps/web`.
3. **Settings → Variables → Add (service-specific, not from a group)**:
   - `NIXPACKS_PHP_ROOT_DIR=/app/public` — Pitfall 6; tells the Nixpacks PHP provider to point Caddy at Laravel's `public/` directory.
4. **Settings → Variables → Attach Group**: `app`, `database`, `redis`, `discord`.
5. **Settings → Networking → Public Networking**: enable. Railway issues `https://web-production-xxxx.up.railway.app` (you can attach a custom domain later).
6. **Settings → Health Check** → path: `/up`, timeout: `30s`. (Laravel 12 ships this route by default via `bootstrap/app.php`'s `withRouting(health: '/up')`.)

### 4b — `worker` service

This is the **same image as `web`** but with a different start command — no separate code, no separate build.

1. **+ New** → **Empty Service** → **Source → Repo**: link to the same `trenchwars` repo.
2. **Settings → Service → Source → Root Directory**: `apps/web` (same as `web`).
3. **Settings → Deploy → Custom Start Command**: `php artisan queue:work --tries=3 --backoff=10 --timeout=120`.
4. **Settings → Variables → Add**: `NIXPACKS_PHP_ROOT_DIR=/app/public` (build phases need it; even though the worker doesn't serve HTTP, the build step is identical to web).
5. **Settings → Variables → Attach Group**: `app`, `database`, `redis`, `discord`.
6. NO public networking (worker is an internal job runner — Postgres + Redis are reached over Railway's private network).
7. NO health check (queue:work doesn't bind to HTTP).

### 4c — `bot` service

1. **+ New** → **Empty Service** → **Source → Repo**: link to the same repo.
2. **Settings → Service → Source → Root Directory**: `apps/bot`.
3. **Settings → Variables → Add**: `NIXPACKS_NODE_VERSION=22`.
4. **Settings → Variables → Attach Group**: `app`, `discord`.
5. NO public networking.

### 4d — `rcon-worker` service

1. **+ New** → **Empty Service** → **Source → Repo**: link to the same repo.
2. **Settings → Service → Source → Root Directory**: `apps/rcon-worker`.
3. **Settings → Variables → Add**: `NIXPACKS_NODE_VERSION=22`.
4. **Settings → Variables → Attach Group**: `app`, `redis` (RCON-specific keys land in Phase 8).
5. NO public networking.

---

## Step 5 — Push to deploy

```bash
git push origin main
```

Railway auto-builds each service whose Root Directory contains a changed file (path-based service triggers can be configured under **Settings → Service → Triggers**, but by default any push rebuilds all services).

Tail the logs:

```bash
railway logs --service web
railway logs --service worker
railway logs --service bot
railway logs --service rcon-worker
```

Run migrations once after the first deploy (the `web` service does NOT run migrations on every boot to avoid races; this is intentional):

```bash
railway run --service web php artisan migrate --force
```

---

## Step 6 — Smoke check

1. Open the public web URL in a browser. The landing page renders with the "Log in with Discord" CTA.
2. Click "Log in with Discord". Complete OAuth on Discord. You land back on `/` with a success flash.
3. Grant yourself admin via Railway CLI:
   ```bash
   railway run --service web php artisan trenchwars:make-admin <YOUR_DISCORD_USER_ID>
   ```
4. Open `/admin`. The Filament panel renders with the trench-military theme; you see User, Player, Role, Permission resources and the Audit page.
5. Verify the worker service is processing jobs:
   ```bash
   railway logs --service worker
   ```
   Should show `Processing: Closure` or job-class names when queue activity happens.
6. Verify the health check is green: Railway dashboard → `web` service → Deployments → most recent → Health Check should report 200 OK against `/up`.

---

## Troubleshooting

| Symptom                                  | Cause                                                                                          | Fix                                                                                                                                                                                |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Build error: "no PHP detected"           | Root Directory not set on the `web`/`worker` service                                          | **Settings → Source → Root Directory** = `apps/web`                                                                                                                                |
| Build error on `bot`: "package not found"| `cd ../..` not happening before `pnpm install` (workspace not resolvable)                     | Confirm `apps/bot/nixpacks.toml` install phase has `cd ../.. && pnpm install --filter @trenchwars/bot...`                                                                          |
| 502 from Caddy on web service            | `NIXPACKS_PHP_ROOT_DIR` missing or wrong                                                      | **Settings → Variables**: `NIXPACKS_PHP_ROOT_DIR=/app/public`                                                                                                                      |
| "Couldn't reach Discord" toast on /auth  | Discord redirect URI mismatch                                                                 | https://discord.com/developers/applications → register the EXACT production URL with NO trailing slash and matching protocol (Pitfall 2)                                          |
| 419 on form POST                         | `<meta csrf-token>` re-introduced into Blade                                                  | `git diff apps/web/resources/views/app.blade.php` — remove any csrf-token meta (Pitfall 3); Inertia handles XSRF via cookie                                                        |
| Filament panel CSS broken                | Filament theme bundle missing                                                                 | Confirm `pnpm run build` ran during the Nixpacks `build` phase; check `apps/web/public/build/filament/manifest.json` exists in deploy logs                                          |
| 403 on `/admin` even after `make-admin`  | Spatie permission guard mismatch (Pitfall 4)                                                  | Verify `config/permission.php` `default_guard_name => 'web'` is set                                                                                                                |
| Worker service idle (no jobs processing) | `queue:work` not running                                                                      | **Settings → Deploy → Custom Start Command** on the `worker` service must be `php artisan queue:work --tries=3 --backoff=10 --timeout=120`                                         |
| Build timeout                             | Nixpacks default 30-min build budget exceeded by composer + pnpm install                      | **Settings → Build → Build Timeout**: increase to 60 min; or split: pre-warm `vendor/` and `node_modules` via Railway's build-cache feature                                        |
| Env var change not reflected             | Railway env vars only re-inject on next deploy                                                | Trigger a redeploy: dashboard → service → Deployments → "Redeploy", or `railway up`                                                                                                |
| `psql` extension missing on first deploy | uuid-ossp / citext / pgcrypto not enabled in Railway Postgres                                 | Migrations enable extensions in their `up()` (Pitfall 5); if first deploy fails, manually `railway run --service web psql $DATABASE_URL -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"` |
| Bot/rcon-worker can't reach Postgres     | Wrong env: those services do NOT need `DATABASE_URL` (D-004 — bot is thin display layer)      | Confirm `bot` only has `app` + `discord` groups attached; `rcon-worker` only has `app` + `redis` (HMAC-signed POSTs to web; no direct DB access)                                   |

---

## Reference

- `.planning/phases/01-foundations/01-RESEARCH.md` — full research, including all pitfalls
- `apps/web/railway.json` — service-level Railway config (this is the source of truth, not a root `railway.toml`)
- `apps/web/nixpacks.toml` — build phases for the web/worker image
- `apps/bot/nixpacks.toml` + `apps/bot/railway.json` — bot service
- `apps/rcon-worker/nixpacks.toml` + `apps/rcon-worker/railway.json` — RCON worker service
- `.docs/02-architecture.md` — service topology
- `CLAUDE.md` §6 (Security) — secret handling, redirect_uri exact-match, CSRF, session cookie posture
- `.planning/PROJECT.md` D-014 — Railway 5 services + Postgres + Redis plugins (LOCKED)
