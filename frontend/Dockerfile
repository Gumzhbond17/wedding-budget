# syntax=docker/dockerfile:1.7
#
# Builds the Nuxt SPA to static files and serves them with nginx, which also proxies the API
# to the Laravel container (one origin). Built by docker-compose.yml, which provides the
# "nginx-config" build context (../docker/nginx). Stand-alone:
#   docker build --build-context nginx-config=../docker/nginx -t wedding-budget-web .

ARG NODE_VERSION=24

# --- build: static output of nuxt generate ---------------------------------------------
FROM node:${NODE_VERSION}-alpine AS build

WORKDIR /app
ENV NUXT_TELEMETRY_DISABLED=1 \
    COREPACK_ENABLE_DOWNLOAD_PROMPT=0
# pnpm at the exact version of package.json's "packageManager".
RUN corepack enable

# Fetch packages from the lockfile alone, so this layer survives source changes.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN --mount=type=cache,id=pnpm-store,target=/root/.local/share/pnpm/store \
    pnpm fetch --frozen-lockfile

COPY . .
RUN --mount=type=cache,id=pnpm-store,target=/root/.local/share/pnpm/store \
    pnpm install --frozen-lockfile --offline

# Empty = same origin: the browser calls /api/... on the host that served the page.
ARG NUXT_PUBLIC_API_BASE=""
ENV NUXT_PUBLIC_API_BASE=${NUXT_PUBLIC_API_BASE}
RUN pnpm generate

# --- web: nginx with the static files only (no Node at runtime) ------------------------
FROM nginxinc/nginx-unprivileged:1.28-alpine AS web

COPY --from=nginx-config default.conf /etc/nginx/conf.d/default.conf
COPY --from=nginx-config security-headers.conf laravel.conf /etc/nginx/snippets/
COPY --from=build /app/.output/public /usr/share/nginx/html

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1
