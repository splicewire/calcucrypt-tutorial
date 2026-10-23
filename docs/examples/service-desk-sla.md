# Install a local approval SLA extension

This example adds one local Composer feature to the service desk. It observes approval timing after commit; it never blocks an approval or retries a failed observation. The host adapter in `app/Support/ServiceDeskSlaHost.php` supplies fresh selected-team membership, Owner/Admin authority and retained request data. The package has no dependency on App classes.

Use a fresh isolated checkout, private local `.env` and SQLite `database/service-desk.sqlite`. Set `APP_ENV=local`, a unique `http://127.0.0.1:<port>` URL, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array` and `CACHE_STORE=array`. Keep demo seeding off. Set `BEAM_SCRATCH_STORAGE_ROOT` to an owned absolute storage directory. Never point this example at an owner database.

Install this host's committed Composer and frozen JS dependencies first:

```sh
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
```

Choose the exact local extension source checkout and full commit in `SLA_SOURCE` and `SLA_REF`. In an owned alternate manifest, retain this host's original dependencies and lock, add a `vcs` repository pointing at `SLA_SOURCE`, and require `splicewire/service-desk-sla` as `dev-main#SLA_REF`. Keep the shipping manifest/lock intact. The proof uses `composer.sla.json` and `composer.sla.lock` with local-overlay merge includes disabled.

With `SLA_SOURCE` and `SLA_REF` exported, create those alternate files once from the committed inputs:

```sh
python3 - <<'PYTHON'
import json, os, pathlib, shutil
source = pathlib.Path(os.environ['SLA_SOURCE']).resolve()
ref = os.environ['SLA_REF']
assert (source / 'composer.json').is_file()
assert len(ref) == 40 and all(c in '0123456789abcdef' for c in ref)
assert not pathlib.Path('composer.sla.json').exists()
manifest = json.loads(pathlib.Path('composer.json').read_text())
manifest['repositories'] = [{'type': 'vcs', 'url': str(source)}] + list(manifest.get('repositories', []))
manifest['require']['splicewire/service-desk-sla'] = 'dev-main#' + ref
manifest.setdefault('extra', {}).setdefault('merge-plugin', {})['include'] = []
pathlib.Path('composer.sla.json').write_text(json.dumps(manifest, indent=4) + '\n')
shutil.copyfile('composer.lock', 'composer.sla.lock')
PYTHON
```

```sh
COMPOSER=composer.sla.json XDEBUG_MODE=off herd composer update splicewire/service-desk-sla --minimal-changes --dry-run --no-scripts --no-interaction
COMPOSER=composer.sla.json XDEBUG_MODE=off herd composer update splicewire/service-desk-sla --minimal-changes --no-scripts --no-interaction --prefer-dist
```

Inspect the solver delta before installation: the initial proof adds only the extension, with zero existing package updates/removals. Verify installed `source.reference` and physical files against the exact source archive. A path symlink alone does not establish artifact installation.

Prepare the owned database and generated contracts:

```sh
XDEBUG_MODE=off herd php artisan key:generate
XDEBUG_MODE=off herd php artisan package:discover
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction
XDEBUG_MODE=off herd php artisan db:seed --class=ServiceRequestApprovalSeeder --no-interaction
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm run build
XDEBUG_MODE=off herd php scripts/service-request-approval-fixture.php "$PWD/storage/app/private/sla-actors.json"
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=47119
```

Use the chosen port consistently in APP_URL. The private fixture writes random credentials only to its new mode-0600 file and refuses populated hosts. Its Owner/Admin/Member and second-team actors are tutorial setup, not an extension API. The existing install command can emit a starter navigation warning despite exit 0; inspect actual resource/schema/route/table discovery instead of treating the exit code as proof that every installer substep succeeded.

The accepted starter's locked Beam Inertia release lacks its existing `fileEditorHref` config property, so the original declared build fails before Vite. The selected disposable proof uses exact Beam `54b5b096d3e2197fec5258deb31b763969477bb6`, Schemastud `5ac791e79ceb2be846b8f880f4da04979123f795` and resources `f3ab73f4e5cbf4bc15415d262f961a231c217764`, with verified compiled exports and a fresh single-package Inertia build. This is an explicit alternate composition, not a claim that shipping locks build unchanged. Preserve the app feature, pin all source/peer inputs, use owned copies and record the actual resolved outputs. Do not use package-manager link preparation against shared source. The complete source/peer/hash recipe accompanies the slice proof.

## Configure and approve

Log in with the fixture Owner. In Service desk → Approval SLA, create an enabled 15-minute rule. Reload. Admin can edit it; Member can inspect it but cannot save. One rule belongs to the freshly selected team. Submitted actor/team IDs cannot change that attribution.

Create a service request through Service requests → New service request. Open it, choose Approve, and confirm the dialog. In SLA evaluations, inspect the read-only result and reload. Detail shows the source transition ID, request/team references, request creation and persisted approval time, plus the threshold snapshot. Exact equality is Met; later is Breached. The proof ages only owned fixture requests to 14 and 16 minutes, then uses the same public approval UI; equality/subsecond behavior is separately tested against persisted facts.

Disable the rule and approve another request: approval and its fact remain normal and no new evaluation appears. Existing receipts retain their threshold. Other teams have separate settings/results; removed membership, teamless/guest access and forbidden writes do not become empty successful data. An upstream read failure shows an error with Retry. Successful-empty stock text is “No records.”

Observation is best effort. A missing/inconsistent source throws into the existing workflow observer-reporting seam after commit. Approval remains committed; no false Met receipt is manufactured. There is no outbox, backfill, scheduler, billing enforcement or transaction spanning approval and observation. The rule read after commit may reflect a concurrent edit. Repeated fact delivery deduplicates by persisted transition UUID; this does not establish universal exactly-once delivery.

## Remove and reinstall without deleting data

In this owned alternate Composer context:

```sh
COMPOSER=composer.sla.json XDEBUG_MODE=off herd composer remove splicewire/service-desk-sla --minimal-changes --no-interaction
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm run build
```

Normal Composer hooks refresh package discovery and Popcorn registries. Check their actual completion. Do not roll back migrations or drop tables. Feature navigation/resources/listener disappear while core requests and approval continue. Rules/evaluations remain stored.

Add the same exact VCS requirement back to `composer.sla.json`, run the constrained update above with normal hooks, then regenerate assets and build. Retained settings/results reappear. Reinstall does not scan old facts; approvals made while disabled, removed or while observation failed receive no retroactive evaluation. Compare the retained rows before/after, not just UI visibility. No marketplace publication, purchase, licensing or release is part of this local example.
