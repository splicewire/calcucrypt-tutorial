import { useMemo, useState } from 'react';
import { useApiCalculatorsResolve } from '@/generated/hooks/api-calculators';
import type { Env, ExprValue } from './expression/evaluate';
import { type Collections, evaluateGraph, type Vectors } from './graph';
import type { CalculatorGraph, CollectionControl, CollectionRow } from './types';

type Resolution = App.Data.CalculatorResolutionData;
type ResolveInput = App.Data.CalculatorResolveInputData;
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
 * round-trips to re-resolve the facts, with `refreshing` true while it does.
 *
 * The page's server render supplies the first resolution. Every later one comes from the
 * `resolve` operation (`POST /api/calculators/{slug}/resolve`) through its generated hook, so
 * the tool page and a calculator embedded in a guide re-resolve the same way.
 */
export function useCalculator(slug: string, graph: CalculatorGraph, initialResolution: Resolution): UseCalculator {
    const [inputs, setInputs] = useState<Record<string, InputValue>>(() => scalarInputs(graph));
    const [collections, setCollections] = useState<Collections>(() => initialCollections(graph));
    const [resolution, setResolution] = useState<Resolution>(initialResolution);
    const resolve = useApiCalculatorsResolve();

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

    // Send the server only what it prices: the inputs that steer a ref, and each priced
    // collection's symbol fields. Everything else stays in the browser.
    const reresolve = (nextInputs: Record<string, InputValue>, nextCollections: Collections): void => {
        const body: ResolveInput = { inputs: {} };
        for (const id of refInputs) {
            body.inputs[id] = nextInputs[id];
        }
        for (const [id, fields] of refCols) {
            body.inputs[id] = (nextCollections[id] ?? []).map((row) =>
                Object.fromEntries([...fields].map((field) => [field, row[field]])),
            );
        }

        // A mutate-level `onSuccess` fires for the latest call only, so a slow earlier
        // response can never overwrite a newer one.
        resolve.mutate({ params: { id: slug }, body }, { onSuccess: setResolution });
    };

    const setInput = (id: string, value: InputValue): void => {
        const next = { ...inputs, [id]: value };
        setInputs(next);

        if (refInputs.has(id)) {
            reresolve(next, collections);
        }
    };

    // Collections apply in place: compute the next rows from the current state, then store
    // them and (when the priced symbols changed) re-resolve with the same rows.
    const updateCollection = (collectionId: string, rows: CollectionRow[], reprices: boolean): void => {
        const next = { ...collections, [collectionId]: rows };
        setCollections(next);

        if (reprices) {
            reresolve(inputs, next);
        }
    };

    const setCell = (collectionId: string, rowIndex: number, field: string, value: number | string | boolean): void => {
        const rows = (collections[collectionId] ?? []).map((row, r) => (r === rowIndex ? { ...row, [field]: value } : row));
        updateCollection(collectionId, rows, refCols.get(collectionId)?.has(field) ?? false);
    };

    const addRow = (collectionId: string): void => {
        const control = controlFor(graph, collectionId);
        if (!control) {
            return;
        }
        updateCollection(collectionId, [...(collections[collectionId] ?? []), blankRow(control)], refCols.has(collectionId));
    };

    const removeRow = (collectionId: string, rowIndex: number): void => {
        const control = controlFor(graph, collectionId);
        const minRows = control?.minRows ?? 1;
        const current = collections[collectionId] ?? [];
        if (current.length <= minRows) {
            return;
        }
        updateCollection(
            collectionId,
            current.filter((_, r) => r !== rowIndex),
            refCols.has(collectionId),
        );
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
        refreshing: resolve.isPending,
        refInputs,
    };
}

export type { ExprValue };
