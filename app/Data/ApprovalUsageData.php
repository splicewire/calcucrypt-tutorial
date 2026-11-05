<?php

namespace App\Data;

use App\Authorization\ApprovalUsageReadPolicy;
use App\Backings\ApprovalUsageBacking;
use Schemastud\DataSchemas\Attributes\Title;
use Schemastud\Frame\Attributes\Column;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

#[ParticleResource(
    key: 'service-request-usage', backing: ApprovalUsageBacking::class, data: ApprovalUsageData::class,
    input: false, publicRead: null, readPolicy: ApprovalUsageReadPolicy::class, policy: 'service-desk.usage.read',
    label: 'Approval usage', singularLabel: 'Approval usage', form: 'bare', section: 'service-desk',
    icon: 'ChartNoAxesColumn', navOrder: 30, readOnly: true, editable: false, deletable: false, showable: true,
)]
#[Title('Approval usage')]
class ApprovalUsageData extends BeamData
{
    public function __construct(
        #[Title('ID')]
        public string $id,
        #[Title('Meter'), Column(label: 'Meter', sort: 0)]
        public string $meter,
        #[Title('Quantity'), Column(label: 'Quantity', sort: 1)]
        public int $quantity,
        #[Title('Unit'), Column(label: 'Unit', sort: 2)]
        public string $unit,
        #[Title('Period'), Column(label: 'Period', sort: 3)]
        public string $period,
    ) {}
}
