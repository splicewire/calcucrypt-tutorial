<?php

namespace Tests\Feature;

use App\Marketplace\TeamEntitlementScopeResolver;
use App\Marketplace\TeamMarketplaceContext;
use App\Models\User;
use App\Providers\LocalMarketplaceServiceProvider;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Market\Checkout\Contracts\EntitlementScopeResolver;
use Tests\TestCase;

class LocalMarketplaceAuthorityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_membership_is_rechecked_even_when_selected_team_and_relation_are_loaded(): void
    {
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Market authority');
        $context = new TeamMarketplaceContext;
        $this->assertTrue($context->mayCreate($owner->fresh()));
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $member->load('currentTeam');
        $this->assertNotNull($context->team($member));
        $this->assertFalse($context->mayCreate($member));
        $this->assertFalse($context->mayOperate($member));
        $team->memberships()->where('user_id', $member->id)->delete();
        $this->assertNull($context->team($member));
        $this->assertFalse($context->mayCreate($member));
        $this->assertFalse($context->mayOperate($member));
        $this->assertNull($context->team(null));
    }

    public function test_entitlement_scope_follows_fresh_selection_not_a_cached_team(): void
    {
        if (! interface_exists(EntitlementScopeResolver::class)) {
            $this->markTestSkipped('Optional Marketplace entitlement scope contract is absent.');
        }
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Scope one');
        $this->actingAs($owner->fresh());
        $scope = new TeamEntitlementScopeResolver(new TeamMarketplaceContext);
        $this->assertSame('team:'.$team->id, $scope->scopeRef());
        $team->memberships()->where('user_id', $owner->id)->delete();
        $this->assertNull($scope->scopeRef());
    }

    public function test_default_example_is_disabled_without_optional_route_or_package_requirement(): void
    {
        $this->assertFalse((bool) config('local-marketplace.enabled'));
        $this->assertSame(\Splicewire\Beam\Source\ParticleRouteManifestSource::class, config('beam.client.sources.defaults'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('local-marketplace'));
        // The example does not require beam-market: it selects that package only for its alternate context
        // (selected-additions.json, written into composer.market.json), which the host's manifest never merges. A host
        // whose own composer.json requires beam-market, a tier whose capability it is, owns that requirement.
        $manifest = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $selected = json_decode((string) file_get_contents(base_path('examples/local-marketplace/selected-additions.json')), true);
        $this->assertNotContains('composer.market.json', $manifest['extra']['merge-plugin']['include'] ?? []);
        $this->assertNotSame($selected[LocalMarketplaceServiceProvider::MARKET_PACKAGE], $manifest['require'][LocalMarketplaceServiceProvider::MARKET_PACKAGE] ?? null);
    }

    public function test_example_withholds_market_routes_only_where_it_owns_the_composition(): void
    {
        $keys = [
            'beam.market.extensions.register_routes',
            'beam.market.review_queue.register_routes',
            'beam.market.reporting.register_routes',
            'beam.market.federation.publish.register_routes',
        ];
        $manifest = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $hostRequiresMarket = array_key_exists('splicewire/laravel-beam-market', $manifest['require']);
        $this->assertSame($hostRequiresMarket, LocalMarketplaceServiceProvider::hostRequiresMarket(base_path('composer.json')));

        // This host: the example's register() withholds the four surfaces only where the host does not require beam-market.
        config(array_fill_keys($keys, 'host value'));
        (new LocalMarketplaceServiceProvider($this->app))->register();
        foreach ($keys as $key) {
            $this->assertSame($hostRequiresMarket ? 'host value' : false, config($key), $key);
        }

        // Both answers, whichever this tier is.
        $dir = sys_get_temp_dir().'/local-marketplace-manifest-'.bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            file_put_contents($dir.'/requires.json', json_encode(['require' => ['php' => '^8.4', 'splicewire/laravel-beam-market' => 'dev-main']]));
            file_put_contents($dir.'/omits.json', json_encode(['require' => ['php' => '^8.4']]));
            $this->assertTrue(LocalMarketplaceServiceProvider::hostRequiresMarket($dir.'/requires.json'));
            $this->assertFalse(LocalMarketplaceServiceProvider::hostRequiresMarket($dir.'/omits.json'));
            $this->assertFalse(LocalMarketplaceServiceProvider::hostRequiresMarket($dir.'/absent.json'));
        } finally {
            array_map('unlink', glob($dir.'/*.json') ?: []);
            rmdir($dir);
        }
    }
}
