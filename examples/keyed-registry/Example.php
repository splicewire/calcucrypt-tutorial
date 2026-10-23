<?php

namespace Examples\KeyedRegistry;

use InvalidArgumentException;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Laravel\Sources\RegistryGraphSource;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\PopulationRequirement;
use Rushing\Popcorn\Registries\RegistryKey;

/** Immutable teaching policy; these labels are NOT authenticated users or roles. */
final readonly class LocalPolicy implements Authorizer
{
    /** @param list<string> $allowed */
    private function __construct(private array $allowed) {}

    public static function fixture(string $name): self
    {
        return match ($name) {
            'reader' => new self([]),
            'operator' => new self(['view-sla']),
            default => throw new InvalidArgumentException('Unknown local policy fixture'),
        };
    }

    public function allows(string $ability, RegistryKey $key): bool
    {
        return in_array($ability, $this->allowed, true);
    }
}

final class Example
{
    public const KEYS = ['desk.automations.requests.list', 'desk.automations.sla.inspect'];

    /** @return BasicRegistry<string> */
    public static function registry(string $fixture): BasicRegistry
    {
        $policy = LocalPolicy::fixture($fixture);
        $registry = new BasicRegistry(new IsRegistry(
            root: 'desk.automations', entryType: 'string',
            onKeyDuplicate: OnKeyDuplicate::Reject,
            populationRequirement: PopulationRequirement::Required,
        ));
        $registry->register('requests.list', 'Request inspection', by: 'example/service-desk');
        $registry->register('sla.inspect', 'SLA inspection', by: 'example/service-desk', ability: 'view-sla');
        // Ability declarations alone do NOT filter: install the actual native Authorizer.
        $registry->authorizeWith($policy);

        return $registry;
    }

    /** @return array<string, mixed> */
    public static function view(string $fixture): array
    {
        return match ($fixture) {
            'reader', 'operator' => ['context' => 'local-policy-fixture (not an authenticated user)', 'fixture' => $fixture, ...self::project(self::registry($fixture))],
            'trusted-tooling-reader' => ['context' => 'TRUSTED SHELL TOOLING ONLY', 'fixture' => 'reader-unfiltered-sample', ...self::project(self::registry('reader'), trustedTooling: true)],
            default => throw new InvalidArgumentException('Unknown local fixture'),
        };
    }

    /** @param BasicRegistry<string> $registry
     * @param  (callable(RegistryGraphSource): GraphStore)|null  $factory  Test seam; CLI always uses the native factory.
     * @return array<string, mixed>
     */
    public static function project(BasicRegistry $registry, bool $trustedTooling = false, ?callable $factory = null): array
    {
        $declaration = $registry->declaration();
        $setupKeys = array_map(fn (RegistryKey $key): string => (string) $key, $registry->unfiltered()->keys());
        sort($setupKeys);
        if ($declaration->root !== 'desk.automations' || $declaration->entryType !== 'string'
            || $declaration->onKeyDuplicate !== OnKeyDuplicate::Reject
            || $declaration->populationRequirement !== PopulationRequirement::Required || $setupKeys !== self::KEYS) {
            throw new InvalidArgumentException('Incomplete or unexpected sample population');
        }
        // No setup counts, payloads, registrants, abilities or miss reasons are returned.
        $view = $trustedTooling ? $registry->unfiltered() : $registry;
        $keys = $view->keys();
        $visible = array_map(fn (RegistryKey $key): string => (string) $key, $keys);
        sort($visible);
        $expectedTypes = [];
        $expectedEdges = [];
        foreach ($keys as $key) {
            $segments = $key->segments();
            foreach ($segments as $index => $_) {
                $id = implode('.', array_slice($segments, 0, $index + 1));
                $expectedTypes[$id] = $index === count($segments) - 1 ? 'Entry' : 'Branch';
                if ($index > 0) {
                    $parent = implode('.', array_slice($segments, 0, $index));
                    $expectedEdges[$parent.'>'.$id] = ['from' => $parent, 'type' => 'PARENT_OF', 'to' => $id];
                }
            }
        }
        if ($expectedTypes === []) {
            throw new InvalidArgumentException('Expected a populated visible sample');
        }
        ksort($expectedTypes);
        $source = new RegistryGraphSource($registry, unfiltered: $trustedTooling);
        $store = $factory === null ? RelationalDriverFactory::make($source, 'keyed-registry-example') : $factory($source);
        if (! $store instanceof EnumerableStore || ! $store->supports(Capability::Enumerate)) {
            throw new InvalidArgumentException('Whole-snapshot enumeration is required');
        }
        $nodes = [];
        $actualTypes = [];
        foreach ($store->nodes() as $node) {
            $nodes[] = ['id' => $node->id()->value(), 'type' => $node->type()];
            $actualTypes[$node->id()->value()] = $node->type();
        }
        ksort($actualTypes);
        if ($actualTypes !== $expectedTypes || count($nodes) !== count($expectedTypes)) {
            throw new InvalidArgumentException('Incomplete or mismatched native projection');
        }
        usort($nodes, fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $edges = [];
        foreach ($store->edges() as $edge) {
            if ($edge->type() !== 'PARENT_OF' || ! isset($actualTypes[$edge->from()->value()], $actualTypes[$edge->to()->value()])) {
                throw new InvalidArgumentException('Unexpected graph edge');
            }
            $edges[] = ['from' => $edge->from()->value(), 'type' => $edge->type(), 'to' => $edge->to()->value()];
        }
        usort($edges, fn (array $a, array $b): int => [$a['from'], $a['type'], $a['to']] <=> [$b['from'], $b['type'], $b['to']]);

        $expectedEdges = array_values($expectedEdges);
        usort($expectedEdges, fn (array $a, array $b): int => [$a['from'], $a['type'], $a['to']] <=> [$b['from'], $b['type'], $b['to']]);
        if ($edges !== $expectedEdges) {
            throw new InvalidArgumentException('Incomplete native edge projection');
        }

        return ['visibleKeys' => $visible, 'nodes' => $nodes, 'edges' => $edges];
    }
}
