<?php

declare(strict_types=1);

use App\Enums\AssociationMode;
use App\Enums\CalculatorStatus;
use App\Models\Calculator;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Enums\PostStatus;
use Tests\Support\FakeConnector;

uses(Tests\TestCase::class, RefreshDatabase::class);

function seedCalculator(string $slug = 'crypto-profit'): Calculator
{
    return Calculator::create([
        'slug' => $slug,
        'name' => 'Crypto Profit Calculator',
        'summary' => 'Profit vs live price.',
        'computation' => 'profit',
        'status' => CalculatorStatus::Published,
        'published_at' => Carbon::now()->subDay(),
        'graph' => [
            'nodes' => [
                ['id' => 'amount', 'kind' => 'input', 'label' => 'Amount', 'control' => ['type' => 'number', 'default' => 1]],
                ['id' => 'asset', 'kind' => 'input', 'label' => 'Asset', 'control' => ['type' => 'enum', 'default' => 'BTC', 'options' => ['BTC']]],
                ['id' => 'sellPrice', 'kind' => 'ref', 'label' => 'Live price', 'ref' => ['connector' => 'coinmarketcap', 'metric' => 'spot_usd', 'symbolFrom' => 'asset']],
                ['id' => 'value', 'kind' => 'output', 'label' => 'Value', 'expr' => 'amount * sellPrice', 'deps' => ['amount', 'sellPrice']],
            ],
            'outputs' => ['value'],
        ],
    ]);
}

function seedGuide(): Post
{
    return Post::create([
        'slug' => 'how-crypto-profit-works',
        'title' => 'How crypto profit really works',
        'teaser' => 'The formula behind every gain.',
        'status' => PostStatus::Published,
        'published_at' => Carbon::now()->subDay(),
        'body_blocks' => ['seed' => true],
        'body_blocks_html' => '<p>Intro.</p><div data-embed="profit"></div><p>Outro.</p>',
    ]);
}

beforeEach(function (): void {
    config()->set('facts.connectors.coinmarketcap', FakeConnector::class);
    $this->withoutVite();
});

it('renders a guide with an embedded, server-resolved calculator', function (): void {
    $guide = seedGuide();
    $calculator = seedCalculator();
    $guide->calculators()->attach($calculator->id, ['mode' => AssociationMode::Embed->value, 'anchor' => 'profit']);

    $this->get('/guides/how-crypto-profit-works')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('guides/show')
            ->where('guide.slug', 'how-crypto-profit-works')
            ->has('embeds', 1)
            ->where('embeds.0.anchor', 'profit')
            ->where('embeds.0.spec.slug', 'crypto-profit')
            ->where('embeds.0.resolution.facts.sellPrice.source', 'FakeSource')
            ->has('links', 0),
        );
});

it('surfaces a link-mode association as a CTA, not an embed', function (): void {
    $guide = seedGuide();
    $calculator = seedCalculator();
    $guide->calculators()->attach($calculator->id, ['mode' => AssociationMode::Link->value]);

    $this->get('/guides/how-crypto-profit-works')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('embeds', 0)
            ->has('links', 1)
            ->where('links.0.slug', 'crypto-profit'),
        );
});

it('404s for an unpublished guide', function (): void {
    $guide = seedGuide();
    $guide->update(['status' => PostStatus::Draft]);

    $this->get('/guides/how-crypto-profit-works')->assertNotFound();
});

it('lists published guides', function (): void {
    seedGuide();

    $this->get('/guides')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('guides/index')->has('guides', 1));
});
