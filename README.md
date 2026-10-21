# Laravel + React Starter Kit

## Introduction

Our React starter kit provides a robust, modern starting point for building Laravel applications with a React frontend using [Inertia](https://inertiajs.com).

Inertia allows you to build modern, single-page React applications using classic server-side routing and controllers. This lets you enjoy the frontend power of React combined with the incredible backend productivity of Laravel and lightning-fast Vite compilation.

This React starter kit utilizes React 19, TypeScript, Tailwind, and the [shadcn/ui](https://ui.shadcn.com) and [radix-ui](https://www.radix-ui.com) component libraries.

## Install (beam tier)

`composer setup` runs the beam tier installer — **`splicewire:beam:install`** — instead of a bare
`migrate`. The installer walks the `BeamInstallManifest` core-first (each beam-* package's publish tags),
then migrates once, so the beam stack lands in one command:

```bash
composer setup
# or, standalone, after key:generate:
php artisan splicewire:beam:install --no-interaction
#   no --force: it means overwrite-published-files and would republish config/beam/accounts.php over
#   this starter's committed tier gate. A production migrate/seed is waived only by --allow-production-migrate.
```

**The site self-documents on first boot.** `composer setup` leaves `/docs`, `/docs/api` and
`/docs/mcp` live with no wiring step of your own. The API reference is generated from **this host's
own routes** — not a copy of an upstream spec — so it describes what your deployment actually serves,
and it re-generates at install and deploy (never on request). The pages are `BeamUxEntry` rows this
site owns: edit them, move them, or re-root the whole subtree by changing the `/docs` row's `segment`.
Nothing re-asserts them, and deleting them is a supported way to opt out.

The host mounts `Route::beamUxSite()` last in `routes/web.php`, because it is a catch-all.
`resources/js/app.tsx` configures the packaged entry page from `@splicewire/beam-ux/pages` with
the site's link component and `SiteLayout` wrapper. Its resolver checks host pages first, then the
package's page map; add `resources/js/pages/site/entry.tsx` only when you want to replace that page.
An installed Beam package contributes a docs page by seeding a row and, where needed, mounting the
JSON endpoint its body renders.

`composer setup` runs `splicewire:beam:ux:compile` after `pnpm install`, because entry bodies compile
with **the host's** toolchain (`@mdx-js/mdx`) and there is deliberately no in-browser compile fallback.
Run it again after editing a body outside the app; `splicewire:beam:doctor` reports anything stale.

**Prototyping ships in the bare install.** The starter requires
`splicewire/laravel-beam-ux-prototype` and uses its existing Inertia integration. With
`APP_ENV=local` and `npm run dev` running, open `/_prototype` for the gallery or
`/_prototype/starter` for the starter example. The package registers the local-only Laravel route;
`resources/js/pages/_prototype.tsx` hosts its prototype router without the application layouts.

Author throwaway prototypes in `resources/js/_prototype/**`, importing shipped components read-only
over colocated fixtures. `_chrome/nav.ts` contains host-owned navigation data. The package installer
publishes the scaffold and binds its convention to the host; `config/beam/ux-prototype.php` points it
at this starter's source tree, stylesheet, package manifest, and brand component.

The Inertia resolver excludes the prototype host page from its production glob and imports it only
under `import.meta.env.DEV`. The page also guards the discovery glob. Run the packaged build check:

```bash
npm run beam:verify-prototype-boundary
```

This builds the application and scans `public/build` (the `prototype.outDir` in `package.json`) for
prototype module and route tokens. The package's static wiring audit is available through
`php artisan splicewire:beam:ux:prototype:doctor`; add `--boundary` to run the build check too.

## Exposing a model in the admin — drop one annotated Data class

An existing model with its table and authorization policy shows up in the Frame admin by dropping
**one** `#[ParticleResource]`-annotated Data class into `app/Data/`. That is the whole declaration — no second declaration, no `registerClass()` in a provider,
no handler-map entry. Beam's boot-time attributed discovery scans `app/Data` (config
`frame.discover_paths`), reflects the attribute into the admin manifest, and the resource appears at
`/frame/manifest` with its editor route wired.

The unified `#[ParticleResource]` carries the full manifest field set on the Data class itself — label,
model, nav placement (`group` / `section` / `navOrder` / `icon`), and route identity (`routeName`):

```php
#[ParticleResource(
    key: 'sitemap',
    backing: SitemapRecord::class,
    label: 'Sitemap',           // a non-empty label marks the resource FRAMED (navigable + editable)
    group: 'Site',
    section: 'links',
    navOrder: 99,
)]
class SitemapData extends Data { /* … Column-annotated fields … */ }
```

See `app/Data/SitemapData.php` for the editable sitemap declaration. This registers the resource in
Frame; a REST API is an explicit host decision using `Particle::mount()` inside the chosen middleware.
The attribute does not create the model, table or policy and does not mount that REST exposure.

For the historical private-request API/UI checkpoint, follow [Service requests](docs/examples/service-request.md):
a model/table/policy, separate read and input Data, authenticated create/list/show, generated routes
and response types, a cookie-free TypeScript client, and schema-driven create/list/read-only-detail
with automatic list return on a fresh localhost fixture.

Follow the [local team creation and invitation guide](docs/examples/service-desk-team.md)
for the historical account onboarding checkpoint. Continue with the current
[shared team queue](docs/examples/service-desk-team-requests.md), including live membership
authority, creator attribution, outsider denial and migration of the application contract.
Then inspect [existing Theme entitlements](docs/examples/service-desk-entitlements.md) with
a guarded local grant/revoke fixture and the separate role/feature boundaries.

## Checks

`composer types:generate` derives TypeScript modules from the app's Data classes/enums and
referenced Frame registry types. Edit the PHP declarations, then regenerate. The DTO modules
are gitignored under `resources/js/generated/App/` and `resources/js/generated/Schemastud/`.
The committed `routes.ts` and `aliases.ts` in the same parent directory belong to the Beam
client generator and are not replaced by this command.

The `dev`, `build`, `build:ssr`, and `types:check` scripts regenerate before consuming these
modules, so `composer setup` covers generation too. After changing Data during a running dev
session, rerun `composer types:generate`.

`pnpm types:check` checks the application and generated contracts, including negative type
cases with library checking enabled. Production builds run this check before Vite. Profile,
security, and auth consumers import their page types from the generated modules.

`composer test` runs **the PHP suite and nothing else** — it no longer chains pint and phpstan, which
stay reachable as `composer lint:check` and `composer types:check`. The composite is
`composer ci:check`, which delegates to `bin/ci-check` and runs **every** gate (suite first, then
pint, phpstan, eslint, prettier, tsc) so no gate can mask another. It exits `0` all-passed, `1` a
real failure, and `2` when a gate could not be measured at all — test `!= 0`, never `== 1`.

PHPStan runs as two processes. `phpstan.neon` (`composer types:check`, the `php:types` gate) analyses
the application only. `phpstan.migration-stubs.neon` analyses the beam-accounts migration stubs before
they are published; `tests/Feature/MigrationStubAnalysisTest.php` runs it with a private cache, so a
defect in a stub fails `php:test`, not `php:types`. Do not put the stubs back in `phpstan.neon`: a stub
and its byte-identical published copy analysed in one process share one AST, and PHPStan then reports
correct PHPDocs as missing. To run the stub analysis by hand, give it a fresh cache:
`TMPDIR="$(mktemp -d)" vendor/bin/phpstan analyse -c phpstan.migration-stubs.neon`.

## Co-dev overlay (local package dev)

To develop against your local package checkouts in `~/Workspaces/php/packages/**` (symlinked into
`vendor/` — path repos win over the git `repositories` via `wikimedia/composer-merge-plugin`):

```bash
cp composer.local.json.dist composer.local.json  # engage the overlay (gitignored active copy)
composer update                                   # resolve to local checkouts
php artisan splicewire:beam:install --no-interaction

rm composer.local.json && composer update         # reset to git-resolved
```

Never commit a `composer.lock` resolved through the overlay. Family packages are required at
`dev-main`, so the committed lock must be regenerated with no `composer.local.json` present, after the
package commits it depends on are pushed: `grep -c '"type": "path"' composer.lock` must print `0`.

Family packages (`@splicewire/*`, `@schemastud/*`) resolve from npm at
exact versions, in `dependencies` and, where a transitive range must be pinned, `pnpm.overrides`. A
`link:` specifier into `~/Workspaces/js/packages/**` is local co-dev only: never commit it or the
`pnpm-lock.yaml` it writes, because a runner has no `~/Workspaces/js` and `pnpm install` fails there.
`vite.config.ts` dedupes React and friends for that linked case. To ship a JS package change, publish
the package, bump the exact version here, and regenerate the lock: `grep -c 'link:' pnpm-lock.yaml`
must print `0`.

The JS twin is `scripts/js-overlay`. It links your local family checkouts through `package.json` itself,
because pnpm 9 reads `pnpm.overrides` only from there:

```bash
cp js.local.json.dist js.local.json  # gitignored active copy; edit the paths for this machine
scripts/js-overlay on                # link:, pnpm install, hide package.json and the lock from git
scripts/js-overlay status
scripts/js-overlay off               # restore both as committed, pnpm install --frozen-lockfile
```

`js.local.json.dist` links the family packages the replay links today. To link another, add a `dep:` line (a
direct dependency) or an `override:` line (a `pnpm.overrides` pin) to your `js.local.json`.

Run `off` before a pull, checkout or rebase that touches `package.json` or `pnpm-lock.yaml`, then `on` again.
While it is on, a real dependency change is hidden too, so `off` (and a repeat `on`) refuses when either file
differs from what `on` left beyond its own `link:` entries, and names what differs: redo that change after
`off`. If only the lock moved because a linked package's own dependencies changed, choose explicitly:
`on --accept-lock` or `off --discard-lock`. The hiding is `git update-index --skip-worktree`, which git's FAQ
says is not meant for this; that caveat is accepted knowingly, with the guard.

## Official Documentation

Documentation for all Laravel starter kits can be found on the [Laravel website](https://laravel.com/docs/starter-kits).

## Contributing

Thank you for considering contributing to our starter kit! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

All contributions to the Starter Kits from now on should be made through [Maestro](https://github.com/laravel/maestro).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## License

The Laravel + React starter kit is open-sourced software licensed under the MIT license.
