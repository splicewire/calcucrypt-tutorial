<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

// One calculator per plan: free, trader and pro.
const FREE_CALCULATOR = 'crypto-profit';
const TRADER_CALCULATOR = 'crypto-position-size';
const PRO_CALCULATOR = 'crypto-capital-gains';

/** A user working in a team on the given plan, with the given role. Null for a guest. */
function readerOn(?string $plan, Role $role = Role::Member): ?User
{
    if ($plan === null) {
        return null;
    }

    $owner = User::factory()->create();
    $team = app(TeamProvisioner::class)->createTeamFor($owner, ucfirst($plan).' team');
    $team->forceFill(['plan' => $plan])->save();

    if ($role === Role::Owner) {
        return $owner->refresh();
    }

    $member = User::factory()->create();
    app(TeamProvisioner::class)->addMember($member, $team, $role);
    $member->switchTeam($team);

    return $member->refresh();
}

beforeEach(function (): void {
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);

    // The real catalogue: 4 calculators, 2 of them paid, and the Guides that embed them.
    $this->artisan('content:sync')->assertSuccessful();
});

/*
 * The matrix: who may use which calculator. Every reader may read every calculator (`show` is 200),
 * but only a reader whose plan covers it gets its graph and may resolve it.
 */
dataset('readers × calculators', [
    'guest × free' => [null, Role::Member, FREE_CALCULATOR, true],
    'guest × trader' => [null, Role::Member, TRADER_CALCULATOR, false],
    'guest × pro' => [null, Role::Member, PRO_CALCULATOR, false],
    'free Member × free' => ['free', Role::Member, FREE_CALCULATOR, true],
    'free Member × trader' => ['free', Role::Member, TRADER_CALCULATOR, false],
    'free Member × pro' => ['free', Role::Member, PRO_CALCULATOR, false],
    'trader Member × free' => ['trader', Role::Member, FREE_CALCULATOR, true],
    'trader Member × trader' => ['trader', Role::Member, TRADER_CALCULATOR, true],
    'trader Member × pro' => ['trader', Role::Member, PRO_CALCULATOR, false],
    'pro Owner × free' => ['pro', Role::Owner, FREE_CALCULATOR, true],
    'pro Owner × trader' => ['pro', Role::Owner, TRADER_CALCULATOR, true],
    'pro Owner × pro' => ['pro', Role::Owner, PRO_CALCULATOR, true],
]);

it('gates the graph and the resolve op by plan', function (?string $plan, Role $role, string $slug, bool $unlocked): void {
    $reader = readerOn($plan, $role);
    if ($reader !== null) {
        $this->actingAs($reader);
    }

    $show = $this->getJson("/api/calculators/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.locked', ! $unlocked);

    expect($show->json('data.graph') !== null)->toBe($unlocked);

    $this->postJson("/api/calculators/{$slug}/resolve")
        ->assertStatus($unlocked ? 200 : 403);
})->with('readers × calculators');

it('leaves a locked calculator\'s math out of the payload entirely', function (): void {
    $response = $this->getJson('/api/calculators/'.TRADER_CALCULATOR)
        ->assertOk()
        ->assertJsonPath('data.locked', true)
        ->assertJsonPath('data.graph', null)
        ->assertJsonPath('data.requiredPlan', 'trader')
        ->assertJsonMissingPath('data.graph.nodes');

    expect($response->getContent())->not->toContain('"nodes"');
});

it('names the cheapest plan that unlocks each calculator', function (): void {
    $plans = collect($this->getJson('/api/calculators?per_page=50')->assertOk()->json('data'))
        ->pluck('requiredPlan', 'slug');

    expect($plans[FREE_CALCULATOR])->toBeNull()
        ->and($plans[TRADER_CALCULATOR])->toBe('trader')
        ->and($plans[PRO_CALCULATOR])->toBe('pro');
});

it('marks locked calculators on the index and keeps the graph off a locked tool page', function (): void {
    $this->get('/calculators')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/index')
            ->where('calculators', fn ($calculators) => collect($calculators)->where('locked', true)->count() === 2
                && collect($calculators)->firstWhere('slug', TRADER_CALCULATOR)['requiredPlan'] === 'trader'),
        );

    $this->get('/calculators/'.TRADER_CALCULATOR)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.locked', true)
            ->where('spec.graph', null)
            ->where('resolution', null),
        );
});

it('unlocks the tool page for a reader whose plan covers it', function (): void {
    $this->actingAs(readerOn('trader'))
        ->get('/calculators/'.TRADER_CALCULATOR)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spec.locked', false)
            ->has('spec.graph.nodes')
            ->has('resolution.seedEnv')
            ->where('can', fn ($can) => $can['calculators.'.TRADER_CALCULATOR] === true
                && $can['calculators.'.PRO_CALCULATOR] === false),
        );
});

it('renders a paid calculator embedded in a Guide as locked, with no resolution', function (): void {
    $embedOf = fn ($embeds) => collect($embeds)->firstWhere('spec.slug', PRO_CALCULATOR);

    $this->get('/guides/crypto-taxes-end-to-end')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('embeds', fn ($embeds) => $embedOf($embeds)['spec']['locked'] === true
            && $embedOf($embeds)['spec']['graph'] === null
            && $embedOf($embeds)['resolution'] === null));

    $this->actingAs(readerOn('pro', Role::Owner))
        ->get('/guides/crypto-taxes-end-to-end')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('embeds', fn ($embeds) => $embedOf($embeds)['spec']['locked'] === false
            && isset($embedOf($embeds)['resolution']['seedEnv'])));
});

/*
 * The browser calls `/api/*` with its session cookie. The API is stateless unless the request
 * comes from this site's own pages, so this signs in for real, forgets who is signed in, and then
 * sends only what a browser would: the session cookie and a same-site Referer.
 */
it('resolves a paid calculator for a signed-in browser over its session', function (): void {
    $trader = readerOn('trader');

    $this->post(route('login.store'), ['email' => $trader->email, 'password' => 'password'])->assertRedirect();
    $session = app('session.store');
    $cookie = [$session->getName() => $session->getId()];

    $resolve = function (array $headers) use ($session, $cookie) {
        Auth::forgetGuards();
        $session->flush();

        return $this->withHeaders($headers)
            ->withCredentials()
            ->withCookies($cookie)
            ->postJson('/api/calculators/'.TRADER_CALCULATOR.'/resolve');
    };

    // From the site's own page, the session signs the trader in.
    $resolve(['Referer' => config('app.url').'/calculators/'.TRADER_CALCULATOR])->assertOk();

    // From anywhere else, the same cookie is ignored and the caller is a guest.
    $resolve(['Referer' => 'https://elsewhere.example/'])->assertForbidden();

    expect(Route::getRoutes()->getByName('api.calculators.resolve')->gatherMiddleware())->toContain('api')
        ->and(app('router')->getMiddlewareGroups()['api'])->toContain(EnsureFrontendRequestsAreStateful::class);
});
