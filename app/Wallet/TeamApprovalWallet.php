<?php

namespace App\Wallet;

use App\Support\CommittedApprovals;
use App\Wallet\Data\TeamApprovalWalletData;
use Illuminate\Support\Facades\DB;
use LogicException;
use Rushing\Commerce\Models\CreditEntry;
use Rushing\Commerce\Wallets;
use Splicewire\Beam\Accounts\Models\Team;

final class TeamApprovalWallet
{
    public function __construct(private TeamWalletContext $context, private CommittedApprovals $facts, private Wallets $wallets) {}

    public function read(mixed $actor): TeamApprovalWalletData
    {
        return $this->forTeam($this->context->requireTeam($actor));
    }

    private function forTeam(Team $team): TeamApprovalWalletData
    {
        $this->assertConfiguration();
        $party = $this->context->party($team);
        $meter = new ApprovalWalletMeter($party, (string) $team->getKey(), $this->facts);
        $assessment = $this->wallets->budgetSource($party, 'usd', new CommerceUsageMeter($meter))->assess();
        $credited = (int) round($assessment->cap * 100);
        $usage = (int) round($assessment->spend * 100);

        return new TeamApprovalWalletData('approvals', (string) $team->getAttribute('name'),
            self::usd($credited), intdiv($usage, 25), self::usd($usage), self::usd($credited - $usage));
    }

    public function assertConfiguration(): void
    {
        if (config('commerce.currency') !== 'USD' || config('commerce.credit.unit') !== 'usd') {
            throw new LogicException('The teaching wallet requires USD currency and usd credits.');
        }
        // This example reads facts and credits in one host connection, not central/tenant fallback.
        if ((new CreditEntry)->getConnection()->getName() !== DB::connection()->getName()) {
            throw new LogicException('The approval wallet requires the host fact and credit connection.');
        }
    }

    private static function usd(int $cents): string
    {
        return ($cents < 0 ? '-$' : '$').number_format(abs($cents) / 100, 2, '.', '');
    }
}
