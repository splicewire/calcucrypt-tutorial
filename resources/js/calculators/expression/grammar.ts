// The grammar an evaluator runs under — the seam the expression language grows through (ADR 0006). The
// mirror of the PHP twin (app/Calculators/Expression/Grammar.php). An evaluator consults it in three
// places: a call looks in `functions` before the built-ins, an identifier looks in `constants` before
// the environment, and the tokenizer reads `1.5e-3` as one number only when `exponentLiterals` is on.
//
// DEFAULT_GRAMMAR is exactly the original grammar: no table entries, no constants, no exponent
// literals, so the six built-ins (min, max, abs, round, pow, sqrt) are all there is.

/** A table function: receives the evaluated arguments and returns a number, or throws. */
export type GrammarFunction = (args: number[]) => number;

export interface Grammar {
    readonly functions: Readonly<Record<string, GrammarFunction>>;
    readonly constants: Readonly<Record<string, number>>;
    readonly exponentLiterals: boolean;
}

export const DEFAULT_GRAMMAR: Grammar = Object.freeze({
    functions: Object.freeze({}),
    constants: Object.freeze({}),
    exponentLiterals: false,
});

/** The table entry for `name`, if the grammar has one (own keys only, never the prototype's). */
export function grammarFunction(
    grammar: Grammar,
    name: string,
): GrammarFunction | undefined {
    return Object.hasOwn(grammar.functions, name)
        ? grammar.functions[name]
        : undefined;
}

/** Whether `name` is a constant of the grammar (own keys only). */
export function isGrammarConstant(grammar: Grammar, name: string): boolean {
    return Object.hasOwn(grammar.constants, name);
}
