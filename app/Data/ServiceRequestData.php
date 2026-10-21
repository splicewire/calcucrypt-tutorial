<?php

namespace App\Data;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ServiceRequestTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Schemastud\Frame\Attributes\Column;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

#[ParticleResource(
    key: 'service-requests',
    backing: ServiceRequest::class,
    data: ServiceRequestData::class,
    input: ServiceRequestInputData::class,
    publicRead: null,
    label: 'Service requests',
    singularLabel: 'Service request',
    form: 'bare',
    editData: ServiceRequestInputData::class,
    section: 'service-desk',
    icon: 'Inbox',
    navOrder: 10,
    editable: false,
    deletable: false,
    showable: true,
)]
class ServiceRequestData extends BeamData
{
    public function __construct(
        public int $id,
        #[Column(label: 'Title', sort: 0)]
        public string $title,
        #[Column('badge', label: 'Status', sort: 1)]
        public string $status,
    ) {}

    /**
     * @param  Builder<ServiceRequest>  $query
     * @return Builder<ServiceRequest>
     */
    public static function scope(Builder $query): Builder
    {
        $actor = Auth::user();
        $team = $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;

        return $team === null ? $query->whereRaw('1 = 0') : $query->where('team_id', $team->getKey());
    }

    public static function prepare(ServiceRequest $record, ServiceRequestInputData $input, User $actor): void
    {
        if (! $record->exists) {
            $team = ServiceRequestTeam::forUser($actor);
            abort_if($team === null, 403, 'Select a team with an active membership.');
            $record->team_id = (int) $team->getKey();
            $record->user_id = (string) $actor->getAuthIdentifier();
        }
    }
}
