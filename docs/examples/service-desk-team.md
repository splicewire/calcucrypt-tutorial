# Create a team and invite a local teammate

> Historical checkpoint: this chapter and its commands describe starter
> `35cbc40e6ca6bea295ba7cad36cd0a0d3e1624bb` with Beam UI
> `0991bf37cb66ed948a863b86050e3dc1890e61d6`. Check out that starter commit for the original
> creator-only behavior and client. For current team-shared requests, follow the explicit
> [shared queue continuation](service-desk-team-requests.md); latest source requires team membership.

This continuation uses the shipped team form, roster and signed invitation flow. Start from a new
clone of the exact starter candidate and the accepted Beam UI candidate supplied for this checkpoint
(`0991bf37cb66ed948a863b86050e3dc1890e61d6`). Follow [fresh setup and the explicit UI overlay](service-request.md)
through generation and `pnpm build`. Keep both committed locks. Use a new database, file sessions,
and an available strict localhost port; this guide uses `http://127.0.0.1:46210`.

## Prepare private local accounts and capture

Before starting your own server, change only this disposable fixture's environment:

```bash
node --input-type=module <<'JS'
import { readFileSync, writeFileSync } from 'node:fs';
let env = readFileSync('.env', 'utf8');
for (const [key, value] of Object.entries({ MAIL_MAILER: 'log', LOG_CHANNEL: 'single', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync' })) {
    const pattern = new RegExp(`^${key}=.*$`, 'm');
    env = pattern.test(env) ? env.replace(pattern, `${key}=${value}`) : `${env}\n${key}=${value}\n`;
}
writeFileSync('.env', env, { mode: 0o600 });
JS
XDEBUG_MODE=off herd php artisan config:clear
XDEBUG_MODE=off herd php artisan splicewire:beam:ux:seed-nav
XDEBUG_MODE=off herd php scripts/service-request-fixture.php storage/app/private/team-actors.json --team
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port=46210 --tries=1 --no-reload
```

`--team` creates two existing, verified local accounts at reserved `example.test` addresses, with
random passwords and tokens in the new private JSON file. It uses the same local SQLite safety
checks as the resource fixture. Read their logins locally; keep that file, browser state and capture
private. Registration stays closed. No email leaves the process: `log` writes the actual notification
to this fixture's `storage/logs/laravel.log`; `sync` runs it locally. Do not substitute a delivery transport.
Changing the environment requires restarting your own server. Refuse a busy port rather than moving
silently or stopping someone else's listener.

## Follow the visible journey

1. Open `/login` and sign in with the private `owner` login. From Dashboard click **Create a team**
   in the account navigation. This link persists for teamless and joined users. The existing Team
   page can still report “No active team” before creation; the new link remains available.
2. Name the team “Service desk workshop” and click **Create team**. The roster opens and shows the
   creating account as Owner. Creation selects this team as the actor's current team.
3. Click **Invite member**, enter the private `other` account's email and explicitly choose **Member**
   (the role selector initially offers Admin). Send once. The roster shows a pending invitation.
4. Open this fixture's log locally and follow the actual invitation URL in a separate private browser
   context. The notification contains text and HTML versions; use its full `/invitations/...` link,
   including `expires` and `signature`. If viewing raw quoted-printable mail, remove soft line breaks
   (`=` at line end), decode `=3D` to `=` and HTML `&amp;` to `&`. Never reconstruct a link from a database
   token or copy the signed URL into evidence. Protect the log with `chmod 600 storage/logs/laravel.log`.
5. The guest invitation panel offers **Log in**. Sign in as `other`; the ready panel returns. Click
   **Accept invitation**, reach Dashboard, then click **Team**. Both accounts see the two-person roster.
   Reload the owner's roster: pending is gone. The teammate has Member authority and no invite or
   role-management controls. The owner can manage membership; Admin can invite but cannot perform
   owner-only role changes.

The account team client uses same-origin session cookies and CSRF against `/beam/accounts`; this
journey does not claim generated hooks power these screens. The captured link is signed and expires
(default seven days). An account with a different email cannot accept it. A used link reports its
used state and cannot add another membership. Invite existing local accounts here; new-account signup
is outside this checkpoint.

## Check the effect and boundary

Inspect only this fixture's SQLite database: `beam_teams`, `beam_memberships`, `beam_invitations` and
`users`. The named team is non-personal, the two memberships have Owner/Member roles, both users'
`current_team_id` matches it, and the invitation has `accepted_at`. Do not export tokens, passwords,
raw mail or signed links. A refused invite/role mutation must leave these rows unchanged.

An anonymous JSON POST to `/teams/create` returns 401; the browser form redirects to login. Member
POST `/beam/accounts/invitations` and PUT `/beam/accounts/members/{member-user-id}/role` return 403.
A wrong-account JSON POST to `/invitations/{token}/redeem` returns 403; repeating it after acceptance
returns 410. Use session CSRF for direct browser requests. No denial should create another membership.

A role belongs to membership in one team; `current_team_id` selects the active team. This host has
no browser team switcher at this checkpoint. Creation and invitation redemption select a team, but
default registration does not provision a team-of-one. Team membership does not create a physical
tenant/database or share ServiceRequest rows: those remain scoped to the authenticated user. Create
an owner's service request and verify the joined Member still cannot list/read it.

Run focused checks on the fresh candidate fixture:

```bash
XDEBUG_MODE=off herd php artisan test --filter='AccountNavIaInvariantsTest|TeamOnboardingTest|ServiceRequestJourneyTest|ServiceRequestFrameTest'
pnpm build
```

These tests use their own in-memory database. Full-suite/static-analysis health is a separate claim;
the known unrelated migration PHPStan findings are not repaired here. Stop only your server, retain
review evidence separately, and retire private fixtures through the existing scratch lifecycle.
