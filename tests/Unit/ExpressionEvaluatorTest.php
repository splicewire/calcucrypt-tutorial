<?php

declare(strict_types=1);

use App\Calculators\Expression\ExpressionEvaluator;
use App\Calculators\Expression\ExpressionException;

/**
 * The PHP half of the evaluator-parity contract. It runs the SAME golden vectors the
 * Vitest suite runs (tests/fixtures/expression-golden.json). If a change makes PHP and TS
 * disagree, one of the two suites goes red — that is the whole point.
 */

/** @return array<int, array{expr: string, env: array<string, float|int|bool>, expect: float|int|bool}> */
function goldenVectors(): array
{
    $path = __DIR__.'/../fixtures/expression-golden.json';
    $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return $decoded['cases'];
}

it('matches every golden vector', function (): void {
    $evaluator = new ExpressionEvaluator;

    foreach (goldenVectors() as $case) {
        $actual = $evaluator->evaluate($case['expr'], $case['env']);

        if (is_bool($case['expect'])) {
            expect($actual)->toBe($case['expect'], "expr: {$case['expr']}");
        } else {
            expect($actual)->toBeFloat();
            expect(abs($actual - $case['expect']))->toBeLessThan(1e-9, "expr: {$case['expr']}");
        }
    }
});

it('throws on division by zero', function (): void {
    expect(fn () => (new ExpressionEvaluator)->evaluate('1 / 0'))
        ->toThrow(ExpressionException::class);
});

it('does not evaluate the untaken ternary branch (short-circuit)', function (): void {
    // cost == 0 → the divide must never run, so this must NOT throw.
    $result = (new ExpressionEvaluator)->evaluate('cost == 0 ? 0 : profit / cost', ['cost' => 0, 'profit' => 5]);

    expect($result)->toBe(0.0);
});

it('throws on an unknown identifier', function (): void {
    expect(fn () => (new ExpressionEvaluator)->evaluate('mystery + 1'))
        ->toThrow(ExpressionException::class);
});

it('throws on a malformed expression', function (): void {
    expect(fn () => (new ExpressionEvaluator)->evaluate('1 + '))
        ->toThrow(ExpressionException::class);
});
