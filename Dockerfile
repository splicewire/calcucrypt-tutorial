# The standard beam/satellite-tier deploy: builds the container image every site deploys to Cloud
# Run via .github/workflows/deploy.yml + splicewire:beam:provision (splicewire/laravel-beam-provision).
# Proven live against beam-pilot-gcp-cloud-run (gcp-cloud-run-provisioning map, tickets 10 + the CI
# follow-on); baked into laravel-beam-starter and laravel-satellite-starter identically.
#
# The builder needs PHP and Node: pnpm build generates TypeScript from the Laravel Data
# declarations, checks the generated contracts and frontend, then runs Vite.

FROM php:8.4-cli-bookworm AS builder
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev ca-certificates curl gnupg \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath exif \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN corepack enable

WORKDIR /app
COPY composer.json composer.lock ./
# `gh_app_token` is a build secret (never baked into an image layer), passed only when a host has
# private packages to resolve — splicewire/laravel-beam-docs at every tier, plus satellite-tier's
# own family — using splicewire-market's App reused for our internal CI (see
# .github/workflows/deploy.yml). `required=false` + the `-s` check make this a no-op for a build
# that does not pass it, which then runs an ordinary anonymous `composer install`.
RUN --mount=type=secret,id=gh_app_token,required=false \
    if [ -s /run/secrets/gh_app_token ]; then \
        git config --global url."https://x-access-token:$(cat /run/secrets/gh_app_token)@github.com/".insteadOf "https://github.com/"; \
    fi \
    && composer install --no-dev --no-scripts --no-interaction --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Generation boots Laravel without requiring an APP_KEY or a database connection. Vite
# reads this throwaway .env so VITE_APP_NAME is compiled into the bundle. Remove it after
# building; runtime values are injected by the deployment environment.
RUN cp .env.example .env \
    && pnpm install --no-frozen-lockfile --ignore-scripts \
    && pnpm run build \
    && rm .env

FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath exif \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=builder /app /var/www/html
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod -R a+rwX storage bootstrap/cache

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
# Laravel's own docs document a bare socket-directory `host` as PDO pgsql's way to address a Unix
# socket — Cloud Run's Auth Proxy volume mount exposes it at /cloudsql/<connection_name> (ticket
# 01's finding). Real value is exported by entrypoint.sh from DB_CREDENTIALS_JSON at boot.
ENV DB_CONNECTION=pgsql

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
