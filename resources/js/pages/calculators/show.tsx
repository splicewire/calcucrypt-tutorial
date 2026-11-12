import { Head, Link } from '@inertiajs/react';
import { CalculatorWidget } from '@/components/calculators/calculator-widget';

export default function CalculatorShow({
    spec,
    resolution,
}: {
    spec: App.Data.CalculatorSpecData;
    resolution: App.Data.CalculatorResolutionData;
}) {
    return (
        <>
            <Head title={spec.name} />
            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <nav className="mb-6 font-mono text-xs text-muted-foreground">
                    <Link href="/" className="hover:text-foreground">
                        The Crypt
                    </Link>
                    <span className="px-1.5">/</span>
                    <Link href="/calculators" className="hover:text-foreground">
                        Calculators
                    </Link>
                    <span className="px-1.5">/</span>
                    <span className="text-foreground">{spec.name}</span>
                </nav>

                <header className="mb-8 max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground">{spec.name}</h1>
                    {spec.summary && <p className="mt-3 text-muted-foreground">{spec.summary}</p>}
                </header>

                <CalculatorWidget spec={spec} resolution={resolution} />

                <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-6">
                    <FiguredOut spec={spec} />
                </div>
            </div>
        </>
    );
}

// "How is it figured?" — the declared expressions behind each computed node, so the math is
// never a black box (it is, after all, the whole "trust the numbers" promise).
function FiguredOut({ spec }: { spec: App.Data.CalculatorSpecData }) {
    const graph = spec.graph as { nodes: Array<{ id: string; label: string; expr?: string }> };
    const formulas = graph.nodes.filter((node) => node.expr);

    if (formulas.length === 0) {
        return <span />;
    }

    return (
        <details className="group text-sm">
            <summary className="cursor-pointer font-mono text-xs tracking-wide text-muted-foreground uppercase hover:text-foreground">
                How is this figured?
            </summary>
            <dl className="mt-3 space-y-1.5">
                {formulas.map((node) => (
                    <div key={node.id} className="flex gap-2 font-mono text-xs">
                        <dt className="text-muted-foreground">{node.label}</dt>
                        <dd className="text-foreground">= {node.expr}</dd>
                    </div>
                ))}
            </dl>
        </details>
    );
}
