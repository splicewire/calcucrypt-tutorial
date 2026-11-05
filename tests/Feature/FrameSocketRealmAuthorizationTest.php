<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * `G3-FRAME-SOCKET-REALM-BLIND` — the host-tier proof that this starter's `config('frame.realms')`
 * is an AUTHORIZATION and not only a projection.
 *
 * The leak was measured on a fresh TOWER (`https://fresh-tower.test`, 2026-09-12) as `demo-member`,
 * an ordinary tenant user already 403'd at `/operator` — but the socket, the middleware and the
 * operator realm list are identical here, so this starter carried the same hole:
 *
 * ```
 * GET /frame/resources/users    → 200  every user row, with its email
 * GET /frame/resources/teams    → 200  every team
 * ```
 *
 * Frame mounts ONE socket for every realm under `frame.middleware` (`['web','auth']` here), so no
 * value of that key could refuse the operator realm's resources without refusing the tenant realm's
 * too. The gate is per-resource and lives in the package —
 * `Splicewire\Beam\Realm\RealmEntitlementResourceGate`, bound over
 * `Schemastud\Frame\Contracts\ResourceAccessGate` — and reads THIS file's realm list plus
 * `beam.core.realm_gates.operator`.
 *
 * The package suite proves the rule; this proves the wiring: that the mount, the middleware, the
 * realm list and the entitlement cascade at this host actually meet.
 *
 * ⚠️ Scope, stated so a green run is not read as more than it is: this asserts the resources this
 * host places in the operator realm. A resource in NO realm is still served to any authenticated
 * principal by design — `members`, `invitations` and `tokens` depend on exactly that, and
 * `G1-BEAM-SCOPE-ISOLATION` is what proves their row-level scope instead.
 */
class FrameSocketRealmAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** The same operator cascade {@see OperatorOsEntitlementTest} grants — a realm grant, not a flag. */
    private function operator(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'Operators', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    /** @return list<string> the operator realm's resource keys, read from config rather than retyped */
    private function operatorResources(): array
    {
        return (array) config('frame.realms.operator', []);
    }

    public function test_the_operator_realms_resources_are_refused_to_an_ordinary_member(): void
    {
        $member = User::factory()->create(); // no realm grant ⇒ os.operate false

        $this->assertNotSame([], $this->operatorResources(), 'the operator realm declares no resources');

        foreach ($this->operatorResources() as $resource) {
            $this->actingAs($member)
                ->getJson("/frame/resources/{$resource}")
                ->assertForbidden();

            // The read verbs are the ones that leaked; `schema` discloses the shape of a surface
            // the member may not reach, and the write gate never covered it.
            $this->actingAs($member)
                ->getJson("/frame/resources/{$resource}/schema")
                ->assertForbidden();

            $this->actingAs($member)
                ->getJson("/frame/resources/{$resource}/records/1")
                ->assertForbidden();
        }
    }

    public function test_an_operator_still_reads_the_operator_realms_resources(): void
    {
        $operator = $this->operator();

        foreach ($this->operatorResources() as $resource) {
            $this->actingAs($operator)
                ->getJson("/frame/resources/{$resource}")
                ->assertOk();
        }
    }

    /**
     * The other half, and the one a too-broad gate would break: the tenant realm declares no gate,
     * so an ordinary member keeps reading its resources exactly as before — scoped by their own
     * row-level scope, which is what `G1-BEAM-SCOPE-ISOLATION` proves separately.
     *
     * A member is `teamMember()`, not a bare `create()`: a bare user belongs to no team, and since the
     * list read asks the bound policy's viewAny (launch security row 51a71469) it is refused the list
     * the way it was always refused a single row.
     */
    public function test_a_tenant_realm_resource_is_unchanged_for_an_ordinary_member(): void
    {
        $member = User::factory()->teamMember()->create();

        $this->actingAs($member)
            ->getJson('/frame/resources/beam-ux-entry')
            ->assertOk();
    }

    public function test_a_user_no_team_admitted_is_refused_the_tenant_realm_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/frame/resources/beam-ux-entry')
            ->assertForbidden();
    }

    /** An anonymous caller is still refused by `frame.middleware`, which this does not replace. */
    public function test_the_socket_still_refuses_a_guest(): void
    {
        $this->getJson('/frame/resources/users')->assertUnauthorized();
    }
}
