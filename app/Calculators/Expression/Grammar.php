<?php

declare(strict_types=1);

namespace App\Calculators\Expression;

use Closure;

/**
 * The grammar an evaluator runs under — the seam the expression language grows through (ADR 0006). The
 * mirror of the TypeScript twin (resources/js/calculators/expression/grammar.ts). An evaluator consults
 * it in three places: a call looks in `functions` before the built-ins, an identifier looks in
 * `constants` before the environment, and the tokenizer reads `1.5e-3` as one number only when
 * `exponentLiterals` is on.
 *
 * The default (`new Grammar`) is exactly the original grammar: no table entries, no constants, no
 * exponent literals, so the six built-ins (min, max, abs, round, pow, sqrt) are all there is.
 */
class Grammar
{
    /**
     * @param  array<string, Closure(list<float>): float>  $functions  name → evaluated arguments → number, or throws
     * @param  array<string, float>  $constants  reserved identifier → value
     */
    public function __construct(
        public readonly array $functions = [],
        public readonly array $constants = [],
        public readonly bool $exponentLiterals = false,
    ) {}
}
