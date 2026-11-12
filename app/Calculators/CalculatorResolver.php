<?php

declare(strict_types=1);

namespace App\Calculators;

use App\Calculators\Graph\CalculatorGraph;
use App\Data\CalculatorResolutionData;
use App\Data\FactData;
use App\Models\Calculator;

/**
 * The deep façade every controller calls to ground a Calculator. It walks the graph's ref
 * nodes, resolves each into a Live Fact through the RefResolver, and returns the numbers +
 * provenance the client needs to evaluate the rest of the DAG locally. It never evaluates
 * the arithmetic itself — that is the client's job (and the shared-grammar guarantee makes
 * the client's numbers trustworthy).
 */
final class CalculatorResolver
{
    public function __construct(private readonly RefResolver $refResolver) {}

    /**
     * @param  array<string, mixed>  $inputs  user-entered input values (partial; defaults fill the rest)
     */
    public function resolve(Calculator $calculator, array $inputs = []): CalculatorResolutionData
    {
        $graph = new CalculatorGraph($calculator->graph);
        $inputs = $this->withDefaults($graph, $inputs);

        $facts = [];
        $seedEnv = [];
        $seedVectors = [];
        $vectorFacts = [];

        foreach ($graph->refNodes() as $id => $node) {
            // A vector ref resolves one Fact per row of the collection it iterates.
            if (isset($node['over'])) {
                $rows = $this->rowsFor($inputs, (string) $node['over']);
                $rowFacts = $this->refResolver->resolveVector($node, $rows);

                $vectorFacts[$id] = $rowFacts;
                $seedVectors[$id] = array_map(fn (?FactData $fact): ?float => $fact?->value, $rowFacts);

                continue;
            }

            $fact = $this->refResolver->resolve($node, $inputs);
            if ($fact !== null) {
                $facts[$id] = $fact;
                $seedEnv[$id] = $fact->value;
            }
        }

        return new CalculatorResolutionData(
            facts: $facts,
            seedEnv: $seedEnv,
            seedVectors: $seedVectors,
            vectorFacts: $vectorFacts,
        );
    }

    /**
     * The collection rows for a given collection node id, from user input or its control
     * default. Tolerates a JSON-encoded string (query params over a partial reload).
     *
     * @param  array<string, mixed>  $inputs
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(array $inputs, string $collectionId): array
    {
        $rows = $inputs[$collectionId] ?? [];
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : [];
        }

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Fill unspecified inputs from their control defaults so a ref always has a symbol.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function withDefaults(CalculatorGraph $graph, array $inputs): array
    {
        foreach ($graph->nodes() as $id => $node) {
            $kind = $node['kind'] ?? null;
            if (($kind === 'input' || $kind === 'collection')
                && ! array_key_exists($id, $inputs)
                && isset($node['control']['default'])
            ) {
                $inputs[$id] = $node['control']['default'];
            }
        }

        return $inputs;
    }
}
