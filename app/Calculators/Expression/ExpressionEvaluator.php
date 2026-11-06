<?php

declare(strict_types=1);

namespace App\Calculators\Expression;

/**
 * A tiny, self-contained evaluator for the restricted calculator grammar. It is
 * DELIBERATELY hand-written and kept in lock-step with its TypeScript twin
 * (resources/js/calculators/expression/evaluate.ts) — "same formula → same number on
 * client and server" is the whole promise, and a shared golden-vector fixture
 * (tests/fixtures/expression-golden.json) fails CI the moment the two drift.
 *
 * It parses to an AST first, THEN evaluates, so ternary and &&/|| short-circuit
 * correctly — the profit ROI node `cost == 0 ? 0 : profit / cost * 100` must never
 * evaluate the divide when cost is zero.
 *
 * Grammar (lowest → highest precedence):
 *   ternary ?: (right-assoc) | || | && | == != | < <= > >= | + - | * / % | unary - ! | primary
 * `%` is FLOAT remainder (fmod, not PHP's int %). round() is half-away-from-zero via an
 * explicit formula so it matches JS. Division/modulo by zero throws.
 * Whitelisted functions: min, max, abs, round, pow, sqrt.
 */
final class ExpressionEvaluator
{
    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @param  array<string, float|int|bool>  $env  node-id → resolved value
     */
    public function evaluate(string $expression, array $env = []): float|bool
    {
        return $this->evaluateNode($this->parse($expression), $env);
    }

    /**
     * @return array<string, mixed> the AST root
     */
    public function parse(string $expression): array
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;

        $ast = $this->parseTernary();

        if ($this->pos < count($this->tokens)) {
            throw new ExpressionException("Unexpected token '{$this->tokens[$this->pos]['value']}'");
        }

        return $ast;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $length = strlen($input);
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (ctype_digit($char) || ($char === '.' && $i + 1 < $length && ctype_digit($input[$i + 1]))) {
                $start = $i;
                while ($i < $length && (ctype_digit($input[$i]) || $input[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['type' => 'number', 'value' => substr($input, $start, $i - $start)];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $start = $i;
                while ($i < $length && (ctype_alnum($input[$i]) || $input[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['type' => 'ident', 'value' => substr($input, $start, $i - $start)];

                continue;
            }

            $two = substr($input, $i, 2);
            if (in_array($two, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $two];
                $i += 2;

                continue;
            }

            if (in_array($char, ['+', '-', '*', '/', '%', '<', '>', '!', '(', ')', ',', '?', ':'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $char];
                $i++;

                continue;
            }

            throw new ExpressionException("Unexpected character '{$char}'");
        }

        return $tokens;
    }

    /**
     * @return array{type: string, value: string}|null
     */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    /**
     * Consumes the token when it matches. Impure: advances the parser position, so its
     * result must not be memoised by static analysis.
     *
     * @phpstan-impure
     */
    private function matchOp(string $value): bool
    {
        $token = $this->peek();
        if ($token !== null && $token['type'] === 'op' && $token['value'] === $value) {
            $this->pos++;

            return true;
        }

        return false;
    }

    // --- parser: builds an AST, never evaluates -------------------------------------

    /** @return array<string, mixed> */
    private function parseTernary(): array
    {
        $condition = $this->parseOr();

        if ($this->matchOp('?')) {
            $whenTrue = $this->parseTernary();
            if (! $this->matchOp(':')) {
                throw new ExpressionException("Expected ':' in ternary");
            }
            $whenFalse = $this->parseTernary();

            return ['t' => 'tern', 'c' => $condition, 'a' => $whenTrue, 'b' => $whenFalse];
        }

        return $condition;
    }

    /** @return array<string, mixed> */
    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->matchOp('||')) {
            $left = ['t' => 'log', 'op' => '||', 'l' => $left, 'r' => $this->parseAnd()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseAnd(): array
    {
        $left = $this->parseEquality();
        while ($this->matchOp('&&')) {
            $left = ['t' => 'log', 'op' => '&&', 'l' => $left, 'r' => $this->parseEquality()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseEquality(): array
    {
        $left = $this->parseRelational();
        while (true) {
            if ($this->matchOp('==')) {
                $left = ['t' => 'bin', 'op' => '==', 'l' => $left, 'r' => $this->parseRelational()];
            } elseif ($this->matchOp('!=')) {
                $left = ['t' => 'bin', 'op' => '!=', 'l' => $left, 'r' => $this->parseRelational()];
            } else {
                break;
            }
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseRelational(): array
    {
        $left = $this->parseAdditive();
        while (true) {
            if ($this->matchOp('<=')) {
                $left = ['t' => 'bin', 'op' => '<=', 'l' => $left, 'r' => $this->parseAdditive()];
            } elseif ($this->matchOp('>=')) {
                $left = ['t' => 'bin', 'op' => '>=', 'l' => $left, 'r' => $this->parseAdditive()];
            } elseif ($this->matchOp('<')) {
                $left = ['t' => 'bin', 'op' => '<', 'l' => $left, 'r' => $this->parseAdditive()];
            } elseif ($this->matchOp('>')) {
                $left = ['t' => 'bin', 'op' => '>', 'l' => $left, 'r' => $this->parseAdditive()];
            } else {
                break;
            }
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseAdditive(): array
    {
        $left = $this->parseMultiplicative();
        while (true) {
            if ($this->matchOp('+')) {
                $left = ['t' => 'bin', 'op' => '+', 'l' => $left, 'r' => $this->parseMultiplicative()];
            } elseif ($this->matchOp('-')) {
                $left = ['t' => 'bin', 'op' => '-', 'l' => $left, 'r' => $this->parseMultiplicative()];
            } else {
                break;
            }
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseMultiplicative(): array
    {
        $left = $this->parseUnary();
        while (true) {
            if ($this->matchOp('*')) {
                $left = ['t' => 'bin', 'op' => '*', 'l' => $left, 'r' => $this->parseUnary()];
            } elseif ($this->matchOp('/')) {
                $left = ['t' => 'bin', 'op' => '/', 'l' => $left, 'r' => $this->parseUnary()];
            } elseif ($this->matchOp('%')) {
                $left = ['t' => 'bin', 'op' => '%', 'l' => $left, 'r' => $this->parseUnary()];
            } else {
                break;
            }
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseUnary(): array
    {
        if ($this->matchOp('-')) {
            return ['t' => 'un', 'op' => '-', 'e' => $this->parseUnary()];
        }
        if ($this->matchOp('!')) {
            return ['t' => 'un', 'op' => '!', 'e' => $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    /** @return array<string, mixed> */
    private function parsePrimary(): array
    {
        if ($this->matchOp('(')) {
            $node = $this->parseTernary();
            if (! $this->matchOp(')')) {
                throw new ExpressionException("Expected ')'");
            }

            return $node;
        }

        $token = $this->peek();
        if ($token === null) {
            throw new ExpressionException('Unexpected end of expression');
        }

        if ($token['type'] === 'number') {
            $this->pos++;

            return ['t' => 'num', 'v' => (float) $token['value']];
        }

        if ($token['type'] === 'ident') {
            $this->pos++;
            $name = $token['value'];

            if ($this->matchOp('(')) {
                $args = [];
                if (! $this->matchOp(')')) {
                    do {
                        $args[] = $this->parseTernary();
                    } while ($this->matchOp(','));
                    if (! $this->matchOp(')')) {
                        throw new ExpressionException("Expected ')' after arguments");
                    }
                }

                return ['t' => 'call', 'name' => $name, 'args' => $args];
            }

            return ['t' => 'var', 'name' => $name];
        }

        throw new ExpressionException("Unexpected token '{$token['value']}'");
    }

    // --- evaluator: walks the AST, short-circuits -----------------------------------

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int|bool>  $env
     */
    private function evaluateNode(array $node, array $env): float|bool
    {
        return match ($node['t']) {
            'num' => $node['v'],
            'var' => $this->resolveVar($node['name'], $env),
            'un' => $this->evaluateUnary($node, $env),
            'bin' => $this->evaluateBinary($node, $env),
            'log' => $this->evaluateLogical($node, $env),
            'tern' => $this->toBool($this->evaluateNode($node['c'], $env))
                ? $this->evaluateNode($node['a'], $env)
                : $this->evaluateNode($node['b'], $env),
            'call' => $this->evaluateCall($node, $env),
            default => throw new ExpressionException("Unknown node '{$node['t']}'"),
        };
    }

    /**
     * @param  array<string, float|int|bool>  $env
     */
    private function resolveVar(string $name, array $env): float|bool
    {
        if (! array_key_exists($name, $env)) {
            throw new ExpressionException("Unknown identifier '{$name}'");
        }
        $value = $env[$name];

        return is_bool($value) ? $value : (float) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int|bool>  $env
     */
    private function evaluateUnary(array $node, array $env): float|bool
    {
        $value = $this->evaluateNode($node['e'], $env);

        return $node['op'] === '-'
            ? -$this->toNum($value)
            : ! $this->toBool($value);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int|bool>  $env
     */
    private function evaluateLogical(array $node, array $env): bool
    {
        $left = $this->toBool($this->evaluateNode($node['l'], $env));

        if ($node['op'] === '&&') {
            return $left ? $this->toBool($this->evaluateNode($node['r'], $env)) : false;
        }

        return $left ? true : $this->toBool($this->evaluateNode($node['r'], $env));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int|bool>  $env
     */
    private function evaluateBinary(array $node, array $env): float|bool
    {
        $left = $this->evaluateNode($node['l'], $env);
        $right = $this->evaluateNode($node['r'], $env);

        return match ($node['op']) {
            '+' => $this->toNum($left) + $this->toNum($right),
            '-' => $this->toNum($left) - $this->toNum($right),
            '*' => $this->toNum($left) * $this->toNum($right),
            '/' => $this->divide($this->toNum($left), $this->toNum($right)),
            '%' => $this->modulo($this->toNum($left), $this->toNum($right)),
            '<' => $this->toNum($left) < $this->toNum($right),
            '<=' => $this->toNum($left) <= $this->toNum($right),
            '>' => $this->toNum($left) > $this->toNum($right),
            '>=' => $this->toNum($left) >= $this->toNum($right),
            '==' => $this->looseEquals($left, $right),
            '!=' => ! $this->looseEquals($left, $right),
            default => throw new ExpressionException("Unknown operator '{$node['op']}'"),
        };
    }

    private function divide(float $left, float $right): float
    {
        if ($right === 0.0) {
            throw new ExpressionException('Division by zero');
        }

        return $left / $right;
    }

    private function modulo(float $left, float $right): float
    {
        if ($right === 0.0) {
            throw new ExpressionException('Modulo by zero');
        }

        return fmod($left, $right);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, float|int|bool>  $env
     */
    private function evaluateCall(array $node, array $env): float
    {
        $args = array_map(fn (array $arg): float => $this->toNum($this->evaluateNode($arg, $env)), $node['args']);
        $name = $node['name'];
        $count = count($args);

        return match ($name) {
            'min' => $count >= 1 ? min($args) : throw $this->arity($name),
            'max' => $count >= 1 ? max($args) : throw $this->arity($name),
            'abs' => $count === 1 ? abs($args[0]) : throw $this->arity($name),
            'sqrt' => $count === 1 ? sqrt($args[0]) : throw $this->arity($name),
            'pow' => $count === 2 ? $args[0] ** $args[1] : throw $this->arity($name),
            'round' => ($count >= 1 && $count <= 2)
                ? $this->roundHalfAway($args[0], $count === 2 ? (int) $args[1] : 0)
                : throw $this->arity($name),
            default => throw new ExpressionException("Unknown function '{$name}'"),
        };
    }

    private function arity(string $name): ExpressionException
    {
        return new ExpressionException("Function '{$name}' arity mismatch");
    }

    /**
     * Half-away-from-zero rounding by formula so both evaluators agree regardless of the
     * host language's native round().
     */
    private function roundHalfAway(float $value, int $precision): float
    {
        $factor = 10 ** $precision;
        $sign = $value < 0 ? -1 : 1;

        return $sign * floor(abs($value) * $factor + 0.5) / $factor;
    }

    private function toNum(float|bool $value): float
    {
        if (is_bool($value)) {
            throw new ExpressionException('Expected a number but got a boolean');
        }

        return $value;
    }

    private function toBool(float|bool $value): bool
    {
        return is_bool($value) ? $value : $value !== 0.0;
    }

    private function looseEquals(float|bool $left, float|bool $right): bool
    {
        if (is_bool($left) || is_bool($right)) {
            return $this->toBool($left) === $this->toBool($right);
        }

        return $left === $right;
    }
}
