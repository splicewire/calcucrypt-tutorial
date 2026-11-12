import { Minus, Plus, X } from 'lucide-react';
import { formatValue } from '@/calculators/format';
import { nodeById } from '@/calculators/graph';
import type { CalculatorGraph, CalculatorNode, CollectionControl, EnumControl, NumberControl, TableColumn } from '@/calculators/types';
import { type UseCalculator, useCalculator } from '@/calculators/useCalculator';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { CalculatorReadout, type Readout } from './calculator-readout';
import { ResultChart } from './result-chart';
import { SavedInputsPanel } from './saved-inputs-panel';

type Spec = App.Data.CalculatorSpecData;
type Resolution = App.Data.CalculatorResolutionData;

// The reusable, embeddable calculator surface. A scalar calculator (Profit) renders a form +
// the seven-segment device screen; a collection calculator (Allocation) renders a portfolio
// editor + a per-row allocation table. Both ground every figure in the same live, cited facts.
// `savable` (the tool page) adds a signed-in reader's saved inputs to a scalar calculator.
export function CalculatorWidget({ spec, resolution, savable = false }: { spec: Spec; resolution: Resolution; savable?: boolean }) {
    const graph = spec.graph as unknown as CalculatorGraph;
    const calc = useCalculator(spec.slug, graph, resolution);

    const collectionNode = graph.nodes.find((node) => node.kind === 'collection');
    if (collectionNode && graph.table) {
        return <CollectionWidget graph={graph} calc={calc} collectionNode={collectionNode} />;
    }

    return <ScalarWidget graph={graph} calc={calc} savedFor={savable ? spec.slug : null} />;
}

function ScalarWidget({ graph, calc, savedFor = null }: { graph: CalculatorGraph; calc: UseCalculator; savedFor?: string | null }) {
    const { inputs, setInput, values, vectors, facts, refreshing } = calc;
    const byId = nodeById(graph);
    const inputNodes = graph.nodes.filter((node) => node.kind === 'input');
    const orderedInputs = [
        ...inputNodes.filter((node) => node.control?.type === 'enum'),
        ...inputNodes.filter((node) => node.control?.type !== 'enum'),
    ];
    const refNodes = graph.nodes.filter((node) => node.kind === 'ref' && !node.over);

    const headlineNode = byId[graph.outputs[0]];
    const statNodes: CalculatorNode[] = [
        ...graph.outputs.slice(1).map((id) => byId[id]),
        ...graph.nodes.filter((node) => node.kind === 'computed' && !graph.outputs.includes(node.id)),
    ].filter(Boolean);

    const headline: Readout = {
        label: headlineNode?.label ?? 'Result',
        value: values[headlineNode?.id],
        format: headlineNode?.format,
    };
    const stats: Readout[] = statNodes.map((node) => ({ label: node.label, value: values[node.id], format: node.format }));

    return (
        <div className="space-y-5">
            <div className="grid gap-5 lg:grid-cols-2">
                <div className="rounded-lg border border-border bg-card p-5 sm:p-6">
                    <p className="mb-4 font-mono text-[11px] tracking-widest text-muted-foreground uppercase">Your position</p>
                    <div className="space-y-4">
                        {orderedInputs.map((node) => (
                            <InputControl key={node.id} node={node} value={inputs[node.id]} onChange={(value) => setInput(node.id, value)} />
                        ))}

                        {refNodes.map((node) => (
                            <RefField key={node.id} node={node} value={values[node.id]} refreshing={refreshing} />
                        ))}
                    </div>
                </div>

                <CalculatorReadout headline={headline} stats={stats} facts={facts} refreshing={refreshing} />
            </div>

            {graph.chart?.type === 'line' && <ResultChart graph={graph} vectors={vectors} />}

            {savedFor !== null && <SavedInputsPanel slug={savedFor} calc={calc} />}
        </div>
    );
}

function CollectionWidget({ graph, calc, collectionNode }: { graph: CalculatorGraph; calc: UseCalculator; collectionNode: CalculatorNode }) {
    const { inputs, setInput, collections, setCell, addRow, removeRow, values, vectors, vectorFacts, refreshing } = calc;
    const byId = nodeById(graph);
    const control = collectionNode.control as CollectionControl;
    const rows = collections[collectionNode.id] ?? [];
    const symbolField = control.fields.find((field) => field.type === 'symbol');
    const toggleField = control.fields.find((field) => field.type === 'toggle');
    const scalarInputs = graph.nodes.filter((node) => node.kind === 'input');
    const table = graph.table!;
    const canRemove = rows.length > (control.minRows ?? 1);

    const summaryNodes = graph.outputs.map((id) => byId[id]).filter(Boolean);
    const factList = Object.values(vectorFacts)[0]?.filter((fact): fact is App.Data.FactData => Boolean(fact)) ?? [];
    const source = factList[0]?.source;

    return (
        <div className="space-y-5">
            <div className="grid gap-5 lg:grid-cols-2">
                <div className="rounded-lg border border-border bg-card p-5 sm:p-6">
                    <p className="mb-4 font-mono text-[11px] tracking-widest text-muted-foreground uppercase">Your portfolio</p>
                    <div className="space-y-4">
                        {scalarInputs.map((node) => (
                            <InputControl key={node.id} node={node} value={inputs[node.id]} onChange={(value) => setInput(node.id, value)} />
                        ))}
                    </div>

                    <div className="mt-6 space-y-2">
                        <FieldLabel>{collectionNode.label}</FieldLabel>
                        {rows.map((row, index) => (
                            <div key={index} className="flex items-center gap-2">
                                {symbolField && (
                                    <Select value={String(row[symbolField.id] ?? '')} onValueChange={(value) => setCell(collectionNode.id, index, symbolField.id, value)}>
                                        <SelectTrigger className="h-9 flex-1 font-mono">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(symbolField.options ?? []).map((option) => (
                                                <SelectItem key={option} value={option} className="font-mono">
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {toggleField && (
                                    <button
                                        type="button"
                                        onClick={() => setCell(collectionNode.id, index, toggleField.id, !row[toggleField.id])}
                                        aria-pressed={Boolean(row[toggleField.id])}
                                        className={cn(
                                            'h-9 rounded-md border px-3 font-mono text-[11px] tracking-wide uppercase transition-colors',
                                            row[toggleField.id] ? 'border-terminal/40 bg-terminal/10 text-terminal' : 'border-border text-muted-foreground',
                                        )}
                                    >
                                        {row[toggleField.id] ? 'On' : 'Off'}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => removeRow(collectionNode.id, index)}
                                    disabled={!canRemove}
                                    aria-label="Remove asset"
                                    className="flex size-9 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:text-foreground disabled:opacity-30"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() => addRow(collectionNode.id)}
                            className="mt-1 inline-flex items-center gap-1.5 font-mono text-xs text-terminal transition-opacity hover:opacity-80"
                        >
                            <Plus className="size-3.5" /> {control.addLabel ?? 'Add row'}
                        </button>
                    </div>
                </div>

                <div className="rounded-lg border border-readout-border bg-readout p-5 text-readout-foreground sm:p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <span className="font-mono text-[11px] tracking-widest text-terminal-dim uppercase">Diversified split</span>
                        {refreshing && <Spinner className="size-4 text-terminal-dim" />}
                    </div>
                    <div className={cn('grid grid-cols-3 gap-x-3 gap-y-4', refreshing && 'opacity-40')}>
                        {summaryNodes.map((node) => (
                            <div key={node.id} className="min-w-0">
                                <div className="truncate font-mono text-lg tabular-nums text-readout-foreground">
                                    {node.id in values ? compactOr(values[node.id], node.format) : '—'}
                                </div>
                                <div className="truncate font-mono text-[10px] tracking-wide text-terminal-dim uppercase">{node.label}</div>
                            </div>
                        ))}
                    </div>
                    {source && (
                        <p className="mt-5 border-t border-readout-border pt-4 font-mono text-[11px] text-terminal-dim">
                            Live market caps · {source}
                        </p>
                    )}
                </div>
            </div>

            <AllocationTable columns={table.columns} rows={rows} vectors={vectors} vectorFacts={vectorFacts} refreshing={refreshing} />
        </div>
    );
}

function AllocationTable({
    columns,
    rows,
    vectors,
    vectorFacts,
    refreshing,
}: {
    columns: TableColumn[];
    rows: Record<string, number | string | boolean>[];
    vectors: UseCalculator['vectors'];
    vectorFacts: UseCalculator['vectorFacts'];
    refreshing: boolean;
}) {
    const shareColumn = columns.find((column) => column.format === 'percent');

    return (
        <div className={cn('overflow-x-auto rounded-lg border border-border bg-card', refreshing && 'opacity-50')}>
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border">
                        {columns.map((column) => (
                            <th key={column.id} className="px-4 py-2.5 text-left font-mono text-[10px] tracking-wide text-muted-foreground uppercase last:text-right">
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => {
                        const share = shareColumn ? Number(vectors[shareColumn.id]?.[index]) : 0;
                        return (
                            <tr key={index} className="border-b border-border/60 last:border-0">
                                {columns.map((column) => (
                                    <Cell key={column.id} column={column} row={row} index={index} vectors={vectors} vectorFacts={vectorFacts} share={share} isShare={column === shareColumn} />
                                ))}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function Cell({
    column,
    row,
    index,
    vectors,
    vectorFacts,
    share,
    isShare,
}: {
    column: TableColumn;
    row: Record<string, number | string | boolean>;
    index: number;
    vectors: UseCalculator['vectors'];
    vectorFacts: UseCalculator['vectorFacts'];
    share: number;
    isShare: boolean;
}) {
    if (column.source === 'field') {
        return <td className="px-4 py-2.5 font-mono font-medium text-foreground">{String(row[column.id] ?? '')}</td>;
    }

    const raw = vectors[column.id]?.[index];
    const value = typeof raw === 'number' ? raw : Number.NaN;
    const fact = vectorFacts[column.id]?.[index];
    const text = column.format === 'currency' ? compactUsd(value) : formatValue(value, column.format);

    if (isShare) {
        return (
            <td className="px-4 py-2.5">
                <div className="flex items-center gap-2">
                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                        <div className="h-full rounded-full bg-terminal" style={{ width: `${Math.max(0, Math.min(100, Number.isFinite(share) ? share : 0))}%` }} />
                    </div>
                    <span className="font-mono tabular-nums text-muted-foreground">{text}</span>
                </div>
            </td>
        );
    }

    return (
        <td className="px-4 py-2.5 font-mono tabular-nums text-foreground last:text-right">
            <span className="inline-flex items-center gap-1.5">
                {fact && <span className="size-1.5 rounded-full bg-terminal" title={`${fact.source} · live`} />}
                {text}
            </span>
        </td>
    );
}

// Big USD figures (market caps) read better abbreviated: $1.20T, $65.0B.
function compactUsd(value: number): string {
    if (!Number.isFinite(value)) {
        return '—';
    }
    const abs = Math.abs(value);
    const [div, suffix] = abs >= 1e12 ? [1e12, 'T'] : abs >= 1e9 ? [1e9, 'B'] : abs >= 1e6 ? [1e6, 'M'] : abs >= 1e3 ? [1e3, 'K'] : [1, ''];
    return `$${(value / div).toFixed(suffix ? 2 : 0)}${suffix}`;
}

function compactOr(value: number | undefined, format: string | undefined): string {
    if (value === undefined || !Number.isFinite(value)) {
        return '—';
    }
    return format === 'currency' ? compactUsd(value) : formatValue(value, format as never);
}

function FieldLabel({ htmlFor, children }: { htmlFor?: string; children: React.ReactNode }) {
    return (
        <Label htmlFor={htmlFor} className="font-mono text-[11px] tracking-widest text-muted-foreground uppercase">
            {children}
        </Label>
    );
}

function InputControl({ node, value, onChange }: { node: CalculatorNode; value: number | string; onChange: (value: number | string) => void }) {
    const control = node.control;
    if (!control || control.type === 'collection') {
        return null;
    }

    if (control.type === 'enum') {
        const enumControl = control as EnumControl;
        return (
            <div className="space-y-1.5">
                <FieldLabel htmlFor={node.id}>{node.label}</FieldLabel>
                <Select value={String(value)} onValueChange={onChange}>
                    <SelectTrigger id={node.id} className="w-full font-mono">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {enumControl.options.map((option) => (
                            <SelectItem key={option} value={option} className="font-mono">
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    const numberControl = control as NumberControl;
    return (
        <div className="space-y-1.5">
            <FieldLabel htmlFor={node.id}>
                {node.label}
                {numberControl.unit && <span className="ml-1.5 lowercase opacity-70">{numberControl.unit}</span>}
            </FieldLabel>
            <input
                id={node.id}
                type="number"
                inputMode="decimal"
                value={Number.isFinite(value as number) ? String(value) : ''}
                min={numberControl.min}
                max={numberControl.max}
                step={numberControl.step === 'any' ? 'any' : numberControl.step}
                onChange={(event) => {
                    const parsed = event.target.value === '' ? Number.NaN : Number(event.target.value);
                    onChange(parsed);
                }}
                className="h-10 w-full rounded-md border border-input bg-background px-3 font-mono text-base text-foreground focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
            />
        </div>
    );
}

// A ref (live) value shown read-only in the form — the "sell price" the market supplies.
function RefField({ node, value, refreshing }: { node: CalculatorNode; value: number | undefined; refreshing: boolean }) {
    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between">
                <FieldLabel>{node.label}</FieldLabel>
                <span className="inline-flex items-center gap-1 font-mono text-[10px] tracking-wide text-terminal uppercase">
                    {refreshing ? <Spinner className="size-3" /> : <span className="size-1.5 rounded-full bg-terminal" />}
                    live
                </span>
            </div>
            <div className={cn('flex h-10 items-center rounded-md border border-border bg-muted/40 px-3 font-mono text-base', refreshing && 'opacity-50')}>
                {value === undefined ? '—' : formatValue(value, node.format)}
            </div>
        </div>
    );
}
