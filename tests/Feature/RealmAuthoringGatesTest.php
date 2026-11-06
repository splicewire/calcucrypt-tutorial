<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Entitlements\DefaultEntitlementResolver;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Realm\RealmRegistry;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * Ticket 02 (theme-entries-and-authoring): the `ux.{realm}.author` per-realm authoring gates, ported
 * from `rushing/audiostud`'s `AppServiceProvider::registerRealmAuthoringGates()`. Proves the same shape:
 * a per-realm ability for every packaged registry entry, backward-compatible with the flat
 * `ux.author` ability (a global holder authors every realm).
 *
 * theme-entries-and-authoring: `ux.author`/`ux.{realm}.author` resolve through the realm-grant cascade
 * (an Owner/Admin of a Team holding `manage` on a realm's root entry, {@see grantRealmReach()}) — no
 * more `is_staff` boolean anywhere in this repo's Gate surface.
 */
class RealmAuthoringGatesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function realms(): array
    {
        return array_keys(app(RealmRegistry::class)->all());
    }

    private function authorAbility(string $realm): string
    {
        return "ux.{$realm}.author";
    }

    /** @param list<string> $realms */
    private function grantRealmReach(User $user, array $realms): void
    {
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        $grants = app(AccessGrants::class);

        foreach ($realms as $realm) {
            $grants->share(BeamUxEntry::rootFor($realm), $team, AccessGrant::ABILITY_MANAGE);
        }
    }

    public function test_it_defines_an_author_ux_realm_ability_for_every_known_realm(): void
    {
        $staff = User::factory()->create();
        $this->grantRealmReach($staff, $this->realms());
        $staff = $staff->fresh();

        foreach ($this->realms() as $realm) {
            if (in_array($realm, DefaultEntitlementResolver::SEAT_REALMS, true)) {
                // A read seat (beam-accounts): its grant composes the realm's view entitlement, never authoring.
                $this->assertFalse($staff->can($this->authorAbility($realm)), "{$realm} is a read seat, not authored");

                continue;
            }

            $this->assertTrue($staff->can($this->authorAbility($realm)), "{$realm} is authored by its realm grant");
        }
    }

    public function test_author_ux_is_backward_compatible_a_holder_authors_every_realm(): void
    {
        $staff = User::factory()->create();
        $this->grantRealmReach($staff, $this->realms());
        $staff = $staff->fresh();

        $this->assertTrue($staff->can('ux.author'));
        $this->assertTrue($staff->can('ux.site.author'));
        $this->assertTrue($staff->can('ux.user.author'));
        $this->assertTrue($staff->can('ux.tenant.author'));
        $this->assertTrue($staff->can('ux.operator.author'));
        $this->assertFalse(Gate::has('ux.account.author'));
    }

    public function test_a_non_staff_user_is_denied_every_realm_authoring_ability(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('ux.author'));

        foreach ($this->realms() as $realm) {
            $this->assertFalse($user->can($this->authorAbility($realm)));
        }
    }
}
