import { evaluate } from './expression/evaluate';
import type { Env, ExprValue } from './expression/evaluate';
import { DEFAULT_GRAMMAR } from './expression/grammar';
import type { Grammar } from './expression/grammar';
import type {
    CalculatorGraph,
    CalculatorNode,
    CollectionRow,
    Reducer,
} from './types';

// Client-side graph engine — the arithmetic runs here, not on the server (the server only
// resolves refs into numbers; see app/Calculators/CalculatorResolver.php). The scalar
// expression grammar (./expression/evaluate) is untouched; this layer orchestrates it over
// collections so a calculator can hold an ARRAY of rows:
//   - `each`      maps a scalar expr over a collection's rows            → a vector
//   - `aggregate` folds a vector to a scalar (sum/avg/min/max/count)     → a scalar
//   - a `ref` with `over` is resolved once per row by the server         → a vector (seeded)
// Locus still falls out of the dep structure: refs are pre-resolved; everything else here.

export type Scalars = Record<string, ExprValue>;
export type Vectors = Record<string, ExprValue[]>;
export type Collections = Record<string, CollectionRow[]>;

export interface GraphResult {
    scalars: Scalars;
    vectors: Vectors;
}

export function nodeById(
    graph: CalculatorGraph,
): Record<string, CalculatorNode> {
    const map: Record<string, CalculatorNode> = {};

    for (const node of graph.nodes) {
        map[node.id] = node;
    }

    return map;
}

// Kahn's algorithm — identical ordering to the PHP engine. A cycle throws.
export function topoOrder(graph: CalculatorGraph): string[] {
    const indegree: Record<string, number> = {};
    const dependents: Record<string, string[]> = {};

    for (const node of graph.nodes) {
        indegree[node.id] ??= 0;

        for (const dep of node.deps ?? []) {
            (dependents[dep] ??= []).push(node.id);
            indegree[node.id]++;
        }
    }

    const queue = graph.nodes
        .filter((node) => indegree[node.id] === 0)
        .map((node) => node.id);
    const order: string[] = [];

    while (queue.length > 0) {
        const id = queue.shift() as string;
        order.push(id);

        for (const dependent of dependents[id] ?? []) {
            if (--indegree[dependent] === 0) {
                queue.push(dependent);
            }
        }
    }

    if (order.length !== graph.nodes.length) {
        throw new Error('Calculator graph has a dependency cycle.');
    }

    return order;
}

function reduceVector(values: ExprValue[], reducer: Reducer): number {
    const nums = values.filter(
        (v): v is number => typeof v === 'number' && Number.isFinite(v),
    );

    switch (reducer) {
        case 'count':
            return values.length;
        case 'sum':
            return nums.reduce((total, v) => total + v, 0);
        case 'avg':
            return nums.length === 0
                ? 0
                : nums.reduce((total, v) => total + v, 0) / nums.length;
        case 'min':
            return nums.length === 0 ? 0 : Math.min(...nums);
        case 'max':
            return nums.length === 0 ? 0 : Math.max(...nums);
    }
}

// Maps a scalar expression over one collection's rows. In each row's scope: the outer
// scalars, the row's own numeric/boolean fields, and any already-computed vector (a per-row
// ref or another `each`) that iterates the SAME collection, indexed at this row.
function evaluateEach(
    node: CalculatorNode,
    rows: CollectionRow[],
    scalars: Scalars,
    vectors: Vectors,
    vectorOver: Record<string, string | undefined>,
    grammar: Grammar,
): ExprValue[] {
    const over = node.over;
    const expr = node.expr;

    if (!over || !expr) {
        return [];
    }

    const siblingVectors = Object.keys(vectors).filter(
        (id) => vectorOver[id] === over,
    );

    return rows.map((row, r) => {
        const rowEnv: Env = { ...scalars };

        for (const [field, value] of Object.entries(row)) {
            if (typeof value === 'number' || typeof value === 'boolean') {
                rowEnv[field] = value;
            }
        }

        for (const id of siblingVectors) {
            const v = vectors[id][r];

            if (typeof v === 'number' || typeof v === 'boolean') {
                rowEnv[id] = v;
            }
        }

        try {
            return evaluate(expr, rowEnv, grammar);
        } catch {
            return Number.NaN;
        }
    });
}

const FOLD_MAX_PERIODS = 10_000;

// A temporal recurrence. Seed `acc` from `init`, then iterate `step` `count` times with the
// PREVIOUS `acc` and the 1-based period `i` in scope. Returns the whole series (one entry per
// period) plus its final value — so a fold is both a vector (the growth curve) and a scalar
// (the ending balance). Pure client arithmetic; no ref, so locus stays derived.
function evaluateFold(
    node: CalculatorNode,
    scalars: Scalars,
    grammar: Grammar,
): { series: ExprValue[]; final: ExprValue } {
    const seed = node.init
        ? safeEvaluate(node.init, scalars, grammar)
        : Number.NaN;

    const rawCount =
        typeof node.count === 'number' ? node.count : scalars[node.count ?? ''];
    const n =
        typeof rawCount === 'number' && Number.isFinite(rawCount)
            ? Math.max(0, Math.min(FOLD_MAX_PERIODS, Math.round(rawCount)))
            : 0;

    const step = node.step;

    if (!step) {
        return { series: [], final: seed };
    }

    const series: ExprValue[] = [];
    let acc: ExprValue = seed;

    for (let i = 1; i <= n; i++) {
        acc = safeEvaluate(
            step,
            { ...scalars, acc: typeof acc === 'number' ? acc : Number.NaN, i },
            grammar,
        );
        series.push(acc);
    }

    return { series, final: acc };
}

function safeEvaluate(expr: string, env: Env, grammar: Grammar): ExprValue {
    try {
        return evaluate(expr, env, grammar);
    } catch {
        return Number.NaN;
    }
}

/**
 * Evaluate the whole graph over the seeded environment. `collections` holds the user's array
 * inputs; `baseVectors` holds server-resolved per-row refs. Returns the resolved scalars and
 * vectors; a node that cannot evaluate is simply left out (the UI renders "—").
 */
export function evaluateGraph(
    graph: CalculatorGraph,
    baseScalars: Env,
    collections: Collections = {},
    baseVectors: Vectors = {},
    grammar: Grammar = DEFAULT_GRAMMAR,
): GraphResult {
    const byId = nodeById(graph);
    const scalars: Scalars = { ...baseScalars };
    const vectors: Vectors = { ...baseVectors };

    const vectorOver: Record<string, string | undefined> = {};

    for (const node of graph.nodes) {
        if (node.over && (node.kind === 'each' || node.kind === 'ref')) {
            vectorOver[node.id] = node.over;
        }
    }

    for (const id of topoOrder(graph)) {
        const node = byId[id];

        if (!node) {
            continue;
        }

        if (node.kind === 'each') {
            const rows = collections[node.over ?? ''] ?? [];
            vectors[id] = evaluateEach(
                node,
                rows,
                scalars,
                vectors,
                vectorOver,
                grammar,
            );
            continue;
        }

        if (node.kind === 'fold') {
            const { series, final } = evaluateFold(node, scalars, grammar);
            vectors[id] = series;
            scalars[id] = final;
            continue;
        }

        if (node.kind === 'aggregate' && node.of && node.reducer) {
            scalars[id] = reduceVector(vectors[node.of] ?? [], node.reducer);
            continue;
        }

        // Scalar computed/output nodes (refs and inputs are already seeded).
        if (node.expr && node.kind !== 'ref') {
            try {
                const value = evaluate(node.expr, scalars, grammar);
                scalars[id] = value;
            } catch {
                // leave unresolved — the readout shows "—"
            }
        }
    }

    return { scalars, vectors };
}

/**
 * Scalar-only convenience for the classic arithmetic DAG (Profit et al.): evaluate every
 * computed/output node over the base env and return the resolved scalars.
 */
export function computeValues(
    graph: CalculatorGraph,
    baseEnv: Env,
): Record<string, ExprValue> {
    return evaluateGraph(graph, baseEnv).scalars;
}
