# Local licensed Composer delivery of Service-desk SLA

This continues [the sandbox paid SLA example](paid-marketplace-sla.md). It uses real listing review, buyer licenses, Composer protocol requests and ZIP extraction, with simulated payment/payout ports. The registry listens only on a disposable host's loopback address. It does not publish a registry, charge a card, create a hosted account, or authorize live adoption of the proof-only Lunar patch.

## Prepare the selected composition

Use a fresh owned host/database and the previous example's exact audited pre-SLA composition. The slice22 evidence preserves the actual261-record pre-SLA lock at Marketdf2. Its paired manifest is explicitly derived by removing only the SLA requirement/repository from the actual post-SLA manifest; its Composer content hash matches the retained lock. Preserve that distinction and record local VCS URL transport remaps separately.

Add the exact reviewed `splicewire/laravel-beam-market-packages` local VCS checkpoint, then select the paired Market checkpoint containing `ListingDistributionChanged`. Inspect a dry update: only the selected Registry addition and owning Market/Registry source advances are allowed; preserve all other full lock records. Install with `--no-scripts`, compare installed metadata and physical archive bytes, and run installed `composer audit` before discovery or application boot. The accepted local composition still includes the separately disclosed UUID-compatible Lunar patch; the stock registry Lunar source is not represented as installer-green.

Keep the host's original shipping Composer and pnpm manifests/locks unchanged; use `COMPOSER=composer.registry.json` for the alternate composition. Set `BEAM_SCRATCH_STORAGE_ROOT` to owned output and retain the previous guide's fresh SQLite, sandbox flags, native workflow prerequisites and fresh actors. Use the existing private registry disk configured by this host: `SERVICE_DESK_MARKETPLACE_REGISTRY_ROOT` points inside the run root, outside `public`, and the disk has `serve=false`. Bare registry seller-resource routes are disabled by `config/beam/market_packages.php`; host creator policy and routes remain authoritative.

After audit, run ordinary discovery, registry preparation and `splicewire:beam:install --no-interaction --no-seed`. Registry contributes exactly its three central migration stubs, in provider order: listings, releases, manifest. Use the optional Accounts userables example migration and six explicitly selected workflow paths from the paid example; do not publish the entire tenant/auth estate. A single SQLite fixture does not prove tenant isolation or cross-connection atomicity.

## Publish and acquire

Use a standalone owned Git artifact clone at SLA `v0.1.1`, allowlisted by `BEAM_MARKET_LOCAL_REPOS`. `BEAM_MARKET_ARTIFACT_SOURCE` is the actual `Splicewire\Beam\Market\Artifacts\Support\LocalGitExtensionArtifactSource` class. `BEAM_MARKET_SIMULATED_REPO_CALLBACK=true` enables only the explicitly labeled local provider simulation.

Create a paid listing through Your extensions, publish its actual tag, submit it, and approve as the real Root reviewer. Check publication/registry availability **before** a buyer event can mask ordering. Two independently signed-in buyers purchase with the fake payment port. Each receives a distinct existing license key, hidden until Show key. Do not print keys in commands, screenshots, logs, source, shared proof or shell history.

## Private Composer continuation

Use different disposable Composer homes and caches for A and B. These settings must be active even if generic UI instructions show `--global`; that flag must never address an owner home. Never execute a command containing the displayed key as an argument.

```sh
export COMPOSER=composer.registry.json
export COMPOSER_HOME="$RUN_ROOT/composer-home-A"
export COMPOSER_CACHE_DIR="$RUN_ROOT/composer-cache-A"
mkdir -m 700 "$COMPOSER_HOME" "$COMPOSER_CACHE_DIR"
python3 - <<'PY'
import getpass, json, os
from pathlib import Path
home = Path(os.environ['COMPOSER_HOME'])
origin = input('Exact loopback registry origin, including port: ')
assert origin.startswith('127.0.0.1:') and origin.split(':')[1].isdigit()
key = getpass.getpass('Buyer license key (not echoed): ')
p = home / 'auth.json'
fd = os.open(p, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w') as f:
    json.dump({'http-basic': {origin: {'username': 'composer', 'password': key}}}, f)
PY
```

In the alternate manifest add a canonical Composer repository at `http://127.0.0.1:<owned-port>/registry`, restricted with `"only": ["splicewire/service-desk-sla"]`. There must be **no SLA VCS/path/package fallback**. Loopback HTTP needs `config.secure-http=false` in this disposable composition; this is not a production HTTPS recipe. Select `splicewire/service-desk-sla:0.1.1`, prefer its `dist`, dry-update that package only, then run the normal Composer update with hooks. Expect one ZIP install and no changes to the other selected records. Bind installed22 files to the actual tagged archive and the lock's loopback ZIP URL; metadata or a path symlink alone is insufficient.

For a separate cold buyer download, use B's private home/cache and `composer create-project splicewire/service-desk-sla "$RUN_ROOT/buyer-B-cold" 0.1.1 --repository=http://127.0.0.1:<owned-port>/registry --no-install --no-scripts --no-interaction --prefer-dist`. This proves B's archive download/extraction, not integration of that standalone directory. The real host integration is A's dependency install.

Run native stack install without seeds, asset generation and the declared host typecheck/Vite build. Request installation through the entitled listing, then let actual deployment observation detect0.1.1. Create an enabled selected-team rule as Owner/Admin and approve a real request: inspect the committed fact, one usage unit and retained SLA evaluation. Buyer entitlement does not grant site-operator refresh/remove authority.

## Update, refuse and retain

Publish the documentation-only local `v0.1.2` release through the creator UI. Its runtime, migrations and dependencies match0.1.1. A site operator may request Update; until Composer actually changes code, the UI must show requested0.1.2/detected0.1.1 rather than claiming deployment. Select0.1.2 in the alternate manifest and update only SLA through the same authenticated registry. Verify both versions remain in metadata, the new ZIP matches the tag, and every other lock record and existing business row remains unchanged.

Test an unavailable0.1.99 constraint with a valid buyer credential. **Wait for the actual command exit before inspecting/restoring inputs or beginning another mutating journey.** Compare the final lock, installed files and business tables to the immediately preceding snapshot; then restore only the deliberately changed alternate manifest. The original overlapping/provisional builder observation is not final preservation evidence.

Root revokes A through Buyer licenses. A's newly authorized requests must fail; B's fresh cold requests must still succeed. Creator withdrawal must deny both buyers' new server reads/downloads, and later unrelated Product-license notifications must not reopen distribution. Previously installed code, request/fact/usage/SLA data, license/payment history and cached ZIPs remain. Test a fresh cache separately from a warmed cache and an explicit offline attempt; report actual results without promising cached copies can be revoked.

Shared distribution uses the current persisted Published Product and its associated usable, matching distribution license. Buyer issue/revoke events reconcile that authority instead of toggling a shared flag. A post-commit Market signal handles initial publication after UID association. Request-time checks deny a stale-true registry cache. An outer rollback suppresses publication signals; a failed post-commit listener cannot undo committed publication. Reconciliation timestamps describe observations, not first-transition history. Split-connection behavior and general retry/concurrency guarantees are unproved.

Stop verified owned listeners/proxies, record actual supported lease release/status, retain private inputs for independent comparison, and share only scrubbed evidence. The complete slice22 recipe binds exact candidates, native prerequisites, source/installed/build/served identities and original failures separately.
