<?php

use Examples\DeclaredTopology\Example;
use Examples\DeclaredTopology\IncompleteMeasurement;
use PHPUnit\Framework\TestCase;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Enums\Capability;
use Rushing\PackageTopology\Contract\PackageScope;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;
use Rushing\PackageTopology\Sources\DeclaredContractSource;

require_once __DIR__.'/Example.php';

/** Standalone PHPUnit: no Testbench/Laravel bootstrap or RefreshDatabase. */
final class ExampleTest extends TestCase
{
    private static int $next = 0;

    private string $root;

    private string $fixture;

    protected function setUp(): void
    {
        $this->root = Example::directory((string) getenv('TOPOLOGY_RUN_ROOT'));
        $fixtures = json_decode((string) getenv('TOPOLOGY_TEST_FIXTURES'), true, 512, JSON_THROW_ON_ERROR);
        $this->fixture = $fixtures[self::$next++];
        // These are newly allocated registered rooms, never a served host/vendor/owner target.
        Example::prepare($this->root, $this->fixture, false);
    }

    public function test_clean_cycle_clean_uses_real_fresh_native_snapshots(): void
    {
        $before = Example::snapshot($this->fixture);
        $clean = Example::inspect($this->root, $this->fixture);
        self::assertSame(4, $clean['rules']);
        self::assertSame(0, $clean['didNotLook']);
        self::assertSame([], $clean['unresolved']);
        self::assertSame([], $clean['violations']);
        self::assertSame(Example::NAMES, array_column($clean['nodes'], 'id'));
        self::assertSame([true, true], array_column($clean['nodes'], 'installed'));
        self::assertSame([['from' => 'fixture/orders', 'type' => 'REQUIRES', 'to' => 'fixture/contracts']], $clean['edges']);
        self::assertSame($before, Example::snapshot($this->fixture));
        $file = $this->fixture.'/fixture/contracts/composer.json';
        $bytes = file_get_contents($file);
        file_put_contents($file, json_encode(['name' => 'fixture/contracts', 'require' => ['fixture/orders' => '*']], JSON_THROW_ON_ERROR));
        $cyclic = Example::inspect($this->root, $this->fixture);
        self::assertCount(2, $cyclic['edges']);
        self::assertNotEmpty($cyclic['violations']);
        self::assertSame('acyclic', $cyclic['violations'][0]['kind']);
        self::assertStringContainsString('fixture/contracts', $cyclic['violations'][0]['message']);
        self::assertStringContainsString('fixture/orders', $cyclic['violations'][0]['message']);
        file_put_contents($file, $bytes);
        self::assertSame($clean, Example::inspect($this->root, $this->fixture));
        self::assertSame($before, Example::snapshot($this->fixture));
    }

    public function test_malformed_json_is_not_silently_skipped_as_clean(): void
    {
        file_put_contents($this->fixture.'/fixture/contracts/composer.json', '{');
        $this->expectException(JsonException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_empty_scope_is_not_clean(): void
    {
        unlink($this->fixture.'/fixture/contracts/composer.json');
        unlink($this->fixture.'/fixture/orders/composer.json');
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_missing_endpoint_refuses_incomplete_input(): void
    {
        $file = $this->fixture.'/fixture/orders/composer.json';
        $data = Example::json($file);
        $data['require'] = ['fixture/missing' => '*'];
        file_put_contents($file, json_encode($data));
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_symlink_payload_refuses_before_external_manifest_read(): void
    {
        $file = $this->fixture.'/fixture/contracts/composer.json';
        unlink($file);
        symlink($this->fixture.'/canary.txt', $file);
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_fixture_outside_selected_root_refuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->fixture, $this->root);
    }

    public function test_driver_without_compute_and_enumeration_cannot_silently_skip_cycle(): void
    {
        $driver = new class implements GraphStore
        {
            public function name(): string
            {
                return 'unsupported';
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
        Example::inspect($this->root, $this->fixture, fn (): GraphStore => $driver);
    }

    public function test_native_unexamined_scope_is_visible_and_refused(): void
    {
        $contract = (new DeclaredContractSource($this->fixture, ['fixture/*']))->contract('scope-control');
        $source = new ComposerManifestGraphSource($this->fixture, ['fixture/*'], named: $contract->packagesNamed());
        $store = RelationalDriverFactory::make($source);
        $scope = new class implements PackageScope
        {
            public function sees(string $name): bool
            {
                return false;
            }
        };
        try {
            Example::measure($contract, $store, $this->fixture, $scope);
            self::fail('Unexamined rules must not be credited as clean');
        } catch (IncompleteMeasurement $error) {
            self::assertSame(3, $error->measurement['didNotLook']);
            self::assertCount(3, $error->measurement['unresolved']);
            self::assertStringContainsString('did not look', $error->measurement['unresolved'][0]);
        }
    }

    public function test_duplicate_identity_is_invalid_instead_of_overwritten(): void
    {
        $file = $this->fixture.'/fixture/contracts/composer.json';
        file_put_contents($file, '{"name":"fixture/orders","require":{}}');
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_missing_declarations_cannot_pass_on_acyclic_append_alone(): void
    {
        $file = $this->fixture.'/fixture/orders/composer.json';
        $data = Example::json($file);
        unset($data['extra']);
        file_put_contents($file, json_encode($data));
        $this->expectException(InvalidArgumentException::class);
        Example::inspect($this->root, $this->fixture);
    }

    public function test_prepare_refuses_existing_nonempty_fixture(): void
    {
        $before = Example::snapshot($this->fixture);
        try {
            Example::prepare($this->root, $this->fixture, true);
            self::fail('Existing fixture must not be overwritten');
        } catch (InvalidArgumentException) {
            self::assertSame($before, Example::snapshot($this->fixture));
        }
    }
}
