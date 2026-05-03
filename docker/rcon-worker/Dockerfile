# Source: 01-RESEARCH.md Recommended Project Structure — apps/rcon-worker Dockerfile node:22-alpine
FROM node:22-alpine

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /app

COPY pnpm-workspace.yaml package.json tsconfig.base.json ./
COPY packages/shared-types ./packages/shared-types
COPY apps/rcon-worker ./apps/rcon-worker

RUN pnpm install --frozen-lockfile=false --filter @trenchwars/rcon-worker... \
 && cd apps/rcon-worker && pnpm build

WORKDIR /app/apps/rcon-worker
CMD ["node", "dist/index.js"]
