<?php

namespace Tests\Support\GuideScheduling;

use App\Content\Guides\GuideCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Realm\Contracts\TenancyContext;
use Splicewire\Beam\Ux\Models\BeamUxRelease;
use Splicewire\Beam\Ux\Release\ReleaseOrigin;
use Splicewire\Beam\Ux\Release\ReleaseService;
use Splicewire\Beam\Workflows\Actions\Contracts\WorkflowActionAuthority;
use Splicewire\Beam\Workflows\Actions\WorkflowActionContext;

/** Uses real UX author/member checks; it is not a permissive test execution gate. */
class ReleaseAuthority implements WorkflowActionAuthority
{
    public function authorize(Model $subject, string $transition, WorkflowActionContext $context): void
    {
        $tenancy = app(TenancyContext::class);
        if ($tenancy->isMultiTenant() || $tenancy->isActive() || $context->tenantToken !== CalendarContext::SCOPE
            || ! $subject instanceof BeamUxRelease || $transition !== 'publish'
            || $subject->getAttribute('origin') !== ReleaseOrigin::Built || $subject->getAttribute('realm') !== 'site') {
            throw new AuthorizationException('This fixture schedules only built central Guide Releases.');
        }
        $service = app(ReleaseService::class);
        $actor = $service->resolveActorToken($context->principal);
        if (! $actor instanceof User) {
            throw new AuthorizationException('The recorded publication actor is unavailable.');
        }
        $members = $subject->members()->get();
        if ($members->isEmpty()) {
            throw new AuthorizationException('An empty Release cannot be scheduled.');
        }
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        try {
            $registrar->setPermissionsTeamId($actor->currentTeamOrPersonal()?->getKey());
            $actor->unsetRelation('roles')->unsetRelation('permissions');
            foreach ($members as $member) {
                $entry = $service->entry($subject, $member->entry_id);
                if ($entry->getAttribute('namespace') !== GuideCatalog::NAMESPACE) {
                    throw new AuthorizationException('Only Guide members are admitted by this fixture.');
                }
                $service->authorizeFileEdit($entry, $actor, restore: true);
            }
        } finally {
            $actor->unsetRelation('roles')->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
