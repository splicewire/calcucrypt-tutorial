<?php

namespace Tests\Feature\Topology;

use Rushing\PackageTopology\Testing\AssertsDeclaredTopology;
use Tests\TestCase;

/**
 * The ONE topology stop-gap. It declares NOTHING itself — the contract is
 * assembled from the `extra.package-topology` blocks of the installed tier
 * packages (rushing/php-package-topology's DeclaredContractSource). A
 * misplaced refactor — a package depending UP, a namespace crossing the vendor
 * seam, a require cycle — fails here. Correcting a rule is a one-line edit in the
 * package that OWNS it; this test follows automatically, no per-repo copy to drift.
 */
class DeclaredTopologyTest extends TestCase
{
    use AssertsDeclaredTopology;

    protected function vendorPath(): string
    {
        return base_path('vendor');
    }

    public function test_the_declared_topology_holds(): void
    {
        $this->assertDeclaredTopologyHolds();
    }
}
