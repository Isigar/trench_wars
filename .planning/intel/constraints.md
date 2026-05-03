# Constraints (synthesized intel)

Technical contracts, schemas, NFRs, and protocols extracted from the 11 SPEC-class documents plus the tech-stack constraints from `03-stack.md` (DOC-classified, but content is a locked tech-choice list per ingest manifest note — included here as `protocol`/`nfr`-style constraints).

Precedence reminder: where any constraint here conflicts with an ADR in `decisions.md`, the ADR wins (logged in `INGEST-CONFLICTS.md` / INFO bucket). No such conflicts were detected in this ingest.

---

## CON-stack-versions
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md
- type: protocol (locked tech versions)
- content:
  - PHP 8.4
  - Laravel 12 (latest LTS line at planning time)
  - Node.js 22 LTS
  - TypeScript 5.x
  - Postgres 16
  - Redis 7

## CON-stack-web-libraries
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md
- type: protocol (locked library set for `apps/web` Laravel)
- content: inertiajs/inertia-laravel v2; laravel/socialite (Discord OAuth); laravel/sanctum (bot tokens); filament/filament v3; spatie/laravel-permission; spatie/laravel-activitylog; spatie/laravel-translatable; spatie/laravel-sluggable; spatie/laravel-sitemap; spatie/laravel-data; laravel/horizon; larastan/larastan (PHPStan level 8); laravel/pint; pestphp/pest; spatie/laravel-medialibrary. No Laravel starter kit.

## CON-stack-frontend-libraries
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md, /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol (locked frontend stack, rendered inside Laravel)
- content: Vue 3 + Inertia v2 + Vite + TypeScript + Tailwind CSS v4 (CSS-first config, no `tailwind.config.ts`); Reka UI primitives; Pinia (cross-page state only); TanStack Table v8; vue-cal v4; vue-chartjs / Chart.js v4; lucide-vue-next; vue-sonner; dayjs (with locale plugins); Tiptap v2 (admin only); markdown-it (public render); laravel-vue-i18n; vue-draggable-plus; vue-virtual-scroller (only when row count > 100). Vitest + Playwright for tests.

## CON-stack-bot-libraries
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md
- type: protocol (locked stack for `apps/bot` Discord adapter)
- content: discord.js v14 (TypeScript); @discordjs/builders, @discordjs/rest; ioredis (subscriber for `discord:outbound`); undici (HTTP to web); pino (logging); vitest.

## CON-stack-rcon-libraries
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md
- type: protocol (locked stack for `apps/rcon-worker` CRCON adapter)
- content: TypeScript; undici (CRCON HTTP client); ioredis (consumer of `rcon:sessions` stream); ws (CRCON live log streaming when available); pino, vitest.

## CON-stack-tooling
- source: /home/rtx/projects/trench-wars/.docs/03-stack.md
- type: protocol (tooling)
- content: pnpm workspaces; changesets for cross-package versioning of `shared-types` if needed; lefthook or husky for git hooks (pint + eslint on staged); GitHub Actions for CI.

---

## CON-arch-services
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: protocol (service topology)
- content: Five Railway services + two managed plugins.
  - `web`: Laravel 12, PHP 8.4, Inertia v2, Vue 3 — public site, admin (Filament), REST API, source of truth.
  - `worker`: Laravel + Horizon — async jobs (Discord push, RCON scheduling, scheduled publishing, notifications). Same image as `web`, different start command.
  - `bot`: Node.js 22, discord.js v14, TypeScript — Discord adapter (slash commands, modals, role sync). Calls `web` for all logic.
  - `rcon-worker`: Node.js 22, TypeScript — CRCON adapter, subscribes to live match logs, pushes events to `web`.
  - `db`: Postgres 16 (Railway plugin) — primary store.
  - `redis`: Redis 7 (Railway plugin) — queues, cache, bot↔web pub/sub.

## CON-arch-monorepo-layout
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: schema (repo layout)
- content:
  ```
  trenchwars/
  ├── apps/
  │   ├── web/                # Laravel app (PHP 8.4)
  │   ├── bot/                # discord.js Node service (TS)
  │   └── rcon-worker/        # CRCON Node service (TS)
  ├── packages/
  │   └── shared-types/       # TS types shared between bot/rcon-worker/frontend
  ├── docker/                 # local dev docker-compose
  ├── docs/                   # planning docs
  ├── .github/workflows/      # CI
  ├── package.json            # pnpm workspaces root
  ├── pnpm-workspace.yaml
  ├── tsconfig.base.json
  └── README.md
  ```
  Composer root stays inside `apps/web`. Inertia/Vue lives inside `apps/web/resources/js` (not a separate workspace). `packages/shared-types` is consumed by `apps/bot`, `apps/rcon-worker`, and `apps/web` frontend; types generated from Laravel via spatie/laravel-data + custom artisan command emitting `.d.ts`.

## CON-arch-bot-to-web-comm
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md, /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: api-contract
- content: HTTP, Sanctum personal access token (one machine token per bot instance, scoped `bot:*`). Header `X-Bot-Acts-As-User: <discord_id>` on every interaction so `web` resolves causer correctly. Bot does no business logic and no DB writes.

## CON-arch-web-to-bot-comm
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md, /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: api-contract
- content: Redis pub/sub on channel `discord:outbound`. Envelope:
  ```json
  { "type": "channel.message", "channel_id": "...", "embed": {...}, "components": [...] }
  ```
  Outbound is durably persisted into `discord_outbound_messages` (`pending → sent | failed`). Stale `pending` rows older than N minutes retried by `worker`.

## CON-arch-rcon-to-web-comm
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md, /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: api-contract
- content: Signed HTTP from `rcon-worker` → `web`. `POST /api/internal/match/{id}/events`. HMAC SHA-256 over `(timestamp + body)`, header `X-Rcon-Signature`, 60s freshness window.

## CON-arch-web-to-rcon-comm
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: protocol
- content: Schedule-driven. `worker` enqueues `OpenRconSession` jobs from match scheduler; job publishes to Redis stream `rcon:sessions` which `rcon-worker` consumes.

## CON-arch-railway-deployment
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: protocol
- content: One Railway project; five services + two plugins. Each service deploys from a subdirectory using Railway's "Root Directory" config. Env groups: `database` (DATABASE_URL), `redis` (REDIS_URL), `discord` (bot token, app id, public key, webhook secret), `rcon` (HMAC secret), `app` (APP_KEY, APP_URL). Secrets in Railway only; `.env.example` documents shape.

## CON-arch-local-dev
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: protocol
- content: `docker/docker-compose.yml` runs Postgres + Redis. Each app runs natively with `pnpm dev` / `php artisan serve`. Tailscale or ngrok for Discord webhook tunnelling during bot dev.

## CON-arch-ci
- source: /home/rtx/projects/trench-wars/.docs/02-architecture.md
- type: protocol
- content: GitHub Actions matrix per app, triggered by path filters.
  - `web`: PHP 8.4 + Pest + PHPStan level 8 + Pint.
  - `bot` and `rcon-worker`: pnpm install, tsc, vitest, eslint.
  - `shared-types`: tsc only.

---

## CON-domain-entities
- source: /home/rtx/projects/trench-wars/.docs/04-domain-model.md
- type: schema (domain model)
- content: Entity map covering Identity (User, Player, PlayerPrivacy), Clans & taxonomy (Clan, ClanTag, ClanMembership, ClanInvite, ClanApplication), Games (Game, GameRole, GameMatchType, GameMatchTypeRoleLimit), Matches (Match, MatchAccessRule, MatchTeam, MatchSlot, MatchSignup, MatchResult, MatchPlayerStat, MatchEvent), Tournaments (Tournament, TournamentParticipant, TournamentStage, TournamentRound, TournamentBracket), Match infrastructure (MatchServer, MatchServerBooking), CMS (Article, Category, Event), Permissions & audit (Spatie Role/Permission, ActivityLog), Discord (DiscordGuild). Full prose at source.

## CON-domain-invariants
- source: /home/rtx/projects/trench-wars/.docs/04-domain-model.md
- type: schema (invariants)
- content:
  - A user has exactly zero or one active ClanMembership.
  - A MatchSignup's slot belongs to a MatchTeam in the same Match.
  - MatchSlot.capacity is decided at slot-materialisation; live count of confirmed signups must never exceed it (enforced by transaction with row lock on the slot).
  - A TournamentBracket's `advances_to_bracket_id` must be in the same tournament and a later round.
  - A MatchServerBooking's window must not overlap any other booking on the same server.

---

## CON-db-conventions
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema (DB conventions)
- content: Postgres 16. UUIDv7 primary keys (`uuid` column type with default `gen_random_uuid()` for now; switch to UUIDv7 when Postgres 17 ships, or use `symfony/uid` to generate v7 in app). Timestamps `created_at`, `updated_at` everywhere. Soft deletes `deleted_at` on user-facing entities (Clan, Player, Article, Event, Match). Translatable text columns are `jsonb` keyed by locale. Use Laravel `Schema::create` with explicit foreign keys; `cascadeOnDelete` only on `*_pivot` and `match_slots/match_signups/match_player_stats`. Restrict elsewhere. Every translatable jsonb has a CHECK constraint requiring at least one locale key. Encrypted columns use Laravel encrypted casts.

## CON-db-table-users
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `users(id uuid PK, discord_id text UNIQUE NOT NULL, username text NOT NULL, email text NULL, avatar_url text NULL, locale text DEFAULT 'en', left_community_at timestamptz NULL, last_login_at timestamptz NULL)`.

## CON-db-table-players
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `players(id uuid PK, user_id uuid UNIQUE FK users, slug text UNIQUE NOT NULL, display_name text NULL, avatar_source text DEFAULT 'discord', avatar_path text NULL, bio jsonb NULL, country_code text NULL)`.

## CON-db-table-player-privacy
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `player_privacy` 1:1 with `players`. Booleans default true except `show_real_name`. Fields: `show_real_name`, `show_discord_tag`, `show_clan_history`, `show_match_history`, `show_stats`, `show_to (public|community|clan|private)`.

## CON-db-table-clans
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `clans(id uuid PK, slug text UNIQUE, tag text UNIQUE, name text NOT NULL, description jsonb translatable, country_code text NULL, owner_user_id uuid FK users, status text DEFAULT 'active' (active/suspended/disbanded), discord_role_id text NULL, discord_announce_channel_id text NULL)`.

## CON-db-table-clan-tags
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `clan_tags(id, slug UNIQUE, label jsonb translatable, color text)`. Pivot `clan_clan_tag(clan_id, clan_tag_id)` PK composite.

## CON-db-table-clan-memberships
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `clan_memberships(id uuid PK, clan_id FK clans, user_id FK users, role text NOT NULL ∈ {leader|officer|member|recruit}, joined_at NOT NULL, left_at NULL, invited_by FK users NULL)`. Partial unique index `(user_id) WHERE left_at IS NULL` enforces one active clan.

## CON-db-table-clan-invites-applications
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `clan_invites` and `clan_applications`: `id, clan_id, user_id, status, message, decided_by, decided_at`.

## CON-db-table-games
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `games(id, slug UNIQUE, name jsonb translatable, description jsonb, icon_path, status, sort_order)`.

## CON-db-table-game-roles
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `game_roles(id, game_id, key, label jsonb, description jsonb, category ∈ {command|infantry|armor|recon|support}, is_command_role bool, sort_order, max_per_team_default)`. UNIQUE `(game_id, key)`.

## CON-db-table-game-match-types
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `game_match_types(id, game_id, key, label jsonb, description jsonb, team_count_default int, team_size_default int, scoring_rules jsonb)`. UNIQUE `(game_id, key)`.

## CON-db-table-game-match-type-role-limits
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `game_match_type_role_limits(id, game_match_type_id, game_role_id, capacity int)`. UNIQUE `(game_match_type_id, game_role_id)`.

## CON-db-table-matches
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `matches(id uuid PK, game_id FK games, game_match_type_id FK game_match_types, host_clan_id FK clans, title text NULL, visibility text NOT NULL ∈ {public|private|tag-restricted}, status text NOT NULL ∈ {draft|scheduled|live|finished|cancelled}, scheduled_start NOT NULL, scheduled_end NOT NULL, match_server_id FK match_servers NULL, tournament_bracket_id FK tournament_brackets NULL, created_by FK users)`. Indexes on `scheduled_start`, `status`, `host_clan_id`, `tournament_bracket_id`.

## CON-db-table-match-access-rules
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_access_rules(id, match_id, clan_tag_id)`. Whitelist of allowed tags when visibility is `tag-restricted`.

## CON-db-table-match-teams
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_teams(id, match_id, side ∈ {A|B|C|...}, clan_id NULL, score int NULL)`.

## CON-db-table-match-slots
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_slots(id, match_team_id, game_role_id, capacity int, sort_order, label_override jsonb NULL)`.

## CON-db-table-match-signups
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_signups(id, match_slot_id, user_id, status ∈ {confirmed|pending|standby|withdrawn}, created_at)`. UNIQUE `(match_slot_id, user_id) WHERE status = 'confirmed'`.

## CON-db-table-match-results
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_results(id, match_id UNIQUE, winner_team_id NULL, score_summary jsonb, source ∈ {rcon|manual|peer}, captured_at, confirmed_by NULL)`.

## CON-db-table-match-events
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_events(id, match_id, type, payload jsonb, occurred_at)`. Index on `(match_id, occurred_at)`.

## CON-db-table-match-player-stats
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_player_stats(id, match_id, user_id, role_played_id FK game_roles NULL, kills, deaths, assists, score, time_played_seconds)`. UNIQUE `(match_id, user_id)`.

## CON-db-table-match-servers
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_servers(id, name, region, host, crcon_base_url, crcon_api_key_encrypted, status ∈ {active|maintenance|offline}, notes)`. CRCON key encrypted with `Crypt::encryptString` at rest.

## CON-db-table-match-server-bookings
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `match_server_bookings(id, match_server_id, match_id UNIQUE, window_start, window_end)`. Postgres exclusion constraint preventing overlap on `(match_server_id, tstzrange(window_start, window_end))`.

## CON-db-table-tournaments
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `tournaments(id, game_id, slug, name jsonb, description jsonb, format_hint text, start_date, end_date, status, visibility, created_by)`.

## CON-db-table-tournament-participants
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `tournament_participants(id, tournament_id, clan_id, seed int NULL, status ∈ {registered|active|eliminated|withdrawn}, eliminated_at)`. UNIQUE `(tournament_id, clan_id)`.

## CON-db-table-tournament-stages
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `tournament_stages(id, tournament_id, name, format ∈ {round_robin|single_elim|double_elim|swiss}, sort_order, settings jsonb)`.

## CON-db-table-tournament-rounds
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `tournament_rounds(id, tournament_stage_id, number int, starts_at timestamptz NULL)`.

## CON-db-table-tournament-brackets
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `tournament_brackets(id, tournament_round_id, bracket_position int, home_participant_id NULL, away_participant_id NULL, match_id UNIQUE NULL FK matches, winner_participant_id NULL, advances_to_bracket_id NULL FK self)`.

## CON-db-table-articles
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `articles(id, slug, category_id NULL, title jsonb, excerpt jsonb, body jsonb, hero_image_path, published_at, scheduled_for, author_user_id)`. Indexes on `published_at`, `scheduled_for`.

## CON-db-table-categories
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `categories(id, slug, name jsonb)`.

## CON-db-table-events
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `events(id, slug, title jsonb, description jsonb, starts_at, ends_at, location text, kind ∈ {match|tournament|league|external}, linkable_type, linkable_id)` polymorphic to match/tournament.

## CON-db-table-discord-guild
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `discord_guild` single row: `guild_id, name, system_channel_id, default_member_role_id, bot_status_channel_id`.

## CON-db-table-discord-outbound-messages
- source: /home/rtx/projects/trench-wars/.docs/05-database-schema.md
- type: schema
- content: `discord_outbound_messages(id, channel_id, payload jsonb, status ∈ {pending|sent|failed}, attempts, last_error, sent_at)`. Used for retry/durability of bot pushes.

---

## CON-perm-two-layer
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol (authorization model)
- content: Two layers — (1) Platform roles (Spatie), global, applied to staff users; (2) Clan roles (enum on `clan_memberships.role`), scoped to one clan. Policies combine both; e.g. *edit clan profile* = (caller has clan membership in this clan AND role ∈ {Leader, Officer}) OR (caller has platform role admin/super-admin).

## CON-perm-platform-roles
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol
- content: Platform roles seeded by migration, editable in Filament:
  - `super-admin`: Bootstrap user(s); everything, including managing other admins.
  - `admin`: Trusted staff; full domain CRUD; cannot manage super-admins.
  - `moderator`: Volunteers; read all, edit clans/players for moderation, ban, resolve match disputes.
  - `cms-editor`: Editorial; articles, events, categories. Cannot touch clans/matches.

## CON-perm-permission-naming
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol
- content: Naming convention `<resource>.<action>`. Examples: `clan.viewAny`, `clan.update`, `clan.suspend`, `clan.disband`, `match.create`, `match.update.any`, `match.update.own`, `match.cancel`, `match.result.publish`, `tournament.manage`, `article.publish`, `audit.view`, `permissions.assign`. Full matrix in `database/seeders/PermissionSeeder.php`; UI checkbox grid (Role × Permission) at `/admin/permissions`.

## CON-perm-clan-roles
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol
- content:
  - `leader`: Disband, transfer leadership, manage all members and tags.
  - `officer`: Invite, kick, accept applications, edit clan profile, create matches as clan.
  - `member`: Sign up to matches as clan member, view clan-internal pages.
  - `recruit`: Same as member but flagged in UI; not eligible for tournament rosters by default.

## CON-audit-subjects
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol (audit log)
- content: `spatie/laravel-activitylog` subjects: User, Player, Clan, ClanMembership, ClanTag, Game, GameRole, GameMatchType, Match, MatchSlot, MatchSignup, MatchResult, MatchServer, Tournament, Article, Event, Role, Permission. Logged: Filament create/update/delete (LogsActivity trait), domain events (clan join/leave, match signup/cancel, slot capacity change, result publish, RCON ingestion, role/permission changes), auth events (login, logout, locale change, profile privacy change), Discord-side actions (causer = User behind interaction).

## CON-audit-event-names
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol
- content: Conventional event names emitted via `AuditService` wrapping `activity()->performedOn(...)->causedBy(...)->log(...)`: `clan.member.joined`, `clan.member.left`, `clan.member.role.changed`, `match.created`, `match.signup.confirmed`, `match.signup.withdrawn`, `match.result.published`, `permission.role.assigned`, `permission.role.revoked`. Discord interactions resolve causer via Sanctum-authenticated bot token's `actsAsUserId` header.

## CON-audit-surfaces
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: protocol
- content: Per-entity Audit tab in Filament resources via RelationManager filtered by `subject_type/subject_id`. Global Audit Log page at `/admin/audit` filterable by actor, subject type, event, date range, batch UUID. Read-only — no edits/deletes through UI.

## CON-audit-retention
- source: /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
- type: nfr
- content: Round 1: indefinite. Revisit after six months — likely partition `activity_log` by month and archive past 12 months.

---

## CON-discord-oauth
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: protocol
- content: OAuth via Socialite. `GET /auth/discord` → Discord with scopes `identify email`. `GET /auth/discord/callback` resolves/creates `User` keyed by `discord_id`. On first login, also create matching `Player` and `PlayerPrivacy`. Store Discord avatar URL and locale. Discord access tokens are NOT stored long-term — only what's needed at login moment. Optional `guilds` scope check ("must be in league guild") off by default round 1.

## CON-discord-bot-arch
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: protocol
- content: Node.js bot is a thin Discord adapter. Subscribes to Discord gateway events; renders interactions (slash commands, modals, components) by calling `web` and rendering the response; subscribes to Redis `discord:outbound`; holds no domain state — restarts safe. Auth to `web` via Sanctum personal access token scoped `bot:*` + header `X-Bot-Acts-As-User: <discord_id>`. Latency budget: 3s Discord interaction window — fast reads call `web` synchronously, anything risky uses `deferReply()`.

## CON-discord-slash-commands
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: api-contract (round 1 slash commands)
- content:
  - `/clan info <name>` → `GET /api/v1/clans/{slug}`
  - `/clan list` → `GET /api/v1/clans` (paginated; Discord components for paging)
  - `/clan apply <name>` → `POST /api/v1/clans/{slug}/applications` (DM officer)
  - `/match list` → `GET /api/v1/matches?upcoming=1` (filtered by user clan tags)
  - `/match info <id>` → `GET /api/v1/matches/{id}` (embed with slot availability)
  - `/match signup <id>` → modal → `POST /api/v1/matches/{id}/signups`
  - `/match leave <id>` → `DELETE /api/v1/matches/{id}/signups/me`
  - `/profile [@user]` → `GET /api/v1/players/{discord_id}` (privacy-aware)
  - `/me` → `GET /api/v1/players/me` (always full data for self)

## CON-discord-event-hooks
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md, /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract (Discord → web)
- content: All payloads HMAC-signed (header `X-Discord-Signature`, 60s timestamp window).
  - `guildMemberRemove` → `POST /api/v1/discord/events/member-leave` (`{discord_id}`) — mark `users.left_community_at`; end active `ClanMembership`.
  - `guildMemberUpdate` (role removed) → `POST /api/v1/discord/events/role-removed` (`{discord_id, role_id}`) — mirror manual Discord-admin clan-role removal.
  - `guildMemberAdd` (verified) → `POST /api/v1/discord/events/member-add` (`{discord_id, joined_at}`) — optional auto-link if Discord ID exists.

## CON-discord-outbound-use-cases
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: protocol
- content: Match created → announce in host clan's channel; match starting in 1h / 15m → mention signed-up players; match cancelled → announce + DM signups; result published → results card with MVPs; tournament bracket advanced → bracket update card.

## CON-discord-role-sync
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: protocol
- content: Joining a clan in `web` → enqueue `AssignDiscordRole(user, clan)` → bot adds role. Leaving / kicked → `RemoveDiscordRole`. Failures retry via Horizon. Discord-side manual changes reconciled via `guildMemberUpdate` hook.

## CON-discord-security
- source: /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
- type: nfr
- content: Replay protection: HMAC + 60s timestamp window. Spoofed actor: bot uses scoped token; `acts-as` header trusted only when caller is bot service identity. Rate limits: bot respects Discord rate limits via discord.js built-in; `web` rate-limits API per-bot-token via Laravel rate limiter.

---

## CON-rcon-server-model
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: Match servers are league-owned, registered in Filament under "Match Servers". Each has CRCON deployment alongside (same host or LAN). Storing CRCON credentials, not raw RCON — CRCON exposes richer API (live logs, structured events, player stats).

## CON-rcon-booking
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: Creating a Match optionally links a `match_server_id`. Saving creates `match_server_bookings` covering `[scheduled_start − 5m, scheduled_end + 30m]`. Postgres exclusion constraint prevents overlap. Filament shows server availability per slot when scheduling.

## CON-rcon-session-lifecycle
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: Per booked match:
  1. At `start − 5m`: worker enqueues `OpenRconSession(match_id)`; publishes onto Redis stream `rcon:sessions` with booking window.
  2. `rcon-worker` consumes; opens CRCON session — validates connectivity, subscribes to live log endpoint.
  3. During window: streams events (kill, round_start, round_end, player_join, player_leave, side_win, score), normalises them, POSTs batches to `web` (`/api/internal/match/{id}/events`), HMAC signed.
  4. On `round_end`: posts synthesized `match_end` event. `web` computes `MatchResult` (winner, score) and persists `MatchPlayerStat` rows.
  5. At `end + 30m`: session closes, worker ack'd, Match status flips `live → finished`.

## CON-rcon-event-types
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: schema (normalised `match_events.type`)
- content: `match_start`, `round_start`, `round_end`, `kill` (attacker/victim/weapon), `player_join`, `player_leave`, `team_switch`, `side_win` (allies/axis), `score_change`, `match_end`, `error` (CRCON unreachable, log gap, etc.). Payload is normalised JSON — no HLL-specific keys at this layer beyond `weapon` string.

## CON-rcon-result-computation
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: At `match_end`: (1) reduce events into per-team scores using `game_match_types.scoring_rules`; (2) decide winner team; (3) aggregate `MatchPlayerStat` from `kill` events grouped by attacker/victim; (4) persist `MatchResult` with `source = 'rcon'`; (5) trigger Discord announce + audit log.

## CON-rcon-manual-override
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: `MatchResult` always allows admin/officer edit — winner, scores, MVPs. Mark `source = 'manual'` (or `peer` if both clans confirmed). Audit log records override. If RCON ingestion fails entirely, organiser enters result by hand.

## CON-rcon-failure-modes
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content:
  - CRCON unreachable on session open → worker retries with backoff for 5 minutes, then publishes `match_events.error`. `web` flags match for manual entry.
  - Log gap (mid-match disconnect) → on reconnect, worker requests CRCON's historical log slice for missing window. Fills `match_events`. If still incomplete, falls back to manual.
  - Server not booked but match started → worker has nothing to do; admin enters manually.
  - CRCON API key rotated → server marked `status = offline`; admin re-enters key in Filament. Existing bookings fail open and require manual entry.

## CON-rcon-security
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: nfr
- content: CRCON keys encrypted at rest via Laravel encrypted casts. Outbound `rcon-worker` → CRCON: HTTPS-only when CRCON exposed publicly; HTTP allowed via private network (per-server `crcon_base_url`). Inbound `rcon-worker` → `web`: HMAC SHA-256 over `(timestamp + body)`, header `X-Rcon-Signature`, 60s freshness window.

## CON-rcon-test-connectivity
- source: /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
- type: protocol
- content: Filament action `Test Connection` on `MatchServer` calls CRCON `/api/get_status`, returns latency and current game state.

---

## CON-frontend-goals
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: nfr
- content:
  1. Fast first load. SSR-friendly (Inertia SSR enabled in production).
  2. Mobile-first: clan members organise scrims from their phones.
  3. Dark mode default with light mode option.
  4. Accessible: keyboard, screen-reader, contrast AA.
  5. Translatable from day one — no hardcoded strings.
  6. Component library is owned, not a black box.

## CON-frontend-build
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol
- content: Vite as bundler, integrated through `laravel/vite-plugin`. TypeScript for all `resources/js`. SSR: separate Vite SSR entry for Inertia SSR, enabled in production. Code splitting by Inertia page (Vite default for dynamic imports). Lazy load heavy modules: charts, calendar, bracket viewer, rich-text editor.

## CON-frontend-styling-tokens
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: schema (theme tokens)
- content: Tailwind CSS v4 (CSS-first config, no `tailwind.config.ts`). CSS variables for theme tokens in `resources/css/tokens.css`. Two themes: `dark` (default) and `light`. Tokens cover: color (background, surface, surface-elevated, border, text, muted, accent, danger, success, warning), spacing (Tailwind default scale), radius (`--radius-sm: 4px`, `--radius-md: 6px`, `--radius-lg: 10px`), font-stack, shadow, motion durations.

## CON-frontend-visual-direction
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol
- content: Trench/military aesthetic without kitsch. Restrained palette: muted olive surfaces, off-white text, blood-red accent (~#A4262C), warning ochre. Type: Inter for UI; JetBrains Mono for tags, IDs, small caps labels. High-contrast hero photos for clan/tournament pages, restrained elsewhere. Radii: 6px default, 10px on cards, 4px on inputs.

## CON-frontend-component-library
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: schema (component inventory)
- content: Reka UI primitives (wrapped in `resources/js/components/ui/`): Button, IconButton, Input, Textarea, Select, Combobox, Switch, Checkbox, Radio, Slider, Dialog, Sheet, Drawer, Tabs, Accordion, Tooltip, Popover, DropdownMenu, Toast, Avatar, Badge, Tag, Card, Separator, ScrollArea, Pagination, Skeleton, Spinner. Domain components (in `resources/js/components/domain/`): ClanCard, ClanTagChip, PlayerCard, PlayerAvatar (handles privacy + Discord-vs-upload source), MatchCard, MatchSlotList, MatchSlotPicker, MatchResultCard, BracketView, RoundRobinTable, TournamentStandings, EventCalendar, RsvpButton, RoleBadge.

## CON-frontend-state
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol
- content: Inertia carries page state. No client-side router. Pinia reserved for cross-page concerns: current user, locale, toasts queue, unread notifications, theme. Avoid duplicating server state in Pinia — re-render via Inertia visit when stale.

## CON-frontend-forms
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol
- content: Inertia `useForm` for everything. Server returns Laravel validation errors which `useForm` surfaces field-by-field. `<FormField>` wrapper ties label + control + error. Optimistic UI only where safe (toasts, RSVP toggles); destructive actions always wait for server.

## CON-frontend-bracket-viewer
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md, /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol
- content: Custom Vue components. Single-elim: standard cascading rounds, lines drawn with SVG. Double-elim: winners + losers brackets stacked, grand-final tail. Round-robin: matrix table. Swiss: standings + per-round lists. Bracket data from `web` already laid out (round, position).

## CON-frontend-pages-layouts
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: schema
- content: Layouts: `PublicLayout` (header: logo, nav, locale switcher, login button; footer); `AuthedLayout` (extends Public with user menu and notifications dropdown); `AdminLayout` (Filament-owned). Pages tree:
  ```
  Public:
    Home.vue, clans/Index.vue (/clans), clans/Show.vue (/clans/{slug}),
    players/Show.vue (/players/{slug}), matches/Index.vue (/matches),
    matches/Show.vue (/matches/{id}), tournaments/Index.vue (/tournaments),
    tournaments/Show.vue (/tournaments/{slug}), events/Index.vue (/events),
    blog/Index.vue (/blog), blog/Show.vue (/blog/{slug}),
    auth/Login.vue (/login)
  Authed:
    me/Profile.vue (/me), me/Privacy.vue (/me/privacy),
    me/Notifications.vue (/me/notifications), me/Clan.vue (/me/clan),
    matches/SignupModal.vue (modal opened from Show)
  ```

## CON-frontend-folder-structure
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: schema
- content:
  ```
  resources/js/
  ├── app.ts                  # Inertia + Vue bootstrap
  ├── ssr.ts                  # SSR entry
  ├── layouts/
  ├── pages/
  ├── components/
  │   ├── ui/                 # owned primitives
  │   ├── domain/             # league-specific components
  │   └── icons/              # only if we need custom SVGs beyond lucide
  ├── composables/            # useUser, useLocale, useTheme, useToasts
  ├── stores/                 # Pinia stores
  ├── lib/                    # http client, formatters, validators
  ├── locales/                # JSON translation files (en.json initial)
  └── types/                  # generated from Laravel via spatie/laravel-data
  ```

## CON-frontend-type-safety
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md, /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: protocol
- content: Backend exposes DTOs through `spatie/laravel-data` resources. Custom artisan command `php artisan typescript:generate` writes `resources/js/types/api.d.ts` with all DTO types. Frontend imports from `@/types/api`. Same generation flow exports types into `packages/shared-types` for the bot.

## CON-frontend-performance
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: nfr
- content: SSR for first paint on public pages. Inertia partial reloads on filter/pagination (don't re-render unchanged props). Image handling via `spatie/laravel-medialibrary` with responsive sizes; serve WebP. Long lists virtualised with `vue-virtual-scroller` only when row count > 100. Avoid global Pinia stores that load eagerly.

## CON-frontend-accessibility
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: nfr
- content: Reka UI primitives provide focus management, ARIA, keyboard nav. Color contrast tested at AA on both themes. Visible focus rings everywhere — `:focus-visible` styled in tokens. Form fields linked to labels and error messages by id. Live regions for toasts.

## CON-frontend-testing
- source: /home/rtx/projects/trench-wars/.docs/09-frontend.md
- type: protocol
- content: Vitest for unit tests of composables and pure components. Playwright smoke tests on critical paths: login, create match, sign up to slot, view bracket. Run in CI on PR. No heavy snapshot tests.

---

## CON-i18n-locale-resolution
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol (per-request locale resolution order)
- content:
  1. Authenticated user's `locale` column.
  2. `?lang=` query parameter (sets cookie).
  3. `lang` cookie.
  4. `Accept-Language` header.
  5. Default `en`.
  Stored on `users.locale`. Updated when user changes language. Discord locale honoured on first login.

## CON-i18n-static-strings
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content: Backend: standard Laravel `__('key')` and `lang/{locale}/*.php`. Frontend: `laravel-vue-i18n` — translations merged from `lang/` into Inertia shared props (`translations`) per request, hydrated client-side. Filament: built-in i18n, locale follows Laravel app locale.

## CON-i18n-translatable-content
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: schema
- content: `spatie/laravel-translatable`. Translatable columns are jsonb keyed by locale, e.g. `{"en": "Allied Command", "cs": "Velitelství spojenců"}`. Models: `Clan.description`, `ClanTag.label`, `Game.name/description`, `GameRole.label/description`, `GameMatchType.label/description`, `Article.title/excerpt/body`, `Category.name`, `Event.title/description`. Filament shows per-locale tabs in form components for translatable fields. Locale list lives in config. Adding a locale: add to config + run backfill seeder adding the locale key to every translatable column with empty string.

## CON-i18n-fallback
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content: When active locale missing for a row, fall back to first non-empty locale (order: active → en → first available). Never show raw JSON.

## CON-i18n-formatting
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content: dayjs on frontend with locale plugins. Plurals via vue-i18n style: `t('matches', n)` selects from `'no matches | one match | {count} matches'`. Numbers via `Intl.NumberFormat` keyed off active locale. Time zones: store all timestamps in UTC, render in user's preferred TZ (`users.timezone`, default `UTC`).

## CON-i18n-url-strategy
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content: Round 1: no locale prefix (`/clans/{slug}`). Single-language URL, language picked by user setting/cookie. Move to `/{locale}/clans/{slug}` later without breaking deep links — current URLs become canonical for `en`.

## CON-i18n-seo
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content: `<html lang>` set to active locale. `hreflang` injected when alternate translations exist. Sitemap (`spatie/laravel-sitemap`) emits one entry per locale per translatable URL once a second language is added.

## CON-i18n-add-locale-procedure
- source: /home/rtx/projects/trench-wars/.docs/10-i18n.md
- type: protocol
- content:
  1. Add code to `config/i18n.php` `available_locales`.
  2. Copy `lang/en/*.php` → `lang/{code}/*.php`, translate.
  3. Run `php artisan content:locale:backfill {code}` to add empty keys to translatable columns.
  4. Translate user content via Filament (per-locale tabs).
  5. Switch homepage language switcher to include the new code.
  No code changes beyond config in step 1.

---

## CON-tournament-formats
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol (round-1 supported tournament formats)
- content:
  1. Round-robin — every participant plays every other once (or N times).
  2. Single elimination — bracket collapses to a final.
  3. Double elimination — winners + losers brackets meet at grand final.
  4. Swiss — `R` rounds, opponents matched by current standings, no eliminations.

## CON-tournament-lifecycle
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol
- content:
  1. Create tournament in Filament (game, format hint, dates, visibility).
  2. Register participants (Clans). Seeds optional. Seeding via Filament drag handle.
  3. Create stages — for each, choose format and settings (best-of, tiebreaker rules, swiss round count).
  4. Generate brackets — service class per format takes participants → produces `tournament_rounds` + `tournament_brackets`.
  5. Materialise matches — when round is ready (both participants known), Match created, slots materialised from chosen `GameMatchType`, signups open.
  6. Play & record — same as standalone matches.
  7. Advance — when match finishes, bracket records `winner_participant_id`. Next bracket pulls participants via `advances_to_bracket_id`.

## CON-tournament-bracket-generation
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol
- content:
  - Round-robin: standard circle method (Berger tables) producing `R` rounds for `N` participants (`R = N − 1` if even, `N` if odd with bye). Output: `R` rounds × `N/2` brackets each.
  - Single elimination: pad participants to next power of 2 with byes. Round 1 has `N/2` matches; subsequent rounds have half as many. `advances_to_bracket_id` chains winners forward.
  - Double elimination: winners bracket as single elim. Losers bracket fed from each round of winners. Grand final with optional reset match if losers winner beats winners winner.
  - Swiss: `R` rounds (configurable, typically `ceil(log2(N))`). Pairings each round computed by current points, no rematches, color/side balance where applicable. Final standings by points → tiebreakers (Buchholz, head-to-head).

## CON-tournament-public-view
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: schema
- content: Inertia page at `/tournaments/{slug}`. Tabs: Overview · Bracket · Schedule · Standings · Participants. Live updates poll for status (`router.reload({ only: ['bracket'] })` every 30s during live rounds). WebSockets considered for v2.

## CON-tournament-admin-tools
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol
- content:
  - Reseed: regenerate brackets from current participants if no matches played in stage.
  - Manual override: set bracket winner directly when no Match was played.
  - Forfeit: mark participant as forfeit; advances opponent.
  - Withdraw participant: marks all pending matches as forfeit.
  - Recalculate standings: re-runs tiebreaker logic (round-robin, swiss).

## CON-tournament-edge-cases
- source: /home/rtx/projects/trench-wars/.docs/11-tournaments.md
- type: protocol
- content: Odd participant count in round-robin → byes. Withdrawal mid-tournament → all pending matches credited to opponents; standings recalculated. Time conflict → admin reschedules; bookings prevent server overlap. Two stages with different match types supported (e.g. group stage 6v6, finals 50v50).

---

## CON-cms-articles
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: schema
- content: Filament-managed articles. Public site reads server-rendered HTML. Fields: `slug` (auto-generated, editable), `title` (translatable), `excerpt` (translatable, ~280 char limit), `body` (translatable, rich text via Tiptap), `hero_image` (via medialibrary), `category_id` (single category), `published_at` (live when set in past), `scheduled_for` (scheduler publishes at that time), `author_user_id` (derived from creator, editable by admins).

## CON-cms-publishing-flow
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: Draft → Scheduled → Published. Editor sets `scheduled_for` → status `Scheduled`. Laravel scheduler runs every minute, finds rows where `scheduled_for ≤ now()` and `published_at is null`, publishes them (sets `published_at = scheduled_for`, clears `scheduled_for`). Optional Discord announce on publish (per-article configurable).

## CON-cms-permissions
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: `cms-editor` and above: create/edit drafts and publish. `moderator`: read drafts but not publish.

## CON-cms-events
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: schema
- content: Public-facing scheduled occurrences. Fields: `slug`, `title` (translatable), `description` (translatable), `starts_at`, `ends_at`, `location` (free text or "online"), `kind ∈ {match | tournament | league | external}`, `linkable_type/id` (polymorphic — when `kind = match` links `matches.id`, when `kind = tournament` links `tournaments.id`).

## CON-cms-event-surfaces
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: Calendar page `/events` with month/week/day views. Home page highlights upcoming events. Auto-generated events for matches and tournaments: when a Match is created with `visibility = public`, an `Event` is auto-created and kept in sync. When Match is rescheduled, Event follows. When deleted, Event is removed.

## CON-cms-media-library
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: `spatie/laravel-medialibrary`. Disks: `public` (clan logos, hero images, article images, CDN in front in production) and `local` (encrypted private uploads — none round 1). Image variants generated on upload: `thumb` (256), `card` (640), `hero` (1600). WebP encoded.

## CON-cms-search
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: Round 1 minimal: full-text on Postgres `to_tsvector` for `articles.body`, `clans.name + description`, `players.display_name`. Public search bar on header. Results page `/search?q=…`. Meilisearch considered, out of scope round 1.

## CON-cms-comments-out-of-scope
- source: /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
- type: protocol
- content: Comments / reactions out of scope round 1. Discussion happens in Discord. Link-to-Discord-thread on each article considered for v2.

---

## CON-api-surfaces
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content: Two surfaces — Public REST API at `/api/v1/*` (Sanctum bearer auth, primarily for bot, designed reusable) and Internal API at `/api/internal/*` (`rcon-worker` only, HMAC signed). All payloads JSON. UTC timestamps in ISO 8601. IDs are UUIDs.

## CON-api-conventions
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - Resource paths plural noun: `/api/v1/clans`, `/api/v1/matches`.
  - Pagination: cursor-based for feeds, page-based for admin lists. `?cursor=…&per_page=20` returns `{ data, next_cursor }`.
  - Filtering: flat query params (`?game=hll&status=scheduled&clan_tag=eu`).
  - Errors: Laravel-default JSON: `{ message, errors: { field: ["..."] } }` for 422; `{ message }` for 4xx/5xx.
  - Idempotency: write endpoints accept `Idempotency-Key` header; deduplicated for 24h.
  - Rate limits: 60 req/min per Sanctum token by default; bot token raised to 600/min.
  - Versioning: URL prefix `v1`. Breaking changes bump to `v2` and run side-by-side until bot updated.

## CON-api-auth
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content: User: session cookie (web). API calls go through Inertia, not REST. Bot: Sanctum bearer token, scope `bot:*`. Internal (RCON): HMAC SHA-256 over `(timestamp + body)`, header `X-Rcon-Signature`, 60s freshness.

## CON-api-public-clans
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - GET `/api/v1/clans` — list, filterable by `tag`, `status`, `q`.
  - GET `/api/v1/clans/{slug}` — detail with members, tags.
  - POST `/api/v1/clans/{slug}/applications` — apply to join (bot/web).
  - GET `/api/v1/clans/{slug}/members` — roster, privacy-filtered.

## CON-api-public-players
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - GET `/api/v1/players/me` — self, full data.
  - GET `/api/v1/players/{slug}` — public profile, privacy-filtered.
  - GET `/api/v1/players/discord/{discord_id}` — bot lookup helper.
  - PATCH `/api/v1/players/me` — update display_name, avatar_source, bio.
  - PATCH `/api/v1/players/me/privacy` — update privacy flags.

## CON-api-public-matches
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - GET `/api/v1/matches` — filters: `upcoming`, `clan`, `tag`, `game`, `status`.
  - GET `/api/v1/matches/{id}` — detail with slots, signups, result.
  - POST `/api/v1/matches` — create (officer/leader of host clan).
  - PATCH `/api/v1/matches/{id}` — update (organiser).
  - DELETE `/api/v1/matches/{id}` — cancel (organiser/admin).
  - POST `/api/v1/matches/{id}/signups` — body `{ slot_id }`.
  - DELETE `/api/v1/matches/{id}/signups/me` — withdraw.
  - POST `/api/v1/matches/{id}/result` — manual result entry (organiser/admin).

## CON-api-public-tournaments
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - GET `/api/v1/tournaments` — list.
  - GET `/api/v1/tournaments/{slug}` — detail with stages.
  - GET `/api/v1/tournaments/{slug}/bracket` — full bracket data.
  - GET `/api/v1/tournaments/{slug}/standings` — computed standings.

## CON-api-public-cms
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - GET `/api/v1/articles` — paginated, filter by category.
  - GET `/api/v1/articles/{slug}` — detail.
  - GET `/api/v1/events` — calendar feed, `?from=&to=`.

## CON-api-internal-endpoints
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: api-contract
- content:
  - POST `/api/internal/match/{id}/events` — body `{ events: [{ type, payload, occurred_at }] }`.
  - POST `/api/internal/match/{id}/session-status` — body `{ status, error? }`.
  HMAC `X-Rcon-Signature`.

## CON-api-dtos
- source: /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- type: protocol
- content: DTOs authored once with `spatie/laravel-data` per resource (e.g. `ClanData` with id/slug/tag/name/description/status/tags/member_count/owner_id). Custom `php artisan typescript:generate` emits matching `.d.ts` for `apps/web` frontend and `packages/shared-types` for bot/rcon-worker.
