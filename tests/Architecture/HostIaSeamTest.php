<?php

namespace Tests\Architecture;

use Splicewire\Beam\Testing\AssertsHostIaSeam;
use Tests\TestCase;

/**
 * The host IA seam at the beam starter (ux-walkthrough SPEC §5.1, UX-01). It passes only when this host's violations
 * equal the ratchet below; every entry names the UX ticket that removes it, and that ticket deletes the entry in the
 * same commit. Never writes.
 */
class HostIaSeamTest extends TestCase
{
    use AssertsHostIaSeam;


    protected function hostIaRatchet(): array
    {
        return [
            'T5 class App\\Beam\\RealmRegistry' => 'UX-12b: App\\Beam\\RealmRegistry is deleted',
            'T5 nav.yml:account-team realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:account-theme realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:account-tokens realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:dashboard realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:operator-dashboard realm=operator' => 'UX-09: operator rows move to resource section / host NavSections (IA-13)',
            'T5 nav.yml:settings-profile realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
        ];
    }

    public function test_the_host_ia_seam_holds_up_to_its_ratchet(): void
    {
        $this->assertHostIaSeamRatchet();
    }
}
