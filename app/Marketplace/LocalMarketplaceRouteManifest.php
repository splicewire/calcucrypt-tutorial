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
            if (isset($manifest[$name])) {
                throw new \LogicException('Duplicate marketplace manifest route: '.$name);
            }
            $matches = [];
            foreach ($this->router->getRoutes() as $candidate) {
                if ($candidate->getName() === $name) {
                    $matches[] = $candidate;
                }
            }
            if (count($matches) !== 1) {
                throw new \LogicException('Expected exactly one selected marketplace route: '.$name);
            }
            $route = $matches[0];
            $manifest[$name] = [
                'path' => ltrim($route->uri(), '/'),
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'visibility' => 'internal',
            ];
        }

        return $manifest;
    }
}
