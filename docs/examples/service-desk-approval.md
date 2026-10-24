# Approve a shared service request through a declared action

For the current **one-host, one-request** trail, start with [the cumulative preview](service-desk-current-preview.md).
The setup/checkpoint commands below retain their own historical inputs; do not concatenate their fresh fixtures.

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

For the paired read-schema candidates and visible disabled ID/Approval detail, continue with
[write/read schema purpose](service-desk-read-schema.md). This chapter retains its historical
locked-package setup and list/history proof; the continuation supplies the current package overlay.

Continue from [shared team requests](service-desk-team-requests.md) with the supplied current starter
checkpoint and unchanged Beam UI `ec097daba3c1ace1c482c07467b3eb71bc0ec729`. A Member creates a request;
a selected-team Owner or Admin approves it. The existing Frame action, confirmation, notice and refetch
supply the UI. No new controller, workflow editor or JavaScript workflow dependency is needed.

Approval is a separate decision: `pending → approved`. Existing `status` still accepts open/closed
at creation; closed does not mean approved. Historical rows default to pending without fabricated
approval history. Creator/team attribution, team-null quarantine and selected-team read scope remain.
Ordinary request fields stay read-only after create; there is no general update/delete API.

## Fresh host and explicit definition setup

Use Herd PHP, Composer 2, Node22.13+ and pnpm9.15.3 with access to the committed packages. Clone the
supplied current starter checkpoint into a **new disposable host**, never an owner checkout. Use
existing scratch ownership/capacity/retention tools for rig proof. Do not reuse retained tenancy DBs.

```bash
git clone /absolute/path/to/current-candidate /absolute/path/to/new-approval-desk
cd /absolute/path/to/new-approval-desk
git checkout <current-starter-checkpoint>
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
node --input-type=module <<'JS'
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const database = resolve('database/service-desk.sqlite');
if (existsSync('.env') || existsSync(database)) throw Error('Use a new fixture');
let env = readFileSync('.env.example', 'utf8');
const values = {
    APP_ENV: 'local', APP_URL: 'http://127.0.0.1:46530', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    FILESYSTEM_DISK: 'local', LOG_CHANNEL: 'single', ACCOUNT_SEED_DEMO_USERS: 'false',
};
for (const [key, value] of Object.entries(values)) {
    const line = new RegExp(`^#?\\s*${key}=.*$`, 'm');
    env = line.test(env) ? env.replace(line, `${key}=${value}`) : `${env}\n${key}=${value}\n`;
}
writeFileSync('.env', env, { flag: 'wx', mode: 0o600 });
writeFileSync(database, '', { flag: 'wx', mode: 0o600 });
JS
XDEBUG_MODE=off herd php artisan key:generate
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
XDEBUG_MODE=off herd php artisan splicewire:beam:install --no-interaction
XDEBUG_MODE=off herd php artisan db:seed --class=ServiceRequestApprovalSeeder
```

The normal installer creates the canonical workflow/activity tables, including published tenant/shared
migration paths, on this SQLite default connection. Do not copy package schemas or run a second
workflow engine. The additive host migration adds only approval_state/workflow_version. The explicit
seeder validates and creates the service-request.approval lineage/version idempotently; it preserves
an existing lineage rather than replacing its definition. Bindings register at boot without writing
or seeding a database. Registration/demo user seeding remains off in this fixture.

Follow the [explicit local UI build recipe](service-request.md#build-the-explicit-local-ui-candidate)
using a **new Beam clone at ec097da above**, new pinned build tools and this fixture as SERVICE_DESK_ROOT.
The historical chapter supplies the build recipe only; keep the current starter checkpoint and locks.
Then run in this host:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off pnpm exec tsc --project tsconfig.service-request.json
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-request-approval-fixture.php storage/app/private/approval-actors.json
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46530 --tries=1 --no-reload
```

Choose another available localhost port explicitly if needed. The new fixture helper refuses existing
users/teams/output, nonlocal/non-SQLite hosts, a different database filename/location, remote URL,
non-file sessions, async queues or non-array mail. It creates seven verified, non-Root reserved
`.invalid` actors through existing TeamProvisioner and keeps passwords/tokens in the mode600 private
file. Read it locally for ordinary form login; never attach it or authentication traces to proof.
Existing service-request-fixture.php modes and other guides remain separate and unchanged.

| Actor | Selected membership / extra feature authority |
| --- | --- |
| owner | A Owner |
| admin | A Admin |
| member | A Member |
| outsider | B Owner |
| teamless | none |
| operator | C Owner, canonical operator-root manage grant |
| theme-owner | D Owner, canonical site-root manage grant |

Feature grants are controls, not approval credentials for A. The policy requires current selected-team
membership with Owner/Admin role and matching request team. Creator attribution is independent of
that approval authority. Root, operator privilege and Theme authoring are not used to approve A.

## Create, confirm, approve and reload

1. In a private browser context sign in as member through `/login`. Use Service desk → Service requests,
   create “Replace broken monitor” with status open, and return to the list. Approval is pending.
   Member has no actionable Approve button. Read-only detail still shows the ordinary title/status.
2. In a separate context sign in as owner. Open the shared request. Press the existing **Approve**
   action, inspect its confirmation and confirm once. The actual web request returns200 with
   applied=true, the existing notice says “Request approved.” and the resource refetches.
3. Return through Service requests. The **Approval** column now reads approved; reload retains it.
   Open detail again: title/status remain unchanged and read-only. This checkpoint does **not** add
   an Approval field to the detail form: its editData schema remains the title/status input. Verify
   persisted marking through the list, actual GET and history below. Separate read/display-schema
   context support is unfinished framework territory, not a fake writable input field in this guide.
4. Create a second request as member with status closed and approve it as admin. It remains closed,
   becomes approved and retains member as creator. These are independent axes.

The generic action may still be offered on an approved row because list capability projection is
advisory. Confirming again returns409 with applied=false and a blocker; the dialog displays refusal
rather than a success toast. No second fact/effect is added. A pending stale tab must receive that
same server refusal. A broken version pin also fails closed. The narrow invalid stored-marking
adapter maps only the locked Symfony domain diagnostic to409; unrelated programming faults remain500.

## Inspect state, version, immutable history and local effects

Use the actual request id from the response/URL. In this same private host:

```bash
export APPROVAL_REQUEST_ID=1
XDEBUG_MODE=off herd php artisan tinker --execute='
$r = App\Models\ServiceRequest::findOrFail((int) getenv("APPROVAL_REQUEST_ID"));
dump($r->only(["id", "title", "status", "user_id", "team_id", "approval_state", "workflow_version"]));
dump(app(Splicewire\Beam\Workflows\Control\WorkflowHistory::class)->forSubject($r)->toArray());
dump(app(Splicewire\Beam\Workflows\Definition\DefinitionStore::class)->version($r->workflow_version)?->toArray());
'
rg 'Service request approval committed' storage/logs/laravel.log
```

A successful transition changes approval_state, pins the actual active immutable definition version
on first success, and inserts one workflow_transition_facts row atomically with that state. The fact
has a stable ID, service-request subject/id, approve, pending→approved, version, UTC occurrence time
and actor `user:<authenticated approver id>`. A row is not pinned at creation. Broken existing pins
never adopt a newer active definition. Definition edits fork immutable versions; this guide does not
edit/migrate definitions or expose authoring UI.

The named service-request.approval-receipt effect writes a safe local single-channel log receipt with
transitionId/subjectId/from/to/versionId/actor **after outer commit**. It contains no password, bearer,
request title/body or recipient. Compare its transition ID with WorkflowHistory. The durable truth
is the fact, not a log line. Effect failure is reported without undoing committed state/fact; a
process crash after commit can lose a best-effort receipt. No durable outbox or external webhook,
mail, job, connector or scheduled action is claimed.

Inspect activity_log separately for subject_type=service-request and the actual subject id. Display
uses event complete and a message like “transition:approve → approved”; its opaque actor is in
properties and causer is anonymous. Display is best effort and independent of authoritative history.
WorkflowTransitioned and named effects are the lifecycle event seams. ParticleOp kind Write does not
itself emit BeamParticlePersisted; the existing create/write pipeline's service-requests.persisted
catalog entry is a different signal, not automatic webhook delivery for this transition.

## Declared route/output consumer and refusals

The generated operation is **POST `/service-requests/{id}/approve`**, named service-requests.approve,
inside auth/verified web middleware with ordinary session/CSRF. Its fixed approve transition accepts
**no payload**. It declares record ability approve, WorkflowTransitionAttemptData output and an
ActionAffordance. The resource's existing selected-team scope also bounds operation subject lookup.
There is no separate bearer approval endpoint or arbitrary transition/actor/version input.

examples/service-request-approval-client.ts imports the actual generated route and output type and
exports approveServiceRequest(id) for a signed-in browser. It sends the normal XSRF cookie header,
checks HTTP status and returns the typed applied/blockers/projection outcome. It does not execute
React Query hooks or claim runtime schema validation. The generated hook's mutation body remains
unknown; server input:false rejects payloads rather than trusting client typing.

Compile this example and its negative type witness with the command above. tests/Types/
service-request-approval.ts fails if applied becomes untyped (the @ts-expect-error becomes unused).
The actual browser proof separately executes this exported consumer with a real session on another
pending record, alongside the ordinary generic UI path.

| Caller / state | Expected outcome |
| --- | --- |
| Selected A Owner/Admin, pending A request |200 applied, one state/pin/fact/effect |
| Selected A Member |403, keeps normal create/read |
| B/C/D Owner, including operator/theme grantees, A request |404 from resource scope |
| Guest JSON |401; ordinary HTML recovery is login |
| Removed, teamless, nonmember or dangling selected team |404 scoped subject absence |
| Authorized caller sends actor/state/version payload |422, no write |
| Already approved / invalid marking / broken pin |409, no new state/pin/history/effect |
| Unrelated unexpected engine/programming fault |500, not relabeled as conflict |

Missing/invalid CSRF is refused before the operation; use the normal browser session for these
controls. Also check a valid create with closed remains accepted while status=approved yields422.
Untrusted approval/actor/version fields on create cannot override server defaults or creator/team.
Compare request/pin/fact/effect rows around every refusal. Existing authenticated update/delete
surface stays closed; approval does not add general editing or a membership bypass.

```bash
XDEBUG_MODE=off herd php artisan test --filter='ServiceRequestApprovalTest|ServiceRequestTeamTest|ServiceRequestJourneyTest|ServiceRequestFrameTest|OperatorInspectionTest|ThemeSharedPropTest'
XDEBUG_MODE=off pnpm types:check
XDEBUG_MODE=off pnpm exec tsc --project tsconfig.service-request.json
XDEBUG_MODE=off pnpm build
```

ServiceRequestApprovalTest uses its own in-memory SQLite database with real commits, proving explicit
outer rollback leaves no marking/pin/fact/effect, commit emits once, and a controlled effect failure
leaves committed state/fact. It also tests broken pins, forged input, admin/member/outsider/stale scope,
wrong-state/repeat conflicts and unexpected500s. These checks supplement the real mounted browser
operation and persistence inspection; they are not full-suite or PostgreSQL concurrency proof.
Full configured PHPStan retains the two known unrelated schema-token migration errors35/47.

Owning references: installed workflows docs/transactional-lifecycles.md, docs/workflow-history.md and
LifecycleService/DefinitionStore/TransitionEffectRegistry; Beam's particle doctrine and ADR-0223;
Frame ADR-0005 and the Frame/ResourceActions catalogue's confirmation/refusal/detail states.
Stop this listener/browser, release capacity and record guarded scratch/SQLite retention or teardown.
No owner environment, retained PostgreSQL proof store, publication or deployment is needed.
