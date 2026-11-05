<?php

use App\Data\Pages\FrameConsolePageData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Splicewire\Beam\Authorization\SeatGate;
use Splicewire\Beam\Dashboard\RealmDashboard;
use Splicewire\Beam\Ux\Data\Pages\EntryPageData;
use Splicewire\Beam\Ux\Head\PageHead;
use Splicewire\Beam\Ux\Head\PageHeadComposer;
use Splicewire\Beam\Ux\Http\Middleware\ResolvePreviewContext;
use Splicewire\Beam\Ux\Http\PublicEntryGate;
use Splicewire\Beam\Ux\Intake\IntakeForms;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Preview\PreviewBar;
use Splicewire\Beam\Ux\Preview\PreviewContext;
use Splicewire\Beam\Ux\Support\PageEntryRef;

require __DIR__.'/local-marketplace.php';
require __DIR__.'/team-wallet.php';
require __DIR__.'/canonical-mdx.php';
require __DIR__.'/calcucrypt.php';

// The FRONT DOOR is the OOTB site realm: `/` renders the promoted <SiteLayout> chrome (public) AND —
// behind the ux.author seam — mounts the in-place visual editor (@/editor). (frontend-surfaces wiring.)
//
// It shares `entry` ({id, slug}) so the page addresses its beam-ux row by ID (ADR-0214 §2). The server
// is the only party that can: no compile-time frontend map can carry a per-database uuid.
//
// ADR-0234 d2: the Page Head composed from the same row rides along, and the root template prints it before any script.
//
// EP-07d: its published forms get their schemas and the door's honeypot field, as a packaged entry page's do.
//
// ADR-0232 d15 (EP-18): `/` renders inside a Preview like every packaged page. The Preview middleware verifies the
// cookie; the entry ref then addresses the Preview's version of the body, the bar rides along, and a home the
// Preview takes down answers the uniform 404. Outside a Preview nothing here changes.
Route::get('/', function (Request $request) {
    abort_if(PageEntryRef::hiddenByPreview('home'), 404);
    $entry = PageEntryRef::for('home');
    $model = $entry === null ? null : BeamUxEntry::find($entry['id']);
    $preview = app(PreviewBar::class)->for($request, $model);
    $head = PageHead::forPage('home', $request);
    $context = $request->attributes->get('beam.ux.preview');
    if ($model !== null && $context instanceof PreviewContext) {
        // A preview is an immutable view of the Release's entries and ancestry. Never fall
        // back to live ancestry here: it can differ from the version the guest is previewing.
        $chain = app(PublicEntryGate::class)->chainForEntry($model, 'site', null, $context);
        $head = $chain === null
            ? []
            : app(PageHeadComposer::class)
                ->compose(
                    $chain[array_key_last($chain)],
                    $request->path(),
                    false,
                    $request,
                    $chain,
                )
                ->elements();
    }

    return Inertia::render('site/home', EntryPageData::from(['entry' => $entry]))
        ->with($model === null ? [] : app(IntakeForms::class)->pagePropsFor($model))
        ->with('head', $head)
        ->with($preview === null ? [] : ['preview' => $preview]);
})->middleware(ResolvePreviewContext::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Splicewire\Beam\Facades\Particle::ops('service-requests', 'service-requests', [App\Particle\Operations\ApproveServiceRequest::class, App\Particle\Operations\ReadRequestApprovalHistory::class]);

    Route::get('service-requests/{id}/inspection', App\Http\Controllers\RequestApprovalInspectionController::class)
        ->whereNumber('id')->name('service-requests.inspection');

    // The host owns auth + verified; the packaged operations each declare ux.author.
    Splicewire\Beam\Ux\Support\EditorRoutes::mount();

    // The authed home IS the OOTB account realm, and it is the TENANT realm's dashboard leaf. Fortify
    // redirects login here (`config/fortify.php` home => /dashboard).
    //
    // `splicewire/laravel-beam-ux` registers one read-only `{realm}-dashboard` resource per realm and
    // mounts its list leaf at `/{realmBase}/dashboard` (realm-dashboards ticket 04) — for the tenant
    // realm, whose base is `/`, that is this path. The page is the same packaged frame console the
    // `{frameRoute}` catch-all below serves for every other tenant leaf, rendered with the same
    // (absent) props so the client reads one realm context and one manifest cache entry for the whole
    // realm; the console's client router matches `dashboard` and mounts the list shell, whose rows are
    // the realm's summary cards and jump-to tiles (`GET /frame/resources/tenant-dashboard`). Declared
    // here rather than left to the catch-all so the route keeps its name: Fortify, the tests and the
    // seeded account rail all address it as `dashboard`.
    //
    // This replaces the packaged `account/home` placeholder, and with it the `entry` share that page
    // carried: a frame list is not an authored page, so there is no chrome for the visual editor to
    // address. The `/` and `/account/theme` routes keep theirs.
    Route::get('dashboard', fn () => Inertia::render('frame/console'))->name('dashboard')->defaults(SeatGate::OPEN_TO_MEMBERS, true);

    // ── The ACCOUNT-REALM settings surfaces: API tokens and Team ─────────────────────────────────
    //
    // Two more `account/*` pages, so app.tsx's layout resolution frames them in <AccountShell>, and
    // their nav seats come from `resources/beam-ux/nav.yml`'s `account` realm, seeded into the account
    // sitemap. (`/dashboard` no longer shares that shell: since de4f17b it renders `frame/console`
    // under AppLayout, whose sidebar lists the same account seats.) That is the
    // whole reason these are account-realm pages rather than `settings/*` ones: the packaged
    // SettingsLayout's sub-nav (Profile / Security / Appearance) is a hardcoded list inside
    // `@splicewire/beam-inertia`, so a `settings/tokens` page would have been reachable only by URL,
    // which is the defect this change exists to fix.
    //
    // The page bodies are `@splicewire/beam-accounts`' own <TokensRoster> and <TeamPage>, wired to
    // the transport below. Nothing about either surface is re-implemented here — see
    // `resources/js/pages/account/{tokens,team}.tsx`, which are transport adapters and nothing else.
    //
    // ⚠️ These are NOT the tenant frame console's `tokens` / `members` / `invitations` leaves, and
    // those three have been removed from `config('frame.realms')['tenant']` in the same change. Read
    // that file's realm comment for why one capability may not have two surfaces here.
    Route::get('account/tokens', fn () => Inertia::render('account/tokens'))->name('account.tokens')->defaults(SeatGate::OPEN_TO_MEMBERS, true);
    Route::get('account/team', fn () => Inertia::render('account/team'))->name('account.team')->defaults(SeatGate::OPEN_TO_MEMBERS, true);

    // ── The THEME entry's editor seat (G2-BEAM-THEME-NAV) ────────────────────────────────────────
    //
    // A third account-realm page, for the same reason the two above are account-realm pages: its nav
    // seat comes from `resources/beam-ux/nav.yml`'s `account` realm and `<AccountShell>` renders that
    // rail. Before this row, changing a theme token meant hand-editing `ThemeSeeder` and reseeding —
    // the theme entry is `type = theme`, and `UxType`'s own doctrine keeps a theme body out of the
    // visual canvas (its body is TOKENS, not a composable tree), so the editor dock had no page to
    // attach to and no surface anywhere mounted the row.
    //
    // ⚠️ **Not a bespoke theme UI, deliberately.** `theme-entries-and-authoring`'s PRD closed Part A §5
    // with the ruling that the theme editor is Frame's form on the `ParticleResource` — so the page
    // body is `@splicewire/beam-ux`'s own `<ThemeEditor>`, which is `useEntryBody` →
    // `RegionInspector`'s `form` body (the REAL `@schemastud/seam` SchemaForm) → Save draft and
    // explicit Publish through the SAME entry operations and fixed-version Release service mounted above. The fields are generated from the
    // server's schema (`EntryBodyEnvelope::schemaFor()` answers a theme entry with `ThemeSchemas`'
    // `{canvas, site}`), so adding a token in PHP adds a field here with no frontend change.
    //
    // It shares `entry` for the same reason `/` and `/dashboard` do, and it is the ONLY way this page
    // can work: the theme row's id is a per-database uuid, so only the server can say which row the
    // page means (`PageEntryRef::theme()`).
    //
    // **Gated on `can:ux.author`.** The `body`/`save-body` operations declare `ability: 'ux.author'`
    // and refuse a guest or a member on their own — that is the lock. This is the door: a screen whose
    // every read and write a principal would be refused should not be offered to them as a page.
    Route::get('account/theme', fn () => Inertia::render('account/theme', EntryPageData::from([
        'entry' => PageEntryRef::theme(),
    ])))->middleware('can:ux.author')->name('account.theme');

    // The account-tier REST survivors the two pages above talk to — tokens (reveal-once mint +
    // archive/rotate/renew), members (role change + remove) and invitations (send/resend/revoke),
    // all shipped by `splicewire/laravel-beam-accounts`.
    //
    // ⚠️ **A macro the HOST calls; the package mounts nothing.** api-surface-coherence 141 ruled that
    // middleware is part of an exposure and "the route file owns it", and these verbs mint bearer
    // credentials and change who can reach a team. It is also what keeps the package from deciding,
    // by provider order, which `beam.accounts.tokens.index` a host that already mounts its own
    // (`~/Herd/splicewire-app`) actually serves.
    //
    // Called INSIDE this group, so the surface inherits `auth` + `verified` from it; the macro's own
    // middleware default is dropped by passing an explicit empty list rather than being applied
    // twice.
    Route::splicewireAccountApiRoutes(middleware: []);

    // The OPERATOR realm's front door (frontend-surfaces.md). It lands on the realm's DASHBOARD LEAF —
    // `/operator/dashboard`, the `operator-dashboard` read-only resource `splicewire/laravel-beam-ux`
    // registers for every realm (realm-dashboards ticket 04) and the operator frame console below
    // serves: a frame list whose rows are the realm's summary cards (live, actor-scoped counts of the
    // resources the rail seats) and its jump-to tiles (the rail itself, drawn as tiles).
    //
    // This replaces the hand-rolled landing: three counts computed inline here (`OperatorStatsData`)
    // and rendered by the packaged `operator/dashboard` page. Those figures now come from each
    // resource's summary provider through `GET /frame/resources/operator-dashboard`, gated per resource
    // by the same reach the rail is, so this host writes no count and no page. The path is derived from
    // the same constant the leaf is mounted from, not spelled twice.
    //
    // Gated on the `os.operate` entitlement — the same gate as the console it redirects into, so the
    // door is never softer than the room. A non-staff user is 403'd HERE, before the redirect.
    Route::get('operator', fn () => redirect()->to('/operator/'.RealmDashboard::PATH))
        ->middleware('can:entitlement:os.operate')
        ->name('operator.home');

    // The OS-SHELL desktop (frontend-surfaces.md). The windowed realm composer. Route-gated on the
    // projected `os.enter` entitlement (`can:entitlement:os.enter`); the shell itself does the fusion pivot
    // off shared `can['os.enter']` (entitled → desktop, else app-first). Staff hold it via the
    // DefaultEntitlementResolver; a non-staff user is 403'd and the OS realm is omitted from the manifest.
    Route::inertia('os', 'os')->middleware('can:entitlement:os.enter')->name('os.shell');
});

// ── Getting onto a team: `teams.create` and the emailed `invitations.accept` link ───────────────────
//
// `splicewire/laravel-beam-accounts`' self-service team creation (the `teams.create` page + the
// `CreateTeam` op at `teams.store`) and invitation acceptance (the signed `invitations.accept` page every
// sent invitation now emails + the `RedeemInvitation` op at `invitations.redeem`). A fresh registration
// here provisions NO team, so these are how a new user gets onto one; `DashboardWelcome` offers
// "Create a team" and mentions invitation links only because these two names exist.
//
// OUTSIDE the `auth` group above on purpose: the accept page must open for a guest, who registers or
// logs in from it and is returned to the link (it is stored as the intended URL). The three signed-in
// routes carry the same `auth` + `verified` as the group; the guest page adds nothing to `web`.
// Mounted here, ahead of the frame console's `{frameRoute}` and the entry renderer's catch-alls.
Route::splicewireTeamRoutes(middleware: ['auth', 'verified'], guestMiddleware: []);

require __DIR__.'/settings.php';

// ── The TENANT FRAME CONSOLE mount ──────────────────────────────────────────────────────────────────
//
// One page component (`resources/js/pages/frame/console.tsx`) served at every path the tenant realm
// projects, so the client router in `resources/js/frame/router.tsx` can match the leaf. Which surface
// renders is the MANIFEST's decision, read from the same `routeContext` the nav's hrefs come from — a
// second server-side resource→page mapping here would be a copy that can drift from the nav.
//
// ⚠️ **The segment list is DERIVED, never written down.** `RouteContextProjector::hrefs('tenant')` is
// the same call the manifest's own hrefs come from, so a resource this host adds to
// `config('frame.realms')['tenant']` is mounted by adding it there and nowhere else. Writing the
// segments out would be the third copy of a list that already exists twice.
//
// ⚠️ **Registered BEFORE `Route::beamUxSite()` and constrained to those segments.** The renderer below
// takes a `{path}` catch-all; an unconstrained catch-all here would swallow every authored page.
// `rescue(..., [])` means a projector failure mounts NOTHING rather than mounting everything.
Route::middleware(['auth', 'verified'])->group(function () {
    $hrefs = rescue(function (): array {
        $paths = array_values(app(Splicewire\Beam\Ux\Frame\RouteContextProjector::class)->hrefs('tenant'));

        // Every seat DECLARED for the realm, not the seats some viewer is shown. Routes are registered
        // once, with no actor, so the gated nav projection here was a GUEST's nav: its section hrefs
        // mounted only while a resource with no read boundary kept a seat populated for everyone.
        // Mounting a seat's path serves the console shell; what it lists is the read boundary's call.
        $sections = array_map(
            fn (Splicewire\Beam\Nav\NavSection $seat): string => $seat->href,
            app(Splicewire\Beam\Nav\NavSectionRegistry::class)->for('tenant')
        );

        return array_filter([...$paths, ...$sections]);
    }, [], false);

    // Only the FIRST segment is enumerated; the optional second matches the `/:id` record twin. Every
    // tenant leaf is one of those two shapes (the projector emits `path` and `path/:id`, nothing
    // deeper), so this stays exact rather than greedy.
    $segments = array_values(array_unique(array_map(
        fn (string $href): string => explode('/', trim($href, '/'))[0],
        $hrefs
    )));

    if ($segments !== []) {
        Route::get('{frameRoute}', fn () => Inertia::render('frame/console'))
            ->where('frameRoute', '('.implode('|', array_map(fn (string $s): string => preg_quote($s, '/'), $segments)).')(\/[^\/]+)?')
            ->name('frame.console');
    }
});

// ── The OPERATOR FRAME CONSOLE mount ────────────────────────────────────────────────────────────────
//
// The same packaged page as the tenant console above, mounted a second time for a second realm — which
// is the shape the foundation already documented and nothing had ever built:
// `Schemastud\Frame\Registry\NavManifest::realmFor()` reads the matched route's `defaults['realm']`
// and its docblock says outright that "a host mounts the same controller once per realm".
//
// ⚠️ Measured on fresh-tower.test 2026-09-11, and this mount was TRIED AND REVERTED then, on purpose.
// `RouteContextProjector::hrefs('operator')` projects `/operator/users`, `/operator/users/:id`,
// `/operator/teams`, `/operator/teams/:id`, and all four 404'd because the console above is built from
// `hrefs('tenant')` alone. Adding the mount made them serve — and the console rendered "No surface
// here", because `@splicewire/beam-inertia`'s `pages/frame/console.tsx` fetched a hardcoded
// `/frame/manifest` and mounted `<BrowserRouter>` with no basename, while this realm's `routeContext`
// paths are realm-RELATIVE (`users`) and its nav hrefs realm-PREFIXED (`/operator/users`). One packaged
// console, one manifest URL, one root basename ⇒ exactly one servable realm per host. The client half
// now takes the three props below, so this is a mount again rather than a half-landed experiment.
//
// THREE THINGS, spelled out because they are facts about an EXPOSURE and the route file owns those
// (api-surface-coherence 141):
//   · `realm`       — the manifest cache key, so a second realm's console cannot be served the first's
//                     manifest out of react-query's cache.
//   · `basename`    — `<BrowserRouter basename>`, so realm-relative leaves match under `/operator`.
//   · `manifestUrl` — this realm's own manifest mount, below.
//
// Gated on the SAME entitlement as `/operator` itself, so the console cannot be a softer door than the
// dashboard it sits beside. The frame socket behind it is gated independently and per resource by
// `Splicewire\Beam\Realm\RealmEntitlementResourceGate` — this middleware is the door, not the lock.
Route::middleware(['auth', 'verified', 'can:entitlement:os.operate'])
    ->prefix('operator')
    ->name('operator.frame.')
    ->group(function (): void {
        // This realm's manifest. `->defaults('realm', 'operator')` is the whole mechanism:
        // `NavManifest::realmFor()` reads it off the matched route and `FrameNavContribution` projects
        // the operator realm's nav and routeContext instead of the `beam.ux.frame_nav.default_realm`
        // fallback the root mount rides.
        Route::get('frame/manifest', FrameManifestController::class)
            ->defaults('realm', 'operator')
            ->name('manifest');

        // DERIVED, never written down — the same rule as the tenant mount. `hrefs('operator')` returns
        // realm-PREFIXED hrefs (`/operator/users`), so the base is stripped back off to get the
        // route-relative segment this prefixed group needs.
        $hrefs = rescue(function (): array {
            $paths = array_values(app(Splicewire\Beam\Ux\Frame\RouteContextProjector::class)->hrefs('operator'));

            // Every seat DECLARED for the realm, not the seats some viewer is shown. Routes are registered
            // once, with no actor, so the gated nav projection here was a GUEST's nav: its section hrefs
            // mounted only while a resource with no read boundary kept a seat populated for everyone.
            // Mounting a seat's path serves the console shell; what it lists is the read boundary's call.
            $sections = array_map(
                fn (Splicewire\Beam\Nav\NavSection $seat): string => $seat->href,
                app(Splicewire\Beam\Nav\NavSectionRegistry::class)->for('operator')
            );

            return array_filter([...$paths, ...$sections]);
        }, [], false);

        $segments = array_values(array_filter(array_unique(array_map(
            function (string $href): string {
                $relative = ltrim(Illuminate\Support\Str::after($href, '/operator'), '/');

                return explode('/', $relative)[0];
            },
            $hrefs
        ))));

        if ($segments !== []) {
            Route::get('{frameRoute}', fn () => Inertia::render('frame/console', FrameConsolePageData::from([
                'realm' => 'operator',
                'basename' => '/operator',
                'manifestUrl' => '/operator/frame/manifest',
            ])))
                ->where('frameRoute', '('.implode('|', array_map(fn (string $s): string => preg_quote($s, '/'), $segments)).')(\/[^\/]+)?')
                ->name('console');
        }
    });

// The PUBLIC ENTRY RENDERER (ADR-0209 §2) — resolves any unclaimed URL against the `site` realm's
// containment tree and renders the entry through `resources/js/pages/site/entry.tsx`. This is what
// makes the seeded `/docs`, `/docs/api` and `/docs/mcp` live on a fresh install, and what serves every
// page authored after it.
//
// **LAST, and that is load-bearing.** It registers a `{path}` catch-all, so every named route above
// must already be declared or it gets swallowed. `claimRoot: false` (the default) leaves `/` to the
// `site/home` route above; a site served WHOLLY from entries passes `claimRoot: true` instead.
//
// It also mounts the compiled-artifact route the page shell imports, above its own catch-all. The
// renderer 404s uniformly on anything it cannot resolve, gate, and read — so an incumbent catch-all
// of your own must be registered BELOW this line, or `/{any}` never sees a request again.
Route::beamUxSite('site/entry');
