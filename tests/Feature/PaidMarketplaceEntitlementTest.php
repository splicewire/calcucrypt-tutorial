<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Providers\LocalMarketplaceServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use LucaLongo\Licensing\Models\License;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\ProductType;
use PHPUnit\Framework\Attributes\DataProvider;
use Rushing\Commerce\Drivers\FakeDriver;
use Rushing\Commerce\Events\PurchaseCompleted;
use Rushing\Commerce\Exceptions\FakeDriverInProduction;
use Rushing\Commerce\MoneyInManager;
use Splicewire\Beam\Licenser\Events\LicenseRevoked;
use Splicewire\Beam\Licenser\LicenseIssuer;
use Splicewire\Beam\Market\Checkout\Contracts\EntitlementScopeResolver;
use Splicewire\Beam\Market\Checkout\Entitlements;
use Splicewire\Beam\Market\Checkout\Exceptions\CheckoutDeclined;
use Splicewire\Beam\Market\Checkout\Exceptions\ListingRequiresEntitlement;
use Splicewire\Beam\Market\Checkout\PurchaseListing;
use Splicewire\Beam\Market\Data\InstalledExtensionData;
use Splicewire\Beam\Market\Data\MarketExtensionData;
use Splicewire\Beam\Market\Extensions\InstallListing;
use Splicewire\Beam\Market\Models\ListingEntitlement;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\Models\ProductVariant;
use Splicewire\Beam\Market\Models\Seller;
use Tests\TestCase;

class PaidMarketplaceEntitlementTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Entitlements::class)) {
            $this->markTestSkipped('Optional paid Marketplace composition is absent.');
        }
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32)), 'commerce.driver' => 'fake', 'beam.market.checkout.driver' => 'fake']);
        Language::factory()->create(['code' => 'en', 'default' => true]);
        Currency::factory()->create(['code' => 'USD', 'default' => true]);
    }

    private function listing(int $amount = 1900): Product
    {
        $seller = Seller::create(['tenant_id' => 'paid-unit-team', 'name' => 'Sandbox fixture seller', 'payout_gateway' => 'sandbox', 'payout_account_id' => 'sandbox-only']);
        $type = ProductType::firstOrFail();
        $listing = Product::create(['product_type_id' => $type->id, 'status' => 'draft', 'name' => ['en' => 'Sandbox paid listing']]);
        $listing->market()->create(['seller_id' => $seller->id]);
        $tier = ProductVariant::factory()->create(['product_id' => $listing->id, 'sku' => 'PAID-'.$listing->id]);
        $tier->prices()->create(['currency_id' => Currency::where('code', 'USD')->firstOrFail()->id, 'price' => $amount, 'min_quantity' => 1]);

        return $listing->fresh();
    }

    public static function revocationSurfaces(): array
    {
        return [['read'], ['key'], ['acquire']];
    }

    #[DataProvider('revocationSurfaces')]
    public function test_real_issuer_revocation_denies_stale_active_read_acquisition_and_key_projection(string $surface): void
    {
        $buyer = User::factory()->teamMember()->create();
        $listing = $this->listing();
        $this->actingAs($buyer);
        $entitlement = app(PurchaseListing::class)($listing, $buyer);
        $installed = app(InstallListing::class)($listing, 'catalog', $buyer);
        $before = $entitlement->fresh()->getAttributes();
        Event::fake([LicenseRevoked::class]);
        app(LicenseIssuer::class)->revoke($entitlement->license_id);
        $this->assertSame(ListingEntitlement::STATUS_ACTIVE, $entitlement->fresh()->status);
        $this->assertSame($before, $entitlement->fresh()->getAttributes());
        if ($surface === 'read') {
            $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
            $this->assertFalse(MarketExtensionData::project($listing->fresh())->isEntitled);
        } elseif ($surface === 'key') {
            $this->assertNull(InstalledExtensionData::project($installed->fresh())->entitlement?->licenseKey);
        } else {
            $this->expectException(ListingRequiresEntitlement::class);
            app(InstallListing::class)($listing, 'catalog', $buyer);
        }
    }

    public function test_missing_and_wrong_product_real_licenses_fail_closed(): void
    {
        $buyer = User::factory()->teamMember()->create();
        $listing = $this->listing();
        $entitlement = app(PurchaseListing::class)($listing, $buyer);
        $wrong = app(LicenseIssuer::class)->issue(licensable: $this->listing());
        foreach (['missing-license', $wrong->id] as $uid) {
            $entitlement->update(['license_id' => $uid]);
            $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
        }
    }

    public function test_sequential_duplicate_purchase_and_decline_do_not_mint_extra_licenses(): void
    {
        $buyer = User::factory()->teamMember()->create();
        $listing = $this->listing();
        $captured = 0;
        Event::listen(PurchaseCompleted::class, function () use (&$captured): void {
            $captured++;
        });
        $one = app(PurchaseListing::class)($listing, $buyer);
        $licenses = License::count();
        $two = app(PurchaseListing::class)($listing, $buyer);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, $captured);
        $this->assertSame($licenses, License::count());
        try {
            app(PurchaseListing::class)($this->listing(66602), $buyer);
            $this->fail('The sandbox decline must not grant a purchase.');
        } catch (CheckoutDeclined $e) {
            $this->assertSame('failed', $e->purchase->payment->status->value);
        }
        $this->assertSame(1, ListingEntitlement::count());
        $this->assertSame(1, $captured);
        $this->assertSame($licenses, License::count());
    }

    public function test_real_revocation_preserves_other_buyer_and_same_buyer_in_another_scope(): void
    {
        $scope = new class implements EntitlementScopeResolver
        {
            public string $current = 'team:a';

            public function scopeRef(): ?string
            {
                return $this->current;
            }
        };
        app()->instance(EntitlementScopeResolver::class, $scope);
        $buyer = User::factory()->teamMember()->create();
        $second = User::factory()->teamMember()->create();
        $listing = $this->listing();
        $one = app(PurchaseListing::class)($listing, $buyer);
        $two = app(PurchaseListing::class)($listing, $second);
        $scope->current = 'team:b';
        $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
        $three = app(PurchaseListing::class)($listing, $buyer);
        app(LicenseIssuer::class)->revoke($one->license_id);
        $this->assertSame('revoked', $one->fresh()->status);
        $this->assertSame($three->id, app(Entitlements::class)->activeFor($listing, $buyer)?->id);
        $scope->current = 'team:a';
        $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
        $this->assertSame($two->id, app(Entitlements::class)->activeFor($listing, $second)?->id);
        $this->assertSame($one->payment_ref, $one->fresh()->payment_ref);
    }

    public function test_public_purchase_and_root_revoke_use_declared_permissions_and_real_issuer(): void
    {
        config(['local-marketplace.enabled' => true]);
        (new LocalMarketplaceServiceProvider(app()))->boot();
        Route::middleware('web')->group(base_path('routes/local-marketplace.php'));
        $buyer = User::factory()->teamMember()->create();
        $listing = $this->listing();
        $listing->update(['status' => 'published']);
        $buy = '/api/local-marketplace/catalog/'.$listing->id.'/purchase';
        $this->actingAs($buyer)->postJson($buy)->assertForbidden();
        setPermissionsTeamId($buyer->current_team_id);
        $buyer->givePermissionTo(Permission::findOrCreate('marketplace.buyer', 'web'));
        $this->postJson($buy, ['buyer_ref' => 'forged', 'scope_ref' => 'team:foreign', 'amount' => 1])->assertUnprocessable();
        $this->assertSame(0, ListingEntitlement::count());
        $response = $this->postJson($buy)->assertOk();
        $grant = ListingEntitlement::firstOrFail();
        $this->assertSame((string) $buyer->id, $grant->buyer_ref);
        $this->assertSame('team:'.$buyer->current_team_id, $grant->scope_ref);
        $revoke = '/api/local-marketplace/catalog/'.$listing->id.'/revoke-entitlement';
        $this->postJson($revoke, ['entitlementId' => $grant->id])->assertForbidden();
        $root = User::factory()->teamMember()->create();
        setPermissionsTeamId(null);
        $root->assignRole(Role::findOrCreate('Root', 'web'));
        $this->actingAs($root->fresh());
        $this->postJson($revoke, ['entitlementId' => $grant->id])->assertOk()
            ->assertJsonPath('data.status', 'revoked')->assertJsonMissingPath('data.licenseKey');
        $this->assertSame('revoked', $grant->fresh()->status);
        $this->assertFalse(License::where('uid', $grant->license_id)->firstOrFail()->isUsable());
        $this->postJson($revoke, ['entitlementId' => $grant->id])->assertOk()->assertJsonPath('data.alreadyRevoked', true);
        $this->actingAs($buyer->fresh())->postJson('/api/local-marketplace/catalog/'.$listing->id.'/install')->assertForbidden();
        $this->assertSame($grant->payment_ref, $grant->fresh()->payment_ref);
    }

    public function test_unresolved_step_up_and_production_fake_never_grant(): void
    {
        $listing = $this->listing();
        $buyer = User::factory()->teamMember()->create();
        FakeDriver::fakeRequiresAction();
        try {
            app(PurchaseListing::class)($listing, $buyer);
            $this->fail('Step-up must not grant a license.');
        } catch (CheckoutDeclined) {
            $this->assertSame(0, ListingEntitlement::count());
            $this->assertSame(0, License::count());
        } finally {
            FakeDriver::clearFakes();
        }
        app()['env'] = 'production';
        app(MoneyInManager::class)->forgetDrivers();
        try {
            app(PurchaseListing::class)($listing, $buyer);
            $this->fail('Fake payment must refuse production.');
        } catch (FakeDriverInProduction) {
            $this->assertSame(0, ListingEntitlement::count());
            $this->assertSame(0, License::count());
        } finally {
            app()['env'] = 'testing';
            app(MoneyInManager::class)->forgetDrivers();
        }
    }

    public function test_expired_authoritative_license_is_not_an_effective_entitlement(): void
    {
        $listing = $this->listing();
        $buyer = User::factory()->teamMember()->create();
        $grant = app(PurchaseListing::class)($listing, $buyer);
        $license = License::where('uid', $grant->license_id)->firstOrFail();
        $license->update(['expires_at' => now()->subDays(60)]);
        $before = $grant->fresh()->getAttributes();
        $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
        $this->assertSame($before, $grant->fresh()->getAttributes());
    }

    public function test_buyer_install_requires_the_actual_effective_paid_subject_and_preserves_operator_free_install(): void
    {
        config(['local-marketplace.enabled' => true]);
        (new LocalMarketplaceServiceProvider(app()))->boot();
        Route::middleware('web')->group(base_path('routes/local-marketplace.php'));
        $buyer = User::factory()->teamMember()->create();
        setPermissionsTeamId($buyer->current_team_id);
        $buyer->givePermissionTo(Permission::findOrCreate('marketplace.buyer', 'web'));
        $free = $this->listing(0);
        $free->update(['status' => 'published']);
        $paid = $this->listing();
        $paid->update(['status' => 'published']);
        $gate = Gate::forUser($buyer);
        $this->assertFalse($gate->allows('market-extensions.install', $free));
        $this->assertFalse($gate->allows('market-extensions.install', $paid));
        $this->actingAs($buyer)->postJson('/api/local-marketplace/catalog/'.$free->id.'/install')->assertForbidden();
        $this->postJson('/api/local-marketplace/catalog/'.$paid->id.'/install')->assertForbidden();
        $this->assertDatabaseCount('beam_market_installed_extensions', 0);
        $grant = app(PurchaseListing::class)($paid, $buyer);
        $this->assertTrue($gate->allows('market-extensions.install', $paid));
        $this->postJson('/api/local-marketplace/catalog/'.$paid->id.'/install')->assertOk();
        app(LicenseIssuer::class)->revoke($grant->license_id);
        $this->assertFalse($gate->allows('market-extensions.install', $paid));
        $buyer->givePermissionTo(Permission::findOrCreate('marketplace.host-operator', 'web'));
        $this->assertTrue($gate->allows('market-extensions.install', $free));
        $this->postJson('/api/local-marketplace/catalog/'.$free->id.'/install')->assertOk();
        $install = \Splicewire\Beam\Market\Models\InstalledExtension::where('product_id', $free->id)->firstOrFail();
        $install->update(['removal_requested_at' => now()]);
        $before = $install->fresh()->getAttributes();
        $buyer->revokePermissionTo(Permission::findOrCreate('marketplace.host-operator', 'web'));
        $this->postJson('/api/local-marketplace/catalog/'.$free->id.'/install')->assertForbidden();
        $this->assertSame($before, $install->fresh()->getAttributes());
    }

    public function test_legacy_null_license_key_fails_closed_without_losing_history(): void
    {
        $listing = $this->listing();
        $buyer = User::factory()->teamMember()->create();
        $grant = app(PurchaseListing::class)($listing, $buyer);
        $grant->update(['license_key' => null]);
        $before = $grant->fresh()->getAttributes();
        $this->assertNull(app(Entitlements::class)->activeFor($listing, $buyer));
        $this->assertSame($before, $grant->fresh()->getAttributes());
    }

    public function test_payment_sandbox_provider_refuses_production(): void
    {
        config(['local-marketplace.enabled' => true, 'local-marketplace.payment_sandbox' => true]);
        app()['env'] = 'production';
        try {
            (new LocalMarketplaceServiceProvider(app()))->boot();
            $this->fail('The local sandbox provider must refuse production.');
        } catch (\LogicException $e) {
            $this->assertSame('The local payment/payout sandbox cannot run in production.', $e->getMessage());
        } finally {
            app()['env'] = 'testing';
        }
    }
}
