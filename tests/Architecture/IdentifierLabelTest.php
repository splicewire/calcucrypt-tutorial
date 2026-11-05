<?php

namespace Tests\Architecture;

use Splicewire\Beam\Testing\AssertsIdentifierLabels;
use Tests\TestCase;

/**
 * app-walkthrough APP-09 (APP-21): every class reachable from a particle input/output at this host declares its
 * rendered label (#[Title], or ProvidesEnumLabel on an enum), or is listed here with its owner. The list only
 * shrinks. `splicewire:beam:doctor`'s `schema.identifier-label` reports the same set.
 */
class IdentifierLabelTest extends TestCase
{
    use AssertsIdentifierLabels;

    public function test_every_reachable_class_declares_its_label_or_is_listed_with_its_owner(): void
    {
        $this->assertIdentifierLabelRatchet();
    }

    protected function identifierLabelRatchet(): array
    {
        return [
            'App\Data\SitemapData' => 'APP-09b: declare #[Title] (host)',
            'Schemastud\Frame\Data\OverviewData' => 'APP-09b: declare #[Title] (schemastud/frame)',
            'Schemastud\Frame\Data\ResourceCapabilitiesData' => 'APP-09b: declare #[Title] (schemastud/frame)',
            'Schemastud\Frame\Data\SummaryFigureData' => 'APP-09b: declare #[Title] (schemastud/frame)',
            'Schemastud\Frame\Data\SummaryResponseData' => 'APP-09b: declare #[Title] (schemastud/frame)',
            'Splicewire\Beam\Accounts\Data\AccessGrantData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\AuthUserData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\CreateInvitationData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\CreateTeamInputData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\InvitationAcceptedData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\InvitationData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\MembershipData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\ProfileUpdateInputData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\TeamData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\TokenData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\UserData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Accounts\Data\ViewRequestData' => 'APP-09b: declare #[Title] (beam-accounts)',
            'Splicewire\Beam\Data\BeamSchemaData' => 'APP-09b: declare #[Title] (laravel-beam)',
            'Splicewire\Beam\Data\BeamSchemaInputData' => 'APP-09b: declare #[Title] (laravel-beam)',
            'Splicewire\Beam\Data\GitRepoData' => 'APP-09b: declare #[Title] (laravel-beam)',
            'Splicewire\Beam\Data\HookData' => 'APP-09b: declare #[Title] (laravel-beam)',
            'Splicewire\Beam\Data\HookInputData' => 'APP-09b: declare #[Title] (laravel-beam)',
            'Splicewire\Beam\Filters\Data\SavedFilterData' => 'APP-09b: declare #[Title] (beam-filters)',
            'Splicewire\Beam\Filters\Data\SavedFilterInputData' => 'APP-09b: declare #[Title] (beam-filters)',
            'Splicewire\Beam\Taxonomy\Data\SiloData' => 'APP-09b: declare #[Title] (beam-taxonomy)',
            'Splicewire\Beam\Taxonomy\Data\SiloInputData' => 'APP-09b: declare #[Title] (beam-taxonomy)',
            'Splicewire\Beam\Taxonomy\Data\TagData' => 'APP-09b: declare #[Title] (beam-taxonomy)',
            'Splicewire\Beam\Ux\Data\BeamUxEntryBodyData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\BeamUxEntryBodyInputData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\DashboardCardRowData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\DashboardWelcomeData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryBodyClearInputData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryDraftInputData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryPublicationData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryPublishInputData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryVersionData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\EntryVersionRestoreInputData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\MirrorStatusRowData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Data\SitemapHealthRowData' => 'APP-09b: declare #[Title] (beam-ux)',
            'Splicewire\Beam\Ux\Type\UxType' => 'APP-09b: declare ProvidesEnumLabel (beam-ux)',
            'Splicewire\Beam\Workflows\Data\WorkflowProjectionData' => 'APP-09b: declare #[Title] (beam-workflows)',
            'Splicewire\Beam\Workflows\Data\WorkflowTransitionAttemptData' => 'APP-09b: declare #[Title] (beam-workflows)',
            'Splicewire\Beam\Workflows\Data\WorkflowTransitionRequestData' => 'APP-09b: declare #[Title] (beam-workflows)',
        ];
    }
}
