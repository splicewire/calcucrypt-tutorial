#!/bin/sh
set -e

# tenant-database's secret is one JSON blob (ticket 07's deliberate choice — never a plaintext
# per-field output). This is where that blob actually becomes the DB_* env vars Laravel's stock
# pgsql connection reads — a container-boot detail, not a redesign of the secret's shape.
if [ -n "$DB_CREDENTIALS_JSON" ]; then
  eval "$(php -r '
    $c = json_decode(getenv("DB_CREDENTIALS_JSON"), true);
    foreach ([
        "DB_HOST" => "/cloudsql/" . $c["connection_name"],
        "DB_PORT" => "5432",
        "DB_DATABASE" => $c["database"],
        "DB_USERNAME" => $c["username"],
        "DB_PASSWORD" => $c["password"],
    ] as $k => $v) {
        printf("export %s=%s\n", $k, escapeshellarg($v));
    }
  ')"
fi

php artisan migrate --force --no-interaction

# The OpenAPI artifact is generated HERE, in the deployed environment, and never committed.
# Three reasons it cannot happen anywhere earlier:
#   - `storage/app/private/.gitignore` is `*`, so the artifact is never in the repo and never lands
#     in the image via `COPY --from=builder`;
#   - the builder stage runs `composer install --no-scripts` and (since beam-runbook ADR-0004)
#     deliberately boots no artisan, so `composer setup`'s `splicewire:beam:install` — which DOES
#     generate the spec, at BeamInstallCommand.php:180 — never runs there;
#   - the spec is host-specific: `servers[].url` is this deployment's APP_URL, so an artifact baked
#     at build time would advertise the wrong origin.
# Each Cloud Run instance therefore generates its own copy into ephemeral storage, which is correct
# rather than wasteful. Without this, `beam/openapi.{yaml,json}` 404 and the seeded `/docs/api` entry
# renders an <ApiReference> pointing at nothing — a 200 over a missing spec.
#
# NOT under `set -e`: docs are secondary to serving traffic. A generator failure must degrade to a
# 404 on the spec, never a container that will not boot.
if ! php artisan scribe:generate --no-interaction; then
  echo "entrypoint: scribe:generate failed — beam/openapi.{yaml,json} will 404 this instance" >&2
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
