<?php

namespace App\Wallet;

use App\Models\User;
use App\Support\ServiceRequestTeam;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;

final class TeamWalletContext
{
    public function team(mixed $actor): ?Team
    {
        return $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;
    }

    public function mayFund(mixed $actor): bool
    {
        $team = $this->team($actor);

        return $team !== null && $team->memberships()->where('user_id', $actor->getAuthIdentifier())
            ->where('role', Role::Owner->value)->exists();
    }

    public function requireTeam(mixed $actor, bool $fund = false): Team
    {
        $team = $this->team($actor);
        abort_unless($team !== null && (! $fund || $this->mayFund($actor)), 403);

        return $team;
    }

    public function party(Team $team): string
    {
        return 'service-desk-team:'.$team->getKey();
    }
}
