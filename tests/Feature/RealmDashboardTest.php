<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * The realm dashboards this host gets by writing NO config (realm-dashboards ticket 05): one read-only
 * `{realm}-dashboard` resource per realm, registered by `splicewire/laravel-beam-ux`, whose frame index
 * streams the realm's summary cards and jump-to tiles, and whose list leaf the packaged frame console
 * serves at `/{realmBase}/dashboard`.
 *
 * Asserted over the wire, at the two seams a host can actually break: the realm manifest (which is the
 * only thing the console's router and rail read) and the frame socket's rows for a given actor. Nothing
 * here names a backing, a provider or a widget component. `config/frame.php` is deliberately untouched
 * — the whole claim is that it need not be.
 *
 * {@see OperatorFrameManifestTest} proves the operator manifest's mount and gate; this file proves what
 * that manifest now carries and what the leaf it names serves.
 */
class RealmDashboardTest extends TestCase
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

    /**
     * Every destination in a nav tree: the nodes with an href and no children, depth-first. Headers
     * (seats) are not destinations and draw no tile, so they are not leaves here either.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function leaves(array $nodes): array
    {
        $leaves = [];

        foreach ($nodes as $node) {
            if (($node['children'] ?? []) !== []) {
                $leaves = [...$leaves, ...self::leaves($node['children'])];
            } elseif (is_string($node['href'] ?? null)) {
                $leaves[] = $node;
            }
        }

        return $leaves;
    }

    /** @return list<array<string, mixed>> */
    private function rows(User $actor, string $resource): array
    {
        // `per_page` is sent at frame's maximum on purpose: the backing answers every card in one page,
        // and the controller then re-slices by `per_page` (default 25) — so a client that omits it can
        // page a realm with more cards than that. Asserted below as `total === count(data)`.
        $response = $this->actingAs($actor)->getJson("/frame/resources/{$resource}?per_page=100")->assertOk();

        $this->assertSame($response->json('total'), count($response->json('data')), 'every row must land in one page');

        return $response->json('data');
    }

    // ---------------------------------------------------------------- the operator manifest

    public function test_the_operator_manifest_carries_the_dashboard_resource_its_list_leaf_its_nav_leaf_and_its_contexts(): void
    {
        $manifest = $this->actingAs($this->operator())->getJson('/operator/frame/manifest')->assertOk()->json();

        $this->assertContains('operator-dashboard', array_column($manifest['resources'], 'key'));

        // The router leaf: a list at `dashboard` (NOT `operator-dashboard`), with no `:id` twin — a card
        // is a projection of another resource, and that resource's own list is where it opens.
        $leaves = array_column($manifest['routeContext'], null, 'routeName');
        $this->assertSame('list', $leaves['operator-dashboard.index']['mounts']);
        $this->assertSame('dashboard', $leaves['operator-dashboard.index']['path']);
        $this->assertSame('operator-dashboard', $leaves['operator-dashboard.index']['resource']);
        $this->assertSame(
            [],
            array_filter($manifest['routeContext'], fn (array $entry): bool => $entry['resource'] === 'operator-dashboard' && str_ends_with($entry['path'], ':id')),
            'showable: false, editable: false ⇒ no record twin',
        );

        // The nav leaf: section-less, first, at the realm's base — the head of the rail.
        $first = $manifest['nav']['items'][0];
        $this->assertSame('operator-dashboard.index', $first['routeName']);
        $this->assertSame('/operator/dashboard', $first['href']);
        $this->assertSame('Dashboard', $first['title']);
        $this->assertSame([], $first['children']);

        // ...and ONCE: the dashboard resource is section-less, and a seat listing the realm's section-less
        // resources (this host's retired `OperatorRailSeat`) once drew it a second time — measured 2026-09-14.
        $dashboardLeaves = array_filter(
            self::leaves($manifest['nav']['items']),
            fn (array $leaf): bool => ($leaf['routeName'] ?? null) === 'operator-dashboard.index',
        );
        $this->assertCount(1, $dashboardLeaves, 'the dashboard leaf is projected exactly once');

        // The render context the rows are drawn in: the resource's root `list-item` names the card widget.
        $this->assertSame(
            ['participates' => true, 'widget' => 'dashboard-card'],
            $manifest['contexts']['operator-dashboard']['byNode']['']['list-item'],
        );
    }

    // ---------------------------------------------------------------- the operator rows

    /**
     * Declared cards keep their live provider figures; rail-only Users and Teams are TILES, not cards
     * (ADR-0224: a default-provider resource with no declared context is a nav tile, not a derived card).
     * Every destination appears once - card XOR tile - and the tiles are the realm's remaining nav leaves,
     * after every card. This host seats no card-declaring resource in the operator realm, so its dashboard
     * is all tiles (Users, Teams); a host that adds a card resource asserts it the same way.
     */
    public function test_the_operator_dashboard_streams_declared_cards_with_live_counts_then_remaining_product_tiles(): void
    {
        $operator = $this->operator();

        $rows = $this->rows($operator, 'operator-dashboard');
        $cards = array_values(array_filter($rows, fn (array $row): bool => $row['context'] !== 'nav'));
        $tiles = array_values(array_filter($rows, fn (array $row): bool => $row['context'] === 'nav'));

        // Cards first (one sort: navOrder, then label), tiles after every card.
        $this->assertSame([...$cards, ...$tiles], $rows, 'every tile is after every card');

        // ONE page whatever the request asks for: a dashboard is one screen, so `per_page=1` yields the
        // same rows as the maximum — a backing that let the controller re-slice would answer one row.
        $this->assertSame(
            array_column($rows, 'id'),
            array_column($this->actingAs($operator)->getJson('/frame/resources/operator-dashboard?per_page=1')->assertOk()->json('data'), 'id'),
            'every card lands in one page regardless of per_page',
        );

        // ADR-0224: Users and Teams are default-provider resources with no declared dashboard context, so
        // they are rail TILES, never cards. Any declared card keeps its own live provider figures.
        // This host seats no card-declaring operator resource, so the card set is EMPTY. Assert the FULL
        // set (not merely that Users/Teams are absent) so an invented third card cannot slip through.
        $this->assertSame([], $cards, 'the operator dashboard declares no cards on this host');

        // Jump-to equals the remaining PRODUCT rail: exclude the Developer zone (zone=meta, ADR-0224),
        // the dashboard's own leaf, and any card destination, in the order the rail draws them.
        $manifest = $this->actingAs($operator)->getJson('/operator/frame/manifest')->assertOk()->json();
        $cardHrefs = array_column($cards, 'href');
        $productNav = array_filter($manifest['nav']['items'], fn (array $node): bool => ($node['zone'] ?? null) !== 'meta');
        $rail = array_values(array_filter(
            self::leaves($productNav),
            fn (array $leaf): bool => ($leaf['routeName'] ?? null) !== 'operator-dashboard.index'
                && ! in_array($leaf['href'], $cardHrefs, true),
        ));

        $tileHrefs = array_column($tiles, 'href');

        $this->assertNotEmpty($tiles);
        $this->assertSame(array_column($rail, 'href'), $tileHrefs);
        $this->assertSame(array_column($rail, 'title'), array_column($tiles, 'label'));
        $this->assertNotContains('/operator/dashboard', $tileHrefs);

        // ADR-0224: Users and Teams are tiles, not cards; every destination appears once (card XOR tile).
        $this->assertContains('/operator/users', $tileHrefs);
        $this->assertContains('/operator/teams', $tileHrefs);
        $this->assertSame([], array_values(array_intersect($cardHrefs, $tileHrefs)));
        $this->assertCount(count(array_unique([...$cardHrefs, ...$tileHrefs])), $rows);

        foreach ($tiles as $tile) {
            $this->assertNull($tile['summary']);
            $this->assertNull($tile['resource']);
        }
    }

    // ---------------------------------------------------------------- the gate

    public function test_a_member_without_os_operate_is_refused_the_dashboard_and_is_shown_no_leaf(): void
    {
        $member = User::factory()->teamMember()->create(); // membership, but no realm grant ⇒ os.operate false

        // The resource's own read gate is the realm gate's ability, declared on it (`entitlement:os.operate`).
        $this->actingAs($member)->getJson('/frame/resources/operator-dashboard')->assertForbidden();

        // The realm's manifest is behind the same gate, so there is no rail to carry a leaf at all...
        $this->actingAs($member)->getJson('/operator/frame/manifest')->assertForbidden();

        // ...and the realm-blind default manifest (the tenant realm) carries the tenant's leaf, never the
        // operator's: absent, not locked, not empty.
        $routeNames = array_column(self::leaves($this->actingAs($member)->getJson('/frame/manifest')->assertOk()->json('nav.items')), 'routeName');
        $this->assertNotContains('operator-dashboard.index', $routeNames);
        $this->assertContains('tenant-dashboard.index', $routeNames);
    }

    public function test_a_guest_is_refused_the_dashboard(): void
    {
        $this->getJson('/frame/resources/operator-dashboard')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- the doors

    /**
     * `/operator` is the realm's front door and it lands on the dashboard LEAF — the hand-rolled landing
     * (three counts computed in the route closure, `OperatorStatsData`) is gone. The door keeps the
     * realm's gate: a member is refused at `/operator` itself, before any redirect.
     */
    public function test_the_operator_front_door_lands_on_the_dashboard_leaf(): void
    {
        // The guest FIRST: `actingAs()` sticks for the rest of the test, so a guest asked last is not one.
        $this->get('/operator')->assertRedirect(route('login'));

        $operator = $this->operator();

        $this->actingAs($operator)->get('/operator')->assertRedirect('/operator/dashboard');

        $this->actingAs($operator)->get('/operator/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('frame/console', false)
                ->where('realm', 'operator')
                ->where('basename', '/operator')
                ->where('manifestUrl', '/operator/frame/manifest'));

        $this->actingAs(User::factory()->create())->get('/operator')->assertForbidden();
    }

    /**
     * The account realm's home is the TENANT realm's dashboard leaf: `/dashboard` serves the frame console
     * (its router matches `dashboard` off the tenant manifest), the tenant rail leads with that leaf, and
     * the socket streams the tenant's cards and tiles for an ordinary member.
     */
    public function test_the_account_home_is_the_tenant_realms_dashboard_leaf(): void
    {
        // A team member: the rail and the cards offer only what the viewer can read, and a user in no
        // team holds no role to read with.
        $member = User::factory()->teamMember()->create();

        $this->actingAs($member)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console', false));

        $manifest = $this->actingAs($member)->getJson('/frame/manifest')->assertOk()->json();

        $first = $manifest['nav']['items'][0];
        $this->assertSame('tenant-dashboard.index', $first['routeName']);
        $this->assertSame('/dashboard', $first['href']);

        $leaves = array_column($manifest['routeContext'], null, 'routeName');
        $this->assertSame('list', $leaves['tenant-dashboard.index']['mounts']);
        $this->assertSame('dashboard', $leaves['tenant-dashboard.index']['path']);
        $this->assertSame(
            ['participates' => true, 'widget' => 'dashboard-card'],
            $manifest['contexts']['tenant-dashboard']['byNode']['']['list-item'],
        );

        $rows = $this->rows($member, 'tenant-dashboard');
        $cards = array_values(array_filter($rows, fn (array $row): bool => $row['context'] !== 'nav'));
        $tiles = array_values(array_filter($rows, fn (array $row): bool => $row['context'] === 'nav'));
        $rail = array_values(array_filter(
            self::leaves($manifest['nav']['items']),
            fn (array $leaf): bool => ($leaf['routeName'] ?? null) !== 'tenant-dashboard.index',
        ));

        $this->assertNotEmpty($rows, 'the tenant dashboard must stream something for a member');
        $this->assertSame([...$cards, ...$tiles], $rows, 'every tile is after every card');
        $this->assertSame(array_column($rail, 'href'), array_column($tiles, 'href'), 'jump-to equals the rail');

        // Every card is one of the rail's own destinations, with figures: the dashboard cannot surface a
        // resource the rail does not, and never an empty card.
        foreach ($cards as $card) {
            $this->assertContains($card['href'], array_column($rail, 'href'), "card {$card['id']} names a leaf the rail does not");
            $this->assertNotEmpty($card['summary']['figures']);
        }
    }
}
