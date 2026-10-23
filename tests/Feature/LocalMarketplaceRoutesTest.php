<?php

namespace Tests\Feature;

use App\Marketplace\TeamMarketplaceContext;
use App\Models\Permission;
use App\Models\User;
use App\Providers\LocalMarketplaceServiceProvider;
use Illuminate\Support\Facades\Route;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Market\Models\InstalledExtension;
use Splicewire\Beam\Market\Models\Product;
use Tests\TestCase;

class LocalMarketplaceRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Product::class)) {
            $this->markTestSkipped('Optional Marketplace composition is absent.');
        }
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'local-marketplace.enabled' => true]);
        $this->withoutVite();
        (new LocalMarketplaceServiceProvider(app()))->boot();
        Route::middleware('web')->group(base_path('routes/local-marketplace.php'));
        Language::factory()->create(['code' => 'en', 'default' => true]);
        Currency::factory()->create(['code' => 'USD', 'default' => true]);
    }

    private function owner(): User
    {
        $actor = User::factory()->create();
        app(TeamProvisioner::class)->createTeamFor($actor, 'Marketplace team');

        return $actor->fresh();
    }

    public function test_paid_buyer_permission_is_explicit_and_removed_membership_is_authoritative(): void
    {
        $buyer = $this->owner();
        $gate = \Illuminate\Support\Facades\Gate::forUser($buyer);
        $this->assertFalse($gate->allows('market-extensions.purchase'));
        setPermissionsTeamId($buyer->current_team_id);
        $buyer->givePermissionTo(Permission::findOrCreate('marketplace.buyer', 'web'));
        $this->assertTrue($gate->allows('market-extensions.purchase'));
        $this->assertFalse($gate->allows('market-extensions.revoke-entitlement'));
        $buyer->currentTeam->memberships()->where('user_id', $buyer->id)->delete();
        $this->assertFalse($gate->allows('market-extensions.purchase'));
    }

    public function test_creator_identities_and_draft_writes_stay_with_the_selected_team(): void
    {
        $owner = $this->owner();
        $id = $this->actingAs($owner)->postJson('/api/local-marketplace/listings', [
            'name' => 'SLA extension', 'summary' => 'A local free extension',
            'seller_id' => 'forged', 'review_status' => 'approved_human', 'status' => 'published',
        ])->assertCreated()->json('data.id');
        $product = Product::findOrFail($id);
        $this->assertSame('team:'.$owner->current_team_id, $product->market->seller->tenant_id);
        $this->assertNull($product->market->review_status);
        $this->assertSame('draft', $product->status->getValue());
        $before = $product->getAttributes();
        $outside = $this->owner();
        $this->actingAs($outside)->getJson('/api/local-marketplace/listings/'.$id)->assertNotFound();
        $this->putJson('/api/local-marketplace/listings/'.$id, ['name' => 'Stolen'])->assertNotFound();
        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_member_and_removed_membership_cannot_create_or_authorize_a_repo(): void
    {
        $owner = $this->owner();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        $member->switchTeam($team);
        $this->actingAs($member->fresh())->postJson('/api/local-marketplace/listings', ['name' => 'Denied'])->assertForbidden();
        $this->postJson('/api/local-marketplace/repositories')->assertForbidden();
        $owner->load('currentTeam');
        $team->memberships()->where('user_id', $owner->id)->delete();
        $this->actingAs($owner)->postJson('/api/local-marketplace/repositories')->assertForbidden();
        $this->postJson('/api/local-marketplace/listings', ['name' => 'Stale'])->assertForbidden();
        $this->assertSame(0, Product::count());
    }

    public function test_site_wide_operator_permission_is_explicit_and_member_server_refusal_is_independent(): void
    {
        $owner = $this->owner();
        $context = new TeamMarketplaceContext;
        $this->assertFalse($context->mayOperate($owner));
        setPermissionsTeamId($owner->current_team_id);
        $owner->givePermissionTo(Permission::findOrCreate('marketplace.host-operator', 'web'));
        $this->assertTrue($context->mayOperate($owner));
        $member = User::factory()->create();
        app(TeamProvisioner::class)->addMember($member, $owner->currentTeam, Role::Member);
        $member->switchTeam($owner->currentTeam);
        $record = InstalledExtension::create(['package' => 'local/authority-fixture', 'installed_at' => now()]);
        $this->actingAs($member->fresh())->postJson('/api/local-marketplace/installed/'.$record->id.'/remove')->assertForbidden();
        $this->assertTrue($record->fresh()->exists);
        $this->actingAs($owner->fresh())->getJson('/api/local-marketplace/review/pending')->assertForbidden();
    }

    public function test_generated_route_map_preserves_particles_and_resolves_the_two_bespoke_reads(): void
    {
        app('router')->getRoutes()->refreshNameLookups();
        $manifest = app(\App\Marketplace\LocalMarketplaceRouteManifest::class)->toArray();
        $this->assertArrayHasKey('service-requests.index', $manifest);
        foreach (['local-marketplace.connection', 'local-marketplace.review.pending'] as $name) {
            $this->assertSame(app('router')->getRoutes()->getByName($name)->uri(), $manifest[$name]['path']);
            $this->assertSame(['GET'], $manifest[$name]['methods']);
        }
    }

    public function test_missing_bespoke_route_refuses_generation(): void
    {
        $router = new \Illuminate\Routing\Router(app('events'), app());
        $source = new \App\Marketplace\LocalMarketplaceRouteManifest(app(\Splicewire\Beam\Source\ParticleRouteManifestSource::class), $router);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one selected marketplace route');
        $source->toArray();
    }

    public function test_duplicate_live_name_refuses_generation(): void
    {
        $router = new \Illuminate\Routing\Router(app('events'), app());
        $router->get('fixture-one', fn () => 'fixture')->name('local-marketplace.connection');
        $router->get('fixture-two', fn () => 'fixture')->name('local-marketplace.connection');
        $source = new \App\Marketplace\LocalMarketplaceRouteManifest(app(\Splicewire\Beam\Source\ParticleRouteManifestSource::class), $router);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected exactly one selected marketplace route');
        $source->toArray();
    }
}
