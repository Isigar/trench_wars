# Phase 01 Deferred Items

Out-of-scope discoveries logged during execution. NOT auto-fixed (per executor SCOPE BOUNDARY rule). Listed for the planner/verifier of a later plan to decide on.

## .docs/ untracked

- **Discovered during:** Plan 01-04 task 1 git-status survey.
- **State:** `.docs/` directory exists on disk with 17 reference markdown files (architecture, schema, decisions, etc.) but is untracked in git.
- **Why out of scope:** Pre-existing condition before plan 01-01; not a result of the current task's changes.
- **Suggested resolution:** A docs-only commit at phase-end OR explicit mention in 01-VALIDATION.md / phase summary. Plans 01-08, 01-12, 01-15, 01-16 reference `.docs/` but do not create it. The repo apparently received `.docs/` content as an out-of-band intel ingest before plan execution started.

## rcon-worker docker image build failure

- **Discovered during:** Plan 01-07 startup `docker compose up -d` (no service filter).
- **State:** Building image `trenchwars-rcon-worker` fails at:
  ```
  RUN pnpm install --frozen-lockfile=false --filter @trenchwars/rcon-worker... \
   && cd apps/rcon-worker && pnpm build
  ```
  with exit code 2 inside the Dockerfile's RUN step. Affects only the `rcon-worker` service. `web`, `web-nginx`, `postgres`, `redis` build/start fine.
- **Why out of scope:** Plan 01-07 only touches the public Inertia/Vue/Tailwind pipeline; the rcon-worker image is a Phase 8 implementation detail (D-005, plan-08+ in the inventory). `apps/rcon-worker` package source likely doesn't yet have the files the Dockerfile expects.
- **Worked around by:** `docker compose up -d web postgres redis` (filtered service list) — sufficient for plan 01-07's verification (Pest + pnpm build inside web).
- **Suggested resolution:** Plan 01-08 onward, OR a small docker-compose-fix plan that either (a) marks the rcon-worker service as profile=phase-8 (so it doesn't auto-start), (b) makes its Dockerfile build conditional on `apps/rcon-worker/dist` existing, or (c) introduces a placeholder index.ts in apps/rcon-worker so `pnpm build` succeeds. Phase 8 is the natural owner of the working image.
