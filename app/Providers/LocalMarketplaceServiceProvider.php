<?php

namespace App\Providers;

use App\Http\Middleware\RequireMarketplaceRoot;
use App\Marketplace\CreatorListingPolicy;
use App\Marketplace\LocalMarketplaceRouteManifest;
use App\Marketplace\SandboxPayoutGateway;
use App\Marketplace\TeamEntitlementScopeResolver;
use App\Marketplace\TeamMarketplaceContext;
use App\Marketplace\TeamSellerResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;
use Splicewire\Beam\Market\Checkout\Contracts\EntitlementScopeResolver;
use Splicewire\Beam\Market\Checkout\Entitlements;
use Splicewire\Beam\Market\Data\InstalledExtensionData;
use Splicewire\Beam\Market\Data\MarketExtensionData;
use Splicewire\Beam\Market\Data\MarketListingData;
use Splicewire\Beam\Market\Data\MarketSellerData;
use Splicewire\Beam\Market\Data\SellerRepoAuthorizationData;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\Payout\Contracts\PayoutGatewayResolver;
use Splicewire\Beam\Market\RepoAuthorization\Contracts\CurrentSellerResolver;
use Splicewire\Beam\Particle\ParticleResourceRegistry;
use Splicewire\Beam\Source\ParticleRouteManifestSource;

final class LocalMarketplaceServiceProvider extends ServiceProvider
{
    public const MARKET_PACKAGE = 'splicewire/laravel-beam-market';

    public function register(): void
    {
        // A host that requires beam-market itself owns that composition; the example leaves the package's surfaces alone.
        if (self::hostRequiresMarket($this->app->basePath('composer.json'))) {
            return;
        }

        // No optional API/federation/reporting surface is exposed by merely installing the example.
        config([
            'beam.market.extensions.register_routes' => false,
            'beam.market.review_queue.register_routes' => false,
            'beam.market.reporting.register_routes' => false,
            'beam.market.federation.publish.register_routes' => false,
        ]);
    }

    /**
     * Whether the host's own manifest requires beam-market. The caller passes the host's `composer.json`, never the
     * alternate manifest `COMPOSER` may name: the example's own context (`composer.market.json`) adds beam-market there,
     * and the example owns that composition.
     */
    public static function hostRequiresMarket(string $manifest): bool
    {
        $decoded = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

        return is_array($decoded) && is_array($decoded['require'] ?? null)
            && array_key_exists(self::MARKET_PACKAGE, $decoded['require']);
    }

    public function boot(): void
    {
        if (! config('local-marketplace.enabled') || ! interface_exists(CurrentSellerResolver::class)) {
            return;
        }

        $source = config('beam.client.sources.defaults');
        if (! in_array($source, [ParticleRouteManifestSource::class, LocalMarketplaceRouteManifest::class], true)) {
            throw new \LogicException('This example requires the starter particle route manifest source.');
        }
        config(['beam.client.sources.defaults' => LocalMarketplaceRouteManifest::class]);

        $this->app->booted(function (): void {
            // Market installs its tenancy defaults during packageBooted; this host has teams.
            $this->app->bind(CurrentSellerResolver::class, TeamSellerResolver::class);
            $this->app->bind(EntitlementScopeResolver::class, TeamEntitlementScopeResolver::class);
            Gate::policy(Product::class, CreatorListingPolicy::class);
            if (config('local-marketplace.payment_sandbox')) {
                if (! $this->app->environment(['local', 'testing'])) {
                    throw new \LogicException('The local payment/payout sandbox cannot run in production.');
                }
                config(['commerce.driver' => 'fake', 'beam.market.checkout.driver' => 'fake']);
                $this->app->bind(PayoutGatewayResolver::class, SandboxPayoutGateway::class);
            }
            Gate::define('market-extensions.purchase', fn ($actor): bool => app(TeamMarketplaceContext::class)->mayPurchase($actor));
            Gate::define('market-extensions.revoke-entitlement', fn ($actor): bool => app(TeamMarketplaceContext::class)->team($actor) !== null
                && BeamAccounts::isRoot($actor->fresh()));
            Gate::define('market-extensions.install', fn ($actor, ?Product $listing = null): bool => app(TeamMarketplaceContext::class)->mayOperate($actor)
                || ($listing !== null && app(TeamMarketplaceContext::class)->mayPurchase($actor)
                    && ! $listing->isFree()
                    && app(Entitlements::class)->isEntitled($listing, $actor)));
            // The starter scans app/Data; explicitly register these existing package declarations.
            $registry = $this->app->make(ParticleResourceRegistry::class);
            foreach ([
                MarketSellerData::class,
                SellerRepoAuthorizationData::class,
                MarketListingData::class,
                MarketExtensionData::class,
                InstalledExtensionData::class,
            ] as $declaration) {
                $registry->registerClass($declaration);
            }

            foreach (['installed-extensions.refresh', 'installed-extensions.remove'] as $ability) {
                Gate::define($ability, fn ($actor): bool => app(TeamMarketplaceContext::class)->mayOperate($actor));
            }
        });

        $router = $this->app->make(Router::class);
        if (! isset($router->getMiddleware()['require.root'])) {
            $router->aliasMiddleware('require.root', RequireMarketplaceRoot::class);
        }
    }
}
