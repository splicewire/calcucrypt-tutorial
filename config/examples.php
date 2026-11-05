<?php

return [
    'service_desk' => [
        // Null enables the example outside production. Explicit true/false wins.
        // Demo users keep the accounts package's own independent default gate.
        'seed' => env('SERVICE_DESK_SEED_EXAMPLE'),
    ],
];
