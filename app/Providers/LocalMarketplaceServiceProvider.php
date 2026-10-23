<?php

namespace App\Providers;

use App\Http\Middleware\RequireMarketplaceRoot;
use App\Marketplace\CreatorListingPolicy;
use App\Marketplace\TeamEntitlementScopeResolver;
use App\Marketplace\TeamMarketplaceContext;
use App\Marketplace\TeamSellerResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Market\Checkout\Contracts\EntitlementScopeResolver;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\RepoAuthorization\Contracts\CurrentSellerResolver;

final class LocalMarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No optional API/federation/reporting surface is exposed by merely installing the example.
        config([
            'beam.market.extensions.register_routes' => false,
            'beam.market.review_queue.register_routes' => false,
            'beam.market.reporting.register_routes' => false,
            'beam.market.federation.publish.register_routes' => false,
        ]);
    }

    public function boot(): void
    {
        if (! config('local-marketplace.enabled') || ! interface_exists(CurrentSellerResolver::class)) {
            return;
        }

        $this->app->booted(function (): void {
            // Market installs its tenancy defaults during packageBooted; this host has teams.
            $this->app->bind(CurrentSellerResolver::class, TeamSellerResolver::class);
            $this->app->bind(EntitlementScopeResolver::class, TeamEntitlementScopeResolver::class);
            Gate::policy(Product::class, CreatorListingPolicy::class);
            // The starter scans app/Data; explicitly register these existing package declarations.
            $registry = $this->app->make(\Splicewire\Beam\Particle\ParticleResourceRegistry::class);
            foreach ([
                \Splicewire\Beam\Market\Data\MarketSellerData::class,
                \Splicewire\Beam\Market\Data\SellerRepoAuthorizationData::class,
                \Splicewire\Beam\Market\Data\MarketListingData::class,
                \Splicewire\Beam\Market\Data\MarketExtensionData::class,
                \Splicewire\Beam\Market\Data\InstalledExtensionData::class,
            ] as $declaration) {
                $registry->registerClass($declaration);
            }

            foreach (['market-extensions.install', 'installed-extensions.refresh', 'installed-extensions.remove'] as $ability) {
                Gate::define($ability, fn ($actor): bool => app(TeamMarketplaceContext::class)->mayOperate($actor));
            }
        });

        $router = $this->app->make(Router::class);
        if (! isset($router->getMiddleware()['require.root'])) {
            $router->aliasMiddleware('require.root', RequireMarketplaceRoot::class);
        }
    }
}
