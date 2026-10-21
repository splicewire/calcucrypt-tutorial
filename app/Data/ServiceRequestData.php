<?php

namespace App\Data;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Splicewire\Beam\Data\BeamData;
use Splicewire\Beam\Particle\Attributes\ParticleResource;

#[ParticleResource(
    key: 'service-requests',
    backing: ServiceRequest::class,
    data: ServiceRequestData::class,
    input: ServiceRequestInputData::class,
    publicRead: null,
)]
class ServiceRequestData extends BeamData
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
    ) {}

    /**
     * @param  Builder<ServiceRequest>  $query
     * @return Builder<ServiceRequest>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->where('user_id', Auth::id());
    }

    public static function prepare(ServiceRequest $record, ServiceRequestInputData $input, User $actor): void
    {
        if (! $record->exists) {
            $record->user_id = (string) $actor->getAuthIdentifier();
        }
    }
}
