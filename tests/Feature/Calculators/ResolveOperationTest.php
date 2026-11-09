<?php

use App\Content\Calculators\CalculatorDefinition;
use App\Content\Calculators\Definitions\ConverterCalculator;
use App\Content\Calculators\Definitions\ProfitCalculator;
use App\Data\CalculatorResolutionData;
use App\Data\ResolveCalculatorInputData;
use App\Enums\CalculatorStatus;
use App\Models\Calculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Splicewire\Beam\Particle\OperationKind;
use Splicewire\Beam\Particle\ParticleOperationRegistry;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

/** A published Calculator built from one of the Crypt's real definitions. */
function publishedCalculator(CalculatorDefinition $definition): Calculator
{
    return Calculator::create([
        'slug' => $definition->slug(),
        'name' => $definition->name(),
        'summary' => $definition->summary(),
        'key' => $definition->key(),
        'status' => CalculatorStatus::Published,
        'published_at' => Carbon::now()->subDay(),
        'graph' => $definition->graph(),
    ]);
}

beforeEach(function (): void {
    // Price refs through a fake connector, never CoinMarketCap.
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
});

it('declares resolve as a read operation on calculators', function (): void {
    $operation = app(ParticleOperationRegistry::class)->get('calculators', 'resolve');

    expect($operation->kind)->toBe(OperationKind::Read)
        ->and($operation->ability)->toBe('resolve')
        ->and($operation->input)->toBe(ResolveCalculatorInputData::class)
        ->and($operation->output)->toBe(CalculatorResolutionData::class)
        ->and(Route::has('api.calculators.resolve'))->toBeTrue();
});

it('resolves a calculator for a guest', function (): void {
    publishedCalculator(new ProfitCalculator);

    $this->postJson('/api/calculators/crypto-profit/resolve', ['inputs' => ['asset' => 'ETH']])
        ->assertOk()
        ->assertJsonPath('data.seedEnv.sellPrice', 2500)
        ->assertJsonPath('data.facts.sellPrice.symbol', 'ETH')
        ->assertJsonPath('data.facts.sellPrice.source', 'FakeSource');
});

it('fills inputs it is not sent from their defaults', function (): void {
    publishedCalculator(new ProfitCalculator);

    $this->postJson('/api/calculators/crypto-profit/resolve')
        ->assertOk()
        ->assertJsonPath('data.facts.sellPrice.symbol', 'BTC');
});

it('resolves for a signed-in reader', function (): void {
    publishedCalculator(new ProfitCalculator);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/calculators/crypto-profit/resolve', ['inputs' => ['asset' => 'SOL']])
        ->assertOk()
        ->assertJsonPath('data.seedEnv.sellPrice', 100);
});

// The tutorial's subset has no collection calculator (Calcucrypt's allocation test covers rows);
// the converter shows one request grounding two refs.
it('resolves every ref a calculator declares', function (): void {
    publishedCalculator(new ConverterCalculator);

    $this->postJson('/api/calculators/crypto-converter/resolve', ['inputs' => ['fromCoin' => 'SOL', 'toCoin' => 'BTC']])
        ->assertOk()
        ->assertJsonPath('data.seedEnv', ['fromPrice' => 100, 'toPrice' => 42000])
        ->assertJsonPath('data.facts.toPrice.symbol', 'BTC');
});

it('rejects a malformed inputs map', function (array $body, string $error): void {
    publishedCalculator(new ProfitCalculator);

    $this->postJson('/api/calculators/crypto-profit/resolve', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'inputs is not a map' => [['inputs' => 'ETH'], 'inputs'],
    'a value is an object' => [['inputs' => ['asset' => ['symbol' => 'ETH']]], 'inputs.asset'],
    'an input the calculator does not have' => [['inputs' => ['leverage' => 10]], 'inputs'],
    'a number that is not numeric' => [['inputs' => ['amount' => 'lots']], 'inputs.amount'],
    'an asset outside the options' => [['inputs' => ['asset' => 'DOGE']], 'inputs.asset'],
]);

it('404s for a draft calculator', function (): void {
    publishedCalculator(new ProfitCalculator)->update(['status' => CalculatorStatus::Draft]);

    $this->postJson('/api/calculators/crypto-profit/resolve')->assertNotFound();
});

it('runs the same resolver as the tool page', function (): void {
    publishedCalculator(new ProfitCalculator);
    $this->withoutVite();

    $api = $this->postJson('/api/calculators/crypto-profit/resolve', ['inputs' => ['asset' => 'ETH']])->json('data');

    $this->get('/calculators/crypto-profit?asset=ETH')
        ->assertInertia(fn ($page) => $page->where('resolution', $api));
});

/*
 * The browser calls `/api/*` with its session cookie. The API is stateless unless the request
 * comes from this site's own pages, so this signs in for real, forgets who is signed in, and then
 * sends only what a browser would: the session cookie and a same-site Referer. A stand-in rule
 * (resolve only for a signed-in reader) shows which caller the policy saw; chapter 3's plans are
 * the real rule.
 */
it('sees the signed-in reader when the browser calls over its session', function (): void {
    publishedCalculator(new ProfitCalculator);
    Gate::before(fn (?User $user, string $ability) => $ability === 'resolve' ? $user !== null : null);
    $reader = User::factory()->create();

    $this->post(route('login.store'), ['email' => $reader->email, 'password' => 'password'])->assertRedirect();
    $session = app('session.store');
    $cookie = [$session->getName() => $session->getId()];

    $resolve = function (array $headers) use ($session, $cookie) {
        Auth::forgetGuards();
        $session->flush();

        return $this->withHeaders($headers)
            ->withCredentials()
            ->withCookies($cookie)
            ->postJson('/api/calculators/crypto-profit/resolve');
    };

    // From the site's own page, the session signs the reader in.
    $resolve(['Referer' => config('app.url').'/calculators/crypto-profit'])->assertOk();

    // From anywhere else, the same cookie is ignored and the caller is a guest.
    $resolve(['Referer' => 'https://elsewhere.example/'])->assertForbidden();

    expect(app('router')->getMiddlewareGroups()['api'])->toContain(EnsureFrontendRequestsAreStateful::class);
});
