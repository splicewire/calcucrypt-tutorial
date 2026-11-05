<?php

namespace App\Marketplace;

use App\Models\User;
use Splicewire\Beam\Market\Models\Product;
use Splicewire\Beam\Market\RepoAuthorization\Contracts\CurrentSellerResolver;

final class CreatorListingPolicy
{
    public function __construct(private CurrentSellerResolver $sellers) {}

    // Keep the package's authenticated, row-scoped catalog/creator read contract.
    public function view(User $actor, Product $listing): bool
    {
        return true;
    }

    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function create(User $actor): bool
    {
        return $this->sellers->resolve($actor) !== null;
    }

    public function update(User $actor, Product $listing): bool
    {
        $seller = $this->sellers->resolve($actor);

        return $seller !== null && $listing->market?->seller_id === $seller->getKey();
    }

    public function delete(User $actor, Product $listing): bool
    {
        return false;
    }
}
