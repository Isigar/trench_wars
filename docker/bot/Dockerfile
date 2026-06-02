# Source: 01-RESEARCH.md Recommended Project Structure — apps/bot Dockerfile node:22-alpine
FROM node:22-alpine

RUN corepack enable && corepack prepare pnpm@9.15.0 --activate

WORKDIR /app

# Copy workspace files for `pnpm install --filter` to work
COPY pnpm-workspace.yaml package.json pnpm-lock.yaml tsconfig.base.json ./
COPY packages/shared-types ./packages/shared-types
COPY apps/bot ./apps/bot

# Build shared-types BEFORE the bot (the bot's tsc imports @trenchwars/shared-types,
# whose dist/ is gitignored) — the `...` selector builds workspace deps topologically.
RUN pnpm install --frozen-lockfile=false --filter @trenchwars/bot... \
 && pnpm --filter @trenchwars/bot... build

WORKDIR /app/apps/bot
CMD ["node", "dist/index.js"]
