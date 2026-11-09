<?php

declare(strict_types=1);

use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CryptSeeder::class);
});

// The tutorial's subset of Calcucrypt's catalogue. Its prices come from the keyless
// FixtureConnector (config/facts.php), so every fact's source reads `Fixture`.
dataset('grounded calculators', [
    'profit' => ['crypto-profit', 'finance.investment-profit', ['sellPrice']],
    'converter' => ['crypto-converter', 'finance.currency-conversion', ['fromPrice', 'toPrice']],
    'position size' => ['crypto-position-size', 'finance.position-size', ['spot']],
]);

dataset('ungrounded calculators', [
    'capital gains' => ['crypto-capital-gains', 'finance.capital-gains'],
]);

it('renders a grounded calculator with its live facts resolved', function (string $slug, string $key, array $factNodes): void {
    $this->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(function ($page) use ($slug, $key, $factNodes) {
            $page->component('calculators/show')
                ->where('spec.slug', $slug)
                ->where('spec.key', $key);

            foreach ($factNodes as $node) {
                $page->has("resolution.facts.{$node}")
                    ->where("resolution.facts.{$node}.source", 'Fixture');
            }

            return $page;
        });
})->with('grounded calculators');

it('renders an ungrounded tax calculator that resolves with no live facts', function (string $slug, string $key): void {
    $this->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.key', $key)
            ->where('resolution.facts', []),
        );
})->with('ungrounded calculators');
