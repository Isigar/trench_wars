# Trenchwars

Multi-clan league platform for Hell Let Loose (game-agnostic data model).
Two clans schedule a scrim from Discord, slot up by role, play it on a registered match server, and watch results write themselves.

> **Round 1 status:** Phase 1 (Foundations) — building the deployable Laravel skeleton.
> See `.planning/ROADMAP.md` for the 9-phase plan.

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
├── .planning/                # GSD planning artifacts
└── .docs/                    # frozen design docs
```

---

## Documentation

- **AI/developer conventions:** `CLAUDE.md`
- **Locked decisions (D-001..D-021):** `.planning/PROJECT.md`
- **Roadmap (9 phases):** `.planning/ROADMAP.md`
- **Phase 1 plans:** `.planning/phases/01-foundations/`
- **Architecture (frozen):** `.docs/02-architecture.md`
- **Database schema (frozen):** `.docs/05-database-schema.md`
- **UI design contract:** `.planning/phases/01-foundations/01-UI-SPEC.md`

## License

Private — not yet licensed for redistribution.
