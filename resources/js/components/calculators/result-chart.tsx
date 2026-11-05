import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatValue } from '@/calculators/format';
import type { Vectors } from '@/calculators/graph';
import type { CalculatorGraph } from '@/calculators/types';

// Draws a calculator's `chart` spec. Today it renders a `line` chart over a fold's series
// vector (the growth curve of a compounding balance) — one point per period. Other chart
// types can slot in beside it as the vocabulary grows.
export function ResultChart({ graph, vectors }: { graph: CalculatorGraph; vectors: Vectors }) {
    const chart = graph.chart;
    if (chart?.type !== 'line' || !chart.valueNode) {
        return null;
    }

    const series = vectors[chart.valueNode] ?? [];
    const data = series.map((value, index) => ({ period: index + 1, value: typeof value === 'number' && Number.isFinite(value) ? value : null }));
    if (data.length === 0) {
        return null;
    }

    const valueLabel = chart.series?.[0]?.label ?? 'Value';

    return (
        <div className="rounded-lg border border-border bg-card p-5 sm:p-6">
            {chart.title && <p className="mb-4 font-mono text-[11px] tracking-widest text-muted-foreground uppercase">{chart.title}</p>}
            <ResponsiveContainer width="100%" height={220}>
                <LineChart data={data} margin={{ top: 4, right: 8, bottom: 0, left: 8 }}>
                    <CartesianGrid stroke="var(--color-border)" strokeDasharray="2 4" vertical={false} />
                    <XAxis
                        dataKey="period"
                        tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)', fontFamily: 'var(--font-mono)' }}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--color-border)' }}
                    />
                    <YAxis
                        width={56}
                        tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)', fontFamily: 'var(--font-mono)' }}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(value: number) => formatCompact(value)}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--color-terminal)', strokeWidth: 1, strokeOpacity: 0.4 }}
                        contentStyle={{
                            background: 'var(--color-readout)',
                            border: '1px solid var(--color-readout-border)',
                            borderRadius: 8,
                            fontFamily: 'var(--font-mono)',
                            fontSize: 12,
                            color: 'var(--color-readout-foreground)',
                        }}
                        labelFormatter={(period) => `Period ${period}`}
                        formatter={(value: number) => [formatValue(value, graph.nodes.find((node) => node.id === chart.valueNode)?.format), valueLabel]}
                    />
                    <Line type="monotone" dataKey="value" stroke="var(--color-terminal)" strokeWidth={2} dot={false} isAnimationActive connectNulls />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}

// Compact axis ticks so large balances stay legible: 1.2M, 65K.
function formatCompact(value: number): string {
    if (!Number.isFinite(value)) {
        return '';
    }
    const abs = Math.abs(value);
    const [div, suffix] = abs >= 1e9 ? [1e9, 'B'] : abs >= 1e6 ? [1e6, 'M'] : abs >= 1e3 ? [1e3, 'K'] : [1, ''];
    return `${(value / div).toFixed(suffix ? 1 : 0)}${suffix}`;
}
