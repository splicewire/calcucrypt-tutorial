<?php

declare(strict_types=1);

namespace App\Facts\Contracts;

use App\Facts\FactObservation;

/**
 * A swappable adapter to one external market source (CONTEXT.md "Connector"). Host
 * vertical vocabulary — this lives in app/, NEVER in any rushing/* engine src. Which
 * connector produced a Fact is recorded on the Fact.
 */
interface Connector
{
    /**
     * The connector's stable key (e.g. "coinmarketcap"), recorded on every Fact it produces.
     */
    public function key(): string;

    /**
     * Fetch one metric for a set of symbols. Keyed by symbol; a symbol the source could not
     * price is simply absent from the result (the FactStore falls back for it).
     *
     * @param  list<string>  $symbols
     * @return array<string, FactObservation>
     */
    public function fetch(string $metric, array $symbols): array;
}
