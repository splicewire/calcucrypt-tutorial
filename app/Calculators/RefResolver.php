<?php

declare(strict_types=1);

namespace App\Calculators;

use App\Data\FactData;
use App\Facts\FactStore;

/**
 * Resolves one `ref` node into a Live Fact — the ONLY I/O seam in calculator evaluation.
 * A ref names its connector + metric and where its symbol comes from (`symbolFrom`, an
 * input id like `asset`, or a static `symbol`).
 */
final class RefResolver
{
    public function __construct(private readonly FactStore $store) {}

    /**
     * @param  array<string, mixed>  $refNode
     * @param  array<string, mixed>  $inputs
     */
    public function resolve(array $refNode, array $inputs): ?FactData
    {
        $ref = $refNode['ref'] ?? [];

        $symbol = isset($ref['symbolFrom'])
            ? ($inputs[$ref['symbolFrom']] ?? null)
            : ($ref['symbol'] ?? null);

        return $this->resolveSymbol($ref, $symbol);
    }

    /**
     * Resolve one Fact per row of a collection — a vector ref (`over`). Each row supplies its
     * own symbol via `symbolFrom` (a collection field); rows are returned in order, with null
     * where a row's symbol is blank or could not be priced.
     *
     * @param  array<string, mixed>  $refNode
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<?FactData>
     */
    public function resolveVector(array $refNode, array $rows): array
    {
        $ref = $refNode['ref'] ?? [];
        $symbolFrom = $ref['symbolFrom'] ?? null;

        $facts = [];
        foreach ($rows as $row) {
            $symbol = is_array($row) ? ($row[$symbolFrom] ?? null) : null;
            $facts[] = $this->resolveSymbol($ref, $symbol);
        }

        return $facts;
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function resolveSymbol(array $ref, mixed $symbol): ?FactData
    {
        if ($symbol === null || $symbol === '') {
            return null;
        }

        $connector = $ref['connector'] ?? config('facts.default_connector');
        $metric = $ref['metric'] ?? config('facts.default_metric');

        return $this->store->resolve((string) $connector, (string) $metric, (string) $symbol);
    }
}
