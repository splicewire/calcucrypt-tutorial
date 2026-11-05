<?php

namespace App\Particle\Operations;

use App\Billing\PlanCatalog;
use App\Data\PurchasePlanInputData;
use App\Data\PurchasePlanOutputData;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Rushing\Commerce\Data\Customer;
use Rushing\Commerce\Data\LineItem;
use Rushing\Commerce\Data\Money;
use Rushing\Commerce\Data\Order;
use Rushing\Commerce\Enums\PurchaseKind;
use Rushing\Commerce\MoneyIn;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\Subject\NoSubject;

/**
 * `POST /api/plans/purchase`: buy a plan for the team you are working in.
 *
 * Beam does the plumbing before `handle()` runs:
 *
 *   - `subject: NoSubject` — the op acts on the catalogue, not on one stored record, so there is
 *     no `{id}` in the URL; the plan to buy is in the body;
 *   - `ability: 'plans.purchase'` with no subject is an entitlement check
 *     (`entitlement:plans.purchase`), which `CalcucryptEntitlementResolver` grants to the Owner
 *     or an Admin of the current team, so a Member or a guest gets 403;
 *   - `input:` validates the body, so a plan that is not on sale is a 422.
 *
 * `handle()` places one Order on the money-in rail. Under the default `fake` rail nothing leaves
 * the process and the payment succeeds at once. When it does, `rushing/laravel-commerce` fires
 * `PurchaseCompleted`, and {@see \App\Listeners\ApplyPurchasedPlan} moves the team onto the
 * plan. A declined payment fires nothing, so the team keeps the plan it had.
 */
#[ParticleOp(
    resource: 'plans',
    name: 'purchase',
    kind: OperationKind::Write,
    ability: 'plans.purchase',
    affordance: false,
    input: PurchasePlanInputData::class,
    output: PurchasePlanOutputData::class,
    subject: NoSubject::class,
    abilityModel: false,
)]
class PurchasePlan
{
    public static function handle(mixed $subject, Request $request, User $actor): PurchasePlanOutputData
    {
        $input = PurchasePlanInputData::from($request->all());
        $plan = app(PlanCatalog::class)->find($input->plan);
        $team = $actor->currentTeamOrPersonal();

        if ($plan === null || $team === null) {
            throw new AuthorizationException;
        }

        $order = Order::for(
            customer: new Customer(
                id: (string) $team->getKey(),
                name: (string) $team->getAttribute('name'),
                email: $actor->email,
                type: $team->getMorphClass(),
            ),
            lineItems: [LineItem::for("{$plan->name} plan", Money::of($plan->price, $plan->currency))],
            kind: PurchaseKind::Perpetual,
            reference: "plan:{$plan->slug}",
        );

        // The beneficiary is the team, and the grant says what it bought. The engine never reads
        // either: they ride on the Purchase for the listener to act on.
        $purchase = app(MoneyIn::class)->place(
            $order,
            beneficiaryId: (string) $team->getKey(),
            grant: ['plan' => $plan->slug],
        );

        return new PurchasePlanOutputData(
            plan: $plan->slug,
            paid: $purchase->payment->succeeded(),
            declineCode: $purchase->payment->errorCode,
            teamPlan: (string) $team->refresh()->getAttribute('plan'),
        );
    }
}
