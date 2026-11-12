<?php

namespace App\Billing;

use App\Models\User;
use Splicewire\Beam\Accounts\Entitlements\GrantSource;
use Splicewire\Beam\Accounts\Enums\Role;

/**
 * Who may buy a plan for the current team: its Owner or an Admin hold `plans.purchase`, the ability the
 * `plans.purchase` operation checks. A Member does not: they use the team's plan but cannot change it.
 *
 * A grant for a reason other than the plan, so it is a grant source, listed in `config/beam/accounts.php`
 * (`entitlements.grants`). Its key is declared up front in keys(), so Beam defines its ability before anyone asks.
 */
class PlanPurchasers implements GrantSource
{
    public const PURCHASE_PLANS = 'plans.purchase';

    public static function keys(): array
    {
        return [self::PURCHASE_PLANS];
    }

    public function grantsFor(mixed $principal): array
    {
        if (! $principal instanceof User) {
            return [];
        }

        $team = $principal->currentTeamOrPersonal();
        $role = $team === null ? null : Role::tryFrom((string) $principal->teamRole($team));

        return $role?->grantEligible() ? [self::PURCHASE_PLANS] : [];
    }
}
