# Continue with actual tenant storage

The shared service-desk checkpoint isolates rows by the selected team and live membership.
It does not initialize a separate schema or database for that team.

For the next optional learning branch, use the Tower starter's
[two-tenant service-request reference](https://github.com/splicewire/laravel-tower-starter/tree/fbda3ede8da365487a8f84b1ee58fa36b0016321/examples/service-desk-tenancy).
This is a local review checkpoint; remote publication is deferred. In a workspace checkout,
the same guide is `~/Workspaces/laravel/starters/laravel-tower-starter/examples/service-desk-tenancy/README.md`
after checking out that candidate, or the exact isolated worktree supplied with the handoff.

It runs a fresh Tower API fixture with central credentials, two PostgreSQL tenant schemas,
the existing `/w/{tenant}/api/v1` boundary and the same declared title/status/id resource pattern.
The guide proves create/list/show with generated types/routes, cross-store absence, refused membership
and request cleanup. A dual-seated actor sees distinct rows even at the same local id.

Use a newly allocated local `test_*` PostgreSQL database and the fixture's separate bootstrap.
This branch does not move this application's accounts, team memberships or accepted request rows.
Schema name resolution is distinct from RLS or tenant-specific database credentials. The API example
makes no operator-UI or same-SPA tenant-switching claim; those are separate increments.
