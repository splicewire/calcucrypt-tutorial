<?php

declare(strict_types=1);

namespace App\Content\Calculators;

use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Discovers every {@see CalculatorDefinition} under the Definitions directory. `content:sync`
 * reads this to know the whole authored catalog — adding a calculator is adding a class.
 */
class CalculatorRegistry
{
    /**
     * @return list<CalculatorDefinition>
     */
    public function all(): array
    {
        $directory = __DIR__.'/Definitions';

        $definitions = [];
        foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
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

        usort($definitions, fn (CalculatorDefinition $a, CalculatorDefinition $b): int => $a->slug() <=> $b->slug());

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
