<?php

namespace Tests\Unit;

use App\Account\StarterAccountShell;
use App\Models\User;
use Tests\TestCase;

/**
 * The account shell's profile block printed "1 MEMBERS" (launch ticket 05 item 6): each metric label must agree with its
 * count.
 * Read through shellFor(), the path the account shell renders.
 */
class StarterAccountShellTest extends TestCase
{
    public function test_each_profile_metric_label_agrees_with_its_count(): void
    {
        $profile = (new StarterAccountShell)->shellFor((new User)->forceFill(['email' => 'demo@example.test', 'name' => 'Demo']))->profile;
        $lines = array_map(fn ($m) => "{$m->value} {$m->label}", $profile->metrics);

        $this->assertContains('1 MEMBER', $lines);
        $this->assertContains('0 PROJECTS', $lines);
        $this->assertNotContains('1 MEMBERS', $lines);
    }
}
