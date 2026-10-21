# Service requests: declaration to a typed API consumer

This example creates, lists and reads a service-desk request. The completed implementation ships in
this starter. It uses Beam's generic resource controller and Eloquent backing, with per-user ownership.
It does not add a CRUD screen, tenancy, teams or release/git authoring.

## Prerequisites and fresh setup

Use Herd PHP with the extensions required by composer.lock, Composer 2, Node 22.13+, pnpm 9.15.3,
and access to the locked GitHub packages. Keep the committed PHP/JS locks; no local overlay is needed.
Start from a **new clone of your candidate checkpoint**, never a live site's checkout or database.
For an OpenRig proof, use the ecosystem's scratch clone/ownership and capacity tools, retain evidence
through handoff, and use its guarded teardown. Do not use helpers that copy an owner .env or relink Herd.

```bash
git clone /absolute/path/to/candidate /absolute/path/to/new-service-desk
cd /absolute/path/to/new-service-desk
git checkout <candidate-commit>
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
```

Create the fixture's own environment and new SQLite file. The following refuses existing state;
it reads only the committed .env.example. Use an available localhost port (46210 here).

```bash
node --input-type=module <<'JS'
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const database = resolve('database/service-desk.sqlite');
if (existsSync('.env') || existsSync(database)) throw Error('Use a new fixture');
let env = readFileSync('.env.example', 'utf8');
const values = {
    APP_ENV: 'local', APP_URL: 'http://127.0.0.1:46210', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array', FILESYSTEM_DISK: 'local', LOG_CHANNEL: 'stderr',
};
for (const [key, value] of Object.entries(values)) {
    const line = new RegExp(`^#?\\s*${key}=.*$`, 'm');
    env = line.test(env) ? env.replace(line, `${key}=${value}`) : `${env}\n${key}=${value}\n`;
}
writeFileSync('.env', env, { flag: 'wx', mode: 0o600 });
writeFileSync(database, '', { flag: 'wx', mode: 0o600 });
console.log({ connection: 'sqlite', database });
JS
XDEBUG_MODE=off herd php artisan key:generate
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction
```

The explicit registry cache step replaces a Composer hook skipped by --no-scripts. The installer
publishes missing package files, migrates this new database and seeds its package fixtures.
Use no --force: it means overwrite published configuration. This path does not need Herd links,
nginx changes, a PostgreSQL service or an external mail/queue service. Do not run it against owner state.

## What the completed declaration supplies

To scaffold an equivalent resource in a different fresh application, use:

```bash
XDEBUG_MODE=off herd php artisan make:model ServiceRequest --migration --policy
XDEBUG_MODE=off herd php artisan splicewire:beam:make:particle-resource ServiceRequest --key=service-requests
```

Do not rerun these over this example. The generators create placeholders; completing the model,
migration, policy, DTO fields/write map and authenticated mount is the author's work.

- app/Models/ServiceRequest.php and its migration define integer id, title, status, UUID user_id and
  timestamps. Only title/status are mass assignable.
- app/Data/ServiceRequestData.php declares backing: ServiceRequest::class, its own read data:, explicit
  input: ServiceRequestInputData::class and publicRead: null. The ordinary model becomes an EloquentBacking;
  no custom controller, backing adapter or second registry is needed. The read projection is id/title/status.
- app/Data/ServiceRequestInputData.php implements MapsToModelAttributes. Title is required, nonempty and at
  most 200 characters. Status is open or closed and defaults to open. Invalid input returns JSON 422.
- The read Data's scope selects the authenticated actor's rows. Its prepare hook sets user_id from the
  authenticated actor, never request input. Extra user_id/userId input cannot transfer ownership.
- app/Policies/ServiceRequestPolicy.php permits authenticated create/list and checks ownership on detail.
  These CRUD policies are distinct from an operation's ability: declaration; ParticleResource has no ability: slot.
- routes/api.php explicitly mounts only index/show/store under auth:sanctum using Particle::mount. The
  attribute registers a declaration; it does not mount these REST routes. Guests receive 401; another actor's
  record receives 404 from the scoped lookup. Update/delete are not exposed.

The routes are service-requests.index/store at `/api/service-requests` and service-requests.show at
`/api/service-requests/{id}`. Successful writes return 201; reads return 200, each with a data envelope.
For generic contracts, see the installed Beam [particle doctrine](../../vendor/splicewire/laravel-beam/docs/agents/particle-doctrine.md)
and [client runtime contract](../../vendor/splicewire/laravel-beam/docs/client-runtime-contract.md).

## Generate and check the contract

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm types:check
pnpm exec tsc --project tsconfig.service-request.json
XDEBUG_MODE=off herd php artisan test --filter='ServiceRequestJourneyTest|TypeScriptGenerationTest'
```

Inspect the generator's ran/skipped/failed summary. This starter's configured pipeline runs
TypeScript transformation, JSON schemas, then SDK generation. No required step should be skipped.
`composer types:generate` alone does not regenerate client routes/hooks.

The actual outputs are resources/js/generated/App/Data/index.ts (ServiceRequestData and input),
routes.ts (route values), hooks/service-requests.ts (generated React Query hooks) and aliases.ts.
The configured schema generator also emits resources/schemas. Namespace Data modules are gitignored
and regenerated; routes/hooks/aliases follow this repository's committed-artifact policy.
The type probe in tests/Types/service-request.ts rejects a numeric title through @ts-expect-error;
if that response shape becomes untyped, the compiler reports an unused expectation.

**Typing boundary:** generated responses are typed; generated mutation bodies currently remain unknown.
The input DTO validates on the server. This example does not claim generated request-body type safety,
runtime response-schema validation or execution of generated React hooks.

## Issue disposable credentials and run the client

In the fresh fixture, create two new tutorial actors and tokens in a private file. The issuer refuses
an existing output and requires local/testing with a real service-desk.sqlite file inside this fixture's database
folder. Do not put the credential file in public/ or include it in evidence.

```bash
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/service-request-fixture.json
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46210 --tries=1
```

The server binds localhost and attempts only that port. If occupied, choose another explicitly;
never stop another process or let the server move ports. In another terminal in the fixture:

```bash
SERVICE_REQUEST_BASE_URL=http://127.0.0.1:46210 \
SERVICE_REQUEST_FIXTURE="$PWD/storage/app/private/service-request-fixture.json" \
pnpm example:service-request
```

The Node driver in examples/service-request-client.ts imports **actual generated route values and
ServiceRequestData**. The runner bundles that TypeScript in memory using the existing esbuild dependency.
Node fetch sends the fixture bearer with no cookie/session. This is a separate declared transport
boundary from the browser's host-owned @/lib/api (which uses same-origin cookies/XSRF).

The driver prints only response data, method/path/status and its result, never token values. It creates
a request, finds it in the owner's list and reads matching detail; invalid input yields 422 without
an extra owner row, a guest yields 401, and another actor cannot see/read it. It also attempts to forge
ownership on a second create: the row stays with the creating actor and the first actor cannot read it.
Use tests/Feature/ServiceRequestJourneyTest.php for independent database assertions. For a manual
check, inspect this fixture's SQLite service_requests table and compare the returned ids, title/status
and user_id with the private fixture actor ids; do not copy tokens into the report.

Stop only this server with Ctrl-C. Keep evidence outside the disposable fixture and remove it through
its existing scratch/fixture lifecycle after review. This API-only journey provides no UI or visual proof.
