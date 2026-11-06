<?php

declare(strict_types=1);

namespace App\Facts\Connectors;

use App\Facts\Contracts\Connector;
use App\Facts\FactObservation;
use Illuminate\Support\Carbon;

/**
 * A keyless, deterministic market source: the tutorial's default connector.
 *
 * Every ref a calculator declares names a connector (`coinmarketcap` in the shipped definitions).
 * With `FACTS_DRIVER=fixture` (the default) `config/facts.php` points every connector name at this
 * class, so the calculators resolve the same prices on every machine, offline, with no API key. The
 * prices are Calcucrypt's own offline baseline (its `CryptSeeder`), observed at one fixed instant.
 *
 * Set `FACTS_DRIVER=live` and `CMC_API_KEY` to price from CoinMarketCap instead.
 */
final class FixtureConnector implements Connector
{
    /** The single instant every fixture price was "observed". */
    public const AS_OF = '2026-11-01T00:00:00Z';

    /** @var array<string, array<string, float>> metric → symbol → value */
    public const VALUES = [
        'spot_usd' => [
            'BTC' => 61000.0,
            'ETH' => 3000.0,
            'SOL' => 140.0,
            'BNB' => 580.0,
            'XRP' => 0.52,
            'ADA' => 0.45,
            'AVAX' => 30.0,
            'DOGE' => 0.12,
        ],
        'market_cap' => [
            'BTC' => 1_200_000_000_000.0,
            'ETH' => 400_000_000_000.0,
            'SOL' => 65_000_000_000.0,
            'BNB' => 85_000_000_000.0,
            'XRP' => 30_000_000_000.0,
            'ADA' => 15_000_000_000.0,
            'AVAX' => 12_000_000_000.0,
            'DOGE' => 18_000_000_000.0,
        ],
    ];

    public function key(): string
    {
        return 'fixture';
    }

    public function fetch(string $metric, array $symbols): array
    {
        $observations = [];
        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            $value = self::VALUES[$metric][$symbol] ?? null;

            if ($value !== null) {
                $observations[$symbol] = new FactObservation(
                    value: $value,
                    source: 'Fixture',
                    asOf: Carbon::parse(self::AS_OF),
                );
            }
        }

        return $observations;
    }
}
