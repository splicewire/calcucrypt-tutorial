<?php

use Examples\KeyedRegistry\Example;
use Examples\KeyedRegistry\LocalPolicy;
use PHPUnit\Framework\TestCase;
use Rushing\Graphine\Contracts\EdgeContract;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Contracts\NodeContract;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Laravel\Sources\RegistryGraphSource;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Exceptions\AmbiguousRegistryMatch;
use Rushing\Popcorn\Registries\Exceptions\DuplicateRegistryKey;
use Rushing\Popcorn\Registries\Exceptions\InvalidRegistryKey;
use Rushing\Popcorn\Registries\Exceptions\MissReason;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\PopulationRequirement;
use Rushing\Popcorn\Registries\RegistryKey;

require_once __DIR__.'/Example.php';

final class ExampleTest extends TestCase
{
    public function test_reader_operator_reader_native_keys_and_exact_graphs(): void
    {
        $reader = Example::view('reader');
        $operator = Example::view('operator');
        self::assertSame($reader, Example::view('reader'));
        self::assertSame(['desk.automations.requests.list'], $reader['visibleKeys']);
        self::assertSame(Example::KEYS, $operator['visibleKeys']);
        self::assertSame([
            ['id' => 'desk', 'type' => 'Branch'],
            ['id' => 'desk.automations', 'type' => 'Branch'],
            ['id' => 'desk.automations.requests', 'type' => 'Branch'],
            ['id' => 'desk.automations.requests.list', 'type' => 'Entry'],
        ], $reader['nodes']);
        self::assertSame([
            ['from' => 'desk', 'type' => 'PARENT_OF', 'to' => 'desk.automations'],
            ['from' => 'desk.automations', 'type' => 'PARENT_OF', 'to' => 'desk.automations.requests'],
            ['from' => 'desk.automations.requests', 'type' => 'PARENT_OF', 'to' => 'desk.automations.requests.list'],
        ], $reader['edges']);
        self::assertCount(6, $operator['nodes']);
        self::assertCount(5, $operator['edges']);
        self::assertContains(['id' => 'desk.automations.sla', 'type' => 'Branch'], $operator['nodes']);
        self::assertContains(['id' => 'desk.automations.sla.inspect', 'type' => 'Entry'], $operator['nodes']);
    }

    public function test_explicit_tooling_clone_preserves_original_reader(): void
    {
        $registry = Example::registry('reader');
        $declaration = clone $registry->declaration();
        $before = Example::project($registry);
        $trusted = Example::project($registry, trustedTooling: true);
        self::assertCount(6, $trusted['nodes']);
        self::assertSame(Example::KEYS, $trusted['visibleKeys']);
        self::assertSame($before, Example::project($registry));
        self::assertEquals($declaration, $registry->declaration());
        self::assertFalse($registry->has('sla.inspect'));
        self::assertNull($registry->tryResolve('sla.inspect'));
    }

    public function test_no_entry_payload_registrant_ability_or_reason_is_rendered(): void
    {
        foreach (['reader', 'operator', 'trusted-tooling-reader'] as $fixture) {
            $json = json_encode(Example::view($fixture), JSON_THROW_ON_ERROR);
            foreach (['Request inspection', 'SLA inspection', 'example/service-desk', 'view-sla', 'Filtered', 'Absent', 'candidates'] as $internal) {
                self::assertStringNotContainsString($internal, $json);
            }
            self::assertSame(['context', 'fixture', 'visibleKeys', 'nodes', 'edges'], array_keys(Example::view($fixture)));
        }
    }

    public function test_leaf_lookup_and_denied_miss_safe_semantics(): void
    {
        $reader = Example::registry('reader');
        self::assertSame('Request inspection', $reader->resolve('requests.list'));
        self::assertFalse($reader->has('sla.inspect'));
        self::assertNull($reader->tryResolve('sla.inspect'));
        $operator = Example::registry('operator');
        self::assertSame('SLA inspection', $operator->resolve('sla.inspect'));
        // Private test only: same KEY, not different-key message comparisons.
        $filtered = null;
        try {
            $reader->resolve('sla.inspect');
        } catch (RegistryMiss $error) {
            $filtered = $error;
        }
        self::assertNotNull($filtered);
        $absentRegistry = new BasicRegistry(clone $reader->declaration());
        $absentRegistry->register('requests.list', 'Request inspection');
        $absent = null;
        try {
            $absentRegistry->resolve('sla.inspect');
        } catch (RegistryMiss $error) {
            $absent = $error;
        }
        self::assertNotNull($absent);
        self::assertSame($filtered->key, $absent->key);
        self::assertSame(MissReason::Filtered, $filtered->reason);
        self::assertSame(MissReason::Absent, $absent->reason);
        self::assertSame($absent->getMessage(), $filtered->getMessage());
        self::assertSame([], $filtered->candidates);
        self::assertFalse($reader->has('unknown'));
        self::assertNull($reader->tryResolve('unknown'));
    }

    public function test_derived_branch_is_not_an_entry_even_one_descendant(): void
    {
        $registry = Example::registry('reader');
        self::assertFalse($registry->has('requests'));
        foreach (['resolve', 'tryResolve'] as $method) {
            try {
                $registry->$method('requests');
                self::fail('A derived branch must remain ambiguous');
            } catch (AmbiguousRegistryMatch $error) {
                self::assertCount(1, $error->candidates);
            }
        }
    }

    public function test_duplicate_reject_preserves_original_entry_and_projection(): void
    {
        $registry = Example::registry('reader');
        $before = Example::project($registry);
        try {
            $registry->register('requests.list', 'Replacement payload', by: 'other/registrar');
            self::fail('Duplicate key must be rejected');
        } catch (DuplicateRegistryKey) {
            self::assertSame('Request inspection', $registry->resolve('requests.list'));
            self::assertSame($before, Example::project($registry));
        }
    }

    public function test_required_empty_native_keys_and_graph_are_not_populated_success(): void
    {
        $empty = new BasicRegistry(new IsRegistry(root: 'desk.automations', entryType: 'string',
            onKeyDuplicate: OnKeyDuplicate::Reject, populationRequirement: PopulationRequirement::Required));
        self::assertSame([], $empty->keys());
        $store = RelationalDriverFactory::make(new RegistryGraphSource($empty));
        self::assertSame([], $store->nodes());
        self::assertSame([], $store->edges());
        try {
            $empty->resolve('requests.list');
            self::fail('Required empty must report unpopulated');
        } catch (RegistryMiss $error) {
            self::assertSame(MissReason::Unpopulated, $error->reason);
        }
        $this->expectException(InvalidArgumentException::class);
        Example::project($empty);
    }

    public function test_ungated_read_never_invokes_authorizer(): void
    {
        $registry = Example::registry('reader');
        $calls = 0;
        $spy = new class($calls) implements Authorizer
        {
            public function __construct(private int &$calls) {}

            public function allows(string $ability, RegistryKey $key): bool
            {
                $this->calls++;

                return false;
            }
        };
        $registry->authorizeWith($spy);
        self::assertTrue($registry->has('requests.list'));
        self::assertSame('Request inspection', $registry->resolve('requests.list'));
        self::assertSame(0, $calls);
        self::assertFalse($registry->has('sla.inspect'));
        self::assertGreaterThan(0, $calls);
    }

    public function test_missing_authorizer_really_exposes_gated_entry(): void
    {
        $registry = Example::registry('reader');
        self::assertFalse($registry->has('sla.inspect'));
        $registry->authorizeWith(null); // explicit in-memory counterfactual, never CLI/host opt-in
        self::assertTrue($registry->has('sla.inspect'));
        self::assertContains('desk.automations.sla.inspect', Example::project($registry)['visibleKeys']);
    }

    public function test_invalid_canonical_key_is_not_empty_result(): void
    {
        $this->expectException(InvalidRegistryKey::class);
        Example::registry('reader')->has('Not.Canonical');
    }

    public function test_unknown_policy_fixture_refuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocalPolicy::fixture('authenticated-owner');
    }

    public function test_non_enumerable_store_refuses(): void
    {
        $store = new class implements GraphStore
        {
            public function name(): string
            {
                return 'not-enumerable';
            }

            public function supports(Capability $capability): bool
            {
                return false;
            }

            public function speaks(): array
            {
                return [];
            }
        };
        $this->expectException(InvalidArgumentException::class);
        Example::project(Example::registry('reader'), factory: fn (): GraphStore => $store);
    }

    public function test_enumerable_but_incomplete_graph_refuses_false_success(): void
    {
        $registry = Example::registry('reader');
        $native = RelationalDriverFactory::make(new RegistryGraphSource($registry));
        $incomplete = new class($native->nodes()) implements EnumerableStore, GraphStore
        {
            /** @param list<NodeContract> $nodes */
            public function __construct(private array $nodes) {}

            public function name(): string
            {
                return 'incomplete';
            }

            public function supports(Capability $capability): bool
            {
                return $capability === Capability::Enumerate;
            }

            public function speaks(): array
            {
                return [];
            }

            /** @return list<NodeContract> */
            public function nodes(): array
            {
                return $this->nodes;
            }

            /** @return list<EdgeContract> */
            public function edges(): array
            {
                return [];
            }
        };
        $this->expectException(InvalidArgumentException::class);
        Example::project($registry, factory: fn (): GraphStore => $incomplete);
    }
}
