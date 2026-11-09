import { Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { planName } from '@/components/crypt/cards';
import { cn } from '@/lib/utils';

type Spec = App.Data.CalculatorSpecData;

// What a reader sees in place of a calculator their plan does not cover. The server already left
// the graph and the live prices out (`spec.locked`), so there is nothing here to hide: this only
// says which plan unlocks it, and asks a guest to sign in first.
export function LockedCalculator({
    spec,
    compact = false,
}: {
    spec: Spec;
    compact?: boolean;
}) {
    const { auth } = usePage().props;
    const plan = planName(spec.requiredPlan);

    return (
        <div
            data-testid="locked-calculator"
            className={cn(
                'flex flex-col items-center rounded-lg border border-dashed border-border bg-card text-center',
                compact ? 'gap-2 p-6' : 'gap-3 px-6 py-14',
            )}
        >
            <span className="flex size-10 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground">
                <Lock className="size-4" aria-hidden />
            </span>
            <h2
                className={cn(
                    'font-semibold text-foreground',
                    compact ? 'text-base' : 'text-lg',
                )}
            >
                {spec.name} is part of the {plan} plan
            </h2>

            {auth?.user ? (
                <p className="max-w-md text-sm text-muted-foreground">
                    Your team's current plan doesn't include it. Upgrade your
                    team to {plan} to use it.
                </p>
            ) : (
                <>
                    <p className="max-w-md text-sm text-muted-foreground">
                        Sign in with a {plan} team to use it.
                    </p>
                    <Link
                        href="/login"
                        className="mt-1 inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
                    >
                        Sign in
                    </Link>
                </>
            )}

            {!compact && (
                <Link
                    href="/calculators"
                    className="mt-2 font-mono text-xs text-muted-foreground hover:text-foreground"
                >
                    Browse the free calculators →
                </Link>
            )}
        </div>
    );
}
