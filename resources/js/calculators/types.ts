// Hand-written types for a Calculator's `graph` JSON. The server ships the graph as
// `Record<string, any>` (CalculatorSpecData); this is the shape it actually holds, kept in
// step with the seed schema (database/seeders/CryptSeeder.php) and the PHP graph engine.

export type NodeFormat = 'currency' | 'percent' | 'number';

export interface NumberControl {
    type: 'number';
    default: number;
    min?: number;
    max?: number;
    step?: number | 'any';
    unit?: string;
}

export interface EnumControl {
    type: 'enum';
    default: string;
    options: string[];
}

// A field within a collection row. `symbol` picks a live asset (drives per-row refs);
// `toggle` is an include/exclude boolean; `number` is a per-row numeric input.
export interface CollectionField {
    id: string;
    type: 'symbol' | 'toggle' | 'number';
    label: string;
    default?: number | string | boolean;
    options?: string[];
    unit?: string;
    step?: number | 'any';
}

export type CollectionRow = Record<string, number | string | boolean>;

export interface CollectionControl {
    type: 'collection';
    fields: CollectionField[];
    default: CollectionRow[];
    addLabel?: string;
    minRows?: number;
    maxRows?: number;
}

export type NodeControl = NumberControl | EnumControl | CollectionControl;

export type Reducer = 'sum' | 'avg' | 'min' | 'max' | 'count';

export interface RefSpec {
    connector: string;
    metric: string;
    symbolFrom?: string;
    symbol?: string;
}

export interface CalculatorNode {
    id: string;
    kind: App.Enums.NodeKind;
    label: string;
    expr?: string;
    deps?: string[];
    ref?: RefSpec;
    format?: NodeFormat;
    control?: NodeControl;
    /** `each`/`aggregate`/vector-`ref`: the collection node id this node iterates over. */
    over?: string;
    /** `aggregate`: the vector node id being folded. */
    of?: string;
    /** `aggregate`: how the vector folds to a scalar. */
    reducer?: Reducer;
    /** `fold`: how many periods to iterate — a scalar node id or a literal count. */
    count?: string | number;
    /** `fold`: expression for the seed value (period 0), over the outer scalars. */
    init?: string;
    /** `fold`: per-period recurrence; in scope: outer scalars, `acc` (previous value), `i` (1-based period). */
    step?: string;
}

export interface ChartSeries {
    id: string;
    label: string;
}

export interface ChartSpec {
    type: string;
    title?: string;
    series: ChartSeries[];
    /** For collection calculators: the vector node id charted one bar/slice per row. */
    overNode?: string;
    valueNode?: string;
    labelField?: string;
}

// Renders a per-row results grid for a collection calculator. Each column is either a
// row field of the collection (e.g. `symbol`) or a vector node id (e.g. `allocation`).
export interface TableColumn {
    source: 'field' | 'vector';
    id: string;
    label: string;
    format?: NodeFormat;
}

export interface TableSpec {
    over: string;
    columns: TableColumn[];
}

export interface CalculatorGraph {
    nodes: CalculatorNode[];
    outputs: string[];
    chart?: ChartSpec;
    table?: TableSpec;
}
