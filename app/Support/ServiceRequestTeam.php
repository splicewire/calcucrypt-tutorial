<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Splicewire\Beam\Accounts\Models\Team;

final class ServiceRequestTeam
{
    public static function forUser(User $user): ?Team
    {
        $selected = $user->getAttribute('current_team_id');
        if ($selected === null) {
            return null;
        }

        // Query membership afresh: a loaded currentTeam or a leftover role is not authority.
        return Team::query()->whereKey($selected)
            ->whereHas('memberships', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->first();
    }
}
