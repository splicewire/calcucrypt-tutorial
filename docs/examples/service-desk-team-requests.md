# Continue to a shared team queue

For the current **one-host, one-request** trail, start with [the cumulative preview](service-desk-current-preview.md).
The setup/checkpoint commands below retain their own historical inputs; do not concatenate their fresh fixtures.

The current starter changes ServiceRequest authority from creator-only records to a shared queue
for the actor's selected team. Owner, Admin and Member can create and read that team's requests.
A different team's members cannot read them. Teamless or removed members cannot list/create.
The creator remains recorded in user_id; creator identity alone grants no read access.

The original [resource](service-request.md) and [invitation](service-desk-team.md) chapters remain
executable at starter `35cbc40e6ca6bea295ba7cad36cd0a0d3e1624bb` plus Beam UI `0991bf37cb66ed948a863b86050e3dc1890e61d6`.
Their creator-only assertions and original client apply to that checkpoint. Latest source uses this
chapter and the evolved `pnpm example:service-request` client. This is single-database membership
isolation, not physical tenancy, tenant domains/databases, a team switcher or a new identity banner.

## Fresh setup at the current candidate

Use Herd PHP, Composer 2, Node 22.13+, pnpm 9.15.3 and access to locked packages. Start with a new,
isolated clone of the current candidate supplied for review, never an owner checkout or database.
Keep the committed locks. For rig proof use existing scratch ownership/capacity and guarded teardown.

```bash
git clone /absolute/path/to/current-candidate /absolute/path/to/new-shared-desk
cd /absolute/path/to/new-shared-desk
git checkout <current-candidate-commit>
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
pnpm install --frozen-lockfile
node --input-type=module <<'JS'
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const database = resolve('database/service-desk.sqlite');
if (existsSync('.env') || existsSync(database)) throw Error('Use a new fixture');
let env = readFileSync('.env.example', 'utf8');
const values = {
    APP_ENV: 'local', APP_URL: 'http://127.0.0.1:46210', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log', FILESYSTEM_DISK: 'local', LOG_CHANNEL: 'single',
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

The installer migrates/seeds only this new SQLite file. Do not use --force or copy an owner .env.
For the browser, use the explicit Beam UI candidate
`ec097daba3c1ace1c482c07467b3eb71bc0ec729`. Follow only the
[isolated package build/overlay recipe](service-request.md#build-the-explicit-local-ui-candidate)
with a fresh Beam clone and pinned build tools; use this chapter's **current starter** as
SERVICE_DESK_ROOT. Use the Beam checkpoint above, which adds successful team-transition cache resets to the earlier
UI. That build recipe is shared with the historical chapter; it does not require
checking the starter back to the old private-row commit. Then regenerate and build this fixture:

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off pnpm build
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
```

## Join and use the shared queue in a browser

```bash
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/team-actors.json --team
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46210 --tries=1 --no-reload
```

`--team` creates four existing verified local actors, owner/other/outsider/teamless, using reserved
`example.test` addresses. It provisions no teams. Passwords/tokens remain in that new private file.
Read logins locally, never paste them or signed URLs into evidence. Mail is captured only in this
fixture's log; no external delivery transport is used. Registration stays closed.

1. Sign in as owner, click **Create a team** in account navigation and create “Service desk A”.
   On Team invite the existing `other` account, explicitly choosing **Member** (the first role is Admin).
2. Protect `storage/logs/laravel.log` with chmod600, inspect it locally, and open its actual complete
   signed `/invitations/...?...` URL in a separate private browser context. Decode quoted-printable
   soft line breaks, `=3D` and HTML `&amp;` if reading raw mail. Use the notification's link, not one
   reconstructed from storage. The guest chooses Log in, signs in as other, sees ready, then Accept
   invitation. Both rosters show Owner/Member; creation and redemption select team A.
3. As owner click Service requests, New service request, enter “Repair team printer”, Save. The generic
   console returns to its list. As other click Service requests and see that same open request;
   open/reload its read-only detail. Create “Member reports network outage”. Owner reloads and sees it.
   There are no team/creator input fields, record edit or delete controls.
4. In another context sign in as outsider and use Create a team for “Service desk B”. Its request list
   starts empty; an A record URL returns the generic unavailable state without its values. Create a B
   request: owner/other still see only A. A role on B grants no authority over A.
5. Sign in as teamless. The service-request list/create navigation is absent. The persistent account
   **Create a team** path remains usable. Direct JSON list/create calls are 403. Direct resource console
   renders the stock “No surface here” state (HTTP200, no form or Save), with the same account
   recovery link; this chapter does not add
   a custom empty screen. Use Create a team to recover rather than assuming a team was provisioned.

Team authority is a fresh query through the explicit actor's memberships and current_team_id. A
cached currentTeam relation, leftover role or arbitrary pointer is insufficient. Removing membership
revokes the next read/write even if that pointer remains. No automatic personal-team fallback or
browser team switcher is supplied. Team role management remains owner-only; this resource change
does not widen account administration.

## Contract and migration

`App/Support/ServiceRequestTeam` validates the selected team. The ordinary ServiceRequest policy
requires it for viewAny/create and matches record team for detail. Data scope selects only that team;
prepare assigns team_id and authenticated creator user_id. Input/MapsToModelAttributes and model
fillable stay title/status only. Forged user_id/userId/team_id/teamId cannot choose either authority.
REST and Frame consume these same existing seams with no new backing, controller or package upgrade.

The additive migration leaves all pre-existing request values unchanged and adds nullable team_id
with an indexed FK to beam_teams and restricted team deletion. Historical private rows stay null and
are excluded even for their creator. **There is no automatic transfer or backfill** based on the
creator's current team. Do not migrate a live site for this tutorial. Test the transition on a fresh
disposable copy or new fixture; transferring old private requests requires a separate explicit decision.
Creator deletion retains the pre-existing user FK behavior; no account-deletion journey is added.

The packaged create-team and invitation-accept adapters reset affected Frame manifest, record/list
and facet caches only after successful transitions. This prevents the prior teamless manifest from
hiding newly reachable resources in the same browser session. Failure preserves cached state;
unrelated queries remain intact. No manual refresh is part of the ordinary create/join journey.

The read response remains id/title/status, and the generated request schema still exposes only
title/status. Run the actual generator and inspect its ran/skipped/failed summary. Generated response
types are consumed by the Node driver; mutation-body typing remains unknown and server DTO validation
is authoritative. No response-runtime validation or generated React-hook execution is claimed.

## Run the latest API example separately

Use another new fixture/database at this same candidate and the setup above. Its API-only journey
needs generated assets but no browser build/overlay. Do not reuse the browser's actor file or reserved
accounts. `--shared` creates four private tutorial actors and uses the existing provisioner to seat
Owner/Member on A and outsider Owner on B; teamless stays unseated. It replaces the interactive joining
step only for this API fixture and makes no invitation-mail proof claim.

```bash
XDEBUG_MODE=off herd php artisan splicewire:beam:generate:assets --json
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/shared-api.json --shared
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46210 --tries=1 --no-reload
# In another terminal in this fixture:
SERVICE_REQUEST_BASE_URL=http://127.0.0.1:46210 \
SERVICE_REQUEST_FIXTURE="$PWD/storage/app/private/shared-api.json" \
pnpm example:service-request
```

The driver proves Owner/Member sharing, separate-team exclusion, teamless admission refusal, guest401,
invalid422 without an extra row, forged ownership and B's independent create. REST create returns201;
the browser's cookie/CSRF Frame create returns200. Unsupported REST update/delete remain405 and
Frame writes403. Other-team detail is404. Invalid selection list/create is403; detail is404 on REST
or earlier admission403 on Frame. These are deliberate refusal boundaries, not a physical-tenancy claim.

Inspect this fixture's service_requests team_id/user_id against beam_memberships and users.current_team_id;
never export passwords/tokens/raw mail. Run focused checks:

```bash
XDEBUG_MODE=off herd php artisan test --filter='ServiceRequestTeamTest|ServiceRequestJourneyTest|ServiceRequestFrameTest|AccountNavIaInvariantsTest|TeamOnboardingTest|TypeScriptGenerationTest'
pnpm exec tsc --project tsconfig.service-request.json
XDEBUG_MODE=off pnpm build
```

Tests use an independent in-memory database, including migration round-trip/private-row preservation
and live membership removal. Full suite health is not implied; the two known unrelated migration
PHPStan errors remain disclosed. Stop only your server, release capacity and use existing guarded
scratch retention/teardown for private actors, capture and fixtures.

Continue with [existing Theme entitlement access](service-desk-entitlements.md) to distinguish
selected-team queue membership from User-level feature reach, using a separate fresh local fixture.

## Continue with tenant storage

Team-scoped rows are the boundary proved here. The optional [two-tenant storage branch](service-desk-tenancy.md)
uses a separate Tower fixture to teach PostgreSQL schema isolation and request cleanup.

For a separate read-only operator journey, follow
[existing Operator Teams inspection](service-desk-operator-inspection.md). It uses explicit operator
authority in a new private host.

Continue with [request approval](service-desk-approval.md) to apply one versioned approval decision
through the existing declared resource action, separately from open/closed status.
