<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ServiceRequestTeam;

class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return ServiceRequestTeam::forUser($user) !== null;
    }

    public function create(User $user): bool
    {
        return ServiceRequestTeam::forUser($user) !== null;
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        $team = ServiceRequestTeam::forUser($user);

        return $team !== null && (string) $request->team_id === (string) $team->getKey();
    }
}
