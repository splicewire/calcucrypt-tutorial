<?php

namespace Tests\Feature;

use App\Marketplace\TeamEntitlementScopeResolver;
use App\Marketplace\TeamMarketplaceContext;
use App\Models\User;
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
        $manifest = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('splicewire/laravel-beam-market', $manifest['require']);
    }
}
