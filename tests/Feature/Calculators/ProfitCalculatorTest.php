<?php

declare(strict_types=1);

use App\Calculators\CalculatorResolver;
use App\Enums\CalculatorStatus;
use App\Models\Calculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

function profitCalculator(): Calculator
{
    return Calculator::create([
        'slug' => 'crypto-profit',
        'name' => 'Crypto Profit Calculator',
        'summary' => 'Profit and ROI vs live price.',
        'computation' => 'profit',
        'status' => CalculatorStatus::Published,
        'published_at' => Carbon::now()->subDay(),
        'graph' => [
            'nodes' => [
                ['id' => 'amount', 'kind' => 'input', 'label' => 'Amount', 'control' => ['type' => 'number', 'default' => 0.5]],
                ['id' => 'buyPrice', 'kind' => 'input', 'label' => 'Buy price', 'control' => ['type' => 'number', 'default' => 30000]],
                ['id' => 'asset', 'kind' => 'input', 'label' => 'Asset', 'control' => ['type' => 'enum', 'default' => 'BTC', 'options' => ['BTC', 'ETH', 'SOL']]],
                ['id' => 'sellPrice', 'kind' => 'ref', 'label' => 'Live price', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'asset']],
                ['id' => 'profit', 'kind' => 'output', 'label' => 'Profit', 'expr' => 'amount * sellPrice - amount * buyPrice', 'deps' => ['amount', 'sellPrice', 'buyPrice']],
            ],
            'outputs' => ['profit'],
        ],
    ]);
}

beforeEach(function (): void {
    // Resolve refs through a fake connector — never CoinMarketCap.
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
    // Assert on Inertia props without needing a built Vite manifest.
    $this->withoutVite();
});

it('resolves the default asset ref into a fact with provenance', function (): void {
    $resolution = app(CalculatorResolver::class)->resolve(profitCalculator());

    expect($resolution->seedEnv)->toHaveKey('sellPrice')
        ->and($resolution->seedEnv['sellPrice'])->toBe(42000.0)
        ->and($resolution->facts['sellPrice']->source)->toBe('FakeSource')
        ->and($resolution->facts['sellPrice']->symbol)->toBe('BTC');
});

it('re-resolves a different asset when the asset input changes', function (): void {
    $resolution = app(CalculatorResolver::class)->resolve(profitCalculator(), ['asset' => 'ETH']);

    expect($resolution->seedEnv['sellPrice'])->toBe(2500.0)
        ->and($resolution->facts['sellPrice']->symbol)->toBe('ETH');
});

it('serves the tool page under the site chrome with spec and resolution', function (): void {
    profitCalculator();

    $this->get('/calculators/crypto-profit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.slug', 'crypto-profit')
            ->where('spec.computation', 'profit')
            ->where('resolution.seedEnv.sellPrice', 42000)
            ->where('resolution.facts.sellPrice.source', 'FakeSource'),
        );
});

it('re-resolves via a partial reload query on the show route', function (): void {
    profitCalculator();

    $this->get('/calculators/crypto-profit?asset=SOL')
        ->assertInertia(fn ($page) => $page->where('resolution.seedEnv.sellPrice', 100));
});

it('404s for an unpublished calculator', function (): void {
    $calculator = profitCalculator();
    $calculator->update(['status' => CalculatorStatus::Draft]);

    $this->get('/calculators/crypto-profit')->assertNotFound();
});

it('identifies referenced symbols from the ref input options', function (): void {
    expect(profitCalculator()->referencedSymbols())->toEqualCanonicalizing(['BTC', 'ETH', 'SOL']);
});
