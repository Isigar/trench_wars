# Trenchwars

Multi-clan league platform for Hell Let Loose (game-agnostic data model).
Two clans schedule a scrim from Discord, slot up by role, play it on a registered match server, and watch results write themselves.

> **v1.0 status:** ✅ SHIPPED — milestone tag `v1.0` (2026-05-17). Nine phases (foundations → clans → games → matches → bot → tournaments → CMS → RCON automation → polish) delivered across 442 commits / 14 calendar days; 1303 Pest web + 176 bot Vitest + 40 rcon-worker Vitest tests passing; 21 ADRs LOCKED (D-001..D-021).
> Milestone archive: [`.planning/milestones/v1.0-ROADMAP.md`](./.planning/milestones/v1.0-ROADMAP.md) · audit: [`.planning/milestones/v1.0-MILESTONE-AUDIT.md`](./.planning/milestones/v1.0-MILESTONE-AUDIT.md).

---

## What This Is

- **Web** — Laravel 12 + PHP 8.4 + Inertia v2 + Vue 3 + Filament v3 admin panel.
- **Bot** — discord.js v14 thin display layer (Phase 5).
- **RCON worker** — Node + undici + ws bridge to CRCON (Phase 8).
- **Datastores** — Postgres 16 + Redis 7.
- **Hosting** — Railway (5 services + Postgres + Redis plugins).
- **Auth** — Discord OAuth only.

For locked architectural decisions and AI/developer conventions: see [`CLAUDE.md`](./CLAUDE.md) and [`.planning/PROJECT.md`](./.planning/PROJECT.md).

---

## Prerequisites

You need exactly three things on your host:

1. **Docker Desktop** with WSL integration enabled for your distro
   *(Settings → Resources → WSL Integration → enable for this distro → Apply & Restart)*.
2. **Node.js 22** *(only needed for editor tooling; everything build-related runs inside containers)*.
3. **Git**.

You do **NOT** need PHP, Composer, Postgres, or Redis on your host. The host PHP install (8.3 with broken `intl`) is **not used**.

---

## First-time setup

### 1. Clone and copy env

```bash
git clone <repo-url> trenchwars
cd trenchwars
cp .env.example .env
```

### 2. Create a Discord OAuth application

1. Open https://discord.com/developers/applications and click **New Application**. Name it `Trenchwars Local Dev` (or whatever you like).
2. In the application's **OAuth2** tab, copy **Client ID** and **Client Secret**.
3. Under **OAuth2 → Redirects**, add exactly: `http://localhost:8000/auth/discord/callback`
   *(no trailing slash — Discord matches verbatim).*
4. Save changes in the Discord portal.

### 3. Fill in your `.env`

Edit `.env` and set:

```bash
DISCORD_CLIENT_ID=<from step 2>
DISCORD_CLIENT_SECRET=<from step 2>
# DISCORD_REDIRECT_URI defaults to http://localhost:8000/auth/discord/callback (matches step 3) — leave as is.
```

Leave the database, Redis, and other defaults alone for local dev.

### 4. Bring up the stack

```bash
make up        # docker compose up -d (postgres, redis, web, web-nginx, bot, rcon-worker)
make ps        # confirm services are healthy (wait ~30s on first build)
```

### 5. Generate the app key, run migrations, seed the first admin

```bash
make artisan ARGS="key:generate"
make migrate
# Once you've logged in once with Discord, grant yourself admin:
make artisan ARGS="trenchwars:make-admin <YOUR_DISCORD_USER_ID>"
# Find your Discord user ID: enable Developer Mode in Discord, right-click your name → Copy User ID.
```

### 6. Open the app

- Public site: http://localhost:8000/
- Admin (Filament): http://localhost:8000/admin

Click **Log in with Discord** on the landing page to complete OAuth. The first login auto-creates your `users`, `players`, and `player_privacy` rows.

---

## Daily commands

| Command | What it does |
|---|---|
| `make up` | Start all services (detached) |
| `make down` | Stop all services |
| `make logs` | Tail logs from all services |
| `make shell` | Bash shell inside the `web` container |
| `make artisan ARGS="..."` | Run any artisan command |
| `make composer ARGS="..."` | Run composer (require, install, update) |
| `make pnpm ARGS="..."` | Run pnpm (install, add, build) |
| `make pest` | Run the Pest test suite |
| `make pint` | Run Pint formatter |
| `make phpstan` | Run PHPStan level 8 |
| `make migrate` | Run pending migrations |
| `make fresh` | Drop and re-run migrations + seeders |

---

## Production

Trenchwars v1.0 deploys to Railway as 5 services + Postgres 16 + Redis 7 plugins (D-014). The Dockerfiles under `docker/` and the per-service `nixpacks.toml` files in `apps/*/` carry the build contract; production uses the same images as local dev (D-021).

- [`DEPLOYMENT.md`](./DEPLOYMENT.md) — Railway deploy walkthrough (per-service plumbing, first deploy, first-boot data, rollback).
- [`CONFIGURATION.md`](./CONFIGURATION.md) — authoritative env-var matrix across web / bot / rcon-worker / ssr / worker.
- [`LAUNCH-CHECKLIST.md`](./LAUNCH-CHECKLIST.md) — sequential go-live checklist consolidating the 9-phase manual smoke from `.planning/milestones/v1.0-phases/`.

---

## Project layout

```
trenchwars/
├── apps/
│   ├── web/                  # Laravel 12 + Inertia + Filament (PHP 8.4)
│   ├── bot/                  # Discord bot (Node 22, Phase 5)
│   └── rcon-worker/          # CRCON adapter (Node 22, Phase 8)
├── packages/
│   └── shared-types/         # TS types generated from PHP DTOs
├── docker/                   # per-service Dockerfiles
├── docker-compose.yml        # local dev stack
├── Makefile                  # short aliases for compose exec
├── .env.example              # env shape (copy to .env, fill in secrets)
├── CLAUDE.md                 # AI/developer conventions (READ THIS FIRST)
├── DEPLOYMENT.md             # Railway production deploy guide
├── CONFIGURATION.md          # env-var matrix
├── LAUNCH-CHECKLIST.md       # go-live checklist
├── .planning/                # GSD planning artifacts
└── .docs/                    # frozen design docs
```

---

## Documentation

**Launch + ops:**

- [`DEPLOYMENT.md`](./DEPLOYMENT.md) — production Railway deploy guide.
- [`CONFIGURATION.md`](./CONFIGURATION.md) — env-var matrix + rotation cadence.
- [`LAUNCH-CHECKLIST.md`](./LAUNCH-CHECKLIST.md) — sequential first-deploy → live checklist.

**Project conventions + architecture:**

- [`CLAUDE.md`](./CLAUDE.md) — AI/developer conventions, container-only rule (D-021), code style + test conventions, security checklist.
- [`.planning/PROJECT.md`](./.planning/PROJECT.md) — locked decisions (D-001..D-021), Current State, Next Milestone Goals.

**Milestone archive (v1.0):**

- [`.planning/milestones/v1.0-ROADMAP.md`](./.planning/milestones/v1.0-ROADMAP.md) — 9-phase roadmap with every plan + per-phase deliverables.
- [`.planning/milestones/v1.0-REQUIREMENTS.md`](./.planning/milestones/v1.0-REQUIREMENTS.md) — mappable v1 requirements + v2 deferrals.
- [`.planning/milestones/v1.0-MILESTONE-AUDIT.md`](./.planning/milestones/v1.0-MILESTONE-AUDIT.md) — cross-phase integration audit + hotfix log.

**Frozen design docs:**

- `.docs/02-architecture.md` — system architecture.
- `.docs/05-database-schema.md` — database schema.
- `.docs/14-roadmap.md` — original M1-M9 plan.

## License

Private — not yet licensed for redistribution.
