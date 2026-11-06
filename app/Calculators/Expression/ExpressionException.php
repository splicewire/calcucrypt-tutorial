<?php

declare(strict_types=1);

namespace App\Calculators\Expression;

use RuntimeException;

/**
 * Thrown when an expression cannot be parsed or evaluated. Mirrors the TS
 * `ExpressionError` (resources/js/calculators/expression/evaluate.ts) so a bad
 * expression fails the same way on both sides of the client/server seam.
 */
final class ExpressionException extends RuntimeException {}
