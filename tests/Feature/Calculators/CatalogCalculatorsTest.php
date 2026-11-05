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
    'profit' => ['crypto-profit', 'profit', ['sellPrice']],
    'converter' => ['crypto-converter', 'converter', ['fromPrice', 'toPrice']],
    'position size' => ['crypto-position-size', 'position-size', ['spot']],
]);

dataset('ungrounded calculators', [
    'capital gains' => ['crypto-capital-gains', 'capital-gains'],
]);

it('renders a grounded calculator with its live facts resolved', function (string $slug, string $computation, array $factNodes): void {
    $this->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(function ($page) use ($slug, $computation, $factNodes) {
            $page->component('calculators/show')
                ->where('spec.slug', $slug)
                ->where('spec.computation', $computation);

            foreach ($factNodes as $node) {
                $page->has("resolution.facts.{$node}")
                    ->where("resolution.facts.{$node}.source", 'Fixture');
            }

            return $page;
        });
})->with('grounded calculators');

it('renders an ungrounded tax calculator that resolves with no live facts', function (string $slug, string $computation): void {
    $this->get("/calculators/{$slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('spec.computation', $computation)
            ->where('resolution.facts', []),
        );
})->with('ungrounded calculators');
