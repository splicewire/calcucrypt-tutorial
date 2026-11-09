<?php

namespace App\Policies;

use App\Models\Calculator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who may use a Calculator: resolve its live refs. Reading needs no policy: the `calculators`
 * resource's scope keeps drafts out, and every reader reads through its declared Public Read.
 * What a reader may not use is locked in the data itself (CalculatorSpecData::project()).
 */
class CalculatorPolicy
{
    /**
     * Who may use a Calculator: resolve its live refs, and see its graph. A free calculator
     * (no entitlement) is open to everyone, guests included. A paid one needs its entitlement,
     * which the user's current team holds through its plan.
     */
    public function resolve(?User $user, Calculator $calculator): bool
    {
        if ($calculator->entitlement === null) {
            return true;
        }

        return Gate::forUser($user)->allows('entitlement:'.$calculator->entitlement);
    }
}
