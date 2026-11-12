<?php

namespace App\Policies;

use App\Models\Calculator;
use App\Models\User;

/**
 * Who may use a Calculator: resolve its live refs. Reading needs no policy: the `calculators`
 * resource's scope keeps drafts out, and every reader reads through its declared Public Read.
 */
class CalculatorPolicy
{
    /**
     * Today, everyone who may read it.
     */
    public function resolve(?User $user, Calculator $calculator): bool
    {
        return true;
    }
}
