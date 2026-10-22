<?php

namespace App\Data;

use App\Backings\ExternalRequestBacking;
use Schemastud\DataSchemas\Attributes\Title;
use Schemastud\Frame\Attributes\Column;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

#[ParticleResource(
    key: 'external-requests', backing: ExternalRequestBacking::class, data: ExternalRequestData::class,
    input: false, publicRead: null, readPolicy: \App\Authorization\ExternalRequestReadPolicy::class, policy: 'service-desk.external.read', label: 'External requests',
    singularLabel: 'External request', form: 'bare', section: 'service-desk', icon: 'ExternalLink',
    navOrder: 20, readOnly: true, editable: false, deletable: false, showable: true,
)]
#[Title('External request')]
class ExternalRequestData extends BeamData
{
    public function __construct(
        #[Title('ID')]
        public string $id,
        #[Title('Source'), Column(label: 'Source', sort: 0)]
        public string $source,
        #[Title('External ID'), Column(label: 'External ID', sort: 1)]
        public string $externalId,
        #[Column(label: 'Title', sort: 2)]
        public string $title,
        #[Column('badge', label: 'Status', sort: 3)]
        public string $status,
        #[Title('Updated'), Column(label: 'Updated', sort: 4)]
        public string $updatedAt,
    ) {}
}
