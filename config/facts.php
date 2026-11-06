<?php

use App\Facts\Connectors\CoinMarketCapConnector;
use App\Facts\Connectors\FixtureConnector;

// `fixture` (the default) prices every ref from the keyless, deterministic FixtureConnector, so
// the tutorial runs offline and every machine sees the same numbers. `live` uses CoinMarketCap and
// needs CMC_API_KEY.
$live = env('FACTS_DRIVER', 'fixture') === 'live';

return [
    /*
    |--------------------------------------------------------------------------
    | Live Fact cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Crypto prices move fast — a Fact is only "fresh" for a short window. Ref
    | resolution reads the cache first, then the latest stored Fact, then the
    | connector. Keep this small.
    |
    */
    'ttl' => (int) env('FACTS_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | The default metric a ref resolves when its node omits one.
    |--------------------------------------------------------------------------
    */
    'default_metric' => 'spot_usd',

    /*
    |--------------------------------------------------------------------------
    | Connector registry (host vocabulary — never engine src)
    |--------------------------------------------------------------------------
    |
    | Keyed by the connector name a ref node references
    | (`ref.connector`). Each maps to a class implementing App\Facts\Contracts\Connector.
    |
    */
    'connectors' => [
        'coinmarketcap' => $live ? CoinMarketCapConnector::class : FixtureConnector::class,
    ],

    'default_connector' => 'coinmarketcap',
];
