<?php

namespace Tests\Support\GuideScheduling;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Splicewire\Beam\Calendars\Actions\ActionContext;
use Splicewire\Beam\Calendars\Contracts\ActionContextProvider;
use Splicewire\Beam\Realm\Contracts\TenancyContext;
use Splicewire\Beam\Ux\Release\ReleaseService;

/** Optional integration host, deliberately limited to this single central test database. */
class CalendarContext implements ActionContextProvider
{
    public const SCOPE = 'tutorial-guide-proof:central';

    public function current(): ActionContext
    {
        $tenancy = app(TenancyContext::class);
        $actor = auth()->user();
        if ($tenancy->isMultiTenant() || $tenancy->isActive() || ! $actor instanceof User) {
            throw new AuthorizationException('A central authenticated Guide author is required.');
        }
        $token = app(ReleaseService::class)->actorTokenFor($actor);

        return new ActionContext($token, $token, self::SCOPE);
    }
}
