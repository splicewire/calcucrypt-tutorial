<?php

declare(strict_types=1);

namespace App\Facts\Connectors;

use App\Facts\Contracts\Connector;
use App\Facts\FactObservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The CoinMarketCap adapter. Speaks CMC's `cryptocurrency/quotes/latest` and maps a USD
 * quote into a neutral FactObservation. Host vocabulary — never engine src.
 *
 * Metrics map to a field on CMC's USD quote: `spot_usd` → `price`, `market_cap` →
 * `market_cap`. A single `quotes/latest` call serves both; new metrics add a row to the map.
 */
final class CoinMarketCapConnector implements Connector
{
    /** metric → the field to read off CMC's `quote.USD` object. */
    private const METRIC_FIELDS = [
        'spot_usd' => 'price',
        'market_cap' => 'market_cap',
        'change_pct' => 'percent_change_24h',
    ];

    public function key(): string
    {
        return 'coinmarketcap';
    }

    public function fetch(string $metric, array $symbols): array
    {
        $field = self::METRIC_FIELDS[$metric] ?? null;
        if ($field === null) {
            throw new RuntimeException("CoinMarketCapConnector does not support metric '{$metric}'.");
        }

        $symbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        if ($symbols === []) {
            return [];
        }

        $apiKey = config('services.coinmarketcap.key');
        if (blank($apiKey)) {
            // No key configured — no live facts. The FactStore falls back to the last
            // stored/seeded Fact, so the site degrades rather than breaks.
            return [];
        }

        $response = Http::withHeaders([
            'X-CMC_PRO_API_KEY' => $apiKey,
            'Accept' => 'application/json',
        ])
            ->baseUrl((string) config('services.coinmarketcap.base_url'))
            ->get('/v1/cryptocurrency/quotes/latest', [
                'symbol' => implode(',', $symbols),
                'convert' => 'USD',
            ])
            ->throw()
            ->json();

        return $this->mapQuotes($response['data'] ?? [], $field);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, FactObservation>
     */
    private function mapQuotes(array $data, string $field): array
    {
        $observations = [];

        foreach ($data as $symbol => $coin) {
            // CMC may return a coin as an object or (with certain params) an array of matches.
            $coin = array_is_list($coin) ? ($coin[0] ?? null) : $coin;
            $usd = $coin['quote']['USD'] ?? null;

            if ($usd === null || ! isset($usd[$field])) {
                continue;
            }

            $observations[strtoupper((string) $symbol)] = new FactObservation(
                value: (float) $usd[$field],
                source: 'CoinMarketCap',
                asOf: isset($usd['last_updated']) ? Carbon::parse($usd['last_updated']) : Carbon::now(),
                raw: $usd,
            );
        }

        return $observations;
    }
}
