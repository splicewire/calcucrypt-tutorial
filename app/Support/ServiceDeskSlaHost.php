<?php

namespace App\Support;

use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\ServiceDeskSla\Contracts\ServiceDeskHost;
use Splicewire\ServiceDeskSla\RequestSnapshot;

final class ServiceDeskSlaHost implements ServiceDeskHost
{
    public function selectedTeam(?Authenticatable $actor): ?int
    {
        return $actor instanceof User ? ServiceRequestTeam::forUser($actor)?->getKey() : null;
    }

    public function mayManage(Authenticatable $actor, int $teamId): bool
    {
        $team = $actor instanceof User ? ServiceRequestTeam::forUser($actor) : null;

        return $team !== null && (int) $team->getKey() === $teamId
            && $team->memberships()->where('user_id', $actor->getAuthIdentifier())
                ->whereIn('role', [Role::Owner->value, Role::Admin->value])->exists();
    }

    public function request(string $subjectType, string $subjectId): ?RequestSnapshot
    {
        if ($subjectType !== 'service-request' || ! ctype_digit($subjectId)) {
            return null;
        }
        $request = ServiceRequest::query()->find($subjectId);

        return $request === null || $request->created_at === null ? null : new RequestSnapshot(
            (string) $request->getKey(), (int) $request->team_id, $request->title, CarbonImmutable::instance($request->created_at),
        );
    }
}
