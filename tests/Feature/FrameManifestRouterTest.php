<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Rushing\DataNav\Contracts\NavExpander;
use Rushing\DataNav\Contracts\NavMatcher;
use Rushing\DataNav\NavGate;
use Rushing\DataNav\NavRegistry;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * This host emits a manifest-generated router table and writes NO controller to do it.
 *
 * Until OTB M2, `/frame/manifest` here carried `resources` and `contexts` and nothing else — `nav`
 * and `routeContext` existed at exactly ONE host in the estate, which had hand-written its own copy
 * of frame's manifest controller. The projection now ships in `splicewire/laravel-beam-ux` behind
 * frame's `FrameNavContributor` plug; what this host supplies is `config('frame.realms')`, a list it
 * spells out (`api-surface-coherence` 141/142).
 *
 * The realm comparison checks both realms and requires disjoint route lists. A
 * test that only ever asked for `tenant` would pass against a projector that ignored the realm and
 * returned every resource to everyone — which is precisely the defect these assertions exist to
 * catch, and it is invisible to a single-realm fixture.
 */
class FrameManifestRouterTest extends TestCase
{
    /** Real realm grants and resource visibility use persisted principals. */
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * This test goes over HTTP; the projection tests below call the controller directly.
     *
     * `app->call()` on a controller cannot tell a MOUNTED route from an absent one, and skips
     * `config('frame.middleware')` entirely; a suite built only that way would stay green if the
     * route were deleted (AGENTS.md §*The estate's signature defect*, the 404-body row: *"read the
     * route table"*). So the mount is asserted here, once, against the real request pipeline —
     * including that the route this host serves is frame's OWN controller and not a host copy.
     *
     * The per-realm tests below call the controller directly so each realm is asked the same way.
     * The unscoped `/frame/manifest` mount answers the default (tenant) realm only; the operator realm
     * has its own mount, `/operator/frame/manifest`, whose route, gate and projected rail are proved
     * over HTTP in {@see OperatorFrameManifestTest}.
     */
    public function test_the_mounted_route_serves_frames_controller_and_emits_the_new_keys(): void
    {
        $route = app('router')->getRoutes()->getByName('frame.manifest');

        $this->assertNotNull($route, 'The manifest route must be MOUNTED, not merely callable.');
        $this->assertSame(FrameManifestController::class, $route->getActionName());

        $response = $this->actingAs($this->operator())->getJson('/frame/manifest');

        $response->assertOk();
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($response->json()),
            'Over the wire, from the package, with no controller in this host.'
        );
        $this->assertNotEmpty($response->json('routeContext'));
    }

    public function test_it_emits_nav_and_route_context_from_the_package_with_no_host_controller(): void
    {
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($this->manifestFor('tenant', $this->operator()))
        );
    }

    public function test_it_scopes_the_router_table_to_the_realm_the_host_placed_each_resource_in(): void
    {
        $actor = $this->operator();
        $tenant = $this->routeNames('tenant', $actor);
        $operator = $this->routeNames('operator', $actor);

        $this->assertContains('beam-ux-entry.index', $tenant);
        $this->assertContains('hooks.index', $tenant);
        $this->assertNotContains('users.index', $tenant);

        $this->assertContains('users.index', $operator);
        $this->assertContains('teams.index', $operator);
        $this->assertNotContains('beam-ux-entry.index', $operator);

        // The two realms must not merely differ — neither may overlap the other, or a projector
        // leaking one realm's list into the other could still satisfy both blocks above.
        $this->assertSame([], array_values(array_intersect($tenant, $operator)));
    }

    public function test_it_gives_a_resource_the_per_record_twin_its_own_declaration_allows(): void
    {
        $byName = $this->byName('tenant', $this->operator());

        // `editable` ⇒ an edit form.
        $this->assertSame('edit', $byName['beam-ux-entry.edit']->mounts);
        // `showable && ! editable` ⇒ a read-only detail, never an edit shell whose save the server 405s.
        $this->assertSame('detail', $byName['git-repo.edit']->mounts);
    }

    public function test_a_resource_that_is_neither_showable_nor_editable_gets_a_list_leaf_and_nothing_else(): void
    {
        // ⚠️ **The realm list is overridden HERE rather than read from `config/frame.php`, and that is
        // the correction rather than a convenience.** This case used to be asserted against whatever
        // the host happened to have in its tenant realm, naming `tokens` and `invitations` as "the
        // live cases here" — so when this host moved both to the ACCOUNT realm (they are served by
        // `/account/tokens` and `/account/team` now; see config/frame.php's realm comment) the
        // assertion went red over a composition change rather than over the rule it guards.
        //
        // The rule is the PROJECTOR's, not this host's IA: a resource declaring neither `showable`
        // nor `editable` must get a list leaf and no per-record twin, because a twin would be an edit
        // shell whose save the server 405s. Naming the realm membership this test needs makes the
        // premise explicit and makes the test survive the host rearranging its own navigation —
        // which is exactly what a host is free to do.
        // ADDITIVE, through the registry's own idempotent seam rather than by rewriting
        // `config('frame.realms')` — that config is read once, when `BeamServiceProvider` binds the
        // registry, so a runtime `config()` set would be silently too late and this test would go
        // green over a premise it never established.
        app(\Splicewire\Beam\Particle\ParticleResourceRegistry::class)
            ->loadRealmMap(['tenant' => ['tokens', 'invitations']]);

        $byName = $this->byName('tenant', $this->operator());

        // Measuring something: the editable control in the same list still gets its twin.
        $this->assertSame('edit', $byName['beam-ux-entry.edit']->mounts);

        $this->assertArrayHasKey('tokens.index', $byName);
        $this->assertArrayHasKey('invitations.index', $byName);
        $this->assertArrayNotHasKey('tokens.edit', $byName);
        $this->assertArrayNotHasKey('invitations.edit', $byName);
    }

    public function test_an_unregistered_navigation_is_an_empty_tree_not_a_failure(): void
    {
        // "Is a navigation registered for this realm?" is a fact about the HOST, so the contributor
        // answers it with an empty tree beside a real routeContext instead of throwing. The router
        // half needs no navigation to exist, and declining outright would throw away the half that
        // works — which is how a seam ships and delivers nothing.
        // ⚠️ Rebinds an EMPTY NavRegistry since 2026-09-05, and the reason is the whole point of
        // the test rather than a detail of it. beam-ux now registers a `DeclaredSectionNavigation`
        // for every realm this host ships, so "no navigation is registered for tenant" stopped
        // being reachable here — this test began failing with a populated Ops seat, and was then
        // reported as a PRE-EXISTING failure by three separate later runs. It was not pre-existing:
        // it was a real regression, laundered into background noise by being counted rather than read.
        //
        // The property it guards is still true and still load-bearing: `NavRegistry::build()` throws
        // `RegistryMiss` on an unknown key, and "this host registered no navigation" must answer with
        // an empty tree beside a real routeContext rather than 500 the manifest. Delete the catch in
        // `FrameNavContribution::navigation()` and every host with no navigation breaks. So the test
        // now creates the condition explicitly instead of relying on the host to happen to be in it.
        $this->app->instance(NavRegistry::class, new NavRegistry(
            $this->app->make(NavGate::class),
            $this->app->make(NavExpander::class),
            $this->app->make(NavMatcher::class),
        ));

        $manifest = $this->manifestFor('tenant', $this->operator());

        $this->assertSame([], $manifest['nav']['items']);
        $this->assertNotEmpty($manifest['routeContext']);
    }

    public function test_operator_resource_metadata_follows_the_current_principals_realm_grant(): void
    {
        $allowed = $this->manifestFor('operator', $this->operator());
        $keys = array_map(fn ($resource): string => $resource->key, $allowed['resources']);

        $this->assertContains('users', $keys);
        $this->assertContains('teams', $keys);
        $this->assertArrayHasKey('users', $allowed['contexts']);
        $this->assertArrayHasKey('teams', $allowed['contexts']);

        foreach ([null, User::factory()->create()] as $actor) {
            $denied = $this->manifestFor('operator', $actor);

            $this->assertSame([], $denied['resources']);
            $this->assertSame([], $denied['contexts']);
        }
    }

    private function operator(): User
    {
        // The router table is per-actor read-gated (beam `declare resource read boundaries`, integrator
        // read-check ruling 2026-10-10 15:13Z): a route lists only for an actor the read guard admits. This
        // actor must be authorized for BOTH realms it is asserted against - a PROVISIONED tenant owner (so the
        // tenant realm's beam-ux-entry/hooks list) WITH operator realm reach (so the operator realm's
        // users/teams list).
        $user = User::factory()->create();
        $team = app(\Splicewire\Beam\Accounts\Teams\TeamProvisioner::class)->personalTeamFor($user);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestFor(string $realm, ?User $actor): array
    {
        $this->app['auth']->forgetGuards();
        if ($actor !== null) {
            $this->actingAs($actor);

            // A controller-direct call skips config('frame.middleware'), so the live splicewire.team
            // middleware never points the team-scoped role registrar at the actor's tenant - without it the
            // actor resolves zero roles and the per-actor read gate empties the realm's router table for the
            // wrong reason (the seam SitemapWriteAuthorizationTest documents). Point it at the actor's team.
            if ($actor->currentTeam !== null) {
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($actor->currentTeam->getKey());
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                $actor->unsetRelation('roles')->unsetRelation('permissions');
            }
        }

        $request = Request::create('/frame/manifest', 'GET');
        $request->setUserResolver(fn () => $actor);
        $route = new RoutingRoute(['GET'], 'frame/manifest', []);
        $route->defaults('realm', $realm);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $this->app->call(FrameManifestController::class, ['request' => $request]);
    }

    /**
     * @return array<int, string>
     */
    private function routeNames(string $realm, User $actor): array
    {
        return array_map(
            fn ($entry): string => $entry->routeName,
            $this->manifestFor($realm, $actor)['routeContext']
        );
    }

    /**
     * @return array<string, \Schemastud\Frame\Registry\RouteContextEntry>
     */
    private function byName(string $realm, User $actor): array
    {
        $byName = [];

        foreach ($this->manifestFor($realm, $actor)['routeContext'] as $entry) {
            $byName[$entry->routeName] = $entry;
        }

        return $byName;
    }
}
