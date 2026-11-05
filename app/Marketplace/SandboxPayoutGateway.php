<?php

namespace App\Marketplace;

use Splicewire\Beam\Market\Models\Seller;
use Splicewire\Beam\Market\Payout\Contracts\PayoutGatewayResolver;
use Splicewire\Beam\Market\Payout\Data\PayoutAccount;

/** Local teaching port: real onboarding calls this, but no external payout account is created. */
final class SandboxPayoutGateway implements PayoutGatewayResolver
{
    public function gatewayKey(): string
    {
        return 'local-sandbox';
    }

    public function provision(Seller $seller): PayoutAccount
    {
        return new PayoutAccount($this->gatewayKey(), 'sandbox-'.$seller->getKey());
    }

    public function enableTaxReporting(Seller $seller): void {}
}
