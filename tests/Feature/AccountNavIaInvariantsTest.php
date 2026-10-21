<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Ia\IaInvariantViolation;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * ux-walkthrough UX-06: the account rail (`accountNav`) runs the IA invariants. Since the realm switcher (UX-12a) is the
 * operator realm's door, the rail carries no Operator seat and no Console; every breach throws, and the old catch-all no
 * longer hides it.
 */
class AccountNavIaInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Artisan::call('splicewire:beam:ux:seed-nav');
    }

    private function staff(): User
    {
        $staff = User::factory()->create();
        $team = Team::create(['user_id' => $staff->id, 'name' => 'Staff', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $staff->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $staff->fresh();
    }

    /** @return list<string> */
    private function accountHrefs(User $user): array
    {
        $hrefs = [];
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(function (Assert $page) use (&$hrefs) {
                $walk = function (array $nodes) use (&$walk, &$hrefs): void {
                    foreach ($nodes as $node) {
                        $hrefs[] = $node['href'] ?? null;
                        $walk($node['children'] ?? []);
                    }
                };
                $walk($page->toArray()['props']['accountNav']['items'] ?? []);
            });

        return $hrefs;
    }

    /*
     * ux-walkthrough IA-2/IA-3, after UX-12a (integrator 07:45Z, finding 4): the realm switcher is the operator realm's
     * one door, so the account rail offers no Operator seat and no Console, even to staff, and crosses no realm.
     */
    public function test_the_account_rail_offers_no_operator_seat_or_console_even_to_staff(): void
    {
        $hrefs = $this->accountHrefs($this->staff());

        $this->assertNotContains('/operator', $hrefs, 'Operator is reached through the realm switcher only (IA-3)');
        $this->assertNotContains('/beam-ux-entry', $hrefs, 'the Console seat is gone (IA-2)');
        $this->assertContains('/dashboard', $hrefs);
    }

    /** The realm keys the shared HostIa payload offers this principal (the realm switcher's source), unlocked only. */
    private function switcherRealms(User $user): array
    {
        $keys = [];
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(function (Assert $page) use (&$keys) {
                foreach ($page->toArray()['props']['realms']['realms'] ?? [] as $realm) {
                    if (! ($realm['locked'] ?? false)) {
                        $keys[] = $realm['key'];
                    }
                }
            });

        return $keys;
    }

    /*
     * review-r1: the rail losing its Operator seat must not leave staff without a door. The realm switcher reads the shared
     * HostIa payload, which offers operator (unlocked) to staff and not to a plain member.
     */
    public function test_staff_keep_the_operator_door_in_the_realm_switcher_and_a_member_has_none(): void
    {
        $this->assertContains('operator', $this->switcherRealms($this->staff()));
        $this->assertNotContains('operator', $this->switcherRealms(User::factory()->teamMember()->create()));
    }

    public function test_a_plain_member_gets_the_rail_without_the_door(): void
    {
        $hrefs = $this->accountHrefs(User::factory()->create());

        $this->assertNotContains('/operator', $hrefs);
        $this->assertContains('/dashboard', $hrefs);
    }

    public function test_teamless_and_joined_users_can_discover_team_creation_from_the_account_rail(): void
    {
        foreach ([User::factory()->create(), User::factory()->teamMember()->create()] as $user) {
            $this->assertContains('/teams/create', $this->accountHrefs($user));
            $this->actingAs($user)->get('/teams/create')->assertOk();
        }
    }

    public function test_guests_cannot_open_or_submit_team_creation(): void
    {
        $this->get('/teams/create')->assertRedirect('/login');
        $this->postJson('/teams/create', ['name' => 'Guest team'])->assertUnauthorized();
        $this->assertDatabaseMissing('beam_teams', ['name' => 'Guest team']);
    }

    public function test_an_account_row_that_joins_no_mounted_route_throws_rather_than_vanishing(): void
    {
        BeamUxEntry::query()->where('slug', 'account-team')->update(['segment' => '/account/no-such-page']);

        $this->withoutExceptionHandling();
        $this->expectException(IaInvariantViolation::class);
        $this->expectExceptionMessageMatches('#I4 tenant /account/no-such-page#');

        $this->actingAs(User::factory()->create())->get(route('dashboard'));
    }

    public function test_in_production_a_breaking_row_is_reported_at_error_level_and_the_page_still_renders(): void
    {
        config(['beam.ux.ia.throw' => false]);
        Log::spy();
        BeamUxEntry::query()->where('slug', 'account-team')->update(['segment' => '/account/no-such-page']);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

        Log::shouldHaveReceived('error')->with(Mockery::on(
            fn (string $message): bool => str_contains($message, 'tenant rail at') && str_contains($message, 'I4 tenant /account/no-such-page'),
        ), Mockery::any())->once();
    }
}
