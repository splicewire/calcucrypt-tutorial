<?php

declare(strict_types=1);

namespace App\Facts;

use App\Data\FactData;
use App\Facts\Contracts\Connector;
use App\Models\Fact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * The single seam every calculator ref and the feed refresh go through. Resolution order
 * for a live value: short-TTL cache → fresh stored Fact → live connector fetch → last
 * stored Fact flagged stale. It NEVER returns blank while any Fact has ever been recorded
 * — the provenance chip degrades to "as of HH:MM" instead of disappearing.
 */
final class FactStore
{
    private function ttl(): int
    {
        return (int) config('facts.ttl', 60);
    }

    /**
     * Resolve one live value for a calculator ref. Returns null only when the symbol has
     * never been priced by anything (no cache, no stored Fact, no live fetch).
     */
    public function resolve(string $connector, string $metric, string $symbol): ?FactData
    {
        $symbol = strtoupper($symbol);
        $key = Fact::keyFor($connector, $metric, $symbol);

        $cached = Cache::get($this->cacheKey($key));
        if (is_array($cached)) {
            return $this->fromCache($cached);
        }

        $latest = $this->latest($key);
        if ($latest !== null && $latest->as_of->gt(Carbon::now()->subSeconds($this->ttl()))) {
            $this->prime($latest);

            return FactData::fromModel($latest);
        }

        // Stale or missing — try a live fetch for just this symbol.
        $fetched = $this->refresh($connector, $metric, [$symbol]);
        if (isset($fetched[$symbol])) {
            return FactData::fromModel($fetched[$symbol]);
        }

        // Fall back to the last known Fact, flagged stale.
        return $latest !== null ? FactData::fromModel($latest, stale: true) : null;
    }

    /**
     * Pull a metric for a set of symbols from the connector and persist each as a Fact.
     * Used by the feed refresh. A symbol the connector could not price is skipped.
     *
     * @param  list<string>  $symbols
     * @return array<string, Fact> keyed by symbol
     */
    public function refresh(string $connector, string $metric, array $symbols): array
    {
        $observations = $this->connectorFor($connector)->fetch($metric, $symbols);

        $facts = [];
        foreach ($observations as $symbol => $observation) {
            $facts[strtoupper($symbol)] = $this->record($connector, $metric, $symbol, $observation);
        }

        return $facts;
    }

    public function record(string $connector, string $metric, string $symbol, FactObservation $observation): Fact
    {
        $symbol = strtoupper($symbol);

        $fact = Fact::create([
            'key' => Fact::keyFor($connector, $metric, $symbol),
            'connector' => $connector,
            'metric' => $metric,
            'symbol' => $symbol,
            'value' => $observation->value,
            'source' => $observation->source,
            'as_of' => $observation->asOf,
            'raw' => $observation->raw,
        ]);

        $this->prime($fact);

        return $fact;
    }

    public function latest(string $key): ?Fact
    {
        return Fact::query()->where('key', $key)->latest('as_of')->first();
    }

    private function connectorFor(string $name): Connector
    {
        $class = config("facts.connectors.{$name}");
        if ($class === null) {
            throw new InvalidArgumentException("No connector registered for '{$name}'.");
        }

        return app($class);
    }

    private function prime(Fact $fact): void
    {
        Cache::put($this->cacheKey($fact->key), [
            'value' => $fact->floatValue(),
            'source' => $fact->source,
            'asOf' => $fact->as_of->toIso8601String(),
            'connector' => $fact->connector,
            'metric' => $fact->metric,
            'symbol' => $fact->symbol,
        ], $this->ttl());
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function fromCache(array $cached): FactData
    {
        return new FactData(
            value: (float) $cached['value'],
            source: (string) $cached['source'],
            asOf: (string) $cached['asOf'],
            connector: (string) $cached['connector'],
            metric: (string) $cached['metric'],
            symbol: (string) $cached['symbol'],
        );
    }

    private function cacheKey(string $key): string
    {
        return "fact:{$key}";
    }
}
