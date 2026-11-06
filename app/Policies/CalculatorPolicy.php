<?php

namespace App\Policies;

use App\Models\Calculator;
use App\Models\User;

/**
 * Who may read a Calculator, and who may use one. Everyone may read: the `calculators` resource's
 * scope already keeps drafts out, and a guest reads through its declared Public Read.
 */
class CalculatorPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Calculator $calculator): bool
    {
        return true;
    }

    /**
     * Who may use a Calculator: resolve its live refs. Today, everyone who may read it.
     */
    public function resolve(?User $user, Calculator $calculator): bool
    {
        return true;
    }
}
