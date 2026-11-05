<?php

namespace App\Wallet;

use Rushing\Commerce\Contracts\UsageMeter;

/** Optional Commerce contract bridge; all spend and scope rules live in the core meter. */
final class CommerceUsageMeter implements UsageMeter
{
    public function __construct(private ApprovalWalletMeter $meter) {}

    public function debitedFor(string $partyId, string $unit): float
    {
        return $this->meter->debitedFor($partyId, $unit);
    }
}
