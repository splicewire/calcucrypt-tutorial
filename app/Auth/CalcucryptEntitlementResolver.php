<?php

namespace App\Auth;

use App\Models\User;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Entitlements\DefaultEntitlementResolver;
use Splicewire\Beam\Accounts\Entitlements\EntitlementComposer;
use Splicewire\Beam\Accounts\Enums\Role;

/**
 * The entitlement keys a user holds: their current team's plan, plus Beam's default grants.
 *
 * - **The plan.** `beam_teams.plan` names a bundle in `config/beam/accounts.php`
 *   (`entitlements.bundles`). Every member gets it, whatever their role, because the team pays for
 *   the plan. Only the *current* team counts: belonging to a pro team does not unlock pro while you
 *   are working in a free one.
 * - **Buying a plan.** The Owner or an Admin of the current team holds `plans.purchase`, the
 *   ability the `plans.purchase` operation checks. A Member does not: they use the team's plan but
 *   cannot change it.
 * - **The default grants.** {@see DefaultEntitlementResolver} still supplies the `ux.*` and `os.*`
 *   keys an Owner or Admin earns from their team's realm grants, so binding this resolver changes
 *   nothing about who can operate the site.
 *
 * Bound in `config/permission-cascade.php`. A guest holds nothing.
 */
class CalcucryptEntitlementResolver implements EntitlementResolver
{
    /** Who may buy a plan for the current team. */
    public const PURCHASE_PLANS = 'plans.purchase';

    public function __construct(
        private EntitlementComposer $composer,
        private DefaultEntitlementResolver $defaults,
    ) {}

    /**
     * @return list<string>
     */
    public function entitlementsFor(mixed $principal): array
    {
        if (! $principal instanceof User) {
            return [];
        }

        return $this->composer->composeForBundles(
            $this->planFor($principal),
            grants: [
                ...array_values($this->defaults->entitlementsFor($principal)),
                ...($this->mayBuyFor($principal) ? [self::PURCHASE_PLANS] : []),
            ],
        );
    }

    /** Whether the user is the Owner or an Admin of the team they are working in. */
    protected function mayBuyFor(User $user): bool
    {
        $team = $user->currentTeamOrPersonal();
        $role = $team === null ? null : Role::tryFrom((string) $user->teamRole($team));

        return $role?->grantEligible() ?? false;
    }

    /** The plan of the team the user is working in: `free`, `trader` or `pro`. */
    protected function planFor(User $user): string
    {
        $plan = $user->currentTeamOrPersonal()?->getAttribute('plan');

        return is_string($plan) && $plan !== '' ? $plan : 'free';
    }
}
