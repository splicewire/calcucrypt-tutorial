<?php

namespace App\Policies;

use App\Models\Calculator;
use App\Models\User;

/**
 * Who may read a Calculator, and who may resolve one. Today everyone may read: the
 * `calculators` resource's scope already keeps drafts out, and a guest reads through its
 * declared Public Read without asking here.
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
     * Who may ground a Calculator's live refs through the `resolve` operation. The `?User`
     * lets a guest through to this check. Today every published calculator resolves for
     * anyone; paid plans narrow it later.
     */
    public function resolve(?User $user, Calculator $calculator): bool
    {
        return $calculator->isPublished();
    }
}
