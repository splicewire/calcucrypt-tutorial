# Service requests: declared API, typed client and resource UI

The current paired [read-schema continuation](service-desk-read-schema.md) adds explicit read detail
without extending the write input. Use its package overlay for that outcome.

> Historical checkpoint: this chapter and its commands describe starter
> `35cbc40e6ca6bea295ba7cad36cd0a0d3e1624bb` with Beam UI
> `0991bf37cb66ed948a863b86050e3dc1890e61d6`. Check out that starter commit for the original
> creator-only behavior and client. For current team-shared requests, follow the explicit
> [shared queue continuation](service-desk-team-requests.md); latest source requires team membership.

This example creates, lists and reads a service-desk request. The completed implementation ships in
this starter. It uses Beam's generic resource controller and Eloquent backing, with per-user ownership.
The UI continuation below adds the existing Frame create/list/read-only-detail experience.
Continue with [team creation and local invitation](service-desk-team.md) after this resource journey.
Tenancy, editing, deletion and release/git authoring are outside this example.

## Prerequisites and fresh setup

Use Herd PHP with the extensions required by composer.lock, Composer 2, Node 22.13+, pnpm 9.15.3,
and access to the locked GitHub packages. Keep the committed PHP/JS locks. The API client uses them directly; the UI continuation uses an
explicit local Beam package candidate until that shared change is published separately.
Start from a **new clone of your candidate checkpoint**, never a live site's checkout or database.
For an OpenRig proof, use the ecosystem's scratch clone/ownership and capacity tools, retain evidence
through handoff, and use its guarded teardown. Do not use helpers that copy an owner .env or relink Herd.

```bash
git clone /absolute/path/to/candidate /absolute/path/to/new-service-desk
cd /absolute/path/to/new-service-desk
git checkout 35cbc40e6ca6bea295ba7cad36cd0a0d3e1624bb
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
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
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
its existing scratch/fixture lifecycle after review. The client journey above provides API proof. Continue below for the browser UI.

## Expose the resource through Frame

The completed ServiceRequestData adds its display label/singularLabel, `form: 'bare'`,
`editData: ServiceRequestInputData::class`, `section: 'service-desk'`, icon and navigation order.
Its explicit `editable: false`, `deletable: false`, `showable: true` keep the record surface read-only
while leaving creation enabled. `#[Column]` on title and status selects the list columns; the status
column names the existing badge renderer. `#[Title('Service request')]` on the input DTO supplies the
form's human label. No id or user_id field is part of that input schema.

The host adds the resource to `config/frame.php`'s existing `realms.tenant` list and registers a
Service desk `NavSection` in AppServiceProvider. That section uses the existing registry, Product
audience and explicit null entitlement/permission slots. It is a navigation seat for authenticated
users, not a team/tenant implementation. The generic console mount in routes/web.php derives its
segments from this membership and section; there is no ServiceRequest page, controller or transport.
`section` joins a registered seat; a display group alone would not create it.

On this dependency checkpoint the original REST-only declaration was absent from Frame's manifest.
The added presentation and exposure now produce list, separate `/new`, and read-only `/:id` leaves.
Do not generalize that historical registration behavior into a rule that labels define resource
existence: newer package work separates resource registration from navigation. Inspect the actual
manifest and routes for the package snapshot you consume.

Frame serves the input DTO through its **request-mode** schema generator because editData explicitly
selects it. Input validation alone does not select the form schema. Here title is required and limited
to 200 characters; status is an open/closed choice with default open. The generic generated schema and
the request-mode schema can differ in required/default treatment. The form fetches the actual schema.

Owning references in the installed sources:
[Frame resource socket](../../vendor/schemastud/laravel-frame/src/Http/Controllers/FrameResourceController.php),
[resource declaration](../../vendor/splicewire/laravel-beam/src/Particle/Attributes/ParticleResource.php),
and [navigation seat](../../vendor/splicewire/laravel-beam/src/Nav/NavSection.php).
In the Schemastud catalogue see `Frame/EditShell` create/detail and `Frame/ListShell`; in Beam see
`Inertia/Frame/Console/Create Returns To List`. Those component fixtures complement the host journey.

## Build the explicit local UI candidate

Use a **new isolated Beam source checkout** at the package candidate supplied with this example.
The shared change belongs to beam-inertia's existing console router: a successful generic create calls
EditShell's onSaved callback and visits its own list in the same shell/realm. Failed creates remain
on the form; record edit/detail and heavyweight widgets keep their existing behavior.

The selected Beam checkpoint's root npm lock is inconsistent with its beam-resources declaration, so
`npm ci` at that source root is not a working prerequisite for this example. Keep that lock and the
starter locks unchanged. The following local build overlay uses the starter's locked runtime peers
and isolated, pinned build tools. All paths must be new, disposable development paths, not live sites.
Run this while in the fresh starter fixture. `BEAM_UI_WORKTREE` must name the newly cloned Beam root;
its node_modules must not exist yet. Do not point it at a shared package checkout.

```bash
export SERVICE_DESK_ROOT="$PWD"
export BEAM_UI_WORKTREE=/absolute/path/to/new-beam-candidate
export BEAM_BUILD_TOOLS=/absolute/path/to/new-beam-build-tools
# Clone the supplied local Beam candidate and check out its exact checkpoint first.
npm install --prefix "$BEAM_BUILD_TOOLS" --ignore-scripts --no-audit --no-fund --save-exact tsup@8.5.1 typescript@5.9.3
node --input-type=module <<'JS'
import { existsSync, mkdirSync, readdirSync, symlinkSync } from 'node:fs';
import { resolve, join } from 'node:path';
const { SERVICE_DESK_ROOT, BEAM_UI_WORKTREE, BEAM_BUILD_TOOLS } = process.env;
if (!SERVICE_DESK_ROOT || !BEAM_UI_WORKTREE || !BEAM_BUILD_TOOLS) throw Error('Set all three explicit roots');
const destination = resolve(BEAM_UI_WORKTREE, 'node_modules');
if (existsSync(destination)) throw Error('Use a new isolated Beam source checkout');
mkdirSync(destination);
const host = resolve(SERVICE_DESK_ROOT, 'node_modules');
for (const name of readdirSync(host)) {
    if (name.startsWith('.') || ['tsup', 'typescript'].includes(name)) continue;
    symlinkSync(join(host, name), join(destination, name));
}
for (const name of ['tsup', 'typescript', '.bin']) {
    symlinkSync(resolve(BEAM_BUILD_TOOLS, 'node_modules', name), join(destination, name));
}
JS
(cd "$BEAM_UI_WORKTREE" && npm run build --workspace beam-inertia)
node --input-type=module <<'JS'
import { lstatSync, unlinkSync, symlinkSync } from 'node:fs';
import { resolve } from 'node:path';
const installed = resolve('node_modules/@splicewire/beam-inertia');
if (!lstatSync(installed).isSymbolicLink()) throw Error('Expected a disposable pnpm package link');
unlinkSync(installed);
symlinkSync(resolve(process.env.BEAM_UI_WORKTREE, 'beam-inertia'), installed);
JS
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
pnpm build
XDEBUG_MODE=off herd php artisan route:list --path=frame
XDEBUG_MODE=off herd php artisan test --filter='ServiceRequestJourneyTest|ServiceRequestFrameTest|TypeScriptGenerationTest'
```

This overlay changes only this fixture's installed package link; no shipping manifest/lock receives a
machine path. The package build externalizes runtime peers, and those links resolve to the starter's
committed installs. Record the exact source checkpoint, built exports and emitted host Vite manifest
for a proof. Rebuilding a package after the host build requires rebuilding the host again. No shared
Storybook artifacts, Herd links or host configuration need changing.

## Follow the browser journey

Use a new database for this UI walkthrough, or finish the fixed-row API smoke in a separate fixture.
The initial UI owner must have no service requests. Generate two new password-login actors privately:

```bash
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/ui-actors.json --browser
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46210 --tries=1 --no-reload
```

`--browser` creates verified local fixture users and records email/password alongside the disposable
API token. Read the owner login locally from that private file; never publish it or paste it into
proof. It is a teaching/test fixture, not user registration or a product credential screen. Sessions
use this fixture's file driver. Set it before starting the server; changing .env while `--no-reload`
is running requires restarting your own server.

1. Open `http://127.0.0.1:46210/login`, sign in as owner and reach Dashboard. Use the Service requests
   link under Service desk. The list starts empty and offers New service request.
2. Click New. Title is blank/required; status renders the declared open/closed choices with open
   selected. Save without a title: the browser focuses the field and shows its native required-field
   message. No request is written. The server independently refuses invalid input with JSON 422.
3. Enter “Replace broken monitor” and Save once. The console automatically returns to the list after
   the successful write; the new title and open badge are visible. Click the row, then reload. Detail
   shows the same values in read-only controls, with no Save/Delete.
4. In a separate private browser context, sign in as other. Its list is empty and a direct first-actor
   detail URL reveals no values. On this locked transport a record 404 currently appears as “Could not
   load this record.” with Retry. This is a generic unavailable state, not a claim of field-level or
   specialized not-found messaging. A guest is redirected to sign-in by the console route.

Frame uses session cookies and CSRF at `/frame/resources/service-requests`, with `/schema` and
`/records/{id}` for form/detail. Its successful create returns **200**, while the separate bearer API
client's `/api/service-requests` returns **201**. Anonymous JSON requests return 401. Other-actor
record reads return 404; unsupported update/delete are denied 403 by model-policy authorization.
Ownership is still assigned by prepare from the authenticated actor; forged owner fields are ignored.
No authorization is weakened for a visible control. A generic server write failure is shown as a
status message; inline server-422 field mapping is not promised by this transport.

The same component catalogue covers generic create-success navigation independently of Laravel.
Host browser proof must additionally confirm navigation, schema, persisted SQLite values and ownership.
Stop only your own server and retain/remove fixtures through their existing lifecycle. This guide
makes no claim that the whole application's unrelated static-analysis or test suites are green.
