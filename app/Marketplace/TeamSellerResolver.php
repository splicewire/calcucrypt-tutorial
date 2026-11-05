<?php

namespace App\Marketplace;

use Splicewire\Beam\Market\Models\Seller;
use Splicewire\Beam\Market\RepoAuthorization\Contracts\CurrentSellerResolver;

final class TeamSellerResolver implements CurrentSellerResolver
{
    public function __construct(private TeamMarketplaceContext $context) {}

    public function resolve(mixed $actor): ?Seller
    {
        if (! $this->context->mayCreate($actor)) {
            return null;
        }

        $team = $this->context->team($actor);

        return Seller::query()->firstOrCreate(
            ['tenant_id' => 'team:'.$team->getKey()],
            ['name' => $team->name],
        );
    }
}
