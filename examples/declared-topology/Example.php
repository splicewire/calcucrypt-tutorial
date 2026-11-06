<?php

namespace Examples\DeclaredTopology;

use InvalidArgumentException;
use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Enums\Capability;
use Rushing\PackageTopology\Contract\PackageScope;
use Rushing\PackageTopology\Contract\TopologyContract;
use Rushing\PackageTopology\Evaluator\TopologyEvaluator;
use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;
use Rushing\PackageTopology\Sources\DeclaredContractSource;

final class IncompleteMeasurement extends \RuntimeException
{
    /** @param array<string, mixed> $measurement */
    public function __construct(public array $measurement)
    {
        parent::__construct('Incomplete graph measurement');
    }
}

/** A bounded teaching example, not a filesystem authorizer or a global graph binding. */
final class Example
{
    public const NAMES = ['fixture/contracts', 'fixture/orders'];

    public static function directory(string $path): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $real = realpath($path);
        if ($real === false || $real !== $path || ! is_dir($real) || is_link($path)) {
            throw new InvalidArgumentException('Expected a real directory without symlink components');
        }

        return $real;
    }

    /** @return array<string, mixed> */
    public static function json(string $file): array
    {
        if (! is_file($file) || is_link($file)) {
            throw new InvalidArgumentException('Expected a regular manifest');
        }
        $value = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('Expected a JSON object');
        }

        return $value;
    }

    public static function ownership(string $path): void
    {
        $data = self::json($path.'/.openrig-scratch-owner.json');
        $stat = stat($path);
        if ($stat === false) {
            throw new InvalidArgumentException('Fixture identity unavailable');
        }
        if (($data['schema'] ?? null) !== 'openrig.scratch-owner/v2' || ($data['path'] ?? null) !== $path
            || ($data['owner'] ?? null) !== getenv('OPENRIG_SESSION_NAME') || empty($data['owner'])
            || ($data['dev'] ?? null) !== $stat['dev'] || ($data['ino'] ?? null) !== $stat['ino'] || empty($data['id'])) {
            throw new InvalidArgumentException('An exclusive owned scratch fixture is required');
        }
    }

    public static function location(string $root, string $fixture): string
    {
        $root = self::directory($root);
        $fixture = self::directory($fixture);
        if (dirname($fixture) !== $root) {
            throw new InvalidArgumentException('Fixture must be a direct child of the run root');
        }
        self::ownership($root);
        self::ownership($fixture);

        return $fixture;
    }

    /** @return array<string, string> regular file => SHA256 */
    public static function snapshot(string $fixture): array
    {
        $files = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($walk as $file) {
            if ($file->isLink() || (! $file->isDir() && ! $file->isFile())) {
                throw new InvalidArgumentException('Symlink or nonregular fixture payload refused');
            }
            if ($file->isFile()) {
                $hash = hash_file('sha256', $file->getPathname());
                if ($hash === false) {
                    throw new InvalidArgumentException('Fixture bytes unavailable');
                }
                $files[substr($file->getPathname(), strlen($fixture) + 1)] = $hash;
            }
        }
        ksort($files);

        return $files;
    }

    public static function validate(string $root, string $fixture): string
    {
        $fixture = self::location($root, $fixture);
        // Walk before any manifest read: a symlinked package directory must not be followed.
        $files = array_keys(self::snapshot($fixture));
        $expected = ['.openrig-scratch-owner.json', 'canary.txt', 'fixture/contracts/composer.json', 'fixture/orders/composer.json'];
        if ($files !== $expected) {
            throw new InvalidArgumentException('Incomplete or unexpected fixture file scope');
        }
        foreach (self::NAMES as $name) {
            $manifest = self::json($fixture.'/'.$name.'/composer.json');
            if (($manifest['name'] ?? null) !== $name || array_diff(array_keys($manifest), ['name', 'require', 'extra']) !== []) {
                throw new InvalidArgumentException('Unexpected package identity or manifest fields');
            }
            $require = $manifest['require'] ?? null;
            if (! is_array($require)) {
                throw new InvalidArgumentException('Missing dependency map');
            }
            foreach ($require as $target => $constraint) {
                if (! in_array($target, self::NAMES, true) || ! is_string($constraint) || $constraint === '') {
                    throw new InvalidArgumentException('Missing endpoint or unsupported dependency');
                }
            }
            if ($name === 'fixture/orders') {
                if (array_keys($require) !== ['fixture/contracts'] || ($manifest['extra']['package-topology'] ?? null) !== [
                    'mustRequire' => ['fixture/contracts'],
                    'mustBeInstalled' => ['fixture/orders', 'fixture/contracts'],
                ]) {
                    throw new InvalidArgumentException('Expected nonempty orders declarations');
                }
            } elseif (isset($manifest['extra']) || ! in_array(array_keys($require), [[], ['fixture/orders']], true)) {
                throw new InvalidArgumentException('Unexpected contracts declaration');
            }
        }

        return $fixture;
    }

    /** @param callable(ComposerManifestGraphSource): GraphStore|null $factory Test seam only; CLI always uses the native factory.
     * @return array<string, mixed>
     */
    public static function inspect(string $root, string $fixture, ?callable $factory = null): array
    {
        $fixture = self::validate($root, $fixture);
        $before = self::snapshot($fixture);
        $contract = (new DeclaredContractSource($fixture, ['fixture/*']))->contract('orders-example');
        $source = new ComposerManifestGraphSource($fixture, ['fixture/*'], named: $contract->packagesNamed());
        $store = $factory === null ? RelationalDriverFactory::make($source, 'orders-example') : $factory($source);
        $result = self::measure($contract, $store, $fixture, $source);
        if (self::snapshot($fixture) !== $before) {
            throw new InvalidArgumentException('Fixture changed during inspection');
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public static function measure(TopologyContract $contract, GraphStore $store, string $fixture, PackageScope $scope): array
    {
        if (! $store instanceof ComputeStore || ! $store instanceof EnumerableStore
            || ! $store->supports(Capability::Compute) || ! $store->supports(Capability::Enumerate)) {
            throw new InvalidArgumentException('Compute and enumeration capabilities are required');
        }
        $nodes = [];
        foreach ($store->nodes() as $node) {
            $nodes[] = ['id' => $node->id()->value(), 'type' => $node->type(), 'installed' => $node->properties()['installed'] ?? false];
        }
        usort($nodes, fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        if (array_column($nodes, 'id') !== self::NAMES || in_array(false, array_column($nodes, 'installed'), true) || $contract->rules === []) {
            throw new InvalidArgumentException('Incomplete node or declaration population');
        }
        $edges = [];
        foreach ($store->edges() as $edge) {
            $edges[] = ['from' => $edge->from()->value(), 'type' => $edge->type(), 'to' => $edge->to()->value()];
        }
        usort($edges, fn (array $a, array $b): int => [$a['from'], $a['type'], $a['to']] <=> [$b['from'], $b['type'], $b['to']]);
        $evaluator = new TopologyEvaluator;
        $violations = $evaluator->evaluate($contract, $store, $fixture, $scope);
        $result = [
            'scope' => ['fixture/*'], 'nodes' => $nodes, 'edges' => $edges,
            'rules' => count($contract->rules), 'ruleKinds' => array_map(fn ($rule): string => $rule->kind->value, $contract->rules),
            'didNotLook' => $evaluator->didNotLook(),
            'unresolved' => array_map(fn ($rule): string => $rule->message(), $evaluator->unresolved()),
            'violations' => array_map(fn ($violation): array => ['kind' => $violation->kind->value, 'message' => $violation->message()], $violations),
        ];
        if ($result['didNotLook'] !== 0 || $result['unresolved'] !== []) {
            throw new IncompleteMeasurement($result);
        }

        return $result;
    }

    public static function prepare(string $root, string $fixture, bool $cyclic): void
    {
        $fixture = self::location($root, $fixture);
        if (array_keys(self::snapshot($fixture)) !== ['.openrig-scratch-owner.json']
            || count(scandir($fixture)) !== 3) {
            throw new InvalidArgumentException('Prepare requires an empty registered fixture');
        }
        mkdir($fixture.'/fixture/orders', 0700, true);
        mkdir($fixture.'/fixture/contracts', 0700, true);
        $orders = ['name' => 'fixture/orders', 'require' => ['fixture/contracts' => '*'], 'extra' => ['package-topology' => [
            'mustRequire' => ['fixture/contracts'], 'mustBeInstalled' => ['fixture/orders', 'fixture/contracts'],
        ]]];
        $contracts = ['name' => 'fixture/contracts', 'require' => $cyclic ? ['fixture/orders' => '*'] : new \stdClass];
        file_put_contents($fixture.'/fixture/orders/composer.json', json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        file_put_contents($fixture.'/fixture/contracts/composer.json', json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        file_put_contents($fixture.'/canary.txt', "Unrelated fixture bytes stay unchanged.\n");
        self::validate($root, $fixture);
    }
}
