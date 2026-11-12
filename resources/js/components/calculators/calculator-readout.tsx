import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { formatValue } from '@/calculators/format';
import type { NodeFormat } from '@/calculators/types';
import { ProvenanceChip } from './provenance-chip';

export interface Readout {
    label: string;
    value: number | undefined;
    format?: NodeFormat;
}

// A 14-segment LCD figure with the classic dim "ghost" segments behind it (the digital font
// renders "8" as every segment lit). The headline number of a calculator's device screen.
function SevenSegment({ value, negative }: { value: string; negative: boolean }) {
    const ghost = '8'.repeat(Math.max(value.replace('!', '').length, 1));

    return (
        <div className="relative inline-block leading-none">
            <span aria-hidden className="font-digital text-terminal-dim/25 select-none" style={{ letterSpacing: '0.04em' }}>
                {ghost}
            </span>
            <span
                className={cn('absolute inset-0 font-digital', negative ? 'text-destructive' : 'text-readout-foreground')}
                style={{
                    letterSpacing: '0.04em',
                    textShadow: negative
                        ? '0 0 16px oklch(0.6 0.2 25 / 0.45)'
                        : '0 0 16px oklch(0.86 0.2 155 / 0.5)',
                }}
            >
                {value}
            </span>
        </div>
    );
}

/**
 * The calculator "device screen" — a near-black panel with a giant seven-segment headline,
 * a strip of stat readouts, and the live provenance footer. This IS the calcucrypt brand:
 * every result reads like a physical calculator display.
 */
export function CalculatorReadout({
    headline,
    stats,
    facts,
    refreshing,
}: {
    headline: Readout;
    stats: Readout[];
    facts: Record<string, App.Data.FactData>;
    refreshing: boolean;
}) {
    const headlineValue = headline.value;
    const negative = typeof headlineValue === 'number' && headlineValue < 0;
    // Seven-segment shows sign + integer digits only (no $ / commas), e.g. "+11105".
    const digits =
        headlineValue === undefined || !Number.isFinite(headlineValue)
            ? '----'
            : (headlineValue >= 0 ? '+' : '-') + Math.abs(Math.round(headlineValue)).toString();

    const factList = Object.values(facts);

    return (
        <div className="rounded-lg border border-readout-border bg-readout p-5 text-readout-foreground sm:p-6">
            <div className="mb-1 flex items-center justify-between">
                <span className="font-mono text-[11px] tracking-widest text-terminal-dim uppercase">{headline.label}</span>
                {refreshing && <Spinner className="size-4 text-terminal-dim" />}
            </div>

            <div className={cn('overflow-x-auto py-2 text-4xl sm:text-5xl', refreshing && 'opacity-40')}>
                <SevenSegment value={digits} negative={negative} />
            </div>

            <div className={cn('font-mono text-sm text-terminal', refreshing && 'opacity-40')}>
                {headlineValue === undefined ? '—' : formatValue(headlineValue, headline.format)}
            </div>

            <div className={cn('mt-5 grid grid-cols-3 gap-x-3 gap-y-4 border-t border-readout-border pt-4', refreshing && 'opacity-40')}>
                {stats.map((stat) => (
                    <div key={stat.label} className="min-w-0">
                        <div className="truncate font-mono text-sm tabular-nums text-readout-foreground/90">
                            {stat.value === undefined ? '—' : formatValue(stat.value, stat.format)}
                        </div>
                        <div className="truncate font-mono text-[10px] tracking-wide text-terminal-dim uppercase">{stat.label}</div>
                    </div>
                ))}
            </div>

            {factList.length > 0 && (
                <div className="mt-5 flex flex-wrap gap-2 border-t border-readout-border pt-4">
                    {factList.map((fact) => (
                        <ProvenanceChip key={fact.symbol} fact={fact} onDark />
                    ))}
                </div>
            )}
        </div>
    );
}
