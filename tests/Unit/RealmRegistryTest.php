<?php

namespace Tests\Unit;

use App\Beam\RealmRegistry;
use Tests\TestCase;

/**
 * Ticket 04 (theme-entries-and-authoring, starter half): `composable` is retired outright — fully
 * subsumed by ADR-0016's per-node opacity overlay. `BEHAVIOR_REALMS`/`isBehaviorRealm()`/
 * `composableByDefault()` are dropped; `auth`/`operator` stay ordinary named realms in
 * {@see RealmRegistry::realms()}, just without the behavior-realm carve-out.
 */
class RealmRegistryTest extends TestCase
{
    public function test_realms_still_lists_auth_and_operator_without_the_behavior_carve_out(): void
    {
        $realms = RealmRegistry::realms();

        $this->assertContains(RealmRegistry::REALM_SITE, $realms);
        $this->assertContains(RealmRegistry::REALM_ACCOUNT, $realms);
        $this->assertContains(RealmRegistry::REALM_AUTH, $realms);
        $this->assertContains(RealmRegistry::REALM_OPERATOR, $realms);
        $this->assertSame(array_values(array_unique($realms)), $realms, 'realms() must not report duplicates');
    }

    public function test_author_ability_naming_is_unaffected_by_the_retirement(): void
    {
        $this->assertSame('ux.auth.author', RealmRegistry::authorAbility('auth'));
        $this->assertSame('ux.operator.author', RealmRegistry::authorAbility('operator'));
    }
}
