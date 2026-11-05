<?php

namespace Tests\Architecture;

use Splicewire\Beam\Testing\AssertsUngatedWrites;
use Tests\TestCase;

/**
 * app-walkthrough APP-08 (APP-11): every write route at this host outside the particle surface and the framework's own doors either declares a gate or is listed
 * here with its owner. The list only shrinks: a new ungated write fails, and so does an entry whose route is now
 * gated or gone. `splicewire:beam:doctor`'s `http.ungated-write` reports the same set.
 */
class UngatedWriteTest extends TestCase
{
    use AssertsUngatedWrites;

    public function test_every_ungated_write_route_is_listed_with_its_owner(): void
    {
        $this->assertUngatedWriteRatchet();
    }

    protected function ungatedWriteRatchet(): array
    {
        return [
            'DELETE beam/accounts/invitations/{id}' => 'gated in the handler: InvitationData::assertManages (team owner or admin), which the audit cannot see; APP-08b: express it as an ability',
            'DELETE beam/accounts/members/{id}' => 'gated in the handler: assertOwner (team owner only), which the audit cannot see; APP-08b: express it as an ability',
            'DELETE beam/accounts/tokens/sessions/others' => 'self-service by design: findOwnToken scopes the write to the caller\'s own tokens',
            'DELETE beam/accounts/tokens/{id}' => 'self-service by design: findOwnToken scopes the write to the caller\'s own tokens',
            'DELETE beam/accounts/tokens/{id}/permanent' => 'self-service by design: findOwnToken scopes the write to the caller\'s own tokens',
            'DELETE settings/profile' => 'self-service by design: the caller\'s own profile and password (the starter kit\'s settings)',
            'PATCH settings/profile' => 'self-service by design: the caller\'s own profile and password (the starter kit\'s settings)',
            'POST beam/accounts/invitations' => 'APP-08b: team invitation; declare the team-manage ability',
            'POST beam/accounts/invitations/{id}/resend' => 'gated in the handler: InvitationData::assertManages (team owner or admin), which the audit cannot see; APP-08b: express it as an ability',
            'POST beam/accounts/tokens' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
            'POST beam/accounts/tokens/{id}/renew' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
            'POST beam/accounts/tokens/{id}/rotate' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
            'PUT beam/accounts/members/{id}/role' => 'gated in the handler: assertOwner (team owner only), which the audit cannot see; APP-08b: express it as an ability',
            'PUT settings/password' => 'self-service by design: the caller\'s own profile and password (the starter kit\'s settings)',
        ];
    }
}
