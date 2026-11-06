// A tiny, self-contained evaluator for the restricted calculator grammar. It is the
// mirror of the PHP twin (app/Calculators/Expression/ExpressionEvaluator.php) — "same
// formula → same number on client and server" is the whole promise, and a shared
// golden-vector fixture (tests/fixtures/expression-golden.json) fails CI the moment the
// two drift. When you change one side, change the other and re-run both suites.
//
// It parses to an AST first, THEN evaluates, so ternary and &&/|| short-circuit correctly
// — the profit ROI node `cost == 0 ? 0 : profit / cost * 100` must never evaluate the
// divide when cost is zero.
//
// Grammar (lowest → highest precedence):
//   ternary ?: (right-assoc) | || | && | == != | < <= > >= | + - | * / % | unary - ! | primary
// `%` is float remainder (JS % — matches PHP fmod). round() is half-away-from-zero via an
// explicit formula. Division/modulo by zero throws. Functions: min, max, abs, round, pow, sqrt.
//
// The trailing `grammar` (./grammar.ts) is the seam the language grows through (ADR 0006): a call
// consults its function table first, an identifier its constants first, and the tokenizer reads
// e-notation only when its flag is on. DEFAULT_GRAMMAR is exactly the grammar above.

import { DEFAULT_GRAMMAR, grammarFunction, isGrammarConstant } from './grammar';
import type { Grammar } from './grammar';

export type EnvValue = number | boolean;
export type Env = Record<string, EnvValue>;
export type ExprValue = number | boolean;

export class ExpressionError extends Error {}

type Token = { type: 'number' | 'ident' | 'op'; value: string };

type Node =
    | { t: 'num'; v: number }
    | { t: 'var'; name: string }
    | { t: 'un'; op: '-' | '!'; e: Node }
    | { t: 'bin'; op: BinOp; l: Node; r: Node }
    | { t: 'log'; op: '&&' | '||'; l: Node; r: Node }
    | { t: 'tern'; c: Node; a: Node; b: Node }
    | { t: 'call'; name: string; args: Node[] };

type BinOp =
    '+' | '-' | '*' | '/' | '%' | '<' | '<=' | '>' | '>=' | '==' | '!=';

const TWO_CHAR_OPS = new Set(['==', '!=', '<=', '>=', '&&', '||']);
const ONE_CHAR_OPS = new Set([
    '+',
    '-',
    '*',
    '/',
    '%',
    '<',
    '>',
    '!',
    '(',
    ')',
    ',',
    '?',
    ':',
]);

function isDigit(ch: string): boolean {
    return ch >= '0' && ch <= '9';
}

function isAlpha(ch: string): boolean {
    return (ch >= 'a' && ch <= 'z') || (ch >= 'A' && ch <= 'Z') || ch === '_';
}

function isAlnum(ch: string): boolean {
    return isAlpha(ch) || isDigit(ch);
}

function tokenize(input: string, grammar: Grammar): Token[] {
    const tokens: Token[] = [];
    let i = 0;

    while (i < input.length) {
        const ch = input[i];

        if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') {
            i++;
            continue;
        }

        if (
            isDigit(ch) ||
            (ch === '.' && i + 1 < input.length && isDigit(input[i + 1]))
        ) {
            const start = i;

            while (
                i < input.length &&
                (isDigit(input[i]) || input[i] === '.')
            ) {
                i++;
            }

            if (
                grammar.exponentLiterals &&
                i < input.length &&
                (input[i] === 'e' || input[i] === 'E')
            ) {
                let j = i + 1;

                if (
                    j < input.length &&
                    (input[j] === '+' || input[j] === '-')
                ) {
                    j++;
                }

                if (j < input.length && isDigit(input[j])) {
                    i = j;

                    while (i < input.length && isDigit(input[i])) {
                        i++;
                    }
                }
            }

            tokens.push({ type: 'number', value: input.slice(start, i) });
            continue;
        }

        if (isAlpha(ch)) {
            const start = i;

            while (i < input.length && isAlnum(input[i])) {
                i++;
            }

            tokens.push({ type: 'ident', value: input.slice(start, i) });
            continue;
        }

        const two = input.slice(i, i + 2);

        if (TWO_CHAR_OPS.has(two)) {
            tokens.push({ type: 'op', value: two });
            i += 2;
            continue;
        }

        if (ONE_CHAR_OPS.has(ch)) {
            tokens.push({ type: 'op', value: ch });
            i++;
            continue;
        }

        throw new ExpressionError(`Unexpected character '${ch}'`);
    }

    return tokens;
}

// --- parser: builds an AST, never evaluates -------------------------------------------

class Parser {
    private pos = 0;

    constructor(private readonly tokens: Token[]) {}

    parse(): Node {
        const ast = this.ternary();

        if (this.pos < this.tokens.length) {
            throw new ExpressionError(
                `Unexpected token '${this.tokens[this.pos].value}'`,
            );
        }

        return ast;
    }

    private peek(): Token | undefined {
        return this.tokens[this.pos];
    }

    private matchOp(value: string): boolean {
        const token = this.peek();

        if (token && token.type === 'op' && token.value === value) {
            this.pos++;

            return true;
        }

        return false;
    }

    private ternary(): Node {
        const condition = this.or();

        if (this.matchOp('?')) {
            const whenTrue = this.ternary();

            if (!this.matchOp(':')) {
                throw new ExpressionError("Expected ':' in ternary");
            }

            const whenFalse = this.ternary();

            return { t: 'tern', c: condition, a: whenTrue, b: whenFalse };
        }

        return condition;
    }

    private or(): Node {
        let left = this.and();

        while (this.matchOp('||')) {
            left = { t: 'log', op: '||', l: left, r: this.and() };
        }

        return left;
    }

    private and(): Node {
        let left = this.equality();

        while (this.matchOp('&&')) {
            left = { t: 'log', op: '&&', l: left, r: this.equality() };
        }

        return left;
    }

    private equality(): Node {
        let left = this.relational();

        for (;;) {
            if (this.matchOp('==')) {
                left = { t: 'bin', op: '==', l: left, r: this.relational() };
            } else if (this.matchOp('!=')) {
                left = { t: 'bin', op: '!=', l: left, r: this.relational() };
            } else {
                break;
            }
        }

        return left;
    }

    private relational(): Node {
        let left = this.additive();

        for (;;) {
            if (this.matchOp('<=')) {
                left = { t: 'bin', op: '<=', l: left, r: this.additive() };
            } else if (this.matchOp('>=')) {
                left = { t: 'bin', op: '>=', l: left, r: this.additive() };
            } else if (this.matchOp('<')) {
                left = { t: 'bin', op: '<', l: left, r: this.additive() };
            } else if (this.matchOp('>')) {
                left = { t: 'bin', op: '>', l: left, r: this.additive() };
            } else {
                break;
            }
        }

        return left;
    }

    private additive(): Node {
        let left = this.multiplicative();

        for (;;) {
            if (this.matchOp('+')) {
                left = { t: 'bin', op: '+', l: left, r: this.multiplicative() };
            } else if (this.matchOp('-')) {
                left = { t: 'bin', op: '-', l: left, r: this.multiplicative() };
            } else {
                break;
            }
        }

        return left;
    }

    private multiplicative(): Node {
        let left = this.unary();

        for (;;) {
            if (this.matchOp('*')) {
                left = { t: 'bin', op: '*', l: left, r: this.unary() };
            } else if (this.matchOp('/')) {
                left = { t: 'bin', op: '/', l: left, r: this.unary() };
            } else if (this.matchOp('%')) {
                left = { t: 'bin', op: '%', l: left, r: this.unary() };
            } else {
                break;
            }
        }

        return left;
    }

    private unary(): Node {
        if (this.matchOp('-')) {
            return { t: 'un', op: '-', e: this.unary() };
        }

        if (this.matchOp('!')) {
            return { t: 'un', op: '!', e: this.unary() };
        }

        return this.primary();
    }

    private primary(): Node {
        if (this.matchOp('(')) {
            const node = this.ternary();

            if (!this.matchOp(')')) {
                throw new ExpressionError("Expected ')'");
            }

            return node;
        }

        const token = this.peek();

        if (!token) {
            throw new ExpressionError('Unexpected end of expression');
        }

        if (token.type === 'number') {
            this.pos++;

            return { t: 'num', v: Number(token.value) };
        }

        if (token.type === 'ident') {
            this.pos++;
            const name = token.value;

            if (this.matchOp('(')) {
                const args: Node[] = [];

                if (!this.matchOp(')')) {
                    do {
                        args.push(this.ternary());
                    } while (this.matchOp(','));

                    if (!this.matchOp(')')) {
                        throw new ExpressionError(
                            "Expected ')' after arguments",
                        );
                    }
                }

                return { t: 'call', name, args };
            }

            return { t: 'var', name };
        }

        throw new ExpressionError(`Unexpected token '${token.value}'`);
    }
}

// --- evaluator: walks the AST, short-circuits -----------------------------------------

function toNum(value: ExprValue): number {
    if (typeof value === 'boolean') {
        throw new ExpressionError('Expected a number but got a boolean');
    }

    return value;
}

function toBool(value: ExprValue): boolean {
    return typeof value === 'boolean' ? value : value !== 0;
}

function looseEquals(left: ExprValue, right: ExprValue): boolean {
    if (typeof left === 'boolean' || typeof right === 'boolean') {
        return toBool(left) === toBool(right);
    }

    return left === right;
}

// The scale is the EXACT power of ten, parsed from its literal (exact for |p| <= 22), and a negative
// precision divides rather than multiplying by a fraction, so every operation is exact on identical
// operands (the host `10 ** p` is not: V8 gives 10 ** -4 = 0.00009999999999999999).
function roundHalfAway(value: number, precision: number): number {
    const factor = Number(`1e${Math.abs(precision)}`);
    const sign = value < 0 ? -1 : 1;

    return precision >= 0
        ? (sign * Math.floor(Math.abs(value) * factor + 0.5)) / factor
        : sign * Math.floor(Math.abs(value) / factor + 0.5) * factor;
}

// round()'s precision: an integer in [-22, 22] after truncation toward zero, or an error.
function precision(value: number): number {
    if (!Number.isFinite(value)) {
        throw new ExpressionError('round() precision must be finite');
    }

    const truncated = Math.trunc(value);

    if (truncated < -22 || truncated > 22) {
        throw new ExpressionError('round() precision must be within [-22, 22]');
    }

    return truncated === 0 ? 0 : truncated;
}

function evaluateCall(name: string, args: number[], grammar: Grammar): number {
    const count = args.length;
    const arity = (): never => {
        throw new ExpressionError(`Function '${name}' arity mismatch`);
    };

    const fn = grammarFunction(grammar, name);

    if (fn) {
        return fn(args);
    }

    switch (name) {
        case 'min':
            return count >= 1 ? Math.min(...args) : arity();
        case 'max':
            return count >= 1 ? Math.max(...args) : arity();
        case 'abs':
            return count === 1 ? Math.abs(args[0]) : arity();
        case 'sqrt':
            return count === 1 ? Math.sqrt(args[0]) : arity();
        case 'pow':
            return count === 2 ? args[0] ** args[1] : arity();
        case 'round':
            return count >= 1 && count <= 2
                ? roundHalfAway(args[0], count === 2 ? precision(args[1]) : 0)
                : arity();
        default:
            throw new ExpressionError(`Unknown function '${name}'`);
    }
}

function evaluateNode(node: Node, env: Env, grammar: Grammar): ExprValue {
    switch (node.t) {
        case 'num':
            return node.v;
        case 'var': {
            if (isGrammarConstant(grammar, node.name)) {
                return grammar.constants[node.name];
            }

            if (!(node.name in env)) {
                throw new ExpressionError(`Unknown identifier '${node.name}'`);
            }

            const value = env[node.name];

            return typeof value === 'boolean' ? value : Number(value);
        }
        case 'un': {
            const value = evaluateNode(node.e, env, grammar);

            return node.op === '-' ? -toNum(value) : !toBool(value);
        }
        case 'log': {
            const left = toBool(evaluateNode(node.l, env, grammar));

            if (node.op === '&&') {
                return left
                    ? toBool(evaluateNode(node.r, env, grammar))
                    : false;
            }

            return left ? true : toBool(evaluateNode(node.r, env, grammar));
        }
        case 'tern':
            return toBool(evaluateNode(node.c, env, grammar))
                ? evaluateNode(node.a, env, grammar)
                : evaluateNode(node.b, env, grammar);
        case 'call':
            return evaluateCall(
                node.name,
                node.args.map((arg) => toNum(evaluateNode(arg, env, grammar))),
                grammar,
            );
        case 'bin':
            return evaluateBinary(node, env, grammar);
    }
}

function evaluateBinary(
    node: Extract<Node, { t: 'bin' }>,
    env: Env,
    grammar: Grammar,
): ExprValue {
    const left = evaluateNode(node.l, env, grammar);
    const right = evaluateNode(node.r, env, grammar);

    switch (node.op) {
        case '+':
            return toNum(left) + toNum(right);
        case '-':
            return toNum(left) - toNum(right);
        case '*':
            return toNum(left) * toNum(right);
        case '/': {
            const divisor = toNum(right);

            if (divisor === 0) {
                throw new ExpressionError('Division by zero');
            }

            return toNum(left) / divisor;
        }
        case '%': {
            const divisor = toNum(right);

            if (divisor === 0) {
                throw new ExpressionError('Modulo by zero');
            }

            return toNum(left) % divisor;
        }
        case '<':
            return toNum(left) < toNum(right);
        case '<=':
            return toNum(left) <= toNum(right);
        case '>':
            return toNum(left) > toNum(right);
        case '>=':
            return toNum(left) >= toNum(right);
        case '==':
            return looseEquals(left, right);
        case '!=':
            return !looseEquals(left, right);
    }
}

export function parse(
    expression: string,
    grammar: Grammar = DEFAULT_GRAMMAR,
): Node {
    return new Parser(tokenize(expression, grammar)).parse();
}

export function evaluate(
    expression: string,
    env: Env = {},
    grammar: Grammar = DEFAULT_GRAMMAR,
): ExprValue {
    return evaluateNode(parse(expression, grammar), env, grammar);
}
