<?php

declare(strict_types=1);

namespace App\Content\Calculators;

use ReflectionClass;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\PopulationRequirement;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Rushing\Popcorn\Registries\Rootable;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Every authored {@see CalculatorDefinition}, keyed by its Calculator Key on the popcorn kernel
 * (ADR 0005). `content:sync` reads this to know the whole authored catalog — adding a calculator is
 * adding a class under `Definitions/`.
 *
 * The root is brand-free and domain-first, `calculators`, so `finance.dca` is addressable through the
 * shared index as `calculators.finance.dca`. The entitlement keys that also spell `calculators.{slug}`
 * live in Beam's entitlement namespace, not in this keyspace.
 *
 * - **Duplicates are rejected.** Two definitions on one key are one computation authored twice, so the
 *   registry is the factory's first dedup signal and a colliding mint fails loudly.
 * - **Empty is a bug.** The authored catalog always has entries; `get()` on an empty registry reports it
 *   unpopulated rather than a bare miss.
 * - **The miss pair.** `get()` is for a key the code chose and throws on a miss; `find()` is for a key
 *   from outside (a URL, a spreadsheet cell, an agent's argument) and answers null.
 * - **Every key obeys the grammar** ({@see CalculatorKey}) at registration, so a definition with a
 *   malformed key or an unlisted domain stops the boot instead of publishing.
 *
 * @implements Registry<CalculatorDefinition>
 */
#[IsRegistry(
    root: 'calculators',
    entryType: CalculatorDefinition::class,
    onKeyDuplicate: OnKeyDuplicate::Reject,
    populationRequirement: PopulationRequirement::Required,
    description: 'the authored calculator catalog, one entry per Calculator Key `<domain>.<computation>` (two kebab segments, domain on the closed host list). Discovered from app/Content/Calculators/Definitions; a duplicate key is refused. The `u` first segment is reserved for user compositions, which resolve through their own store and never here.',
)]
class CalculatorRegistry implements Registry
{
    /** @var BasicRegistry<CalculatorDefinition> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);

        foreach ($this->discover() as $definition) {
            $this->register($definition->key(), $definition, 'class:'.$definition::class);
        }
    }

    /**
     * Every definition, ordered by slug.
     *
     * @return list<CalculatorDefinition>
     */
    public function all(): array
    {
        $definitions = $this->entries->matches($this->entries->root());

        usort($definitions, fn (CalculatorDefinition $a, CalculatorDefinition $b): int => $a->slug() <=> $b->slug());

        return $definitions;
    }

    /** The definition at a key the code chose; throws {@see \Rushing\Popcorn\Registries\Exceptions\RegistryMiss}. */
    public function get(RegistryKey|string $key): CalculatorDefinition
    {
        return $this->entries->resolve($key);
    }

    /** The definition at a key from outside, or null. */
    public function find(RegistryKey|string $key): ?CalculatorDefinition
    {
        return $this->entries->tryResolve($key);
    }

    public function register(RegistryKey|string $key, mixed $entry, ?string $by = null, ?string $ability = null): static
    {
        CalculatorKey::assertValid($this->relativeOf($key), $by ?? get_debug_type($entry));

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * The key as it reads under this registry's root, so the grammar is checked on the same address
     * the kernel stores: `finance.dca` and `calculators.finance.dca` (string or {@see RegistryKey}) both
     * read `finance.dca` — popcorn's "relative in, absolute out" rule. A string the kernel cannot
     * parse is returned as given, and the grammar check says why it is not a Calculator Key.
     */
    private function relativeOf(RegistryKey|string $key): string
    {
        $parsed = $key instanceof RegistryKey ? $key : Key::tryParse($key);

        if ($parsed === null) {
            return (string) $key;
        }

        $root = $this->entries->declaration()->rootKey();

        if ($parsed instanceof Rootable) {
            $parsed = $parsed->underRoot($root);
        }

        $segments = $parsed->segments();
        $depth = count($root->segments());

        return array_slice($segments, 0, $depth) === $root->segments()
            ? implode('.', array_slice($segments, $depth))
            : (string) $parsed;
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    /**
     * The keys as the definitions spell them, relative to the root (`finance.dca`).
     *
     * @return list<string>
     */
    public function relativeKeys(): array
    {
        return $this->entries->relativeKeys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    /**
     * @return list<CalculatorDefinition>
     */
    private function discover(): array
    {
        $definitions = [];
        foreach (Finder::create()->files()->in(__DIR__.'/Definitions')->name('*.php')->sortByName() as $file) {
            $class = $this->classFor($file);
            if ($class === null) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(CalculatorDefinition::class)) {
                continue;
            }

            $definitions[] = $reflection->newInstance();
        }

        return $definitions;
    }

    /**
     * @return class-string<CalculatorDefinition>|null
     */
    private function classFor(SplFileInfo $file): ?string
    {
        /** @var class-string<CalculatorDefinition> $class */
        $class = __NAMESPACE__.'\\Definitions\\'.$file->getBasename('.php');

        return class_exists($class) ? $class : null;
    }
}
