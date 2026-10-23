<?php

namespace App\Marketplace;

use Splicewire\Beam\Market\Checkout\Contracts\EntitlementScopeResolver;

final class TeamEntitlementScopeResolver implements EntitlementScopeResolver
{
    public function __construct(private TeamMarketplaceContext $context) {}

    public function scopeRef(): ?string
    {
        $team = $this->context->team(auth()->user());

        return $team === null ? null : 'team:'.$team->getKey();
    }
}
