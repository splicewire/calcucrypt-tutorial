<?php

declare(strict_types=1);

use App\Calculators\CalculatorResolver;
use App\Facts\Connectors\FixtureConnector;
use App\Models\Calculator;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    // Any attempt to reach a real market API fails the test: the tutorial is keyless.
    Http::preventStrayRequests();
});

it('is the default connector for every ref the calculators declare', function (): void {
    expect(config('facts.connectors.coinmarketcap'))->toBe(FixtureConnector::class);
});

it('prices a symbol deterministically at one fixed instant', function (): void {
    $observations = (new FixtureConnector)->fetch('spot_usd', ['btc', 'ETH', 'NOPE']);

    expect(array_keys($observations))->toBe(['BTC', 'ETH'])
        ->and($observations['BTC']->value)->toBe(61000.0)
        ->and($observations['ETH']->source)->toBe('Fixture')
        ->and($observations['ETH']->asOf->toIso8601ZuluString())->toBe('2026-11-01T00:00:00Z');
});

it('grounds a seeded calculator with no API key and no network', function (): void {
    config(['services.coinmarketcap.key' => null]);
    $this->seed(CryptSeeder::class);

    $resolution = app(CalculatorResolver::class)
        ->resolve(Calculator::query()->where('slug', 'crypto-profit')->firstOrFail(), ['asset' => 'ETH']);

    expect($resolution->seedEnv)->toBe(['sellPrice' => 3000.0])
        ->and($resolution->facts['sellPrice']->source)->toBe('Fixture');
});
