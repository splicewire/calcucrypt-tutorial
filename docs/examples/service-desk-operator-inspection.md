# Inspect teams through the existing Operator surface

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

Continue from the [shared request queue](service-desk-team-requests.md). Use the supplied current
starter checkpoint and unchanged Beam UI `ec097daba3c1ace1c482c07467b3eb71bc0ec729`.
This walkthrough reads host-wide team metadata through the shipped Operator → People → Teams UI.
It uses a private local grant fixture, ordinary password sign-in and the existing Frame transport.

The operator-root **manage** grant is broader than this walkthrough: it also unlocks existing OS
and operator authoring surfaces. It is not a new read-only operator role. Keep this journey to
navigation and reads. Do not use impersonation, edit users, change roles, author content or invoke
billing actions. An ordinary Owner, a Member on the operator team and a site-theme grantee do not
acquire operator authority just from those memberships or site grants.

## Prepare a new local host

Use Herd PHP, Composer 2, Node 22.13+, pnpm 9.15.3 and access to the committed packages. Start a
**new clone of the supplied current starter checkpoint**; keep its dependency locks. For rig proof,
use existing scratch ownership/capacity tools. Never reuse owner state or retained tenancy databases.

```bash
git clone /absolute/path/to/current-candidate /absolute/path/to/new-operator-desk
cd /absolute/path/to/new-operator-desk
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
    APP_ENV: 'local', APP_URL: 'http://127.0.0.1:46490', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    FILESYSTEM_DISK: 'local', LOG_CHANNEL: 'stderr', ACCOUNT_SEED_DEMO_USERS: 'false',
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
```

Follow the [explicit local UI build recipe](service-request.md#build-the-explicit-local-ui-candidate)
using a **new Beam clone at ec097da above**, new pinned build tools and this fresh host as
SERVICE_DESK_ROOT. That historical chapter supplies the build recipe only; retain the current
starter checkpoint. The overlay changes this disposable host's package link, not its shipping locks.
Then, in this host:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/operator-actors.json --operator-inspection
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46490 --tries=1 --no-reload
```

Use another available localhost port explicitly if needed. The helper requires local SQLite
service-desk.sqlite in this host, an explicit http://127.0.0.1 port, file sessions, sync queue, array
mail and new private output. Setup requires no existing users or teams. Demo-user seeding is disabled
above for that reason. It creates verified reserved `.invalid` actors with private passwords/tokens;
credentials remain in the mode 600 private JSON file. Read them locally for login and never attach
the file, passwords, cookies, tokens or authentication traces to evidence.

| Actor | Membership and authority |
| --- | --- |
| operator | Owner of Inspection operator team; canonical operator-root manage allow |
| member | Member of that same team; no operator entitlement |
| owner | Owner of another team, without a realm grant |
| outside | Owner of Inspection outside team, without a realm grant |
| site-owner | Owner of another team; canonical site-root manage allow only |

All actors are non-Root. Setup uses existing TeamProvisioner and AccessGrants. Its safe stdout
records actor/team/root IDs, effective entitlement keys and scoped grants, never credentials.
The operator has os.operate, os.enter and operator authoring keys; site-owner has ux.site.author,
not os.operate. An unrelated operator-root view grant and the site manage grant are preservation
controls. The private setup is a CLI teaching fixture, not a product grant-management UI.

## Sign in and inspect a real outside team

Use separate private browser contexts for each actor. Sign in at `/login` using the ordinary email/
password form as operator. Enter `/operator`, then use **People → Teams** in the existing operator
navigation. Open **Inspection outside team** from the list. Inspect its name, owner email,
membership-seat count, personal/shared type and creation timestamp. Teams has no create, edit or
delete controls. Reload the detail to confirm initial HTML/assets and persisted record data work.

The actual Teams response fields are id, name, ownerEmail, memberCount, personal and createdAt.
The outside owner email is `outside@operator-inspection.example.invalid`, memberCount is 1,
personal is false and createdAt is the actual fixture timestamp. The operator team's count is 2.
`memberCount` counts membership seats, not invitations, active users or a named roster.

This is intentionally **operator-wide team metadata**, including owner emails beyond the operator's
own teams. It contains no service-request bodies, passwords, tokens, subscriptions or billing data.
The central connection and SQLite default point to this host's one private database. This view is
not a physical tenant's request store. The [optional PostgreSQL schema branch](service-desk-tenancy.md)
remains a separate API reference with its own bootstrap, storage and cleanup lifecycle.

**Users has a different scope.** Non-Root operators see themselves and shared-team peers; the
actual outside user's detail is 404. The list wire includes permissions and isRoot despite
presentation hints that hide list columns. Do not treat those hints as API redaction, promise a
global customer directory or grant Root to widen it. Users also has policy-controlled editing;
leave that surface's actions outside this read-only Teams walkthrough. The account TeamPage is
self-service membership management, not this operator inspection resource.

## Check the authority and read-only effects

With each control actor signed in separately, GET `/operator/teams`, `/operator/frame/manifest`,
`/frame/resources/teams`, `/frame/resources/teams/records/<outside-team-id>` and
`/frame/resources/teams/schema`. Expect 403 for member, owner, outside and site-owner. The operator
gets 200 and actual data at these paths. Guests redirect to login on the HTML page and receive 401
on JSON manifest/socket reads. Seeing or hiding a menu alone does not establish these gates.

In another terminal in this same fixture:

```bash
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/operator-actors.json --operator-inspection=inspect
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/operator-actors.json --operator-inspection=revoke
```

Re-request the page and direct list/detail/schema as the **same operator**: each must now return 403.
The view grant remains and does not grant os.operate. No cross-tab push is promised; an already
rendered screen is not fresh server authorization. Restore only the same team/root manage ability:

```bash
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/operator-actors.json --operator-inspection=restore
```

Re-request Teams: the operator is admitted again and the outside row is unchanged. AccessGrants
recreates the manage grant with a new row ID; unrelated view/site grants retain their IDs/content.
Do not use a coexisting deny row as revocation: the installed default resolver reads allow/manage
rows. The helper validates the private file's host/database and reserved actor/team/root identities
before changing the exact operator manage scope.

Compare scoped users, teams, memberships, service_requests and unrelated grant rows before/after
inspection. Normal authentication/session timestamps are separate from business writes. Operator
GET navigation/read must not modify business rows; the deliberate manage revoke/restore is the
only grant change. Teams manifest reports creatable=false, editable=false, deletable=false and
showable=true; inspect rendered controls too, without sending write-shaped operator mutations.
Member retains the ordinary shared request list and reads the operator team's request, while outside
cannot read it. Operator privilege supplies no bypass to selected-team request policy.

```bash
XDEBUG_MODE=off herd php artisan test --filter='OperatorInspectionTest|OperatorOsEntitlementTest|OperatorFrameManifestTest|FrameSocketRealmAuthorizationTest|ServiceRequestTeamTest'
XDEBUG_MODE=off herd php vendor/bin/pint --test scripts/service-request-fixture.php
XDEBUG_MODE=off pnpm types:check
XDEBUG_MODE=off pnpm build
```

These focused checks complement actual browser, socket and SQLite observations; they are not a
full-suite claim. Full configured PHPStan retains two unrelated schema-token migration errors.
Stop this listener/browser, release proof capacity and record guarded scratch retention/teardown.
No owner environment, PostgreSQL proof stores, mail delivery, publication or deployment is needed.

Continue with [request approval](service-desk-approval.md) to apply one versioned approval decision
through the existing declared resource action, separately from open/closed status.
