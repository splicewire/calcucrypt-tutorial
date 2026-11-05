<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resource discovery
    |--------------------------------------------------------------------------
    | Beam's boot-time resource discovery scans these paths for Data classes
    | carrying `#[ParticleResource]` and reflects each into the admin manifest —
    | no per-resource `registerClass()` in a provider. Dropping ONE annotated
    | Data class under a scanned path IS the wiring: it shows up in Frame's
    | `/frame/manifest` and gets its editor route with no further code.
    |
    | `resources` is the explicit class-string list (always honoured, cheap);
    | `discover_paths` is the filesystem scan (dev convenience). The unified
    | `#[ParticleResource]` attribute carries the full manifest field set
    | (label / model / nav placement / route identity), so the Data class is the
    | single source of the declaration.
    */

    // Empty on purpose. BeamUxEntryData used to be listed here because discover_paths' app_path('Data')
    // scan (this host's own app/Data) cannot reach a PACKAGE class — but laravel-beam-ux now registers
    // its own declarations from its provider (ADR-0214 §5), which runs after this list and takes the key
    // under OnKeyDuplicate::Supersede. The line registered first, was displaced by the identical class, and
    // did nothing. registry-kernel 68.
    'resources' => [],

    'discover_paths' => [
        app_path('Data'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Realm membership — which resources this host places in which realm
    |--------------------------------------------------------------------------
    | A HOST-SIDE LIST, spelled out, and deliberately so: `api-surface-coherence`
    | 142 rules membership a host concern and 141 ("SPELL IT OUT") retired
    | auto-mount as vocabulary. No package writes this and no discovery fills it.
    |
    | It is what turns the manifest's `nav` / `routeContext` block from a shape
    | into a router table. `splicewire/laravel-beam-ux` supplies the PROJECTION
    | over this list — the list leaf per resource, the per-record twin the
    | declaration allows, the shell/href join — through frame's own
    | `FrameNavContributor` plug, so this host emits a manifest-generated router
    | with NO controller of its own. Before this list existed the projection was
    | present and had nothing to project: `routeContext` came back empty, which
    | is the honest reading, not a bug in it.
    |
    | Split on who the surface is FOR, which is the only question membership
    | answers here. Platform identity (`users`, `teams`) is the operator's;
    | everything a workspace edits about its own site is the tenant's. A host
    | that disagrees edits this list — that is the point of it being here.
    |
    | `beam-ux-mirror-status` and `beam-ux-sitemap-health` are read-only
    | diagnostics and ride the tenant realm beside the content they report on.
    */

    /*
    |--------------------------------------------------------------------------
    | Middleware — the gate on Frame's whole server surface
    |--------------------------------------------------------------------------
    |
    | Frame mounts its manifest AND its generic resource CRUD socket under this
    | stack (`schemastud/laravel-frame/routes/frame.php:15`), and its own docblock
    | says a host "gates it by setting `frame.middleware`". This host never set the
    | key, so it fell back to the package default `['web']` — which is CSRF and a
    | session, not authorization.
    |
    | ⚠️ Measured 2026-09-05, before this line existed:
    |   GET  /frame/manifest          -> 200 anonymously, disclosing every nav seat,
    |                                    its children and 12 resource definitions
    |   GET  /frame/resources/tokens  -> 200 anonymously
    |   POST /frame/resources/{r}     -> reachable by ANY authenticated session;
    |                                    a member-tier user got 422 (validation ran),
    |                                    not 403 — `FrameResourceController` contains
    |                                    zero `authorize`/`Gate::` calls by design,
    |                                    because the gate is this line.
    |
    | The flagship already spells this out: all 32 of its frame routes carry
    | `Authenticate:sanctum` and ZERO are ungated. This is the same act, and it is
    | host-side because ticket 141 ruled that middleware is part of an EXPOSURE and
    | "the route file owns it" — a package defaulting to auth would be guessing at a
    | host's public surface.
    |
    | Note this gates the MANIFEST too, deliberately: it describes admin surfaces and
    | has no business answering an anonymous caller. The public site pages ride
    | `Route::beamUxSite()`, not this stack, and are unaffected.
    |
    */

    'middleware' => ['web', 'auth'],

    'realms' => [
        'operator' => [
            'users',
            'teams',
        ],

        /*
        | ⚠️ `members`, `invitations` and `tokens` are DELIBERATELY ABSENT, and their absence is a
        | composition decision rather than an omission.
        |
        | All three are beam-accounts resources declaring `group: 'Settings'`. They were listed here
        | and measured on 2026-09-11 to be routable and completely unreachable: `FrameNavContribution`
        | projects only the sections a package SEATS (`NavSection`), beam-ux seats `authoring` and
        | `ops`, and beam-accounts deliberately seats none — its own `tests/NavSectionTest.php` records
        | that decision and names the two honest futures. `group:` is a label in Frame's resource index;
        | `section:` is the nav join key. Nothing joined, so nothing rendered, and `/tokens`,
        | `/members` and `/invitations` answered only to a typed URL.
        |
        | Seating them here would not have been enough either. Frame's generic console cannot serve
        | what those two surfaces need:
        |   - `tokens` is `readOnly: true` BY DECLARATION (the reveal-once mint is a host escape hatch
        |     its own docblock names), so its list renders no create control at all — correctly;
        |   - `invitations` declares `showable: false, editable: false`, so its route context carries
        |     no `/:id` twin; the console's list override passes `onOpen` only when a twin exists, and
        |     `ListShell` derives the toolbar's `onNew` from that same `onOpen` — so "New invitation"
        |     rendered with `onClick={undefined}` and was a dead control.
        |
        | Both capabilities now live in the ACCOUNT realm, where this host's navigation actually is:
        | `/account/tokens` and `/account/team` (routes/web.php), rendering `@splicewire/beam-accounts`'
        | own <TokensRoster> and <TeamPage> against the package's REST surface, with nav seats in
        | `resources/beam-ux/nav.yml`. Listing them here as well would give one capability two
        | surfaces — one working, one half-working — which is the drift this list exists to prevent.
        |
        | The Frame resource socket is unaffected and still serves all three: realm membership drives
        | the manifest's nav/routeContext projection, not `frame/resources/{resource}`.
        */
        'tenant' => [
            'service-requests',
            'external-requests',
            'service-request-usage',
            'beam-ux-release-files',
            'beam-ux-entry',
            'sitemap',
            'schemas',
            'hooks',
            'git-repo',
            'beam-ux-sitemap-health',
            'beam-ux-mirror-status',
        ],
    ],

];
