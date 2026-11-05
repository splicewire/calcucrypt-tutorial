<?php

namespace Tests\Architecture;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Testing\AssertsCommercialSeams;
use Tests\TestCase;

/**
 * The commercial seams at this starter (purchase-walkthrough SPEC §5.1, BUY-01). It passes only when this host's
 * violations equal the ratchet below; every entry names the BUY ticket that removes it, and that ticket deletes the
 * entry in the same commit. Never writes a file.
 */
class CommercialSeamTest extends TestCase
{
    use AssertsCommercialSeams;
    use RefreshDatabase;

    public function test_the_commercial_seams_match_the_ratchet(): void
    {
        $this->assertCommercialSeamRatchet();
    }

    /**
     * Calcucrypt's one money path: a team Owner buys the Trader plan through the PurchasePlan operation. The gate
     * drives it on the fake rail and requires that nothing leaves the process and PurchaseCompleted fires.
     */
    protected function commercialMoneyPaths(): array
    {
        return [
            'plan purchase' => [
                'route' => 'api.plans.purchase',
                'drive' => function (): void {
                    $owner = User::factory()->create();
                    app(TeamProvisioner::class)->createTeamFor($owner, 'Desk');

                    $this->actingAs($owner->refresh())
                        ->postJson('/api/plans/purchase', ['plan' => 'trader'])
                        ->assertOk()
                        ->assertJsonPath('data.paid', true);
                },
            ],
        ];
    }

    protected function commercialSeamRatchet(): array
    {
        // Empty: BUY-05 phase C declared the registration door and deleted the starter's CreateNewUser.
        return [];
    }
}
