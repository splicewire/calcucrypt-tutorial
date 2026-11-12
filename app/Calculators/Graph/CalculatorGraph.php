<?php

declare(strict_types=1);

namespace App\Calculators\Graph;

use App\Calculators\Expression\ExpressionException;

/**
 * Hydrates a calculator's `graph` JSON and answers structural questions about it:
 * topological order and which nodes are refs. Locus (client vs server) falls out of the
 * dep structure — only `ref` nodes touch the server; everything else is client-evaluable
 * once refs are resolved into numbers. Mirrors resources/js/calculators/graph.ts.
 */
final class CalculatorGraph
{
    /** @var array<string, array<string, mixed>> node-id → node */
    private array $nodes = [];

    /**
     * @param  array<string, mixed>  $graph
     */
    public function __construct(array $graph)
    {
        foreach ($graph['nodes'] ?? [] as $node) {
            $this->nodes[$node['id']] = $node;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function refNodes(): array
    {
        return array_filter($this->nodes, fn (array $node): bool => ($node['kind'] ?? null) === 'ref');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function inputNodes(): array
    {
        return array_filter($this->nodes, fn (array $node): bool => ($node['kind'] ?? null) === 'input');
    }

    /**
     * Dependency order (a node appears after all of its deps). Kahn's algorithm; a cycle
     * is a malformed graph and throws.
     *
     * @return list<string>
     */
    public function topoOrder(): array
    {
        $indegree = [];
        $dependents = [];
        foreach ($this->nodes as $id => $node) {
            $indegree[$id] ??= 0;
            foreach ($node['deps'] ?? [] as $dep) {
                $dependents[$dep][] = $id;
                $indegree[$id]++;
            }
        }

        $queue = [];
        foreach ($indegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($dependents[$id] ?? [] as $dependent) {
                if (--$indegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($order) !== count($this->nodes)) {
            throw new ExpressionException('Calculator graph has a dependency cycle.');
        }

        return $order;
    }
}
