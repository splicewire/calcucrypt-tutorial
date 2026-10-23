# Distribute the local service desk SLA extension

This opt-in example connects the existing Market creator workspace, human review and Extensions area to the service desk. It uses a local Git repository and explicit terminal installs. It does not publish to GitHub or a package registry, take payment or install code from a web button.

The example is disabled unless `SERVICE_DESK_MARKETPLACE_ENABLED=true`. Its optional page source is `examples/local-marketplace/page.tsx`; `node scripts/prepare-local-marketplace.mjs` copies those exact bytes into the ignored Inertia page location. Ordinary starter builds do not import the optional Market JavaScript package.

## Isolated dependency prerequisite

Use a new private checkout, SQLite database, environment and generated output. Keep shipping `composer.json`, `composer.lock`, `package.json` and `pnpm-lock.yaml` unchanged. The alternate context below binds the accepted 225-package starter lock. Another lock needs its own measured dependency delta.

The selected disposable composition adds29 packages, downgrades Activitylog5→4, Scout11→10 and Guzzle8/Promises3/PSR7implementation3→7/2/2, and patches CommonMark2.10.0→2.10.4 and HttpFoundation8.1.6→8.1.9. It uses Lunar2.0.0-alpha.6, an alpha catalog engine. These changes are not selected for a live site or the shipping lock.

There is an additional **local prerequisite patch**: upstream Lunar4447e91ab35969ba3707d15ff32bdfbb340736d3 hardcodes stock Spatie role/permission models in its post-migration staff state and manifest. That fails against this starter's configured UUID models. The isolated patch1adeac5a3f404452cabf85095fc839afd713f6d8 uses Spatie PermissionRegistrar's configured models in those two files. It is installed from a local VCS branch with an explicit2.0.0-alpha.6 compatibility alias. It is not an upstream release or an approved live fork. Keep the patch and its integer/UUID regression tests visible; a future live maintenance decision is separate.

Prepare clean source checkouts at these exact identities:

| Input | Ref |
| --- | --- |
| splicewire/laravel-beam-market |09beb6843dedc3cef49b710308c4efa19afb7565 |
| splicewire/laravel-beam-licenser |8336a345f20d7f8338fb1c042d08e71b3c67baa3 |
| rushing/laravel-commerce |aef4bfbdce492bbcc91c3027a46bdeb1042a4a77 |
| patched lunarphp/core |1adeac5a3f404452cabf85095fc839afd713f6d8 |
| SLA initial artifact |c96859b9a00223f0a17da95764f7704d158aef9c |
| SLA metadata-only update |271bb0f5927fc1e2b8bc0f8a772a45579ea57b60 |

The preparation script validates source identities and tracked cleanliness, pins every original package version/source and writes only the alternate manifest and copied lock. Use named local branches for those VCS checkouts:

```sh
python3 examples/local-marketplace/prepare-composer.py \
  --market-source "$MARKET_SOURCE" --licenser-source "$LICENSER_SOURCE" \
  --commerce-source "$COMMERCE_SOURCE" --lunar-source "$PATCHED_LUNAR_SOURCE"

COMPOSER=composer.market.json XDEBUG_MODE=off herd composer update \
  splicewire/laravel-beam-market splicewire/laravel-beam-licenser rushing/laravel-commerce \
  lunarphp/core spatie/laravel-activitylog laravel/scout \
  guzzlehttp/guzzle guzzlehttp/promises guzzlehttp/psr7 \
  league/commonmark symfony/http-foundation \
  --minimal-changes --dry-run --no-scripts --no-interaction
```

Inspect the delta before removing `--dry-run`. The expected initial solver delta remains29 additions/seven changed original packages/zero removals; Lunar is the explicitly disclosed patched source/alias rather than the registry source. Install with scripts disabled, compare actual installed metadata and run an **installed** audit before discovery or any application boot:

```sh
COMPOSER=composer.market.json XDEBUG_MODE=off herd composer audit --format=json
```

A successful solver, skipped audit, old audit or version label alone does not establish this prerequisite.

## Private local host

Create a new SQLite file at `database/service-desk.sqlite`, a new application key and private environment. Use `APP_ENV=local`, a loopback HTTP URL with its own port, SQLite, file sessions, sync queue, array mail and `SCOUT_DRIVER=collection` with a unique `SCOUT_PREFIX`. Keep application/licensing keys and actor credentials out of logs and public directories.

Enable only this example. Keep the licenser self-hosted client gate off with `BEAM_LICENSER_ENABLED=false`; this does not replace the real listing LicenseIssuer used by review. Set `BEAM_TENANCY_SEED_DEMO_TENANT=false`. Use an owned nonproduction `BEAM_SCRATCH_STORAGE_ROOT` for generated bodies.

The artifact source must be a **standalone fresh clone with a .git directory**, not a linked worktree. Tag `v0.1.0` at exactc968 in that disposable clone. Configure:

```dotenv
SERVICE_DESK_MARKETPLACE_ENABLED=true
BEAM_MARKET_ARTIFACT_SOURCE='Splicewire\Beam\Market\Artifacts\Support\LocalGitExtensionArtifactSource'
BEAM_MARKET_LOCAL_REPOS='splicewire/service-desk-sla=/absolute/owned/artifact-clone'
BEAM_MARKET_SIMULATED_REPO_CALLBACK=true
BEAM_LICENSER_ENABLED=false
```

The provider association is explicitly **simulated**, default-off outside this fixture and labelled on the page. It does not certify GitHub access. The repository allowlist and Git object/tag bytes, not the working directory's uncommitted files, determine inspection.

After the audit passes, use the native preparation order:

```sh
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan package:discover
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan popcorn:registries:cache
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction --no-seed
```

Omitting `--own-tables` uses the installer's supported noninteractive ownership default. The literal `--own-tables=all` is not a special selector. Native ownership re-dates published Beam permission/activity/tag migrations ahead of competing vendor migrations in this fresh fixture. Do not hand-create production schemas, suppress Lunar's listener or preseed staff rows to bypass the compatibility problem. Registry caching is a real prerequisite for the installer's completion generators.

The host supplies a fresh selected-team Seller and entitlement scope. Owner/Admin creator roles do not make a site operator. Only the explicit `marketplace.host-operator` permission admits site-wide acquire/update/remove actions; installed PHP code is shared by all teams. Review delegates to the existing Accounts central Root mechanism, separately from creator and operator authority.

Prepare the optional page and generate current declarations/routes:

```sh
node scripts/prepare-local-marketplace.mjs
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
```

Use the selected isolated JavaScript composition, not the old locked Inertia99 build: Beam54b5b096d3e2197fec5258deb31b763969477bb6, Schemastud5ac791e79ceb2be846b8f880f4da04979123f795 and generated resourcesf3ab73f4e5cbf4bc15415d262f961a231c217764. Build the owning beam-market and beam-inertia packages with their declared `npm run build`, resolve their actual built exports and exact peers into the owned host, then run its declared `pnpm run build`. The source refs are not compiled release refs. Do not run a package manager against shared linked sources, change shipping locks or treat the existence of dist as source/build proof. The final proof recipe records the exact isolated input, peer, generated type and output hashes.

Create tutorial actors only in the fresh private database:

```sh
COMPOSER=composer.market.json XDEBUG_MODE=off herd php scripts/local-marketplace-fixture.php \
  "$PWD/storage/app/private/local-marketplace.json"
```

The helper refuses existing users/teams and writes credentials privately. It creates actual team roles, a distinct central Root reviewer and one explicit site operator. It creates no listing, review, license, install request or deployment receipt.

## Journey and boundaries

Open `/local-marketplace` and use ordinary form login for each actor. The creator associates the allowlisted local repository, inspects `v0.1.0`, drafts the free Composer listing, releases it and submits it. Free Composer extensions still follow the ordinary automated/human review workflow; they are not free Scaffold exports. A reviewer approves or rejects through the actual review operations. Approval persists real listing licensing state. Free buyer acquisition separately bypasses paid buyer-entitlement issuance.

Acquire as the site operator while the SLA package is absent. Requested/instructed is not detected/installed. Perform the actual constrained local VCS terminal install in the same `COMPOSER=composer.market.json` context, run normal discovery/native install/assets/build and refresh the Installed area. Confirm physical package source/archive bytes independently from the displayed version. Configure SLA using its stock Frame screens and approve actual requests; inspect persisted evaluations and unchanged core request/fact/usage behavior.

For the update, create the local `v0.1.1` tag at exact271bb0f only when releasing the second artifact. Its composer description is the sole change; it is a metadata update, not new feature behavior. Follow the existing release and re-review rules. Requesting0.1.1 while0.1.0 remains deployed must keep the old observed/last-verified state until a successful real terminal update.

Remove first requests removal. The package remains deployed until the terminal removal, discovery/cache/assets and actual observation settle absence. Retain requests, workflow facts, SLA rules and evaluations. Reacquire/reinstall should restore the feature without backfilling old transitions. Disabled SLA and source/observer failures remain distinct from successful empty or met results. An after-commit observer cannot roll back an already committed approval.

The builder proof exercises this local lifecycle, real listing licensing, changed-dependency controls and removal/reinstall preservation. Independent comparison and acceptance are separate. This does not approve a shipping dependency downgrade, a live Lunar fork, paid commerce, remote publication or deployment.


The optional taxonomy ownership pivot is an explicit host prerequisite. The starter declines the full Accounts auth migration estate. Do not enable that estate for this example. `examples/local-marketplace/migrations/2026_10_23_000000_create_userables_table.php` is byte-identical to the selected installed Accounts `database/migrations/tenant/create_userables_table.php.stub`; it supplies the taxonomy HasUser pivot only. Its owning schema is tenant-resident in a tenancy host. This single-database local example does not prove tenant isolation. After the native stack install, apply this one path on the same private SQLite connection:

```bash
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan migrate --path=examples/local-marketplace/migrations --no-interaction
```

The selected Accounts source is `a18b2fefe67f6694bf2e69012a1deff47cf6b6a9`; the owning stub and copied migration SHA-256 are `ae61e92069a592b9649d463e00dc4742ac2d9da031891444232b5a489c0bec88`. Recheck both before running and retain the migration receipt. The broader auth estate, grants and seed behavior stay unchanged.


## Explicit package changes and checks

In the private alternate context, add a VCS repository restricted to `splicewire/service-desk-sla` and pointing at the allowlisted standalone artifact clone. Require exact `0.1.0` for the first deployment. Inspect this constrained dry run before executing without `--dry-run`:

```sh
COMPOSER=composer.market.json XDEBUG_MODE=off herd composer update splicewire/service-desk-sla --dry-run --no-scripts --no-interaction
```

First install must be one install, zero updates/removals; all previous 254 full lock records stay equal. Actual execution uses normal Composer hooks, which perform discovery and registry caching. Run the native installer with `--no-seed`, generate assets and build the host. Use the same alternate context for every command. Change only the SLA requirement to `0.1.1` for the metadata update: its dry run must be one update, and all other records remain equal. A failed attempt to require unavailable `0.1.99` must revert the alternate files and leave the old detected/last-verified deployment intact.

Removal and reinstall are explicit terminal operations after their respective UI requests:

```sh
COMPOSER=composer.market.json XDEBUG_MODE=off herd composer remove splicewire/service-desk-sla --no-interaction
# Perform normal cache/registry/assets preparation and the declared host build; then Check again.
COMPOSER=composer.market.json XDEBUG_MODE=off herd composer require splicewire/service-desk-sla:0.1.1 --no-interaction
COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction --no-seed
```

Do not roll migrations back. Preserve rules, evaluations, requests and immutable facts. Approving a request while the extension is absent still increments the read-only usage projection. Reinstall does not evaluate that older transition; a new approval is evaluated normally. The observer is best effort after commit: deliberately invalid request source can leave a committed approval/fact and no evaluation. It does not reverse the approval.

Focused checks in the alternate installed composition:

```sh
SERVICE_DESK_MARKETPLACE_ENABLED=false COMPOSER=composer.market.json XDEBUG_MODE=off herd php artisan test \
  tests/Feature/LocalMarketplaceAuthorityTest.php tests/Feature/LocalMarketplaceRoutesTest.php \
  tests/Feature/LocalMarketplaceDependenciesTest.php tests/Feature/ServiceRequestApprovalTest.php \
  tests/Feature/ApprovalUsageTest.php tests/Feature/ExternalRequestTest.php
```

The dependency tests use native owning migrations on an isolated in-memory connection. They exercise Lunar log old/new values, actor and batch identity, workflow actor/run grouping and rollback, plus Scout collection save/update/delete/search for taxonomy and Lunar models. CollectionEngine reads live rows and deliberately performs no external index IO; this does not certify Meilisearch. The separate local HTTP proof covers SourceClient success/empty/503/timeout/redirect refusal/auth-before-request and private-token non-disclosure, plus the actual Guzzle promise/PSR7 stream boundary. The marketplace catalog has category/kind filters; no new free-text search UI is promised.
