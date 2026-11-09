import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    useApiPlansIndex,
    useApiPlansPurchase,
} from '@/generated/hooks/api-plans';
import { cn } from '@/lib/utils';

type Plan = App.Data.PlanOfferData;

function price(plan: Plan): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: plan.currency,
    }).format(plan.price / 100);
}

// The plans that would unlock a locked calculator, cheapest first, with a buy button for each.
//
// It reads the can-map the server shares with every page:
// - `can['plans.purchase']` says whether this reader may buy for their team (its Owner or an Admin);
// - a plan whose every key is already allowed is the plan the team has.
//
// A paid purchase reloads the page's props, so the calculator comes back unlocked from the server.
export function PlanPicker({
    requiredPlan,
    compact = false,
}: {
    requiredPlan: string | null;
    compact?: boolean;
}) {
    // The flat ability map HandleInertiaRequests shares (Beam's CanMapBuilder).
    const { can } = usePage<{ can: Record<string, boolean> }>().props;
    const plans = useApiPlansIndex();
    const purchase = useApiPlansPurchase({
        onSuccess: (result) => {
            if (result.paid) {
                router.reload();
            }
        },
    });

    const all = plans.data ?? [];
    const required = all.find((plan) => plan.slug === requiredPlan);
    const choices = all
        .filter((plan) =>
            (required?.unlocks ?? []).every((key) =>
                plan.unlocks.includes(key),
            ),
        )
        .sort((a, b) => a.price - b.price);
    const mayBuy = can['plans.purchase'] === true;
    const held = (plan: Plan) => plan.unlocks.every((key) => can[key]);

    if (choices.length === 0) {
        return null;
    }

    return (
        <div
            data-testid="plan-picker"
            className={cn(
                'flex w-full max-w-md flex-col gap-2',
                !compact && 'mt-2',
            )}
        >
            {choices.map((plan) => (
                <div
                    key={plan.slug}
                    className={cn(
                        'flex items-center gap-3 rounded-md border px-4 py-3 text-left',
                        plan.slug === requiredPlan
                            ? 'border-primary'
                            : 'border-border',
                    )}
                >
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-foreground">
                            {plan.name}{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {price(plan)}
                            </span>
                        </p>
                        {!compact && (
                            <p className="text-xs text-muted-foreground">
                                {plan.summary}
                            </p>
                        )}
                    </div>
                    {held(plan) ? (
                        <span className="text-xs text-muted-foreground">
                            Your plan
                        </span>
                    ) : (
                        mayBuy && (
                            <Button
                                size="sm"
                                disabled={purchase.isPending}
                                onClick={() =>
                                    purchase.mutate({
                                        body: { plan: plan.slug },
                                    })
                                }
                            >
                                Buy {plan.name}
                            </Button>
                        )
                    )}
                </div>
            ))}

            {!mayBuy && (
                <p className="text-xs text-muted-foreground">
                    Ask your team's owner or an admin to upgrade.
                </p>
            )}
            {purchase.data && !purchase.data.paid && (
                <p role="alert" className="text-xs text-destructive">
                    The payment was declined ({purchase.data.declineCode}). Your
                    team is still on its current plan.
                </p>
            )}
            {purchase.isError && (
                <p role="alert" className="text-xs text-destructive">
                    The purchase didn't go through. Try again.
                </p>
            )}
        </div>
    );
}
