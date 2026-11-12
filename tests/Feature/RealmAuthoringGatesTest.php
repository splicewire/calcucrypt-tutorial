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
 * Package authoring gates preserve per-realm grants; this host only adds beam-docs.publish.
 * A coarse ux.author grant never substitutes for another realm's authoring grant.
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

    public function test_a_site_grant_does_not_grant_other_realms_or_document_publication(): void
    {
        $user = User::factory()->create();
        $this->grantRealmReach($user, ['site']);
        $user = $user->fresh();

        $this->assertTrue($user->can('ux.author'));
        $this->assertTrue($user->can('ux.site.author'));
        foreach (['ux.user.author', 'ux.tenant.author', 'ux.operator.author', 'beam-docs.publish'] as $ability) {
            $this->assertFalse($user->can($ability), $ability);
        }

        $this->actingAs($user)->get(route('account.theme'))->assertOk();
        $this->get('/operator')->assertForbidden();
        $this->getJson('/frame/resources/users')->assertForbidden();
    }

    public function test_the_host_document_publication_alias_follows_the_operator_grant(): void
    {
        $user = User::factory()->create();
        $this->grantRealmReach($user, ['operator']);

        $this->assertTrue($user->fresh()->can('beam-docs.publish'));
    }
}
