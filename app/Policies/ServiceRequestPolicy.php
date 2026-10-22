<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ServiceRequestTeam;
use Splicewire\Beam\Accounts\Enums\Role;

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

    public function approve(User $user, ServiceRequest $request): bool
    {
        $team = ServiceRequestTeam::forUser($user);

        // The manifest asks on a fresh model; invocation resolves the actual scoped record.
        return $team !== null
            && (! $request->exists || (string) $request->team_id === (string) $team->getKey())
            && $team->memberships()->where('user_id', $user->getKey())
                ->whereIn('role', [Role::Owner->value, Role::Admin->value])->exists();
    }
}
