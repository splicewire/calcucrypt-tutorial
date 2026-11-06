<?php

namespace App\Policies;

use App\Models\Calculator;
use App\Models\User;

/**
 * Who may read a Calculator. Today everyone may: the `calculators` resource's scope already
 * keeps drafts out, and a guest reads through its declared Public Read without asking here.
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
}
