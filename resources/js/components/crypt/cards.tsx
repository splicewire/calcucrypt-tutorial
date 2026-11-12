import { Link } from '@inertiajs/react';

type CalculatorSummary = App.Data.CalculatorSummaryData;

function Tag({ children }: { children: React.ReactNode }) {
    return <p className="font-mono text-[10px] tracking-widest text-terminal-dim uppercase">{children}</p>;
}

export function CalculatorCard({ calculator }: { calculator: CalculatorSummary }) {
    return (
        <Link
            href={`/calculators/${calculator.slug}`}
            className="block rounded-lg border border-border bg-card p-4 transition-colors hover:border-terminal"
        >
            {calculator.category && <Tag>{calculator.category}</Tag>}
            <h3 className="mt-1 text-base font-semibold text-foreground">{calculator.name}</h3>
            <p className="mt-1.5 text-sm text-muted-foreground">{calculator.summary}</p>
        </Link>
    );
}

export function CardGrid({ children }: { children: React.ReactNode }) {
    return <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</div>;
}

export function EmptyState({ children }: { children: React.ReactNode }) {
    return <p className="rounded-lg border border-dashed border-border p-10 text-center text-muted-foreground">{children}</p>;
}
