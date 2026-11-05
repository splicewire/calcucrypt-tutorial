<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Invitation;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Notifications\TeamInvitationNotification;
use Tests\TestCase;

/**
 * This host's wiring of `splicewire/laravel-beam-accounts`' team onboarding (`routes/web.php`,
 * `Route::splicewireTeamRoutes()`): a fresh registration here provisions no team, so creating one and
 * accepting an emailed invitation are the only ways onto one. The package's own suite covers the
 * verdicts; this proves THIS host mounts them where a guest can reach the link, with its own User model
 * and its own teamless `CreateNewUser`.
 */
class TeamOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** Registration is closed by default (BUY-05); a new invitee signs up through the registration door until the claim door (BUY-11) lets them in without it. */
    protected ?string $accountRegistration = 'open';

    public function test_the_four_onboarding_routes_are_mounted_by_name()
    {
        foreach (['teams.create', 'teams.store', 'invitations.accept', 'invitations.redeem'] as $name) {
            $this->assertTrue(Route::has($name), "route [{$name}] is not mounted");
        }

        $this->assertSame(['web', 'auth', 'verified'], Route::getRoutes()->getByName('teams.create')->gatherMiddleware());
        $this->assertSame(['web'], Route::getRoutes()->getByName('invitations.accept')->gatherMiddleware());
    }

    public function test_a_registered_user_with_no_team_creates_one_and_owns_it()
    {
        $user = User::factory()->create();
        $this->assertNull($user->currentTeamOrPersonal());

        // `false`: the page ships in @splicewire/beam-inertia, not in this host's resources/js/pages.
        $this->actingAs($user)->get('/teams/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('account/create-team', false)->where('action', '/teams/create'));

        $this->actingAs($user)->post('/teams/create', ['name' => 'Rocket Club'])
            ->assertRedirect(route('account.team'));

        $team = Team::query()->where('name', 'Rocket Club')->sole();
        $this->assertSame(Role::Owner, $team->memberRole($user));
        $this->assertSame((string) $team->getKey(), (string) $user->fresh()->current_team_id);
    }

    public function test_an_invitation_is_emailed_and_a_new_registrant_accepts_it_onto_the_inviting_team()
    {
        Notification::fake();

        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $this->actingAs($owner)->post('/teams/create', ['name' => 'Acme']);
        $team = Team::query()->where('name', 'Acme')->sole();

        // `fresh()`: `actingAs()` keeps the in-memory model, whose `current_team_id` predates the create.
        $this->actingAs($owner->fresh())->postJson('/beam/accounts/invitations', ['email' => 'newbie@example.test', 'role' => 'member'])
            ->assertCreated();

        $link = null;
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function (TeamInvitationNotification $mail) use (&$link) {
            $link = $mail->acceptUrl;

            return true;
        });

        auth()->logout();

        $this->get($link)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/accept-invitation', false)
                ->where('state', 'guest')
                ->where('teamName', 'Acme')
                ->etc());

        $this->post(route('register.store'), [
            'name' => 'New Bie',
            'email' => 'newbie@example.test',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])->assertRedirect($link);

        $invitation = Invitation::query()->where('email', 'newbie@example.test')->sole();

        $this->post(route('invitations.redeem', ['id' => $invitation->token]))
            ->assertRedirect(route('dashboard'));

        $newbie = User::query()->where('email', 'newbie@example.test')->sole();
        $this->assertSame(Role::Member, $team->memberRole($newbie));
        $this->assertSame((string) $team->getKey(), (string) $newbie->current_team_id);
    }
}
