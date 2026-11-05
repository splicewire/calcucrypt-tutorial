<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\ProductType;
use Splicewire\Beam\Licenser\LicenseIssuer;
use Splicewire\Beam\Market\Artifacts\Contracts\ExtensionArtifactSource;
use Splicewire\Beam\Market\Artifacts\Support\LocalGitExtensionArtifactSource;
use Splicewire\Beam\Market\Checkout\PurchaseListing;
use Splicewire\Beam\Market\Creator\PublishExtensionRelease;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\Models\ProductVariant;
use Splicewire\Beam\Market\Models\Seller;
use Splicewire\Beam\Market\Models\SellerRepoAuthorization;
use Splicewire\Beam\Market\Review\DecideListingReview;
use Splicewire\Beam\Market\Review\SubmitListingForReview;
use Splicewire\Beam\Market\Review\TakedownListing;
use Splicewire\Beam\MarketPackages\Models\PackageListing;
use Splicewire\Beam\MarketPackages\Models\PackageRelease;
use Tests\TestCase;

class LicensedComposerDistributionTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(PackageListing::class) || ! class_exists(Product::class)) {
            $this->markTestSkipped('Optional licensed Registry composition is absent.');
        }
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32)), 'local-marketplace.enabled' => true, 'local-marketplace.payment_sandbox' => true]);
        (new \App\Providers\LocalMarketplaceServiceProvider(app()))->boot();
        Language::factory()->create(['code' => 'en', 'default' => true]);
        Currency::factory()->create(['code' => 'USD', 'default' => true]);
        $repo = getenv('SERVICE_DESK_REGISTRY_ARTIFACT_REPO');
        $this->assertIsString($repo, 'Set the owned exact271 Git artifact clone for this focused composition.');
        config(['beam.market.artifacts.local_repos' => ['splicewire/service-desk-sla' => $repo]]);
        app()->bind(ExtensionArtifactSource::class, fn () => new LocalGitExtensionArtifactSource);
        $this->actingAs(User::factory()->teamMember()->create());
    }

    private function reviewedListing(bool $review = true): Product
    {
        $seller = Seller::create(['tenant_id' => 'registry-focused-team', 'name' => 'Local registry fixture seller']);
        $type = ProductType::query()->where('name', 'Beam Extension')->first() ?? ProductType::factory()->create(['name' => 'Beam Extension']);
        $listing = Product::create(['product_type_id' => $type->id, 'status' => 'draft', 'name' => ['en' => 'Licensed SLA']]);
        $listing->market()->create(['seller_id' => $seller->id, 'repo_full_name' => 'splicewire/service-desk-sla']);
        // This fixture supplies repo-admission context only; public proof exercises the actual simulated callback.
        SellerRepoAuthorization::create(['seller_id' => $seller->id, 'installation_id' => 220001, 'repos' => [['id' => 220001, 'full_name' => 'splicewire/service-desk-sla']], 'status' => 'active', 'authorized_at' => now()]);
        $tier = ProductVariant::factory()->create(['product_id' => $listing->id, 'sku' => 'REGISTRY-'.$listing->id]);
        $tier->prices()->create(['currency_id' => Currency::where('code', 'USD')->firstOrFail()->id, 'price' => 1900, 'min_quantity' => 1]);
        app(PublishExtensionRelease::class)($listing->fresh(), 'v0.1.1', 'Exact local tagged archive.');
        $row = PackageListing::where('market_product_id', $listing->market->id)->firstOrFail();
        $this->assertFalse($row->resolvable);
        $release = PackageRelease::where('package_listing_id', $row->id)->firstOrFail();
        $this->assertTrue(Storage::disk($release->dist_disk)->exists($release->dist_path));
        if (! $review) {
            return $listing->fresh();
        }

        app(SubmitListingForReview::class)($listing->fresh());
        $this->assertTrue(app(DecideListingReview::class)->approve($listing->market()->firstOrFail())->applied);
        $this->assertSame('published', (string) $listing->fresh()->status);
        $this->assertNotNull($listing->market()->firstOrFail()->license_id);
        $this->assertTrue($row->fresh()->resolvable, 'First approval must unlock without a buyer issue masking event order.');

        return $listing->fresh();
    }

    private function headers(string $key): array
    {
        return ['Authorization' => 'Basic '.base64_encode('composer:'.$key)];
    }

    public function test_revoking_buyer_a_does_not_disable_buyer_b_registry_downloads(): void
    {
        $listing = $this->reviewedListing();
        $a = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $b = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $this->withHeaders($this->headers($b->license_key))->getJson('/registry/packages.json')->assertOk();
        app(LicenseIssuer::class)->revoke($a->license_id);
        $this->withHeaders($this->headers($a->license_key))->getJson('/registry/packages.json')->assertUnauthorized();
        $this->withHeaders($this->headers($b->license_key))->getJson('/registry/packages.json')->assertOk();
    }

    public function test_later_product_license_issue_cannot_resurrect_a_withdrawn_registry_listing(): void
    {
        $listing = $this->reviewedListing();
        $b = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $this->assertTrue(app(TakedownListing::class)->selfUnpublish($listing)->applied);
        $this->withHeaders($this->headers($b->license_key))->getJson('/registry/packages.json')->assertForbidden();
        // Domain-level event isolation, not a successful public purchase after withdrawal.
        app(LicenseIssuer::class)->issue(licensable: $listing, meta: ['kind' => 'buyer-entitlement']);
        $this->withHeaders($this->headers($b->license_key))->getJson('/registry/packages.json')->assertForbidden();
    }

    public function test_reinstatement_uses_new_uid_and_delayed_old_events_cannot_disable_it(): void
    {
        $listing = $this->reviewedListing();
        $buyer = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $old = app(LicenseIssuer::class)->find($listing->market()->firstOrFail()->license_id);
        $this->assertTrue(app(TakedownListing::class)->selfUnpublish($listing)->applied);
        $this->assertTrue(app(\Splicewire\Beam\Market\Review\DecideTakedown::class)->reinstate($listing->market()->firstOrFail())->applied);
        $this->assertNotSame($old->id, $listing->market()->firstOrFail()->license_id);
        event(new \Splicewire\Beam\Licenser\Events\LicenseRevoked($old));
        event(new \Splicewire\Beam\Licenser\Events\LicenseIssued($old));
        $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertOk();
    }

    public function test_missed_notification_and_stale_true_cache_cannot_override_persisted_withdrawal(): void
    {
        $listing = $this->reviewedListing();
        $buyer = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $listing->forceFill(['status' => \Lunar\Core\States\Product\Draft::class])->save();
        $this->assertTrue(PackageListing::firstOrFail()->resolvable);
        $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertForbidden();
    }

    public function test_publication_signal_waits_for_commit_and_outer_rollback_emits_nothing(): void
    {
        $listing = $this->reviewedListing();
        $listing->forceFill(['status' => \Lunar\Core\States\Product\Draft::class])->save();
        $events = [];
        \Illuminate\Support\Facades\Event::listen(\Splicewire\Beam\Market\PackageDistribution\Events\ListingDistributionChanged::class,
            function ($event) use (&$events) {
                $events[] = $event->productId;
            });
        $db = $listing->getConnection();
        $db->beginTransaction();
        try {
            app(\Splicewire\Beam\Market\Actions\PublishListing::class)($listing);
            $this->assertSame([], $events);
        } finally {
            $db->rollBack();
        }
        $this->assertSame([], $events);
        $this->assertSame('draft', (string) $listing->fresh()->status);
        $db->transaction(fn () => app(\Splicewire\Beam\Market\Actions\PublishListing::class)($listing->fresh()));
        $this->assertSame([$listing->getKey()], $events);
    }

    public function test_distribution_license_cancellation_denies_even_if_notification_is_missed(): void
    {
        $listing = $this->reviewedListing();
        $buyer = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $license = \LucaLongo\Licensing\Models\License::where('uid', $listing->market()->firstOrFail()->license_id)->firstOrFail();
        $license->cancel();
        $this->assertTrue(PackageListing::firstOrFail()->resolvable);
        $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertForbidden();
    }

    public function test_expiry_and_grace_are_checked_for_presented_and_distribution_licenses(): void
    {
        $listing = $this->reviewedListing();
        $buyer = app(PurchaseListing::class)($listing, User::factory()->teamMember()->create());
        $distribution = \LucaLongo\Licensing\Models\License::where('uid', $listing->market()->firstOrFail()->license_id)->firstOrFail();
        $credential = \LucaLongo\Licensing\Models\License::where('uid', $buyer->license_id)->firstOrFail();
        foreach ([$credential, $distribution] as $license) {
            $license->forceFill(['expires_at' => now()->subMinute()])->save();
            $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertStatus($license->is($credential) ? 401 : 403);
            $license->forceFill(['status' => \LucaLongo\Licensing\Enums\LicenseStatus::Grace, 'expires_at' => now()->subMinute()])->save();
            $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertOk();
            $license->forceFill(['expires_at' => now()->subYears(10)])->save();
            $this->withHeaders($this->headers($buyer->license_key))->getJson('/registry/packages.json')->assertStatus($license->is($credential) ? 401 : 403);
            $license->forceFill(['status' => \LucaLongo\Licensing\Enums\LicenseStatus::Active, 'expires_at' => null])->save();
        }
    }

    public function test_failed_publication_does_not_turn_an_issued_license_into_distribution(): void
    {
        $listing = $this->reviewedListing();
        $listing->forceFill(['status' => \Lunar\Core\States\Product\Draft::class])->save();
        $issued = app(LicenseIssuer::class)->issue(licensable: $listing);
        $listing->market()->firstOrFail()->forceFill(['license_id' => $issued->id])->save();
        $onboarding = \Mockery::mock(\Splicewire\Beam\Market\Payout\PayoutOnboarding::class);
        $onboarding->shouldReceive('attemptFor')->once()->andThrow(new \RuntimeException('Fixture onboarding failure'));
        try {
            (new \Splicewire\Beam\Market\Actions\PublishListing($onboarding))($listing);
            $this->fail('Expected the actual publication prerequisite failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fixture onboarding failure', $e->getMessage());
        }
        $this->assertNotNull(app(LicenseIssuer::class)->find($issued->id));
        $this->assertSame('draft', (string) $listing->fresh()->status);
        $this->withHeaders($this->headers($issued->licenseKey))->getJson('/registry/packages.json')->assertForbidden();
    }

    public function test_after_commit_listener_failure_does_not_claim_to_undo_committed_publication(): void
    {
        $listing = $this->reviewedListing();
        $listing->forceFill(['status' => \Lunar\Core\States\Product\Draft::class])->save();
        \Illuminate\Support\Facades\Event::listen(\Splicewire\Beam\Market\PackageDistribution\Events\ListingDistributionChanged::class,
            fn () => throw new \RuntimeException('Fixture post-commit listener failure'));
        try {
            $listing->getConnection()->transaction(fn () => app(\Splicewire\Beam\Market\Actions\PublishListing::class)($listing));
            $this->fail('Expected post-commit failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fixture post-commit listener failure', $e->getMessage());
        }
        $this->assertSame(0, $listing->getConnection()->transactionLevel());
        $this->assertSame('published', (string) $listing->fresh()->status);
    }

    public function test_existing_distribution_key_acquisition_remains_compatible_and_scoped(): void
    {
        $listing = $this->reviewedListing();
        $market = $listing->market()->firstOrFail();
        // Existing site-acquisition callers receive the distribution key at issuance, not a buyer row.
        $issued = app(LicenseIssuer::class)->issue(licensable: $listing);
        $market->forceFill(['license_id' => $issued->id])->save();
        app(\Splicewire\Beam\MarketPackages\LicenseGatedPackageDistributionGatewayResolver::class)->reconcile($market);
        $this->withHeaders($this->headers($issued->licenseKey))->getJson('/registry/packages.json')->assertOk();
        $this->withHeaders($this->headers($issued->licenseKey))->getJson('/registry/p2/other/package.json')->assertNotFound();
    }

    public function test_private_release_archive_denies_a_real_product_key_before_review(): void
    {
        $listing = $this->reviewedListing(false);
        $issued = app(LicenseIssuer::class)->issue(licensable: $listing);
        $this->assertNull($listing->market()->firstOrFail()->license_id);
        $this->assertFalse(PackageListing::firstOrFail()->resolvable);
        foreach (['/registry/packages.json', '/registry/p2/splicewire/service-desk-sla.json', '/registry/dist/splicewire/service-desk-sla/0.1.1.zip'] as $path) {
            $this->withHeaders($this->headers($issued->licenseKey))->get($path)->assertForbidden();
        }
    }
}
