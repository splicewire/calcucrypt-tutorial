<?php

namespace App\Wallet\Data;

use Splicewire\Beam\Data\BeamData;

class WalletTopupResultData extends BeamData
{
    public function __construct(
        public bool $captured,
        public string $status,
        public string $driver,
        public string $reference,
        public int $amountCents,
        public ?string $declineCode,
        public TeamApprovalWalletData $wallet,
    ) {}
}
