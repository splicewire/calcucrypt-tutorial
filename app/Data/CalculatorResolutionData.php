<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the server ships for a calculator's grounded leaves: the resolved Facts (for the
 * provenance chips) and a flat `seedEnv` of ref-id → number the client seeds its evaluation
 * environment with. The client then evaluates the whole arithmetic DAG on every keystroke;
 * only changing a ref input (the asset) round-trips for a fresh resolution.
 *
 * A vector ref (`over` a collection) resolves ONE Fact per row: `seedVectors` carries the
 * per-row numbers (null where a row's symbol could not be priced) and `vectorFacts` the
 * per-row Facts, both aligned to the collection's row order.
 */
#[TypeScript]
class CalculatorResolutionData extends Data
{
    /**
     * @param  array<string, FactData>  $facts  scalar ref node-id → resolved Fact
     * @param  array<string, float>  $seedEnv  scalar ref node-id → resolved numeric value
     * @param  array<string, array<int, float|null>>  $seedVectors  vector ref node-id → per-row values
     * @param  array<string, array<int, FactData|null>>  $vectorFacts  vector ref node-id → per-row Facts
     */
    public function __construct(
        public array $facts,
        public array $seedEnv,
        public array $seedVectors = [],
        public array $vectorFacts = [],
    ) {}
}
