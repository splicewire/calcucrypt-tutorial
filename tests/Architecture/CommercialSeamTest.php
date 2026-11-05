<?php

namespace Tests\Architecture;

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

    public function test_the_commercial_seams_match_the_ratchet(): void
    {
        $this->assertCommercialSeamRatchet();
    }

    protected function commercialSeamRatchet(): array
    {
        // Empty: BUY-05 phase C declared the registration door and deleted the starter's CreateNewUser.
        return [];
    }
}
