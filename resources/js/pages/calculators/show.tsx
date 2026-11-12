import { Head, Link } from '@inertiajs/react';
import { GuideCard } from '@/components/crypt/cards';
import { CalculatorWidget } from '@/components/calculators/calculator-widget';

type GuideSummary = App.Data.GuideSummaryData;

export default function CalculatorShow({
    spec,
    resolution,
    guides = [],
    primaryGuide = null,
}: {
    spec: App.Data.CalculatorSpecData;
    resolution: App.Data.CalculatorResolutionData;
    guides?: GuideSummary[];
    primaryGuide?: GuideSummary | null;
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
                    {primaryGuide && (
                        <Link
                            href={`/guides/${primaryGuide.slug}`}
                            className="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
                        >
                            Read the guide →
                        </Link>
                    )}
                </div>

                <LearnMore guides={guides} primaryGuide={primaryGuide} />
            </div>
        </>
    );
}

// Back-findability: the Guides that explain this calculator, its canonical explainer first.
function LearnMore({ guides, primaryGuide }: { guides: GuideSummary[]; primaryGuide: GuideSummary | null }) {
    if (guides.length === 0) {
        return null;
    }

    const primarySlug = primaryGuide?.slug;
    const ordered = [...guides].sort((a, b) => (a.slug === primarySlug ? -1 : b.slug === primarySlug ? 1 : 0));

    return (
        <section className="mt-12 border-t border-border pt-8">
            <h2 className="text-xl font-semibold text-foreground">Learn more</h2>
            <p className="mt-1 mb-5 font-mono text-xs text-muted-foreground">Guides that explain this calculator</p>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {ordered.map((guide) => (
                    <div key={guide.slug} className="relative">
                        {guide.slug === primarySlug && (
                            <span className="absolute top-2 right-2 z-10 rounded-sm border border-terminal bg-background px-1.5 py-px font-mono text-[9px] tracking-wide text-terminal uppercase">
                                Primary
                            </span>
                        )}
                        <GuideCard guide={guide} />
                    </div>
                ))}
            </div>
        </section>
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
