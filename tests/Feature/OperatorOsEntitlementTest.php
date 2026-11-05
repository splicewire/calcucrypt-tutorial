<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Entitlements\CanMapBuilder;
use Splicewire\Beam\Realm\RealmManifestProjector;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * The entitlement gates on /operator + /os, wired via `DefaultEntitlementResolver`
 * (splicewire/laravel-beam-accounts, ACC-01) — resolved off a Team's realm-grant cascade (an
 * Owner/Admin holding `manage` on a realm's root entry, {@see grantOperatorRealm()}), not `is_staff`
 * (this host never bound its own resolver and the package default has never read that flag). A
 * grantee reaches both surfaces and holds the feature keys; an ungranted user is 403'd and the
 * operator realm is omitted from the manifest.
 */
class OperatorOsEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function grantOperatorRealm(User $user): void
    {
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);
    }

    public function test_a_staff_user_passes_the_operator_and_os_gates(): void
    {
        $staff = User::factory()->create();
        $this->grantOperatorRealm($staff);
        $staff = $staff->fresh();

        // The OS-shell page is a built Vite entry — a granted principal passes the `os.enter` gate and renders.
        $this->actingAs($staff)->get(route('os.shell'))->assertOk();

        // The grantee passes the `os.operate` gate — a passing gate is what this test asserts, so the
        // response is NOT the 403 an ungranted user gets. (The operator Inertia page is not yet a static
        // Vite build input in the starter, a pre-existing bundler gap downstream of and orthogonal to the
        // gate, so a full render may 500 on the manifest lookup — either way the gate let it through.)
        $status = $this->actingAs($staff)->get(route('operator.home'))->baseResponse->getStatusCode();
        $this->assertNotSame(403, $status, 'a granted principal should pass the os.operate gate');
    }

    public function test_a_non_staff_user_is_forbidden_from_operator_and_os(): void
    {
        $user = User::factory()->create(); // no realm grant

        $this->actingAs($user)->get(route('operator.home'))->assertForbidden();
        $this->actingAs($user)->get(route('os.shell'))->assertForbidden();
    }

    public function test_the_can_map_reflects_staff_entitlements(): void
    {
        $staff = User::factory()->create();
        $this->grantOperatorRealm($staff);
        $staff = $staff->fresh();
        $demo = User::factory()->create();

        $staffCan = app(CanMapBuilder::class)->withFeatureKeys(['os.enter'])->forPrincipal($staff);
        $demoCan = app(CanMapBuilder::class)->withFeatureKeys(['os.enter'])->forPrincipal($demo);

        foreach (['os.operate', 'os.enter', 'ux.author'] as $key) {
            $this->assertTrue($staffCan[$key] ?? false, "a granted principal should hold {$key}");
            $this->assertFalse($demoCan[$key] ?? false, "an ungranted principal should not hold {$key}");
        }
    }

    public function test_the_operator_realm_is_hard_gated_out_of_a_non_staff_manifest(): void
    {
        $staff = User::factory()->create();
        $this->grantOperatorRealm($staff);
        $staff = $staff->fresh();
        $demo = User::factory()->create();

        $staffRealms = collect(app(RealmManifestProjector::class)->project($staff))->pluck('key')->all();
        $demoRealms = collect(app(RealmManifestProjector::class)->project($demo))->pluck('key')->all();

        $this->assertContains('operator', $staffRealms);
        $this->assertNotContains('operator', $demoRealms);
    }
}
