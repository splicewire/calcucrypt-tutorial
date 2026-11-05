<?php

/*
|--------------------------------------------------------------------------
| beam-accounts host overrides (merged over the package defaults)
|--------------------------------------------------------------------------
|
| This starter owns its committed auth schema: UUID users, password resets,
| sessions, passkeys, permissions and personal access tokens. App\Models\User
| uses HasRoles, and DatabaseSeeder provisions demo team roles and realm grants.
|
| The package's full auth migration estate also includes tenant userables,
| guest tokens and sign-offs. This starter does not adopt those tenant tables.
| Keep the host-owned schema without publishing the package's full estate.
*/

use Splicewire\Beam\Accounts\Authorization\RolePermissions;
use Splicewire\Beam\Models\BeamSchema;
use Splicewire\Beam\Models\GitRepo;

return [
    // 'absent' excludes the full package estate; false would claim EVERY member is committed here.
    // BeamAccountsServiceProvider::estateDeclaredAbsent() honours this explicit host choice.
    // PublishGateCoverageAudit still reports overlapping stems with our committed auth tables as
    // advisory evidence. It does not require the unadopted tenant tables to silence that warning.
    'publish_auth_migrations' => 'absent',
    // Teams publishing stays enabled for the package's teams/memberships/invitations estate,
    // which the demo subjects and their team roles use.
    // Committed members live in migrations/shared/, matching the publisher destination;
    // a copy at the migration root would be missed and published a second time.
    'register_migrations' => true,
    // The package mounts routes/account.php (profile edit/update/destroy, security edit, password update)
    // by default. This host registers the same method+URI pairs from routes/settings.php with its own
    // controllers, because its settings pages bind a PageEntryRef entry the package page DTOs expose no
    // slot for — so with the default on, both register and the host's silently win in the route table
    // (ux-demo-convergence inventory §1.5). One capability, one surface: the package default is disabled
    // here until the package can take the host's entry binding. The account API macro is unaffected;
    // routes/web.php mounts it explicitly.
    'register_routes' => false,

    // Who may become a user (purchase-walkthrough M10, BUY-05; owner ruling 2026-10-05, option (a)): registration is
    // CLOSED unless ACCOUNT_REGISTRATION=open. A closed door mounts no route, so POST /register 404s. Invitation claims
    // are their own door. Declared in full because mergeConfigFrom is shallow at this level.
    'doors' => [
        'registration' => env('ACCOUNT_REGISTRATION', 'closed'),
        'oauth' => [
            'providers' => [],
            'create' => 'never',
            'domains' => [],
        ],
        'operator' => true,
    ],

    // Entitlement bundles (Frame OS ADR-0013 §3). The DefaultEntitlementResolver grants a STAFF principal
    // the `staff` bundle below — the operator/OS/authoring capabilities — so /operator + /os resolve OOTB
    // for the seeded staff user. Staff is NOT a flag: there is no `is_staff` column any more (retired,
    // particle-identity-resources ticket 04); the resolver derives it from the realm-grant cascade
    // (ACC-01) — a Team's `manage` grant on a realm's root. Declared in full because mergeConfigFrom is
    // shallow at this level (must restate default_staff_bundle / staff_roles alongside `bundles`). Keep
    // the keys in sync with config/app.php `entitlements` and config/beam/core.php `realm_gates`.
    'entitlements' => [
        'bundles' => [
            'staff' => ['ux.author', 'os.enter', 'os.operate'],
        ],
        'default_staff_bundle' => 'staff',
        'staff_roles' => ['staff', 'operator'],
    ],
    // UX-08c: `GitRepo` and `BeamSchema` reserve their tokens (GrantedExplicitly): a team member read a host's repo
    // roots, dirty paths and schemas through the uniform tiering. This host grants them to its team owner and admin, by
    // name, with the exact abilities the tiering gave them before; a member holds neither. Existing role rows move
    // through the targeted `reserve_git_repo_and_beam_schema_tokens` migration.
    'roles' => [
        'abilities' => RolePermissions::DEFAULT_ABILITIES,
        'grants' => [
            'owner' => [
                GitRepo::class => RolePermissions::DEFAULT_ABILITIES['owner'],
                BeamSchema::class => RolePermissions::DEFAULT_ABILITIES['owner'],
            ],
            'admin' => [
                GitRepo::class => RolePermissions::DEFAULT_ABILITIES['admin'],
                BeamSchema::class => RolePermissions::DEFAULT_ABILITIES['admin'],
            ],
        ],
    ],
];
