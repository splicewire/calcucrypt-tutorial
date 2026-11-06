<?php

use App\Auth\CalcucryptEntitlementResolver;

return [
    // Who holds which entitlement keys: the current team's plan plus Beam's default grants.
    // Every other permission-cascade key keeps its package default.
    'entitlement_resolver' => CalcucryptEntitlementResolver::class,
];
