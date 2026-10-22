# Inspect existing Theme access with local entitlements

Current authoring compatibility: follow [Theme draft/Release adoption](service-desk-releases.md)
for the selected package pair and fresh/upgrade setup. Theme **Save draft** keeps published tokens
unchanged; explicit **Publish** releases the saved version (or requests review). Earlier slice proof
and candidate identities remain historical. This chapter's resource/access behavior is unchanged.

Continue from the [shared team queue](service-desk-team-requests.md). This chapter uses the current
starter candidate and unchanged Beam UI `ec097daba3c1ace1c482c07467b3eb71bc0ec729`.
It reproduces access to the shipped Theme editor, through a disposable administrative CLI fixture.
It does not save or publish tokens while allowed. Membership policy and feature entitlement are
separate axes: a Member can use their selected team's request queue without Theme authoring access.

## Start a separate fresh host

Use the prerequisites and committed dependency installs in the shared queue chapter, in a **new
clone of the supplied current starter checkpoint**. Keep locks unchanged. Do not reuse its users,
database, .env, host URL or private actor file, and never run these steps in an owner checkout.
For rig proof, claim scratch capacity and register these disposable paths with the existing tools.

```bash
git clone /absolute/path/to/current-candidate /absolute/path/to/new-entitlement-desk
cd /absolute/path/to/new-entitlement-desk
git checkout <current-candidate-commit>
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
node --input-type=module <<'JS'
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const database = resolve('database/entitlement-guide.sqlite');
if (existsSync('.env') || existsSync(database)) throw Error('Use a new fixture');
let env = readFileSync('.env.example', 'utf8');
const values = {
    APP_ENV: 'local', APP_URL: 'http://127.0.0.1:46280', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log', FILESYSTEM_DISK: 'local', LOG_CHANNEL: 'single', ACCOUNT_SEED_DEMO_USERS: 'false',
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
XDEBUG_MODE=off herd php artisan db:seed --class='Splicewire\Beam\Ux\Seeders\ThemeSeeder'
```

This fixture disables the package's optional demo-user seeding with ACCOUNT_SEED_DEMO_USERS=false,
so setup can require empty users/teams before provisioning its private actors.
Theme seeding is explicit: this locked install does not otherwise guarantee the `theme/default`
entry. It seeds this fixture only. No --force or owner configuration is needed.

Follow the [explicit package build/overlay recipe](service-request.md#build-the-explicit-local-ui-candidate)
using a **new Beam clone at ec097da above**, new pinned build tools and this fixture as
SERVICE_DESK_ROOT. The historical resource chapter supplies that build recipe only; keep the
current starter candidate. Then run:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php setup storage/app/private/entitlement-actors.json
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46280 --tries=1 --no-reload
```

The helper refuses nonlocal/non-SQLite hosts, a different database filename/location, remote URLs,
non-file sessions, async queues or non-log mail. Setup refuses an existing state file, users or teams.
It uses existing TeamProvisioner and creates six verified reserved `example.test` actors. Passwords
and bearer tokens remain in the new mode600 private JSON file; inspect them locally for sign-in,
never paste that file, auth headers or raw logs into proof. Registration remains closed.

| Actor | Memberships | Selected team |
| --- | --- | --- |
| owner | A Owner | A |
| admin | A Admin | A |
| member | A Member | A |
| outsider | B Owner | B |
| teamless | none | none |
| aggregate | A Owner, B Member | B |

Setup also creates the canonical `BeamUxEntry::rootFor('site')` and one A/site-root **view** grant
as an unrelated-row preservation control. View alone does not grant authoring. Later helper commands
validate the private file's host/database, canonical root and tutorial A owner/actor identities before
mutating only that team's root manage rows. Safe stdout includes identities, scoped grants and keys;
it does not contain credentials. This is a local fixture helper, not a grant administration API/UI.

## Walk the existing Theme navigation

Use separate private browser contexts for each actor. In the account navigation click **Theme**.
Without a manage grant all six reach the existing styled403 page, “You don't have access to this
page”; use **Dashboard** to recover. Theme navigation remains visible even when access is denied.
Direct `/account/theme` and JSON GET `/beam-ux-entries/<themeId>/body` both return403. The helper's
safe summary supplies themeId. In another terminal in this fixture:

```bash
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php inspect storage/app/private/entitlement-actors.json
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php grant storage/app/private/entitlement-actors.json
```

Grant uses the configured AccessGrant model through `AccessGrants::share($root, $teamA, 'manage')`.
Re-request Theme through its ordinary link. Owner and Admin now see the existing **Theme tokens**
editor and its Save/Discard controls; the body GET is200. Aggregate also gets this access despite
selecting B. Member, outsider and teamless stay403. **Do not press Save**: visibility and body-read
access are the outcome here, not successful token editing or publication.

| Phase | A Owner/Admin and aggregate: page/body | A Member, B Owner, teamless: page/body |
| --- | --- | --- |
| No manage grant | 403 | 403 |
| A/site manage allow | 200 | 403 |
| Allow plus matching deny row | 200 (installed-default limit) | 403 |
| Revoke matching manage rows | 403 | 403 |

The safe keys for eligible actors after grant are `ux.site.author` and `ux.author`. Neither
`os.enter` nor `os.operate` is acquired. Basic service-request list/create remains200/201 for valid
selected-team members,403 for teamless, in every phase. Create one request as owner and one as
outsider using their Service requests navigation. Aggregate sees B's queue and can read B's row;
A's row returns404 on `/api/service-requests/<id>`. This distinguishes the two authority axes without
adding a team switcher. The request API example in the preceding chapter documents its JSON shape.

For the bounded deny diagnostic, while the allow row exists:

```bash
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php deny-row storage/app/private/entitlement-actors.json
```

Re-request the page/body: eligible actors still get200. The **installed default** reads allow/manage
rows and does not subtract coexisting deny rows, even though the generic composer supports deny
algebra. Do not teach deny as revocation here. Remove the tutorial grant with:

```bash
XDEBUG_MODE=off herd php scripts/service-desk-entitlements.php revoke storage/app/private/entitlement-actors.json
```

This calls `AccessGrants::revoke($root, $teamA, 'manage')`, deleting the matching allow and diagnostic
deny rows. The safe summary should retain the original view grant ID and no manage rows. No grant
for another ability, team or root is removed. Re-request Theme and body: all actors are403 again.
An external CLI mutation does not broadcast to an open tab; navigation/re-request is the explicit
boundary. No live cross-tab cache invalidation is promised. An old editor's visibility is not write
authority: its next request must pass the existing server gate.

## Verify the rejected write without publishing

Before revoke, retain an allowed body GET response privately. After revoke, POST
`/beam-ux-entries/<themeId>/save-body` with the same valid `{"body": <body object>}` shape and a changed
`site.accent` value. Use the signed-in browser's normal cookie/CSRF request context. Expect403, not a
validation error; do not send this write while granted. Independently read the actual body through
`EntryBodyEnvelope::read($theme)->body` and compare the ordered `beam_versions` rows before/after.
Both must be unchanged. A raw entry row alone does not establish unchanged versioned body content.
The focused regression below exercises precisely this no-write effect with an independent test DB.

```bash
XDEBUG_MODE=off herd php artisan test --filter='EntitlementGuideTest|ThemeEditorSeatTest|RealmAuthoringGatesTest|ServiceRequestTeamTest'
XDEBUG_MODE=off herd php vendor/bin/pint --test scripts/service-desk-entitlements.php tests/Feature/EntitlementGuideTest.php
pnpm types:check
XDEBUG_MODE=off pnpm build
```

Tests cover eligible-A/selected-B separation and valid-shaped denied write persistence, alongside
existing Theme/realm/request gates. They do not establish full suite health. Full configured PHPStan
still reports the two known unrelated errors in the existing schema-token reservation migration.
Stop your listener/browser, release capacity and use guarded scratch retention/teardown for this
host, private actors and captures. Do not remove owner state or publish this fixture.

## What this resolver means

The principal is the authenticated **User**. Installed DefaultEntitlementResolver inspects **all**
Owner/Admin team memberships, then their allow/manage realm grants, and composes keys with an empty
baseline. It does not resolve a selected team's subscription or plan. Member is ineligible for this
feature even when allowed to use their team's requests. Aggregate's A entitlement and selected-B
request isolation are therefore both intentional.

The host Theme is coarse, host-global branding behind `ux.author`, not team-private branding or
per-token authorization. Use the canonical site root in this recipe; the installed realm adapter
maps granted entry IDs to realms and does not itself guarantee root-only grants. These boundaries
are not a new policy or entitlement-key design. The account shell's **Free** chip is static demo
text, not evidence of a billing subscription. This host has no installed PHP commerce/tenancy
adoption from following this guide.

For the installed sources, read [DefaultEntitlementResolver](../../vendor/splicewire/laravel-beam-accounts/src/Entitlements/DefaultEntitlementResolver.php),
[EntitlementComposer](../../vendor/splicewire/laravel-beam-accounts/src/Entitlements/EntitlementComposer.php),
[AccessGrants](../../vendor/splicewire/laravel-beam-accounts/src/Sharing/AccessGrants.php) and
[Role](../../vendor/splicewire/laravel-beam-accounts/src/Enums/Role.php). The generic contract is
[EntitlementResolver](../../vendor/rushing/laravel-permission-cascade/src/Contracts/EntitlementResolver.php).
`AppServiceProvider` aliases ux.author to entitlement:ux.author. The existing account route and
beam-ux EntryBodyShowOp/EntryBodySaveOp enforce it; `HandleInertiaRequests` exposes resolved UI props.
The packaged ThemeEditor consumes these body operations. These source seams, rather than historical
staff/plan examples, determine this locked host's behavior.

Continue with [resource content branding](service-desk-branding.md) to save a token through the
existing editor and inspect its local publication and scoped light/dark resource effect.

For a separate read-only operator journey, follow
[existing Operator Teams inspection](service-desk-operator-inspection.md). It uses explicit operator
authority in a new private host.
