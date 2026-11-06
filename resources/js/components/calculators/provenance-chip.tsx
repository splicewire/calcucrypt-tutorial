import { formatAsOf, formatValue } from '@/calculators/format';
import { cn } from '@/lib/utils';

// "Cite every number." A compact pill carrying a live figure's symbol, price, freshness, and
// source — "BTC $64,250 · live · CoinMarketCap". It degrades to a stale variant (amber, "as
// of HH:MM") rather than ever going blank. `onDark` tints it for the readout device screen.
export function ProvenanceChip({
    fact,
    onDark = false,
    className,
}: {
    fact: App.Data.FactData;
    onDark?: boolean;
    className?: string;
}) {
    const stale = fact.stale;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 font-mono text-[11px]',
                onDark ? 'text-terminal-dim' : stale ? 'text-amber-600 dark:text-amber-500' : 'text-muted-foreground',
                className,
            )}
            title={`${fact.source} · ${fact.symbol} · as of ${formatAsOf(fact.asOf)}`}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    stale ? 'bg-amber-500' : onDark ? 'bg-terminal' : 'bg-terminal',
                )}
            />
            <span className={onDark ? 'text-readout-foreground' : 'text-foreground'}>{fact.symbol}</span>
            <span className={onDark ? 'text-readout-foreground' : 'text-foreground'}>{formatValue(fact.value, 'currency')}</span>
            <span className="opacity-50">·</span>
            <span>{stale ? `as of ${formatAsOf(fact.asOf)}` : 'live'}</span>
            <span className="opacity-50">·</span>
            <span>{fact.source}</span>
        </span>
    );
}
