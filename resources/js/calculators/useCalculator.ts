import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { Env, ExprValue } from './expression/evaluate';
import { type Collections, evaluateGraph, type Vectors } from './graph';
import type { CalculatorGraph, CollectionControl, CollectionRow } from './types';

type Resolution = App.Data.CalculatorResolutionData;
type InputValue = number | string;

export interface UseCalculator {
    inputs: Record<string, InputValue>;
    setInput: (id: string, value: InputValue) => void;
    collections: Collections;
    setCell: (collectionId: string, rowIndex: number, field: string, value: number | string | boolean) => void;
    addRow: (collectionId: string) => void;
    removeRow: (collectionId: string, rowIndex: number) => void;
    values: Record<string, number>;
    vectors: Vectors;
    facts: Resolution['facts'];
    vectorFacts: Resolution['vectorFacts'];
    refreshing: boolean;
    /** input ids whose change triggers a server round-trip (they feed a ref symbol). */
    refInputs: Set<string>;
}

function scalarInputs(graph: CalculatorGraph): Record<string, InputValue> {
    const inputs: Record<string, InputValue> = {};
    for (const node of graph.nodes) {
        if (node.kind === 'input' && node.control && node.control.type !== 'collection') {
            inputs[node.id] = node.control.default;
        }
    }
    return inputs;
}

function initialCollections(graph: CalculatorGraph): Collections {
    const collections: Collections = {};
    for (const node of graph.nodes) {
        if (node.kind === 'collection' && node.control?.type === 'collection') {
            // Clone so per-row edits never mutate the spec's default.
            collections[node.id] = node.control.default.map((row) => ({ ...row }));
        }
    }
    return collections;
}

function refInputIds(graph: CalculatorGraph): Set<string> {
    const ids = new Set<string>();
    for (const node of graph.nodes) {
        if (node.kind === 'ref' && !node.over && node.ref?.symbolFrom) {
            ids.add(node.ref.symbolFrom);
        }
    }
    return ids;
}

// Collections that feed a vector ref — editing their symbol set requires a server round-trip
// to re-resolve the per-row facts. Maps collectionId → the symbol field ids that gate a refetch.
function refCollections(graph: CalculatorGraph): Map<string, Set<string>> {
    const map = new Map<string, Set<string>>();
    for (const node of graph.nodes) {
        if (node.kind === 'ref' && node.over && node.ref?.symbolFrom) {
            const fields = map.get(node.over) ?? new Set<string>();
            fields.add(node.ref.symbolFrom);
            map.set(node.over, fields);
        }
    }
    return map;
}

function controlFor(graph: CalculatorGraph, collectionId: string): CollectionControl | undefined {
    const node = graph.nodes.find((n) => n.id === collectionId);
    return node?.control?.type === 'collection' ? node.control : undefined;
}

function blankRow(control: CollectionControl): CollectionRow {
    const row: CollectionRow = {};
    for (const field of control.fields) {
        row[field.id] = field.default ?? (field.type === 'symbol' ? (field.options?.[0] ?? '') : field.type === 'toggle' ? true : 0);
    }
    return row;
}

// null seeds (a row that could not be priced) become NaN so a per-row expression still runs
// and the value simply drops out of any aggregate; the UI renders "—".
function seedVectors(resolution: Resolution): Vectors {
    const vectors: Vectors = {};
    for (const [id, values] of Object.entries(resolution.seedVectors ?? {})) {
        vectors[id] = (values as (number | null)[]).map((v) => (v === null ? Number.NaN : v));
    }
    return vectors;
}

/**
 * The calculator's live state. Scalar inputs recompute the whole graph on every keystroke
 * with no network. Collections (arrays of rows) drive `each`/`aggregate` nodes locally too —
 * only changing the SET of priced symbols (add/remove a row, pick a different asset)
 * round-trips to re-resolve the per-row facts, with `refreshing` true while it does.
 */
export function useCalculator(graph: CalculatorGraph, resolution: Resolution): UseCalculator {
    const [inputs, setInputs] = useState<Record<string, InputValue>>(() => scalarInputs(graph));
    const [collections, setCollections] = useState<Collections>(() => initialCollections(graph));
    const [refreshing, setRefreshing] = useState(false);

    const refInputs = useMemo(() => refInputIds(graph), [graph]);
    const refCols = useMemo(() => refCollections(graph), [graph]);
    const baseVectors = useMemo(() => seedVectors(resolution), [resolution]);

    const result = useMemo(() => {
        const env: Env = { ...resolution.seedEnv };
        for (const [id, value] of Object.entries(inputs)) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                env[id] = value;
            }
        }
        return evaluateGraph(graph, env, collections, baseVectors);
    }, [inputs, collections, resolution, baseVectors, graph]);

    const values = useMemo(() => {
        const out: Record<string, number> = {};
        for (const [id, value] of Object.entries(result.scalars)) {
            if (typeof value === 'number') {
                out[id] = value;
            }
        }
        return out;
    }, [result]);

    const reresolve = (nextCollections: Collections): void => {
        // JSON-encode each collection: an array of row objects does not survive GET query
        // serialization intact (indices split per field), but the resolver json_decodes a
        // string row set. One param per collection also keeps reloads from accumulating.
        const data: Record<string, string> = {};
        for (const [id, rows] of Object.entries(nextCollections)) {
            data[id] = JSON.stringify(rows);
        }

        router.reload({
            only: ['resolution'],
            data,
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });
    };

    const setInput = (id: string, value: InputValue): void => {
        setInputs((prev) => ({ ...prev, [id]: value }));

        if (refInputs.has(id)) {
            router.reload({
                only: ['resolution'],
                data: { [id]: value },
                onStart: () => setRefreshing(true),
                onFinish: () => setRefreshing(false),
            });
        }
    };

    const setCell = (collectionId: string, rowIndex: number, field: string, value: number | string | boolean): void => {
        setCollections((prev) => {
            const rows = (prev[collectionId] ?? []).map((row, r) => (r === rowIndex ? { ...row, [field]: value } : row));
            const next = { ...prev, [collectionId]: rows };
            if (refCols.get(collectionId)?.has(field)) {
                reresolve(next);
            }
            return next;
        });
    };

    const addRow = (collectionId: string): void => {
        const control = controlFor(graph, collectionId);
        if (!control) {
            return;
        }
        setCollections((prev) => {
            const rows = [...(prev[collectionId] ?? []), blankRow(control)];
            const next = { ...prev, [collectionId]: rows };
            if (refCols.has(collectionId)) {
                reresolve(next);
            }
            return next;
        });
    };

    const removeRow = (collectionId: string, rowIndex: number): void => {
        const control = controlFor(graph, collectionId);
        const minRows = control?.minRows ?? 1;
        setCollections((prev) => {
            const current = prev[collectionId] ?? [];
            if (current.length <= minRows) {
                return prev;
            }
            const rows = current.filter((_, r) => r !== rowIndex);
            const next = { ...prev, [collectionId]: rows };
            if (refCols.has(collectionId)) {
                reresolve(next);
            }
            return next;
        });
    };

    return {
        inputs,
        setInput,
        collections,
        setCell,
        addRow,
        removeRow,
        values,
        vectors: result.vectors,
        facts: resolution.facts,
        vectorFacts: resolution.vectorFacts ?? {},
        refreshing,
        refInputs,
    };
}

export type { ExprValue };
