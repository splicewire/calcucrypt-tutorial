import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { type Env, evaluate, ExpressionError } from './evaluate';

// The TS half of the evaluator-parity contract. It runs the SAME golden vectors the PHP
// Pest suite runs (tests/fixtures/expression-golden.json). If a change makes TS and PHP
// disagree, one of the two suites goes red — that is the whole point.

type GoldenCase = { expr: string; env: Env; expect: number | boolean };

const fixturePath = fileURLToPath(new URL('../../../../tests/fixtures/expression-golden.json', import.meta.url));
const cases: GoldenCase[] = JSON.parse(readFileSync(fixturePath, 'utf-8')).cases;

describe('expression evaluator parity', () => {
    it.each(cases)('evaluates $expr', ({ expr, env, expect: expected }) => {
        const actual = evaluate(expr, env);

        if (typeof expected === 'boolean') {
            expect(actual).toBe(expected);
        } else {
            expect(actual).toBeCloseTo(expected, 9);
        }
    });

    it('throws on division by zero', () => {
        expect(() => evaluate('1 / 0')).toThrow(ExpressionError);
    });

    it('does not evaluate the untaken ternary branch (short-circuit)', () => {
        expect(evaluate('cost == 0 ? 0 : profit / cost', { cost: 0, profit: 5 })).toBe(0);
    });

    it('throws on an unknown identifier', () => {
        expect(() => evaluate('mystery + 1')).toThrow(ExpressionError);
    });

    it('throws on a malformed expression', () => {
        expect(() => evaluate('1 + ')).toThrow(ExpressionError);
    });
});
