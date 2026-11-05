# Service desk: setup and historical local-source proof

For the current starter, run `composer setup`, then `composer dev`. Setup registers the service-request
approval lineage through the normal seed command, alongside the accounts demo subjects. Sign in as a
Member, create a request, then sign in as a different same-team Owner and approve it. The request's
Approval state becomes approved; its ordinary Status and creator remain unchanged.

The host config key `examples.service_desk.seed` defaults to on outside production and off in
production; `SERVICE_DESK_SEED_EXAMPLE` explicitly overrides it. Disabling it reports a skipped step.
The accounts demo-user gate is separate and retains its default. No demo override is added to `.env.example`.
See [the current preview](service-desk-current-preview.md) for the complete current source packet and
[approval](service-desk-approval.md) for the request/decision contract.

The recipe below is retained historical six-checkout and HTTP proof, not the normal setup path or a
registry release. Its explicit seed call and demo-user override describe that old fixture only. Use
fresh owned roots; do not mix its pinned sources with a later preview. Prefix-free commands assume a
working PHP/Composer toolchain. These commands have not been re-proven at the B21 checkpoint.

## Historical basic checkpoint

The following earlier six-checkout recipe records the basic resource/client checkpoint. Its sources and fresh fixture are historical. Do not concatenate it with later approval/usage/history recipes or treat its old install/update commands as the current cumulative preview. For a fresh current run, use the chapter and packet above, including contained Composer homes/cache and the reviewed wrapper from the first Composer command.

Create a team-owned request with a Title and Status. Consume generated route and response contracts, then use the stock Frame list, create and read-only detail. The example already contains the declaration and host wiring so you can inspect and run the complete composition.

This is a **local development candidate** recipe. Obtain the supplied six Git checkouts first; these unpublished checkpoints are not registry releases or equivalent to remote main. Use a new disposable directory, Node 22.13+, pnpm 9.15.3, Python3, PHP and Composer. Commands below run in one shell unless a second terminal is explicitly named. No owner site, environment or database is used.

## 1. Prepare a fresh host

Set `CANDIDATE_ROOT` to the directory containing the six supplied checkouts named in the script. Set `DEMO_PARENT` to an existing directory you own for disposable development files. The script clones each supplied source and checks out its exact checkpoint; it does not alter the supplied checkouts. Choose an unused numeric localhost port; 46802 is the example default.

```bash
export CANDIDATE_ROOT=/absolute/path/to/supplied/checkouts
export DEMO_PARENT=/absolute/path/to/owned/scratch
export GUIDE_PORT=46802
export GUIDE_ROOT="$(mktemp -d "$DEMO_PARENT/beam-service-desk.XXXXXX")"
node --input-type=module <<'JS'
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
const sources = [
  ['host', 'wt-beam-dx-service-request-20261021', '478561dee1d5cf82f4ee008742e9bdd1524b7026'],
  ['ux', 'wt-beam-dx-ux-release-recording-20261022', '237206e91b8dd2ea2d919aafb24b786caac38c8e'],
  ['beam', 'wt-beam-dx-beam-authoring-20261022', '2a377b28cfe26d42aaaf199254c6e965fbff45fe'],
  ['frame', 'wt-beam-dx-schemastud-read-schema-20261021', 'a773feb9604fe1f32433211f86334a43640770ee'],
  ['workflows', 'wt-beam-dx-workflows-release-20261022', '7ee34de5511aecbaacf173dcd1b39cd6397e3d65'],
  ['phpframe', 'wt-beam-dx-frame-read-schema-20261021', 'b9adc1ac13562f3e32d0c84926a1bd97c64e7f85'],
];
if (!/^\d+$/.test(process.env.GUIDE_PORT ?? '') || +process.env.GUIDE_PORT < 1024 || +process.env.GUIDE_PORT > 65535)
  throw Error('Choose an unused numeric localhost port from 1024 to 65535');
for (const [name, supplied, sha] of sources) {
  const source = join(process.env.CANDIDATE_ROOT, supplied);
  const target = join(process.env.GUIDE_ROOT, name);
  if (!existsSync(source) || existsSync(target)) throw Error('Use supplied sources and a fresh output');
  execFileSync('git', ['clone', '--no-hardlinks', '--no-checkout', source, target], { stdio: 'inherit' });
  execFileSync('git', ['-C', target, 'checkout', '--detach', sha], { stdio: 'inherit' });
  if (execFileSync('git', ['-C', target, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).trim() !== sha)
    throw Error('Candidate mismatch');
}
JS
cd "$GUIDE_ROOT/host"
composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
```

The existing nonproduction scratch-storage setting below directs generated mirrors/artifacts into this disposable directory. It leaves authored source files intact and makes local Git recording unavailable there. The alternate Composer manifest below overlays only PHP Frame, UX and workflows. The host's canonical locks stay unchanged. Other PHP packages and JS peers remain pinned by committed locks.

```bash
node --input-type=module <<'JS'
import { readFileSync, writeFileSync, copyFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
if (existsSync('composer.proof.json')) throw Error('Use a new host');
const manifest = JSON.parse(readFileSync('composer.json', 'utf8'));
for (const [name, directory] of [
  ['schemastud/laravel-frame', 'phpframe'],
  ['splicewire/laravel-beam-ux', 'ux'],
  ['splicewire/laravel-beam-workflows', 'workflows'],
]) manifest.repositories.unshift({ type: 'path', url: join(process.env.GUIDE_ROOT, directory),
  options: { symlink: true, versions: { [name]: 'dev-main' } } });
writeFileSync('composer.proof.json', JSON.stringify(manifest, null, 4) + '\n', { flag: 'wx' });
copyFileSync('composer.lock', 'composer.proof.lock');
JS
COMPOSER=composer.proof.json composer update \
  schemastud/laravel-frame splicewire/laravel-beam-ux splicewire/laravel-beam-workflows \
  --no-scripts --no-interaction
python3 - <<'PY'
from pathlib import Path
import os, re
host = Path.cwd()
values = {'APP_ENV': 'local', 'APP_URL': 'http://127.0.0.1:' + os.environ['GUIDE_PORT'],
    'DB_CONNECTION': 'sqlite', 'DB_DATABASE': str(host / 'database/service-desk.sqlite'),
    'CACHE_STORE': 'array', 'SESSION_DRIVER': 'file', 'QUEUE_CONNECTION': 'sync',
    'MAIL_MAILER': 'array', 'FILESYSTEM_DISK': 'local', 'LOG_CHANNEL': 'single',
    'ACCOUNT_SEED_DEMO_USERS': 'false',
    'BEAM_SCRATCH_STORAGE_ROOT': str(Path(os.environ['GUIDE_ROOT']) / 'files')}
text = (host / '.env.example').read_text()
for key, value in values.items():
    pattern = re.compile(r'^#?\s*' + key + r'=.*$', re.M)
    text = pattern.sub(key + '=' + value, text) if pattern.search(text) else text + '\n' + key + '=' + value + '\n'
with (host / '.env').open('x') as output: output.write(text)
(host / '.env').chmod(0o600)
(host / 'database/service-desk.sqlite').touch(exist_ok=False)
PY
php artisan key:generate
php artisan popcorn:registries:cache
php artisan splicewire:beam:install --no-interaction
php artisan db:seed --class=ServiceRequestApprovalSeeder
```

Prepare the paired UI through the starter's existing scripts. The workflow peer is generated from installed DTOs by the canonical transformer; it is a build input, not another runtime policy. The action-form build is the public subpath needed here, not the unrelated workflow graph/admin build. The explicit parser/tool dependencies belong only to this disposable preparation directory.

```bash
export FRAME_JS_ROOT="$GUIDE_ROOT/frame"
export BEAM_UI_ROOT="$GUIDE_ROOT/beam"
export UI_BUILD_TOOLS="$GUIDE_ROOT/tools"
mkdir "$UI_BUILD_TOOLS"
node --input-type=module <<'JS'
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
writeFileSync(join(process.env.UI_BUILD_TOOLS, 'package.json'), JSON.stringify({ private: true,
  packageManager: 'pnpm@9.15.3', dependencies: { tsup: '8.5.1', typescript: '5.9.3', zustand: '5.0.14',
    recast: '0.23.12', '@babel/parser': '7.29.7', '@babel/types': '7.29.7' } }, null, 2) + '\n');
JS
(cd "$UI_BUILD_TOOLS" && pnpm install)
export RELEASE_WORKFLOW_PEER="$GUIDE_ROOT/workflow-peer"
php examples/generate-release-workflow-peer.php "$RELEASE_WORKFLOW_PEER"
node examples/prepare-release-ui.mjs
npm --prefix "$FRAME_JS_ROOT" run build --workspace frame
npm --prefix "$BEAM_UI_ROOT" run build:action-form --workspace beam-workflows
npm --prefix "$BEAM_UI_ROOT" run build --workspace beam-ux
npm --prefix "$BEAM_UI_ROOT" run build --workspace beam-inertia
```

## 2. Read the declaration and host composition

In `app/Data/ServiceRequestData.php`, `#[ParticleResource]` names the Eloquent `App\Models\ServiceRequest` backing, `ServiceRequestData` read DTO and `ServiceRequestInputData` input/edit DTO. `editable:false`, `deletable:false` and `showable:true` select create plus read-only detail, not a universal editor. `publicRead:null` is this authenticated resource's declaration, not an anonymous-safe field policy.

Input is Title (maximum 200 characters) and Status (`open` default, `open` or `closed`). Output is ID, Title, Status and Approval. Team and creator IDs come from server preparation, not form input. The model's migration supplies storage. `ServiceRequestTeam` and the resource scope check live selected-team membership. Service desk navigation is registered by the app; it does not require a content-nav seed file. The host chooses `config/frame.php` realm placement and middleware, Service desk navigation, and the packaged console mount in `routes/web.php`; the generic resource socket is separate. Discovery does not replace these choices or a migration.

## 3. Generate and call the actual endpoint

Create a **new shared fixture**, including credentials for ordinary form logins. The current Node client refuses the older private-row fixture. The helper writes a private mode 600 JSON file; keep its tokens and passwords out of public output. Array mail sends no invitation email.

```bash
php artisan splicewire:beam:generate:assets --json
pnpm exec tsc --project tsconfig.service-request.json
pnpm build
php scripts/service-request-fixture.php storage/app/private/shared-actors.json --shared
```

Print the following assignments in the first terminal, then copy the printed line into a second terminal so it has the same directory and port. Keep that second terminal running:

```bash
# First terminal: print the assignments to copy into the second terminal.
printf 'export GUIDE_ROOT=%q GUIDE_PORT=%q\n' "$GUIDE_ROOT" "$GUIDE_PORT"
```

After copying that printed line into the second terminal:

```bash
cd "$GUIDE_ROOT/host"
php artisan serve --host=127.0.0.1 --port="$GUIDE_PORT" --tries=1 --no-reload
```

Back in the first terminal:

```bash
SERVICE_REQUEST_BASE_URL="http://127.0.0.1:$GUIDE_PORT" \
SERVICE_REQUEST_FIXTURE="$GUIDE_ROOT/host/storage/app/private/shared-actors.json" \
node scripts/service-request-client.mjs
```

Expected: `result: passed` after real HTTP requests, including create201, same-team list/detail200, invalid input422 with no added row, guest401, foreign-team record404 and teamless403. The driver imports generated route templates and `ServiceRequestData` response types. Its request body is `unknown` and its response cast is not runtime validation. This proves neither typed mutation-body validation nor executing React hooks. Server validation and authorization remain authoritative.
