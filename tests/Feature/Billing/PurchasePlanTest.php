<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Rushing\Commerce\Drivers\FakeDriver;
use Rushing\Commerce\Events\PurchaseCompleted;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

/** A user working in a fresh free-plan team, in the given role. */
function buyerWithRole(Role $role): User
{
    $owner = User::factory()->create();
    $team = app(TeamProvisioner::class)->createTeamFor($owner, 'Desk');

    if ($role === Role::Owner) {
        return $owner->refresh();
    }

    $user = User::factory()->create();
    app(TeamProvisioner::class)->addMember($user, $team, $role);
    $user->switchTeam($team);

    return $user->refresh();
}

/** The plan of the team the user works in, read fresh from `beam_teams`. */
function planOf(User $user): string
{
    return (string) Team::query()->findOrFail($user->currentTeamOrPersonal()?->getKey())->plan;
}

beforeEach(function (): void {
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
    $this->artisan('content:sync')->assertSuccessful();
});

afterEach(fn () => FakeDriver::clearFakes());

it('lists the plans on sale to anyone, with the calculators each unlocks', function (): void {
    $plans = collect($this->getJson('/api/plans')->assertOk()->json('data'))->keyBy('slug');

    expect($plans->keys()->all())->toEqualCanonicalizing(['trader', 'pro'])
        ->and($plans['trader']['price'])->toBe(900)
        ->and($plans['trader']['currency'])->toBe('USD')
        ->and($plans['trader']['unlocks'])->toEqualCanonicalizing(config('beam.accounts.entitlements.bundles.trader'))
        ->and($plans['pro']['unlocks'])->toHaveCount(2);
});

it('lists the plans to a signed-in reader too', function (Role $role): void {
    $this->actingAs(buyerWithRole($role))
        ->getJson('/api/plans')
        ->assertOk()
        ->assertJsonCount(2, 'data');
})->with([
    'Owner' => [Role::Owner],
    'Member' => [Role::Member],
]);

it('lets an Owner buy trader, after which position-size resolves', function (): void {
    $owner = buyerWithRole(Role::Owner);

    $this->actingAs($owner)
        ->postJson('/api/calculators/crypto-position-size/resolve')
        ->assertForbidden();

    $this->actingAs($owner)
        ->postJson('/api/plans/purchase', ['plan' => 'trader'])
        ->assertOk()
        ->assertJsonPath('data.plan', 'trader')
        ->assertJsonPath('data.paid', true)
        ->assertJsonPath('data.declineCode', null)
        ->assertJsonPath('data.teamPlan', 'trader');

    expect(planOf($owner))->toBe('trader');

    // The next request, as a fresh load of the same user.
    $this->actingAs($owner->fresh())
        ->postJson('/api/calculators/crypto-position-size/resolve')
        ->assertOk();

    $this->actingAs($owner->fresh())
        ->postJson('/api/calculators/crypto-capital-gains/resolve')
        ->assertForbidden();
});

it('lets an Admin buy for the team too', function (): void {
    $admin = buyerWithRole(Role::Admin);

    $this->actingAs($admin)
        ->postJson('/api/plans/purchase', ['plan' => 'pro'])
        ->assertOk()
        ->assertJsonPath('data.teamPlan', 'pro');

    expect(planOf($admin))->toBe('pro');
});

it('refuses a Member, who uses the team plan but cannot change it', function (): void {
    $member = buyerWithRole(Role::Member);

    $this->actingAs($member)
        ->postJson('/api/plans/purchase', ['plan' => 'trader'])
        ->assertForbidden();

    expect(planOf($member))->toBe('free');
});

it('refuses a guest', function (): void {
    $this->postJson('/api/plans/purchase', ['plan' => 'trader'])->assertForbidden();
});

it('leaves the plan free when the fake rail declines the card', function (): void {
    $owner = buyerWithRole(Role::Owner);
    FakeDriver::fakeDecline();

    $this->actingAs($owner)
        ->postJson('/api/plans/purchase', ['plan' => 'trader'])
        ->assertOk()
        ->assertJsonPath('data.paid', false)
        ->assertJsonPath('data.declineCode', 'card_declined')
        ->assertJsonPath('data.teamPlan', 'free');

    expect(planOf($owner))->toBe('free');

    $this->actingAs($owner->fresh())
        ->postJson('/api/calculators/crypto-position-size/resolve')
        ->assertForbidden();
});

it('answers 422 for a plan that is not on sale', function (): void {
    $this->actingAs(buyerWithRole(Role::Owner))
        ->postJson('/api/plans/purchase', ['plan' => 'free'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan');
});

it('leaves moving the plan to the PurchaseCompleted listener', function (): void {
    $owner = buyerWithRole(Role::Owner);
    $team = $owner->currentTeamOrPersonal();

    // With the event faked no listener runs, so a plan that still moved would mean the op set it.
    Event::fake([PurchaseCompleted::class]);

    $this->actingAs($owner)->postJson('/api/plans/purchase', ['plan' => 'trader'])->assertOk();

    Event::assertDispatched(
        PurchaseCompleted::class,
        fn (PurchaseCompleted $event): bool => $event->purchase->beneficiaryId === (string) $team->getKey()
            && $event->purchase->grant === ['plan' => 'trader'],
    );
    expect(planOf($owner))->toBe('free');
});

it('tells the page who may buy, through the can-map', function (Role $role, bool $mayBuy): void {
    $this->actingAs(buyerWithRole($role))
        ->get('/calculators/crypto-position-size')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can', fn ($can) => $can['plans.purchase'] === $mayBuy));
})->with([
    'Owner' => [Role::Owner, true],
    'Admin' => [Role::Admin, true],
    'Member' => [Role::Member, false],
]);
