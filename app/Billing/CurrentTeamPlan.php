<?php

namespace App\Billing;

use App\Models\User;
use Splicewire\Beam\Accounts\Entitlements\PlanSource;

/**
 * Where a reader's plan lives: on the team they are working in (`beam_teams.plan`). Every member gets it, whatever
 * their role, because the team pays for the plan. Only the *current* team counts: belonging to a pro team does not
 * unlock pro while you are working in a free one.
 *
 * Named in `config/beam/accounts.php` (`entitlements.plan`). beam-accounts' module resolver does the rest: it unions
 * the plan's bundle with Beam's default grants. A guest has no plan.
 */
class CurrentTeamPlan implements PlanSource
{
    public function planFor(mixed $principal): ?string
    {
        if (! $principal instanceof User) {
            return null;
        }

        $plan = $principal->currentTeamOrPersonal()?->getAttribute('plan');

        return is_string($plan) && $plan !== '' ? $plan : null;
    }
}
