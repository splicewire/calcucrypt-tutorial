<?php

namespace Tests\Feature;

use App\Data\Pages\FrameConsolePageData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * The OPERATOR realm's manifest, over the wire — the endpoint the operator app chrome's rail reads.
 *
 * `@splicewire/beam-inertia` gives `operator/*` pages `OperatorLayout`, whose sidebar fetches
 * `/operator/frame/manifest` (otb-ui-frontier-sidebar DESIGN-01, "Want 2"). If this mount is missing
 * the rail is empty with no error on screen, and if it rides the unscoped `/frame/manifest` default the
 * rail beside an operator page lists the TENANT realm's sections. {@see FrameManifestRouterTest} asks
 * the controller for each realm directly; only an HTTP request proves the mount, its
 * `->defaults('realm', 'operator')` and its gate.
 */
class OperatorFrameManifestTest extends TestCase
{
    use RefreshDatabase;

    /** The same operator cascade {@see OperatorOsEntitlementTest} grants — a realm grant, not a flag. */
    private function operator(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'Operators', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    public function test_the_operator_manifest_is_mounted_as_frames_own_controller_for_the_operator_realm(): void
    {
        $route = app('router')->getRoutes()->getByName('operator.frame.manifest');

        $this->assertNotNull($route, 'The operator manifest route must be MOUNTED, not merely callable.');
        $this->assertSame('operator/frame/manifest', $route->uri());
        $this->assertSame(FrameManifestController::class, $route->getActionName());
        $this->assertSame('operator', $route->defaults['realm'] ?? null);
        $this->assertContains('can:entitlement:os.operate', $route->gatherMiddleware());
    }

    public function test_an_operator_reads_the_operator_realms_manifest(): void
    {
        $response = $this->actingAs($this->operator())->getJson('/operator/frame/manifest');

        $response->assertOk();
        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($response->json()),
        );

        // The realm is not a key on the wire; it is WHICH router table comes back. The operator realm
        // places `users` and `teams`, the tenant realm `beam-ux-entry` — disjoint, so a mount that fell
        // back to the default realm cannot satisfy both assertions.
        $routeNames = array_column($response->json('routeContext'), 'routeName');
        $this->assertContains('users.index', $routeNames);
        $this->assertContains('teams.index', $routeNames);
        $this->assertNotContains('beam-ux-entry.index', $routeNames);
    }

    /**
     * The rail lists what an operator can actually reach — measured EMPTY on 2026-09-14, when users and teams
     * seated no section and every seat that reached the realm was dropped as empty. They sit in
     * beam-accounts' People task section now (ux-walkthrough UX-09), which replaced this host's
     * `OperatorRailSeat`; this host seats no operator page of its own.
     *
     * Asserted on the REAL projection (the mounted route, the registered seat, `keepBound()`'s pruning),
     * and each href is then REQUESTED as that operator: a row that projects but 404s is the defect this
     * rail would otherwise ship silently.
     */
    public function test_an_operators_rail_lists_the_operator_surfaces_and_each_one_opens(): void
    {
        $operator = $this->operator();

        $nav = $this->actingAs($operator)->getJson('/operator/frame/manifest')->assertOk()->json('nav.items');

        $this->assertNotEmpty($nav, 'The operator rail projected no section at all.');

        // Every href at any depth: the Developer zone holds its seats one level down (UX-08).
        $hrefs = [];
        $walk = function (array $nodes) use (&$walk, &$hrefs): void {
            foreach ($nodes as $node) {
                if (isset($node['href'])) {
                    $hrefs[] = $node['href'];
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk($nav);

        $this->assertContains('/operator/users', $hrefs);
        $this->assertContains('/operator/teams', $hrefs);

        foreach (array_unique($hrefs) as $href) {
            // A matching GET route first: it names the missing mount, where a bare 404 would not.
            $route = rescue(fn () => app('router')->getRoutes()->match(Request::create($href)), null, false);
            $this->assertNotNull($route, "The operator rail links [{$href}], which no route mounts.");

            // Bespoke tier pages may need a tier's own fixture to render (the satellite's platform
            // connection probes its tower); this file proves the frame-backed rows and the header open,
            // and each tier proves its own pages beside the page's own test.
            // `/operator` is the realm's front door and lands on its dashboard leaf by redirect
            // (realm-dashboards ticket 05); the leaf itself is the console, so it is opened by following it.
            if (str_starts_with($route->getName() ?? '', 'operator.frame.') || $href === '/operator') {
                $this->actingAs($operator)->followingRedirects()->get($href)->assertOk();
            }
        }
    }

    public function test_an_ordinary_member_is_refused_the_operator_manifest(): void
    {
        $member = User::factory()->create(); // no realm grant ⇒ os.operate false

        $this->actingAs($member)->getJson('/operator/frame/manifest')->assertForbidden();
    }

    public function test_a_guest_is_refused_the_operator_manifest(): void
    {
        $this->getJson('/operator/frame/manifest')->assertUnauthorized();
    }

    /**
     * The console catch-all is the OTHER door in this group, and until this test only its rail rows were
     * requested — as an operator, so nothing proved it refuses anyone. A leaf is asked for rather than
     * the manifest because the console mount is a separate route that could lose the group's gate alone.
     */
    public function test_an_ordinary_member_is_refused_the_operator_console(): void
    {
        $member = User::factory()->create(); // no realm grant ⇒ os.operate false

        $this->actingAs($member)->get('/operator/users')->assertForbidden();
    }

    public function test_a_guest_is_refused_the_operator_console(): void
    {
        $this->get('/operator/users')->assertRedirect(route('login'));
    }

    /**
     * The console's props are a declared {@see FrameConsolePageData}, and the page's own props are
     * EXACTLY `FrameRealmContext`'s three keys — shared props aside. A key renamed or added on either
     * side then fails here instead of rendering "No surface here" in the browser.
     */
    public function test_an_operator_opens_the_console_with_exactly_the_realm_context_props(): void
    {
        $response = $this->actingAs($this->operator())->get('/operator/users');

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('frame/console', false)
            ->where('realm', 'operator')
            ->where('basename', '/operator')
            ->where('manifestUrl', '/operator/frame/manifest'));

        $props = $response->inertiaProps();
        $ownProps = array_diff_key($props, Inertia::getShared());

        $this->assertSame(
            ['realm' => 'operator', 'basename' => '/operator', 'manifestUrl' => '/operator/frame/manifest'],
            $ownProps,
        );
        $this->assertSame(array_keys($ownProps), array_keys(FrameConsolePageData::empty()));
    }
}
