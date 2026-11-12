<?php

namespace App\Listeners;

use Rushing\Commerce\Events\PurchaseCompleted;
use Splicewire\Beam\Accounts\Models\Team;

/**
 * Moves a team onto the plan it paid for.
 *
 * `rushing/laravel-commerce` fires `PurchaseCompleted` only once a payment has gone through, and it
 * never knows what a purchase grants. Calcucrypt decides that here: a purchase whose grant names a
 * plan sets `beam_teams.plan` on the beneficiary team. The next request reads the new plan through
 * `App\Billing\CurrentTeamPlan`, so the plan's calculators unlock with nothing to refresh.
 *
 * Laravel finds this listener by its type-hinted event; there is no registration to write.
 */
class ApplyPurchasedPlan
{
    public function handle(PurchaseCompleted $event): void
    {
        $plan = $event->purchase->grant['plan'] ?? null;
        $teamId = $event->purchase->beneficiaryId;

        if (! is_string($plan) || ! array_key_exists($plan, (array) config('plans', [])) || $teamId === null) {
            return;
        }

        Team::query()->find($teamId)?->forceFill(['plan' => $plan])->save();
    }
}
