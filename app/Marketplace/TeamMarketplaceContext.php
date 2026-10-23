<?php

namespace App\Marketplace;

use App\Models\User;
use App\Support\ServiceRequestTeam;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;

final class TeamMarketplaceContext
{
    public function team(mixed $actor): ?Team
    {
        return $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;
    }

    public function mayCreate(mixed $actor): bool
    {
        $team = $this->team($actor);

        return $team !== null && $team->memberships()->where('user_id', $actor->getAuthIdentifier())
            ->whereIn('role', [Role::Owner->value, Role::Admin->value])->exists();
    }

    public function mayPurchase(mixed $actor): bool
    {
        return $this->team($actor) !== null && $actor->fresh()->getAllPermissions()
            ->contains('name', 'marketplace.buyer');
    }

    public function mayOperate(mixed $actor): bool
    {
        // This permission admits SITE-WIDE code changes, not a team-local installation.
        // A leftover role on an actor with removed membership is never sufficient.
        return $this->team($actor) !== null && $actor->fresh()->getAllPermissions()
            ->contains('name', 'marketplace.host-operator');
    }
}
