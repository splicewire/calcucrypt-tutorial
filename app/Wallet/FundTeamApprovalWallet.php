<?php

namespace App\Wallet;

use App\Wallet\Data\WalletTopupInputData;
use App\Wallet\Data\WalletTopupResultData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Rushing\Commerce\Data\Customer;
use Rushing\Commerce\Data\LineItem;
use Rushing\Commerce\Data\Money;
use Rushing\Commerce\Data\Order;
use Rushing\Commerce\Enums\PurchaseKind;
use Rushing\Commerce\MoneyIn;
use Splicewire\Beam\Data\ResponseBody;
use Splicewire\Beam\Particle\ActionAffordance;
use Splicewire\Beam\Particle\Attributes\ParticleOp;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\Subject\NoSubject;

#[ParticleOp(
    resource: 'team-approval-wallet', name: 'top-up', kind: OperationKind::Write,
    ability: 'service-desk.wallet.fund', abilityModel: false,
    input: WalletTopupInputData::class, output: WalletTopupResultData::class, subject: NoSubject::class,
    affordance: new ActionAffordance(label: 'Add sandbox funds'),
)]
class FundTeamApprovalWallet
{
    public static function handle(mixed $model, Request $request, mixed $actor): ResponseBody
    {
        $context = app(TeamWalletContext::class);
        $team = $context->requireTeam($actor, fund: true);
        abort_unless(config('team-wallet.sandbox') && app()->environment(['local', 'testing'])
            && config('commerce.driver') === 'fake', 403, 'Sandbox funding is unavailable.');
        app(TeamApprovalWallet::class)->assertConfiguration();
        if (array_diff(array_keys($request->all()), ['amount_usd', '_token']) !== []) {
            throw ValidationException::withMessages(['amount_usd' => 'Only the USD amount may be supplied.']);
        }
        $input = WalletTopupInputData::validateAndCreate($request);
        $cents = (int) round($input->amountUsd * 100);
        $party = $context->party($team);
        $order = Order::for(new Customer($party), [LineItem::for('Sandbox approval wallet funds', Money::of($cents, 'USD'))],
            kind: PurchaseKind::CreditTopup, currency: 'USD');
        // Configured MoneyIn rail and the registered PurchaseCompleted funding listener are authoritative.
        // An exception/timeout may occur AFTER capture; no automatic transaction/payment retry here.
        $purchase = app(MoneyIn::class)->place($order, beneficiaryId: $party);
        $payment = $purchase->payment;
        $result = new WalletTopupResultData($payment->succeeded(), $payment->status->value, $payment->driver,
            $payment->providerRef ?? $purchase->orderId, $cents, $payment->errorCode,
            app(TeamApprovalWallet::class)->read($actor));

        return new ResponseBody(
            success: $result->captured, statusCode: $result->captured ? 200 : 402,
            message: $result->captured
                ? 'Sandbox funds captured. This is a balance observation, not settlement.'
                : 'Sandbox payment declined; no funds added. A new deliberate attempt is a new purchase.',
            data: $result,
        );
    }
}
