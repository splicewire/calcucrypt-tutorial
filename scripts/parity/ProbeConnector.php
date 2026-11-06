<?php

declare(strict_types=1);

namespace CalcucryptParity;

use App\Facts\Contracts\Connector;
use App\Facts\FactObservation;
use Illuminate\Support\Carbon;

/**
 * The market source both apps price from during the parity probe: fixed prices at one fixed instant,
 * the same table as the tutorial's FixtureConnector. Installing the same connector in both apps keeps
 * the comparison about the apps' code, not about where their prices come from.
 */
final class ProbeConnector implements Connector
{
    private const AS_OF = '2026-11-01T00:00:00Z';

    /** @var array<string, array<string, float>> */
    private const VALUES = [
        'spot_usd' => ['BTC' => 61000.0, 'ETH' => 3000.0, 'SOL' => 140.0, 'BNB' => 580.0, 'XRP' => 0.52, 'ADA' => 0.45, 'AVAX' => 30.0, 'DOGE' => 0.12],
        'market_cap' => ['BTC' => 1.2e12, 'ETH' => 4.0e11, 'SOL' => 6.5e10, 'BNB' => 8.5e10, 'XRP' => 3.0e10, 'ADA' => 1.5e10, 'AVAX' => 1.2e10, 'DOGE' => 1.8e10],
    ];

    public function key(): string
    {
        return 'parity';
    }

    public function fetch(string $metric, array $symbols): array
    {
        $out = [];
        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            if (isset(self::VALUES[$metric][$symbol])) {
                $out[$symbol] = new FactObservation(self::VALUES[$metric][$symbol], 'Parity', Carbon::parse(self::AS_OF));
            }
        }

        return $out;
    }
}
