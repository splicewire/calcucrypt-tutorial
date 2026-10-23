<?php

namespace App\Marketplace;

use Illuminate\Routing\Router;
use Splicewire\Beam\Source\ParticleRouteManifestSource;
use Splicewire\Beam\Source\RouteManifestSource;

/** Add the two selected bespoke reads to the ordinary particle-generated route map. */
final class LocalMarketplaceRouteManifest implements RouteManifestSource
{
    public function __construct(private ParticleRouteManifestSource $particles, private Router $router) {}

    public function toArray(): array
    {
        $manifest = $this->particles->toArray();
        foreach (['local-marketplace.connection', 'local-marketplace.review.pending'] as $name) {
            $route = $this->router->getRoutes()->getByName($name);
            if ($route === null) {
                throw new \LogicException('Missing selected marketplace route: '.$name);
            }
            $manifest[$name] = [
                'path' => ltrim($route->uri(), '/'),
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'visibility' => 'internal',
            ];
        }

        return $manifest;
    }
}
