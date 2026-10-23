<?php

namespace App\Wallet\Data;

use App\Wallet\TeamApprovalWalletBacking;
use App\Wallet\TeamApprovalWalletReadPolicy;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Schemastud\Frame\Attributes\Column;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

#[ParticleResource(
    key: 'team-approval-wallet', backing: TeamApprovalWalletBacking::class, data: TeamApprovalWalletData::class,
    input: false, publicRead: null, readPolicy: TeamApprovalWalletReadPolicy::class, policy: 'service-desk.wallet.read',
    label: 'Approval wallet', singularLabel: 'Approval wallet', form: 'bare', section: 'service-desk',
    icon: 'Wallet', navOrder: 35, readOnly: true, editable: false, deletable: false, showable: true,
)]
#[Title('Approval wallet')]
#[Description('Lifetime balance observation. Sandbox funds; teaching tariff $0.25 per committed approval. Approvals remain available at zero or negative balance.')]
class TeamApprovalWalletData extends BeamData
{
    public function __construct(
        public string $id,
        #[Title('Team'), Column(label: 'Team', sort: 0)]
        public string $team,
        #[Title('Credited USD'), Column(label: 'Credited USD', sort: 1)]
        public string $creditedUsd,
        #[Title('Approvals'), Column(label: 'Approvals', sort: 2)]
        public int $approvalCount,
        #[Title('Attributed usage USD'), Column(label: 'Attributed usage USD', sort: 3)]
        public string $usageUsd,
        #[Title('Balance USD'), Column(label: 'Balance USD', sort: 4)]
        public string $balanceUsd,
        #[Title('Terms'), Column(label: 'Terms', sort: 5)]
        public string $terms = 'Lifetime · $0.25 / approval · sandbox balance observation; approvals remain available.',
    ) {}
}
