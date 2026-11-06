<?php

declare(strict_types=1);

use App\Models\Calculator;
use Database\Seeders\CryptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(CryptSeeder::class);
});

it('exposes the primaryGuide relationship on the calculator', function (): void {
    $profit = Calculator::where('slug', 'crypto-profit')->first();

    expect($profit->primaryGuide)->not->toBeNull()
        ->and($profit->primaryGuide->slug)->toBe('how-crypto-profit-works');
});

it('back-finds every Guide that references a calculator, primary first', function (): void {
    $this->get('/calculators/crypto-profit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calculators/show')
            ->where('primaryGuide.slug', 'how-crypto-profit-works')
            // profit is referenced by its own guide plus the taxes guide
            ->has('guides', 2),
        );
});

it('renders the taxes Guide with its inline embed and a link CTA', function (): void {
    $this->get('/guides/crypto-taxes-end-to-end')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('guides/show')
            ->has('embeds', 1)
            ->where('embeds.0.spec.slug', 'crypto-capital-gains')
            ->where('embeds.0.anchor', 'capital-gains')
            ->has('links', 1), // profit
        );
});
