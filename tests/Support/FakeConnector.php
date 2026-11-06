<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Facts\Contracts\Connector;
use App\Facts\FactObservation;
use Illuminate\Support\Carbon;

/**
 * A deterministic Connector for tests — the resolver seam is exercised through its
 * interface without ever touching CoinMarketCap.
 */
final class FakeConnector implements Connector
{
    /**
     * @param  array<string, float>  $prices  symbol → price
     */
    public function __construct(private array $prices = ['BTC' => 42000.0, 'ETH' => 2500.0, 'SOL' => 100.0]) {}

    public function key(): string
    {
        return 'coinmarketcap';
    }

    public function fetch(string $metric, array $symbols): array
    {
        $observations = [];
        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            if (isset($this->prices[$symbol])) {
                $observations[$symbol] = new FactObservation(
                    value: $this->prices[$symbol],
                    source: 'FakeSource',
                    asOf: Carbon::parse('2026-07-09T10:00:00Z'),
                );
            }
        }

        return $observations;
    }
}
