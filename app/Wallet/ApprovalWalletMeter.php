<?php

namespace App\Wallet;

use App\Support\CommittedApprovals;
use InvalidArgumentException;

/** Concrete per-read meter. Never bound over the application's global UsageMeter. */
final class ApprovalWalletMeter
{
    public function __construct(private string $party, private string $teamId, private CommittedApprovals $facts) {}

    public function debitedFor(string $partyId, string $unit): float
    {
        if ($partyId !== $this->party || $unit !== 'usd') {
            throw new InvalidArgumentException('Unsupported approval wallet party or unit.');
        }

        return ($this->facts->forTeam($this->teamId) * 25) / 100;
    }
}
