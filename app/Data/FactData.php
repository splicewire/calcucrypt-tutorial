<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Fact;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The provenance-chip contract: the wire shape of a resolved Live Fact. Freshness
 * (`asOf`) and source (`source`/`connector`) travel with the number so the frontend can
 * cite every figure without a second lookup. `stale` flags a degraded fallback (the last
 * known Fact was served because a fresh fetch was unavailable).
 */
#[TypeScript]
class FactData extends Data
{
    public function __construct(
        public float $value,
        public string $source,
        public string $asOf,
        public string $connector,
        public string $metric,
        public string $symbol,
        public bool $stale = false,
        public ?float $changePercent24h = null,
    ) {}

    public static function fromModel(Fact $fact, bool $stale = false): self
    {
        $change = $fact->raw['percent_change_24h'] ?? null;

        return new self(
            value: $fact->floatValue(),
            source: $fact->source,
            asOf: $fact->as_of->toIso8601String(),
            connector: $fact->connector,
            metric: $fact->metric,
            symbol: $fact->symbol,
            stale: $stale,
            changePercent24h: is_numeric($change) ? (float) $change : null,
        );
    }
}
