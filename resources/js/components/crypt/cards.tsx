import { Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { GuideGlyph } from '@/components/crypt/guide-glyph';

type CalculatorSummary = App.Data.CalculatorSummaryData;
type GuideSummary = App.Data.GuideSummaryData;

function Tag({ children }: { children: React.ReactNode }) {
    return <p className="font-mono text-[10px] tracking-widest text-terminal-dim uppercase">{children}</p>;
}

/** A plan's display name: `trader` → `Trader`. */
export function planName(plan: string | null): string {
    return plan ? plan.charAt(0).toUpperCase() + plan.slice(1) : 'a paid';
}

// The server decides what is locked (`calculator.locked`); the badge only says so.
export function LockBadge({ plan }: { plan: string | null }) {
    return (
        <span
            data-testid="lock-badge"
            className="inline-flex items-center gap-1 rounded-sm border border-border bg-muted px-1.5 py-px font-mono text-[10px] tracking-wide text-muted-foreground uppercase"
        >
            <Lock className="size-3" aria-hidden />
            {planName(plan)}
        </span>
    );
}

export function CalculatorCard({ calculator }: { calculator: CalculatorSummary }) {
    return (
        <Link
            href={`/calculators/${calculator.slug}`}
            className="block rounded-lg border border-border bg-card p-4 transition-colors hover:border-terminal"
        >
            <div className="flex items-start justify-between gap-2">
                {calculator.category ? <Tag>{calculator.category}</Tag> : <span />}
                {calculator.locked && <LockBadge plan={calculator.requiredPlan} />}
            </div>
            <h3 className="mt-1 text-base font-semibold text-foreground">{calculator.name}</h3>
            <p className="mt-1.5 text-sm text-muted-foreground">{calculator.summary}</p>
        </Link>
    );
}

export function GuideCard({ guide }: { guide: GuideSummary }) {
    return (
        <Link
            href={`/guides/${guide.slug}`}
            className="group block overflow-hidden rounded-lg border border-border bg-card transition-colors hover:border-terminal"
        >
            <GuideGlyph slug={guide.slug} category={guide.category} />
            <div className="p-4">
                {guide.category && <Tag>{guide.category}</Tag>}
                <h3 className="mt-1 text-base font-semibold text-foreground">{guide.title}</h3>
                <p className="mt-1.5 text-sm text-muted-foreground">{guide.teaser}</p>
            </div>
        </Link>
    );
}

export function CardGrid({ children }: { children: React.ReactNode }) {
    return <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</div>;
}

export function EmptyState({ children }: { children: React.ReactNode }) {
    return <p className="rounded-lg border border-dashed border-border p-10 text-center text-muted-foreground">{children}</p>;
}
